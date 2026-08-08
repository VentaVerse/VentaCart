<?php

namespace Tests\Feature\AdminV2;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\User;
use App\Services\ExtensionManager;
use Extensions\CourierFulfillment\FulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * fulfillment-placement Task 2: MEMBERSHIP BY CORE STATUS PLACEMENT.
 *
 * One rule, no exceptions: an order appears in the tab its core order
 * status is placed in (order_statuses.fulfillment_placement). Courier state
 * is not an input to membership at all - it reaches the tabs only the way it
 * would with no fulfillment module installed, by the per-courier status map
 * moving the order's CORE status, whose placement then names the tab.
 *
 * Actions are hardcoded per tab (spec section 3):
 *
 *   Unpaid       Cancel order
 *   To Pack      Book, Cancel order
 *   To Handover  Print waybill, Tracking, Cancel booking (while cancelable)
 *   In Transit   Tracking
 *   Delivered    Tracking
 *   Failed       Tracking
 *   Cancelled    none
 *
 * with two subtractive gates: a courier action renders only when the row
 * actually carries courier data, and no action renders that the server
 * would refuse.
 */
class FulfillmentPlacementMembershipTest extends TestCase
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

    private function installExtension(): void
    {
        app(ExtensionManager::class)->install('courier-fulfillment');
        // Registers the extension's runtime PSR-4 autoloader (and its
        // couriers) up front, so this file can reference
        // Extensions\CourierFulfillment\* before its first HTTP request.
        app(ExtensionManager::class)->bootEnabled();
    }

    /**
     * The order-number anchor exactly as the queue's band renders it. Bare
     * '#12' would also match '#123', which silently turns an assertDontSee()
     * into a lie the moment ids share a prefix.
     */
    private function rowMarker(int $id): string
    {
        return '>#' . $id . '</a>';
    }

    private function enableQuadxStaging(): void
    {
        Setting::create(['group' => 'courier', 'key' => 'quadx_enabled', 'value' => '1']);
        Setting::create(['group' => 'courier', 'key' => 'quadx_mode', 'value' => 'staging']);
        Setting::create(['group' => 'courier', 'key' => 'quadx_staging_api_key', 'value' => Crypt::encryptString('test-api-key')]);
        Setting::create(['group' => 'courier', 'key' => 'quadx_staging_secret_key', 'value' => Crypt::encryptString('test-secret-key')]);
    }

    private function statusPlaced(string $placement, ?string $name = null): OrderStatus
    {
        return OrderStatus::firstOrCreate(
            ['store_id' => 1, 'name' => $name ?? ('Status ' . $placement)],
            ['sort_order' => 1, 'fulfillment_placement' => $placement],
        );
    }

    /** An order on a given status; optionally carrying courier data. */
    private function makeOrder(OrderStatus $status, ?string $tracking = null, ?string $courierStatus = null, string $provider = 'quadx'): Order
    {
        $order = Order::create([
            'store_id' => 1,
            'order_status_id' => $status->id,
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

        if ($tracking !== null || $courierStatus !== null) {
            $order->forceFill([
                'courier_provider' => $provider,
                'courier_tracking_number' => $tracking,
                'courier_status' => $courierStatus,
            ])->save();
        }

        return $order;
    }

    /** The queue URL for a placement, since To Pack/To Handover live under the To Ship heading. */
    private function tabUrl(string $placement): string
    {
        return in_array($placement, ['to_pack', 'to_handover'], true)
            ? $this->queueUrl() . '?filter=to_ship&sub=' . $placement
            : $this->queueUrl() . '?filter=' . $placement;
    }

    // ------------------------------------------------------------------
    // The matrix: every placement, with and without courier data, lands in
    // exactly its own tab and nowhere else.
    // ------------------------------------------------------------------

    public function test_every_placement_lands_in_exactly_its_own_tab_without_courier_data(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $orders = [];
        foreach (FulfillmentService::PLACEMENTS as $placement) {
            $orders[$placement] = $this->makeOrder($this->statusPlaced($placement));
        }

        foreach (FulfillmentService::PLACEMENTS as $placement) {
            $this->assertSame($placement, FulfillmentService::tabBucket($orders[$placement]->fresh()));

            $response = $this->get($this->tabUrl($placement));
            $response->assertOk();
            foreach (FulfillmentService::PLACEMENTS as $other) {
                $marker = $this->rowMarker($orders[$other]->id);
                $placement === $other
                    ? $response->assertSee($marker, false)
                    : $response->assertDontSee($marker, false);
            }
        }
    }

    /**
     * The same matrix with a LIVE QuadX booking on every row. The courier
     * status is identical on all seven, so any leak of courier state into
     * membership would collapse them into one tab: they must still sit in
     * seven different tabs, one per placement.
     */
    public function test_every_placement_lands_in_exactly_its_own_tab_with_courier_data(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $orders = [];
        foreach (FulfillmentService::PLACEMENTS as $placement) {
            $orders[$placement] = $this->makeOrder(
                $this->statusPlaced($placement),
                'QX-' . strtoupper($placement),
                'in_transit',
            );
        }

        foreach (FulfillmentService::PLACEMENTS as $placement) {
            $this->assertSame($placement, FulfillmentService::tabBucket($orders[$placement]->fresh()));

            $response = $this->get($this->tabUrl($placement));
            $response->assertOk();
            foreach (FulfillmentService::PLACEMENTS as $other) {
                $tracking = 'QX-' . strtoupper($other);
                $placement === $other
                    ? $response->assertSee($tracking)
                    : $response->assertDontSee($tracking);
            }
        }
    }

    /**
     * The counts follow the same single rule: one order per placement means
     * one per tab, and To Ship (a heading, not a placement) is exactly its
     * two sub-tabs added together.
     */
    public function test_tab_counts_are_one_per_placement_and_to_ship_is_the_sum_of_its_two_sub_tabs(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        foreach (FulfillmentService::PLACEMENTS as $placement) {
            $this->makeOrder($this->statusPlaced($placement), 'QX-C-' . strtoupper($placement), 'in_transit');
        }
        // A second To Pack order, so To Ship's sum is not symmetrical by luck.
        $this->makeOrder($this->statusPlaced('to_pack'));

        $response = $this->get($this->queueUrl());
        $response->assertOk()
            ->assertSee('data-count-for="unpaid">1<', false)
            ->assertSee('data-count-for="to_pack">2<', false)
            ->assertSee('data-count-for="to_handover">1<', false)
            ->assertSee('data-count-for="to_ship">3<', false)
            ->assertSee('data-count-for="in_transit">1<', false)
            ->assertSee('data-count-for="delivered">1<', false)
            ->assertSee('data-count-for="failed">1<', false)
            ->assertSee('data-count-for="cancelled">1<', false);
    }

    /**
     * The motivating defect, at scale: OpenCart-era orders whose core status
     * is "Delivered" carry no courier data at all (they predate the courier
     * integration). Under the tabs-v3 rule every one of them showed up in
     * To Pack, because "no tracking number" meant "never booked, go pack
     * it". Placement membership puts them where their status says.
     */
    public function test_delivered_placed_legacy_orders_with_no_courier_data_fill_delivered_and_never_to_pack(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $delivered = $this->statusPlaced('delivered', 'Delivered');
        $legacy = [];
        for ($i = 0; $i < 12; $i++) {
            $legacy[] = $this->makeOrder($delivered);
        }
        $genuineWork = $this->makeOrder($this->statusPlaced('to_pack', 'Processing'));

        $this->get($this->queueUrl())
            ->assertOk()
            ->assertSee('data-count-for="delivered">12<', false)
            ->assertSee('data-count-for="to_pack">1<', false)
            ->assertSee($this->rowMarker($genuineWork->id), false)
            ->assertDontSee($this->rowMarker($legacy[0]->id), false);

        $deliveredTab = $this->get($this->queueUrl() . '?filter=delivered&per_page=50');
        $deliveredTab->assertOk()
            ->assertSee($this->rowMarker($legacy[0]->id), false)
            ->assertDontSee($this->rowMarker($genuineWork->id), false);

        // No courier ever touched these rows, so no courier state is
        // invented for them: the badge reads their core order status, and
        // no courier action renders.
        $deliveredTab->assertSee('Delivered')
            ->assertDontSee('openDrawer(JSON.parse(', false)
            ->assertDontSee('Print waybill')
            ->assertDontSee('openBooking(JSON.parse(', false);
    }

    /** A status the owner creates and places themselves shows up in that tab, with that tab's actions. */
    public function test_a_custom_status_appears_in_whichever_tab_the_owner_places_it_under(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        $repacking = OrderStatus::create([
            'store_id' => 1,
            'name' => 'Repacking',
            'sort_order' => 9,
            'fulfillment_placement' => 'to_pack',
        ]);
        $order = $this->makeOrder($repacking);

        $this->get($this->queueUrl() . '?filter=to_ship&sub=to_pack')
            ->assertOk()
            ->assertSee($this->rowMarker($order->id), false)
            ->assertSee('data-count-for="to_pack">1<', false)
            ->assertSee('openBooking(JSON.parse(', false);

        // Re-placing the SAME status moves every order on it, with no order
        // row touched: this is the whole point of the placement setting.
        $repacking->update(['fulfillment_placement' => 'to_handover']);

        $this->get($this->queueUrl() . '?filter=to_ship&sub=to_handover')
            ->assertOk()
            ->assertSee($this->rowMarker($order->id), false)
            ->assertSee('data-count-for="to_handover">1<', false);

        $this->get($this->queueUrl() . '?filter=to_ship&sub=to_pack')
            ->assertOk()
            ->assertDontSee($this->rowMarker($order->id), false);
    }

    /**
     * The courier's ONLY route into the tabs: its status map moves the core
     * order status, and that status's placement decides the tab. Here the
     * order's courier state never changes at all - only its core status
     * does - and the row moves. The mirror image (courier state changing
     * with the status held still) is covered by the with-courier-data matrix
     * above, where seven identical courier states sit in seven tabs.
     */
    public function test_a_core_status_change_is_what_moves_a_row_between_tabs(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $toHandover = $this->statusPlaced('to_handover', 'Order Being Shipped');
        $inTransit = $this->statusPlaced('in_transit', 'Shipped');

        $order = $this->makeOrder($toHandover, 'QX-MAP-001', 'for_pickup');

        $this->get($this->queueUrl() . '?filter=to_ship&sub=to_handover')
            ->assertOk()
            ->assertSee('QX-MAP-001');

        // What a courier status map does: change the CORE status. Nothing
        // else about the shipment changes.
        $order->forceFill(['order_status_id' => $inTransit->id])->save();

        $this->get($this->queueUrl() . '?filter=in_transit')
            ->assertOk()
            ->assertSee('QX-MAP-001');
        $this->get($this->queueUrl() . '?filter=to_ship&sub=to_handover')
            ->assertOk()
            ->assertDontSee('QX-MAP-001');
    }

    // ------------------------------------------------------------------
    // Actions, per tab.
    // ------------------------------------------------------------------

    public function test_unpaid_row_offers_cancel_order_only(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        $order = $this->makeOrder($this->statusPlaced('unpaid', 'Unpaid'));

        $response = $this->get($this->queueUrl() . '?filter=unpaid');
        $response->assertOk()
            ->assertSee($this->rowMarker($order->id), false)
            ->assertSee("cancel-order-{$order->id}", false)
            ->assertDontSee('openBooking(JSON.parse(', false)
            ->assertDontSee('openDrawer(JSON.parse(', false)
            ->assertDontSee('Print waybill')
            ->assertDontSee("cancel-fulfillment-{$order->id}", false);
    }

    public function test_to_pack_row_offers_book_and_cancel_order_only(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        $order = $this->makeOrder($this->statusPlaced('to_pack', 'Processing'));

        $response = $this->get($this->queueUrl() . '?filter=to_ship&sub=to_pack');
        $response->assertOk()
            ->assertSee($this->rowMarker($order->id), false)
            ->assertSee('openBooking(JSON.parse(', false)
            ->assertSee("cancel-order-{$order->id}", false)
            ->assertDontSee('Print waybill')
            ->assertDontSee('openDrawer(JSON.parse(', false)
            ->assertDontSee("cancel-fulfillment-{$order->id}", false);
    }

    public function test_to_handover_row_offers_print_waybill_tracking_and_cancel_booking(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        // 'for_pickup' is in QuadXFulfillment::cancelableStatuses().
        $order = $this->makeOrder($this->statusPlaced('to_handover', 'Order Being Shipped'), 'QX-H-001', 'for_pickup');

        $response = $this->get($this->queueUrl() . '?filter=to_ship&sub=to_handover');
        $response->assertOk()
            ->assertSee('QX-H-001')
            ->assertSee('Print waybill')
            ->assertSee('openDrawer(JSON.parse(', false)
            ->assertSee("cancel-fulfillment-{$order->id}", false)
            ->assertSee('Cancel booking')
            // Never a booking affordance, never the order cancel.
            ->assertDontSee('openBooking(JSON.parse(', false)
            ->assertDontSee("cancel-order-{$order->id}", false);
    }

    public function test_to_handover_row_without_courier_data_shows_no_courier_actions(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        $order = $this->makeOrder($this->statusPlaced('to_handover', 'Order Being Shipped'));

        $response = $this->get($this->queueUrl() . '?filter=to_ship&sub=to_handover');
        $response->assertOk()
            ->assertSee($this->rowMarker($order->id), false)
            ->assertDontSee('Print waybill')
            ->assertDontSee('openDrawer(JSON.parse(', false)
            ->assertDontSee("cancel-fulfillment-{$order->id}", false)
            ->assertDontSee('openBooking(JSON.parse(', false)
            // No parcel to hand over, so it contributes to no courier chip.
            ->assertDontSee('Parcels waiting for handover');
    }

    public function test_in_transit_and_delivered_rows_offer_tracking_only(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        $transit = $this->makeOrder($this->statusPlaced('in_transit', 'Shipped'), 'QX-T-001', 'in_transit');
        $delivered = $this->makeOrder($this->statusPlaced('delivered', 'Delivered'), 'QX-D-001', 'delivered');

        foreach ([['in_transit', $transit, 'QX-T-001'], ['delivered', $delivered, 'QX-D-001']] as [$tab, $order, $tracking]) {
            $response = $this->get($this->queueUrl() . '?filter=' . $tab);
            $response->assertOk()
                ->assertSee($tracking)
                ->assertSee('openDrawer(JSON.parse(', false)
                ->assertDontSee('Print waybill')
                ->assertDontSee('openBooking(JSON.parse(', false)
                ->assertDontSee("cancel-order-{$order->id}", false)
                ->assertDontSee("cancel-fulfillment-{$order->id}", false);
        }
    }

    /**
     * Failed rows keep Tracking. Booking is a To Pack action server-side
     * (FulfillmentService::bookingBlockedReason()), so instead of a Rebook
     * button the server would refuse, the row names the one path forward.
     */
    public function test_failed_row_offers_tracking_and_the_book_again_hint_never_a_book_button(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        $order = $this->makeOrder($this->statusPlaced('failed', 'Failed Delivery'), 'QX-F-001', 'failed_delivery');

        $response = $this->get($this->queueUrl() . '?filter=failed');
        $response->assertOk()
            ->assertSee('QX-F-001')
            ->assertSee('openDrawer(JSON.parse(', false)
            ->assertSee("Change this order's status to one placed under To Pack to book it again.", false)
            ->assertDontSee('openBooking(JSON.parse(', false)
            ->assertDontSee('Print waybill')
            ->assertDontSee("cancel-order-{$order->id}", false);
    }

    public function test_cancelled_row_offers_no_actions_at_all(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        $order = $this->makeOrder($this->statusPlaced('cancelled', 'Canceled'), 'QX-C-001', 'canceled');

        $response = $this->get($this->queueUrl() . '?filter=cancelled');
        $response->assertOk()
            ->assertSee('QX-C-001')
            ->assertDontSee('openBooking(JSON.parse(', false)
            ->assertDontSee('openDrawer(JSON.parse(', false)
            ->assertDontSee('Print waybill')
            ->assertDontSee("cancel-order-{$order->id}", false)
            ->assertDontSee("cancel-fulfillment-{$order->id}", false);
    }

    // ------------------------------------------------------------------
    // The booking gate: To Pack placement only, server-side.
    // ------------------------------------------------------------------

    public function test_book_route_refuses_every_placement_except_to_pack_with_no_outbound_call(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        Http::fake(); // any outbound call here would itself be a bug

        foreach (['unpaid', 'to_handover', 'in_transit', 'delivered', 'failed', 'cancelled'] as $placement) {
            $order = $this->makeOrder($this->statusPlaced($placement));

            $response = $this->post($this->queueUrl() . '/' . $order->id . '/book', [
                'courier' => 'quadx',
                'service' => 'next_day',
                'parcel' => 'box',
                'shipping_payment' => 'net_off',
            ]);

            $response->assertRedirect();
            $response->assertSessionHas('error');
            $this->assertStringContainsString(
                'Set its status to one placed under To Pack before booking.',
                session('error'),
                "placement={$placement}",
            );

            $order->refresh();
            $this->assertNull($order->courier_tracking_number, "placement={$placement}");
            $this->assertNull($order->courier_provider, "placement={$placement}");
        }

        Http::assertNothingSent();
    }

    /** The motivating case, spelled out: a Delivered-placed legacy order can never be booked by accident. */
    public function test_a_delivered_placed_order_cannot_be_booked(): void
    {
        $this->installExtension();
        $this->enableQuadxStaging();
        Http::fake();

        $order = $this->makeOrder($this->statusPlaced('delivered', 'Delivered'));

        $res = app(FulfillmentService::class)->book($order, [
            'courier' => 'quadx',
            'service' => 'next_day',
            'parcel' => 'box',
            'shipping_payment' => 'net_off',
        ]);

        $this->assertFalse($res['success']);
        $this->assertSame('placement_blocked', $res['code']);
        $this->assertStringContainsString('Delivered', $res['error']);
        Http::assertNothingSent();
    }

    public function test_estimate_route_refuses_a_non_to_pack_placement(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();
        Http::fake();

        $order = $this->makeOrder($this->statusPlaced('delivered', 'Delivered'));

        $response = $this->postJson($this->queueUrl() . '/' . $order->id . '/estimate', ['courier' => 'quadx']);

        $response->assertOk()->assertJson(['ok' => false]);
        $this->assertStringContainsString('Set its status to one placed under To Pack before booking.', $response->json('message'));
        Http::assertNothingSent();
    }

    /**
     * The gate blocks genuinely NEW bookings only: the idempotent
     * already-booked branch still answers FIRST, so an ERP retry for an
     * order whose status moved after the booking (which is the normal case
     * now that booking sets a To Handover status) keeps reading
     * already_booked rather than a refusal for a shipment that exists.
     */
    public function test_erp_retry_still_answers_already_booked_on_a_non_to_pack_placement(): void
    {
        $this->installExtension();
        $this->enableQuadxStaging();
        Http::fake();

        $order = $this->makeOrder($this->statusPlaced('to_handover', 'Order Being Shipped'), 'QX-RETRY-001', 'for_pickup');

        $res = app(FulfillmentService::class)->book($order, [
            'courier' => 'quadx',
            'service' => 'next_day',
            'parcel' => 'box',
            'shipping_payment' => 'net_off',
        ]);

        $this->assertTrue($res['success']);
        $this->assertTrue($res['already_booked']);
        $this->assertSame('QX-RETRY-001', $res['tracking_number']);
        $this->assertArrayNotHasKey('code', $res);
        Http::assertNothingSent();
    }
}
