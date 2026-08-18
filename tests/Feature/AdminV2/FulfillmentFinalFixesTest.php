<?php

namespace Tests\Feature\AdminV2;

use App\Models\Country;
use App\Models\Order;
use App\Models\OrderStatus;
use Extensions\CourierFulfillment\PlacementMap;
use App\Models\Setting;
use App\Models\User;
use App\Services\ExtensionManager;
use Extensions\CourierFulfillment\CourierManager;
use Extensions\CourierFulfillment\Models\PickupAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Final-review fixes for the Fulfillment Workflow v2 + SPX plan:
 *
 *  - Finding 1: an SPX booking cancelled under its own vocabulary ('7001')
 *    must read as NOT actively booked — Rebook offered, Print waybill gone,
 *    and the label route refusing server-side (api.md §1.6: cancelled
 *    orders have no printable AWB).
 *  - Finding 2: a permanently failed async creation
 *    (courier_status='creation_failed') must be VISIBLE — Failed tab count
 *    + badge, courier_meta.creation_error rendered, Book available (and
 *    not blocked by the 'creating' guard) — and a still-pending async
 *    booking must flash an honest "Booking submitted…" message, never
 *    "Booked — tracking .".
 *
 * QuadX behavior is byte-frozen — its own pre-existing tests (untouched)
 * are the contract for that; nothing here posts a QuadX payload.
 */
class FulfillmentFinalFixesTest extends TestCase
{
    use RefreshDatabase;

