<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Services\ExtensionManager;
use Extensions\CourierFulfillment\Console\Commands\PollCourierStatus;
use Extensions\CourierFulfillment\CourierManager;
use Extensions\CourierFulfillment\Couriers\Spx\SpxFulfillment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeCourierFulfillment;
use Tests\TestCase;

/**
 * Task T2 (fulfillment-workflow-v2 + SPX plan): courier:poll-status's NEW
 * pending-creation pass (resolvePendingCreations() in PollCourierStatus) —
 * orders sitting in courier_status='creating' get resolved via each
 * courier's resolvePendingCreation(), same as an SPX async booking that
 * didn't finish inside createShipment()'s own bounded poll window.
 *
 * Also proves the sanctioned interface addition (resolvePendingCreation())
 * doesn't disturb the EXISTING mixed-courier queue: a FakeCourierFulfillment
 * order (never 'creating') sits alongside an SPX one and is left untouched.
 */
class SpxPollCourierStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ExtensionManager::class)->install('courier-fulfillment');
        app(ExtensionManager::class)->bootEnabled();
        $this->app->make(\Illuminate\Contracts\Console\Kernel::class)
            ->registerCommand(new PollCourierStatus());
    }

    protected function tearDown(): void
    {
        $ref = new \ReflectionProperty(CourierManager::class, 'registered');
        $ref->setAccessible(true);
        $registered = $ref->getValue();
        unset($registered['fake']);
        $ref->setValue(null, $registered);

        parent::tearDown();
    }

    private function makeOrder(array $overrides = []): Order
    {
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Pending', 'sort_order' => 1, 'is_default' => true]);

        return Order::create(array_merge([
            'store_id' => 1, 'order_status_id' => $status->id,
            'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com',
            'shipping_first_name' => 'Jane', 'shipping_last_name' => 'Doe',
            'shipping_address_1' => '123 Mango St', 'shipping_city' => 'Cebu City',
            'shipping_state' => 'Cebu', 'shipping_postcode' => '6000',
            'payment_method' => 'bank_transfer', 'subtotal' => 1000, 'shipping_cost' => 100, 'total' => 1100,
        ], $overrides));
    }

    private function enableSpx(): void
    {
        CourierManager::register(SpxFulfillment::class);
        Setting::create(['group' => 'courier', 'key' => 'spx_enabled', 'value' => '1']);
        Setting::create(['group' => 'courier', 'key' => 'spx_app_id', 'value' => '424242']);
        Setting::create(['group' => 'courier', 'key' => 'spx_secret_key', 'value' => Crypt::encryptString('fake-app-secret-not-real')]);
        Setting::create(['group' => 'courier', 'key' => 'spx_user_id', 'value' => '999999']);
        Setting::create(['group' => 'courier', 'key' => 'spx_user_secret', 'value' => Crypt::encryptString('fake-user-secret-not-real')]);
    }

    public function test_pending_creation_pass_resolves_a_creating_order_and_the_same_run_tracks_it(): void
    {
        $this->enableSpx();

        $order = $this->makeOrder();
        $order->forceFill([
            'courier_provider' => 'spx',
            'courier_status' => 'creating',
            'courier_meta' => json_encode(['batch_no' => 555, 'pending' => true]),
        ])->save();

        Http::fake([
            '*get_order_create_result*' => Http::response([
                'ret_code' => 0, 'message' => 'success.',
                'data' => [
                    'batch_no' => 555, 'task_status' => 4, 'description' => 'Done',
                    'total_count' => 1, 'success_count' => 1, 'fail_count' => 0, 'progress' => '100%',
                    'orders' => [['order_id' => (string) $order->id, 'tracking_no' => 'SPEPH000000000099', 'estimated_shipping_fee' => 25]],
                    'fail_list' => [],
                ],
            ]),
            '*batch_search_order*' => Http::response([
                'ret_code' => 0, 'message' => 'success.',
                'data' => ['orders' => [[
                    'tracking_no' => 'SPEPH000000000099', 'status' => 'Pending Pickup', 'status_code' => '1001',
                    'tracking_link' => 'https://uat.spx.ph/track?SPEPH000000000099', 'routes' => [],
                ]], 'fail_list' => []],
            ]),
            '*batch_get_asf*' => Http::response(['ret_code' => 0, 'message' => 'success.', 'data' => ['orders' => [], 'fail_list' => []]]),
        ]);

        $this->artisan('courier:poll-status')->assertSuccessful();

        $order->refresh();
        $this->assertSame('SPEPH000000000099', $order->courier_tracking_number);
        $this->assertSame('SPEPH000000000099', $order->tracking_number);
        // The main loop (same run) already tracked it, since it now has a
        // tracking number and a null status.
        $this->assertSame('1001', $order->courier_status);
    }

    public function test_pending_creation_pass_leaves_a_still_running_order_untouched(): void
    {
        $this->enableSpx();

        $order = $this->makeOrder();
        $order->forceFill([
            'courier_provider' => 'spx', 'courier_status' => 'creating',
            'courier_meta' => json_encode(['batch_no' => 1]),
        ])->save();

        Http::fake(['*get_order_create_result*' => Http::response([
            'ret_code' => 0, 'message' => 'success.',
            'data' => ['batch_no' => 1, 'task_status' => 2, 'description' => 'Waiting', 'total_count' => 1, 'success_count' => 0, 'fail_count' => 0, 'progress' => '0%', 'orders' => [], 'fail_list' => []],
        ])]);

        $this->artisan('courier:poll-status')->assertSuccessful();

        $order->refresh();
        $this->assertSame('creating', $order->courier_status);
        $this->assertNull($order->courier_tracking_number);
    }

    /**
     * Mixed-queue proof: a FakeCourierFulfillment order (booked, non-'creating')
     * sits in the same run as an SPX order — the pending-creation pass never
     * touches it (FakeCourierFulfillment::resolvePendingCreation() is a
     * permanent no-op, and its query for courier_status='creating' never
     * matches this order in the first place), and the existing per-courier
     * getTracking() loop below still runs independently, unaffected by the
     * interface's new method existing.
     */
    public function test_mixed_queue_of_fake_and_spx_orders_is_unaffected(): void
    {
        CourierManager::register(FakeCourierFulfillment::class);
        $this->enableSpx();

        $fakeOrder = $this->makeOrder();
        $fakeOrder->forceFill([
            'courier_provider' => 'fake', 'courier_tracking_number' => 'FAKE-1', 'courier_status' => 'pending',
        ])->save();

        $spxOrder = $this->makeOrder();
        $spxOrder->forceFill([
            'courier_provider' => 'spx', 'courier_status' => 'creating',
            'courier_meta' => json_encode(['batch_no' => 2]),
        ])->save();

        Http::fake(['*get_order_create_result*' => Http::response([
            'ret_code' => 0, 'message' => 'success.',
            'data' => ['batch_no' => 2, 'task_status' => 2, 'description' => 'Waiting', 'total_count' => 1, 'success_count' => 0, 'fail_count' => 0, 'progress' => '0%', 'orders' => [], 'fail_list' => []],
        ])]);

        $this->artisan('courier:poll-status')->assertSuccessful();

        $fakeOrder->refresh();
        $spxOrder->refresh();
        // FakeCourierFulfillment::isEnabled() is always false -> not in the
        // main getTracking() loop either -> completely untouched.
        $this->assertSame('pending', $fakeOrder->courier_status);
        $this->assertSame('FAKE-1', $fakeOrder->courier_tracking_number);
        $this->assertSame('creating', $spxOrder->courier_status);
    }
}
