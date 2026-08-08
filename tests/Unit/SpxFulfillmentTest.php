<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Services\ExtensionManager;
use Extensions\CourierFulfillment\Couriers\Spx\SpxApiException;
use Extensions\CourierFulfillment\Couriers\Spx\SpxFulfillment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Task T2 (fulfillment-workflow-v2 + SPX plan): SpxFulfillment's booking,
 * cancellation, tracking, and fee methods. Ground truth for every request/
 * response shape is docs/spx/api.md (verbatim endpoint tables) +
 * docs/spx/integration-brief.md. Http::fake ONLY — zero real outbound
 * requests anywhere in this file.
 *
 * A no-op sleeper is injected into every SpxFulfillment instance below so
 * the bounded-poll/backoff logic (createShipment()'s ~15s window,
 * getTracking()'s 13251 retry) never actually sleeps in the test run.
 */
class SpxFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ExtensionManager::class)->install('courier-fulfillment');
        app(ExtensionManager::class)->bootEnabled();
    }

    private function noSleep(): \Closure
    {
        return function (int $ms): void {
            // intentionally empty
        };
    }

    private function courier(): SpxFulfillment
    {
        return new SpxFulfillment(sleeper: $this->noSleep());
    }

    private function configureAccountCredentials(): void
    {
        Setting::create(['group' => 'courier', 'key' => 'spx_mode', 'value' => 'uat']);
        Setting::create(['group' => 'courier', 'key' => 'spx_app_id', 'value' => '424242']);
        Setting::create(['group' => 'courier', 'key' => 'spx_secret_key', 'value' => \Illuminate\Support\Facades\Crypt::encryptString('fake-app-secret-not-real')]);
        Setting::create(['group' => 'courier', 'key' => 'spx_user_id', 'value' => '999999']);
        Setting::create(['group' => 'courier', 'key' => 'spx_user_secret', 'value' => \Illuminate\Support\Facades\Crypt::encryptString('fake-user-secret-not-real')]);
    }

    /** Courier-agnostic pickup origin (settings group=shipping) — SPX's sender_info source, shared with QuadX/DHL. */
    private function configurePickupOrigin(): void
    {
        Setting::create(['group' => 'shipping', 'key' => 'pickup_province', 'value' => 'Metro Manila']);
        Setting::create(['group' => 'shipping', 'key' => 'pickup_city', 'value' => 'Makati']);
        Setting::create(['group' => 'shipping', 'key' => 'pickup_barangay', 'value' => 'Bel-Air']);
        Setting::create(['group' => 'shipping', 'key' => 'pickup_address', 'value' => '456 Warehouse Ave']);
        Setting::create(['group' => 'shipping', 'key' => 'pickup_postal_code', 'value' => '1200']);
        Setting::create(['group' => 'shipping', 'key' => 'pickup_contact_name', 'value' => 'Warehouse Team']);
        Setting::create(['group' => 'shipping', 'key' => 'pickup_contact_number', 'value' => '09171112222']);
    }

    /** Cebu (non-NCR) delivery address, mirroring FulfillmentTest::makeBookableOrder(). */
    private function makeOrder(array $overrides = []): Order
    {
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Pending', 'sort_order' => 1, 'is_default' => true]);

        return Order::create(array_merge([
            'store_id' => 1,
            'order_status_id' => $status->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'phone' => '+639171234567',
            'shipping_first_name' => 'Jane',
            'shipping_last_name' => 'Doe',
            'shipping_address_1' => '123 Mango St',
            'shipping_barangay' => 'Lahug',
            'shipping_city' => 'Cebu City',
            'shipping_state' => 'Cebu',
            'shipping_postcode' => '6000',
            'shipping_phone' => '+639171234567',
            'payment_method' => 'bank_transfer',
            'subtotal' => 1000,
            'shipping_cost' => 100,
            'total' => 1100,
        ], $overrides));
    }

    private function doneCreateResultResponse(string $trackingNo, int $batchNo = 207982800897461, array $extra = []): array
    {
        return [
            'ret_code' => 0, 'message' => 'success.',
            'data' => array_merge([
                'batch_no' => $batchNo, 'task_status' => 4, 'description' => 'Done',
                'total_count' => 1, 'success_count' => 1, 'fail_count' => 0, 'progress' => '100.00%',
                'orders' => [[
                    'order_id' => '1', 'tracking_no' => $trackingNo,
                    'tracking_no_link' => "https://test.spx.co.th/track?{$trackingNo}",
                    'estimated_shipping_fee' => 25,
                ]],
                'fail_list' => [],
            ], $extra),
        ];
    }

    // ------------------------------------------------------------------
    // createShipment — fast-complete path: payload shape + async resolution.
    // ------------------------------------------------------------------

    public function test_create_shipment_sends_the_documented_payload_shape_and_resolves_fast(): void
    {
        $this->configureAccountCredentials();
        $this->configurePickupOrigin();

        Http::fake([
            '*batch_create_order*' => Http::response(['ret_code' => 0, 'message' => 'success.', 'data' => ['batch_no' => 207982800897461]]),
            '*get_order_create_result*' => Http::response($this->doneCreateResultResponse('SPEPH000000000001')),
        ]);

        $order = $this->makeOrder();
        OrderProduct::create([
            'order_id' => $order->id, 'name' => 'Guitar Strap', 'price' => 500, 'quantity' => 2, 'total' => 1000,
        ]);

        $result = $this->courier()->createShipment($order, [
            'service' => 'standard',
            'parcel'  => 'medium', // Str::slug('Medium')
            'shipping_payment' => 'sender_pay',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('SPEPH000000000001', $result['tracking_number']);
        $this->assertNull($result['status']); // real status comes from the next tracking poll
        $this->assertSame(207982800897461, $result['meta']['batch_no']);

        Http::assertSent(function ($request) use ($order) {
            if (!str_contains($request->url(), 'batch_create_order')) {
                return false;
            }
            $body = $request->data();
            $o = $body['orders'][0];

            return $body['user_id'] === 999999
                && $body['user_secret'] === 'fake-user-secret-not-real'
                && $o['order_id'] === (string) $order->id
                && $o['base_info']['service_type'] === 1
                // sender_info from the shared pickup_* origin.
                && $o['sender_info']['sender_state'] === 'Metro Manila'
                && $o['sender_info']['sender_city'] === 'Makati'
                && $o['sender_info']['sender_district'] === 'Bel-Air'
                && $o['sender_info']['sender_street'] === '456 Warehouse Ave'
                && $o['sender_info']['sender_post_code'] === '1200'
                && $o['sender_info']['sender_phone'] === '09171112222'
                // deliver_info from the order's own shipping address.
                && $o['deliver_info']['deliver_state'] === 'Cebu'
                && $o['deliver_info']['deliver_city'] === 'Cebu City'
                && $o['deliver_info']['deliver_district'] === 'Lahug'
                && $o['deliver_info']['deliver_street'] === '123 Mango St'
                && $o['deliver_info']['deliver_post_code'] === '6000'
                && $o['deliver_info']['deliver_name'] === 'Jane Doe'
                // fulfillment_info: sender_pay -> payment_role 1; not COD -> cod_collection 0; no pickup slot -> drop-off.
                && $o['fulfillment_info']['payment_role'] === 1
                && $o['fulfillment_info']['cod_collection'] === 0
                && !array_key_exists('cod_amount', $o['fulfillment_info'])
                && $o['fulfillment_info']['collect_type'] === 2
                // parcel_info dims come from the "Medium" pouch preset (T1's default: 35x25x15cm/5kg).
                && (float) $o['parcel_info']['parcel_length'] === 35.0
                && (float) $o['parcel_info']['parcel_width'] === 25.0
                && (float) $o['parcel_info']['parcel_height'] === 15.0
                && (float) $o['parcel_info']['parcel_weight'] === 5.0
                && (int) $o['parcel_info']['express_insured_value'] === 0
                && $o['parcel_info']['parcel_item_type'] === 'General merchandise';
        });
    }

    public function test_create_shipment_maps_cod_and_receiver_pay_and_insure(): void
    {
        $this->configureAccountCredentials();
        $this->configurePickupOrigin();

        Http::fake([
            '*batch_create_order*' => Http::response(['ret_code' => 0, 'message' => 'success.', 'data' => ['batch_no' => 1]]),
            '*get_order_create_result*' => Http::response($this->doneCreateResultResponse('SPEPH000000000002', 1)),
        ]);

        $order = $this->makeOrder(['payment_method' => 'cod', 'total' => 1500]);

        $this->courier()->createShipment($order, [
            'parcel' => 'small',
            'shipping_payment' => 'receiver_pay',
            'insure' => true,
        ]);

        Http::assertSent(function ($request) {
            if (!str_contains($request->url(), 'batch_create_order')) {
                return false;
            }
            $o = $request->data()['orders'][0];
            return $o['fulfillment_info']['payment_role'] === 2
                && $o['fulfillment_info']['cod_collection'] === 1
                && $o['fulfillment_info']['cod_amount'] === 1500
                && (float) $o['parcel_info']['express_insured_value'] === 1500.0;
        });
    }

    public function test_create_shipment_uses_custom_dimensions_when_parcel_is_custom(): void
    {
        $this->configureAccountCredentials();
        $this->configurePickupOrigin();

        Http::fake([
            '*batch_create_order*' => Http::response(['ret_code' => 0, 'message' => 'success.', 'data' => ['batch_no' => 1]]),
            '*get_order_create_result*' => Http::response($this->doneCreateResultResponse('SPEPH000000000003', 1)),
        ]);

        $order = $this->makeOrder();

        $result = $this->courier()->createShipment($order, [
            'parcel' => 'custom', 'length_cm' => 60, 'width_cm' => 40, 'height_cm' => 30, 'weight_kg' => 12,
            'shipping_payment' => 'sender_pay',
        ]);

        $this->assertTrue($result['success']);
        Http::assertSent(function ($request) {
            if (!str_contains($request->url(), 'batch_create_order')) {
                return false;
            }
            $p = $request->data()['orders'][0]['parcel_info'];
            return (float) $p['parcel_length'] === 60.0 && (float) $p['parcel_weight'] === 12.0;
        });
    }

    public function test_create_shipment_rejects_custom_parcel_with_missing_dimensions_and_never_calls_spx(): void
    {
        $this->configureAccountCredentials();
        $this->configurePickupOrigin();
        Http::fake();

        $order = $this->makeOrder();
        $result = $this->courier()->createShipment($order, ['parcel' => 'custom', 'shipping_payment' => 'sender_pay']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Custom parcel dimensions', $result['error']);
        Http::assertNothingSent();
    }

    // ------------------------------------------------------------------
    // createShipment — credential + address guards.
    // ------------------------------------------------------------------

    public function test_create_shipment_fails_clearly_without_user_credentials_and_never_calls_spx(): void
    {
        // App-level creds only (isConfigured true), no user_id/user_secret.
        Setting::create(['group' => 'courier', 'key' => 'spx_app_id', 'value' => '424242']);
        Setting::create(['group' => 'courier', 'key' => 'spx_secret_key', 'value' => \Illuminate\Support\Facades\Crypt::encryptString('x')]);
        Http::fake();

        $order = $this->makeOrder();
        $result = $this->courier()->createShipment($order, ['parcel' => 'small', 'shipping_payment' => 'sender_pay']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('User ID / User Secret', $result['error']);
        Http::assertNothingSent();
    }

    public function test_create_shipment_fails_clearly_when_pickup_origin_is_incomplete(): void
    {
        $this->configureAccountCredentials();
        // No pickup_* settings configured at all.
        Http::fake();

        $order = $this->makeOrder();
        $result = $this->courier()->createShipment($order, ['parcel' => 'small', 'shipping_payment' => 'sender_pay']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('pickup address', $result['error']);
        Http::assertNothingSent();
    }

    public function test_create_shipment_fails_clearly_when_order_address_is_incomplete(): void
    {
        $this->configureAccountCredentials();
        $this->configurePickupOrigin();
        Http::fake();

        $order = $this->makeOrder(['shipping_city' => '', 'shipping_state' => '']);
        $result = $this->courier()->createShipment($order, ['parcel' => 'small', 'shipping_payment' => 'sender_pay']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('shipping province/city/postal code', $result['error']);
        Http::assertNothingSent();
    }

    // ------------------------------------------------------------------
    // createShipment — async pending path (bounded poll never completes).
    // ------------------------------------------------------------------

    public function test_create_shipment_persists_creating_status_when_still_pending_after_bounded_poll(): void
    {
        $this->configureAccountCredentials();
        $this->configurePickupOrigin();

        Http::fake([
            '*batch_create_order*' => Http::response(['ret_code' => 0, 'message' => 'success.', 'data' => ['batch_no' => 999]]),
            '*get_order_create_result*' => Http::response([
                'ret_code' => 0, 'message' => 'success.',
                'data' => ['batch_no' => 999, 'task_status' => 3, 'description' => 'Running', 'total_count' => 1, 'success_count' => 0, 'fail_count' => 0, 'progress' => '0%', 'orders' => [], 'fail_list' => []],
            ]),
        ]);

        $order = $this->makeOrder();
        $result = $this->courier()->createShipment($order, ['parcel' => 'small', 'shipping_payment' => 'sender_pay']);

        $this->assertTrue($result['success']);
        $this->assertNull($result['tracking_number']);
        $this->assertSame('creating', $result['status']);
        $this->assertSame(999, $result['meta']['batch_no']);
        $this->assertTrue($result['meta']['pending']);

        // 1 create call + 6 bounded polls (immediate + 5 backoff retries).
        Http::assertSentCount(7);
    }

    public function test_create_shipment_reports_a_failed_async_creation_via_fail_list_not_task_status_alone(): void
    {
        $this->configureAccountCredentials();
        $this->configurePickupOrigin();

        Http::fake([
            '*batch_create_order*' => Http::response(['ret_code' => 0, 'message' => 'success.', 'data' => ['batch_no' => 5]]),
            '*get_order_create_result*' => Http::response([
                'ret_code' => 0, 'message' => 'success.',
                'data' => [
                    'batch_no' => 5, 'task_status' => 4, 'description' => 'Done',
                    'total_count' => 1, 'success_count' => 0, 'fail_count' => 1, 'progress' => '100.00%',
                    'orders' => [],
                    'fail_list' => [['order_id' => '1', 'message' => 'Address invalid.', 'debug_msg' => 'x', 'ret_code' => 10018]],
                ],
            ]),
        ]);

        $order = $this->makeOrder();
        $result = $this->courier()->createShipment($order, ['parcel' => 'small', 'shipping_payment' => 'sender_pay']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Address invalid.', $result['error']);
    }

    // ------------------------------------------------------------------
    // resolvePendingCreation — the courier:poll-status pending-creation pass.
    // ------------------------------------------------------------------

    public function test_resolve_pending_creation_returns_the_tracking_number_once_done(): void
    {
        $this->configureAccountCredentials();

        Http::fake([
            '*get_order_create_result*' => Http::response($this->doneCreateResultResponse('SPEPH000000000009', 42)),
        ]);

        $order = $this->makeOrder();
        $order->forceFill(['courier_provider' => 'spx', 'courier_status' => 'creating', 'courier_meta' => json_encode(['batch_no' => 42])])->save();

        $tracking = $this->courier()->resolvePendingCreation($order);

        $this->assertSame('SPEPH000000000009', $tracking);
    }

    public function test_resolve_pending_creation_returns_null_while_still_running(): void
    {
        $this->configureAccountCredentials();

        Http::fake([
            '*get_order_create_result*' => Http::response([
                'ret_code' => 0, 'message' => 'success.',
                'data' => ['batch_no' => 42, 'task_status' => 2, 'description' => 'Waiting', 'total_count' => 1, 'success_count' => 0, 'fail_count' => 0, 'progress' => '0%', 'orders' => [], 'fail_list' => []],
            ]),
        ]);

        $order = $this->makeOrder();
        $order->forceFill(['courier_provider' => 'spx', 'courier_status' => 'creating', 'courier_meta' => json_encode(['batch_no' => 42])])->save();

        $this->assertNull($this->courier()->resolvePendingCreation($order));
        $this->assertSame('creating', $order->fresh()->courier_status);
    }

    public function test_resolve_pending_creation_records_a_permanent_failure_and_stops_being_creating(): void
    {
        $this->configureAccountCredentials();

        Http::fake([
            '*get_order_create_result*' => Http::response([
                'ret_code' => 0, 'message' => 'success.',
                'data' => [
                    'batch_no' => 7, 'task_status' => 4, 'description' => 'Done',
                    'total_count' => 1, 'success_count' => 0, 'fail_count' => 1, 'progress' => '100%',
                    'orders' => [], 'fail_list' => [['order_id' => '1', 'message' => 'Weight exceeds limit.', 'ret_code' => 10025]],
                ],
            ]),
        ]);

        $order = $this->makeOrder();
        $order->forceFill(['courier_provider' => 'spx', 'courier_status' => 'creating', 'courier_meta' => json_encode(['batch_no' => 7])])->save();

        $this->assertNull($this->courier()->resolvePendingCreation($order));

        $order->refresh();
        $this->assertSame('creation_failed', $order->courier_status);
        $this->assertNull($order->courier_tracking_number);
        $meta = json_decode($order->courier_meta, true);
        $this->assertTrue($meta['creation_failed']);
        $this->assertSame('Weight exceeds limit.', $meta['creation_error']);
    }

    // ------------------------------------------------------------------
    // cancelShipment.
    // ------------------------------------------------------------------

    public function test_cancel_shipment_succeeds(): void
    {
        $this->configureAccountCredentials();
        Http::fake(['*batch_cancel_order*' => Http::response([
            'ret_code' => 0, 'message' => 'success.',
            'data' => ['tracking_no_list' => ['SPEPH1'], 'fail_list' => []],
        ])]);

        $result = $this->courier()->cancelShipment('SPEPH1');
        $this->assertTrue($result['success']);

        Http::assertSent(fn ($r) => $r->data()['tracking_no_list'] === ['SPEPH1']);
    }

    public function test_cancel_shipment_gives_a_friendly_message_on_13002_thrown_at_the_top_level(): void
    {
        $this->configureAccountCredentials();
        Http::fake(['*batch_cancel_order*' => Http::response(['ret_code' => 13002, 'message' => 'OrderUnableCancelCode', 'data' => null])]);

        $result = $this->courier()->cancelShipment('SPEPH1');
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already been picked up', $result['error']);
    }

    public function test_cancel_shipment_gives_a_friendly_message_on_13002_inside_fail_list(): void
    {
        $this->configureAccountCredentials();
        Http::fake(['*batch_cancel_order*' => Http::response([
            'ret_code' => 0, 'message' => 'success.',
            'data' => ['tracking_no_list' => [], 'fail_list' => [['ret_code' => 13002, 'message' => 'Cancel Order Failed.', 'tracking_no' => 'SPEPH1']]],
        ])]);

        $result = $this->courier()->cancelShipment('SPEPH1');
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already been picked up', $result['error']);
    }

    // ------------------------------------------------------------------
    // getTracking.
    // ------------------------------------------------------------------

    private function trackOrderResponse(string $trackingNo, array $overrides = []): array
    {
        return [
            'ret_code' => 0, 'message' => 'success.',
            'data' => ['orders' => [array_merge([
                'tracking_no' => $trackingNo,
                'status' => 'Pending Pickup',
                'status_code' => '1001',
                'tracking_link' => "https://uat.spx.ph/track?{$trackingNo}",
                'routes' => [
                    ['status' => 'Pending Pickup', 'status_code' => '1001', 'message' => 'x', 'timestamp' => 1753171999],
                ],
            ], $overrides)], 'fail_list' => []],
        ];
    }

    public function test_get_tracking_succeeds_and_maps_the_timeline(): void
    {
        $this->configureAccountCredentials();
        Http::fake([
            '*batch_search_order*' => Http::response($this->trackOrderResponse('SPEPH1')),
            '*batch_get_asf*' => Http::response([
                'ret_code' => 0, 'message' => 'success.',
                'data' => ['orders' => [['tracking_no' => 'SPEPH1', 'fee_info' => ['actual_shipping_fee' => 30]]], 'fail_list' => []],
            ]),
        ]);

        $result = $this->courier()->getTracking('SPEPH1');

        $this->assertTrue($result['success']);
        $this->assertSame('1001', $result['status']);
        $this->assertSame(['delivery' => 'https://uat.spx.ph/track?SPEPH1'], $result['links']);
        $this->assertSame([['status' => '1001', 'at' => 1753171999]], $result['timeline']);
        $this->assertSame(['actual_shipping_fee' => 30], $result['fee_info']);
    }

    public function test_get_tracking_retries_on_13251_then_succeeds(): void
    {
        $this->configureAccountCredentials();
        Http::fakeSequence('*batch_search_order*')
            ->push(['ret_code' => 13251, 'message' => 'order id cannot be found', 'data' => null])
            ->push($this->trackOrderResponse('SPEPH1'));
        Http::fake(['*batch_get_asf*' => Http::response(['ret_code' => 0, 'message' => 'success.', 'data' => ['orders' => [], 'fail_list' => []]])]);

        $result = $this->courier()->getTracking('SPEPH1');

        $this->assertTrue($result['success']);
        $this->assertSame('1001', $result['status']);
    }

    public function test_get_tracking_retries_on_13251_inside_fail_list_then_succeeds(): void
    {
        $this->configureAccountCredentials();
        Http::fakeSequence('*batch_search_order*')
            ->push(['ret_code' => 0, 'message' => 'success.', 'data' => ['orders' => [], 'fail_list' => [['ret_code' => 13251, 'message' => 'not found yet', 'tracking_no' => 'SPEPH1']]]])
            ->push($this->trackOrderResponse('SPEPH1'));
        Http::fake(['*batch_get_asf*' => Http::response(['ret_code' => 0, 'message' => 'success.', 'data' => ['orders' => [], 'fail_list' => []]])]);

        $result = $this->courier()->getTracking('SPEPH1');
        $this->assertTrue($result['success']);
    }

    // ------------------------------------------------------------------
    // statusPhase — every code in api.md's order status table.
    // ------------------------------------------------------------------

    public static function statusPhaseProvider(): array
    {
        return [
            'unset status (fresh booking)' => [null, 'to_handover'],
            'async create pending'  => ['creating', 'booking'],
            'async create failed'   => ['creation_failed', 'failed'],
            '1001 Pending Pickup'   => ['1001', 'to_handover'],
            '2001 In Transit'       => ['2001', 'in_transit'],
            '2006 Delivering'       => ['2006', 'in_transit'],
            '3001 On Hold'          => ['3001', 'failed'],
            '4001 Delivered'        => ['4001', 'delivered'],
            '5001 Pickup Failed'    => ['5001', 'failed'],
            '5002 Damaged'          => ['5002', 'failed'],
            '5003 Lost'             => ['5003', 'failed'],
            '6001 Returning'        => ['6001', 'returning'],
            '6002 Return Failed'    => ['6002', 'failed'],
            '6003 Returned'         => ['6003', 'returning'],
            '7001 Cancelled'        => ['7001', 'cancelled'],
        ];
    }

    #[DataProvider('statusPhaseProvider')]
    public function test_every_status_code_lands_in_exactly_one_spine_bucket(?string $status, string $expectedPhase): void
    {
        $result = (new SpxFulfillment())->statusPhase($status);

        $this->assertSame($expectedPhase, $result['phase']);
        $this->assertContains($result['phase'], \Extensions\CourierFulfillment\Support\PhaseTone::PHASES);
        $this->assertArrayHasKey('step', $result);
        $this->assertArrayHasKey('failed', $result);
    }

    public function test_terminal_and_cancelable_statuses_are_consistent_with_the_phase_bucketing(): void
    {
        $courier = new SpxFulfillment();

        foreach ($courier->terminalStatuses() as $status) {
            $this->assertContains($courier->statusPhase($status)['phase'], ['delivered', 'cancelled', 'returning', 'failed']);
        }

        $this->assertSame(['1001'], $courier->cancelableStatuses());
    }

    // ------------------------------------------------------------------
    // estimateFee / getPickupSlots.
    // ------------------------------------------------------------------

    public function test_estimate_fee_returns_the_documented_shape(): void
    {
        $this->configureAccountCredentials();
        $this->configurePickupOrigin();
        Http::fake(['*batch_check_order*' => Http::response([
            'ret_code' => 0, 'message' => 'success.',
            'data' => ['orders' => [['order_id' => '1', 'estimated_shipping_fee' => 23.2, 'basic_shipping_fee' => 29]], 'fail_list' => []],
        ])]);

        $fee = $this->courier()->estimateFee($this->makeOrder(), ['parcel' => 'small', 'shipping_payment' => 'sender_pay']);

        $this->assertNotNull($fee);
        $this->assertSame(23.2, $fee->amount);
        $this->assertSame('PHP', $fee->currency);
        $this->assertIsString($fee->label);
    }

    public function test_estimate_fee_returns_null_when_the_pickup_origin_is_incomplete(): void
    {
        $this->configureAccountCredentials();
        // No pickup_* settings — never even calls SPX for an estimate it can't build.
        Http::fake();

        $fee = $this->courier()->estimateFee($this->makeOrder(), ['parcel' => 'small', 'shipping_payment' => 'sender_pay']);

        $this->assertNull($fee);
        Http::assertNothingSent();
    }

    public function test_get_pickup_slots_returns_the_documented_shape(): void
    {
        $this->configureAccountCredentials();
        Http::fake(['*get_pickup_time*' => Http::response([
            'ret_code' => 0, 'message' => 'success.',
            'data' => [[
                'date' => '2022-02-17', 'pickup_time' => 1645088400,
                'slots' => [
                    ['pickup_time_range_id' => 0, 'pickup_time_range' => 'Now'],
                    ['pickup_time_range_id' => 2, 'pickup_time_range' => '2:00pm-4:00pm'],
                ],
            ]],
        ])]);

        $slots = $this->courier()->getPickupSlots($this->makeOrder());

        $this->assertCount(2, $slots);
        $decoded = json_decode($slots[0]['value'], true);
        $this->assertSame(1645088400, $decoded['pickup_time']);
        $this->assertSame(0, $decoded['pickup_time_range_id']);
        $this->assertStringContainsString('Now', $slots[0]['label']);
    }

    public function test_get_pickup_slots_is_empty_without_user_credentials(): void
    {
        Http::fake();
        $this->assertSame([], (new SpxFulfillment())->getPickupSlots($this->makeOrder()));
        Http::assertNothingSent();
    }

    // ------------------------------------------------------------------
    // Option lists (getParcelOptions honors T1's spx_pouch_presets-or-default carry-forward).
    // ------------------------------------------------------------------

    public function test_get_parcel_options_falls_back_to_default_presets_plus_a_custom_sentinel(): void
    {
        $options = (new SpxFulfillment())->getParcelOptions();

        $values = array_column($options, 'value');
        $this->assertSame(['small', 'medium', 'large', 'custom'], $values);
    }

    public function test_get_parcel_options_reads_the_configured_spx_pouch_presets_setting(): void
    {
        Setting::create(['group' => 'courier', 'key' => 'spx_pouch_presets', 'value' => json_encode([
            ['label' => 'Tiny', 'length_cm' => 10, 'width_cm' => 10, 'height_cm' => 5, 'max_weight_kg' => 1],
        ])]);

        $options = (new SpxFulfillment())->getParcelOptions();
        $this->assertSame(['tiny', 'custom'], array_column($options, 'value'));
    }
}
