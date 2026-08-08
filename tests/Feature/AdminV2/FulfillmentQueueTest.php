<?php

namespace Tests\Feature\AdminV2;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\User;
use App\Services\ExtensionManager;
use Extensions\CourierFulfillment\CourierManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeCourierFulfillment;
use Tests\TestCase;

/**
 * Task W2 of the fulfillment-workflow-v2 plan: the queue re-shelled onto the
 * ERP grammar (segment bar of 8-phase spine tabs with mono counts, handover
 * courier chips, two-tier band/item table, mono pager) and the courier-first
 * two-step booking MODAL (step 1 = enabled-courier choice cards, step 2 = the
 * chosen courier's own form — QuadX's exact pre-existing field set).
 *
 * The QuadX booking POST payload contract is NOT re-asserted here — the
 * pre-existing FulfillmentTest booking tests are that contract, kept
 * untouched and green (the review bar for this task).
 */
class FulfillmentQueueTest extends TestCase
{
    use RefreshDatabase;

    private string $prefix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prefix = config('admin.prefix', 'admin');
    }

    /**
     * CourierManager's registry is process-global static state — any courier a
     * test registers must be un-registered after (same convention as
     * FulfillmentTest::tearDown()).
     */
    protected function tearDown(): void
    {
        $ref = new \ReflectionProperty(CourierManager::class, 'registered');
        $ref->setAccessible(true);
        $registered = $ref->getValue();
        unset($registered['fake']);
        $ref->setValue(null, $registered);

        parent::tearDown();
    }

    private function queueUrl(): string
    {
        return '/' . $this->prefix . '/sales/fulfillment';
    }

    private function installExtension(): void
    {
        app(ExtensionManager::class)->install('courier-fulfillment');
    }

    private function enableQuadxStaging(): void
    {
        Setting::create(['group' => 'courier', 'key' => 'quadx_enabled', 'value' => '1']);
        Setting::create(['group' => 'courier', 'key' => 'quadx_mode', 'value' => 'staging']);
        Setting::create(['group' => 'courier', 'key' => 'quadx_staging_api_key', 'value' => Crypt::encryptString('test-api-key')]);
        Setting::create(['group' => 'courier', 'key' => 'quadx_staging_secret_key', 'value' => Crypt::encryptString('test-secret-key')]);
    }

    /**
     * Task T4: minimal SPX enablement — enough for isEnabled() (spx_enabled
     * + app-id/secret) so it appears as a step-1 choice card, plus account
     * credentials (user_id/user_secret) for the estimate round-trip tests
     * that actually call SpxClient.
     */
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
     * Owner directive (2026-08-08, "unconfigured couriers visible with
     * configuration gate"): the FLAG on, credentials deliberately absent —
     * the exact real-world state that confused the owner (SPX's toggle
     * switched on from the settings page, App ID/App Secret never filled
     * in). isEnabled() stays false; CourierManager::flagged() must still
     * include it.
     */
    private function enableSpxFlagOnly(): void
    {
        Setting::create(['group' => 'courier', 'key' => 'spx_enabled', 'value' => '1']);
    }

    /** QuadX's version of enableSpxFlagOnly() above — proves the gate is courier-agnostic, not hardcoded to SPX. */
    private function enableQuadxFlagOnly(): void
    {
        Setting::create(['group' => 'courier', 'key' => 'quadx_enabled', 'value' => '1']);
    }

    /** Courier-agnostic pickup origin (settings group=shipping) — SPX's sender_info source, mirrors SpxFulfillmentTest. */
    private function configureSpxPickupOrigin(): void
    {
        Setting::create(['group' => 'shipping', 'key' => 'pickup_province', 'value' => 'Metro Manila']);
        Setting::create(['group' => 'shipping', 'key' => 'pickup_city', 'value' => 'Makati']);
        Setting::create(['group' => 'shipping', 'key' => 'pickup_barangay', 'value' => 'Bel-Air']);
        Setting::create(['group' => 'shipping', 'key' => 'pickup_address', 'value' => '456 Warehouse Ave']);
        Setting::create(['group' => 'shipping', 'key' => 'pickup_postal_code', 'value' => '1200']);
        Setting::create(['group' => 'shipping', 'key' => 'pickup_contact_name', 'value' => 'Warehouse Team']);
        Setting::create(['group' => 'shipping', 'key' => 'pickup_contact_number', 'value' => '09171112222']);
    }

    /** An order with a COMPLETE shipping address — the estimate round-trip needs buildOrderPayload() to succeed, unlike makeOrder()'s minimal shape. */
    private function makeCompleteOrder(): Order
    {
        return Order::create([
            'store_id' => 1,
            'order_status_id' => $this->defaultStatus()->id,
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

    private function defaultStatus(): OrderStatus
    {
        return OrderStatus::firstOrCreate(
            ['store_id' => 1, 'name' => 'Processing'],
            ['sort_order' => 1, 'is_default' => true],
        );
    }

    /** An order in the queue; optionally booked with a raw courier status. */
    private function makeOrder(?string $courierStatus = null, ?string $tracking = null, string $provider = 'quadx'): Order
    {
        $order = Order::create([
            'store_id' => 1,
            'order_status_id' => $this->defaultStatus()->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'shipping_first_name' => 'Jane',
            'shipping_last_name' => 'Doe',
            'shipping_address_1' => '123 Mango St',
            'shipping_city' => 'Cebu City',
            'shipping_state' => 'Cebu',
            'payment_method' => 'bank_transfer',
            'subtotal' => 1000,
            'shipping_cost' => 100,
            'total' => 1100,
        ]);

        if ($tracking !== null) {
            $order->forceFill([
                'courier_provider' => $provider,
                'courier_tracking_number' => $tracking,
                'courier_status' => $courierStatus,
            ])->save();
        }

        return $order;
    }

    // ------------------------------------------------------------------
    // Segment bar: the seven operational spine tabs, each with a mono count.
    // ------------------------------------------------------------------

    public function test_queue_renders_all_seven_phase_tabs_with_counts_from_seeded_orders(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        // Two never-booked, three awaiting handover, one in transit, one
        // delivered, one failed, one cancelled — plus one returning, which has
        // NO tab of its own (Returns is a separate page, W4).
        $this->makeOrder();
        $this->makeOrder();
        $this->makeOrder('for_pickup', 'QX-H-001');
        $this->makeOrder('for_pickup', 'QX-H-002');
        $this->makeOrder('searching_for_rider', 'QX-H-003');
        $this->makeOrder('in_transit', 'QX-T-001');
        $this->makeOrder('delivered', 'QX-D-001');
        $this->makeOrder('failed_delivery', 'QX-F-001');
        $this->makeOrder('canceled', 'QX-C-001');
        $this->makeOrder('returned', 'QX-R-001');

        $response = $this->get($this->queueUrl());
        $response->assertOk();

        foreach (['To Pack', 'Booking', 'To Handover', 'In Transit', 'Delivered', 'Failed', 'Cancelled'] as $label) {
            $response->assertSee($label);
        }

        // Counts, per phase, from the seeded distribution above.
        $response->assertSee('data-count-for="to_pack">2<', false);
        $response->assertSee('data-count-for="booking">0<', false);
        $response->assertSee('data-count-for="to_handover">3<', false);
        $response->assertSee('data-count-for="in_transit">1<', false);
        $response->assertSee('data-count-for="delivered">1<', false);
        $response->assertSee('data-count-for="failed">1<', false);
        $response->assertSee('data-count-for="cancelled">1<', false);

        // `returning` is deliberately not a tab (ERP rule: returns are a
        // sibling page, never an eighth filter).
        $response->assertDontSee('data-count-for="returning"', false);
    }

    public function test_phase_tabs_filter_the_list_to_that_phase_only(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->makeOrder('for_pickup', 'QX-H-100');
        $this->makeOrder('delivered', 'QX-D-100');

        $this->get($this->queueUrl() . '?filter=delivered')
            ->assertOk()
            ->assertSee('QX-D-100')
            ->assertDontSee('QX-H-100');

        $this->get($this->queueUrl() . '?filter=to_handover')
            ->assertOk()
            ->assertSee('QX-H-100')
            ->assertDontSee('QX-D-100');

        // Default view is To Book — neither booked order shows there.
        $this->get($this->queueUrl())
            ->assertOk()
            ->assertDontSee('QX-D-100')
            ->assertDontSee('QX-H-100');
    }

    // ------------------------------------------------------------------
    // "Parcels waiting for handover" per-courier chips — only on that tab.
    // ------------------------------------------------------------------

    public function test_handover_courier_chips_render_on_the_to_handover_tab_only(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->makeOrder('for_pickup', 'QX-H-201');
        $this->makeOrder('for_pickup', 'QX-H-202');

        $this->get($this->queueUrl() . '?filter=to_handover')
            ->assertOk()
            ->assertSee('Parcels waiting for handover')
            ->assertSee('QuadX');

        $this->get($this->queueUrl())
            ->assertOk()
            ->assertDontSee('Parcels waiting for handover');
    }

    // ------------------------------------------------------------------
    // The two-step booking modal: step 1 lists EXACTLY the enabled couriers.
    // ------------------------------------------------------------------

    public function test_booking_modal_step_one_lists_exactly_the_enabled_couriers(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();
        // Registered but NOT enabled (FakeCourierFulfillment::isEnabled() is
        // false) — must not appear as a choice card.
        CourierManager::register(FakeCourierFulfillment::class);

        $this->makeOrder(); // one unbooked order so the Book action renders

        $response = $this->get($this->queueUrl());
        $response->assertOk()
            ->assertSee('How will this order ship?')
            ->assertSee('data-courier-card="quadx"', false)
            ->assertDontSee('data-courier-card="fake"', false);
    }

    // ------------------------------------------------------------------
    // Owner directive (2026-08-08, "unconfigured couriers visible with
    // configuration gate"): a courier whose ENABLED FLAG is on but is
    // missing credentials must still render a step-1 card — a disabled,
    // clearly-labeled one — instead of silently vanishing the way
    // enabled()'s AND-credentials gate alone made it disappear.
    // ------------------------------------------------------------------

    public function test_booking_modal_step_one_shows_a_flagged_but_unconfigured_courier_as_a_gated_card(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();
        // SPX's toggle is on but no App ID/App Secret was ever saved — the
        // exact real-world state that confused the owner.
        $this->enableSpxFlagOnly();

        $this->makeOrder();

        $response = $this->get($this->queueUrl());
        $response->assertOk()
            // SPX now HAS a card (the bug fix) ...
            ->assertSee('data-courier-card="spx"', false)
            ->assertSee('data-courier-configured="false"', false)
            ->assertSee('Not configured')
            ->assertSee('Missing App ID / App Secret')
            ->assertSee(route('admin.fulfillment.settings.courier', ['courier' => 'spx']), false)
            // ... but it is NEVER wired to chooseCourier() — a click can't
            // advance it to step 2. QuadX's own card (fully configured)
            // still is.
            ->assertDontSee("chooseCourier('spx')", false)
            // QuadX's card renders BYTE-IDENTICALLY to before this task —
            // the exact pre-existing <button> tag, with no new attribute
            // added to the frozen configured branch (review fix: a prior
            // draft of this task added `data-courier-configured="true"`
            // here, which this exact-tag assertion would now catch).
            ->assertSee('<button type="button" class="cf-choice__card" data-courier-card="quadx" @click="chooseCourier(\'quadx\')">', false);
    }

    /**
     * The modal (and the row's Book button) must still be reachable even
     * when EVERY registered courier is flag-on-but-unconfigured — the whole
     * point is that the owner can open the modal and SEE why nothing is
     * bookable, not have the entire booking affordance vanish.
     */
    public function test_booking_modal_opens_with_only_a_gated_card_when_no_courier_is_fully_configured(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableSpxFlagOnly();

        $this->makeOrder();

        $response = $this->get($this->queueUrl());
        $response->assertOk()
            // The row's Book button renders (openBooking(), not hidden).
            ->assertSee('openBooking(', false)
            ->assertSee('data-courier-card="spx"', false)
            ->assertSee('data-courier-configured="false"', false)
            ->assertSee('Not configured');
    }

    /** Proves the gate is courier-agnostic (QuadXClient::missingCredentialLabels()), not just SPX. */
    public function test_booking_modal_step_one_names_the_missing_quadx_credentials(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxFlagOnly();

        $this->makeOrder();

        $response = $this->get($this->queueUrl());
        $response->assertOk()
            ->assertSee('data-courier-card="quadx"', false)
            ->assertSee('data-courier-configured="false"', false)
            ->assertSee('Missing API Key / Secret Key');
    }

    // ------------------------------------------------------------------
    // Server-side gate (defense in depth): book()/estimate() must reject a
    // flag-on-but-unconfigured courier with a friendly, named message —
    // never the raw signing/API exception the courier's own client would
    // otherwise throw — and must never make an outbound call.
    // ------------------------------------------------------------------

    public function test_book_route_rejects_a_flagged_but_unconfigured_courier_with_a_friendly_message(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableSpxFlagOnly();

        $order = $this->makeOrder();
        Http::fake(); // any outbound call here would itself be a bug

        $response = $this->post($this->queueUrl() . '/' . $order->id . '/book', [
            'courier'          => 'spx',
            'service'          => 'standard',
            'parcel'           => 'medium',
            'shipping_payment' => 'sender_pay',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('SPX is not properly configured', session('error'));
        $this->assertStringContainsString('Settings', session('error'));

        Http::assertNothingSent();
        $this->assertNull($order->fresh()->courier_tracking_number);
        $this->assertNull($order->fresh()->courier_provider);
    }

    public function test_estimate_route_rejects_a_flagged_but_unconfigured_courier_with_a_friendly_message(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableSpxFlagOnly();

        $order = $this->makeOrder();
        Http::fake();

        $response = $this->postJson($this->queueUrl() . '/' . $order->id . '/estimate', [
            'courier' => 'spx',
        ]);

        $response->assertOk()
            ->assertJson(['ok' => false]);
        $this->assertStringContainsString('SPX is not properly configured', $response->json('message'));

        Http::assertNothingSent();
    }

    public function test_booking_modal_step_two_carries_exactly_the_quadx_field_set(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        $this->makeOrder();

        $response = $this->get($this->queueUrl());
        $response->assertOk()
            // The exact field names FulfillmentController::book() validates —
            // the POST the server receives must not change shape.
            ->assertSee('name="courier"', false)
            ->assertSee('name="service"', false)
            ->assertSee('name="parcel"', false)
            ->assertSee('name="shipping_payment"', false)
            ->assertSee('name="insure"', false)
            ->assertSee('Book shipment');
    }

    // ------------------------------------------------------------------
    // Review fix: a cancelled booking (tracking number present, not
    // actively booked) must keep its drawer access — the drawer is the
    // viewing surface for its timeline and the terminal `cancelled` badge,
    // and Rebook alone would orphan both.
    // ------------------------------------------------------------------

    public function test_cancelled_booking_rows_keep_drawer_access_via_a_tracking_button(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        // Rebook needs an enabled courier (same rule as first-time Book).
        $this->enableQuadxStaging();

        $this->makeOrder('canceled', 'QX-CXL-001');

        $response = $this->get($this->queueUrl() . '?filter=cancelled');
        $response->assertOk()
            ->assertSee('QX-CXL-001')
            // Rebook (opens the booking modal) AND a drawer opener both render:
            // openDrawer(...) is emitted only by the row's Tracking button.
            ->assertSee('Rebook')
            ->assertSee('openDrawer(JSON.parse(', false);
    }

    // ------------------------------------------------------------------
    // Owner directive (2026-08-08, "AWB opens on a new tab after booking"):
    // every AWB/waybill anchor opens in a new tab. Locks in the row's
    // "Print waybill" button and the drawer's static "Print AWB" link so a
    // future edit can't silently drop target="_blank"/rel="noopener".
    // ------------------------------------------------------------------

    public function test_awb_anchors_open_in_a_new_tab(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        $order = $this->makeOrder('for_pickup', 'QX-TAB-001');

        $response = $this->get($this->queueUrl() . '?filter=all');
        $content = $response->getContent();

        // Row action: "Print waybill" -> the authenticated label route,
        // opened in a new tab so the operator never loses the queue.
        // x-admin.button's own attribute merge() reorders target/rel after
        // the generated class="..." attribute (see button.blade.php), so
        // this pulls the whole rendered anchor out rather than asserting on
        // one brittle attribute ordering.
        preg_match('/<a href="' . preg_quote(route('admin.fulfillment.label', $order), '/') . '"[^>]*>Print waybill<\/a>/', $content, $waybillMatch);
        $this->assertNotEmpty($waybillMatch, 'Expected a "Print waybill" anchor pointing at the label route.');
        $this->assertStringContainsString('target="_blank"', $waybillMatch[0]);
        $this->assertStringContainsString('rel="noopener"', $waybillMatch[0]);

        // Drawer: "Print AWB" is bound to labelUrl via Alpine (:href), but
        // its target/rel are STATIC attributes on the same anchor markup,
        // present in the response regardless of which row opened it.
        $this->assertStringContainsString(
            ':href="labelUrl" target="_blank" rel="noopener"',
            $content
        );
        $this->assertStringContainsString('>Print AWB<', $content);
    }

    // ------------------------------------------------------------------
    // Interface evolutions #4/#5 — QuadX-safe defaults.
    // ------------------------------------------------------------------

    public function test_quadx_inherits_the_null_fee_estimate_and_empty_pickup_slot_defaults(): void
    {
        $this->installExtension();

        $order = $this->makeOrder();
        $quadx = CourierManager::get('quadx') ?? new \Extensions\CourierFulfillment\Couriers\QuadX\QuadXFulfillment();

        $this->assertNull($quadx->estimateFee($order, []));
        $this->assertSame([], $quadx->getPickupSlots($order));
    }

    // ------------------------------------------------------------------
    // Filter/tab params preserved across the segment bar and filters row.
    // ------------------------------------------------------------------

    public function test_tab_links_preserve_search_and_per_page_params(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $response = $this->get($this->queueUrl() . '?filter=delivered&q=QX&per_page=10');
        $response->assertOk()
            // Every other tab link keeps the operator's search + page size.
            ->assertSee('filter=to_pack&amp;q=QX&amp;per_page=10', false)
            ->assertSee('filter=in_transit&amp;q=QX&amp;per_page=10', false)
            // The filters form re-submits within the active tab.
            ->assertSee('name="filter" value="delivered"', false);
    }

    // ------------------------------------------------------------------
    // Mono pager: "1 to N of M" and a real per-page control.
    // ------------------------------------------------------------------

    public function test_pager_shows_mono_range_and_respects_per_page(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        for ($i = 0; $i < 12; $i++) {
            $this->makeOrder();
        }

        $this->get($this->queueUrl() . '?per_page=10')
            ->assertOk()
            ->assertSee('1 to 10 of 12');

        $this->get($this->queueUrl() . '?per_page=10&page=2')
            ->assertOk()
            ->assertSee('11 to 12 of 12');
    }

    // ------------------------------------------------------------------
    // Command bar: Poll statuses action.
    // ------------------------------------------------------------------

    public function test_poll_statuses_action_runs_the_poll_and_redirects_back_with_a_result(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        // Prime the extension's routes (registered by BootExtensions on the
        // first request after install — same convention as FulfillmentTest).
        $this->get($this->queueUrl())->assertOk();

        // No couriers enabled — the command exits cleanly, and the action
        // reports that instead of erroring.
        $response = $this->post($this->queueUrl() . '/poll');
        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    // ------------------------------------------------------------------
    // Task T4: SPX's step-2 form — pouch presets, custom-dims escape,
    // who-pays options, pickup-slot select, and the lazy "Get estimate"
    // button. QuadX's own step-2 field set (asserted above) is untouched —
    // this only proves SPX's ADDITIONAL markup renders alongside it.
    // ------------------------------------------------------------------

    public function test_spx_step_two_renders_pouch_presets_custom_dims_who_pays_and_estimate_button(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableSpx();

        $this->makeOrder(); // one unbooked order so the modal renders

        $response = $this->get($this->queueUrl());
        $response->assertOk()
            ->assertSee('data-courier-card="spx"', false)
            // Pouch preset labels (default presets — dims baked into the
            // label text by SpxFulfillment::getParcelOptions(); '×' is
            // unicode and gets JS-escaped inside the options payload, so
            // assertions stick to the ASCII-safe parts of each label).
            ->assertSee('Small (25')
            ->assertSee('up to 3kg)')
            ->assertSee('Medium (35')
            ->assertSee('up to 5kg)')
            ->assertSee('Large (45')
            ->assertSee('up to 10kg)')
            ->assertSee('Custom dimensions')
            // Custom-dims escape hatch — present in the DOM (x-show hides it
            // client-side until 'custom' is picked, so it's always rendered).
            ->assertSee('name="length_cm"', false)
            ->assertSee('name="width_cm"', false)
            ->assertSee('name="height_cm"', false)
            ->assertSee('name="weight_kg"', false)
            // Who-pays (SpxFulfillment::getShippingPaymentOptions()).
            ->assertSee('shoulder it')
            ->assertSee('Sender pays the shipping fee')
            ->assertSee('Customer pays the shipping fee (Receiver pay)')
            // Pickup slot select (fetched lazily — see the JS below) + the
            // lazy fee-estimate button (never called on page load).
            ->assertSee('name="pickup_slot"', false)
            ->assertSee('Get estimate')
            // Lazy-fetch wiring: never an eager HTTP call baked into this
            // page's own render.
            ->assertSee('loadSpxPickupSlots()', false)
            ->assertSee('getSpxEstimate()', false);
    }

    /**
     * Load-bearing regression guard: the SPX-only step-2 block MUST be an
     * Alpine `template x-if`, never `x-show`/CSS display:none. An x-show'd
     * block stays in the DOM (and its inputs still POST) even while
     * visually hidden — which would silently inject pickup_slot/length_cm/
     * width_cm/height_cm/weight_kg into a QuadX booking's POST body the
     * moment SPX is ALSO enabled (the exact hazard the byte-frozen QuadX
     * payload constraint exists to prevent), and would 422 every non-custom
     * SPX booking (the hidden, empty custom-dims inputs failing a `numeric`
     * rule). This asserts the fix's shape directly so a future edit can't
     * quietly swap x-if back to x-show without a test noticing.
     */
    public function test_spx_only_step_two_fields_are_removed_from_the_dom_not_merely_hidden(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableSpx();
        $this->enableQuadxStaging();

        $this->makeOrder();

        $response = $this->get($this->queueUrl());
        $response->assertOk()
            ->assertSee('<template x-if="courier === \'spx\'">', false)
            ->assertDontSee('x-show="courier === \'spx\'"', false);
    }

    public function test_spx_card_is_absent_and_quadx_form_untouched_when_spx_is_not_enabled(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        $this->makeOrder();

        $response = $this->get($this->queueUrl());
        $response->assertOk()
            ->assertDontSee('data-courier-card="spx"', false)
            ->assertDontSee('Custom dimensions')
            ->assertDontSee('Get estimate');
    }

    // ------------------------------------------------------------------
    // Task T4 + fulfillment-tabs-v3 Task 2: honest "Creating…" state — an
    // SPX booking that's still mid-flight (courier_status='creating', no
    // tracking number yet) must count and list under To Handover (Task 2's
    // stage-first collapse: the standalone Booking tab is dropped, 'booking'
    // phase orders count under to_handover for TAB MEMBERSHIP), never
    // To Pack — the exact FulfillmentService::phaseFor() short-circuit T2's
    // report flagged. The row's own badge/label still reads "Booking" (the
    // internal phase itself is UNCHANGED — only its tab bucket moved).
    // ------------------------------------------------------------------

    public function test_creating_spx_order_counts_and_lists_under_the_to_handover_tab(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        // A genuinely never-booked order (QuadX-provider baseline, proves
        // the fix is scoped to courier_status='creating' specifically, not
        // "no tracking number" in general).
        $this->makeOrder();

        $creatingOrder = $this->makeOrder();
        $creatingOrder->forceFill([
            'courier_provider' => 'spx',
            'courier_status' => 'creating',
            'courier_tracking_number' => null,
        ])->save();

        $response = $this->get($this->queueUrl());
        $response->assertOk()
            ->assertSee('data-count-for="to_pack">1<', false)
            ->assertSee('data-count-for="booking">0<', false)
            ->assertSee('data-count-for="to_handover">1<', false);

        $handoverTab = $this->get($this->queueUrl() . '?filter=to_handover');
        $handoverTab->assertOk()
            ->assertSee('#' . $creatingOrder->id, false)
            // The spine badge label (PhaseTone::toneFor('booking') is
            // 'info', label is the humanized phase name) is UNCHANGED —
            // the row's own phase still reads 'booking', only its tab
            // membership moved.
            ->assertSee('Booking')
            // The raw-status note + the row's action both say "Creating"
            // (one via humanizeStatus()'s real ellipsis character, the
            // other via the button's literal &hellip; entity — both
            // contain the plain ASCII substring asserted here).
            ->assertSee('Creating');

        // Default view (To Pack) must NOT show the creating order.
        $this->get($this->queueUrl())
            ->assertOk()
            ->assertDontSee('#' . $creatingOrder->id, false);

        // The old Booking-tab filter is now empty — its combos all
        // collapsed into to_handover for tab membership.
        $this->get($this->queueUrl() . '?filter=booking')
            ->assertOk()
            ->assertDontSee('#' . $creatingOrder->id, false);
    }

    // ------------------------------------------------------------------
    // Owner-reported bug (2026-08-08, screenshot-confirmed): the cancel
    // confirm-dialog is rendered inside the queue table's action <td>,
    // whose CSS carries white-space:nowrap (.cf-col-act) — the modal's
    // message <p> inherited that and overflowed the card. Fixed at the
    // shared x-admin.modal card level (whitespace-normal), so any modal
    // nested in a nowrap ancestor self-heals. This locks in the class on
    // the rendered card so the fix can't silently regress.
    // ------------------------------------------------------------------

    public function test_cancel_confirm_dialog_card_carries_whitespace_normal_to_survive_the_nowrap_action_cell(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        // for_pickup + tracking number => activelyBooked && cancelable for
        // QuadX (QuadXFulfillment::cancelableStatuses()), which is what
        // renders the Cancel button + its confirm-dialog into the
        // nowrap-bearing .cf-col-act <td>.
        $order = $this->makeOrder('for_pickup', 'QX-WRAP-001');

        $response = $this->get($this->queueUrl() . '?filter=to_handover');
        $content = $response->getContent();

        $response->assertOk();
        $this->assertStringContainsString('This cancels the courier booking. The order remains rebookable afterward.', $content);

        // Pull the confirm-dialog's modal card <div> out of the response
        // and assert it carries whitespace-normal alongside its existing
        // classes — the exact element whose inherited nowrap caused the
        // overflow.
        $this->assertMatchesRegularExpression(
            '/<div x-show="open" x-transition\s+class="relative w-full max-w-sm bg-v-surface border border-v-border rounded-v-lg shadow-2xl whitespace-normal"/',
            $content,
            'Expected the modal card to carry whitespace-normal so slot content wraps inside nowrap ancestors (e.g. the fulfillment queue\'s .cf-col-act cell).'
        );
    }

    // ------------------------------------------------------------------
    // Task T4: actual vs. estimated fee — two distinctly labeled figures,
    // read from courier_meta (T2's create-response + T2 fix-round's
    // batch_get_asf persistence).
    // ------------------------------------------------------------------

    public function test_actual_and_estimated_fees_render_distinctly_when_present(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $order = $this->makeOrder('1001', 'SPX-FEE-1', 'spx');
        $order->forceFill(['courier_meta' => json_encode([
            'estimated_shipping_fee' => 111.11,
            'actual_fee' => ['actual_shipping_fee' => 222.22],
        ])])->save();

        $response = $this->get($this->queueUrl() . '?filter=to_handover');
        $response->assertOk()
            ->assertSee('SPX-FEE-1')
            ->assertSee('111.11', false)
            ->assertSee('222.22', false);
    }

    // ------------------------------------------------------------------
    // Task T4: the lazy per-order fee-estimate route — Http::fake round
    // trip, and proven auth-guarded (same middleware group as every other
    // fulfillment route).
    // ------------------------------------------------------------------

    public function test_estimate_route_requires_authentication(): void
    {
        $this->installExtension();

        $order = $this->makeOrder();

        // Not actingAs() anyone — a genuine guest request (same convention
        // as FulfillmentTest::test_label_file_route_requires_authentication()).
        $response = $this->post($this->queueUrl() . '/' . $order->id . '/estimate', ['courier' => 'spx']);
        $response->assertRedirect(); // to the login page, never a 200
    }

    public function test_estimate_route_round_trips_the_fee_via_http_fake(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableSpx();
        $this->configureSpxPickupOrigin();

        $order = $this->makeCompleteOrder();

        Http::fake([
            '*batch_check_order*' => Http::response([
                'ret_code' => 0, 'message' => 'success.',
                'data' => ['orders' => [[
                    'order_id' => (string) $order->id,
                    'estimated_shipping_fee' => 55.5,
                ]]],
            ]),
        ]);

        $response = $this->postJson($this->queueUrl() . '/' . $order->id . '/estimate', [
            'courier'          => 'spx',
            'service'          => 'standard',
            'parcel'           => 'medium',
            'shipping_payment' => 'sender_pay',
        ]);

        $response->assertOk()
            ->assertJson(['ok' => true])
            ->assertJsonPath('amount', 55.5)
            ->assertJsonPath('currency', 'PHP');

        Http::assertSent(fn ($request) => str_contains((string) $request->url(), 'batch_check_order'));
    }

    public function test_estimate_route_is_graceful_when_the_order_address_is_incomplete(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableSpx();
        $this->configureSpxPickupOrigin();

        // makeOrder() has no postal code/phone — estimateFee() can't build
        // a real payload, so this must degrade to ok:false, never a 500,
        // and never call SPX at all.
        $order = $this->makeOrder();

        $response = $this->postJson($this->queueUrl() . '/' . $order->id . '/estimate', [
            'courier' => 'spx',
        ]);

        $response->assertOk()->assertJson(['ok' => false]);
        Http::assertNothingSent();
    }

    // ------------------------------------------------------------------
    // Review fix (Critical): the SPX step-2 form ALWAYS submits
    // pickup_slot="" (the pre-selected "Drop off myself" option) for a
    // default drop-off booking — never absent — and
    // ConvertEmptyStringsToNull turns that "" into null BEFORE validation
    // runs. `sometimes|string` (without `nullable`) rejected a present
    // null with a 422, blocking EVERY default SPX booking/estimate. These
    // tests post the EXACT field set the browser sends (through the full
    // HTTP stack, not a direct method call) so this class of bug can't
    // silently return.
    // ------------------------------------------------------------------

    public function test_booking_succeeds_with_the_exact_browser_field_set_for_a_default_drop_off_spx_order(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableSpx();
        $this->configureSpxPickupOrigin();

        $order = $this->makeCompleteOrder();

        Http::fake([
            '*batch_create_order*' => Http::response(['ret_code' => 0, 'message' => 'success.', 'data' => ['batch_no' => 207982800897461]]),
            '*get_order_create_result*' => Http::response([
                'ret_code' => 0, 'message' => 'success.',
                'data' => [
                    'batch_no' => 207982800897461, 'task_status' => 4, 'description' => 'Done',
                    'total_count' => 1, 'success_count' => 1, 'fail_count' => 0, 'progress' => '100.00%',
                    'orders' => [['order_id' => (string) $order->id, 'tracking_no' => 'SPXPH000000000001', 'estimated_shipping_fee' => 25]],
                    'fail_list' => [],
                ],
            ]),
        ]);

        // Exactly what the browser posts for a default (non-custom,
        // drop-off) SPX booking: pickup_slot present as "" — the
        // pre-selected "Drop off myself" <option value="">, never absent.
        // No dims fields — they're outside the DOM entirely unless
        // parcel === 'custom' (the x-if fix), so a real browser never
        // sends them here, but ConvertEmptyStringsToNull's effect on
        // pickup_slot alone was already enough to 422 every booking.
        $response = $this->post($this->queueUrl() . '/' . $order->id . '/book', [
            'courier'          => 'spx',
            'service'          => 'standard',
            'parcel'           => 'medium',
            'shipping_payment' => 'sender_pay',
            'insure'           => '0',
            'pickup_slot'      => '',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('SPXPH000000000001', $order->courier_tracking_number);
    }

    public function test_booking_succeeds_when_blank_dims_strings_are_also_present(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableSpx();
        $this->configureSpxPickupOrigin();

        $order = $this->makeCompleteOrder();

        Http::fake([
            '*batch_create_order*' => Http::response(['ret_code' => 0, 'message' => 'success.', 'data' => ['batch_no' => 207982800897462]]),
            '*get_order_create_result*' => Http::response([
                'ret_code' => 0, 'message' => 'success.',
                'data' => [
                    'batch_no' => 207982800897462, 'task_status' => 4, 'description' => 'Done',
                    'total_count' => 1, 'success_count' => 1, 'fail_count' => 0, 'progress' => '100.00%',
                    'orders' => [['order_id' => (string) $order->id, 'tracking_no' => 'SPXPH000000000002', 'estimated_shipping_fee' => 25]],
                    'fail_list' => [],
                ],
            ]),
        ]);

        // Defense-in-depth: even if all four dims fields somehow arrive as
        // blank strings (-> null) alongside a NON-custom preset, the
        // booking must still succeed via the preset — not 422 on the dims
        // rules, and not mistake nulled dims for a custom-dims attempt.
        $response = $this->post($this->queueUrl() . '/' . $order->id . '/book', [
            'courier'          => 'spx',
            'service'          => 'standard',
            'parcel'           => 'medium',
            'shipping_payment' => 'sender_pay',
            'insure'           => '0',
            'pickup_slot'      => '',
            'length_cm'        => '',
            'width_cm'         => '',
            'height_cm'        => '',
            'weight_kg'        => '',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('SPXPH000000000002', $order->courier_tracking_number);
    }

    public function test_estimate_route_200s_with_the_exact_browser_field_set_for_a_default_drop_off_spx_order(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableSpx();
        $this->configureSpxPickupOrigin();

        $order = $this->makeCompleteOrder();

        Http::fake([
            '*batch_check_order*' => Http::response([
                'ret_code' => 0, 'message' => 'success.',
                'data' => ['orders' => [['order_id' => (string) $order->id, 'estimated_shipping_fee' => 55.5]]],
            ]),
        ]);

        $response = $this->postJson($this->queueUrl() . '/' . $order->id . '/estimate', [
            'courier'          => 'spx',
            'service'          => 'standard',
            'parcel'           => 'medium',
            'shipping_payment' => 'sender_pay',
            'insure'           => '0',
            'pickup_slot'      => '',
            'length_cm'        => '',
            'width_cm'         => '',
            'height_cm'        => '',
            'weight_kg'        => '',
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true]);
        $response->assertJsonPath('amount', 55.5);
    }
}
