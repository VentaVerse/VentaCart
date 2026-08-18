<?php

namespace Tests\Feature\AdminV2;

use App\Models\Order;
use App\Models\OrderStatus;
use Extensions\CourierFulfillment\PlacementMap;
use App\Models\Setting;
use App\Models\User;
use App\Services\ExtensionManager;
use Extensions\CourierFulfillment\CourierManager;
use Extensions\CourierFulfillment\Couriers\Spx\SpxFulfillment;
use Extensions\CourierFulfillment\FulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * fulfillment-placement Task 3: BOOKING SETS THE CORE STATUS, CANCELLING
 * PUTS IT BACK.
 *
 * Task 2 made tab membership read one thing only: the placement of the
 * order's core status. Booking is OUR action and no courier status
 * announces it, so without this task a booked order stays on whatever
 * To-Pack-placed status it was on and can never reach To Handover.
 *
 * The chain this file asserts:
 *
 *   book succeeds -> core status becomes the booked status (to_handover
 *   placed) -> the row moves to To Handover -> that tab's actions (Print
 *   waybill, Cancel booking) are the ones the order needs next.
 *
 *   cancel booking succeeds -> core status returns to a to_pack-placed
 *   status (the one it held before booking when the booking meta recorded
 *   it, else the configured/first fallback) -> the row is back in To Pack,
 *   bookable again.
 */
class FulfillmentBookedStatusTest extends TestCase
{
    use RefreshDatabase;

