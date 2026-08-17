<?php

namespace Tests\Feature\AdminV2;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\ShippingCourier;
use App\Models\User;
use App\Services\ExtensionManager;
use Extensions\CourierFulfillment\CourierManager;
use Extensions\CourierFulfillment\FulfillmentService;
use Extensions\CourierFulfillment\PlacementMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\Support\StubSyncCourierFulfillment;
use Tests\TestCase;

/**
 * The third way a parcel leaves: a courier this app does not integrate with,
 * recorded by hand.
 *
 * Both integrated couriers refuse a destination they do not serve (QuadX
 * answers 422 "Delivery address is not serviceable"), so the merchant's real
 * workflow for those is to arrange an LBC by phone and write it down. What is
 * asserted here is that writing it down is ALL that happens: core's own
 * shipping_courier_id / tracking_number / tracking_link columns are filled,
 * courier_provider stays null, and courier:poll-status never sees it.
 *
 * Http::preventStrayRequests() is on for every test in this file. Not one of
 * these paths may reach a network, and a test that accidentally does must fail
 * loudly rather than book a real parcel.
 */
class FulfillmentManualShipmentTest extends TestCase
{
    use RefreshDatabase;

    private string $prefix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prefix = config('admin.prefix', 'admin');
        Http::preventStrayRequests();
    }

    /** CourierManager's registry is process-global static state (same convention as FulfillmentQueueTest). */
    protected function tearDown(): void
    {
        $ref = new \ReflectionProperty(CourierManager::class, 'registered');
        $ref->setAccessible(true);
        $registered = $ref->getValue();
        unset($registered['stubsync']);
        $ref->setValue(null, $registered);

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Fixtures
    // ------------------------------------------------------------------

    private function queueUrl(): string
    {
        return '/' . $this->prefix . '/sales/fulfillment';
    }

    private function installExtension(): void
    {
        app(ExtensionManager::class)->install('courier-fulfillment');
        app(ExtensionManager::class)->bootEnabled();
        // The extension registers its command from its own provider's boot,
        // which has already run by the time a test installs it. Registering
        // it explicitly is the same thing FulfillmentController::poll() does
        // for the same reason (SpxPollCourierStatusTest sets the precedent).
        app(\Illuminate\Contracts\Console\Kernel::class)
            ->registerCommand(new \Extensions\CourierFulfillment\Console\Commands\PollCourierStatus());
    }

    private function enableQuadxStaging(): void
    {
        Setting::create(['group' => 'courier', 'key' => 'quadx_enabled', 'value' => '1']);
        Setting::create(['group' => 'courier', 'key' => 'quadx_mode', 'value' => 'staging']);
        Setting::create(['group' => 'courier', 'key' => 'quadx_staging_api_key', 'value' => Crypt::encryptString('test-api-key')]);
        Setting::create(['group' => 'courier', 'key' => 'quadx_staging_secret_key', 'value' => Crypt::encryptString('test-secret-key')]);
    }

    /** The store's own courier list, exactly as the real install ships it. */
    private function lbc(): ShippingCourier
    {
        return ShippingCourier::firstOrCreate(
            ['store_id' => 1, 'name' => 'LBC Express'],
            [
                'tracking_url' => 'https://lbconline.lbcexpress.com/tracking/{tracking_number}',
                'is_active'    => true,
                'sort_order'   => 1,
            ],
        );
    }

    private function jt(): ShippingCourier
    {
        return ShippingCourier::firstOrCreate(
            ['store_id' => 1, 'name' => 'J&T Express'],
            [
                'tracking_url' => 'https://www.jtexpress.ph/trajectoryQuery?billcode={tracking_number}',
                'is_active'    => true,
                'sort_order'   => 2,
            ],
        );
    }

    private function statusWithStage(string $placement, string $name): OrderStatus
    {
        $status = OrderStatus::firstOrCreate(
            ['store_id' => 1, 'name' => $name],
            ['sort_order' => 1],
        );

        PlacementMap::put($status->id, $placement);

        return $status;
    }

    private function defaultStatus(): OrderStatus
    {
        $status = OrderStatus::firstOrCreate(
            ['store_id' => 1, 'name' => 'Processing'],
            ['sort_order' => 1, 'is_default' => true],
        );

        PlacementMap::put($status->id, 'to_pack');

        return $status;
    }

    /** A To-Pack-placed order in a store that also has somewhere to put a shipped one. */
    private function makeOrder(?OrderStatus $status = null): Order
    {
        $this->statusWithStage('to_handover', 'Order Being Shipped');

        return Order::create([
            'store_id'            => 1,
            'order_status_id'     => ($status ?? $this->defaultStatus())->id,
            'first_name'          => 'Jane',
            'last_name'           => 'Doe',
            'email'               => 'jane@example.com',
            'shipping_first_name' => 'Jane',
            'shipping_last_name'  => 'Doe',
            // Caluya, Antique: the island municipality QuadX refuses with 422
            // "Delivery address is not serviceable" and SPX marks Support
            // Deliver = 0. This feature exists for exactly this address.
            'shipping_address_1'  => 'Poblacion',
            'shipping_city'       => 'Caluya',
            'shipping_state'      => 'Antique',
            'payment_method'      => 'bank_transfer',
            'subtotal'            => 1000,
            'shipping_cost'       => 100,
            'total'               => 1100,
        ]);
    }

    private function manualUrl(Order $order): string
    {
        return $this->queueUrl() . '/' . $order->id . '/book-manual';
    }

    private function clearUrl(Order $order): string
    {
        return $this->queueUrl() . '/' . $order->id . '/clear-manual';
    }

    // ------------------------------------------------------------------
    // Recording
    // ------------------------------------------------------------------

    public function test_recording_a_manual_shipment_stores_the_courier_tracking_number_and_built_tracking_link(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $lbc = $this->lbc();
        $order = $this->makeOrder();

        $this->post($this->manualUrl($order), [
            'shipping_courier_id' => $lbc->id,
            'tracking_number'     => 'LBC123456789',
            'note'                => 'Customer collects at LBC Cubao branch',
        ])->assertRedirect();

        $order->refresh();

        $this->assertSame($lbc->id, $order->shipping_courier_id);
        $this->assertSame('LBC123456789', $order->tracking_number);
        // Built from the courier's own {tracking_number} template, not guessed.
        $this->assertSame(
            'https://lbconline.lbcexpress.com/tracking/LBC123456789',
            $order->getRawOriginal('tracking_link'),
        );
        // Core's snapshot column is stamped by the model's own saving hook, so
        // renaming or deleting the courier later cannot rewrite this order.
        $this->assertSame('LBC Express', $order->shipping_courier_name);

        // Not a courier booking, in any column an integration owns.
        $this->assertNull($order->courier_provider);
        $this->assertNull($order->courier_tracking_number);
        $this->assertNull($order->courier_status);

        // The note lands in the order's own history, which is where a
        // record-keeping fact belongs.
        $this->assertDatabaseHas('order_histories', ['order_id' => $order->id]);
        $this->assertStringContainsString(
            'Customer collects at LBC Cubao branch',
            (string) $order->histories()->latest('id')->first()?->comment,
        );
    }

    public function test_a_manual_shipment_with_no_tracking_number_is_valid(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $lbc = $this->lbc();
        $order = $this->makeOrder();

        $this->post($this->manualUrl($order), ['shipping_courier_id' => $lbc->id])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $order->refresh();

        $this->assertSame($lbc->id, $order->shipping_courier_id);
        $this->assertNull($order->tracking_number);
        // No number means no link: a tracking page with no consignment on it
        // is worse than no link at all.
        $this->assertNull($order->getRawOriginal('tracking_link'));
        $this->assertTrue(FulfillmentService::isManuallyShipped($order));
    }

    public function test_an_empty_tracking_number_is_stored_as_null_not_an_empty_string(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $order = $this->makeOrder();

        $this->post($this->manualUrl($order), [
            'shipping_courier_id' => $this->lbc()->id,
            'tracking_number'     => '   ',
            'note'                => '  ',
        ])->assertRedirect();

        $order->refresh();
        $this->assertNull($order->tracking_number);
        $this->assertNull($order->getRawOriginal('tracking_link'));
    }

    public function test_recording_requires_an_active_courier_from_the_stores_own_list(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $retired = ShippingCourier::create([
            'store_id'     => 1,
            'name'         => 'Retired Courier',
            'tracking_url' => 'https://example.test/{tracking_number}',
            'is_active'    => false,
        ]);
        $order = $this->makeOrder();

        $this->post($this->manualUrl($order), ['shipping_courier_id' => $retired->id])
            ->assertSessionHasErrors('shipping_courier_id');

        $this->post($this->manualUrl($order), ['shipping_courier_id' => 999999])
            ->assertSessionHasErrors('shipping_courier_id');

        $this->post($this->manualUrl($order), [])
            ->assertSessionHasErrors('shipping_courier_id');

        $this->assertNull($order->refresh()->shipping_courier_id);
    }

    // ------------------------------------------------------------------
    // The load-bearing claim: the poller cannot see it
    // ------------------------------------------------------------------

    public function test_courier_poll_status_never_touches_a_manual_shipment(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        $order = $this->makeOrder();
        $this->post($this->manualUrl($order), [
            'shipping_courier_id' => $this->lbc()->id,
            'tracking_number'     => 'LBC777',
        ])->assertRedirect();

        $order->refresh();
        // The mechanism itself, stated: no provider key, so neither of the
        // poller's two `where('courier_provider', $courier->getKey())` loops
        // can select this row. Nothing excludes it; nothing has to.
        $this->assertNull($order->courier_provider);

        $before = $order->only([
            'shipping_courier_id', 'tracking_number', 'courier_provider',
            'courier_status', 'courier_tracking_number', 'order_status_id',
        ]);
        $historyCount = $order->histories()->count();

        // A registered courier whose getTracking()/resolvePendingCreation()
        // would mutate any order handed to it. Http::preventStrayRequests()
        // means a real outbound call would fail the test outright, so the
        // assertion below is about SELECTION, not about the network.
        CourierManager::register(StubSyncCourierFulfillment::class);

        $this->artisan('courier:poll-status')->assertExitCode(0);

        $order->refresh();
        $this->assertSame($before, $order->only(array_keys($before)));
        $this->assertSame($historyCount, $order->histories()->count());
    }

    public function test_the_poll_command_reports_no_in_flight_orders_for_a_manual_shipment(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        $order = $this->makeOrder();
        $this->post($this->manualUrl($order), [
            'shipping_courier_id' => $this->lbc()->id,
            'tracking_number'     => 'LBC888',
        ])->assertRedirect();

        // QuadX is enabled and would poll anything carrying its provider key.
        // The count it reports is the direct read of what it selected.
        $this->artisan('courier:poll-status')
            ->expectsOutputToContain('Polled 0 in-flight order(s)')
            ->assertExitCode(0);
    }

    // ------------------------------------------------------------------
    // It moves by order status alone
    // ------------------------------------------------------------------

    public function test_recording_moves_the_order_onto_the_booked_status_and_it_advances_by_status_from_there(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $handover = $this->statusWithStage('to_handover', 'Order Being Shipped');
        $shipping = $this->statusWithStage('shipping', 'Shipped');
        $delivered = $this->statusWithStage('delivered', 'Delivered');

        $order = $this->makeOrder();
        $this->assertSame('to_pack', FulfillmentService::tabBucket($order));

        $this->post($this->manualUrl($order), [
            'shipping_courier_id' => $this->lbc()->id,
            'tracking_number'     => 'LBC-TABS-1',
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame($handover->id, (int) $order->order_status_id);
        $this->assertSame('to_handover', FulfillmentService::tabBucket($order));

        // From here the admin advances it exactly as on any ordinary store:
        // by changing the order status, with no parallel state machine.
        $order->forceFill(['order_status_id' => $shipping->id])->save();
        $this->assertSame('shipping', FulfillmentService::tabBucket($order->refresh()));

        $order->forceFill(['order_status_id' => $delivered->id])->save();
        $this->assertSame('delivered', FulfillmentService::tabBucket($order->refresh()));

        // And it never grew any courier state on the way.
        $this->assertNull($order->courier_provider);
        $this->assertNull($order->courier_status);
    }

    public function test_recording_is_refused_when_the_order_is_not_placed_under_to_pack(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $delivered = $this->statusWithStage('delivered', 'Delivered');
        $order = $this->makeOrder($delivered);

        $this->post($this->manualUrl($order), ['shipping_courier_id' => $this->lbc()->id])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNull($order->refresh()->shipping_courier_id);
    }

    // ------------------------------------------------------------------
    // Mutual exclusion with the integrated couriers
    // ------------------------------------------------------------------

    public function test_an_order_booked_with_an_integrated_courier_cannot_be_manually_recorded_over_the_top(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $handover = $this->statusWithStage('to_handover', 'Order Being Shipped');
        $order = $this->makeOrder($handover);
        $order->forceFill([
            'courier_provider'        => 'quadx',
            'courier_tracking_number' => 'QX-LIVE-1',
            'courier_status'          => 'for_pickup',
        ])->save();

        $this->post($this->manualUrl($order), ['shipping_courier_id' => $this->lbc()->id])
            ->assertRedirect()
            ->assertSessionHas('error');

        $order->refresh();
        $this->assertNull($order->shipping_courier_id);
        $this->assertSame('QX-LIVE-1', $order->courier_tracking_number);
    }

    public function test_an_order_with_a_recorded_manual_shipment_cannot_be_booked_with_an_integrated_courier(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $order = $this->makeOrder();
        $this->post($this->manualUrl($order), [
            'shipping_courier_id' => $this->lbc()->id,
            'tracking_number'     => 'LBC-HOLD-1',
        ])->assertRedirect();

        // Straight at the shared booking core, so the refusal is proven at the
        // rule and not merely at a hidden button.
        CourierManager::register(StubSyncCourierFulfillment::class);

        $result = app(FulfillmentService::class)->book($order->refresh(), [
            'courier'          => 'stubsync',
            'service'          => 'standard',
            'parcel'           => 'box',
            'shipping_payment' => 'net_off',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('manually_shipped', $result['code']);

        $order->refresh();
        $this->assertNull($order->courier_provider);
        $this->assertSame('LBC-HOLD-1', $order->tracking_number);
    }

    public function test_recording_twice_is_refused_until_the_first_record_is_cleared(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $order = $this->makeOrder();
        $this->post($this->manualUrl($order), [
            'shipping_courier_id' => $this->lbc()->id,
            'tracking_number'     => 'LBC-FIRST',
        ])->assertRedirect();

        $this->post($this->manualUrl($order), [
            'shipping_courier_id' => $this->jt()->id,
            'tracking_number'     => 'JT-SECOND',
        ])->assertRedirect()->assertSessionHas('error');

        $order->refresh();
        $this->assertSame('LBC-FIRST', $order->tracking_number);
        $this->assertSame($this->lbc()->id, $order->shipping_courier_id);
    }

    // ------------------------------------------------------------------
    // Undo
    // ------------------------------------------------------------------

    public function test_clearing_a_manual_shipment_returns_the_order_to_unbooked(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $order = $this->makeOrder();
        $preStatusId = (int) $order->order_status_id;

        $this->post($this->manualUrl($order), [
            'shipping_courier_id' => $this->lbc()->id,
            'tracking_number'     => 'LBC-UNDO-1',
        ])->assertRedirect();

        $this->assertSame('to_handover', FulfillmentService::tabBucket($order->refresh()));

        $this->post($this->clearUrl($order))->assertRedirect()->assertSessionHas('success');

        $order->refresh();
        $this->assertNull($order->shipping_courier_id);
        $this->assertNull($order->tracking_number);
        $this->assertNull($order->getRawOriginal('tracking_link'));
        $this->assertNull($order->tracking_link);
        $this->assertFalse(FulfillmentService::isManuallyShipped($order));
        // Back to exactly the status it was recorded from, not a generic
        // fallback, and therefore back into To Pack.
        $this->assertSame($preStatusId, (int) $order->order_status_id);
        $this->assertSame('to_pack', FulfillmentService::tabBucket($order));

        // What happened is still on the record.
        $this->assertStringContainsString(
            'Recorded shipment cleared',
            (string) $order->histories()->latest('id')->first()?->comment,
        );
        $history = json_decode((string) $order->courier_meta, true)['booking_history'] ?? [];
        $this->assertCount(1, $history);
        $this->assertSame('LBC-UNDO-1', $history[0]['tracking_number']);
        $this->assertTrue($history[0]['manual']);
    }

    public function test_a_cleared_order_can_be_recorded_again_with_a_different_courier(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $order = $this->makeOrder();

        $this->post($this->manualUrl($order), [
            'shipping_courier_id' => $this->lbc()->id,
            'tracking_number'     => 'LBC-WRONG',
        ])->assertRedirect();
        $this->post($this->clearUrl($order))->assertRedirect();

        $this->post($this->manualUrl($order->refresh()), [
            'shipping_courier_id' => $this->jt()->id,
            'tracking_number'     => 'JT-RIGHT',
        ])->assertRedirect()->assertSessionHas('success');

        $order->refresh();
        $this->assertSame($this->jt()->id, $order->shipping_courier_id);
        $this->assertSame('JT-RIGHT', $order->tracking_number);
        $this->assertSame(
            'https://www.jtexpress.ph/trajectoryQuery?billcode=JT-RIGHT',
            $order->getRawOriginal('tracking_link'),
        );
        // The first, wrong record is still in the audit trail beside the new one.
        $history = json_decode((string) $order->courier_meta, true)['booking_history'] ?? [];
        $this->assertCount(1, $history);
        $this->assertSame('LBC-WRONG', $history[0]['tracking_number']);
    }

    public function test_a_cleared_order_can_be_booked_with_an_integrated_courier_instead(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $order = $this->makeOrder();
        $this->post($this->manualUrl($order), ['shipping_courier_id' => $this->lbc()->id])->assertRedirect();
        $this->post($this->clearUrl($order))->assertRedirect();

        CourierManager::register(StubSyncCourierFulfillment::class);

        $result = app(FulfillmentService::class)->book($order->refresh(), [
            'courier'          => 'stubsync',
            'service'          => 'standard',
            'parcel'           => 'box',
            'shipping_payment' => 'net_off',
        ]);

        $this->assertTrue($result['success'], $result['error'] ?? '');
        $this->assertSame('stubsync', $order->refresh()->courier_provider);
    }

    public function test_clearing_is_refused_for_an_order_that_was_never_manually_recorded(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $order = $this->makeOrder();

        $this->post($this->clearUrl($order))->assertRedirect()->assertSessionHas('error');
    }

    public function test_clearing_is_refused_once_the_order_has_moved_past_the_to_ship_steps(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $delivered = $this->statusWithStage('delivered', 'Delivered');
        $order = $this->makeOrder();
        $this->post($this->manualUrl($order), [
            'shipping_courier_id' => $this->lbc()->id,
            'tracking_number'     => 'LBC-GONE',
        ])->assertRedirect();

        $order->refresh()->forceFill(['order_status_id' => $delivered->id])->save();

        $this->post($this->clearUrl($order))->assertRedirect()->assertSessionHas('error');

        $order->refresh();
        $this->assertSame($this->lbc()->id, $order->shipping_courier_id);
        $this->assertSame('LBC-GONE', $order->tracking_number);
    }

    // ------------------------------------------------------------------
    // The queue
    // ------------------------------------------------------------------

    public function test_the_booking_modal_offers_the_manual_choice_alongside_the_registered_couriers(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();
        $this->lbc();
        $this->makeOrder();

        $response = $this->get($this->queueUrl());

        $response->assertOk();
        $response->assertSee('data-courier-card="quadx"', false);
        $response->assertSee('data-courier-card="__manual"', false);
        $response->assertSee('Another courier');
        // Its own form, its own action, and the store's courier list on it.
        $response->assertSee('book-manual', false);
        $response->assertSee('name="shipping_courier_id"', false);
        $response->assertSee('LBC Express');
    }

    public function test_the_manual_choice_is_offered_even_when_no_integrated_courier_is_configured(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->lbc();
        $this->makeOrder();

        $response = $this->get($this->queueUrl());

        $response->assertOk();
        $response->assertSee('data-courier-card="__manual"', false);
        // The row can open the booking modal. openBooking(JSON.parse(...)) is
        // the row's own Book button; the drawer's static openBooking(active)
        // is a different call, so this substring is unambiguous.
        $response->assertSee('openBooking(JSON.parse(', false);
    }

    public function test_the_manual_choice_is_absent_when_the_store_keeps_no_active_courier(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        ShippingCourier::create([
            'store_id'     => 1,
            'name'         => 'Retired Courier',
            'tracking_url' => 'https://example.test/{tracking_number}',
            'is_active'    => false,
        ]);
        $this->makeOrder();

        $response = $this->get($this->queueUrl());

        $response->assertOk();
        $response->assertDontSee('data-courier-card="__manual"', false);
        $response->assertDontSee('Retired Courier');
    }

    public function test_the_queue_shows_a_manual_shipment_distinctly_and_hides_awb_cancel_booking_and_refresh(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        $order = $this->makeOrder();
        $this->post($this->manualUrl($order), [
            'shipping_courier_id' => $this->lbc()->id,
            'tracking_number'     => 'LBC-QUEUE-1',
        ])->assertRedirect();

        // Recording moved it to To Handover, so that is the tab it is read in.
        $response = $this->get($this->queueUrl() . '?filter=to_ship&sub=to_handover');

        $response->assertOk();
        // Named plainly, with the quiet marker beside it.
        $response->assertSee('LBC Express');
        $response->assertSee('class="v3-factchip">Manual<', false);
        // The tracking link stays clickable, built from the template.
        $response->assertSee('https://lbconline.lbcexpress.com/tracking/LBC-QUEUE-1', false);
        $response->assertSee('LBC-QUEUE-1');

        // None of the three actions that need an integration. Asserted on the
        // ROW's own hooks, because the drawer's Alpine template carries the
        // words "Cancel booking" and "Refresh status" in every page's markup
        // whether or not any row can reach them.
        $response->assertDontSee('Print waybill');
        $response->assertDontSee($this->queueUrl() . '/' . $order->id . '/label', false);
        $response->assertDontSee("cancel-fulfillment-{$order->id}", false);
        $response->assertDontSee($this->queueUrl() . '/' . $order->id . '/cancel', false);
        // No drawer opener either: there is no timeline, no courier status and
        // no waybill behind it.
        $response->assertDontSee('openDrawer(JSON.parse(', false);
        // Nor a second booking over the top of it.
        $response->assertDontSee('openBooking(JSON.parse(', false);
        // The undo is there.
        $response->assertSee('Clear shipment');
        $response->assertSee($this->queueUrl() . '/' . $order->id . '/clear-manual', false);
    }

    public function test_a_manual_shipment_with_no_tracking_number_still_reads_as_a_shipment_in_the_queue(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $order = $this->makeOrder();
        $this->post($this->manualUrl($order), ['shipping_courier_id' => $this->lbc()->id])->assertRedirect();

        $response = $this->get($this->queueUrl() . '?filter=to_ship&sub=to_handover');

        $response->assertOk();
        $response->assertSee('LBC Express');
        $response->assertSee('class="v3-factchip">Manual<', false);
        $response->assertSee('Clear shipment');
    }

    public function test_an_integrated_booking_is_unaffected_by_the_manual_path(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->lbc();

        CourierManager::register(StubSyncCourierFulfillment::class);

        $order = $this->makeOrder();
        $result = app(FulfillmentService::class)->book($order, [
            'courier'          => 'stubsync',
            'service'          => 'standard',
            'parcel'           => 'box',
            'shipping_payment' => 'net_off',
        ]);

        $this->assertTrue($result['success'], $result['error'] ?? '');

        $order->refresh();
        $this->assertSame('stubsync', $order->courier_provider);
        $this->assertNotNull($order->courier_tracking_number);
        // An integrated booking never writes the manual columns, so the two
        // mechanisms stay tellable apart by exactly the predicate that defines
        // them.
        $this->assertNull($order->shipping_courier_id);
        $this->assertFalse(FulfillmentService::isManuallyShipped($order));
    }

    // ------------------------------------------------------------------
    // The order status the operator names (owner directive, 2026-08-12:
    // "Add in manual booking the order status change"). Nothing is sent to a
    // carrier here, so the operator states plainly what state the order is
    // now in.
    // ------------------------------------------------------------------

    public function test_the_manual_form_offers_every_order_status_defaulted_to_the_one_it_would_have_applied(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->lbc();

        $handover = $this->statusWithStage('to_handover', 'Order Being Shipped');
        $this->statusWithStage('delivered', 'Delivered');
        $this->makeOrder();

        $response = $this->get($this->queueUrl())->assertOk();

        // The same list the order page offers, never a subset this module
        // invents.
        $response->assertSee('name="order_status_id"', false)
            ->assertSee('Order Being Shipped')
            ->assertSee('Delivered')
            // Defaulted to what the module would have applied on its own, so
            // an operator who ignores the field gets today's behaviour.
            ->assertSee('value="' . $handover->id . '" selected', false);
    }

    public function test_the_chosen_status_is_what_gets_applied_through_the_normal_status_path(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->statusWithStage('to_handover', 'Order Being Shipped');
        $shipping = $this->statusWithStage('shipping', 'Shipped');

        $order = $this->makeOrder();

        $this->post($this->manualUrl($order), [
            'shipping_courier_id' => $this->lbc()->id,
            'tracking_number'     => 'LBC-STATUS-1',
            'order_status_id'     => $shipping->id,
        ])->assertRedirect()->assertSessionHas('success');

        $order->refresh();
        $this->assertSame($shipping->id, (int) $order->order_status_id);
        $this->assertSame('shipping', FulfillmentService::tabBucket($order));

        // History carries it in the same shape every other status change
        // does: one row, on the new status, saying what happened.
        $history = $order->histories()->latest('id')->first();
        $this->assertSame($shipping->id, (int) $history->order_status_id);
        $this->assertStringContainsString('Shipment recorded with LBC Express', (string) $history->comment);
        $this->assertStringContainsString('Order status set to Shipped', (string) $history->comment);

        // And the record says which status the operator chose.
        $meta = json_decode((string) $order->courier_meta, true);
        $this->assertSame($shipping->id, (int) $meta['manual_shipment']['applied_order_status_id']);
    }

    public function test_omitting_the_status_keeps_todays_behaviour_exactly(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $handover = $this->statusWithStage('to_handover', 'Order Being Shipped');
        $order = $this->makeOrder();

        $this->post($this->manualUrl($order), ['shipping_courier_id' => $this->lbc()->id])
            ->assertRedirect()->assertSessionHas('success');

        $order->refresh();
        $this->assertSame($handover->id, (int) $order->order_status_id);
        $meta = json_decode((string) $order->courier_meta, true);
        $this->assertNull($meta['manual_shipment']['applied_order_status_id']);
    }

    public function test_a_status_that_is_not_a_real_order_status_is_refused(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->statusWithStage('to_handover', 'Order Being Shipped');

        $order = $this->makeOrder();

        $this->post($this->manualUrl($order), [
            'shipping_courier_id' => $this->lbc()->id,
            'order_status_id'     => 999999,
        ])->assertSessionHasErrors('order_status_id');

        $this->assertNull($order->refresh()->shipping_courier_id);
    }

    public function test_clearing_still_returns_the_order_to_where_it_was_recorded_from_after_a_non_default_choice(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        // A second To-Handover-placed status, so the operator's choice is
        // genuinely not the default one and clearing is still offered.
        $this->statusWithStage('to_handover', 'Order Being Shipped');
        $handedToRider = $this->statusWithStage('to_handover', 'Handed to rider');

        $order = $this->makeOrder();
        $preStatusId = (int) $order->order_status_id;

        $this->post($this->manualUrl($order), [
            'shipping_courier_id' => $this->lbc()->id,
            'tracking_number'     => 'LBC-UNDO-2',
            'order_status_id'     => $handedToRider->id,
        ])->assertRedirect();

        $this->assertSame($handedToRider->id, (int) $order->refresh()->order_status_id);

        $this->post($this->clearUrl($order))->assertRedirect()->assertSessionHas('success');

        $order->refresh();
        $this->assertSame($preStatusId, (int) $order->order_status_id);
        $this->assertSame('to_pack', FulfillmentService::tabBucket($order));

        // The audit row remembers which status it had been put in.
        $history = json_decode((string) $order->courier_meta, true)['booking_history'] ?? [];
        $this->assertSame($handedToRider->id, (int) $history[0]['applied_order_status_id']);
    }
}
