<?php

namespace Tests\Feature\AdminV2;

use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\ExtensionManager;
use Extensions\CourierFulfillment\PlacementMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The dashboard is the day sheet: today's page in the shop's daybook.
 *
 * What is asserted here is what the sheet PROMISES, in the order it makes
 * the promises: the date is the heading, the standing sentence is composed
 * from real figures, the day's figures carry their two comparisons, the
 * work lines come from the fulfillment module's own placement mapping and
 * disappear with it, and a quiet day fills the ledger with the most recent
 * orders rather than answering the morning with a blank line.
 */
class DaySheetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // A fixed clock: the sheet's whole content is "today", "yesterday"
        // and "the same weekday last week", none of which can be asserted
        // against a clock that moves under the test.
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

    /**
     * Revenue is a declared property of a status now (owner ruling,
     * 2026-08-09), not a name the sheet recognises, so a fixture that wants
     * its orders to be money has to say so - exactly as a merchant does on
     * the Order Statuses page.
     */
    private function orderStatus(string $name, bool $countsAsRevenue = false): OrderStatus
    {
        return OrderStatus::firstOrCreate(
            ['store_id' => 1, 'name' => $name],
            ['sort_order' => 1, 'counts_as_revenue' => $countsAsRevenue]
        );
    }

    private function order(OrderStatus $status, float $total, string $at, string $first = 'Jane'): Order
    {
        $order = Order::create([
            'store_id' => 1,
            'order_status_id' => $status->id,
            'first_name' => $first,
            'last_name' => 'Doe',
            'email' => strtolower($first) . '@example.com',
            'subtotal' => $total,
            'shipping_cost' => 0,
            'total' => $total,
        ]);

        // created_at is set by the model; the sheet's windows are the whole
        // point of these tests, so it is stamped explicitly.
        $order->forceFill(['created_at' => Carbon::parse($at)])->save();

        return $order->fresh();
    }

    /**
     * One order well in the past, so the store counts as having traded.
     *
     * The sheet asks that question first and answers a store with no trade
     * at all with a different composition entirely, so any test about a
     * QUIET day has to say that the shop is open. November 2024 is inside
     * the plot's two year window and outside every span the pulse compares,
     * so it makes a store trade without moving a figure under a test.
     */
    private function tradedBefore(float $total = 100.00): Order
    {
        return $this->order($this->orderStatus('Payment Received', true), $total, '2024-11-15 10:00:00', 'Historic');
    }

    private function lineItem(Order $order, Product $product, int $quantity, float $total): void
    {
        DB::table('order_products')->insert([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'quantity' => $quantity,
            'price' => $quantity > 0 ? $total / $quantity : $total,
            'total' => $total,
        ]);
    }

    private function paidWith(Order $order, string $method, string $title): Order
    {
        $order->forceFill(['payment_method' => $method, 'payment_method_title' => $title])->save();

        return $order->fresh();
    }

    /** The pulse band for the month, and the one for today. */
    private function band(array $bands, string $key): array
    {
        return collect($bands)->firstWhere('key', $key);
    }

    private function row(array $band, string $label): array
    {
        return collect($band['rows'])->firstWhere('label', $label);
    }

    private function customerIn(CustomerGroup $group, string $email): Customer
    {
        return Customer::create([
            'store_id' => 1,
            'customer_group_id' => $group->id,
            'first_name' => 'Buyer',
            'last_name' => 'One',
            'email' => $email,
            'password' => 'secret-secret',
        ]);
    }

    private function installFulfillment(): void
    {
        app(ExtensionManager::class)->install('courier-fulfillment');
        app(ExtensionManager::class)->bootEnabled();
    }

    private function placedStatus(string $placement, string $name): OrderStatus
    {
        $status = $this->orderStatus($name);
        PlacementMap::put($status->id, $placement);

        return $status;
    }

    // ---------------------------------------------------------------
    // The head
    // ---------------------------------------------------------------

    public function test_the_heading_is_the_date_not_the_word_dashboard(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Sunday, 9 August 2026')
            ->assertDontSee('<h1 class="v-sheet__date" id="v-sheet-date">Dashboard', false);
    }

    public function test_the_standing_sentence_is_composed_from_the_days_real_figures(): void
    {
        $this->actingAs($this->admin());
        $paid = $this->orderStatus('Payment Received', true);

        $this->order($paid, 1500.00, '2026-08-09 09:15:00');
        $this->order($paid, 500.50, '2026-08-09 11:40:00');

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('2 orders so far, 2,000.50 taken.');
    }

    public function test_a_day_with_no_orders_says_so_in_the_standing_sentence(): void
    {
        $this->actingAs($this->admin());
        $this->tradedBefore();

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('No orders yet today.');
    }

    // ---------------------------------------------------------------
    // The pulse: standing, which is what the sheet now leads with
    // ---------------------------------------------------------------

    /**
     * The headline comparison, and the reason it is year on year: retail is
     * seasonal, so this month against last month says more about the
     * calendar than about the business.
     *
     * The reference is cut to the same day of the month, which is the whole
     * point. An order placed on the 20th of last August is real trade and is
     * NOT in this comparison, because the 20th has not happened yet this
     * month and counting it would report a collapse every first of the month
     * that recovered by the thirty-first.
     */
    public function test_the_pulse_sets_this_month_against_the_same_span_last_year(): void
    {
        $this->actingAs($this->admin());
        $paid = $this->orderStatus('Payment Received', true);

        $this->order($paid, 300.00, '2026-08-05 10:00:00');
        $this->order($paid, 200.00, '2026-08-09 10:00:00');
        $this->order($paid, 400.00, '2025-08-03 10:00:00');
        // Same month a year ago, but past the day this month has reached.
        $this->order($paid, 9000.00, '2025-08-20 10:00:00');

        $response = $this->get(route('admin.dashboard'))->assertOk();
        $month = $this->band($response->viewData('bands'), 'month');

        $revenue = $this->row($month, 'Revenue taken');
        $this->assertSame(500.0, $revenue['values']['now']);
        $this->assertSame(400.0, $revenue['values']['ref']);
        $this->assertSame('25.0% ahead', $revenue['change']['words']);

        $placed = $this->row($month, 'Orders placed');
        $this->assertSame(2, $placed['values']['now']);
        $this->assertSame(1, $placed['values']['ref']);

        // Both sides name the span they cover, so a reader can see that the
        // comparison is like for like rather than take it on trust.
        $response->assertSee('August to the 9th')->assertSee('Aug 2025 to the 9th');
    }

    public function test_a_store_with_no_trade_a_year_back_says_so_rather_than_reading_as_a_collapse(): void
    {
        $this->actingAs($this->admin());
        $paid = $this->orderStatus('Payment Received', true);
        $this->order($paid, 750.00, '2026-08-09 10:00:00');

        $response = $this->get(route('admin.dashboard'))->assertOk();

        $this->assertTrue($this->band($response->viewData('bands'), 'month')['referenceMissing']);
        $response->assertSee('No record')->assertSee('Nothing to compare');
    }

    /**
     * Today's reference is what this weekday usually looks like, taken as
     * the MIDDLE of the last eight rather than their mean, so one
     * exceptional Sunday cannot redefine a usual one.
     *
     * The comparison used to be a second three-row table under the month
     * table, repeating its row labels; it now lives on the band blocks'
     * own notes and in the one usual-weekday sentence, so that is where
     * it is asserted.
     */
    public function test_today_is_set_against_the_middle_of_the_last_eight_same_weekdays(): void
    {
        $this->actingAs($this->admin());
        $paid = $this->orderStatus('Payment Received', true);

        foreach (['2026-08-02', '2026-07-26', '2026-07-19', '2026-07-12'] as $quiet) {
            $this->order($paid, 100.00, $quiet . ' 10:00:00');
        }

        foreach (['2026-07-05', '2026-06-28', '2026-06-21', '2026-06-14'] as $busy) {
            $this->order($paid, 500.00, $busy . ' 10:00:00');
        }

        $this->order($paid, 600.00, '2026-08-09 10:00:00');

        $response = $this->get(route('admin.dashboard'))->assertOk();
        $pulse = $response->viewData('pulse');

        $this->assertSame(600.0, $pulse['today']['revenue']);
        $this->assertSame(300.0, $pulse['usual']['revenue']);

        // The band block's note carries the comparison as a phrase, and the
        // sentence under the month table carries the average.
        $response->assertSee('100.0% ahead of a usual Sunday')
            ->assertSee('Average order today')
            ->assertSee('on a usual Sunday.');
    }

    /**
     * A day nobody bought anything on is still a day, and it is a sample.
     * Reading only the Sundays that happen to carry an order would turn "a
     * usual Sunday" into "a usual Sunday on which something sold".
     */
    public function test_a_weekday_with_no_orders_counts_as_a_sample_of_nothing(): void
    {
        $this->actingAs($this->admin());
        $paid = $this->orderStatus('Payment Received', true);

        // Two Sundays out of eight carried trade; the middle of the eight is
        // therefore zero, not the average of the two that did.
        $this->order($paid, 800.00, '2026-08-02 10:00:00');
        $this->order($paid, 800.00, '2026-07-26 10:00:00');

        $usual = $this->get(route('admin.dashboard'))->assertOk()->viewData('pulse')['usual'];

        $this->assertSame(0.0, $usual['revenue']);
    }

    public function test_cancelled_orders_are_counted_as_placed_but_not_as_revenue(): void
    {
        $this->actingAs($this->admin());
        $paid = $this->orderStatus('Payment Received', true);
        $void = $this->orderStatus('Refunded');

        $this->order($paid, 400.00, '2026-08-09 09:00:00');
        $this->order($void, 999.00, '2026-08-09 10:00:00');

        $today = $this->get(route('admin.dashboard'))->assertOk()->viewData('pulse')['today'];

        $this->assertSame(400.0, $today['revenue']);
        $this->assertSame(2, $today['placed']);
        // The average divides revenue by the orders it actually came from,
        // so one refund does not halve it.
        $this->assertSame(400.0, $today['average']);
    }

    /**
     * The sheet asks the status, not the dictionary. Revenue used to be a
     * hardcoded list of names on the controller, so renaming Canceled moved
     * the figure without warning; the flag travels with the row instead.
     */
    public function test_revenue_follows_the_declared_flag_and_not_the_status_name(): void
    {
        $this->actingAs($this->admin());

        // Named like money, declares it is not.
        $complete = $this->orderStatus('Complete', false);
        // Named like a write-off, declares it is money (the merchant's call).
        $odd = $this->orderStatus('Canceled', true);

        $this->order($complete, 700.00, '2026-08-09 09:00:00');
        $this->order($odd, 300.00, '2026-08-09 10:00:00');

        $today = $this->get(route('admin.dashboard'))->assertOk()->viewData('pulse')['today'];

        $this->assertSame(300.0, $today['revenue']);
        $this->assertSame(2, $today['placed']);
    }

    /** Renaming a status does not move a figure: the flag is on the row. */
    public function test_renaming_a_status_leaves_the_days_revenue_untouched(): void
    {
        $this->actingAs($this->admin());
        $paid = $this->orderStatus('Payment Received', true);
        $this->order($paid, 1234.00, '2026-08-09 09:00:00');

        $before = $this->get(route('admin.dashboard'))->viewData('pulse')['today']['revenue'];

        $paid->update(['name' => 'Bayad na']);

        $after = $this->get(route('admin.dashboard'))->viewData('pulse')['today']['revenue'];

        $this->assertSame(1234.0, $before);
        $this->assertSame($before, $after);
    }

    public function test_status_less_ghost_orders_are_not_figures_at_all(): void
    {
        $this->actingAs($this->admin());
        $paid = $this->orderStatus('Payment Received', true);
        $this->order($paid, 100.00, '2026-08-09 09:00:00');

        $ghost = Order::create([
            'store_id' => 1,
            'order_status_id' => null,
            'first_name' => 'Ghost',
            'last_name' => 'Cart',
            'email' => 'ghost@example.com',
            'subtotal' => 5000,
            'shipping_cost' => 0,
            'total' => 5000,
        ]);
        $ghost->forceFill(['created_at' => Carbon::parse('2026-08-09 10:00:00')])->save();

        $today = $this->get(route('admin.dashboard'))->assertOk()->viewData('pulse')['today'];

        $this->assertSame(100.0, $today['revenue']);
        $this->assertSame(1, $today['placed']);
    }

    public function test_an_empty_day_reports_zero_rather_than_omitting_the_figures(): void
    {
        $this->actingAs($this->admin());
        $this->tradedBefore();

        $response = $this->get(route('admin.dashboard'))->assertOk();
        $today = $response->viewData('pulse')['today'];

        $this->assertSame(0.0, $today['revenue']);
        $this->assertSame(0, $today['placed']);
        $this->assertSame(0.0, $today['average']);

        // With nothing sold yet, the usual-weekday sentence states the
        // reference instead of an average of nothing.
        $response->assertSee('A usual Sunday takes');
    }

    // ---------------------------------------------------------------
    // The plot
    // ---------------------------------------------------------------

    public function test_the_plot_draws_twelve_months_against_the_same_twelve_a_year_earlier(): void
    {
        $this->actingAs($this->admin());
        $paid = $this->orderStatus('Payment Received', true);

        $this->order($paid, 1000.00, '2026-08-05 10:00:00');
        $this->order($paid, 700.00, '2025-12-10 10:00:00');
        $this->order($paid, 400.00, '2025-08-03 10:00:00');
        // Past the day this month has reached, so it belongs to neither the
        // reference nor the final point of the earlier trace.
        $this->order($paid, 9000.00, '2025-08-25 10:00:00');

        $response = $this->get(route('admin.dashboard'))->assertOk();
        $plot = $response->viewData('plot');

        $this->assertCount(12, $plot['labels']);
        $this->assertSame('Sep', $plot['labels'][0]);
        $this->assertSame('Aug', $plot['labels'][11]);

        // Sep, Oct, Nov, Dec: December 2025 is the fourth point.
        $this->assertSame(700.0, $plot['current'][3]);
        $this->assertSame(1000.0, $plot['current'][11]);
        // The earlier trace ends on the same span, not the whole month.
        $this->assertSame(400.0, $plot['earlier'][11]);

        $this->assertTrue($plot['hasTrade']);

        // The plot's newest point is printed on the key, so the current
        // value is readable with nothing pointed at anything. The folded
        // "Show the numbers" table is gone: the comparison tables under
        // the plot are the numbers.
        $response->assertSee('August 2026 so far')->assertDontSee('Show the numbers');
    }

    public function test_the_plot_still_renders_when_the_store_has_traded_but_not_lately(): void
    {
        $this->actingAs($this->admin());
        $this->tradedBefore();

        $plot = $this->get(route('admin.dashboard'))->assertOk()->viewData('plot');

        $this->assertCount(12, $plot['labels']);
        // The current year is flat and the earlier trace carries the one
        // historic order: November 2024 is the third of the earlier points.
        $this->assertSame(array_fill(0, 12, 0.0), $plot['current']);
        $this->assertSame(100.0, $plot['earlier'][2]);
    }

    // ---------------------------------------------------------------
    // The day's work
    // ---------------------------------------------------------------

    public function test_work_lines_count_orders_by_the_modules_own_placement_mapping(): void
    {
        $this->installFulfillment();
        $this->actingAs($this->admin());

        $toPack = $this->placedStatus('to_pack', 'Ready to Pack');
        $toHandover = $this->placedStatus('to_handover', 'Order Being Shipped');
        $unpaid = $this->placedStatus('unpaid', 'Unpaid');
        $delivered = $this->placedStatus('delivered', 'Delivered');

        $this->order($toPack, 100, '2026-06-01 10:00:00');
        $this->order($toPack, 100, '2026-06-02 10:00:00');
        $this->order($toHandover, 100, '2026-06-03 10:00:00');
        $this->order($unpaid, 100, '2026-06-04 10:00:00');
        // Placed elsewhere: must not appear in any of the three work counts.
        $this->order($delivered, 100, '2026-06-05 10:00:00');

        $lines = collect($this->get(route('admin.dashboard'))->assertOk()->viewData('workLines'))
            ->pluck('count', 'label');

        $this->assertSame(2, $lines['Parcels to pack']);
        $this->assertSame(1, $lines['Parcels awaiting handover']);
        $this->assertSame(1, $lines['Orders unpaid']);
    }

    public function test_an_unplaced_status_belongs_to_no_work_line(): void
    {
        $this->installFulfillment();
        $this->actingAs($this->admin());

        $this->placedStatus('to_pack', 'Ready to Pack');
        $loose = $this->orderStatus('Processing'); // deliberately never placed
        $this->order($loose, 100, '2026-06-01 10:00:00');

        $lines = collect($this->get(route('admin.dashboard'))->assertOk()->viewData('workLines'))
            ->pluck('count', 'label');

        $this->assertSame(0, $lines['Parcels to pack']);
    }

    public function test_the_packing_count_reaches_the_standing_sentence(): void
    {
        $this->installFulfillment();
        $this->actingAs($this->admin());

        $toPack = $this->placedStatus('to_pack', 'Ready to Pack');
        $this->order($toPack, 100, '2026-06-01 10:00:00');

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('No orders yet today, and 1 parcel waiting to be packed.');
    }

    public function test_the_fulfillment_work_lines_are_absent_when_the_module_is_not_installed(): void
    {
        $this->actingAs($this->admin());
        $this->tradedBefore();

        $response = $this->get(route('admin.dashboard'))->assertOk();
        $labels = collect($response->viewData('workLines'))->pluck('label');

        $this->assertFalse($labels->contains('Parcels to pack'));
        $this->assertFalse($labels->contains('Parcels awaiting handover'));
        $this->assertFalse($labels->contains('Orders unpaid'));

        // The sheet is still a sheet: the core work line survives, and so
        // does every other movement.
        //
        // WHERE it survives moved. The band now leads with the two work
        // figures a merchant acts on first, so with the module gone "Stock
        // about to run out" is a block on the band and the Needs doing list
        // has nothing left to hold and is not drawn. The figure is still on
        // the page and still links to the same screen, which is what this
        // test is about; the heading it used to sit under is not.
        $this->assertTrue($labels->contains('Stock about to run out'));
        $this->assertContains('Stock about to run out', array_column($response->viewData('stats'), 'label'));
        $this->assertSame([], $response->viewData('workRemaining'));
        $response->assertSee('Stock about to run out')->assertSee('Outstanding');
    }

    /**
     * Disabling an extension takes effect by its routes no longer being
     * registered on the next request. A provider that has already booted in
     * this process cannot un-register them (Laravel's provider model, see
     * ExtensionManager::disable()), so the route is withdrawn directly here
     * and the gate is asserted against that: the module's classes are still
     * loaded and its mapping table still populated, and the work lines must
     * still be gone, because Route::has is the state check and class_exists
     * is only the belt to its braces.
     */
    public function test_the_fulfillment_work_lines_disappear_when_the_module_is_disabled(): void
    {
        $this->installFulfillment();
        $toPack = $this->placedStatus('to_pack', 'Ready to Pack');
        $this->order($toPack, 100, '2026-06-01 10:00:00');
        $this->actingAs($this->admin());

        $before = collect($this->get(route('admin.dashboard'))->assertOk()->viewData('workLines'))->pluck('label');
        $this->assertTrue($before->contains('Parcels to pack'));

        app(ExtensionManager::class)->disable('courier-fulfillment');
        $this->withdrawFulfillmentRoutes();

        $response = $this->get(route('admin.dashboard'))->assertOk();
        $labels = collect($response->viewData('workLines'))->pluck('label');

        $this->assertFalse($labels->contains('Parcels to pack'));
        $this->assertFalse($labels->contains('Parcels awaiting handover'));
        $this->assertFalse($labels->contains('Orders unpaid'));
        $this->assertTrue($labels->contains('Stock about to run out'));
        // As above: the core figure moves onto the band when the module's
        // three lines go, so the list is empty rather than headed.
        $this->assertSame([], $response->viewData('workRemaining'));
        $response->assertSee('Stock about to run out')->assertSee('Outstanding')->assertSee('Sunday, 9 August 2026');
    }

    /**
     * The module can also be PAUSED without being uninstalled, which hides
     * it from the Sales menu. The sheet asks the sidebar registry the same
     * question, so a queue the merchant cannot reach from the menu is not
     * offered here either.
     */
    public function test_the_fulfillment_work_lines_go_when_the_merchant_pauses_the_module(): void
    {
        $this->installFulfillment();
        $toPack = $this->placedStatus('to_pack', 'Ready to Pack');
        $this->order($toPack, 100, '2026-06-01 10:00:00');
        $this->actingAs($this->admin());

        Setting::updateOrCreate(['group' => 'courier', 'key' => 'fulfillment_enabled'], ['value' => '0']);

        $response = $this->get(route('admin.dashboard'))->assertOk();
        $labels = collect($response->viewData('workLines'))->pluck('label');

        $this->assertFalse($labels->contains('Parcels to pack'));
        $this->assertTrue($labels->contains('Stock about to run out'));
        // And the standing sentence stops speaking about parcels too.
        $response->assertSee('No orders yet today.');
    }

    /** Rebuilds the route collection without the module's routes. */
    private function withdrawFulfillmentRoutes(): void
    {
        $router = app('router');
        $kept = new \Illuminate\Routing\RouteCollection();

        foreach ($router->getRoutes() as $route) {
            if (! str_starts_with((string) $route->getName(), 'admin.fulfillment.')) {
                $kept->add($route);
            }
        }

        $router->setRoutes($kept);
    }

    // ---------------------------------------------------------------
    // The latest orders
    // ---------------------------------------------------------------

    /**
     * Today's orders lead, newest first, and when today runs short the most
     * recent earlier orders fill the ledger: the old dashboard always
     * showed the last seven orders and the owner ruled that more
     * informative than a morning of "nothing yet". Today's rows print a
     * time; earlier rows print their date.
     */
    public function test_latest_orders_lead_with_today_then_fill_with_recent(): void
    {
        $this->actingAs($this->admin());
        $paid = $this->orderStatus('Payment Received', true);

        $late = $this->order($paid, 900.00, '2026-08-09 13:00:00', 'Zoe');
        $early = $this->order($paid, 300.00, '2026-08-09 08:00:00', 'Abe');
        $before = $this->order($paid, 100.00, '2026-08-08 09:00:00', 'Yesterday');

        $product = Product::create(['store_id' => 1, 'name' => 'Distortion Pedal', 'slug' => 'distortion-pedal', 'price' => 300]);
        DB::table('order_products')->insert([
            'order_id' => $early->id, 'product_id' => $product->id,
            'name' => 'Distortion Pedal', 'quantity' => 1, 'price' => 300, 'total' => 300,
        ]);

        $response = $this->get(route('admin.dashboard'))->assertOk();

        $this->assertSame([$late->id, $early->id, $before->id], $response->viewData('entries')->pluck('id')->all());
        $this->assertSame(2, $response->viewData('entriesTodayCount'));

        // Today's rows carry the clock, the filler carries its date.
        $response->assertSee('Distortion Pedal')
            ->assertSeeInOrder(['1:00 pm', '8:00 am', '8 Aug'])
            ->assertSee('2 today');
    }

    public function test_a_quiet_day_fills_the_ledger_with_the_most_recent_orders(): void
    {
        $this->actingAs($this->admin());
        // The only order on the books is from November 2024, so the ledger
        // reaches back to it and dates it with its year.
        $this->tradedBefore();

        $response = $this->get(route('admin.dashboard'))->assertOk();

        $this->assertSame(0, $response->viewData('entriesTodayCount'));
        $this->assertCount(1, $response->viewData('entries'));

        $response->assertSee('Latest orders')
            ->assertSee('Historic Doe')
            ->assertSee('15 Nov 2024')
            ->assertDontSee('Nothing entered yet today');
    }

    public function test_the_entries_do_not_query_per_order_for_their_line_items(): void
    {
        $this->actingAs($this->admin());
        $paid = $this->orderStatus('Payment Received', true);
        $product = Product::create(['store_id' => 1, 'name' => 'Cable', 'slug' => 'cable', 'price' => 100]);

        foreach (range(1, 8) as $i) {
            $order = $this->order($paid, 100.00, '2026-08-09 0' . ($i % 9) . ':00:00', 'Buyer' . $i);
            DB::table('order_products')->insert([
                'order_id' => $order->id, 'product_id' => $product->id,
                'name' => 'Cable', 'quantity' => 1, 'price' => 100, 'total' => 100,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->get(route('admin.dashboard'))->assertOk();
        $this->assertCount(8, $response->viewData('entries'));
        $itemQueries = collect(DB::getQueryLog())
            // Quoting differs by driver, so match the bare table name. The
            // running accounts also read order_products, but they do it by
            // joining orders; the entries' eager load is the one that does
            // not, and it is the one that must not repeat per order.
            ->filter(fn ($q) => str_contains($q['query'], 'order_products') && ! str_contains($q['query'], 'join'))
            ->count();
        DB::disableQueryLog();

        $this->assertSame(1, $itemQueries, 'Line items must load in one query for the whole day, not one per order.');
    }

    public function test_a_filler_from_this_year_is_dated_without_the_year(): void
    {
        $this->actingAs($this->admin());
        $paid = $this->orderStatus('Payment Received', true);
        $this->order($paid, 100.00, '2026-08-07 16:00:00');

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('7 Aug')
            ->assertDontSee('7 Aug 2026');
    }

    // ---------------------------------------------------------------
    // Standing matters
    // ---------------------------------------------------------------

    public function test_outstanding_matters_are_ruled_off_in_the_rail(): void
    {
        $this->actingAs($this->admin());
        $this->tradedBefore();
        // "Carts recoverable" appears only while the abandoned-carts
        // extension is installed (wave 1 conversion).
        app(\App\Services\ExtensionManager::class)->install('abandoned-carts');

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Outstanding')
            ->assertSee('Reviews awaiting approval')
            ->assertSee('Carts recoverable')
            // Automation health is its own panel now, not a line here.
            ->assertDontSee('Automations overdue')
            ->assertSee('Automations');
    }

    /**
     * The newest pending review names its product on the Outstanding line:
     * one cheap read of a fact the count alone withheld. The detail only
     * exists when the count does.
     */
    public function test_the_review_count_names_the_newest_pending_reviews_product(): void
    {
        $this->actingAs($this->admin());
        $this->tradedBefore();

        $pedal = Product::create(['store_id' => 1, 'name' => 'Overdrive Pedal', 'slug' => 'overdrive-pedal', 'price' => 250]);
        \App\Models\Review::create([
            'product_id' => $pedal->id, 'token' => 'review-token', 'name' => 'A Buyer', 'email' => 'buyer@example.com',
            'rating' => 5, 'body' => 'Great pedal', 'status' => 'pending',
        ]);

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Newest is for Overdrive Pedal');
    }

    // ---------------------------------------------------------------
    // Automation health
    // ---------------------------------------------------------------

    /**
     * Each unhealthy automation is a row with its name, when it last ran,
     * and a Failed or Overdue chip, worst first: failed, then never run,
     * then the stalest of the merely late. The healthy ones are one quiet
     * line, never a roster.
     */
    public function test_unhealthy_automations_are_rows_worst_first_and_healthy_ones_one_line(): void
    {
        $this->actingAs($this->admin());
        $this->tradedBefore();

        \App\Models\Automation::create([
            'key' => 'a_overdue', 'name' => 'Sitemap Refresh', 'command' => 'sitemap:generate',
            'schedule_preset' => 'daily', 'schedule_params' => [], 'cron_expression' => '0 4 * * *',
            'enabled' => true, 'last_run_at' => now()->subDays(3), 'last_run_status' => 'success',
        ]);
        \App\Models\Automation::create([
            'key' => 'b_failed', 'name' => 'Currency Update', 'command' => 'currency:update',
            'schedule_preset' => 'daily', 'schedule_params' => [], 'cron_expression' => '0 16 * * *',
            'enabled' => true, 'last_run_at' => now()->subHours(2), 'last_run_status' => 'failed',
        ]);
        \App\Models\Automation::create([
            'key' => 'c_never', 'name' => 'Review Requests', 'command' => 'review:send',
            'schedule_preset' => 'daily', 'schedule_params' => [], 'cron_expression' => '0 10 * * *',
            'enabled' => true, 'last_run_at' => null, 'last_run_status' => null,
        ]);
        \App\Models\Automation::create([
            'key' => 'd_fine', 'name' => 'Quiet Task', 'command' => 'stock:notify',
            'schedule_preset' => 'daily', 'schedule_params' => [], 'cron_expression' => '* * * * *',
            'enabled' => true, 'last_run_at' => now(), 'last_run_status' => 'success',
        ]);

        $response = $this->get(route('admin.dashboard'))->assertOk();

        $health = $response->viewData('automationHealth');
        $this->assertSame(4, $health['total']);
        $this->assertSame(
            ['Currency Update', 'Review Requests', 'Sitemap Refresh'],
            $health['unhealthy']->pluck('name')->all()
        );

        $response->assertSeeInOrder(['Currency Update', 'Failed', 'Review Requests', 'Never run', 'Sitemap Refresh', 'Overdue'])
            ->assertSee('The other 1 is on schedule.')
            // The healthy one is summarised, not listed.
            ->assertDontSee('Quiet Task');
    }

    public function test_healthy_automations_read_as_one_quiet_line(): void
    {
        $this->actingAs($this->admin());
        $this->tradedBefore();

        \App\Models\Automation::create([
            'key' => 'fine', 'name' => 'Quiet Task', 'command' => 'stock:notify',
            'schedule_preset' => 'daily', 'schedule_params' => [], 'cron_expression' => '* * * * *',
            'enabled' => true, 'last_run_at' => now(), 'last_run_status' => 'success',
        ]);

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Running on schedule.')
            ->assertDontSee('Overdue');
    }

    // ---------------------------------------------------------------
    // Customer activity
    // ---------------------------------------------------------------

    public function test_customer_activity_is_a_feed_of_who_did_what(): void
    {
        $this->actingAs($this->admin());
        $this->tradedBefore();

        $group = CustomerGroup::create(['store_id' => 1, 'name' => 'Retail', 'is_default' => true]);
        $buyer = $this->customerIn($group, 'feed@example.com');

        \App\Models\CustomerActivity::create([
            'customer_id' => $buyer->id, 'type' => 'cart_add',
            'description' => 'Added Distortion Pedal to cart',
            'created_at' => Carbon::parse('2026-08-09 12:30:00'),
        ]);
        \App\Models\CustomerActivity::create([
            'customer_id' => $buyer->id, 'type' => 'login',
            'description' => 'Logged in',
            'created_at' => Carbon::parse('2026-08-09 12:00:00'),
        ]);

        $response = $this->get(route('admin.dashboard'))->assertOk();

        $this->assertSame(2, $response->viewData('activities')->count());

        // Newest first, named, in the feed's own voice.
        $response->assertSee('Customer activity')
            ->assertSeeInOrder(['added Distortion Pedal to cart', 'logged in'])
            ->assertSee('Buyer One');
    }

    // ---------------------------------------------------------------
    // The running accounts
    // ---------------------------------------------------------------

    /**
     * Two rankings, because the item that carries the money and the item
     * that moves are rarely the same item and a shop watching only one of
     * them misreads its own catalogue.
     */
    public function test_the_running_accounts_rank_products_by_revenue_and_by_units(): void
    {
        $this->actingAs($this->admin());
        $paid = $this->orderStatus('Payment Received', true);

        $amp = Product::create(['store_id' => 1, 'name' => 'Valve Amp', 'slug' => 'valve-amp', 'price' => 5000]);
        $picks = Product::create(['store_id' => 1, 'name' => 'Pick Tin', 'slug' => 'pick-tin', 'price' => 20]);

        $this->lineItem($this->order($paid, 5000.00, '2026-07-20 10:00:00'), $amp, 1, 5000.00);
        $this->lineItem($this->order($paid, 1000.00, '2026-08-09 10:00:00'), $picks, 50, 1000.00);

        $response = $this->get(route('admin.dashboard'))->assertOk();
        $accounts = $response->viewData('accounts');

        $this->assertSame('Valve Amp', $accounts['byRevenue'][0]['name']);
        $this->assertSame(5000.0, $accounts['byRevenue'][0]['revenue']);
        $this->assertSame('Pick Tin', $accounts['byUnits'][0]['name']);
        $this->assertSame(50, $accounts['byUnits'][0]['units']);

        $response->assertSee('Carrying the money')->assertSee('Moving off the shelf')->assertSee('Last 30 days');
    }

    /** Sales outside the stated period are outside the stated period. */
    public function test_the_rankings_only_count_the_period_they_state(): void
    {
        $this->actingAs($this->admin());
        $paid = $this->orderStatus('Payment Received', true);

        $old = Product::create(['store_id' => 1, 'name' => 'Last Year Special', 'slug' => 'last-year', 'price' => 90000]);
        $this->lineItem($this->order($paid, 90000.00, '2025-08-03 10:00:00'), $old, 1, 90000.00);

        $now = Product::create(['store_id' => 1, 'name' => 'This Month Cable', 'slug' => 'this-month', 'price' => 300]);
        $this->lineItem($this->order($paid, 300.00, '2026-08-09 10:00:00'), $now, 1, 300.00);

        $accounts = $this->get(route('admin.dashboard'))->assertOk()->viewData('accounts');

        $this->assertSame(['This Month Cable'], array_column($accounts['byRevenue'], 'name'));
    }

    /**
     * PLATFORM NEUTRALITY. The mix reports whatever the orders recorded; it
     * knows nothing about bank transfer, cash on delivery or any gateway.
     */
    public function test_the_payment_mix_reports_whatever_methods_the_store_actually_used(): void
    {
        $this->actingAs($this->admin());
        $paid = $this->orderStatus('Payment Received', true);

        $this->paidWith($this->order($paid, 750.00, '2026-08-09 09:00:00'), 'pigeon', 'Carrier Pigeon');
        $this->paidWith($this->order($paid, 250.00, '2026-08-09 10:00:00'), 'barter', 'Barter');

        $response = $this->get(route('admin.dashboard'))->assertOk();
        $mix = $response->viewData('accounts')['payments'];

        $this->assertSame(['Carrier Pigeon', 'Barter'], array_column($mix, 'label'));
        $this->assertSame(0.75, $mix[0]['share']);
        $this->assertSame(0.25, $mix[1]['share']);

        // The mix used to be a stacked bar under a "How it was paid"
        // heading inside the running accounts. It is now the strip across
        // the top of the page, which is the ERP's channel strip in Venta's
        // terms, so the assertion follows it there: the methods the store
        // actually used, named, with their shares.
        $this->assertSame(['Carrier Pigeon', 'Barter'], array_column($response->viewData('mix'), 'label'));
        $response->assertSee('Carrier Pigeon')->assertSee('Barter')->assertSee('75.0%');
    }

    public function test_orders_with_no_payment_method_are_named_rather_than_dropped(): void
    {
        $this->actingAs($this->admin());
        $paid = $this->orderStatus('Payment Received', true);
        $this->order($paid, 500.00, '2026-08-09 09:00:00');

        $mix = $this->get(route('admin.dashboard'))->assertOk()->viewData('accounts')['payments'];

        $this->assertSame(['Not recorded'], array_column($mix, 'label'));
        $this->assertSame(500.0, $mix[0]['revenue']);
    }

    /**
     * A buyer is an email address, which is the only identity a Venta
     * checkout always captures. Returning means that address had ordered
     * before the period started.
     */
    public function test_new_and_returning_buyers_are_counted_by_email(): void
    {
        $this->actingAs($this->admin());
        $paid = $this->orderStatus('Payment Received', true);

        // Abe bought before the window and again inside it: one buyer, returning.
        $this->order($paid, 100.00, '2026-01-04 10:00:00', 'Abe');
        $this->order($paid, 100.00, '2026-08-09 10:00:00', 'Abe');
        // Zoe is only ever seen inside the window.
        $this->order($paid, 100.00, '2026-08-09 11:00:00', 'Zoe');

        $buyers = $this->get(route('admin.dashboard'))->assertOk()->viewData('accounts')['buyers'];

        $this->assertSame(2, $buyers['total']);
        $this->assertSame(1, $buyers['returning']);
        $this->assertSame(1, $buyers['new']);
    }

    /**
     * MOST STORES HAVE ONE CUSTOMER GROUP, and a split of one is not a
     * split. The line is withheld rather than printing a single 100% row.
     */
    public function test_revenue_by_customer_group_is_withheld_when_one_group_carries_the_trade(): void
    {
        $this->actingAs($this->admin());
        $paid = $this->orderStatus('Payment Received', true);

        $retail = CustomerGroup::create(['store_id' => 1, 'name' => 'Retail', 'is_default' => true]);
        $buyer = $this->customerIn($retail, 'solo@example.com');

        $this->order($paid, 400.00, '2026-08-09 09:00:00')->forceFill(['customer_id' => $buyer->id])->save();

        $response = $this->get(route('admin.dashboard'))->assertOk();

        $this->assertSame([], $response->viewData('accounts')['groups']);
        // A split of one is not a split, so the ring falls back to the
        // division every order records instead of inventing a group one.
        $response->assertDontSee('Orders by customer group');
        $this->assertSame('payment method', $response->viewData('ring')['by']);
    }

    public function test_revenue_by_customer_group_appears_when_two_groups_carry_trade(): void
    {
        $this->actingAs($this->admin());
        $paid = $this->orderStatus('Payment Received', true);

        $retail = CustomerGroup::create(['store_id' => 1, 'name' => 'Retail', 'is_default' => true]);
        $trade = CustomerGroup::create(['store_id' => 1, 'name' => 'Resellers']);

        $one = $this->customerIn($retail, 'retail@example.com');
        $two = $this->customerIn($trade, 'trade@example.com');

        $this->order($paid, 300.00, '2026-08-09 09:00:00')->forceFill(['customer_id' => $one->id])->save();
        $this->order($paid, 700.00, '2026-08-09 10:00:00')->forceFill(['customer_id' => $two->id])->save();

        $response = $this->get(route('admin.dashboard'))->assertOk();
        $groups = $response->viewData('accounts')['groups'];

        $this->assertSame(['Resellers', 'Retail'], array_column($groups, 'label'));
        $this->assertSame(0.7, $groups[0]['share']);

        // The split used to be a list at the foot of the running accounts.
        // It is now what the ring divides by, because a store genuinely
        // running two groups has a more interesting division than how it
        // was paid. Same rule, same source, drawn where it can be seen.
        $response->assertSee('Orders by customer group');
        $this->assertSame('customer group', $response->viewData('ring')['by']);
        $this->assertSame(['Resellers', 'Retail'], array_column($response->viewData('ring')['segments'], 'label'));
    }

    public function test_a_period_with_no_sales_says_so_rather_than_drawing_empty_rankings(): void
    {
        $this->actingAs($this->admin());
        $this->tradedBefore();

        $response = $this->get(route('admin.dashboard'))->assertOk();

        $this->assertSame(0, $response->viewData('accounts')['orders']);
        $response->assertSee('Nothing sold in the last 30 days.')
            ->assertDontSee('Carrying the money');
    }

    // ---------------------------------------------------------------
    // The brand new store, which is every merchant's first sight of this
    // ---------------------------------------------------------------

    public function test_a_store_that_has_never_traded_gets_an_opening_movement_not_a_page_of_zeros(): void
    {
        $this->actingAs($this->admin());

        $response = $this->get(route('admin.dashboard'))->assertOk();

        $this->assertFalse($response->viewData('hasTraded'));

        $response->assertSee('No orders yet.')
            ->assertSee('To open')
            ->assertSee('Products in the catalog')
            ->assertSee('Add a product')
            // None of the movements that would be a row of zeros.
            ->assertDontSee('Last 12 months')
            ->assertDontSee('Running accounts')
            ->assertDontSee("Today&#039;s entries", false);
    }

    public function test_the_opening_movement_counts_the_catalog_and_the_methods_that_are_on(): void
    {
        $this->actingAs($this->admin());

        Product::create(['store_id' => 1, 'name' => 'First Product', 'slug' => 'first-product', 'price' => 100]);

        // A fresh install ships with its own defaults, so the store is put
        // into a known state rather than added to.
        Setting::whereIn('group', ['payment', 'shipping'])->delete();
        Setting::create(['group' => 'payment', 'key' => 'cod_enabled', 'value' => '1']);
        Setting::create(['group' => 'payment', 'key' => 'hitpay_enabled', 'value' => '0']);
        Setting::create(['group' => 'shipping', 'key' => 'flat_rate_enabled', 'value' => '1']);

        $lines = collect($this->get(route('admin.dashboard'))->assertOk()->viewData('openingLines'))
            ->pluck('count', 'label');

        $this->assertSame(1, $lines['Products in the catalog']);
        $this->assertSame(1, $lines['Payment methods on']);
        $this->assertSame(1, $lines['Shipping methods on']);
    }

    /**
     * A cart that reached the orders table and stopped is not trade, so it
     * does not turn the opening movement off.
     */
    public function test_ghost_orders_alone_do_not_make_a_store_traded(): void
    {
        $this->actingAs($this->admin());

        $ghost = Order::create([
            'store_id' => 1,
            'order_status_id' => null,
            'first_name' => 'Ghost',
            'last_name' => 'Cart',
            'email' => 'ghost@example.com',
            'subtotal' => 900,
            'shipping_cost' => 0,
            'total' => 900,
        ]);
        $ghost->forceFill(['created_at' => Carbon::parse('2026-08-09 10:00:00')])->save();

        $this->get(route('admin.dashboard'))->assertOk()->assertSee('To open');
    }

    // ---------------------------------------------------------------
    // Money is the store's own
    // ---------------------------------------------------------------

    public function test_the_currency_mark_comes_from_the_stores_own_configuration(): void
    {
        $this->actingAs($this->admin());
        Currency::create(['code' => 'MYR', 'name' => 'Ringgit', 'symbol' => 'RM', 'rate' => 1, 'is_default' => true, 'is_enabled' => true]);

        $paid = $this->orderStatus('Payment Received', true);
        $this->order($paid, 250.00, '2026-08-09 09:00:00');

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('RM')
            ->assertSee('1 order so far, RM250.00 taken.');
    }

    // ---------------------------------------------------------------
    // Low stock: one definition, three readers
    // ---------------------------------------------------------------

    public function test_the_stock_work_line_links_to_the_catalog_filtered_to_the_same_set(): void
    {
        $this->actingAs($this->admin());
        Setting::create(['group' => 'stock', 'key' => 'low_stock_threshold', 'value' => '5']);

        Product::create(['store_id' => 1, 'name' => 'Almostgone Widget', 'slug' => 'almostgone-widget', 'price' => 10, 'quantity' => 2, 'is_active' => true, 'track_stock' => true]);
        Product::create(['store_id' => 1, 'name' => 'Wellstocked Widget', 'slug' => 'wellstocked-widget', 'price' => 10, 'quantity' => 40, 'is_active' => true, 'track_stock' => true]);
        Product::create(['store_id' => 1, 'name' => 'Soldout Widget', 'slug' => 'soldout-widget', 'price' => 10, 'quantity' => 0, 'is_active' => true, 'track_stock' => true]);

        $line = collect($this->get(route('admin.dashboard'))->assertOk()->viewData('workLines'))
            ->firstWhere('label', 'Stock about to run out');

        $this->assertSame(1, $line['count']);

        // The number and the list it links to are the same set: already at
        // zero has run out, which is a different fact and a different screen.
        $this->get($line['url'])
            ->assertOk()
            ->assertSee('Almostgone Widget')
            ->assertDontSee('Wellstocked Widget')
            ->assertDontSee('Soldout Widget');
    }
}
