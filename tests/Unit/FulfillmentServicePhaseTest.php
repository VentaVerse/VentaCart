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
 * group orders by — it layers "no booking yet" (to_book) and "booked with
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

    public function test_order_never_booked_is_to_book(): void
    {
        $order = $this->order(['courier_tracking_number' => null, 'courier_provider' => null, 'courier_status' => null]);
        $this->assertSame('to_book', FulfillmentService::phaseFor($order));
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
}
