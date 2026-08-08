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
 * ORDER actions are hardcoded per tab (owner rulings, 2026-08-09):
 *
 *   Book            To Pack only
 *   Cancel order    Unpaid and To Pack
 *   Cancel booking  To Handover only
 *
 * COURIER actions key on the row's courier data instead, in ANY tab
 * (owner ruling, fix round): the "every tab except Unpaid" rule was
 * justified by "since it is not booked yet", so the data is what governs.
 *
 *   Tracking        whenever the row carries courier data
 *   Print waybill   whenever the booking is LIVE (isActivelyBooked())
 *
 * over all of which one rule applies, and only ever subtracts: no action
 * renders that the server would refuse.
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
            ->assertSee('data-count-for="shipping">1<', false)
            ->assertSee('data-count-for="failed">1<', false)
            ->assertSee('data-count-for="lost_damaged">1<', false)
            ->assertSee('data-count-for="delivered">1<', false)
            ->assertSee('data-count-for="cancelled">1<', false)
            // All is a VIEW, not a placement: one order per placement plus
            // the extra To Pack one.
            ->assertSee('data-count-for="all">9<', false);
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
        $shipping = $this->statusPlaced('shipping', 'Shipped');

        $order = $this->makeOrder($toHandover, 'QX-MAP-001', 'for_pickup');

        $this->get($this->queueUrl() . '?filter=to_ship&sub=to_handover')
            ->assertOk()
            ->assertSee('QX-MAP-001');

        // What a courier status map does: change the CORE status. Nothing
        // else about the shipment changes.
        $order->forceFill(['order_status_id' => $shipping->id])->save();

        $this->get($this->queueUrl() . '?filter=shipping')
            ->assertOk()
            ->assertSee('QX-MAP-001');
        $this->get($this->queueUrl() . '?filter=to_ship&sub=to_handover')
            ->assertOk()
            ->assertDontSee('QX-MAP-001');
    }

    // ------------------------------------------------------------------
    // Actions, per tab.
    // ------------------------------------------------------------------

    /**
     * An ordinary Unpaid order has no parcel, so the courier actions have
     * nothing to act on and Cancel order is genuinely all it offers. This is
     * what the "not booked yet" justification behind the original rule was
     * protecting, and keying on the data preserves it exactly.
     */
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

    /**
     * The dead end this fix round closes (found live on order 29850, which
     * holds a real SPX AWB while its core status is placed under Unpaid):
     * an Unpaid-placed order carrying a LIVE booking rendered an EMPTY
     * actions cell, because the tab subtracted both courier actions and the
     * live booking subtracted Cancel order. The parcel now carries its own
     * two buttons wherever its core status sits, while Book stays a To Pack
     * action and the unbooked Unpaid row beside it is unchanged.
     */
    public function test_unpaid_row_with_a_live_booking_offers_its_parcels_own_buttons(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        $unpaid = $this->statusPlaced('unpaid', 'Unpaid');
        // 'for_pickup' is a live QuadX booking (isActivelyBooked() true).
        $booked = $this->makeOrder($unpaid, 'QX-U-001', 'for_pickup');
        // Same tab, no parcel: the ordinary unpaid order, unchanged.
        $plain = $this->makeOrder($unpaid);

        $response = $this->get($this->queueUrl() . '?filter=unpaid');
        $response->assertOk()
            ->assertSee('QX-U-001')
            // Tracking and Print waybill, both keyed on this row's own data.
            ->assertSee('openDrawer(JSON.parse(', false)
            ->assertSee('Print waybill')
            ->assertSee('/sales/fulfillment/' . $booked->id . '/label', false)
            // Booking is still a To Pack action, refused server-side here.
            ->assertDontSee('openBooking(JSON.parse(', false)
            // Cancel booking stays To Handover only.
            ->assertDontSee("cancel-fulfillment-{$booked->id}", false)
            // Cancel order is refused while a booking is live, so the row
            // does not offer it either (cancel the booking first).
            ->assertDontSee("cancel-order-{$booked->id}", false);

        // The parcel-less row on the same page keeps exactly its one action
        // and gains no courier affordance from its neighbour.
        $response->assertSee("cancel-order-{$plain->id}", false)
            ->assertDontSee('/sales/fulfillment/' . $plain->id . '/label', false);
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

    /**
     * Owner ruling, 2026-08-09: a real parcel always carries its own
     * buttons, so Tracking and Print waybill render wherever the row's
     * courier data says they can. None of these tabs offers Book, Cancel
     * order or Cancel booking.
     */
    public function test_shipping_delivered_and_lost_damaged_rows_offer_tracking_and_waybill(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        $shipping = $this->makeOrder($this->statusPlaced('shipping', 'Shipped'), 'QX-T-001', 'in_transit');
        $delivered = $this->makeOrder($this->statusPlaced('delivered', 'Delivered'), 'QX-D-001', 'delivered');
        $lost = $this->makeOrder($this->statusPlaced('lost_damaged', 'Lost in Transit'), 'QX-L-001', 'in_transit');

        foreach ([['shipping', $shipping, 'QX-T-001'], ['delivered', $delivered, 'QX-D-001'], ['lost_damaged', $lost, 'QX-L-001']] as [$tab, $order, $tracking]) {
            $response = $this->get($this->queueUrl() . '?filter=' . $tab);
            $response->assertOk()
                ->assertSee($tracking)
                ->assertSee('openDrawer(JSON.parse(', false)
                ->assertSee('Print waybill')
                ->assertDontSee('openBooking(JSON.parse(', false)
                ->assertDontSee("cancel-order-{$order->id}", false)
                ->assertDontSee("cancel-fulfillment-{$order->id}", false);
        }
    }

    /**
     * Fix round: Lost & Damaged joins Failed and Cancelled in carrying the
     * book-again hint, because reshipping after a claim is the normal
     * outcome there and that tab offers no Book of its own.
     *
     * Asserted on the row hint's own class, never on its wording: the same
     * sentence also lives in the drawer's Alpine template, which is present
     * in the page markup for every row, so a text assertion here would pass
     * whether the row rendered the hint or not.
     */
    public function test_lost_damaged_row_carries_the_book_again_hint_delivered_does_not(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        // A settled claim: the booking is cancelled, so nothing blocks a
        // reship and the row's one path forward is the status change.
        $this->makeOrder($this->statusPlaced('lost_damaged', 'Lost in Transit'), 'QX-L-002', 'canceled');
        $this->makeOrder($this->statusPlaced('delivered', 'Delivered'), 'QX-D-002', 'canceled');

        $this->get($this->queueUrl() . '?filter=lost_damaged')
            ->assertOk()
            ->assertSee('co-actions__hint', false);

        // Delivered is settled, not a rebook candidate: no hint there.
        $this->get($this->queueUrl() . '?filter=delivered')
            ->assertOk()
            ->assertDontSee('co-actions__hint', false);
    }

    /**
     * Failed rows keep Tracking. Booking is a To Pack action server-side
     * (FulfillmentService::bookingBlockedReason()), so instead of a Rebook
     * button the server would refuse, the row names the one path forward -
     * but only once the booking is no longer live, since a live parcel has
     * to have its booking cancelled before anything can be reshipped.
     *
     * The hint is asserted on its own class, not its wording: the same
     * sentence lives in the drawer's Alpine template too, which is in the
     * page markup regardless of any row.
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
            // The parcel exists and is still live, so its own two buttons
            // come with it - and no hint, because cancelling the booking
            // comes first.
            ->assertSee('Print waybill')
            ->assertDontSee('co-actions__hint', false)
            ->assertDontSee('openBooking(JSON.parse(', false)
            ->assertDontSee("cancel-order-{$order->id}", false);

        // Booking cancelled: nothing blocks a reship now, so the row names
        // the status change that enables it.
        $order->forceFill(['courier_status' => 'canceled'])->save();

        $this->get($this->queueUrl() . '?filter=failed')
            ->assertOk()
            ->assertSee('co-actions__hint', false)
            ->assertDontSee('openBooking(JSON.parse(', false);
    }

    /**
     * A Cancelled-placed row offers no WORKFLOW action (no Book, no Cancel
     * order, no Cancel booking) - but it still carries its parcel's own
     * buttons, per the 2026-08-09 ruling. Which of the two it gets depends
     * only on the parcel: Tracking whenever courier state exists, Print
     * waybill only while the booking is live, because a cancelled shipment
     * has no printable AWB and the courier refuses to produce one.
     */
    public function test_cancelled_row_offers_no_workflow_actions_only_its_parcel_buttons(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        $cancelledBooking = $this->makeOrder($this->statusPlaced('cancelled', 'Canceled'), 'QX-C-001', 'canceled');
        // The dead end this ruling closes: a LIVE parcel whose core status
        // was moved to a cancelled-placed one. Before the ruling this row
        // had no buttons at all.
        $liveParcel = $this->makeOrder($this->statusPlaced('cancelled', 'Canceled'), 'QX-C-002', 'for_pickup');

        $response = $this->get($this->queueUrl() . '?filter=cancelled');
        $response->assertOk()
            ->assertSee('QX-C-001')
            ->assertSee('QX-C-002')
            ->assertSee('openDrawer(JSON.parse(', false)
            ->assertSee('Print waybill')
            // The live parcel is the one with a waybill; the cancelled
            // booking is not.
            ->assertSee('/sales/fulfillment/' . $liveParcel->id . '/label', false)
            ->assertDontSee('/sales/fulfillment/' . $cancelledBooking->id . '/label', false)
            ->assertDontSee('openBooking(JSON.parse(', false);

        foreach ([$cancelledBooking, $liveParcel] as $order) {
            $response->assertDontSee("cancel-order-{$order->id}", false)
                ->assertDontSee("cancel-fulfillment-{$order->id}", false);
        }
    }

    /** With no parcel on it, a Cancelled row genuinely offers nothing. */
    public function test_cancelled_row_without_courier_data_offers_nothing(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        $order = $this->makeOrder($this->statusPlaced('cancelled', 'Canceled'));

        $response = $this->get($this->queueUrl() . '?filter=cancelled');
        $response->assertOk()
            ->assertSee($this->rowMarker($order->id), false)
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

        foreach (['unpaid', 'to_handover', 'shipping', 'failed', 'lost_damaged', 'delivered', 'cancelled'] as $placement) {
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
