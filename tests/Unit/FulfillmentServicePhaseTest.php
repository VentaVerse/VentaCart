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
    // fulfillment-tabs-v3 Task 2 (amended by the Task 4 owner ruling):
    // FulfillmentService::tabBucket() — the STAGE-FIRST tab/tally
    // membership function. Unlike phaseFor() above (which stays a pure
    // courier-phase derivation, used for row badges), tabBucket() layers
    // the order's own OrderStatus::$fulfillment_stage on top: 'cancelled'
    // stage claims the order's bucket absolutely; 'unpaid' stage claims it
    // only while the order has NO active booking (an actively-booked order
    // follows its courier phase — the booking puts it in the shipping
    // pipeline); a 'ready'-stage order buckets by its courier phase, with
    // 'booking' (async create in flight) collapsing into 'to_handover' for
    // membership purposes only.
    //
    // $order->setRelation('status', ...) stands in for a real OrderStatus
    // row without touching the DB — same "no real Order row" pattern this
    // file already uses via order()/setRawAttributes() above.
    // ------------------------------------------------------------------

    private function orderWithStage(string $stage, array $attributes): Order
    {
        $order = $this->order($attributes);
        $order->setRelation('status', new \App\Models\OrderStatus(['fulfillment_stage' => $stage]));

        return $order;
    }

    public function test_unpaid_stage_claims_the_bucket_only_while_there_is_no_active_booking(): void
    {
        // Owner ruling (spec amendment 59bd751): an ACTIVE in-transit
        // booking pulls the order OUT of Unpaid — the booking puts it in
        // the shipping pipeline, whatever its payment status.
        $booked = $this->orderWithStage('unpaid', [
            'courier_tracking_number' => 'QX-ACTIVE-1',
            'courier_provider' => 'quadx',
            'courier_status' => 'in_transit',
        ]);
        $this->assertSame('in_transit', FulfillmentService::tabBucket($booked));

        // With no booking at all, unpaid claims the order.
        $unbooked = $this->orderWithStage('unpaid', [
            'courier_tracking_number' => null,
            'courier_provider' => null,
            'courier_status' => null,
        ]);
        $this->assertSame('unpaid', FulfillmentService::tabBucket($unbooked));

        // A booking that is no longer live (courier-cancelled) is not an
        // active booking — unpaid claims the order again.
        $cancelledBooking = $this->orderWithStage('unpaid', [
            'courier_tracking_number' => 'QX-ACTIVE-1',
            'courier_provider' => 'quadx',
            'courier_status' => 'canceled',
        ]);
        $this->assertSame('unpaid', FulfillmentService::tabBucket($cancelledBooking));
    }

    public function test_cancelled_stage_claims_the_bucket_even_with_an_in_transit_booking(): void
    {
        $order = $this->orderWithStage('cancelled', [
            'courier_tracking_number' => 'QX-ACTIVE-2',
            'courier_provider' => 'quadx',
            'courier_status' => 'in_transit',
        ]);
        $this->assertSame('cancelled', FulfillmentService::tabBucket($order));
    }

    public function test_ready_stage_with_no_tracking_buckets_to_pack(): void
    {
        $order = $this->orderWithStage('ready', [
            'courier_tracking_number' => null,
            'courier_provider' => null,
            'courier_status' => null,
        ]);
        $this->assertSame('to_pack', FulfillmentService::tabBucket($order));
    }

    /**
     * "creating" (an async courier's booking mid-flight, phase 'booking')
     * counts under 'to_handover' for TAB MEMBERSHIP — but phaseFor() itself
     * (what a row's own badge reads) keeps reporting 'booking' unchanged,
     * so the row's own payload never loses the honest in-flight state.
     */
    public function test_ready_stage_creating_order_buckets_to_handover_but_keeps_booking_phase_on_the_row(): void
    {
        $order = $this->orderWithStage('ready', [
            'courier_tracking_number' => null,
            'courier_provider' => 'spx',
            'courier_status' => 'creating',
        ]);
        $this->assertSame('to_handover', FulfillmentService::tabBucket($order));
        $this->assertSame('booking', FulfillmentService::phaseFor($order));
    }

    /** No explicit stage relation loaded (null order_status_id) defaults to 'ready' — same default the migration itself uses. */
    public function test_missing_status_relation_defaults_to_ready_stage(): void
    {
        $order = $this->order([
            'courier_tracking_number' => null,
            'courier_provider' => null,
            'courier_status' => null,
        ]);
        $this->assertSame('to_pack', FulfillmentService::tabBucket($order));
    }

    public function test_tab_bucket_for_parts_mirrors_the_same_stage_first_rule(): void
    {
        // Owner ruling: unpaid claims only the not-in-pipeline phases…
        $this->assertSame('unpaid', FulfillmentService::tabBucketForParts('unpaid', 'to_pack'));
        $this->assertSame('unpaid', FulfillmentService::tabBucketForParts('unpaid', 'cancelled'));
        // …while an active booking's phase wins ('booking' collapses to
        // to_handover exactly as it does for a ready-stage order).
        $this->assertSame('in_transit', FulfillmentService::tabBucketForParts('unpaid', 'in_transit'));
        $this->assertSame('to_handover', FulfillmentService::tabBucketForParts('unpaid', 'to_handover'));
        $this->assertSame('to_handover', FulfillmentService::tabBucketForParts('unpaid', 'booking'));
        // Cancelled stage stays absolute.
        $this->assertSame('cancelled', FulfillmentService::tabBucketForParts('cancelled', 'in_transit'));
        $this->assertSame('to_pack', FulfillmentService::tabBucketForParts('ready', 'to_pack'));
        $this->assertSame('to_handover', FulfillmentService::tabBucketForParts('ready', 'booking'));
        $this->assertSame('in_transit', FulfillmentService::tabBucketForParts('ready', 'in_transit'));
    }
}
