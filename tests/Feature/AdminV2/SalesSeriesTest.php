<?php

namespace Tests\Feature\AdminV2;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The sales plot's seven periods: what each range promises about its own
 * resolution, its span, and its year-earlier overlay, asserted against the
 * series endpoint the picker fetches from and against the ?range= links
 * the page answers without JavaScript.
 *
 * The promises, per range:
 *
 *   30d, 90d           daily points, the same days a year earlier beneath
 *   This/Last Month    daily points, the same month a year earlier beneath
 *   This Year, 12m     monthly points, the year before beneath
 *   All Time           monthly points over the store's whole history, and
 *                      NO overlay, because the whole history has no
 *                      earlier self
 */
class SalesSeriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-08-09 14:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create();
    }

    private function revenueStatus(): OrderStatus
    {
        return OrderStatus::firstOrCreate(
            ['store_id' => 1, 'name' => 'Payment Received'],
            ['sort_order' => 1, 'counts_as_revenue' => true]
        );
    }

    private function order(float $total, string $at): Order
    {
        $order = Order::create([
            'store_id' => 1,
            'order_status_id' => $this->revenueStatus()->id,
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

    private function series(string $range): array
    {
        return $this->get(route('admin.dashboard.sales-series', ['range' => $range]))
            ->assertOk()
            ->json();
    }

    // ------------------------------------------------------------------
    // The endpoint is the dashboard's own door
    // ------------------------------------------------------------------

    public function test_the_endpoint_requires_the_admin_session(): void
    {
        $this->get(route('admin.dashboard.sales-series', ['range' => '30d']))
            ->assertRedirect(route('login'));
    }

    public function test_a_range_the_picker_does_not_offer_is_not_a_series(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('admin.dashboard.sales-series', ['range' => 'fortnight']))->assertNotFound();
        $this->get(route('admin.dashboard.sales-series'))->assertNotFound();
    }

    // ------------------------------------------------------------------
    // Granularity and span, per range
    // ------------------------------------------------------------------

    public function test_thirty_days_is_thirty_daily_points_with_the_same_days_a_year_earlier(): void
    {
        $this->actingAs($this->admin());
        $this->order(500.00, '2026-08-09 10:00:00');
        $this->order(120.00, '2026-07-20 10:00:00');
        $this->order(80.00, '2025-07-20 10:00:00');

        $p = $this->series('30d');

        $this->assertSame('day', $p['granularity']);
        $this->assertCount(30, $p['x']);
        $this->assertCount(30, $p['full']);
        $this->assertTrue($p['hasEarlier']);
        $this->assertSame('11 July 2026', $p['full'][0]);
        $this->assertSame('9 August 2026', $p['full'][29]);
        $this->assertSame('11 July 2025', $p['earlierLabels'][0]);

        // The window's own trade lands on its own day, and last year's
        // same day sits beneath this year's.
        $this->assertStringContainsString('500.00', $p['currentFmt'][29]);
        $this->assertStringContainsString('120.00', $p['currentFmt'][9]);
        $this->assertStringContainsString('80.00', $p['earlierFmt'][9]);
    }

    public function test_the_open_windows_final_overlay_point_is_cut_to_the_same_hour(): void
    {
        $this->actingAs($this->admin());
        // Last year's same day: one order before 14:00, one after. Only the
        // first is inside the like-for-like span.
        $this->order(100.00, '2025-08-09 10:00:00');
        $this->order(999.00, '2025-08-09 18:00:00');

        $p = $this->series('30d');

        $this->assertStringContainsString('100.00', $p['earlierFmt'][29]);
        $this->assertStringNotContainsString('1,099.00', $p['earlierFmt'][29]);
    }

    public function test_ninety_days_is_ninety_daily_points_and_the_axis_is_thinned(): void
    {
        $this->actingAs($this->admin());
        $this->order(50.00, '2026-08-01 10:00:00');

        $p = $this->series('90d');

        $this->assertSame('day', $p['granularity']);
        $this->assertCount(90, $p['x']);
        $this->assertTrue($p['hasEarlier']);
        $this->assertLessThanOrEqual(8, count($p['axis']));
        $this->assertSame('12 May', $p['axis'][0]);
        $this->assertSame('9 Aug', end($p['axis']));
    }

    public function test_this_month_is_the_month_so_far_day_by_day_against_the_same_month_last_year(): void
    {
        $this->actingAs($this->admin());
        $this->order(300.00, '2026-08-03 10:00:00');
        $this->order(70.00, '2025-08-03 10:00:00');

        $p = $this->series('this-month');

        $this->assertSame('day', $p['granularity']);
        $this->assertCount(9, $p['x']);
        $this->assertSame('August 2026', $p['title']);
        $this->assertSame('August 2025', $p['thenName']);
        $this->assertStringContainsString('300.00', $p['currentFmt'][2]);
        $this->assertStringContainsString('70.00', $p['earlierFmt'][2]);
        $this->assertSame('Today so far', $p['nowRead']['label']);
    }

    public function test_last_month_is_the_whole_month_against_the_same_month_last_year_with_no_cut(): void
    {
        $this->actingAs($this->admin());
        $this->order(400.00, '2026-07-15 10:00:00');
        $this->order(90.00, '2025-07-31 23:00:00');

        $p = $this->series('last-month');

        $this->assertSame('day', $p['granularity']);
        $this->assertCount(31, $p['x']);
        $this->assertSame('July 2026', $p['title']);
        $this->assertSame('July 2025', $p['thenName']);
        // A closed window compares two complete spans: the late-evening
        // order on last year's final day is not cut away.
        $this->assertStringContainsString('90.00', $p['earlierFmt'][30]);
        // The reading is the completed month's own total, never "so far".
        $this->assertSame('July 2026', $p['nowRead']['label']);
        $this->assertStringContainsString('400.00', $p['nowRead']['value']);
    }

    public function test_this_year_is_january_to_now_month_by_month_against_last_year(): void
    {
        $this->actingAs($this->admin());
        $this->order(1000.00, '2026-02-10 10:00:00');
        $this->order(250.00, '2025-02-10 10:00:00');

        $p = $this->series('this-year');

        $this->assertSame('month', $p['granularity']);
        $this->assertCount(8, $p['x']);
        $this->assertSame('January 2026', $p['full'][0]);
        $this->assertSame('August 2026', $p['full'][7]);
        $this->assertTrue($p['hasEarlier']);
        $this->assertStringContainsString('1,000.00', $p['currentFmt'][1]);
        $this->assertStringContainsString('250.00', $p['earlierFmt'][1]);
    }

    public function test_twelve_months_keeps_its_shape(): void
    {
        $this->actingAs($this->admin());
        $this->order(700.00, '2025-12-05 10:00:00');

        $p = $this->series('12m');

        $this->assertSame('month', $p['granularity']);
        $this->assertCount(12, $p['x']);
        $this->assertSame('September 2025', $p['full'][0]);
        $this->assertSame('August 2026', $p['full'][11]);
        $this->assertTrue($p['hasEarlier']);
        $this->assertStringContainsString('700.00', $p['currentFmt'][3]);
    }

    public function test_all_time_spans_the_stores_whole_history_and_has_no_overlay(): void
    {
        $this->actingAs($this->admin());
        $this->order(100.00, '2024-11-15 10:00:00');
        $this->order(200.00, '2026-08-01 10:00:00');

        $p = $this->series('all');

        $this->assertSame('month', $p['granularity']);
        // November 2024 through August 2026 inclusive.
        $this->assertCount(22, $p['x']);
        $this->assertSame('November 2024', $p['full'][0]);
        $this->assertSame('August 2026', $p['full'][21]);
        $this->assertFalse($p['hasEarlier']);
        $this->assertNull($p['earlierFmt']);
        $this->assertNull($p['yEarlier']);
        $this->assertSame('', $p['dThen']);
        $this->assertStringContainsString('100.00', $p['currentFmt'][0]);
        $this->assertStringContainsString('200.00', $p['currentFmt'][21]);
    }

    // ------------------------------------------------------------------
    // The page answers every range without JavaScript
    // ------------------------------------------------------------------

    public function test_the_range_links_render_each_period_server_side(): void
    {
        $this->actingAs($this->admin());
        $this->order(100.00, '2024-11-15 10:00:00');

        $thirty = $this->get(route('admin.dashboard', ['range' => '30d']))->assertOk();
        $this->assertSame('day', $thirty->viewData('plot')['granularity']);
        $this->assertCount(30, $thirty->viewData('plot')['current']);
        $this->assertSame('30d', $thirty->viewData('salesRange'));
        $thirty->assertSee('Last 30 days');

        $all = $this->get(route('admin.dashboard', ['range' => 'all']))->assertOk();
        $this->assertSame('all', $all->viewData('salesRange'));
        $this->assertFalse($all->viewData('plot')['hasEarlier']);
        $all->assertSee('All time');
    }

    public function test_the_default_and_any_unknown_range_are_the_twelve_months(): void
    {
        $this->actingAs($this->admin());
        $this->order(100.00, '2024-11-15 10:00:00');

        $plain = $this->get(route('admin.dashboard'))->assertOk();
        $this->assertSame('12m', $plain->viewData('salesRange'));
        $this->assertSame('month', $plain->viewData('plot')['granularity']);
        $plain->assertSee('Last 12 months')->assertSee('Sales');

        $odd = $this->get(route('admin.dashboard', ['range' => 'nonsense']))->assertOk();
        $this->assertSame('12m', $odd->viewData('salesRange'));
    }

    public function test_the_picker_offers_the_seven_periods_in_order_as_real_links(): void
    {
        $this->actingAs($this->admin());
        $this->order(100.00, '2024-11-15 10:00:00');

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['30d', '90d', 'This Month', 'Last Month', 'This Year', '12m', 'All Time'])
            ->assertSee('range=this-month', false)
            ->assertSee('range=all', false)
            ->assertSee('aria-current="page"', false);
    }
}
