<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Store;
use App\Support\Revenue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The one place that answers "what is revenue".
 *
 * Revenue used to be a hardcoded list of status NAMES living inside the
 * dashboard controller, so a merchant who renamed Canceled silently fell out
 * of the set and their figures moved without warning. It is now a per-status
 * property, and this resolver is the single reader of it.
 *
 * Two rules are pinned here above all others:
 *
 *   1. A status counts as revenue when, and only when, it says it does.
 *   2. An order with NO status NEVER counts, whatever any flag says. Those
 *      rows are ghosts of abandoned checkouts, not orders. That rule lives in
 *      the query layer and is not configurable.
 */
class RevenueResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Store::create(['id' => 1, 'name' => 'Test Store', 'code' => 'test']);
        Carbon::setTestNow(Carbon::parse('2026-08-09 14:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function orderStatus(string $name, bool $countsAsRevenue, int $storeId = 1): OrderStatus
    {
        return OrderStatus::create([
            'store_id' => $storeId,
            'name' => $name,
            'counts_as_revenue' => $countsAsRevenue,
            'sort_order' => 1,
        ]);
    }

    private function order(?OrderStatus $status, float $total, string $at = '2026-08-09 10:00:00'): Order
    {
        $order = Order::create([
            'store_id' => 1,
            'order_status_id' => $status?->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'subtotal' => $total,
            'shipping_cost' => 0,
            'total' => $total,
        ]);

        $order->forceFill(['created_at' => Carbon::parse($at)])->save();

        return $order->fresh();
    }

    // -----------------------------------------------------------------
    // Which statuses count
    // -----------------------------------------------------------------

    public function test_the_id_set_is_exactly_the_statuses_that_declare_themselves_revenue(): void
    {
        $paid = $this->orderStatus('Payment Received', true);
        $shipped = $this->orderStatus('Shipped', true);
        $this->orderStatus('Refunded', false);
        $this->orderStatus('Canceled', false);

        $this->assertEqualsCanonicalizing([$paid->id, $shipped->id], Revenue::statusIds());
    }

    public function test_a_renamed_status_keeps_counting_because_the_flag_travels_with_the_row(): void
    {
        $paid = $this->orderStatus('Payment Received', true);
        $this->order($paid, 500.00);

        $paid->update(['name' => 'Bayad na']);

        $this->assertSame([$paid->id], Revenue::statusIds());
        $this->assertSame(500.0, Revenue::sum(1, Carbon::parse('2026-08-09'), Carbon::parse('2026-08-10')));
    }

    public function test_the_id_set_is_scoped_to_the_store(): void
    {
        Store::create(['id' => 2, 'name' => 'Other Store', 'code' => 'other']);

        $mine = $this->orderStatus('Complete', true, 1);
        $theirs = $this->orderStatus('Complete', true, 2);

        $this->assertSame([$mine->id], Revenue::statusIds(1));
        $this->assertSame([$theirs->id], Revenue::statusIds(2));
    }

    // -----------------------------------------------------------------
    // Caching: one query per request, invalidated on write
    // -----------------------------------------------------------------

    public function test_the_id_set_is_resolved_once_per_request(): void
    {
        $this->orderStatus('Complete', true);
        Revenue::flush();

        DB::enableQueryLog();
        Revenue::statusIds();
        Revenue::statusIds();
        Revenue::statusIds();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(1, $queries, 'The revenue status set must be memoised per request.');
    }

    public function test_saving_a_status_invalidates_the_memo(): void
    {
        $status = $this->orderStatus('Complete', false);
        $this->assertSame([], Revenue::statusIds());

        $status->update(['counts_as_revenue' => true]);
        $this->assertSame([$status->id], Revenue::statusIds());

        $status->update(['counts_as_revenue' => false]);
        $this->assertSame([], Revenue::statusIds());
    }

    public function test_deleting_a_status_invalidates_the_memo(): void
    {
        $status = $this->orderStatus('Complete', true);
        $this->assertSame([$status->id], Revenue::statusIds());

        $status->delete();
        $this->assertSame([], Revenue::statusIds());
    }

    // -----------------------------------------------------------------
    // Summing a period
    // -----------------------------------------------------------------

    public function test_sum_adds_only_the_orders_whose_status_counts(): void
    {
        $paid = $this->orderStatus('Payment Received', true);
        $void = $this->orderStatus('Refunded', false);

        $this->order($paid, 400.00);
        $this->order($paid, 100.50);
        $this->order($void, 999.00);

        $this->assertSame(500.5, Revenue::sum(1, Carbon::parse('2026-08-09'), Carbon::parse('2026-08-10')));
    }

    public function test_sum_is_half_open_so_adjacent_periods_never_double_count(): void
    {
        $paid = $this->orderStatus('Payment Received', true);

        $this->order($paid, 10.00, '2026-08-08 23:59:59');
        $this->order($paid, 20.00, '2026-08-09 00:00:00');
        $this->order($paid, 40.00, '2026-08-09 23:59:59');
        $this->order($paid, 80.00, '2026-08-10 00:00:00');

        $this->assertSame(60.0, Revenue::sum(1, Carbon::parse('2026-08-09'), Carbon::parse('2026-08-10')));
    }

    public function test_sum_is_scoped_to_the_store(): void
    {
        Store::create(['id' => 2, 'name' => 'Other Store', 'code' => 'other']);

        $paid = $this->orderStatus('Complete', true, 1);
        $this->order($paid, 700.00);

        $this->assertSame(700.0, Revenue::sum(1, Carbon::parse('2026-08-09'), Carbon::parse('2026-08-10')));
        $this->assertSame(0.0, Revenue::sum(2, Carbon::parse('2026-08-09'), Carbon::parse('2026-08-10')));
    }

    public function test_with_no_status_counting_revenue_is_zero_rather_than_everything(): void
    {
        $void = $this->orderStatus('Refunded', false);
        $this->order($void, 999.00);

        // The old name-list model fell back to "count everything" when it
        // recognised no cancelled names. Under a declared flag, nothing
        // declared means nothing counts, which is the honest reading.
        $this->assertSame([], Revenue::statusIds());
        $this->assertSame(0.0, Revenue::sum(1, Carbon::parse('2026-08-09'), Carbon::parse('2026-08-10')));
    }

    // -----------------------------------------------------------------
    // THE HARD RULE: no status, no revenue, ever
    // -----------------------------------------------------------------

    /**
     * Every flag combination a store can be in: none counting, some counting,
     * all counting. A ghost row is excluded in all three, because it is
     * excluded by having no status at all rather than by any flag.
     */
    public static function flagCombinations(): array
    {
        return [
            'no status counts'   => [[false, false]],
            'some statuses count' => [[true, false]],
            'every status counts' => [[true, true]],
        ];
    }

    #[DataProvider('flagCombinations')]
    public function test_orders_with_no_status_never_count_as_revenue(array $flags): void
    {
        $statuses = [];
        foreach ($flags as $i => $counts) {
            $statuses[] = $this->orderStatus('Status ' . $i, $counts);
        }

        foreach ($statuses as $status) {
            $this->order($status, 100.00);
        }

        $ghost = $this->order(null, 5000.00);
        $this->assertNull($ghost->order_status_id, 'The fixture must actually be status-less.');

        $expected = 100.0 * count(array_filter($flags));

        $this->assertSame(
            $expected,
            Revenue::sum(1, Carbon::parse('2026-08-09'), Carbon::parse('2026-08-10')),
            'A status-less ghost order leaked into revenue.'
        );

        // And through the query-level API, not only the convenience sum.
        $this->assertSame(
            $expected,
            (float) Revenue::applyTo(Order::query()->where('store_id', 1))->sum('total')
        );

        // And through the raw predicate the aggregate-shaped callers use.
        $taken = (float) DB::table('orders')
            ->where('store_id', 1)
            ->selectRaw('COALESCE(SUM(CASE WHEN ' . Revenue::condition() . ' THEN total ELSE 0 END), 0) AS taken')
            ->value('taken');

        $this->assertSame($expected, $taken);
    }

    /**
     * A ghost row cannot be rescued by pointing the flag at it, because there
     * is no flag to point: the rule is structural, in the query, not a
     * setting a merchant can talk themselves into.
     */
    public function test_the_null_status_rule_is_not_configurable(): void
    {
        $this->orderStatus('Complete', true);
        $this->order(null, 5000.00);

        $this->assertSame(0.0, Revenue::sum(1, Carbon::parse('2026-08-09'), Carbon::parse('2026-08-10')));

        // Even asserted against the naive reading a NOT IN formulation would
        // have produced, which is what this rule exists to prevent.
        $this->assertNotSame(
            5000.0,
            Revenue::sum(1, Carbon::parse('2026-08-09'), Carbon::parse('2026-08-10'))
        );
    }

    // -----------------------------------------------------------------
    // One implementation, not two
    // -----------------------------------------------------------------

    /**
     * The defect this feature exists to remove: a second copy of "which
     * statuses are money" living somewhere else. Nothing outside the resolver
     * may name a revenue status, build its own NOT IN over statuses, or carry
     * a list of status names for this purpose.
     */
    public function test_no_second_implementation_of_the_revenue_rule_exists(): void
    {
        $roots = [base_path('app'), base_path('extensions')];
        $resolver = realpath(base_path('app/Support/Revenue.php'));

        $offenders = [];

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php' || realpath($file->getPathname()) === $resolver) {
                    continue;
                }

                $body = file_get_contents($file->getPathname());

                // The retired name list, in any spelling.
                if (preg_match('/NON_REVENUE_STATUS_NAMES|cancelledStatusIds|nonRevenueStatusIds/i', $body)) {
                    $offenders[] = $file->getPathname() . ' carries a private revenue status list.';
                }

                // A private SQL predicate over order_status_id built for money.
                if (preg_match('/order_status_id\s+NOT\s+IN/i', $body)) {
                    $offenders[] = $file->getPathname() . ' builds its own order_status_id NOT IN predicate.';
                }

                // A private read of the column: the flag has exactly one reader.
                if (str_contains($body, 'counts_as_revenue')
                    && ! str_contains($body, 'OrderStatusController')
                    && ! str_contains($body, 'class OrderStatus ')
                    && basename($file->getPathname()) !== 'OrderStatus.php'
                    && basename($file->getPathname()) !== 'OrderStatusController.php') {
                    $offenders[] = $file->getPathname() . ' reads counts_as_revenue directly instead of through Revenue.';
                }
            }
        }

        $this->assertSame([], $offenders, implode("\n", $offenders));
    }
}
