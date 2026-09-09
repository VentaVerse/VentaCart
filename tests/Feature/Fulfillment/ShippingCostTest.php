<?php

namespace Tests\Feature\Fulfillment;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Extensions\CourierFulfillment\Couriers\QuadX\QuadXFulfillment;
use Extensions\CourierFulfillment\PlacementMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * What the shipment cost the store, kept rather than thrown away.
 *
 * QuadX has always returned the figure in its booking response and Venta has
 * always stored that response whole, so the number was there and simply never
 * promoted anywhere anyone could read. It now lands on the order beside the
 * total, without touching it.
 */
class ShippingCostTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        app(\App\Services\ExtensionManager::class)->install('courier-fulfillment');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        $this->actingAs(User::factory()->create());

        foreach ([
            'quadx_enabled' => '1',
            'quadx_mode' => 'staging',
            'quadx_staging_api_key' => Crypt::encryptString('k'),
            'quadx_staging_secret_key' => Crypt::encryptString('s'),
        ] as $k => $v) {
            Setting::create(['group' => 'courier', 'key' => $k, 'value' => $v]);
        }
    }

    /**
     * QuadX answers the cost question twice: a list price, and the same after
     * its own adjustments. The store pays the adjusted one.
     */
    public function test_the_adjusted_fee_is_what_the_store_actually_paid(): void
    {
        $quadx = new QuadXFulfillment();

        $meta = ['booked_response' => [
            'shipping_fee' => '80.00',
            'fees_adjustment' => ['shipping_fee' => '77'],
        ]];

        $this->assertSame(77.0, $quadx->actualShippingCost($meta));
    }

    public function test_the_list_price_is_used_when_nothing_was_adjusted(): void
    {
        $quadx = new QuadXFulfillment();

        $this->assertSame(80.0, $quadx->actualShippingCost(['booked_response' => ['shipping_fee' => '80.00']]));
    }

    /** A courier that said nothing must not be recorded as free. */
    public function test_a_silent_courier_reports_nothing_rather_than_zero(): void
    {
        $quadx = new QuadXFulfillment();

        $this->assertNull($quadx->actualShippingCost([]));
        $this->assertNull($quadx->actualShippingCost(['booked_response' => []]));
    }

    /** The whole point: it reaches the order, and the total does not move. */
    public function test_booking_records_the_cost_and_leaves_the_total_alone(): void
    {
        $pending = OrderStatus::create(['store_id' => 1, 'name' => 'Processing', 'sort_order' => 1]);
        $booked = OrderStatus::create(['store_id' => 1, 'name' => 'Shipped', 'sort_order' => 2]);
        PlacementMap::put($pending->id, 'to_pack');
        PlacementMap::put($booked->id, 'to_handover');
        // The setting is the only source for which placed status a booking
        // lands on (owner ruling 2026-08-30), so a placement alone is not
        // enough to book.
        Setting::create(['group' => 'courier', 'key' => 'booked_order_status_id', 'value' => (string) $booked->id]);

        $order = Order::create([
            'store_id' => 1, 'first_name' => 'A', 'last_name' => 'Buyer',
            'email' => 'buyer@example.test', 'order_status_id' => $pending->id,
            'total' => 4715.00, 'subtotal' => 4590.00, 'shipping_cost' => 125.00,
        ]);

        Http::fake(['*/orders' => Http::response([
            'tracking_number' => 'QX-COST-001',
            'status' => 'for_pickup',
            'id' => 9,
            'shipping_fee' => '80.00',
            'fees_adjustment' => ['shipping_fee' => '77'],
        ], 200)]);

        $result = app(\Extensions\CourierFulfillment\FulfillmentService::class)
            ->book($order, ['courier' => 'quadx', 'service' => 'next_day', 'parcel' => 'box']);

        $this->assertTrue($result['success'], json_encode($result));

        $order->refresh();
        // Compared as numbers: decimal formatting is the database driver's
        // business, and SQLite and MySQL disagree about trailing zeros.
        $this->assertSame(77.0, (float) $order->shipping_cost_actual, 'the cost QuadX charged');
        $this->assertSame(4715.0, (float) $order->total, 'the total must not move');
        $this->assertSame(125.0, (float) $order->shipping_cost, 'what the customer paid is a different number');
    }

    /** The order page shows the block, without moving the total. */
    public function test_the_order_page_shows_the_fees_below_the_total(): void
    {
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Shipped', 'sort_order' => 1]);
        $order = Order::create([
            'store_id' => 1, 'first_name' => 'A', 'last_name' => 'Buyer',
            'email' => 'buyer@example.test', 'order_status_id' => $status->id,
            'total' => 4715.00, 'subtotal' => 4590.00, 'shipping_cost' => 125.00,
        ]);
        $order->forceFill(['payment_fee' => 170.02, 'shipping_cost_actual' => 77.00])->save();

        $this->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Fees and charges')
            ->assertSee('4,715.00')      // the total, untouched
            ->assertSee('170.02')
            ->assertSee('77.00')
            ->assertSee('Net after fees')
            ->assertSee('4,467.98');     // 4715 - 170.02 - 77
    }

    /** While a figure is missing the page says so, and hedges the wording. */
    public function test_a_missing_fee_is_named_rather_than_shown_as_zero(): void
    {
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Processing', 'sort_order' => 1]);
        $order = Order::create([
            'store_id' => 1, 'first_name' => 'A', 'last_name' => 'Buyer',
            'email' => 'buyer@example.test', 'order_status_id' => $status->id, 'total' => 1000.00,
        ]);
        $order->forceFill(['payment_fee' => 45.00])->save();

        $this->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('awaiting booking')
            ->assertSee('Net so far')
            ->assertDontSee('Net after fees');
    }

    /** Null and zero say different things, and the order page relies on it. */
    public function test_an_unbooked_order_has_no_cost_rather_than_a_zero(): void
    {
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Pending', 'sort_order' => 1]);
        $order = Order::create([
            'store_id' => 1, 'first_name' => 'A', 'last_name' => 'B',
            'email' => 'b@example.test', 'order_status_id' => $status->id, 'total' => 100,
        ]);

        $this->assertNull($order->shipping_cost_actual);
        $this->assertNull($order->payment_fee);
    }
}