    private string $prefix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prefix = config('admin.prefix', 'admin');
    }

    /** Same process-global registry caveat as FulfillmentQueueTest::tearDown(). */
    protected function tearDown(): void
    {
        $ref = new \ReflectionProperty(CourierManager::class, 'registered');
        $ref->setAccessible(true);
        $registered = $ref->getValue();
        unset($registered['stubasync']);
        $ref->setValue(null, $registered);

        parent::tearDown();
    }

    private function queueUrl(): string
    {
        return '/' . $this->prefix . '/extensions/courier-fulfillment';
    }

    private function installExtension(): void
    {
        app(ExtensionManager::class)->install('courier-fulfillment');
        // Registers the extension's runtime PSR-4 autoloader up front, so
        // this file can reference Extensions\CourierFulfillment\* (the
        // module-owned placement mapping) before its first HTTP request.
        app(ExtensionManager::class)->bootEnabled();
    }

    private function enableSpx(): void
    {
        Setting::create(['group' => 'courier', 'key' => 'spx_enabled', 'value' => '1']);
        Setting::create(['group' => 'courier', 'key' => 'spx_mode', 'value' => 'uat']);
        Setting::create(['group' => 'courier', 'key' => 'spx_app_id', 'value' => '424242']);
        Setting::create(['group' => 'courier', 'key' => 'spx_secret_key', 'value' => Crypt::encryptString('fake-app-secret-not-real')]);
        Setting::create(['group' => 'courier', 'key' => 'spx_user_id', 'value' => '999999']);
        Setting::create(['group' => 'courier', 'key' => 'spx_user_secret', 'value' => Crypt::encryptString('fake-user-secret-not-real')]);
    }

    /**
     * The store's pickup origin. It used to be flat
     * settings(group=shipping, key=pickup_*) rows; it is now a record the
     * fulfillment module owns. Same values, so the sender_info SPX receives
     * is unchanged (contact_name is spelled out because the column is
     * required, and 'Sender' is exactly what the settings version fell back
     * to when no pickup_contact_name existed).
     */
    private function configureSpxPickupOrigin(): void
    {
        $country = Country::firstOrCreate(
            ['iso2' => 'PH'],
            ['iso3' => 'PHL', 'name' => 'Philippines', 'phone_code' => '63', 'is_active' => true]
        );

        PickupAddress::create([
            'label'         => 'Store pickup',
            'contact_name'  => 'Sender',
            'contact_phone' => '09171112222',
            'address_1'     => '456 Warehouse Ave',
            'barangay'      => 'Bel-Air',
            'district'      => null,
            'city'          => 'Makati',
            'state'         => 'Metro Manila',
            'postcode'      => '1200',
            'country_id'    => $country->id,
        ]);
    }

    /** Complete shipping address (SPX's buildOrderPayload() needs it), mirrors FulfillmentQueueTest::makeCompleteOrder(). */
    private function makeCompleteOrder(): Order
    {
        $status = OrderStatus::firstOrCreate(
            ['store_id' => 1, 'name' => 'Processing'],
            ['sort_order' => 1, 'is_default' => true],
        );
        PlacementMap::put($status->id, 'to_pack');
        // fulfillment-placement Task 3: booking moves the order onto a
        // To-Handover-placed status and is refused when the store has none
        // (FulfillmentService::bookedStatus()). Seeded on a real install by
        // OrderStatusSeeder; built by hand here, same as the other statuses.
        PlacementMap::put(OrderStatus::firstOrCreate(
            ['store_id' => 1, 'name' => 'Order Being Shipped'],
            ['sort_order' => 4],
        )->id, 'to_handover');

        return Order::create([
            'store_id' => 1, 'order_status_id' => $status->id,
            'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com',
            'phone' => '09171234567',
            'shipping_first_name' => 'Jane', 'shipping_last_name' => 'Doe',
            'shipping_address_1' => '123 Mango St', 'shipping_barangay' => 'Lahug',
            'shipping_city' => 'Cebu City', 'shipping_state' => 'Cebu', 'shipping_postcode' => '6000',
            'shipping_phone' => '09171234567', 'payment_method' => 'bank_transfer',
            'subtotal' => 1000, 'shipping_cost' => 100, 'total' => 1100,
        ]);
    }

    // ------------------------------------------------------------------
    // Finding 1: SPX '7001' reads as NOT actively booked - no waybill
    // offer, no cancel affordance, and the label route refusing server-side.
    //
    // fulfillment-placement Task 2 REWRITE: the original asserted this row
    // landed in the Cancelled TAB (tabs-v courier-phase membership) with a
    // Rebook button. Membership now reads the core status's placement, so
    // the row stays under To Pack (nothing re-statused it), and Finding 1's
    // actual subject - that a 7001 booking is dead, so no live-booking
    // action is offered and Book IS - is asserted there instead.
    // ------------------------------------------------------------------

    public function test_spx_7001_order_offers_booking_again_and_never_print_waybill_or_cancel(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableSpx();

        $order = $this->makeCompleteOrder();
        $order->forceFill([
            'courier_provider' => 'spx',
            'courier_tracking_number' => 'SPEPH000000000CXL',
            'courier_status' => '7001',
        ])->save();

        $this->get($this->queueUrl())
            ->assertOk()
            ->assertSee('data-count-for="to_pack">1<', false)
            ->assertSee('data-count-for="cancelled">0<', false)
            ->assertSee('data-count-for="to_handover">0<', false)
            ->assertSee('SPEPH000000000CXL')
            // The dead booking does not block a fresh one.
            ->assertSee('openBooking(JSON.parse(', false)
            ->assertDontSee('Print waybill')
            // No Cancel affordance either - the booking is already cancelled.
            ->assertDontSee('cancel-fulfillment-' . $order->id, false);
    }

    public function test_label_route_refuses_a_cancelled_spx_shipment_server_side_even_with_a_cached_pdf(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableSpx();
        Storage::fake('local');
        Http::fake();

        $order = $this->makeCompleteOrder();
        $order->forceFill([
            'courier_provider' => 'spx',
            'courier_tracking_number' => 'SPEPH000000000CXL',
            'courier_status' => '7001',
            'courier_meta' => json_encode(['batch_no' => 1]),
        ])->save();

        // A label cached while the booking was still live — must NOT serve.
        Storage::disk('local')->put('courier-labels/spx/SPEPH000000000CXL.pdf', '%PDF stale voided label');

        // Boot the extension's routes (BootExtensions runs on the first
        // request), then hit the label route directly — the UI never links
        // it for a cancelled row, so this is the direct-URL hardening path.
        $this->get($this->queueUrl())->assertOk();
        $response = $this->get($this->queueUrl() . '/' . $order->id . '/label');

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('cancelled', session('error'));
        Http::assertNothingSent();
    }

    // ------------------------------------------------------------------
    // Finding 2: creation_failed — visible, explained, rebookable.
    // ------------------------------------------------------------------

    private function makeCreationFailedOrder(string $reason = 'SPX rejected the delivery address'): Order
    {
        $order = $this->makeCompleteOrder();
        $order->forceFill([
            'courier_provider' => 'spx',
            'courier_status' => 'creation_failed',
            'courier_tracking_number' => null,
            'courier_meta' => json_encode([
                'batch_no' => 99, 'creation_failed' => true, 'creation_error' => $reason,
            ]),
        ])->save();

        return $order;
    }

    /**
     * fulfillment-placement Task 2 REWRITE: the original asserted the
     * creation_failed order counted under the Failed TAB (tabs-v
     * courier-phase membership). Finding 2's real subject is that a
     * permanently failed async create must be VISIBLE and rebookable rather
     * than silently blending in: it now stays in its own tab (To Pack, since
     * nothing re-statused it), which is also where Book is offered, and its
     * badge, label and error still say exactly what happened.
     */
    public function test_creation_failed_order_shows_its_error_and_a_book_action_in_its_own_tab(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableSpx();

        $order = $this->makeCreationFailedOrder();

        $this->get($this->queueUrl())
            ->assertOk()
            ->assertSee('data-count-for="to_pack">1<', false)
            ->assertSee('data-count-for="failed">0<', false)
            ->assertSee('#' . $order->id, false)
            // Spine badge + the honest bridging-status label: the SHIPMENT
            // failed, which the row says plainly even though the tab is
            // decided by the order's status.
            ->assertSee('Failed')
            ->assertSee('Booking failed')
            // The actual reason (courier_meta.creation_error) is rendered.
            ->assertSee('SPX rejected the delivery address')
            // Book action present (openBooking payload).
            ->assertSee('openBooking(JSON.parse(', false);

        $this->get($this->queueUrl() . '?filter=failed')
            ->assertOk()
            ->assertDontSee('#' . $order->id, false);
    }

    public function test_creation_failed_order_can_be_booked_again(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableSpx();
        $this->configureSpxPickupOrigin();

        $order = $this->makeCreationFailedOrder();

        // Fast-complete fake only — the route path constructs SpxFulfillment
        // with its REAL sleeper, so the bounded-backoff path must never run.
        Http::fake([
            '*batch_create_order*' => Http::response(['ret_code' => 0, 'message' => 'success.', 'data' => ['batch_no' => 100]]),
            '*get_order_create_result*' => Http::response([
                'ret_code' => 0, 'message' => 'success.',
                'data' => [
                    'batch_no' => 100, 'task_status' => 4, 'description' => 'Done',
                    'total_count' => 1, 'success_count' => 1, 'fail_count' => 0, 'progress' => '100.00%',
                    'orders' => [['order_id' => (string) $order->id, 'tracking_no' => 'SPXPH000000000RETRY']],
                    'fail_list' => [],
                ],
            ]),
        ]);

        $response = $this->post($this->queueUrl() . '/' . $order->id . '/book', [
            'courier' => 'spx', 'service' => 'standard', 'parcel' => 'medium',
            'shipping_payment' => 'sender_pay', 'insure' => '0', 'pickup_slot' => '',
        ]);

        // The T2 'creating' guard must NOT block a creation_failed order —
        // that create is settled-and-dead, not in flight.
        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertStringContainsString('SPXPH000000000RETRY', session('success'));

        $order->refresh();
        $this->assertSame('SPXPH000000000RETRY', $order->courier_tracking_number);
        // The stale failure record does not survive the successful rebook.
        $meta = json_decode($order->courier_meta, true);
        $this->assertArrayNotHasKey('creation_error', $meta);
    }

    public function test_async_pending_booking_flashes_a_booking_submitted_message_not_a_blank_tracking(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        // A stub ASYNC courier whose createShipment() returns the pending
        // shape (success, no tracking number, status 'creating') — see
        // Tests\Support\StubAsyncCourierFulfillment's docblock for why it
        // lives in its own support file rather than inline here.
        CourierManager::register(\Tests\Support\StubAsyncCourierFulfillment::class);

        $order = $this->makeCompleteOrder();

        $response = $this->post($this->queueUrl() . '/' . $order->id . '/book', [
            'courier' => 'stubasync', 'service' => 'standard', 'parcel' => 'box',
            'shipping_payment' => 'net_off',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertStringContainsString('Booking submitted', session('success'));
        $this->assertStringContainsString('Stub Async', session('success'));
        $this->assertStringNotContainsString('tracking .', session('success'));

        // Owner directive (2026-08-08, "AWB opens on a new tab after
        // booking"): the async pending path has no AWB to open yet, so it
        // must never carry the 'label_url' flash the queue view's auto-open
        // banner keys off of.
        $response->assertSessionMissing('label_url');

        $this->assertSame('creating', $order->fresh()->courier_status);
    }

    // ------------------------------------------------------------------
    // Owner directive (2026-08-08, "AWB opens on a new tab after booking"):
    // a synchronous booking success (tracking number already known) must
    // carry the label URL in the flash so the queue view can auto-open the
    // AWB in a new tab, with a visible fallback button in case a popup
    // blocker swallows the automatic open.
    // ------------------------------------------------------------------

    public function test_synchronous_booking_success_flashes_the_label_url_and_the_queue_renders_the_open_awb_button(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableSpx();
        $this->configureSpxPickupOrigin();

        $order = $this->makeCompleteOrder();

        // Same batch_create_order -> get_order_create_result fake shape as
        // FulfillmentQueueTest::test_booking_succeeds_with_the_exact_browser_field_set_for_a_default_drop_off_spx_order()
        // - a real synchronous success, tracking number already known.
        Http::fake([
            '*batch_create_order*' => Http::response(['ret_code' => 0, 'message' => 'success.', 'data' => ['batch_no' => 207982800897462]]),
            '*get_order_create_result*' => Http::response([
                'ret_code' => 0, 'message' => 'success.',
                'data' => [
                    'batch_no' => 207982800897462, 'task_status' => 4, 'description' => 'Done',
                    'total_count' => 1, 'success_count' => 1, 'fail_count' => 0, 'progress' => '100.00%',
                    'orders' => [['order_id' => (string) $order->id, 'tracking_no' => 'SPXPH000000000AWB', 'estimated_shipping_fee' => 25]],
                    'fail_list' => [],
                ],
            ]),
        ]);

        $response = $this->post($this->queueUrl() . '/' . $order->id . '/book', [
            'courier' => 'spx', 'service' => 'standard', 'parcel' => 'medium',
            'shipping_payment' => 'sender_pay', 'insure' => '0', 'pickup_slot' => '',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertStringContainsString('SPXPH000000000AWB', session('success'));

        // The flash carries the SAME authenticated label route the "Print
        // waybill" button and drawer's "Print AWB" link already use - never
        // the courier's own raw printLabel() URL directly.
        $expectedLabelUrl = route('admin.fulfillment.label', $order->fresh());
        $response->assertSessionHas('label_url', $expectedLabelUrl);

        // Following the redirect renders the queue with the flash still in
        // session (assertRedirect() alone doesn't follow it) - the banner's
        // "Open AWB" button must be a real target="_blank" anchor pointing
        // at that exact URL, never a plain link a middle-click would miss.
        $queuePage = $this->get($this->queueUrl());
        $queuePage->assertOk();
        $queuePage->assertSee('Open AWB');
        $queuePage->assertSee('href="' . $expectedLabelUrl . '" target="_blank" rel="noopener"', false);
    }
}
