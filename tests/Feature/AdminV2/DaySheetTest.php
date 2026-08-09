<?php

namespace Tests\Feature\AdminV2;

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
 * disappear with it, and an empty day says so rather than printing an
 * empty table.
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

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('No orders yet today.');
    }

    // ---------------------------------------------------------------
    // The day's figures
    // ---------------------------------------------------------------

    public function test_the_figures_carry_today_yesterday_and_the_same_weekday_last_week(): void
    {
        $this->actingAs($this->admin());
        $paid = $this->orderStatus('Payment Received', true);

        $this->order($paid, 100.00, '2026-08-09 10:00:00'); // today
        $this->order($paid, 250.00, '2026-08-08 10:00:00'); // yesterday
        $this->order($paid, 375.00, '2026-08-02 10:00:00'); // same weekday last week

        $response = $this->get(route('admin.dashboard'))->assertOk();

        $figures = $response->viewData('figures');
        $revenue = collect($figures)->firstWhere('key', 'revenue')['values'];
        $placed = collect($figures)->firstWhere('key', 'placed')['values'];

        $this->assertSame(100.0, $revenue['today']);
        $this->assertSame(250.0, $revenue['yesterday']);
        $this->assertSame(375.0, $revenue['weekAgo']);
        $this->assertSame([1, 1, 1], [$placed['today'], $placed['yesterday'], $placed['weekAgo']]);

        // The weekday a week back is named, so a Sunday is compared to a Sunday.
        $response->assertSee('Sun 2 Aug');
    }

    public function test_cancelled_orders_are_counted_as_placed_but_not_as_revenue(): void
    {
        $this->actingAs($this->admin());
        $paid = $this->orderStatus('Payment Received', true);
        $void = $this->orderStatus('Refunded');

        $this->order($paid, 400.00, '2026-08-09 09:00:00');
        $this->order($void, 999.00, '2026-08-09 10:00:00');

        $figures = collect($this->get(route('admin.dashboard'))->assertOk()->viewData('figures'));

        $this->assertSame(400.0, $figures->firstWhere('key', 'revenue')['values']['today']);
        $this->assertSame(2, $figures->firstWhere('key', 'placed')['values']['today']);
        // The average divides revenue by the orders it actually came from,
        // so one refund does not halve it.
        $this->assertSame(400.0, $figures->firstWhere('key', 'average')['values']['today']);
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

        $figures = collect($this->get(route('admin.dashboard'))->assertOk()->viewData('figures'));

        $this->assertSame(300.0, $figures->firstWhere('key', 'revenue')['values']['today']);
        $this->assertSame(2, $figures->firstWhere('key', 'placed')['values']['today']);
    }

    /** Renaming a status does not move a figure: the flag is on the row. */
    public function test_renaming_a_status_leaves_the_days_revenue_untouched(): void
    {
        $this->actingAs($this->admin());
        $paid = $this->orderStatus('Payment Received', true);
        $this->order($paid, 1234.00, '2026-08-09 09:00:00');

        $before = collect($this->get(route('admin.dashboard'))->viewData('figures'))
            ->firstWhere('key', 'revenue')['values']['today'];

        $paid->update(['name' => 'Bayad na']);

        $after = collect($this->get(route('admin.dashboard'))->viewData('figures'))
            ->firstWhere('key', 'revenue')['values']['today'];

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

        $figures = collect($this->get(route('admin.dashboard'))->assertOk()->viewData('figures'));

        $this->assertSame(100.0, $figures->firstWhere('key', 'revenue')['values']['today']);
        $this->assertSame(1, $figures->firstWhere('key', 'placed')['values']['today']);
    }

    public function test_an_empty_day_reports_zero_rather_than_omitting_the_figures(): void
    {
        $this->actingAs($this->admin());

        $figures = collect($this->get(route('admin.dashboard'))->assertOk()->viewData('figures'));

        $this->assertSame(0.0, $figures->firstWhere('key', 'revenue')['values']['today']);
        $this->assertSame(0, $figures->firstWhere('key', 'placed')['values']['today']);
        $this->assertSame(0.0, $figures->firstWhere('key', 'average')['values']['today']);
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

        $response = $this->get(route('admin.dashboard'))->assertOk();
        $labels = collect($response->viewData('workLines'))->pluck('label');

        $this->assertFalse($labels->contains('Parcels to pack'));
        $this->assertFalse($labels->contains('Parcels awaiting handover'));
        $this->assertFalse($labels->contains('Orders unpaid'));

        // The sheet is still a sheet: the core work line survives, and so
        // does every other movement.
        $this->assertTrue($labels->contains('Stock about to run out'));
        $response->assertSee('Needs doing')->assertSee('Outstanding');
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
        $response->assertSee('Needs doing')->assertSee('Outstanding')->assertSee('Sunday, 9 August 2026');
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
    // The day's entries
    // ---------------------------------------------------------------

    public function test_todays_orders_are_listed_chronologically_with_what_was_bought(): void
    {
        $this->actingAs($this->admin());
        $paid = $this->orderStatus('Payment Received', true);

        $late = $this->order($paid, 900.00, '2026-08-09 13:00:00', 'Zoe');
        $early = $this->order($paid, 300.00, '2026-08-09 08:00:00', 'Abe');
        $this->order($paid, 100.00, '2026-08-08 09:00:00', 'Yesterday');

        $product = Product::create(['store_id' => 1, 'name' => 'Distortion Pedal', 'slug' => 'distortion-pedal', 'price' => 300]);
        DB::table('order_products')->insert([
            'order_id' => $early->id, 'product_id' => $product->id,
            'name' => 'Distortion Pedal', 'quantity' => 1, 'price' => 300, 'total' => 300,
        ]);

        $response = $this->get(route('admin.dashboard'))->assertOk();
        $entries = $response->viewData('entries');

        $this->assertSame([$early->id, $late->id], $entries->pluck('id')->all());
        $response->assertSee('Distortion Pedal')->assertDontSee('Yesterday Doe');
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
            // Quoting differs by driver, so match the bare table name.
            ->filter(fn ($q) => str_contains($q['query'], 'order_products'))
            ->count();
        DB::disableQueryLog();

        $this->assertSame(1, $itemQueries, 'Line items must load in one query for the whole day, not one per order.');
    }

    public function test_an_empty_day_says_so_instead_of_rendering_an_empty_table(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Nothing entered yet today.')
            // No table at all, not a table with no rows.
            ->assertDontSee('v-sheet__scroll');
    }

    public function test_an_empty_day_names_when_the_last_order_arrived(): void
    {
        $this->actingAs($this->admin());
        $paid = $this->orderStatus('Payment Received', true);
        $this->order($paid, 100.00, '2026-08-07 16:00:00');

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('The last order came in');
    }

    // ---------------------------------------------------------------
    // Standing matters
    // ---------------------------------------------------------------

    public function test_outstanding_matters_are_ruled_off_at_the_foot(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Outstanding')
            ->assertSee('Reviews awaiting approval')
            ->assertSee('Carts recoverable')
            ->assertSee('Automations overdue');
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
