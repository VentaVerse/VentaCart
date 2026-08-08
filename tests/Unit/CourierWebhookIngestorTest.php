<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Services\ExtensionManager;
use Extensions\CourierFulfillment\Support\CourierWebhookIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Final-review fix (Finding 3): CourierWebhookIngestor's PRIMARY
 * reference_id match must verify the event belongs to the order's CURRENT
 * shipment before writing anything. reference_id is just the order id and
 * is REUSED across shipments (cancel -> rebook books the same reference_id
 * again, possibly with a different courier) — so a late webhook from a
 * superseded shipment must be ignored, never applied over the current
 * booking's status.
 *
 * The rule: the order's provider must match the webhook's provider, AND —
 * only when BOTH the order and the event carry a tracking number — those
 * must match too. Events without a tracking number (and orders still
 * mid-create without one) skip the tracking check only, never the provider
 * one — which is exactly why QuadX's pre-existing webhook tests pass
 * byte-untouched.
 */
class CourierWebhookIngestorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ExtensionManager::class)->install('courier-fulfillment');
        app(ExtensionManager::class)->bootEnabled();
    }

    private function makeOrder(array $courierFill = []): Order
    {
        $status = OrderStatus::firstOrCreate(
            ['store_id' => 1, 'name' => 'Pending'],
            ['sort_order' => 1, 'is_default' => true],
        );

        $order = Order::create([
            'store_id' => 1, 'order_status_id' => $status->id,
            'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com',
            'shipping_first_name' => 'Jane', 'shipping_last_name' => 'Doe',
            'shipping_address_1' => '123 Mango St', 'shipping_city' => 'Cebu City',
            'shipping_state' => 'Cebu', 'payment_method' => 'bank_transfer',
            'subtotal' => 1000, 'shipping_cost' => 100, 'total' => 1100,
        ]);

        if ($courierFill !== []) {
            $order->forceFill($courierFill)->save();
        }

        return $order;
    }

    private function event(Order $order, array $overrides = []): array
    {
        return array_merge([
            'reference_id'    => (string) $order->id,
            'tracking_number' => null,
            'status'          => '4001',
            'occurred_at'     => null,
            'remarks'         => null,
        ], $overrides);
    }

    public function test_late_event_carrying_a_superseded_tracking_number_is_ignored(): void
    {
        // Cancelled-then-rebooked: the order now carries the NEW shipment's
        // tracking number; a straggler event from the OLD shipment arrives
        // with the same reference_id and provider.
        $order = $this->makeOrder([
            'courier_provider' => 'spx',
            'courier_tracking_number' => 'SPEPH000000000NEW',
            'courier_status' => '1001',
        ]);

        CourierWebhookIngestor::ingest('spx', [
            $this->event($order, ['tracking_number' => 'SPEPH000000000OLD', 'status' => '7001']),
        ]);

        $order->refresh();
        $this->assertSame('1001', $order->courier_status);
        $this->assertSame('SPEPH000000000NEW', $order->courier_tracking_number);
    }

    public function test_event_from_a_different_provider_with_the_same_reference_id_is_ignored(): void
    {
        // Rebooked cross-provider: the order's CURRENT booking is QuadX, but
        // a late SPX event still knows this order id as its reference_id.
        $order = $this->makeOrder([
            'courier_provider' => 'quadx',
            'courier_tracking_number' => 'QX-CURRENT-1',
            'courier_status' => 'for_pickup',
        ]);

        CourierWebhookIngestor::ingest('spx', [
            $this->event($order, ['tracking_number' => 'SPEPH000000000OLD', 'status' => '7001']),
        ]);

        $order->refresh();
        $this->assertSame('for_pickup', $order->courier_status);
    }

    public function test_event_matching_the_current_shipment_is_applied(): void
    {
        $order = $this->makeOrder([
            'courier_provider' => 'spx',
            'courier_tracking_number' => 'SPEPH000000000NEW',
            'courier_status' => '2001',
        ]);

        CourierWebhookIngestor::ingest('spx', [
            $this->event($order, ['tracking_number' => 'SPEPH000000000NEW', 'status' => '4001']),
        ]);

        $this->assertSame('4001', $order->fresh()->courier_status);
    }

    public function test_event_without_a_tracking_number_is_applied_when_the_provider_matches(): void
    {
        // The tracking check only applies when BOTH sides carry one — an
        // event with none (some courier payloads omit it) must still land,
        // exactly as before this fix.
        $order = $this->makeOrder([
            'courier_provider' => 'quadx',
            'courier_tracking_number' => 'QX-1',
            'courier_status' => 'for_pickup',
        ]);

        CourierWebhookIngestor::ingest('quadx', [
            $this->event($order, ['status' => 'delivered']),
        ]);

        $this->assertSame('delivered', $order->fresh()->courier_status);
    }

    public function test_event_for_an_order_still_awaiting_its_tracking_number_is_applied(): void
    {
        // An async (SPX) order mid-create has NO tracking number on file yet
        // — a webhook can beat the create-result poll. Order-side tracking
        // absent => the tracking check is skipped (provider still verified).
        $order = $this->makeOrder([
            'courier_provider' => 'spx',
            'courier_status' => 'creating',
        ]);

        CourierWebhookIngestor::ingest('spx', [
            $this->event($order, ['tracking_number' => 'SPEPH000000000001', 'status' => '1001']),
        ]);

        $this->assertSame('1001', $order->fresh()->courier_status);
    }

    public function test_tracking_number_fallback_lookup_still_works_when_reference_id_is_absent(): void
    {
        // The F2-scoped fallback (no usable reference_id -> tracking number
        // + provider lookup) is deliberately untouched by Finding 3.
        $order = $this->makeOrder([
            'courier_provider' => 'spx',
            'courier_tracking_number' => 'SPEPH000000000777',
            'courier_status' => '2001',
        ]);

        CourierWebhookIngestor::ingest('spx', [
            $this->event($order, ['reference_id' => null, 'tracking_number' => 'SPEPH000000000777', 'status' => '4001']),
        ]);

        $this->assertSame('4001', $order->fresh()->courier_status);
    }
}
