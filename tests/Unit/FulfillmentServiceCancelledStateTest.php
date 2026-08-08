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
 * Final-review fix (Finding 1): FulfillmentService::isActivelyBooked() must
 * treat "cancelled" PHASE-first — via the resolved courier's own
 * statusPhase() spine bucket — not only the literal 'canceled'/'cancelled'
 * strings the admin Cancel action writes.
 *
 * The hazard: admin cancels an SPX booking ('canceled' is written), then
 * SPX's next poll or its 7001 webhook overwrites courier_status with the
 * courier-vocabulary '7001'. The literal-only check then read the order as
 * actively booked FOREVER — Book/Rebook gone, Cancel gone (7001 isn't
 * cancelable), and Print waybill offered on a shipment whose AWB is void.
 *
 * The literal strings remain the fallback for a booking whose provider is
 * no longer registered; QuadX's own cancelled statuses ARE those literals
 * (and bucket to the cancelled phase anyway), so its behavior is unchanged
 * on either path — its pre-existing tests stay byte-untouched as the
 * contract for that.
 */
class FulfillmentServiceCancelledStateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ExtensionManager::class)->install('courier-fulfillment');
        app(ExtensionManager::class)->bootEnabled(); // registers QuadX + SPX into CourierManager
    }

    private function enableSpx(): void
    {
        Setting::create(['group' => 'courier', 'key' => 'spx_enabled', 'value' => '1']);
        Setting::create(['group' => 'courier', 'key' => 'spx_app_id', 'value' => '424242']);
        Setting::create(['group' => 'courier', 'key' => 'spx_secret_key', 'value' => Crypt::encryptString('fake-app-secret-not-real')]);
        Setting::create(['group' => 'courier', 'key' => 'spx_user_id', 'value' => '999999']);
        Setting::create(['group' => 'courier', 'key' => 'spx_user_secret', 'value' => Crypt::encryptString('fake-user-secret-not-real')]);
    }

    private function configurePickupOrigin(): void
    {
        Setting::create(['group' => 'shipping', 'key' => 'pickup_province', 'value' => 'Metro Manila']);
        Setting::create(['group' => 'shipping', 'key' => 'pickup_city', 'value' => 'Makati']);
        Setting::create(['group' => 'shipping', 'key' => 'pickup_barangay', 'value' => 'Bel-Air']);
        Setting::create(['group' => 'shipping', 'key' => 'pickup_address', 'value' => '456 Warehouse Ave']);
        Setting::create(['group' => 'shipping', 'key' => 'pickup_postal_code', 'value' => '1200']);
        Setting::create(['group' => 'shipping', 'key' => 'pickup_contact_number', 'value' => '09171112222']);
    }

    private function makeOrder(array $courierFill = []): Order
    {
        $status = OrderStatus::firstOrCreate(
            ['store_id' => 1, 'name' => 'Pending'],
            ['sort_order' => 1, 'is_default' => true],
        );
        // fulfillment-placement Task 3: a successful booking moves the order
        // onto a To-Handover-placed status, and is refused when the store has
        // none (FulfillmentService::bookedStatus()). Seeded on a real install
        // by OrderStatusSeeder; built by hand here like every other status.
        OrderStatus::firstOrCreate(
            ['store_id' => 1, 'name' => 'Order Being Shipped'],
            ['sort_order' => 4, 'fulfillment_placement' => 'to_handover'],
        );

        $order = Order::create([
            'store_id' => 1, 'order_status_id' => $status->id,
            'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com',
            'shipping_first_name' => 'Jane', 'shipping_last_name' => 'Doe',
            'shipping_address_1' => '123 Mango St', 'shipping_barangay' => 'Lahug',
            'shipping_city' => 'Cebu City', 'shipping_state' => 'Cebu', 'shipping_postcode' => '6000',
            'shipping_phone' => '09171234567', 'payment_method' => 'bank_transfer',
            'subtotal' => 1000, 'shipping_cost' => 100, 'total' => 1100,
        ]);

        if ($courierFill !== []) {
            $order->forceFill($courierFill)->save();
        }

        return $order;
    }

    public function test_spx_7001_is_not_actively_booked_while_in_flight_statuses_still_are(): void
    {
        $cases = [
            // status => expected isActivelyBooked
            '1001'      => true,   // pending pickup
            '2001'      => true,   // in transit
            '4001'      => true,   // delivered — a real (finished) shipment, never rebookable
            '5002'      => true,   // damaged — failed but still a real shipment
            '7001'      => false,  // SPX's own cancelled code (the Finding 1 hazard)
            'canceled'  => false,  // admin-cancel literal, pre-poll
            'cancelled' => false,
        ];

        foreach ($cases as $status => $expected) {
            $order = $this->makeOrder([
                'courier_provider' => 'spx',
                'courier_tracking_number' => 'SPEPH-' . $status,
                'courier_status' => $status,
            ]);
            $this->assertSame($expected, FulfillmentService::isActivelyBooked($order), "status={$status}");
        }
    }

    public function test_quadx_literal_behavior_is_unchanged(): void
    {
        $canceled = $this->makeOrder([
            'courier_provider' => 'quadx',
            'courier_tracking_number' => 'QX-1',
            'courier_status' => 'canceled',
        ]);
        $this->assertFalse(FulfillmentService::isActivelyBooked($canceled));

        $active = $this->makeOrder([
            'courier_provider' => 'quadx',
            'courier_tracking_number' => 'QX-2',
            'courier_status' => 'for_pickup',
        ]);
        $this->assertTrue(FulfillmentService::isActivelyBooked($active));
    }

    public function test_unregistered_provider_falls_back_to_the_literal_strings(): void
    {
        $canceled = $this->makeOrder([
            'courier_provider' => 'some-retired-courier',
            'courier_tracking_number' => 'XX-1',
            'courier_status' => 'cancelled',
        ]);
        $this->assertFalse(FulfillmentService::isActivelyBooked($canceled));

        $active = $this->makeOrder([
            'courier_provider' => 'some-retired-courier',
            'courier_tracking_number' => 'XX-2',
            'courier_status' => 'whatever',
        ]);
        $this->assertTrue(FulfillmentService::isActivelyBooked($active));
    }

    /**
     * The whole point of Finding 1: a 7001 order must fall through book()'s
     * idempotency guard and create a FRESH shipment, with the superseded
     * tracking number preserved in meta + history (same audit trail the
     * QuadX cancel->rebook flow has always produced).
     *
     * Fast-complete create-result fake only (task_status Done on the very
     * first poll) — CourierManager::get() constructs SpxFulfillment with its
     * real wall-clock sleeper, so the bounded-backoff path must never be
     * exercised here (same caveat as FulfillmentServiceCreatingGuardTest).
     */
    public function test_spx_order_cancelled_as_7001_is_rebookable(): void
    {
        $this->enableSpx();
        $this->configurePickupOrigin();

        Http::fake([
            '*batch_create_order*' => Http::response(['ret_code' => 0, 'message' => 'success.', 'data' => ['batch_no' => 2]]),
            '*get_order_create_result*' => Http::response([
                'ret_code' => 0, 'message' => 'success.',
                'data' => [
                    'batch_no' => 2, 'task_status' => 4, 'description' => 'Done',
                    'total_count' => 1, 'success_count' => 1, 'fail_count' => 0, 'progress' => '100%',
                    'orders' => [['order_id' => '1', 'tracking_no' => 'SPEPH000000000NEW']],
                    'fail_list' => [],
                ],
            ]),
        ]);

        $order = $this->makeOrder([
            'courier_provider' => 'spx',
            'courier_tracking_number' => 'SPEPH000000000OLD',
            'courier_status' => '7001', // stamped by the poll/webhook after the cancel
            'courier_meta' => json_encode(['batch_no' => 1]),
        ]);

        $result = app(FulfillmentService::class)->book($order, [
            'courier' => 'spx', 'parcel' => 'small', 'shipping_payment' => 'sender_pay',
        ]);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['already_booked']);
        $this->assertSame('SPEPH000000000NEW', $result['tracking_number']);

        $order->refresh();
        $this->assertSame('SPEPH000000000NEW', $order->courier_tracking_number);
        $meta = json_decode($order->courier_meta, true);
        $this->assertSame('SPEPH000000000OLD', $meta['previous_tracking_number']);

        $this->assertDatabaseHas('order_histories', [
            'order_id' => $order->id,
            'comment' => 'Rebooked with courier: SPEPH000000000OLD superseded by SPEPH000000000NEW',
        ]);
    }
}
