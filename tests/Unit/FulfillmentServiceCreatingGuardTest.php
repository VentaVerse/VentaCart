<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Services\ExtensionManager;
use Extensions\CourierFulfillment\FulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Task T2 review fix (CRITICAL 2): FulfillmentService::book() must refuse a
 * SECOND booking attempt while an async courier's FIRST creation is still
 * in flight (courier_status='creating', no tracking number yet).
 *
 * Before this fix, isActivelyBooked() — the only guard book() had — requires
 * a tracking number, which a 'creating' order doesn't have yet, so a second
 * "Book" click sailed straight through to createShipment() and submitted a
 * SECOND SPX batch_create_order for the same order. The first batch still
 * creates a real shipment SPX-side; Venta just never learns its tracking
 * number, so it's neither trackable nor cancellable — a real orphaned
 * shipment, not just a UI glitch.
 *
 * Guarded at the FulfillmentService level (not inside SpxFulfillment) so it
 * protects ANY courier whose createShipment() can return this pending
 * state — proven here with SPX, the only async courier that exists today.
 */
class FulfillmentServiceCreatingGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ExtensionManager::class)->install('courier-fulfillment');
        app(ExtensionManager::class)->bootEnabled(); // registers SpxFulfillment into CourierManager
    }

    private function enableSpx(): void
    {
        Setting::create(['group' => 'courier', 'key' => 'spx_enabled', 'value' => '1']);
        Setting::create(['group' => 'courier', 'key' => 'spx_app_id', 'value' => '424242']);
        Setting::create(['group' => 'courier', 'key' => 'spx_secret_key', 'value' => Crypt::encryptString('fake-app-secret-not-real')]);
        Setting::create(['group' => 'courier', 'key' => 'spx_user_id', 'value' => '999999']);
        Setting::create(['group' => 'courier', 'key' => 'spx_user_secret', 'value' => Crypt::encryptString('fake-user-secret-not-real')]);
    }

    private function makeOrder(array $overrides = []): Order
    {
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Pending', 'sort_order' => 1, 'is_default' => true]);
        // fulfillment-placement Task 3: booking needs a To-Handover-placed
        // status to move the order to, or it is refused before any outbound
        // call (FulfillmentService::bookedStatus()).
        OrderStatus::firstOrCreate(
            ['store_id' => 1, 'name' => 'Order Being Shipped'],
            ['sort_order' => 4, 'fulfillment_placement' => 'to_handover'],
        );

        return Order::create(array_merge([
            'store_id' => 1, 'order_status_id' => $status->id,
            'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com',
            'shipping_first_name' => 'Jane', 'shipping_last_name' => 'Doe',
            'shipping_address_1' => '123 Mango St', 'shipping_barangay' => 'Lahug',
            'shipping_city' => 'Cebu City', 'shipping_state' => 'Cebu', 'shipping_postcode' => '6000',
            'shipping_phone' => '09171234567', 'payment_method' => 'bank_transfer',
            'subtotal' => 1000, 'shipping_cost' => 100, 'total' => 1100,
        ], $overrides));
    }

    public function test_book_refuses_a_second_attempt_while_the_first_async_creation_is_still_pending(): void
    {
        $this->enableSpx();
        Http::fake(); // never called — the guard must short-circuit before any client call.

        $order = $this->makeOrder();
        $order->forceFill([
            'courier_provider' => 'spx',
            'courier_status'   => 'creating',
            'courier_meta'     => json_encode(['batch_no' => 123, 'pending' => true]),
        ])->save();

        $result = app(FulfillmentService::class)->book($order, [
            'courier' => 'spx', 'parcel' => 'small', 'shipping_payment' => 'sender_pay',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('creation_in_progress', $result['code']);
        $this->assertStringContainsString('SPX', $result['error']);
        $this->assertStringContainsString((string) $order->id, $result['error']);
        Http::assertNothingSent();

        // Order state is completely untouched — no second batch was ever attempted.
        $order->refresh();
        $this->assertSame('creating', $order->courier_status);
        $this->assertNull($order->courier_tracking_number);
        $this->assertSame(123, json_decode($order->courier_meta, true)['batch_no']);
    }

    public function test_book_refuses_even_when_a_different_courier_is_selected_the_second_time(): void
    {
        $this->enableSpx();
        Http::fake();

        $order = $this->makeOrder();
        $order->forceFill([
            'courier_provider' => 'spx',
            'courier_status'   => 'creating',
            'courier_meta'     => json_encode(['batch_no' => 5]),
        ])->save();

        // QuadX isn't enabled/configured in this test, but the creating-state
        // guard must fire BEFORE that even matters — an order mid-flight with
        // SPX should never be booked out from under it by a different courier.
        $result = app(FulfillmentService::class)->book($order, [
            'courier' => 'quadx', 'service' => 'next_day', 'parcel' => 'box', 'shipping_payment' => 'net_off',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('creation_in_progress', $result['code']);
        Http::assertNothingSent();
    }

    /**
     * Regression sanity: a normal (never-booked) order is NOT blocked by the
     * new guard. Uses a fast-complete (task_status Done on the very first
     * poll) response — CourierManager::get() constructs SpxFulfillment with
     * its REAL default sleeper (book() has no way to inject a test double
     * through the registry), so this deliberately avoids ever exercising the
     * bounded-backoff path, which would sleep for real wall-clock seconds.
     */
    public function test_book_proceeds_normally_when_courier_status_is_not_creating(): void
    {
        $this->enableSpx();
        Http::fake([
            '*batch_create_order*' => Http::response(['ret_code' => 0, 'message' => 'success.', 'data' => ['batch_no' => 1]]),
            '*get_order_create_result*' => Http::response([
                'ret_code' => 0, 'message' => 'success.',
                'data' => [
                    'batch_no' => 1, 'task_status' => 4, 'description' => 'Done',
                    'total_count' => 1, 'success_count' => 1, 'fail_count' => 0, 'progress' => '100%',
                    'orders' => [['order_id' => '1', 'tracking_no' => 'SPEPH000000000001']],
                    'fail_list' => [],
                ],
            ]),
        ]);
        Setting::create(['group' => 'shipping', 'key' => 'pickup_province', 'value' => 'Metro Manila']);
        Setting::create(['group' => 'shipping', 'key' => 'pickup_city', 'value' => 'Makati']);
        Setting::create(['group' => 'shipping', 'key' => 'pickup_barangay', 'value' => 'Bel-Air']);
        Setting::create(['group' => 'shipping', 'key' => 'pickup_address', 'value' => '456 Warehouse Ave']);
        Setting::create(['group' => 'shipping', 'key' => 'pickup_postal_code', 'value' => '1200']);
        Setting::create(['group' => 'shipping', 'key' => 'pickup_contact_number', 'value' => '09171112222']);

        $order = $this->makeOrder(); // courier_status is null — a fresh, never-booked order.

        $result = app(FulfillmentService::class)->book($order, [
            'courier' => 'spx', 'parcel' => 'small', 'shipping_payment' => 'sender_pay',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('SPEPH000000000001', $order->fresh()->courier_tracking_number);
    }
}
