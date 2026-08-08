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

        foreach (['To Book', 'Booking', 'To Handover', 'In Transit', 'Delivered', 'Failed', 'Cancelled'] as $label) {
            $response->assertSee($label);
        }

        // Counts, per phase, from the seeded distribution above.
        $response->assertSee('data-count-for="to_book">2<', false);
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
            ->assertSee('filter=to_book&amp;q=QX&amp;per_page=10', false)
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
}
