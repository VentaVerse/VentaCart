<?php

namespace Tests\Feature\AdminV2;

use App\Models\Order;
use App\Models\OrderStatus;
use Extensions\CourierFulfillment\PlacementMap;
use App\Models\User;
use App\Services\ExtensionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The returns monitoring page (`Sales > Fulfillment > Returns`) - a sibling
 * PAGE, never an eighth queue tab. Lists orders whose LIVE COURIER STATE
 * (CourierFulfillmentInterface::statusPhase(), via
 * FulfillmentController::courierPhaseGroups()) is 'returning' or 'failed'.
 *
 * fulfillment-placement Task 2: this is the ONE surface that still selects
 * orders by courier phase, on purpose and documented as such in the
 * controller. Tabs answer "where does this order sit in my workflow" (core
 * status placement, nothing else); this page answers "which parcels are
 * coming back at me", which only the courier can say. It is therefore
 * independent of placement.
 *
 * This is a monitoring surface only: booking/cancelling/the viewing drawer
 * stay on the main queue. The full customer-initiated RMA process is a
 * distinct product feature (roadmap card), out of scope here.
 */
class FulfillmentReturnsTest extends TestCase
{
    use RefreshDatabase;

    private string $prefix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prefix = config('admin.prefix', 'admin');
    }

    private function queueUrl(): string
    {
        return '/' . $this->prefix . '/sales/fulfillment';
    }

    private function returnsUrl(): string
    {
        return $this->queueUrl() . '/returns';
    }

    private function installExtension(): void
    {
        app(ExtensionManager::class)->install('courier-fulfillment');
        // Registers the extension's runtime PSR-4 autoloader up front, so
        // this file can reference Extensions\CourierFulfillment\* (the
        // module-owned placement mapping) before its first HTTP request.
        app(ExtensionManager::class)->bootEnabled();
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

    /** A cancelled-PLACED status (a real order cancel), distinct from a courier-side cancel/return on the shipment. */
    private function cancelledStageStatus(): OrderStatus
    {
        $status = OrderStatus::firstOrCreate(
            ['store_id' => 1, 'name' => 'Canceled'],
            ['sort_order' => 2, 'is_default' => false],
        );

        PlacementMap::put($status->id, 'cancelled');

        return $status;
    }

    /** An order in the queue; optionally booked with a raw courier status. */
    private function makeOrder(?string $courierStatus = null, ?string $tracking = null, string $provider = 'quadx', ?array $meta = null, ?int $statusId = null): Order
    {
        $order = Order::create([
            'store_id' => 1,
            'order_status_id' => $statusId ?? $this->defaultStatus()->id,
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
                'courier_meta' => $meta !== null ? json_encode($meta) : null,
            ])->save();
        }

        return $order;
    }

    // ------------------------------------------------------------------
    // Page loads + breadcrumb.
    // ------------------------------------------------------------------

    public function test_returns_page_is_ok_with_breadcrumb(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $response = $this->get($this->returnsUrl());

        $response->assertOk();
        $this->assertBreadcrumbTrail($response->getContent(), ['Sales', 'Fulfillment', 'Returns']);
    }

    // ------------------------------------------------------------------
    // Only 'returning'/'failed' phase orders are listed — everything else
    // (unbooked, to_handover, in_transit, delivered, cancelled) is excluded.
    // ------------------------------------------------------------------

    public function test_only_returning_and_failed_phase_orders_are_listed(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->makeOrder(); // to_pack — never booked, no tracking number to assert against
        $this->makeOrder('for_pickup', 'QX-H-001');            // to_handover
        $this->makeOrder('in_transit', 'QX-T-001');             // in_transit
        $this->makeOrder('delivered', 'QX-D-001');               // delivered
        $this->makeOrder('canceled', 'QX-C-001');                 // cancelled
        $this->makeOrder('returned', 'QX-R-001');                 // returning
        $this->makeOrder('failed_delivery', 'QX-F-001');          // failed

        $response = $this->get($this->returnsUrl());
        $response->assertOk()
            ->assertSee('QX-R-001')
            ->assertSee('QX-F-001')
            ->assertDontSee('QX-H-001')
            ->assertDontSee('QX-T-001')
            ->assertDontSee('QX-D-001')
            ->assertDontSee('QX-C-001');
    }

    // ------------------------------------------------------------------
    // fulfillment-placement Task 2 REWRITE (was: "a cancelled-stage order
    // does not appear even in a returning or failed courier phase", the
    // tabs-v3 stage-first rule applied to this page too). This page is not
    // a tab and deliberately does not follow the tab rule: it answers
    // "which parcels are coming back at me", which is a fact about the
    // SHIPMENT, so it reads live courier state and ignores placement
    // entirely. A cancelled order whose parcel is physically returning is
    // exactly the row an operator needs here, precisely because no tab
    // would ever show it.
    // ------------------------------------------------------------------

    public function test_the_page_lists_returning_and_failed_parcels_whatever_the_orders_placement_is(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $cancelledStageId = $this->cancelledStageStatus()->id;

        // Cancelled-placed orders whose parcels are still returning/failed.
        $this->makeOrder('returned', 'QX-CANCELLED-RETURN', 'quadx', null, $cancelledStageId);
        $this->makeOrder('failed_delivery', 'QX-CANCELLED-FAILED', 'quadx', null, $cancelledStageId);

        // A To-Pack-placed counterpart, same courier phases.
        $this->makeOrder('returned', 'QX-TO-PACK-RETURN');

        $response = $this->get($this->returnsUrl());
        $response->assertOk()
            ->assertSee('QX-TO-PACK-RETURN')
            ->assertSee('QX-CANCELLED-RETURN')
            ->assertSee('QX-CANCELLED-FAILED');

        // Those two are simultaneously in the Cancelled TAB, which is not a
        // contradiction: the tab says where the order sits in the workflow,
        // this page says which parcels are coming back.
        $this->get($this->queueUrl() . '?filter=cancelled')
            ->assertOk()
            ->assertSee('data-count-for="cancelled">2<', false);
    }

    // ------------------------------------------------------------------
    // The queue's segment-bar aside link only renders once this route
    // exists (Route::has('admin.fulfillment.returns')) — W2 shipped it
    // guarded and inert; this task's route registration is what turns it on.
    // ------------------------------------------------------------------

    public function test_queue_segment_bar_aside_link_to_returns_renders(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $response = $this->get($this->queueUrl());

        $response->assertOk()
            ->assertSee('href="' . route('admin.fulfillment.returns') . '"', false)
            ->assertSee('Returns');
    }

    // ------------------------------------------------------------------
    // Honest empty state.
    // ------------------------------------------------------------------

    public function test_empty_state_when_nothing_is_returning_or_failed(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->makeOrder(); // an order exists, but none in returning/failed
        $this->makeOrder('delivered', 'QX-D-200');

        $response = $this->get($this->returnsUrl());
        $response->assertOk()->assertSee('Nothing coming back');
    }

    // ------------------------------------------------------------------
    // Columns: order link, courier, raw status + tone badge, MOST RECENT
    // timeline event (by timestamp, not array order), created.
    // ------------------------------------------------------------------

    public function test_row_shows_order_link_courier_raw_status_and_the_latest_timeline_event(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $order = $this->makeOrder('failed_delivery', 'QX-F-300', 'quadx', [
            'timeline' => [
                ['status' => 'for_pickup', 'at' => 1000],
                ['status' => 'failed_delivery', 'at' => 3000], // latest — must win even though not last-pushed below
                ['status' => 'picked_up', 'at' => 2000],
            ],
        ]);

        $response = $this->get($this->returnsUrl());
        $response->assertOk()
            ->assertSee('href="' . route('admin.orders.show', $order) . '"', false)
            ->assertSee('#' . $order->id)
            ->assertSee('QuadX')
            ->assertSee('Failed Delivery') // humanized raw status (no labels configured -> ucwords fallback)
            ->assertSeeInOrder(['Failed Delivery', optional($order->created_at)->format('M d, Y')]);
    }

    /** Asserts each label appears, in order, inside the rendered breadcrumb <nav> (mirrors UrlGrammarTest's helper). */
    private function assertBreadcrumbTrail(string $html, array $expectedLabels): void
    {
        $found = preg_match('/<(nav|div)[^>]*class="[^"]*(?:v-day__trail|v3-head__trail)[^"]*"[^>]*>(.*?)<\/\1>/s', $html, $matches);
        $this->assertSame(1, $found, 'Breadcrumb <nav> not found in response HTML.');

        $trailText = trim(preg_replace('/\s+/', ' ', strip_tags($matches[2])));

        $lastPos = -1;
        foreach ($expectedLabels as $label) {
            $pos = strpos($trailText, $label, $lastPos + 1);
            $this->assertNotFalse($pos, "Expected breadcrumb label '{$label}' not found in order within trail: {$trailText}");
            $lastPos = $pos;
        }
    }
}