    private string $prefix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prefix = config('admin.prefix', 'admin');
    }

    protected function tearDown(): void
    {
        $ref = new \ReflectionProperty(CourierManager::class, 'registered');
        $ref->setAccessible(true);
        $registered = $ref->getValue();
        unset($registered['fake'], $registered['stubasync']);
        $ref->setValue(null, $registered);

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Fixtures
    // ------------------------------------------------------------------

    private function queueUrl(): string
    {
        return '/' . $this->prefix . '/extensions/courier-fulfillment';
    }

    private function settingsUrl(): string
    {
        return '/' . $this->prefix . '/extensions/courier-fulfillment/settings';
    }

    private function installExtension(): void
    {
        app(ExtensionManager::class)->install('courier-fulfillment');
        app(ExtensionManager::class)->bootEnabled();
    }

    private function enableQuadxStaging(): void
    {
        Setting::create(['group' => 'courier', 'key' => 'quadx_enabled', 'value' => '1']);
        Setting::create(['group' => 'courier', 'key' => 'quadx_mode', 'value' => 'staging']);
        Setting::create(['group' => 'courier', 'key' => 'quadx_staging_api_key', 'value' => Crypt::encryptString('test-api-key')]);
        Setting::create(['group' => 'courier', 'key' => 'quadx_staging_secret_key', 'value' => Crypt::encryptString('test-secret-key')]);
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

    private function statusPlaced(string $placement, string $name, int $sort = 1): OrderStatus
    {
        $status = OrderStatus::firstOrCreate(
            ['store_id' => 1, 'name' => $name],
            ['sort_order' => $sort],
        );

        PlacementMap::put($status->id, $placement);

        return $status;
    }

    private function makeOrder(?OrderStatus $status = null): Order
    {
        $status = $status ?? $this->statusPlaced('to_pack', 'Processing');

        return Order::create([
            'store_id' => 1,
            'order_status_id' => $status->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'phone' => '09171234567',
            'shipping_first_name' => 'Jane',
            'shipping_last_name' => 'Doe',
            'shipping_address_1' => '123 Mango St',
            'shipping_barangay' => 'Lahug',
            'shipping_city' => 'Cebu City',
            'shipping_state' => 'Cebu',
            'shipping_postcode' => '6000',
            'shipping_phone' => '09171234567',
            'payment_method' => 'bank_transfer',
            'subtotal' => 1000,
            'shipping_cost' => 100,
            'total' => 1100,
        ]);
    }

    private function fakeQuadxBooking(string $tracking = 'QX-BOOK-001'): void
    {
        Http::fake([
            'api.staging.quadx.xyz/v2/orders' => Http::response([
                'tracking_number' => $tracking,
                'status' => 'for_pickup',
            ], 200),
            // Cancel hits /v2/orders/{tracking}/cancel.
            'api.staging.quadx.xyz/v2/orders/*' => Http::response(['ok' => true], 200),
        ]);
    }

    private function bookPayload(): array
    {
        return ['courier' => 'quadx', 'service' => 'next_day', 'parcel' => 'box', 'shipping_payment' => 'net_off'];
    }

    // ------------------------------------------------------------------
    // 1. The sync path: a successful booking applies the booked status.
    // ------------------------------------------------------------------

    public function test_a_successful_sync_booking_applies_the_configured_booked_status(): void
    {
        $this->installExtension();
        $this->enableQuadxStaging();
        $this->fakeQuadxBooking();

        $pack = $this->statusPlaced('to_pack', 'Processing');
        $handover = $this->statusPlaced('to_handover', 'Ready for Pickup');
        Setting::create(['group' => 'courier', 'key' => 'booked_order_status_id', 'value' => (string) $handover->id]);

        $order = $this->makeOrder($pack);

        $res = app(FulfillmentService::class)->book($order, $this->bookPayload());
        $this->assertTrue($res['success']);

        $order->refresh();
        $this->assertSame($handover->id, $order->order_status_id);
        $this->assertSame('to_handover', FulfillmentService::tabBucket($order));

        // One history row, notify OFF, naming the courier and the tracking
        // number so the audit trail explains why the status moved.
        $history = $order->histories()->where('order_status_id', $handover->id)->get();
        $this->assertCount(1, $history);
        $this->assertFalse((bool) $history->first()->notify_customer);
        $this->assertStringContainsString('QX-BOOK-001', $history->first()->comment);
        $this->assertStringContainsString('Ready for Pickup', $history->first()->comment);
    }

    public function test_booking_defaults_to_order_being_shipped_when_the_setting_is_unset(): void
    {
        $this->installExtension();
        $this->enableQuadxStaging();
        $this->fakeQuadxBooking();

        // Two candidates: the named default must win over the first by sort.
        $this->statusPlaced('to_handover', 'Aaa Handover', 1);
        $named = $this->statusPlaced('to_handover', 'Order Being Shipped', 9);

        $order = $this->makeOrder();

        $res = app(FulfillmentService::class)->book($order, $this->bookPayload());
        $this->assertTrue($res['success']);

        $this->assertSame($named->id, $order->fresh()->order_status_id);
        $this->assertDatabaseMissing('settings', ['group' => 'courier', 'key' => 'booked_order_status_id']);
    }

    public function test_booking_falls_back_to_the_first_to_handover_status_when_nothing_is_configured_or_named(): void
    {
        $this->installExtension();
        $this->enableQuadxStaging();
        $this->fakeQuadxBooking();

        $first = $this->statusPlaced('to_handover', 'Awaiting Handover', 1);
        $this->statusPlaced('to_handover', 'Zulu Handover', 5);

        $order = $this->makeOrder();

        $this->assertTrue(app(FulfillmentService::class)->book($order, $this->bookPayload())['success']);
        $this->assertSame($first->id, $order->fresh()->order_status_id);
    }

    /** A configured id whose status was since re-placed elsewhere is ignored, never applied blindly. */
    public function test_a_configured_booked_status_with_the_wrong_placement_is_ignored(): void
    {
        $this->installExtension();
        $this->enableQuadxStaging();
        $this->fakeQuadxBooking();

        $delivered = $this->statusPlaced('delivered', 'Delivered');
        $handover = $this->statusPlaced('to_handover', 'Order Being Shipped');
        Setting::create(['group' => 'courier', 'key' => 'booked_order_status_id', 'value' => (string) $delivered->id]);

        $order = $this->makeOrder();

        $this->assertTrue(app(FulfillmentService::class)->book($order, $this->bookPayload())['success']);
        $this->assertSame($handover->id, $order->fresh()->order_status_id);
    }

    // ------------------------------------------------------------------
    // 2. Review IMPORTANT 2: a booked order NEVER renders an actionless row.
    // ------------------------------------------------------------------

    /**
     * The guarantee, end to end and through the real page: book with nothing
     * configured at all, then load the queue and find the order in a tab
     * whose actions include Cancel booking and Print waybill.
     */
    public function test_a_booked_order_always_lands_in_a_tab_offering_cancel_booking_and_the_waybill(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();
        $this->fakeQuadxBooking('QX-ACTIONS-001');

        $this->statusPlaced('to_handover', 'Order Being Shipped');
        $order = $this->makeOrder();

        $this->post($this->queueUrl() . '/' . $order->id . '/book', $this->bookPayload())
            ->assertRedirect()
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('to_handover', FulfillmentService::tabBucket($order));

        $this->get($this->queueUrl() . '?filter=to_ship&sub=to_handover')
            ->assertOk()
            ->assertSee('QX-ACTIONS-001')
            ->assertSee('Print waybill')
            ->assertSee('Cancel booking')
            ->assertSee('cancel-fulfillment-' . $order->id, false);

        // And it is no longer sitting actionless in To Pack.
        $this->get($this->queueUrl() . '?filter=to_ship&sub=to_pack')
            ->assertOk()
            ->assertDontSee('QX-ACTIONS-001');
    }

    /**
     * The one configuration where the guarantee above could not hold: no
     * order status is placed under To Handover at all, so a booking would
     * have nowhere to move the order to. Refused BEFORE any outbound call,
     * with a message naming the fix - never a shipment we cannot represent.
     */
    public function test_booking_is_refused_when_no_status_is_placed_under_to_handover(): void
    {
        $this->installExtension();
        $this->enableQuadxStaging();
        Http::fake(); // any outbound call here is itself the bug

        $order = $this->makeOrder($this->statusPlaced('to_pack', 'Processing'));

        $res = app(FulfillmentService::class)->book($order, $this->bookPayload());

        $this->assertFalse($res['success']);
        $this->assertSame('no_booked_status', $res['code']);
        $this->assertStringContainsString('To Handover', $res['error']);
        $this->assertStringContainsString('Status Mapping', $res['error']);

        Http::assertNothingSent();
        $order->refresh();
        $this->assertNull($order->courier_tracking_number);
        $this->assertNull($order->courier_provider);
    }

    /** The placement refusal still answers first: its message is the one an operator can act on per row. */
    public function test_the_placement_gate_still_answers_before_the_missing_status_refusal(): void
    {
        $this->installExtension();
        $this->enableQuadxStaging();
        Http::fake();

        $order = $this->makeOrder($this->statusPlaced('unpaid', 'Unpaid'));

        $res = app(FulfillmentService::class)->book($order, $this->bookPayload());

        $this->assertFalse($res['success']);
        $this->assertSame('placement_blocked', $res['code']);
        Http::assertNothingSent();
    }

    // ------------------------------------------------------------------
    // 3. The async path: the status lands when the creation resolves.
    // ------------------------------------------------------------------

    public function test_an_async_creation_resolved_by_the_poll_applies_the_booked_status(): void
    {
        $this->installExtension();
        $this->enableSpx();
        $this->app->make(\Illuminate\Contracts\Console\Kernel::class)
            ->registerCommand(new \Extensions\CourierFulfillment\Console\Commands\PollCourierStatus());

        $pack = $this->statusPlaced('to_pack', 'Processing');
        $handover = $this->statusPlaced('to_handover', 'Order Being Shipped');

        $order = $this->makeOrder($pack);
        $order->forceFill([
            'courier_provider' => 'spx',
            'courier_status' => 'creating',
            'courier_meta' => json_encode(['batch_no' => 555, 'pending' => true, 'pre_booking_order_status_id' => $pack->id]),
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
            '*batch_get_asf*' => Http::response([
                'ret_code' => 0, 'message' => 'success.',
                'data' => ['orders' => [['tracking_no' => 'SPEPH000000000099', 'fee_info' => ['actual_shipping_fee' => 27.5]]], 'fail_list' => []],
            ]),
        ]);

        $this->artisan('courier:poll-status')->assertSuccessful();

        $order->refresh();
        $this->assertSame('SPEPH000000000099', $order->courier_tracking_number);
        $this->assertSame($handover->id, $order->order_status_id);
        $this->assertSame('to_handover', FulfillmentService::tabBucket($order));
        $this->assertCount(1, $order->histories()->where('order_status_id', $handover->id)->get());
    }

    /** A creation still in flight must not move the status: nothing has been booked yet. */
    public function test_a_still_pending_creation_leaves_the_status_alone(): void
    {
        $this->installExtension();
        $this->enableSpx();
        $this->app->make(\Illuminate\Contracts\Console\Kernel::class)
            ->registerCommand(new \Extensions\CourierFulfillment\Console\Commands\PollCourierStatus());

        $pack = $this->statusPlaced('to_pack', 'Processing');
        $this->statusPlaced('to_handover', 'Order Being Shipped');

        $order = $this->makeOrder($pack);
        $order->forceFill([
            'courier_provider' => 'spx',
            'courier_status' => 'creating',
            'courier_meta' => json_encode(['batch_no' => 1]),
        ])->save();

        Http::fake(['*get_order_create_result*' => Http::response([
            'ret_code' => 0, 'message' => 'success.',
            'data' => ['batch_no' => 1, 'task_status' => 2, 'description' => 'Waiting', 'total_count' => 1, 'success_count' => 0, 'fail_count' => 0, 'progress' => '0%', 'orders' => [], 'fail_list' => []],
        ])]);

        $this->artisan('courier:poll-status')->assertSuccessful();

        $order->refresh();
        $this->assertSame($pack->id, $order->order_status_id);
        $this->assertSame(0, $order->histories()->count());
    }

    /** Repeat polls never write a second identical status history row. */
    public function test_repeat_polls_do_not_duplicate_the_booked_status_history(): void
    {
        $this->installExtension();
        $this->enableSpx();
        $this->app->make(\Illuminate\Contracts\Console\Kernel::class)
            ->registerCommand(new \Extensions\CourierFulfillment\Console\Commands\PollCourierStatus());

        $pack = $this->statusPlaced('to_pack', 'Processing');
        $handover = $this->statusPlaced('to_handover', 'Order Being Shipped');

        $order = $this->makeOrder($pack);
        $order->forceFill([
            'courier_provider' => 'spx',
            'courier_status' => 'creating',
            'courier_meta' => json_encode(['batch_no' => 556, 'pending' => true]),
        ])->save();

        Http::fake([
            '*get_order_create_result*' => Http::response([
                'ret_code' => 0, 'message' => 'success.',
                'data' => [
                    'batch_no' => 556, 'task_status' => 4, 'description' => 'Done',
                    'total_count' => 1, 'success_count' => 1, 'fail_count' => 0, 'progress' => '100%',
                    'orders' => [['order_id' => (string) $order->id, 'tracking_no' => 'SPEPH000000000098']],
                    'fail_list' => [],
                ],
            ]),
            '*batch_search_order*' => Http::response([
                'ret_code' => 0, 'message' => 'success.',
                'data' => ['orders' => [[
                    'tracking_no' => 'SPEPH000000000098', 'status' => 'Pending Pickup', 'status_code' => '1001', 'routes' => [],
                ]], 'fail_list' => []],
            ]),
            '*batch_get_asf*' => Http::response([
                'ret_code' => 0, 'message' => 'success.',
                'data' => ['orders' => [], 'fail_list' => []],
            ]),
        ]);

        $this->artisan('courier:poll-status')->assertSuccessful();
        $this->artisan('courier:poll-status')->assertSuccessful();
        $this->artisan('courier:poll-status')->assertSuccessful();

        $order->refresh();
        $this->assertSame($handover->id, $order->order_status_id);
        $this->assertCount(1, $order->histories()->where('order_status_id', $handover->id)->get());
    }

    // ------------------------------------------------------------------
    // 4. Cancel booking returns the order to a To Pack placed status.
    // ------------------------------------------------------------------

    public function test_cancelling_a_booking_returns_the_order_to_the_status_it_held_before_booking(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();
        $this->fakeQuadxBooking('QX-CYCLE-001');

        // Two To Pack statuses: the one the order was on must win over the
        // fallback, so the round trip is genuinely a return, not a reset.
        $waiting = $this->statusPlaced('to_pack', 'Awaiting Packing', 1);
        $custom = $this->statusPlaced('to_pack', 'Custom Build Queue', 5);
        $this->statusPlaced('to_handover', 'Order Being Shipped');
        Setting::create(['group' => 'courier', 'key' => 'unbooked_order_status_id', 'value' => (string) $waiting->id]);

        $order = $this->makeOrder($custom);

        $this->post($this->queueUrl() . '/' . $order->id . '/book', $this->bookPayload())
            ->assertSessionHas('success');
        $this->assertSame('to_handover', FulfillmentService::tabBucket($order->fresh()));

        $this->post($this->queueUrl() . '/' . $order->id . '/cancel')
            ->assertRedirect()
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame($custom->id, $order->order_status_id, 'cancel must return the order to its pre-booking status');
        $this->assertSame('to_pack', FulfillmentService::tabBucket($order));
        $this->assertNull($order->courier_tracking_number);

        $this->assertStringContainsString('Custom Build Queue', session('success'));
        $this->assertDatabaseHas('order_histories', [
            'order_id' => $order->id,
            'order_status_id' => $custom->id,
            'notify_customer' => false,
        ]);
    }

    public function test_cancelling_falls_back_to_the_configured_unbooked_status_without_a_snapshot(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();
        Http::fake(['api.staging.quadx.xyz/v2/orders/*' => Http::response(['ok' => true], 200)]);

        $this->statusPlaced('to_pack', 'Aaa First Pack', 1);
        $configured = $this->statusPlaced('to_pack', 'Repacking', 4);
        $handover = $this->statusPlaced('to_handover', 'Order Being Shipped');
        Setting::create(['group' => 'courier', 'key' => 'unbooked_order_status_id', 'value' => (string) $configured->id]);

        // A booking made before this task shipped: no pre-booking status in meta.
        $order = $this->makeOrder($handover);
        $order->forceFill([
            'courier_provider' => 'quadx',
            'courier_tracking_number' => 'QX-LEGACY-001',
            'courier_status' => 'for_pickup',
            'courier_meta' => json_encode([]),
        ])->save();

        $this->post($this->queueUrl() . '/' . $order->id . '/cancel')->assertSessionHas('success');

        $order->refresh();
        $this->assertSame($configured->id, $order->order_status_id);
        $this->assertSame('to_pack', FulfillmentService::tabBucket($order));
    }

    public function test_cancelling_falls_back_to_the_first_to_pack_status_when_nothing_is_configured(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();
        Http::fake(['api.staging.quadx.xyz/v2/orders/*' => Http::response(['ok' => true], 200)]);

        $first = $this->statusPlaced('to_pack', 'Awaiting Packing', 1);
        $this->statusPlaced('to_pack', 'Repacking', 4);
        $handover = $this->statusPlaced('to_handover', 'Order Being Shipped');

        $order = $this->makeOrder($handover);
        $order->forceFill([
            'courier_provider' => 'quadx',
            'courier_tracking_number' => 'QX-LEGACY-002',
            'courier_status' => 'for_pickup',
        ])->save();

        $this->post($this->queueUrl() . '/' . $order->id . '/cancel')->assertSessionHas('success');

        $this->assertSame($first->id, $order->fresh()->order_status_id);
    }

    /** A pre-booking status that has since been re-placed is not restored blindly. */
    public function test_a_re_placed_pre_booking_status_falls_back_instead_of_being_restored(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();
        Http::fake(['api.staging.quadx.xyz/v2/orders/*' => Http::response(['ok' => true], 200)]);

        $moved = $this->statusPlaced('delivered', 'Was To Pack Once');
        $fallback = $this->statusPlaced('to_pack', 'Awaiting Packing', 1);
        $handover = $this->statusPlaced('to_handover', 'Order Being Shipped');

        $order = $this->makeOrder($handover);
        $order->forceFill([
            'courier_provider' => 'quadx',
            'courier_tracking_number' => 'QX-MOVED-001',
            'courier_status' => 'for_pickup',
            'courier_meta' => json_encode(['pre_booking_order_status_id' => $moved->id]),
        ])->save();

        $this->post($this->queueUrl() . '/' . $order->id . '/cancel')->assertSessionHas('success');

        $this->assertSame($fallback->id, $order->fresh()->order_status_id);
    }

    /** The audit snapshot keeps the pre-booking status alongside the cancelled booking. */
    public function test_the_booking_history_snapshot_records_the_pre_booking_status(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();
        $this->fakeQuadxBooking('QX-SNAP-001');

        $pack = $this->statusPlaced('to_pack', 'Awaiting Packing');
        $this->statusPlaced('to_handover', 'Order Being Shipped');
        $order = $this->makeOrder($pack);

        $this->post($this->queueUrl() . '/' . $order->id . '/book', $this->bookPayload())->assertSessionHas('success');

        $meta = json_decode($order->fresh()->courier_meta, true);
        $this->assertSame($pack->id, $meta['pre_booking_order_status_id']);

        $this->post($this->queueUrl() . '/' . $order->id . '/cancel')->assertSessionHas('success');

        $meta = json_decode($order->fresh()->courier_meta, true);
        $this->assertCount(1, $meta['booking_history']);
        $this->assertSame($pack->id, $meta['booking_history'][0]['previous_order_status_id']);
    }

    // ------------------------------------------------------------------
    // 5. The hub settings.
    // ------------------------------------------------------------------

    public function test_the_hub_scopes_each_select_to_its_own_placement(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $handover = $this->statusPlaced('to_handover', 'Order Being Shipped');
        $pack = $this->statusPlaced('to_pack', 'Awaiting Packing');
        $this->statusPlaced('delivered', 'DeliveredOnlyStatus');

        $this->get($this->settingsUrl())
            ->assertOk()
            ->assertSee('Booked order status')
            ->assertSee('Unbooked order status')
            ->assertSee('Order Being Shipped')
            ->assertSee('Awaiting Packing')
            ->assertDontSee('DeliveredOnlyStatus')
            ->assertSee('<option value="' . $handover->id . '"', false)
            ->assertSee('<option value="' . $pack->id . '"', false);
    }

    public function test_the_hub_rejects_a_booked_status_that_is_not_placed_under_to_handover(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->statusPlaced('to_handover', 'Order Being Shipped');
        $pack = $this->statusPlaced('to_pack', 'Awaiting Packing');

        $this->get($this->settingsUrl())->assertOk(); // prime routes

        $this->post($this->settingsUrl(), ['settings' => ['booked_order_status_id' => (string) $pack->id]])
            ->assertSessionHasErrors('settings.booked_order_status_id');

        $this->assertDatabaseMissing('settings', ['group' => 'courier', 'key' => 'booked_order_status_id']);
    }

    public function test_the_hub_rejects_an_unbooked_status_that_is_not_placed_under_to_pack(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $handover = $this->statusPlaced('to_handover', 'Order Being Shipped');
        $this->statusPlaced('to_pack', 'Awaiting Packing');

        $this->get($this->settingsUrl())->assertOk();

        $this->post($this->settingsUrl(), ['settings' => ['unbooked_order_status_id' => (string) $handover->id]])
            ->assertSessionHasErrors('settings.unbooked_order_status_id');

        $this->assertDatabaseMissing('settings', ['group' => 'courier', 'key' => 'unbooked_order_status_id']);
    }

    public function test_the_hub_saves_and_clears_both_status_settings(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $handover = $this->statusPlaced('to_handover', 'Order Being Shipped');
        $pack = $this->statusPlaced('to_pack', 'Awaiting Packing');

        $this->get($this->settingsUrl())->assertOk();

        $this->post($this->settingsUrl(), ['settings' => [
            'booked_order_status_id' => (string) $handover->id,
            'unbooked_order_status_id' => (string) $pack->id,
        ]])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('settings', ['group' => 'courier', 'key' => 'booked_order_status_id', 'value' => (string) $handover->id]);
        $this->assertDatabaseHas('settings', ['group' => 'courier', 'key' => 'unbooked_order_status_id', 'value' => (string) $pack->id]);

        // The "Use default" placeholder removes the override entirely rather
        // than persisting an empty value.
        $this->post($this->settingsUrl(), ['settings' => [
            'booked_order_status_id' => '',
            'unbooked_order_status_id' => '',
        ]])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('settings', ['group' => 'courier', 'key' => 'booked_order_status_id']);
        $this->assertDatabaseMissing('settings', ['group' => 'courier', 'key' => 'unbooked_order_status_id']);
    }

    // ------------------------------------------------------------------
    // 6. The ERP idempotent path is untouched by any of this.
    // ------------------------------------------------------------------

    public function test_an_already_booked_erp_retry_changes_no_status_and_writes_no_history(): void
    {
        $this->installExtension();
        $this->enableQuadxStaging();
        Http::fake();

        $handover = $this->statusPlaced('to_handover', 'Order Being Shipped');
        $this->statusPlaced('to_pack', 'Awaiting Packing');

        $order = $this->makeOrder($handover);
        $order->forceFill([
            'courier_provider' => 'quadx',
            'courier_tracking_number' => 'QX-RETRY-001',
            'courier_status' => 'for_pickup',
        ])->save();

        $res = app(FulfillmentService::class)->book($order, $this->bookPayload());

        $this->assertTrue($res['success']);
        $this->assertTrue($res['already_booked']);
        $order->refresh();
        $this->assertSame($handover->id, $order->order_status_id);
        $this->assertSame(0, $order->histories()->count());
        Http::assertNothingSent();
    }
}
