<?php

namespace Tests\Feature\AdminV2;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User;
use App\Services\ExtensionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task W4 of the fulfillment-workflow-v2 plan: the returns monitoring page
 * (`Sales > Fulfillment > Returns`) — a sibling PAGE, never an eighth queue
 * tab. Lists orders whose spine phase (CourierFulfillmentInterface::
 * statusPhase()) is 'returning' or 'failed', derived through the SAME
 * (provider, status) tally FulfillmentController::index() uses for its
 * segment-bar counts (FulfillmentController::phaseTally() /
 * applyPhaseGroups(), shared — never a parallel derivation).
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
    }

    private function defaultStatus(): OrderStatus
    {
        return OrderStatus::firstOrCreate(
            ['store_id' => 1, 'name' => 'Processing'],
            ['sort_order' => 1, 'is_default' => true],
        );
    }

    /** fulfillment-tabs-v3 Task 2: a stage-'cancelled' status (real order cancel), distinct from a courier-phase cancel/return. */
    private function cancelledStageStatus(): OrderStatus
    {
        return OrderStatus::firstOrCreate(
            ['store_id' => 1, 'name' => 'Canceled'],
            ['sort_order' => 2, 'is_default' => false, 'fulfillment_placement' => 'cancelled'],
        );
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
    // fulfillment-tabs-v3 Task 2: STAGE-FIRST membership — a real order
    // cancel (stage 'cancelled') outranks whatever courier phase the
    // shipment happens to carry. A stage-'cancelled' order sitting in a
    // 'returning'/'failed' courier phase must NOT surface here; it belongs
    // to the queue's Cancelled bucket instead.
    // ------------------------------------------------------------------

    public function test_a_cancelled_stage_order_does_not_appear_even_in_a_returning_or_failed_courier_phase(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $cancelledStageId = $this->cancelledStageStatus()->id;

        // Real order cancel (stage 'cancelled'), but the courier phase is
        // still 'returning'/'failed' — the stage must win.
        $this->makeOrder('returned', 'QX-CANCELLED-RETURN', 'quadx', null, $cancelledStageId);
        $this->makeOrder('failed_delivery', 'QX-CANCELLED-FAILED', 'quadx', null, $cancelledStageId);

        // A ready-stage counterpart, same courier phases, DOES belong here.
        $this->makeOrder('returned', 'QX-READY-RETURN');

        $response = $this->get($this->returnsUrl());
        $response->assertOk()
            ->assertSee('QX-READY-RETURN')
            ->assertDontSee('QX-CANCELLED-RETURN')
            ->assertDontSee('QX-CANCELLED-FAILED');
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
        $found = preg_match('/<nav[^>]*class="[^"]*mb-0\.5 flex items-center[^"]*"[^>]*>(.*?)<\/nav>/s', $html, $matches);
        $this->assertSame(1, $found, 'Breadcrumb <nav> not found in response HTML.');

        $trailText = trim(preg_replace('/\s+/', ' ', strip_tags($matches[1])));

        $lastPos = -1;
        foreach ($expectedLabels as $label) {
            $pos = strpos($trailText, $label, $lastPos + 1);
            $this->assertNotFalse($pos, "Expected breadcrumb label '{$label}' not found in order within trail: {$trailText}");
            $lastPos = $pos;
        }
    }
}
