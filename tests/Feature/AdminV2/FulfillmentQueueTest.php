<?php

namespace Tests\Feature\AdminV2;

use App\Models\Order;
use App\Models\OrderStatus;
use Extensions\CourierFulfillment\PlacementMap;
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
        // Registers the extension's runtime PSR-4 autoloader up front, so
        // this file can reference Extensions\CourierFulfillment\* (the
        // module-owned placement mapping) before its first HTTP request.
        app(ExtensionManager::class)->bootEnabled();
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
        // fulfillment-placement Task 3: a successful booking moves the order
        // onto a To-Handover-placed status, and is refused when the store has
        // none (FulfillmentService::bookedStatus()). OrderStatusSeeder ships
        // this status on a real install; the fixture carries it so the
        // booking round-trips below exercise a store that can actually
        // complete a fulfillment.
        $this->statusWithStage('to_handover', 'Order Being Shipped');

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
        $status = OrderStatus::firstOrCreate(
            ['store_id' => 1, 'name' => 'Processing'],
            ['sort_order' => 1, 'is_default' => true],
        );

        PlacementMap::put($status->id, 'to_pack');

        return $status;
    }

    /**
     * fulfillment-placement Task 2: a status placed under a given placement.
     * The mapping is module data (PlacementMap), and it is the ONLY thing
     * that decides which tab an order on this status appears in.
     */
    private function statusWithStage(string $stage, string $name): OrderStatus
    {
        $status = OrderStatus::firstOrCreate(
            ['store_id' => 1, 'name' => $name],
            ['sort_order' => 1],
        );

        PlacementMap::put($status->id, $stage);

        return $status;
    }

    /** An order in the queue; optionally booked with a raw courier status, optionally on a non-default status/stage. */
    private function makeOrder(?string $courierStatus = null, ?string $tracking = null, string $provider = 'quadx', ?OrderStatus $status = null): Order
    {
        $order = Order::create([
            'store_id' => 1,
            'order_status_id' => ($status ?? $this->defaultStatus())->id,
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
    // Segment bar: the six top-level tabs (Unpaid first), placement counts,
    // and the To Ship sub-segment bar.
    //
    // fulfillment-placement Task 2 REWRITE: this test used to seed one
    // order per COURIER PHASE and assert the phase decided the tab (plus
    // the tabs-v3 unpaid carve-out and the 'booking' collapse). Membership
    // now reads the core status's placement and nothing else, so the same
    // scenario is expressed the way it now works: statuses are placed, and
    // the courier states carried along prove they change no count.
    // ------------------------------------------------------------------

    public function test_queue_renders_the_eight_top_level_tabs_with_placement_counts_and_the_to_ship_sub_bar(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $unpaidStatus = $this->statusWithStage('unpaid', 'Unpaid');
        $cancelledStatus = $this->statusWithStage('cancelled', 'Canceled');
        $handoverStatus = $this->statusWithStage('to_handover', 'Order Being Shipped');
        $transitStatus = $this->statusWithStage('shipping', 'Shipped');
        $deliveredStatus = $this->statusWithStage('delivered', 'Delivered');
        $failedStatus = $this->statusWithStage('failed', 'Failed Delivery');

        // Two Unpaid-placed orders, one of them carrying a live in-transit
        // booking: under tabs v3 the booking pulled it into Shipping, now
        // its status keeps it under Unpaid (and Task 3 is what will move the
        // status when a booking is made).
        $this->makeOrder(status: $unpaidStatus);
        $unpaidWithBooking = $this->makeOrder('in_transit', 'QX-U-001', 'quadx', $unpaidStatus);

        // To Ship: two To Pack (never booked) plus four To Handover, one of
        // which is still mid-async-create with no tracking number at all.
        $this->makeOrder();
        $this->makeOrder();
        $this->makeOrder('for_pickup', 'QX-H-001', 'quadx', $handoverStatus);
        $this->makeOrder('for_pickup', 'QX-H-002', 'quadx', $handoverStatus);
        $this->makeOrder('searching_for_rider', 'QX-H-003', 'quadx', $handoverStatus);
        $creating = $this->makeOrder(status: $handoverStatus);
        $creating->forceFill(['courier_provider' => 'spx', 'courier_status' => 'creating', 'courier_tracking_number' => null])->save();

        $this->makeOrder('in_transit', 'QX-T-001', 'quadx', $transitStatus);
        $this->makeOrder('delivered', 'QX-D-001', 'quadx', $deliveredStatus);
        $this->makeOrder('failed_delivery', 'QX-F-001', 'quadx', $failedStatus);

        // Cancelled: a real order cancel, and a courier-side cancel whose
        // status map moved the core status to a cancelled-placed one. Both
        // arrive here the same way now (their status), never via a union of
        // a stage arm and a courier-phase arm.
        $this->makeOrder(status: $cancelledStatus);
        $this->makeOrder('canceled', 'QX-C-001', 'quadx', $cancelledStatus);

        // A returning parcel whose status was never moved: it stays under
        // its own placement (Returns is a page, not a tab).
        $this->makeOrder('returned', 'QX-R-001', 'quadx', $transitStatus);

        $response = $this->get($this->queueUrl());
        $response->assertOk();

        foreach (['All', 'Unpaid', 'To Ship', 'Shipping', 'Failed', 'Lost & Damaged', 'Delivered', 'Cancelled'] as $label) {
            $response->assertSee($label);
        }
        // Top-level Booking tab is dropped entirely (owner directive).
        $response->assertDontSee('>Booking<', false);

        $response->assertSee('data-count-for="unpaid">2<', false);
        $response->assertSee('data-count-for="to_ship">6<', false); // 2 to_pack + 4 to_handover
        $response->assertSee('data-count-for="shipping">2<', false); // the in-transit one + the returning one, both Shipped-placed
        $response->assertSee('data-count-for="delivered">1<', false);
        $response->assertSee('data-count-for="failed">1<', false);
        $response->assertSee('data-count-for="cancelled">2<', false);

        // `returning` is deliberately not a tab (ERP rule: returns are a
        // sibling page, never an eighth filter).
        $response->assertDontSee('data-count-for="returning"', false);

        // The unpaid order's live booking changed nothing about its tab.
        $this->assertNotNull($unpaidWithBooking->courier_tracking_number);

        // Default landing view (filter defaults to to_ship, sub to_pack):
        // its own sub-bar renders with both sub counts.
        $response->assertSee('To Pack');
        $response->assertSee('To Handover');
        $response->assertSee('data-count-for="to_pack">2<', false);
        $response->assertSee('data-count-for="to_handover">4<', false);
    }

    public function test_sub_bar_renders_only_under_to_ship_with_correct_counts_and_defaults_to_pack(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->makeOrder('for_pickup', 'QX-H-100', 'quadx', $this->statusWithStage('to_handover', 'Order Being Shipped'));
        $this->makeOrder('delivered', 'QX-D-100', 'quadx', $this->statusWithStage('delivered', 'Delivered'));

        // Sub-bar renders under To Ship...
        $toShip = $this->get($this->queueUrl() . '?filter=to_ship');
        $toShip->assertOk()
            ->assertSee('data-count-for="to_pack">0<', false)
            ->assertSee('data-count-for="to_handover">1<', false);

        // ...but not under any other top-level tab.
        $this->get($this->queueUrl() . '?filter=delivered')
            ->assertOk()
            ->assertDontSee('data-count-for="to_pack"', false)
            ->assertDontSee('data-count-for="to_handover"', false);

        $this->get($this->queueUrl() . '?filter=cancelled')
            ->assertOk()
            ->assertDontSee('data-count-for="to_pack"', false);
    }

    public function test_to_ship_sub_tabs_filter_the_list_and_default_to_pack(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->makeOrder('for_pickup', 'QX-H-100', 'quadx', $this->statusWithStage('to_handover', 'Order Being Shipped'));
        $this->makeOrder('delivered', 'QX-D-100', 'quadx', $this->statusWithStage('delivered', 'Delivered'));
        $unbooked = $this->makeOrder();

        $this->get($this->queueUrl() . '?filter=delivered')
            ->assertOk()
            ->assertSee('QX-D-100')
            ->assertDontSee('QX-H-100');

        $this->get($this->queueUrl() . '?filter=to_ship&sub=to_handover')
            ->assertOk()
            ->assertSee('QX-H-100')
            ->assertDontSee('QX-D-100')
            ->assertDontSee('#' . $unbooked->id, false);

        // filter=to_ship with NO sub defaults to to_pack.
        $this->get($this->queueUrl() . '?filter=to_ship')
            ->assertOk()
            ->assertSee('#' . $unbooked->id, false)
            ->assertDontSee('QX-D-100')
            ->assertDontSee('QX-H-100')
            ->assertSee('name="sub" value="to_pack"', false);

        // An INVALID sub also defaults to to_pack, never a broken/empty query.
        $this->get($this->queueUrl() . '?filter=to_ship&sub=bogus')
            ->assertOk()
            ->assertSee('#' . $unbooked->id, false)
            ->assertSee('name="sub" value="to_pack"', false);

        // Bare default (no filter/sub at all) is the same as to_ship/to_pack.
        $this->get($this->queueUrl())
            ->assertOk()
            ->assertSee('#' . $unbooked->id, false)
            ->assertDontSee('QX-D-100')
            ->assertDontSee('QX-H-100');
    }

    // ------------------------------------------------------------------
    // Legacy URL aliases: a bookmarked pre-Task-3 (or pre-Task-2) link
    // converges on the new filter=to_ship&sub=... shape via a 301,
    // preserving every other query param.
    // ------------------------------------------------------------------

    public function test_legacy_filter_to_book_redirects_to_to_ship_and_to_pack_sub_preserving_other_params(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $response = $this->get($this->queueUrl() . '?filter=to_book&q=jane&per_page=10');

        $response->assertStatus(301);
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('filter=to_ship', $location);
        $this->assertStringContainsString('sub=to_pack', $location);
        $this->assertStringContainsString('q=jane', $location);
        $this->assertStringContainsString('per_page=10', $location);
    }

    public function test_legacy_filter_to_handover_redirects_to_to_ship_and_to_handover_sub(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $response = $this->get($this->queueUrl() . '?filter=to_handover&per_page=50');

        $response->assertStatus(301);
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('filter=to_ship', $location);
        $this->assertStringContainsString('sub=to_handover', $location);
        $this->assertStringContainsString('per_page=50', $location);

        // The redirect target itself renders fine.
        $this->get($location)->assertOk();
    }

    public function test_legacy_filter_booking_redirects_to_to_ship_and_to_handover_sub(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $response = $this->get($this->queueUrl() . '?filter=booking');

        $response->assertStatus(301);
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('filter=to_ship', $location);
        $this->assertStringContainsString('sub=to_handover', $location);
    }

    // ------------------------------------------------------------------
    // Code-review fix (Critical): phaseTally() (fulfillment-tabs-v3 Task 2)
    // clones $base and joins order_statuses for the stage-first tally — a
    // table that ALSO has an 'id' column. The numeric-search branch below
    // used to write a bare 'id' into that SAME shared $base closure, so
    // MySQL threw SQLSTATE 1052 "Column 'id' in where clause is ambiguous"
    // the instant phaseTally() ran with q=<a numeric id> — i.e. every
    // numeric search, on every page load, since phaseTally() always runs.
    // 'orders.id' is now explicit. This exercises the exact path that 500'd:
    // a real page load (which runs BOTH $query's own listing and
    // phaseTally()'s joined combos query) with a numeric q.
    // ------------------------------------------------------------------

    public function test_numeric_search_does_not_500_the_stage_joined_tally(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $order = $this->makeOrder('for_pickup', 'QX-NUM-1', 'quadx', $this->statusWithStage('to_handover', 'Order Being Shipped'));
        $this->makeOrder(); // a second, non-matching order in the same tally

        $response = $this->get($this->queueUrl() . '?filter=to_ship&sub=to_handover&q=' . $order->id);

        $response->assertOk()
            ->assertSee('#' . $order->id, false)
            ->assertSee('data-count-for="to_handover">1<', false);
    }

    // ------------------------------------------------------------------
    // "Parcels waiting for handover" per-courier chips — moved (Task 3) to
    // render only under To Ship > To Handover, never on To Pack or any
    // other tab.
    // ------------------------------------------------------------------

    public function test_handover_courier_chips_render_on_the_to_handover_sub_tab_only(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $handover = $this->statusWithStage('to_handover', 'Order Being Shipped');
        $this->makeOrder('for_pickup', 'QX-H-201', 'quadx', $handover);
        $this->makeOrder('for_pickup', 'QX-H-202', 'quadx', $handover);

        $this->get($this->queueUrl() . '?filter=to_ship&sub=to_handover')
            ->assertOk()
            ->assertSee('Parcels waiting for handover')
            ->assertSee('QuadX');

        // Not on To Ship's OTHER sub-tab...
        $this->get($this->queueUrl() . '?filter=to_ship&sub=to_pack')
            ->assertOk()
            ->assertDontSee('Parcels waiting for handover');

        // ...nor on the bare default (to_ship/to_pack)...
        $this->get($this->queueUrl())
            ->assertOk()
            ->assertDontSee('Parcels waiting for handover');

        // ...nor on any other top-level tab.
        $this->get($this->queueUrl() . '?filter=shipping')
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
    // fulfillment-placement Task 2 REWRITE (was: "cancelled booking rows
    // keep drawer access via a tracking button"). That test's premise was
    // the tabs-v3 rule that a courier-cancelled BOOKING pulled the order
    // into the Cancelled TAB, where it was offered Rebook plus a drawer.
    // A cancelled booking is not a cancelled order, and it no longer moves
    // the order anywhere: the row stays in the tab its core status is
    // placed in, which for an order nobody has re-statused is still To
    // Pack, and Book (a fresh shipment, which the server permits since the
    // old booking is not live) is exactly the action it needs there.
    // ------------------------------------------------------------------

    public function test_a_courier_cancelled_booking_leaves_the_order_in_its_own_tab_and_still_bookable(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        // Booking again needs an enabled courier (same rule as first-time Book).
        $this->enableQuadxStaging();

        $order = $this->makeOrder('canceled', 'QX-CXL-001');

        $this->get($this->queueUrl() . '?filter=to_ship&sub=to_pack')
            ->assertOk()
            ->assertSee('QX-CXL-001')
            ->assertSee('data-count-for="to_pack">1<', false)
            // Book renders because the server would accept it: the order is
            // To-Pack-placed and its old booking is not live.
            ->assertSee('openBooking(JSON.parse(', false)
            ->assertSee("cancel-order-{$order->id}", false);

        // It is NOT in Cancelled: no courier state ever decides that tab.
        $this->get($this->queueUrl() . '?filter=cancelled')
            ->assertOk()
            ->assertSee('data-count-for="cancelled">0<', false)
            ->assertDontSee('QX-CXL-001');
    }

    /**
     * The other half of the same story: once the courier's status map has
     * moved the CORE status to a cancelled-placed one, the row moves to
     * Cancelled and offers no workflow action at all. Tracking still renders
     * (owner ruling, 2026-08-09: every tab but Unpaid), the waybill does not
     * (the booking is cancelled, and the courier refuses to print one). The
     * row-level "book again" hint (co-actions__hint) was removed on owner
     * directive (2026-08-09); this asserts it never renders here.
     */
    public function test_a_cancelled_placed_row_offers_no_workflow_actions(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        $order = $this->makeOrder('canceled', 'QX-CXL-002', 'quadx', $this->statusWithStage('cancelled', 'Canceled'));

        $response = $this->get($this->queueUrl() . '?filter=cancelled');
        $response->assertOk()
            ->assertSee('QX-CXL-002')
            ->assertDontSee('co-actions__hint', false)
            ->assertSee('openDrawer(JSON.parse(', false)
            ->assertDontSee('openBooking(JSON.parse(', false)
            ->assertDontSee('Print waybill')
            ->assertDontSee("cancel-order-{$order->id}", false)
            ->assertDontSee("cancel-fulfillment-{$order->id}", false);
    }

    // ------------------------------------------------------------------
    // The Book gate, now the spec's own rule: Book is a To Pack action,
    // refused server-side anywhere else, so no Unpaid or Cancelled row
    // offers it - with or without a prior booking.
    // ------------------------------------------------------------------

    public function test_unpaid_tab_row_never_offers_a_book_button(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        $unpaidStatus = $this->statusWithStage('unpaid', 'Unpaid');
        $order = $this->makeOrder(status: $unpaidStatus);

        $response = $this->get($this->queueUrl() . '?filter=unpaid');
        $response->assertOk()
            ->assertSee('#' . $order->id, false)
            // 'openBooking(JSON.parse(' is the ROW button's own call
            // (carrying this row's payload) — distinct from the booking
            // modal drawer's static "openBooking(active)" reopen call,
            // which renders regardless of any row (asserted absent here,
            // not the drawer markup itself).
            ->assertDontSee('openBooking(JSON.parse(', false);
    }

    public function test_cancelled_tab_row_with_no_prior_booking_never_offers_a_book_button(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        // A REAL order cancel (stage 'cancelled') that was never booked at
        // all — courier_tracking_number null, so $rebookable is false. Per
        // the tab-model spec this row is "view only", never Book.
        $cancelledStatus = $this->statusWithStage('cancelled', 'Canceled');
        $order = $this->makeOrder(status: $cancelledStatus);

        $response = $this->get($this->queueUrl() . '?filter=cancelled');
        $response->assertOk()
            ->assertSee('#' . $order->id, false)
            ->assertDontSee('openBooking(JSON.parse(', false);
    }

    public function test_to_pack_rows_offer_book_and_to_handover_rows_offer_the_waybill(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        $unbooked = $this->makeOrder();
        $booked = $this->makeOrder('for_pickup', 'QX-BOOKGATE-001', 'quadx', $this->statusWithStage('to_handover', 'Order Being Shipped'));

        $this->get($this->queueUrl() . '?filter=to_ship&sub=to_pack')
            ->assertOk()
            ->assertSee('#' . $unbooked->id, false)
            ->assertSee('openBooking(', false);

        // To Handover rows never show Book at all (their tab's actions are
        // Print waybill / Tracking / Cancel booking).
        $this->get($this->queueUrl() . '?filter=to_ship&sub=to_handover')
            ->assertOk()
            ->assertSee('QX-BOOKGATE-001')
            ->assertSee('Print waybill');
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

        $order = $this->makeOrder('for_pickup', 'QX-TAB-001', 'quadx', $this->statusWithStage('to_handover', 'Order Being Shipped'));

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
            // The To Ship tab link always lands on its default sub (to_pack),
            // carrying the search + page size along.
            ->assertSee('filter=to_ship&amp;sub=to_pack&amp;q=QX&amp;per_page=10', false)
            ->assertSee('filter=shipping&amp;q=QX&amp;per_page=10', false)
            // The filters form re-submits within the active tab.
            ->assertSee('name="filter" value="delivered"', false);
    }

    public function test_sub_tab_links_preserve_search_and_per_page_params(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $response = $this->get($this->queueUrl() . '?filter=to_ship&sub=to_pack&q=QX&per_page=10');
        $response->assertOk()
            ->assertSee('filter=to_ship&amp;sub=to_handover&amp;q=QX&amp;per_page=10', false)
            ->assertSee('name="sub" value="to_pack"', false);
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
    // Review fix: the pager's Previous/Next anchors were DOUBLE-escaped —
    // x-admin.button's own <a href="{{ $href }}"> (button.blade.php:19)
    // escapes once, and index.blade.php was ALSO echoing ({{ }}) an
    // already-HTML-safe URL onto the component tag, escaping it a second
    // time. A real browser then requested the LITERAL param name
    // "amp;sub" (and "amp;q", "amp;per_page") instead of "sub"/"q"/
    // "per_page" on click — silently dropping every one of them from
    // Next/Previous. This test doesn't just assertSee() a substring (that
    // passed even with the bug, since the FIRST escape pass alone reads
    // fine to a naive assertion) — it extracts the rendered href, decodes
    // it exactly once (html_entity_decode(), what a browser does), and
    // FOLLOWS it, so a regression back to double-escaping is caught by an
    // actual failed navigation, not a string match.
    // ------------------------------------------------------------------

    public function test_pager_next_link_is_single_escaped_and_preserves_filter_sub_and_search(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        // 12 unbooked (to_pack) orders, all matching q=Doe (makeOrder()'s
        // fixed shipping_last_name) — enough for two pages at per_page=10.
        for ($i = 0; $i < 12; $i++) {
            $this->makeOrder();
        }

        $page1 = $this->get($this->queueUrl() . '?filter=to_ship&sub=to_pack&q=Doe&per_page=10');
        $page1->assertOk();
        $content = $page1->getContent();

        // The bug's exact signature: a doubly-escaped '&' reads as
        // '&amp;amp;' in the raw response body.
        $this->assertStringNotContainsString('&amp;amp;', $content);

        // Pull the Next anchor's href attribute out of the rendered page.
        $this->assertMatchesRegularExpression('/Next<\/a>/', $content, 'Expected a real (non-disabled) Next anchor with two pages available.');
        preg_match('/<a href="([^"]+)"[^>]*>Next<\/a>/', $content, $match);
        $this->assertNotEmpty($match, 'Could not find the Next pager anchor in the response.');

        // Exactly what a browser does with an href attribute: HTML-entity
        // decode it ONCE before issuing the request. If the view still
        // double-escaped, this single decode leaves a literal '&amp;' in
        // the URL, which becomes the query param name "amp;sub" etc. below.
        $nextHref = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5);

        $page2 = $this->get($nextHref);
        $page2->assertOk()
            ->assertSee('11 to 12 of 12')
            // filter/sub/q/per_page all survived the click.
            ->assertSee('name="filter" value="to_ship"', false)
            ->assertSee('name="sub" value="to_pack"', false)
            ->assertSee('value="Doe"', false)
            ->assertSee('10 per page');
    }

    // ------------------------------------------------------------------
    // Review fix (coverage gap): an unrecognized top-level filter value
    // must degrade gracefully to the default tab, never a broken/empty
    // page — same "defaults rather than breaks" contract as an invalid
    // ?sub= already has its own test.
    // ------------------------------------------------------------------

    public function test_garbage_top_level_filter_defaults_to_to_ship(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $unbooked = $this->makeOrder();

        $response = $this->get($this->queueUrl() . '?filter=garbage');
        $response->assertOk()
            ->assertSee('name="filter" value="to_ship"', false)
            ->assertSee('#' . $unbooked->id, false);
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

    /**
     * Owner directive (2026-08-09): the command-bar button reads "Refresh
     * from couriers" (the old "Poll courier statuses" label was developer
     * jargon), and its explanatory paragraph is gone entirely - wasted
     * space, per the owner.
     */
    public function test_command_bar_shows_the_refresh_button_without_the_removed_copy(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $response = $this->get($this->queueUrl());
        $response->assertOk()
            ->assertSee('Refresh from couriers')
            ->assertDontSee('Poll courier statuses')
            ->assertDontSee('Pulls the latest status for every in-flight shipment');
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
    // fulfillment-placement Task 2 REWRITE (was: "creating spx order counts
    // and lists under the to handover sub tab"). The tabs-v3 'booking'
    // collapse - a mid-async-create order counting under To Handover
    // whatever its status said - is exactly the courier-state membership
    // this task removes. The honest replacement: an in-flight create shows
    // its state on the ROW (badge "Booking", label "Creating…") while
    // sitting in the tab its core status is placed in, and moving that
    // status is what moves the row.
    // ------------------------------------------------------------------

    public function test_a_creating_spx_order_shows_its_in_flight_state_in_its_placements_tab(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        // A genuinely never-booked order alongside it, on the same status.
        $this->makeOrder();

        $creatingOrder = $this->makeOrder();
        $creatingOrder->forceFill([
            'courier_provider' => 'spx',
            'courier_status' => 'creating',
            'courier_tracking_number' => null,
        ])->save();

        $response = $this->get($this->queueUrl());
        $response->assertOk()
            // Both are To-Pack-placed, so both count there. A missing
            // tracking number is not a tab input either way.
            ->assertSee('data-count-for="to_pack">2<', false)
            ->assertSee('data-count-for="to_handover">0<', false)
            ->assertSee('data-count-for="to_ship">2<', false)
            ->assertSee('#' . $creatingOrder->id, false)
            // The row's own courier state is reported honestly: the spine
            // badge reads Booking (PhaseTone 'info') and the raw-status note
            // reads Creating.
            ->assertSee('Booking')
            ->assertSee('Creating');
        // Top-level Booking tab no longer exists at all.
        $response->assertDontSee('data-count-for="booking"', false);

        // Move the core status, and only then does the row move.
        $creatingOrder->forceFill(['order_status_id' => $this->statusWithStage('to_handover', 'Order Being Shipped')->id])->save();

        $this->get($this->queueUrl() . '?filter=to_ship&sub=to_handover')
            ->assertOk()
            ->assertSee('#' . $creatingOrder->id, false)
            ->assertSee('data-count-for="to_handover">1<', false)
            ->assertSee('Booking')
            // Its drawer opener is the honest "Creating…" one (no tracking
            // number to track yet).
            ->assertSee('Creating')
            ->assertSee('openDrawer(JSON.parse(', false);

        $this->get($this->queueUrl())
            ->assertOk()
            ->assertDontSee('#' . $creatingOrder->id, false);

        // Legacy ?filter=booking still redirects (301) to To Ship, To
        // Handover - unchanged by this task.
        $legacy = $this->get($this->queueUrl() . '?filter=booking');
        $legacy->assertStatus(301);
        $this->assertStringContainsString('sub=to_handover', $legacy->headers->get('Location'));
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
        $order = $this->makeOrder('for_pickup', 'QX-WRAP-001', 'quadx', $this->statusWithStage('to_handover', 'Order Being Shipped'));

        $response = $this->get($this->queueUrl() . '?filter=to_ship&sub=to_handover');
        $content = $response->getContent();

        $response->assertOk();
        // Task 4 wording: an admin booking-cancel now RESETS the courier
        // fields and returns the order to To Pack (no longer "remains
        // rebookable" in place under Cancelled).
        $this->assertStringContainsString('This cancels the courier booking. The order returns to To Pack for rebooking.', $content);

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

        $order = $this->makeOrder('1001', 'SPX-FEE-1', 'spx', $this->statusWithStage('to_handover', 'Order Being Shipped'));
        $order->forceFill(['courier_meta' => json_encode([
            'estimated_shipping_fee' => 111.11,
            'actual_fee' => ['actual_shipping_fee' => 222.22],
        ])])->save();

        $response = $this->get($this->queueUrl() . '?filter=to_ship&sub=to_handover');
        $response->assertOk()
            ->assertSee('SPX-FEE-1')
            ->assertSee('111.11', false)
            ->assertSee('222.22', false);
    }

    // ------------------------------------------------------------------
    // Owner task (2026-08-08, "SPX status from code to their actual
    // meaning"): the row's status-note chip reads the DOCUMENTED human
    // label (SpxFulfillment::statusLabels()), not the bare code — available
    // immediately, with no admin visit to Settings needed first, since
    // index.blade.php's $humanizeStatus() falls back to the courier's own
    // statusLabels() before ucwords(raw code).
    // ------------------------------------------------------------------

    public function test_a_live_spx_order_status_note_shows_the_documented_label_not_the_bare_code(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->makeOrder('1001', 'SPX-STATUS-1', 'spx', $this->statusWithStage('to_handover', 'Order Being Shipped'));

        $response = $this->get($this->queueUrl() . '?filter=to_ship&sub=to_handover');
        $response->assertOk()
            ->assertSee('SPX-STATUS-1')
            ->assertSee('Pending Pickup');

        // The raw code alone is never what the admin sees for a mapped
        // status — it always resolves to the human label.
        $response->assertDontSee('>1001<', false);
    }

    /** A cached spx_status_labels setting (from an explicit Refresh) still takes precedence over the built-in accessor. */
    public function test_a_cached_spx_status_label_from_a_prior_refresh_still_wins_over_the_builtin_map(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        Setting::create([
            'group' => 'courier',
            'key' => 'spx_status_labels',
            'value' => json_encode(['1001' => 'Awaiting Rider Pickup (custom)']),
        ]);

        $this->makeOrder('1001', 'SPX-STATUS-2', 'spx', $this->statusWithStage('to_handover', 'Order Being Shipped'));

        $this->get($this->queueUrl() . '?filter=to_ship&sub=to_handover')
            ->assertOk()
            ->assertSee('Awaiting Rider Pickup (custom)')
            ->assertDontSee('Pending Pickup');
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
