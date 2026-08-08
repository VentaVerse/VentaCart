<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\ExtensionManager;
use Extensions\CourierFulfillment\CourierManager;
use Extensions\CourierFulfillment\FulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeCourierFulfillment;
use Tests\TestCase;

/**
 * Task W1 (fulfillment-workflow-v2 plan): FulfillmentService::phaseFor()
 * is the courier-agnostic entry point the future queue tabs (Task W2) will
 * group orders by — it layers "no booking yet" (`to_pack`, renamed by
 * fulfillment-tabs-v3 Task 2 from its original bucket name) and "booked with
 * an unregistered/unknown courier" on top of whatever the resolved
 * courier's own statusPhase() reports, so callers never need to special-
 * case CourierManager::get() returning null themselves.
 *
 * Extends the Laravel TestCase (not a plain PHPUnit one) purely so the
 * extension's runtime PSR-4 autoloader is registered before
 * Extensions\CourierFulfillment\* is referenced (see PhaseToneTest's
 * docblock). CourierManager's registry is process-global static state (same
 * caveat as tests/Feature/AdminV2/FulfillmentTest.php's tearDown()) — the
 * 'fake' registration is removed after every test so it never leaks into a
 * later test in this run ('quadx' is left registered, same convention as
 * that file, since re-registering it is harmless and every other test in
 * the suite already tolerates it staying registered process-wide).
 */
class FulfillmentServicePhaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ExtensionManager::class)->install('courier-fulfillment');
        app(ExtensionManager::class)->bootEnabled(); // registers QuadXFulfillment into CourierManager
        CourierManager::register(FakeCourierFulfillment::class);
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

    private function order(array $attributes): Order
    {
        $order = new Order();
        $order->setRawAttributes($attributes);
        return $order;
    }

    public function test_order_never_booked_is_to_pack(): void
    {
        $order = $this->order(['courier_tracking_number' => null, 'courier_provider' => null, 'courier_status' => null]);
        $this->assertSame('to_pack', FulfillmentService::phaseFor($order));
    }

    public function test_booked_quadx_order_resolves_the_couriers_own_bucket(): void
    {
        $cases = [
            'for_pickup'      => 'to_handover',
            'in_transit'      => 'in_transit',
            'delivered'       => 'delivered',
            'canceled'        => 'cancelled',
            'returned'        => 'returning',
            'failed_delivery' => 'failed',
        ];

        foreach ($cases as $status => $expectedPhase) {
            $order = $this->order([
                'courier_tracking_number' => 'QX-1',
                'courier_provider' => 'quadx',
                'courier_status' => $status,
            ]);
            $this->assertSame($expectedPhase, FulfillmentService::phaseFor($order), "status={$status}");
        }
    }

    public function test_booked_with_unregistered_provider_falls_back_to_to_handover(): void
    {
        $order = $this->order([
            'courier_tracking_number' => 'XX-1',
            'courier_provider' => 'some-retired-courier',
            'courier_status' => 'whatever',
        ]);
        $this->assertSame('to_handover', FulfillmentService::phaseFor($order));
    }

    public function test_a_second_courier_resolves_through_its_own_statusphase_not_quadxs(): void
    {
        // FakeCourierFulfillment::statusPhase() doesn't return a 'phase' key
        // at all (it predates the spine) — phaseFor() must not blow up on a
        // courier that hasn't been updated yet, and must degrade sensibly.
        $order = $this->order([
            'courier_tracking_number' => 'FAKE-1',
            'courier_provider' => 'fake',
            'courier_status' => 'in_transit',
        ]);
        $this->assertSame('to_handover', FulfillmentService::phaseFor($order));
    }

    // ------------------------------------------------------------------
    // Task T4 fix: an async courier's in-flight creation (SPX's
    // courier_status='creating', no tracking number yet) was previously
    // short-circuiting to 'to_pack' below — this is the regression test
    // for that fix (see this method's own docblock in FulfillmentService).
    // SPX is a REAL registered courier (booted via bootEnabled() in
    // setUp()), so no register()/tearDown() dance is needed for it.
    // ------------------------------------------------------------------

    public function test_creating_order_with_no_tracking_number_resolves_to_the_booking_phase(): void
    {
        $order = $this->order([
            'courier_tracking_number' => null,
            'courier_provider' => 'spx',
            'courier_status' => 'creating',
        ]);
        $this->assertSame('booking', FulfillmentService::phaseFor($order));
    }

    public function test_quadx_orders_are_unaffected_by_the_creating_state_fix(): void
    {
        // QuadX's createShipment() is synchronous and never sets
        // courier_status='creating' — proves the fix is scoped to that
        // exact status string, not to "no tracking number" in general.
        $order = $this->order([
            'courier_tracking_number' => null,
            'courier_provider' => 'quadx',
            'courier_status' => null,
        ]);
        $this->assertSame('to_pack', FulfillmentService::phaseFor($order));
    }

    // ------------------------------------------------------------------
    // fulfillment-placement Task 2: FulfillmentService::tabBucket() is the
    // membership function, and it reads ONE thing - the core order status's
    // fulfillment_placement. The tabs-v3 rules these tests used to assert
    // (stage-vs-phase arbitration, the absolute cancelled arm, the unpaid
    // carve-out, the 'booking' collapse) no longer exist as concepts, so
    // each is replaced here by the equivalent assertion under the new rule:
    // the SAME courier states must now change nothing at all.
    //
    // phaseFor() above is unchanged and still describes the SHIPMENT (row
    // badge, drawer stepper, returns page). The pairs below assert both at
    // once, so a future edit cannot quietly re-couple them.
    //
    // $order->setRelation('status', ...) stands in for a real OrderStatus
    // row without touching the DB — same "no real Order row" pattern this
    // file already uses via order()/setRawAttributes() above.
    // ------------------------------------------------------------------

    private function orderPlaced(string $placement, array $attributes): Order
    {
        $order = $this->order($attributes);
        $order->setRelation('status', new \App\Models\OrderStatus(['fulfillment_placement' => $placement]));

        return $order;
    }

    /** Was: unpaid claims the bucket only while there is no active booking. Now: the placement answers, whatever the booking. */
    public function test_an_unpaid_placed_order_stays_unpaid_whatever_its_courier_state(): void
    {
        foreach ([
            ['QX-ACTIVE-1', 'in_transit'],   // a live booking
            [null, null],                     // never booked
            ['QX-ACTIVE-1', 'canceled'],      // a booking that is no longer live
        ] as [$tracking, $status]) {
            $order = $this->orderPlaced('unpaid', [
                'courier_tracking_number' => $tracking,
                'courier_provider' => $tracking === null ? null : 'quadx',
                'courier_status' => $status,
            ]);
            $this->assertSame('unpaid', FulfillmentService::tabBucket($order), "status={$status}");
        }
    }

    /** Was: cancelled stage claims the bucket ABSOLUTELY (a precedence rule). Now: no precedence exists, it is simply the placement. */
    public function test_a_cancelled_placed_order_is_cancelled_even_with_an_in_transit_booking(): void
    {
        $order = $this->orderPlaced('cancelled', [
            'courier_tracking_number' => 'QX-ACTIVE-2',
            'courier_provider' => 'quadx',
            'courier_status' => 'in_transit',
        ]);
        $this->assertSame('cancelled', FulfillmentService::tabBucket($order));
        // The SHIPMENT is still honestly in transit - that fact just has no
        // say in which tab the order appears in.
        $this->assertSame('in_transit', FulfillmentService::phaseFor($order));
    }

    /**
     * The motivating defect, at unit level: a Delivered-placed order with no
     * courier data at all. phaseFor() answers 'to_pack' (never booked, which
     * is true of every OpenCart-era order), and under tabs v3 that is what
     * decided the tab - which is why 24,620 delivered orders sat in To Pack.
     * Membership now reads the placement and puts it in Delivered.
     */
    public function test_a_delivered_placed_order_with_no_courier_data_is_delivered_not_to_pack(): void
    {
        $order = $this->orderPlaced('delivered', [
            'courier_tracking_number' => null,
            'courier_provider' => null,
            'courier_status' => null,
        ]);
        $this->assertSame('delivered', FulfillmentService::tabBucket($order));
        $this->assertSame('to_pack', FulfillmentService::phaseFor($order));
    }

    /** Was: a ready-stage 'creating' order collapses into to_handover for membership. Now: its placement decides, and the 'booking' phase stays a row-level fact. */
    public function test_a_creating_order_sits_in_its_placements_tab_while_its_phase_still_reads_booking(): void
    {
        $order = $this->orderPlaced('to_pack', [
            'courier_tracking_number' => null,
            'courier_provider' => 'spx',
            'courier_status' => 'creating',
        ]);
        $this->assertSame('to_pack', FulfillmentService::tabBucket($order));
        $this->assertSame('booking', FulfillmentService::phaseFor($order));
    }

    public function test_every_placement_answers_itself(): void
    {
        foreach (FulfillmentService::PLACEMENTS as $placement) {
            $order = $this->orderPlaced($placement, [
                'courier_tracking_number' => 'QX-SAME-1',
                'courier_provider' => 'quadx',
                'courier_status' => 'for_pickup',
            ]);
            $this->assertSame($placement, FulfillmentService::tabBucket($order));
        }
    }

    /** No status relation loaded (null order_status_id) defaults to 'to_pack', the placement column's own default. */
    public function test_missing_status_relation_defaults_to_to_pack(): void
    {
        $order = $this->order([
            'courier_tracking_number' => null,
            'courier_provider' => null,
            'courier_status' => null,
        ]);
        $this->assertSame('to_pack', FulfillmentService::tabBucket($order));
    }

    /** A value outside the enum (unreachable through the column, defensive here) never drops the order out of every tab. */
    public function test_an_unrecognised_placement_falls_back_to_to_pack(): void
    {
        $order = $this->orderPlaced('not_a_tab', [
            'courier_tracking_number' => null,
            'courier_provider' => null,
            'courier_status' => null,
        ]);
        $this->assertSame('to_pack', FulfillmentService::tabBucket($order));
    }

    // ------------------------------------------------------------------
    // The booking gate shares that single rule: To Pack placement only.
    // ------------------------------------------------------------------

    public function test_booking_is_permitted_only_for_a_to_pack_placed_order(): void
    {
        $attributes = ['courier_tracking_number' => null, 'courier_provider' => null, 'courier_status' => null];

        $this->assertNull(FulfillmentService::bookingBlockedReason($this->orderPlaced('to_pack', $attributes)));

        foreach (['unpaid', 'to_handover', 'in_transit', 'delivered', 'failed', 'cancelled'] as $placement) {
            $reason = FulfillmentService::bookingBlockedReason($this->orderPlaced($placement, $attributes));
            $this->assertNotNull($reason, "placement={$placement}");
            $this->assertStringContainsString('Set its status to one placed under To Pack before booking.', $reason);
            $this->assertStringContainsString(FulfillmentService::TAB_LABELS[$placement], $reason);
        }
    }
}
