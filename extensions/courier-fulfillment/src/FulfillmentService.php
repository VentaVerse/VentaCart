<?php

namespace Extensions\CourierFulfillment;

use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\ShippingCourier;
use App\Services\OrderNotificationService;
use App\Services\OrderStatusChanger;
use App\Support\NotifyChoice;
use Extensions\CourierFulfillment\Models\PickupAddress;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Extensions\CourierFulfillment\Contracts\CourierFulfillmentInterface;
use Extensions\CourierFulfillment\Contracts\ProvidesPublicTrackingUrl;
use Extensions\CourierFulfillment\Contracts\ReportsShippingCost;

/**
 * Shared booking core, used by BOTH the admin Fulfillment page and the ERP API
 * endpoint — one implementation, no duplication.
 *
 * Idempotent while a booking is ACTIVE: if the order already carries a
 * tracking number and its courier_status isn't canceled, book() returns that
 * existing booking instead of creating a second shipment (the ERP may retry;
 * a courier order must never be double-booked — we saw 6 duplicate staging
 * bookings for one order from exactly this hazard).
 *
 * A canceled booking is NOT terminal: QuadX accepts repeat bookings for the
 * same reference_id, so cancel -> book again is allowed and creates a fresh
 * shipment, with the old tracking number preserved in courier_meta and order
 * history for an audit trail.
 */
class FulfillmentService
{
    /**
     * Both spellings couriers/admins use for a canceled booking. Defined once
     * here — the controller and view reference this const rather than each
     * keeping their own copy.
     */
    public const CANCELED_STATUSES = ['canceled', 'cancelled'];

    /**
     * The internal bridging statuses an ASYNC courier's create flow writes
     * before a tracking number exists ('creating' — create still in flight;
     * 'creation_failed' — create settled but permanently failed, see
     * CourierFulfillmentInterface::resolvePendingCreation()). Both must
     * resolve through the courier's own statusPhase() rather than the
     * "no tracking number => never booked => to_pack" short-circuit —
     * defined once here for phaseFor() and for the queue view, which reads
     * both to render an honest "Creating…" / "Booking failed" row.
     */
    public const ASYNC_CREATE_STATUSES = ['creating', 'creation_failed'];

    /**
     * The eight placement values a status can be mapped to, listed in tab
     * order. Two tabs are NOT in this list, for opposite reasons: To Ship is
     * a heading over to_pack + to_handover, and All is a VIEW over every
     * order. Neither is ever a placement a status can be put into. This list
     * IS the tab vocabulary: membership is nothing more than reading which of
     * these the order's core status is mapped to (tabBucket()).
     */
    public const PLACEMENTS = [
        'unpaid', 'to_pack', 'to_handover', 'shipping',
        'failed', 'lost_damaged', 'delivered', 'cancelled',
    ];

    /**
     * The answer for a status carrying NO mapping, and for an order with no
     * status row at all. Not a placement and never storable as one: it means
     * "this order belongs to no tab", which is a real state now that the
     * mapping is module-owned and seeded by nothing (owner ruling,
     * 2026-08-09).
     *
     * An unplaced order still appears in the All tab, because All is a VIEW
     * over every order rather than a placement - nothing is ever hidden by
     * being unplaced, it simply has no worklist of its own until the merchant
     * places its status.
     */
    public const UNPLACED = 'unplaced';

    /**
     * Booking is offered, and permitted server-side, for exactly one
     * placement: To Pack. Everything else (unpaid, already handed over, in
     * transit, delivered, failed, cancelled) has to be moved back to a
     * To-Pack-placed status first - the same chain that would exist with no
     * fulfillment module at all. This is also what stops a Delivered-placed
     * legacy order from being booked by accident.
     */
    public const BOOKABLE_PLACEMENT = 'to_pack';

    /**
     * The placement a successful booking moves the order INTO, and the one a
     * cancelled booking moves it back OUT of (fulfillment-placement Task 3).
     *
     * Booking is OUR action: no courier status announces "this seller has
     * booked a shipment", so nothing else in the chain can move the order
     * out of To Pack. Since Task 2 made tab membership nothing but the core
     * status's placement, a booking that did not move the status would leave
     * a booked order sitting in To Pack forever, where the tab offers Book
     * (refused while actively booked) and Cancel order (refused likewise) -
     * an actionless row for the one state that most needs the waybill and
     * the cancel. That is the hole these two constants close.
     */
    public const BOOKED_PLACEMENT = 'to_handover';
    public const UNBOOKED_PLACEMENT = 'to_pack';

    /** Module settings (group 'courier') naming each target status. */
    public const BOOKED_STATUS_SETTING = 'booked_order_status_id';
    public const UNBOOKED_STATUS_SETTING = 'unbooked_order_status_id';

    /**
     * The status name preferred when `booked_order_status_id` is unset: the
     * name the OpenCart-era stores already use for exactly this step, so a
     * migrated store lands on the right status without configuring anything.
     *
     * It is NOT seeded (owner ruling, 2026-08-09: the fulfillment module
     * seeds no order statuses, because a merchant configures this module
     * deliberately at setup). A fresh install therefore has no
     * To-Handover-placed status at all until the merchant places one, which
     * is what the settings hub's setup notice says out loud.
     */
    public const DEFAULT_BOOKED_STATUS_NAME = 'Order Being Shipped';

    /** Human tab name per placement, for operator-facing refusals. */
    public const TAB_LABELS = [
        'unpaid'       => 'Unpaid',
        'to_pack'      => 'To Ship, To Pack',
        'to_handover'  => 'To Ship, To Handover',
        'shipping'     => 'Shipping',
        'failed'       => 'Failed',
        'lost_damaged' => 'Lost and Damaged',
        'delivered'    => 'Delivered',
        'cancelled'    => 'Cancelled',
    ];

    /**
     * The queue URL that lands on a given placement's worklist.
     *
     * The tab vocabulary is this module's, not core's: To Pack and To
     * Handover are SUB-tabs under the To Ship heading, which is why their
     * URLs carry two parameters and every other placement carries one.
     * Nothing outside the module should have to know that, so anything
     * linking into the queue (the day sheet's work lines) asks here instead
     * of assembling query strings from a placement name.
     *
     * @param  string  $placement  one of self::PLACEMENTS
     */
    public static function queueUrl(string $placement): string
    {
        $params = in_array($placement, ['to_pack', 'to_handover'], true)
            ? ['filter' => 'to_ship', 'sub' => $placement]
            : ['filter' => $placement];

        return route('admin.fulfillment.index', $params);
    }

    /**
     * True when the order has a tracking number AND its booking isn't
     * cancelled. "Cancelled" is decided PHASE-first (final-review fix,
     * Finding 1): the resolved courier's own statusPhase() spine bucket —
     * so a courier-vocabulary cancel status (SPX's '7001', stamped by its
     * poll/webhook right after an admin cancel) reads as inactive exactly
     * like the literal 'canceled' the admin cancel action writes. Without
     * this, a 7001 order was "actively booked" forever: no Rebook, no
     * Cancel (7001 isn't cancelable), and a Print-waybill offer on a
     * shipment SPX can no longer print. The literal spellings remain as
     * the fallback for a booking whose provider is no longer registered
     * (or predates the spine and returns no 'phase' key) — QuadX's own
     * cancelled statuses ARE those literals and bucket to the cancelled
     * phase anyway, so its behavior is unchanged on either path.
     */
    /**
     * Is this parcel leaving by a courier we do NOT integrate with?
     *
     * The whole of the manual mechanism, stated as one predicate: core's
     * pre-existing `orders.shipping_courier_id` is set (the admin named a
     * courier from `shipping_couriers`) and `courier_provider` is null (no
     * integration owns this shipment).
     *
     * The null provider is load-bearing rather than incidental. Both loops in
     * courier:poll-status select `where('courier_provider', $courier->getKey())`
     * — a null provider matches no registered key, so a manual shipment is
     * invisible to the poller BY CONSTRUCTION, with no exclusion list to keep
     * in step. It also keeps `courier_provider` honestly meaning "a courier
     * this app talks to", which is what every other reader of that column
     * (CourierManager::get(), trackingLinks(), printLabel(), the webhook
     * ingestor) already assumes.
     */
    public static function isManuallyShipped(Order $order): bool
    {
        return $order->shipping_courier_id !== null && $order->courier_provider === null;
    }

    public static function isActivelyBooked(Order $order): bool
    {
        if (!$order->courier_tracking_number) {
            return false;
        }

        $status = (string) $order->courier_status;
        if (in_array($status, self::CANCELED_STATUSES, true)) {
            return false;
        }

        $courier = CourierManager::get((string) $order->courier_provider);
        if ($courier && ($courier->statusPhase($status)['phase'] ?? null) === 'cancelled') {
            return false;
        }

        return true;
    }

    /**
     * Courier-agnostic entry point onto the common 8-phase spine (see
     * CourierFulfillmentInterface::statusPhase()'s docblock and
     * Support\PhaseTone for the tone each bucket carries).
     *
     * This describes a SHIPMENT, never a tab: it feeds the row's courier
     * badge, the drawer's stepper/timeline, and the returns monitoring page.
     * Tab membership reads the order's core status placement instead
     * (tabBucket()) and never calls this.
     *
     * `to_pack` is resolved HERE, not by any courier: an order with no
     * tracking number has never been booked, which isn't a courier status at
     * all. Once a tracking number exists, the bucket comes from the
     * resolved courier's own statusPhase() — an order booked with a
     * provider that's since been unregistered, or with a courier whose
     * statusPhase() predates the spine and carries no 'phase' key, degrades
     * to `to_handover` rather than throwing: a booked-but-unclassifiable
     * order should still read as "still needs the seller's attention",
     * never crash the queue that's trying to list it.
     */
    public static function phaseFor(Order $order): string
    {
        // Task T4 fix: a courier whose createShipment() is genuinely async
        // (SPX) can be mid-flight with courier_status='creating' and NO
        // tracking number yet (see CourierFulfillmentInterface::
        // resolvePendingCreation()'s docblock). That state was falling into
        // the "never booked" bucket below purely because the tracking
        // number hasn't landed yet, hiding it from the queue's Booking tab
        // entirely (T2's report flagged this short-circuit explicitly).
        // 'creating' must resolve through the courier's own statusPhase()
        // (which maps it to phase 'booking') same as any other booked
        // status — every other tracking-number-less order (including one
        // that was never booked at all) is still 'to_pack' as before.
        //
        // Final-review fix (Finding 2): same treatment for
        // 'creation_failed' — a permanently failed async create is ALSO
        // tracking-number-less, and short-circuiting it to to_pack made
        // statusPhase('creation_failed') => 'failed' dead code: the order
        // silently blended into To Pack with no badge, tab, or rendered
        // creation_error anywhere. Both bridging statuses now route
        // through the courier's own spine mapping (ASYNC_CREATE_STATUSES).
        if (!$order->courier_tracking_number
            && !in_array((string) $order->courier_status, self::ASYNC_CREATE_STATUSES, true)) {
            return 'to_pack';
        }

        return self::phaseForParts($order->courier_provider, $order->courier_status);
    }

    /**
     * Same resolution as phaseFor() for an already-BOOKED order, but from the
     * raw column values instead of an Order instance — lets the queue
     * controller bucket a GROUP BY (courier_provider, courier_status) tally
     * into phases without hydrating one model per order. Callers own the
     * "no tracking number => to_pack" short-circuit; this only answers for
     * rows that have a booking.
     */
    public static function phaseForParts(?string $provider, ?string $status): string
    {
        $courier = CourierManager::get((string) $provider);
        if (!$courier) {
            return 'to_handover';
        }

        return $courier->statusPhase($status)['phase'] ?? 'to_handover';
    }

    /**
     * TAB MEMBERSHIP, the whole rule (fulfillment-placement spec, section 2):
     * an order appears in the tab its CORE ORDER STATUS is placed in. Full
     * stop. No precedence table, no courier-state arbitration, no per-courier
     * overrides.
     *
     * The courier's influence on tabs is indirect and honest: a courier
     * status maps to a core order status (the per-courier status map, which
     * already existed), and that status's placement names the tab. Exactly
     * the chain that would exist with no fulfillment module at all. A booked
     * order sits under To Handover because BOOKING SET ITS STATUS to one
     * placed there (fulfillment-placement Task 3), never because this method
     * peeked at courier_tracking_number.
     *
     * This REPLACES the tabs-v blend (stage-vs-phase arbitration, the
     * cancelled-arm union, the 'booking' collapse) - deleted, not extended.
     * phaseFor()/statusPhase() stay for what they are genuinely for (the
     * row badge, the drawer's 4-step stepper, timeline rendering, and
     * cancelable/terminal decisions on a SHIPMENT); neither is consulted
     * here or by any other membership path.
     *
     * The mapping is MODULE data (PlacementMap, owner ruling 2026-08-09), not
     * a column on the core order_statuses table: uninstalling this extension
     * takes the whole vocabulary with it and leaves core untouched.
     *
     * An order whose status carries no mapping (the state of every status on
     * a fresh install, since the module seeds nothing) answers UNPLACED: it
     * belongs to no tab, appears in All - which constrains nothing - and is
     * counted only there.
     */
    public static function tabBucket(Order $order): string
    {
        $placement = PlacementMap::placementFor($order->order_status_id ? (int) $order->order_status_id : null);

        return ($placement !== null && in_array($placement, self::PLACEMENTS, true))
            ? $placement
            : self::UNPLACED;
    }

    /**
     * The booking gate, one rule shared by every caller (admin queue Book,
     * the fee estimate, and the ERP endpoint): only a To-Pack-placed order
     * may be booked. Returns null when booking is permitted, otherwise the
     * operator-facing reason - which always names the path forward, since
     * changing the order's status is the ONLY way through it.
     *
     * Used by the view too, so a Book button is never rendered on a row this
     * would refuse.
     */
    public static function bookingBlockedReason(Order $order): ?string
    {
        $placement = self::tabBucket($order);
        if ($placement === self::BOOKABLE_PLACEMENT) {
            return null;
        }

        // An unplaced status has no tab to name, so the refusal says what is
        // actually true rather than inventing one - the path forward is the
        // same sentence either way.
        if ($placement === self::UNPLACED) {
            return "Order #{$order->id} sits on a status with no fulfillment placement. Set its status to one placed under To Pack before booking.";
        }

        $label = self::TAB_LABELS[$placement] ?? $placement;

        return "Order #{$order->id} sits under {$label}. Set its status to one placed under To Pack before booking.";
    }

    /**
     * The core status a successful booking moves the order to: the one whose
     * placement (to_handover) carries the row from To Pack to To Handover.
     * Null ONLY when the store has no To-Handover-placed status at all, which
     * book() refuses on rather than creating a shipment it cannot represent.
     *
     * Resolution order, most explicit first:
     *
     *  1. the configured `booked_order_status_id`, but only while that status
     *     is still placed under To Handover (an admin who later re-places it
     *     must not silently keep booking orders into the wrong tab);
     *  2. the status named "Order Being Shipped" when it is placed there;
     *  3. the first To-Handover-placed status by sort order, then name.
     *
     * Steps 2 and 3 are why "unset" is a working configuration rather than a
     * broken one (fulfillment-placement Task 3, review IMPORTANT 2): the
     * setting exists to OVERRIDE this answer, never to enable it.
     */
    public static function bookedStatus(): ?OrderStatus
    {
        return self::resolveStatusSetting(
            self::BOOKED_STATUS_SETTING,
            self::BOOKED_PLACEMENT,
            self::DEFAULT_BOOKED_STATUS_NAME,
        );
    }

    /**
     * The core status a cancelled booking returns the order to.
     *
     * $preferredId is the status the order held BEFORE it was booked (see
     * book(), which records it under courier_meta.pre_booking_order_status_id):
     * honoured when it still exists and is still placed under To Pack, so a
     * store with several To Pack statuses ("Awaiting Packing", "Custom Build
     * Queue") gets a genuine RETURN rather than a reset to whichever status
     * happens to sort first. Otherwise the configured
     * `unbooked_order_status_id`, otherwise the first To-Pack-placed status.
     */
    public static function unbookedStatus(?int $preferredId = null): ?OrderStatus
    {
        if ($preferredId && PlacementMap::placementFor((int) $preferredId) === self::UNBOOKED_PLACEMENT) {
            $preferred = OrderStatus::find($preferredId);
            if ($preferred) {
                return $preferred;
            }
        }

        return self::resolveStatusSetting(self::UNBOOKED_STATUS_SETTING, self::UNBOOKED_PLACEMENT, null);
    }

    /**
     * Shared resolution for both settings above: configured id (scoped to the
     * required placement), then an optional preferred NAME, then the first
     * status carrying that placement. Every arm is scoped to $placement, so
     * the answer either carries it or is null.
     */
    private static function resolveStatusSetting(string $key, string $placement, ?string $preferredName): ?OrderStatus
    {
        // The ids carrying this placement, straight off the module's own
        // mapping (PlacementMap) - core order_statuses has no placement
        // column to filter on any more. Empty means the store has placed
        // nothing there, and every arm below then answers null.
        $ids = PlacementMap::statusIdsFor($placement);
        if ($ids === []) {
            return null;
        }

        // The setting is the only source (owner ruling 2026-08-30). There
        // used to be two guesses behind it, a status NAME and then the first
        // placed status by sort order; a name can be renamed or deleted and a
        // guess that silently changes is worse than a refusal that names the
        // select to fill. An existing store's effective guesses were pinned
        // into these settings once, at upgrade (the 2026_08_30_130000
        // migration), so nothing moved for it.
        $configured = Setting::where('group', 'courier')->where('key', $key)->value('value');
        if ($configured && in_array((int) $configured, $ids, true)) {
            return OrderStatus::find((int) $configured);
        }

        return null;
    }

    /**
     * Applies the booked status to an order whose shipment now genuinely
     * exists - called from BOTH paths a tracking number can arrive by: the
     * synchronous book() below, and courier:poll-status's resolution of an
     * async creation (PollCourierStatus::resolvePendingCreations()).
     *
     * Deliberately NOT called for a booking that is still in flight
     * (courier_status='creating', no tracking number): nothing has been
     * booked yet, the row honestly reads "Creating" in To Pack, and a create
     * that later fails permanently must stay bookable there rather than sit
     * in To Handover with no shipment to hand over.
     *
     * Idempotent: an order already on the booked status is left alone, so a
     * repeat poll (or a courier status map that reached the same status
     * first) can never write a second identical history row.
     *
     * $notify defaults to NotifyChoice::silent(): both automatic callers
     * (book() below and PollCourierStatus's async resolution) pass nothing,
     * because the customer is told about a shipment by the status map's own
     * notify flag or the order page, never by this internal move. bookManual()
     * below is the one caller that passes its own choice: the packer's
     * ticks, or NotifyChoice::follow() when the caller stated nothing.
     *
     * $comment replaces only the FIRST sentence (what happened); the status
     * move is always appended by this method, so every history row still ends
     * the same way. It exists for the manual path (bookManual() below), whose
     * first sentence is honestly "Shipment recorded" plus the operator's own
     * note, not "Courier booking confirmed" — no courier was contacted.
     * Omitted by both integrated callers, whose rows are unchanged.
     */
    public static function applyBookedStatus(Order $order, ?string $courierName = null, ?string $tracking = null, ?string $comment = null, ?OrderStatus $chosen = null, ?NotifyChoice $notify = null): ?OrderStatus
    {
        // $chosen is the manual path's operator-named status (owner
        // directive, 2026-08-12). Absent everywhere else, so an integrated
        // booking still moves to the module's own To-Handover-placed status
        // and nothing about those two callers changes.
        $status = $chosen ?? self::bookedStatus();
        if (!$status || (int) $order->order_status_id === (int) $status->id) {
            return null;
        }

        // $customerComment is what a human (or the customer, if they ever
        // read raw history) wrote about what actually happened; the audit
        // line below is for the record, not for their inbox, so it is
        // appended only to the history row's own comment.
        $customerComment = $comment ?? ('Courier booking confirmed'
            . ($courierName ? ' with ' . $courierName : '')
            . ($tracking ? ' (' . $tracking . ')' : '')
            . '.');

        // A period between the two sentences whatever $customerComment
        // happens to end with (already a period, or a tracking anchor's
        // closing tag) - a bare space left them reading as one run-on
        // clause.
        $trimmed = rtrim($customerComment);
        $historyComment = ($trimmed !== '' && !str_ends_with($trimmed, '.')) ? $trimmed . '.' : $trimmed;
        $historyComment .= ' Order status set to ' . $status->name . ' by fulfillment.';

        $notify ??= NotifyChoice::silent();

        // History row + status update + stock adjustment either all land or
        // none do, same as every other status change in the app.
        DB::transaction(function () use ($order, $status, $historyComment, $customerComment, $notify) {
            OrderStatusChanger::apply($order, $status->id, $historyComment, $notify, Auth::id(), $customerComment);
        });

        return $status;
    }

    /**
     * Is this order paid via a COD-type method (rider collects the total)?
     * Uses the admin-configured mapping (courier.cod_payment_methods); when the
     * store hasn't configured one, falls back to the name heuristic.
     */
    public static function isCodOrder(Order $order): bool
    {
        $raw = Setting::where('group', 'courier')->where('key', 'cod_payment_methods')->value('value');
        $configured = $raw !== null;
        $list = $configured ? array_values(array_filter((array) json_decode($raw, true), fn ($v) => $v !== '' && $v !== '__none__')) : [];

        if ($configured) {
            return in_array((string) $order->payment_method, $list, true);
        }

        $pm = strtolower((string) $order->payment_method);
        return str_contains($pm, 'cod') || str_contains($pm, 'cash on delivery');
    }

    /**
     * Cancel the courier BOOKING (never the order itself). The one shared
     * implementation of the confirmed-cancel state transition — snapshot
     * into courier_meta.booking_history, reset the live courier fields,
     * move the order back to its pre-booking / To-Pack status — extracted
     * from the admin controller so the ERP endpoint runs the exact same
     * flow. See FulfillmentController::cancel()'s docblock for the full
     * fulfillment-tabs-v Task 4 reasoning; the semantics are unchanged.
     *
     * @return array{success:bool, error?:string, message?:string, tracking?:string, order_status?:?string}
     */
    public static function cancelBooking(Order $order, ?int $actorUserId = null): array
    {
        $courier = CourierManager::get((string) $order->courier_provider);
        if (!$courier || !$order->courier_tracking_number) {
            return ['success' => false, 'error' => 'No active courier booking on this order.'];
        }

        $result = $courier->cancelShipment($order->courier_tracking_number);
        if (!$result['success']) {
            $why = trim((string) ($result['error'] ?? '')) ?: 'the courier gave no reason';

            // A refused cancel changes nothing else on the order, so without
            // this the only trace is a toast the operator may not have been
            // looking at. The attempt belongs on the record: it is the
            // evidence that someone tried, and it is what the next person
            // reads when they wonder why the parcel is still booked.
            OrderHistory::create([
                'order_id'        => $order->id,
                'order_status_id' => $order->order_status_id,
                'comment'         => 'Courier cancel REFUSED for ' . $order->courier_tracking_number
                    . ' (' . $courier->getName() . '): ' . $why
                    . ' The booking is still live and the parcel is still with the courier.',
                'notify_customer' => false,
            ]);

            return [
                'success' => false,
                'error'   => 'Could not cancel ' . $order->courier_tracking_number . ' with '
                    . $courier->getName() . '. ' . $why
                    . ' The booking is still live, so cancel it in the courier dashboard if it can still be cancelled there. This attempt is recorded in the order history.',
            ];
        }

        // The courier confirmed — only NOW does the reset happen.
        $tracking = $order->courier_tracking_number;
        $meta = $order->courier_meta ? (json_decode($order->courier_meta, true) ?: []) : [];
        $history = (isset($meta['booking_history']) && is_array($meta['booking_history']))
            ? $meta['booking_history']
            : [];
        $preBookingStatusId = isset($meta['pre_booking_order_status_id'])
            ? (int) $meta['pre_booking_order_status_id']
            : null;
        $history[] = [
            'courier'         => (string) $order->courier_provider,
            'tracking_number' => $tracking,
            'status'          => $order->courier_status,
            'timeline'        => $meta['timeline'] ?? [],
            'meta'            => \Illuminate\Support\Arr::except($meta, ['booking_history']),
            'previous_order_status_id' => $preBookingStatusId,
            'admin_cancelled' => true,
            'cancelled_at'    => now()->toIso8601String(),
        ];

        $fill = [
            'courier_provider'        => null,
            'courier_status'          => null,
            'courier_tracking_number' => null,
            // booking_history is the ONE key that persists through the reset.
            'courier_meta'            => json_encode(['booking_history' => $history]),
            // The cost described the shipment just cancelled. Left behind it
            // reports a charge for a parcel nobody is carrying, and on an
            // order that is never rebooked it would sit in the margin for
            // good. The figure is preserved in booking_history above, which
            // is where a cancelled shipment's facts belong.
            'shipping_cost_actual'    => null,
        ];
        // Core snapshot columns: cleared IF they still point at the shipment
        // just cancelled — a customer must never be shown a voided AWB.
        if ((string) $order->tracking_number === (string) $tracking) {
            $fill['tracking_number'] = null;
            $fill['tracking_link']   = null;
            // The name was stamped for this booking; a manual courier row
            // (shipping_courier_id) would still own it, so only clear when
            // no row does.
            if ($order->shipping_courier_id === null) {
                $fill['shipping_courier_name'] = null;
            }
        }
        $order->forceFill($fill)->save();

        $target = static::unbookedStatus($preBookingStatusId);
        $comment = 'Courier booking cancelled: ' . $tracking . ' (' . $courier->getName() . ').';

        if ($target && (int) $order->order_status_id !== (int) $target->id) {
            DB::transaction(function () use ($order, $target, $comment, $actorUserId) {
                // Undoing a booking, back to where it was before it existed.
                // The customer was never told about this booking through this
                // internal move (see applyBookedStatus()), so reverting it
                // stays just as quiet.
                OrderStatusChanger::apply(
                    $order,
                    $target->id,
                    $comment . ' Order status set to ' . $target->name . ', back under To Ship, To Pack.',
                    NotifyChoice::silent(),
                    $actorUserId,
                );
            });

            return [
                'success' => true,
                'tracking' => $tracking,
                'order_status' => $target->name,
                'message' => "Cancelled booking {$tracking} for order #{$order->id}. The order is back in To Ship, To Pack as {$target->name}.",
            ];
        }

        $currentName = $order->status?->name ?? 'its current status';
        OrderHistory::create([
            'order_id'        => $order->id,
            'order_status_id' => $order->order_status_id,
            'comment'         => $comment . ' Order status unchanged (' . $currentName . ').',
            'notify_customer' => false,
        ]);

        return [
            'success' => true,
            'tracking' => $tracking,
            'order_status' => $order->status?->name,
            'message' => $target
                ? "Cancelled booking {$tracking} for order #{$order->id}. The order stays on {$currentName}, in To Ship, To Pack."
                : "Cancelled booking {$tracking} for order #{$order->id}. No order status is placed under To Pack, so the order stays on {$currentName}. Place one under Extensions, Fulfillment, Status Mapping.",
        ];
    }

    /**
     * Live tracking pull + local refresh, extracted from the admin
     * controller (same reasoning as cancelBooking() above): updates
     * courier_status, timeline/links meta, the core tracking_link
     * snapshot, and runs StatusMapper — then reports what came back.
     *
     * @return array{success:bool, error?:string, status?:?string, timeline?:array, links?:array}
     */
    /**
     * The link the shopper gets for an integrated shipment, resolved in one
     * place for booking, the poll and a manual refresh alike.
     *
     * The courier's own per-shipment URL first (delivery, then pickup), then
     * its public tracker keyed by the number when it runs one, then whatever
     * core already holds. Never null out a stored link: a poll cycle that
     * returns nothing is a quiet API, not a vanished parcel.
     */
    public static function resolveTrackingLink(CourierFulfillmentInterface $courier, ?array $links, ?string $tracking, ?string $keep, array $courierMeta = []): ?string
    {
        // Owner ruling 2026-09-03: a courier that runs its own public tracker
        // wins, ahead of whatever its API hands back per shipment. QuadX
        // returns links on its logistics platform (fareye.co) rather than its
        // consumer brand, and those were reaching customers and the queue.
        // The shopper gets the courier's public tracker or nothing.
        if ($tracking !== null && $tracking !== '' && $courier instanceof ProvidesPublicTrackingUrl) {
            $public = $courier->publicTrackingUrl($tracking, $courierMeta);
            if ($public) {
                return $public;
            }
        }

        // Couriers with no public tracker of their own still rely on the
        // per-shipment URLs their API returns.
        foreach ([$links['delivery'] ?? null, $links['pickup'] ?? null] as $u) {
            if (is_string($u) && str_starts_with($u, 'http')) {
                return $u;
            }
        }

        return $keep;
    }

    public static function refreshTracking(Order $order): array
    {
        $courier = CourierManager::get((string) $order->courier_provider);
        if (!$courier || !$order->courier_tracking_number) {
            return ['success' => false, 'error' => 'No courier booking to track.'];
        }

        $result = $courier->getTracking($order->courier_tracking_number);
        if (!$result['success']) {
            return ['success' => false, 'error' => 'Tracking failed: ' . ($result['error'] ?? 'unknown error')];
        }

        $fill = [];
        if (!empty($result['status'])) {
            $fill['courier_status'] = $result['status'];
        }

        $links = $result['links'] ?? null;
        $timeline = $result['timeline'] ?? null;
        if (!empty($links) || !empty($timeline)) {
            $meta = $order->courier_meta ? (json_decode($order->courier_meta, true) ?: []) : [];
            if (!empty($links)) {
                $meta['tracking_links'] = $links;
            }
            if (!empty($timeline)) {
                $meta['timeline'] = $timeline;
            }
            $fill['courier_meta'] = json_encode($meta);
        }

        // Raw column, NOT the accessor: the accessor live-derives and would
        // trigger relation queries / freeze derived URLs.
        $trackingLink = self::resolveTrackingLink($courier, is_array($links) ? $links : null, $order->courier_tracking_number, $order->getRawOriginal('tracking_link'), $order->courier_meta ? (json_decode($order->courier_meta, true) ?: []) : []);
        if ($trackingLink !== $order->getRawOriginal('tracking_link')) {
            $fill['tracking_link'] = $trackingLink;
        }

        // Bookings made before the name was stamped at booking time left the
        // shopper a bare number with no courier beside it. Heal it here,
        // where the courier is already known, unless a manual courier row
        // owns the name.
        if ($order->shipping_courier_name === null && $order->shipping_courier_id === null) {
            $fill['shipping_courier_name'] = $courier->getName();
        }

        if (!empty($fill)) {
            // courier_* columns aren't in core Order::$fillable — must forceFill.
            $order->forceFill($fill)->save();
        }
        if (!empty($result['status'])) {
            StatusMapper::apply($order, $result['status']);
        }

        return [
            'success' => true,
            'status' => $result['status'] ?? null,
            'timeline' => $result['timeline'] ?? [],
            'links' => $result['links'] ?? [],
        ];
    }

    /**
     * @param array $opts courier, service, parcel, buyer_payment, shipping_payment
     * @return array{success:bool, already_booked?:bool, courier?:string, tracking_number?:?string, status?:?string, error?:string, code?:string}
     */
    public function book(Order $order, array $opts): array
    {
        // Async-create-in-progress guard (T2 review, CRITICAL 2). An async
        // courier (SPX) can return success from createShipment() with NO
        // tracking number yet — courier_status='creating', batch_no stashed
        // in courier_meta (see CourierFulfillmentInterface::
        // resolvePendingCreation()'s docblock for the full contract). That
        // state is invisible to isActivelyBooked() below (it requires a
        // tracking number), so without this guard a second "Book" click
        // would submit a SECOND batch_create_order for the same order,
        // silently orphaning the first — a real SPX shipment with no
        // tracking number on file, so it's neither trackable nor
        // cancellable from Venta. Checked FIRST, before even resolving the
        // requested courier, so it protects ANY courier whose
        // createShipment() can return this pending state (not just SPX) and
        // blocks the attempt regardless of which courier the admin picks
        // the second time — an order mid-flight with one courier must never
        // be booked out from under it by a different one.
        if ((string) $order->courier_status === 'creating') {
            $inProgressWith = CourierManager::get((string) $order->courier_provider)?->getName() ?: (string) $order->courier_provider;
            return [
                'success' => false,
                'code'    => 'creation_in_progress',
                'error'   => "Order #{$order->id} is already being booked with {$inProgressWith}. Please wait for it to finish (usually a few seconds) and try again.",
            ];
        }

        // Manual-shipment guard. An order whose parcel has already been
        // recorded as leaving by a courier we do not integrate with
        // (isManuallyShipped()) carries a courier name, a tracking number and
        // a tracking link that a real booking would silently overwrite,
        // leaving two shipments' worth of record on one order and no way to
        // tell which one the customer was given. The manual record has to be
        // cleared first, which is one click on the row (clearManual()).
        //
        // Checked here, alongside the other "this order is already committed
        // somewhere" guards, and before the courier is resolved so the answer
        // does not depend on which courier was picked.
        if (self::isManuallyShipped($order)) {
            return [
                'success' => false,
                'code'    => 'manually_shipped',
                'error'   => "Order #{$order->id} is already recorded as shipping with " . self::manualCourierName($order) . '. Clear that shipment first, then book.',
            ];
        }

        $courier = CourierManager::get((string) ($opts['courier'] ?? ''));
        if (!$courier || !$courier->isEnabled()) {
            // Owner directive (2026-08-08, "unconfigured couriers visible
            // with configuration gate"): server-side gate, defense in depth
            // behind step 1's own card-level block. A courier the admin has
            // switched ON (CourierManager::isFlagged()) but never finished
            // configuring gets a message that NAMES it and points at
            // Settings, instead of the raw signing/API exception its own
            // client would otherwise throw (see SpxClient::post()'s
            // "SPX is not configured" RuntimeException) or the generic
            // "not available" message a genuinely unregistered/flag-off
            // courier still gets — the two cases read differently on
            // purpose. 'code' stays 'courier_unavailable' either way (the
            // ERP endpoint's 409-vs-422 branch keys off this exact string —
            // ErpFulfillmentController::book()) so only the message differs.
            $error = ($courier && CourierManager::isFlagged($courier->getKey()))
                ? "{$courier->getName()} is not properly configured. Check its credentials in Settings."
                : 'Selected courier is not available.';

            return ['success' => false, 'code' => 'courier_unavailable', 'error' => $error];
        }

        // Idempotency guard — an ACTIVE booking (tracking number + non-canceled
        // status) => return the existing shipment untouched. A canceled booking
        // falls through and books a fresh shipment.
        if (self::isActivelyBooked($order)) {
            return [
                'success'         => true,
                'already_booked'  => true,
                'courier'         => $order->courier_provider,
                'tracking_number' => $order->courier_tracking_number,
                'status'          => $order->courier_status,
            ];
        }

        // Placement gate (fulfillment-placement spec, section 3): booking is
        // permitted for To-Pack-placed orders only, refused server-side for
        // every other placement - which is what keeps a Delivered-placed
        // legacy order (24,620 of them from the OpenCart era) unbookable,
        // and what makes "Book is a To Pack action" true rather than merely
        // hidden in the UI. Shared by BOTH callers of this method (admin
        // queue + ERP endpoint) - the ERP's 409-vs-422 branch keys off
        // 'courier_unavailable' only, so 'placement_blocked' answers 422
        // there.
        //
        // Ordering is load-bearing (kept verbatim from tabs-v's F3 fix):
        // checked BELOW the two idempotent branches above
        // (creation_in_progress + already_booked), never before them - the
        // gate blocks genuinely NEW bookings only. An ERP retry for an order
        // that is already booked must keep answering already_booked (200)
        // even if the order's status moved after the booking; a blocked
        // answer there would read as a failed booking for a shipment that in
        // fact exists.
        $blocked = self::bookingBlockedReason($order);
        if ($blocked !== null) {
            return [
                'success' => false,
                'code'    => 'placement_blocked',
                'error'   => $blocked,
            ];
        }

        // Destination gate (fulfillment-placement Task 3, review IMPORTANT 2).
        // A booking that cannot move the order's status would leave it in To
        // Pack carrying a live shipment, where the tab's own actions (Book,
        // Cancel order) are both refused for exactly that reason - an
        // actionless row, with no waybill and no way to cancel the parcel
        // from the queue. So the shipment is never created in the first
        // place: refused BEFORE the outbound call, naming the one fix.
        //
        // Reachable on a FRESH install (owner ruling, 2026-08-09: the
        // seeder ships no fulfillment-specific statuses), and on a store
        // that has re-placed or deleted every To-Handover-placed status.
        // bookedStatus() falls back through "Order Being Shipped" to the
        // first To-Handover-placed status, so any one of them is enough.
        // The settings hub carries a matching setup notice so this refusal
        // is never a mystery.
        //
        // Ordering: BELOW the placement gate, whose per-row message is the
        // one an operator can act on, and below both idempotent branches for
        // the same reason those come first.
        //
        // Deliberately NOT part of bookingBlockedReason() (which the queue
        // view calls per row to decide whether to render Book): this is a
        // store-wide configuration fault, not a fact about any one order.
        // Hiding every Book button would leave the admin with no button to
        // click and no explanation; the refusal message IS the explanation,
        // and it costs one refused click rather than a silent dead queue.
        // The fee estimate stays on the placement gate alone for the same
        // reason - quoting a fee creates nothing.
        if (self::bookedStatus() === null) {
            return [
                'success' => false,
                'code'    => 'no_booked_status',
                'error'   => 'No booked order status is selected, so a booked order would have nowhere to go. Choose one under Extensions, Fulfillment, Settings.',
            ];
        }

        // Captured before booking: null on a first-ever booking, the old
        // (canceled) tracking number on a rebook.
        $previousTracking = $order->courier_tracking_number;
        // The status the order sits on right now (a To-Pack-placed one, per
        // the gate above) - recorded in courier_meta so cancelling this
        // booking can return the order to exactly where it came from rather
        // than to a generic fallback (see FulfillmentController::cancel()).
        $preBookingStatusId = (int) $order->order_status_id;
        // fulfillment-tabs-v Task 4: an admin-initiated booking cancel
        // resets the courier fields but persists the audit trail under
        // courier_meta.booking_history (FulfillmentController::cancel()) —
        // carried forward here so a rebook's fresh meta never wipes it.
        $previousMeta = $order->courier_meta ? (json_decode($order->courier_meta, true) ?: []) : [];

        $result = $courier->createShipment($order, $opts);
        if (!($result['success'] ?? false)) {
            return ['success' => false, 'code' => 'booking_failed', 'error' => $result['error'] ?? 'Booking failed.'];
        }

        $meta = $result['meta'] ?? [];
        // Where this parcel actually left from, snapshotted (not a foreign
        // key) so the drawer and the booking history still read correctly
        // after the address is renamed, re-defaulted or deleted. Resolved
        // through the SAME rule both adapters used to build the payload -
        // PickupAddress::resolveFor($opts) - so the record can never
        // disagree with what was sent.
        $pickup = PickupAddress::resolveFor($opts);
        if ($pickup) {
            $meta['pickup_address'] = [
                'id'      => $pickup->id,
                'label'   => $pickup->label,
                'summary' => $pickup->summary(),
            ];
        }
        $meta['previous_tracking_number'] = $previousTracking;
        $meta['pre_booking_order_status_id'] = $preBookingStatusId;
        if (isset($previousMeta['booking_history']) && is_array($previousMeta['booking_history'])) {
            $meta['booking_history'] = $previousMeta['booking_history'];
        }

        // What the shipment cost the STORE, from a courier willing to say.
        // Read from the meta this booking just produced, never from the
        // column, which still holds the previous booking at this point.
        //
        // Recorded beside the total and never inside it. The customer's
        // shipping charge (orders.shipping_cost) and the courier's charge to
        // us are different numbers that happen to sit close together, and
        // conflating them is how a margin report starts lying. A courier that
        // reports nothing leaves the column null, which reads as "not known"
        // rather than as free.
        $cost = $courier instanceof ReportsShippingCost
            ? $courier->actualShippingCost(is_array($meta) ? $meta : [])
            : null;

        // forceFill bypasses mass-assignment: courier_* columns are extension-owned
        // and not in core Order::$fillable; courier_meta has no cast so encode it.
        $order->forceFill([
            // Always written, including as null. Only setting it when the
            // courier answered would let a rebooking inherit the PREVIOUS
            // courier's figure: cancel a QuadX booking, rebook with one that
            // reports nothing, and the old 77.00 would stand in for a
            // shipment it has nothing to do with. This column describes the
            // current booking or it describes nothing.
            'shipping_cost_actual'    => $cost,
            'courier_provider'        => $courier->getKey(),
            'courier_tracking_number' => $result['tracking_number'] ?? null,
            'courier_status'          => $result['status'] ?? null,
            'courier_meta'            => json_encode($meta),
            'courier_booked_at'       => now(),
            // Core snapshot field (Order Historical Snapshots): the extension is the
            // only writer of core tracking_number; no tracking_link yet at booking time.
            'tracking_number'         => $result['tracking_number'] ?? null,
            // Never the previous booking's link (it would point at a
            // superseded shipment): the courier's public tracker for THIS
            // number when it has one, else null until the poll brings the
            // per-shipment URL.
            'tracking_link'           => self::resolveTrackingLink($courier, null, $result['tracking_number'] ?? null, null, is_array($meta) ? $meta : []),
            // Who carries it, in the same core snapshot column a manual
            // shipment fills from its courier row. Without it the shopper's
            // account page and every email showed a bare number with no
            // courier beside it for an integrated booking.
            'shipping_courier_name'   => $courier->getName(),
        ])->save();

        if ($previousTracking) {
            OrderHistory::create([
                'order_id'        => $order->id,
                'order_status_id' => $order->order_status_id,
                'comment'         => 'Rebooked with courier: ' . $previousTracking . ' superseded by ' . ($result['tracking_number'] ?? 'unknown'),
                'notify_customer' => false,
            ]);
        }

        // The shipment exists, so the order moves to To Handover. Only when a
        // tracking number actually landed: an async courier can return
        // success with courier_status='creating' and nothing to hand over yet
        // (see applyBookedStatus()'s docblock) - courier:poll-status applies
        // the status when that creation resolves instead.
        if (!empty($result['tracking_number'])) {
            self::applyBookedStatus($order, $courier->getName(), $result['tracking_number']);
        }

        return [
            'success'         => true,
            'already_booked'  => false,
            'courier'         => $courier->getKey(),
            'tracking_number' => $result['tracking_number'] ?? null,
            'status'          => $result['status'] ?? null,
        ];
    }

    /**
     * Record a parcel leaving by a courier this app does not integrate with.
     *
     * WHY THIS IS A BRANCH AND NOT A COURIER. Both integrated couriers refuse
     * to book a delivery to an address they do not serve (QuadX answers 422
     * "Delivery address is not serviceable"; SPX marks such municipalities
     * Support Deliver = 0), so for those destinations the merchant picks a
     * courier nobody integrates with, arranges it by phone, and needs Venta to
     * REMEMBER it. There is no API to call, no waybill to fetch, no status to
     * poll — which is why this is not a CourierFulfillmentInterface
     * implementation: nineteen of that contract's twenty-two methods would be
     * no-ops, and the three that are not (getKey, getName, isEnabled) would
     * put a manual pseudo-courier into the poller's two provider loops, the
     * settings hub's per-courier card list, hasEnabled()'s "is this module
     * usable" answer, and the flagged()/isFlagged() credential machinery,
     * every one of which would then need an exception carved back out.
     *
     * Nothing new is stored. It writes core's own pre-existing manual courier
     * columns — `shipping_courier_id`, `tracking_number`, `tracking_link` —
     * which the order page has always rendered and which 12 orders already
     * use. `courier_provider` is deliberately left null (see
     * isManuallyShipped()).
     *
     * Every gate book() applies is applied here too, in the same order and
     * from the same shared helpers, so "which orders may leave To Pack" has
     * exactly one answer regardless of how the parcel ships.
     *
     * @param array{shipping_courier_id:int, tracking_number?:?string, note?:?string} $opts
     * @return array{success:bool, courier?:string, tracking_number?:?string, error?:string, code?:string}
     */
    public function bookManual(Order $order, array $opts): array
    {
        if ((string) $order->courier_status === 'creating') {
            $inProgressWith = CourierManager::get((string) $order->courier_provider)?->getName() ?: (string) $order->courier_provider;
            return [
                'success' => false,
                'code'    => 'creation_in_progress',
                'error'   => "Order #{$order->id} is already being booked with {$inProgressWith}. Wait for that to finish, cancel it, then record this shipment.",
            ];
        }

        // An integrated booking is a real parcel with a real waybill. Recording
        // a second, manual one over the top would leave the order claiming two
        // shipments — cancel the booking first, which is the same order of
        // operations cancelOrder() already demands.
        if (self::isActivelyBooked($order)) {
            $bookedWith = CourierManager::get((string) $order->courier_provider)?->getName()
                ?: strtoupper((string) $order->courier_provider);
            return [
                'success' => false,
                'code'    => 'already_booked',
                'error'   => "Order #{$order->id} is already booked with {$bookedWith} ({$order->courier_tracking_number}). Cancel that booking first.",
            ];
        }

        if (self::isManuallyShipped($order)) {
            return [
                'success' => false,
                'code'    => 'manually_shipped',
                'error'   => "Order #{$order->id} is already recorded as shipping with " . self::manualCourierName($order) . '. Clear that shipment first to record a different one.',
            ];
        }

        $blocked = self::bookingBlockedReason($order);
        if ($blocked !== null) {
            return ['success' => false, 'code' => 'placement_blocked', 'error' => $blocked];
        }

        // The operator's own answer to "what state is this order in now"
        // (owner directive, 2026-08-12). Absent means the module's own
        // To-Handover-placed status, which is exactly what this path has
        // always applied, so ignoring the field changes nothing.
        $chosenStatus = null;
        if (!empty($opts['order_status_id'])) {
            $chosenStatus = OrderStatus::where('store_id', (int) config('app.store_id', 1))
                ->find((int) $opts['order_status_id']);
            if (!$chosenStatus) {
                return ['success' => false, 'code' => 'status_unavailable', 'error' => 'That order status is not available. Choose one from the list.'];
            }
        }

        // Same destination gate as book(): a recorded shipment that cannot
        // move the order onward would sit in To Pack offering to be recorded
        // again. Refused before anything is written, and only when nobody
        // named a status themselves: an operator who did has answered the
        // very question this gate exists to ask.
        if ($chosenStatus === null && self::bookedStatus() === null) {
            return [
                'success' => false,
                'code'    => 'no_booked_status',
                'error'   => 'No order status is placed under To Handover, so a shipped order would have nowhere to go. Place one under Extensions, Fulfillment, Status Mapping.',
            ];
        }

        // Optional since card VENT-35. With none named this is a plain status
        // update: the order moves, tracking and comment are saved, and no
        // courier is recorded as carrying it. A courier id that was given but
        // is not an active courier is still an error, since that is a wrong
        // answer rather than no answer.
        $courierId = (int) ($opts['shipping_courier_id'] ?? 0);
        $courier = $courierId > 0
            ? ShippingCourier::where('is_active', true)->find($courierId)
            : null;

        if ($courierId > 0 && !$courier) {
            return ['success' => false, 'code' => 'courier_unavailable', 'error' => 'That courier is not available. Choose an active courier under Sales, Couriers.'];
        }

        $tracking = trim((string) ($opts['tracking_number'] ?? '')) ?: null;
        $note     = trim((string) ($opts['comment'] ?? $opts['note'] ?? '')) ?: null;
        // Who decides whether the customer hears about this. A caller that
        // says nothing at all (an ERP call with neither field) gets the
        // store's own answer through the doorway: the status's
        // emails_customer tick and each channel's own list. Fulfillment's
        // NotifyMap is retiring and is no longer read here. The queue form
        // always states both halves (a hidden notify_customer=0 and a
        // hidden channels[] sentinel), so a packer unticking everything
        // has said no rather than nothing.
        $saysEmail = array_key_exists('notify_customer', $opts);
        $saysChannels = array_key_exists('channels', $opts);
        $targetStatusId = ($chosenStatus ?? self::bookedStatus())?->id;

        if (!$saysEmail && !$saysChannels) {
            $choice = NotifyChoice::follow();
        } else {
            // A stated half is the caller's own and stays explicit; the half
            // it left out is answered from the same homes follow() reads.
            $notify = $saysEmail
                ? (bool) $opts['notify_customer']
                : (bool) OrderStatus::whereKey($targetStatusId)->value('emails_customer');

            $channelKeys = $saysChannels
                ? array_values(array_filter((array) $opts['channels'], fn ($key) => is_string($key) && $key !== ''))
                : array_values(array_map(
                    fn ($c) => $c->key(),
                    array_filter(OrderNotificationService::channels(), fn ($c) => $c->wantsStatus($targetStatusId))
                ));

            $choice = NotifyChoice::explicit($notify, $channelKeys);
        }

        // Built from the courier's own {tracking_number} template, and only
        // when there is a number to put in it — a link to a tracking page with
        // no consignment on it is worse than no link.
        $trackingLink = ($tracking && $courier) ? $courier->getTrackingLink($tracking) : null;

        $preBookingStatusId = (int) $order->order_status_id;
        $meta = $order->courier_meta ? (json_decode($order->courier_meta, true) ?: []) : [];
        // Only the two keys the undo path reads survive: the audit trail of
        // past bookings, and where this order came from. Any provider-owned
        // residue from a previously cancelled booking is dropped, because no
        // provider owns this shipment.
        $meta = [
            'booking_history'             => (isset($meta['booking_history']) && is_array($meta['booking_history'])) ? $meta['booking_history'] : [],
            'pre_booking_order_status_id' => $preBookingStatusId,
            'manual_shipment'             => [
                'shipping_courier_id' => $courier?->id,
                'courier_name'        => $courier?->name,
                'tracking_number'     => $tracking,
                'note'                => $note,
                'recorded_at'         => now()->toIso8601String(),
                'recorded_by'         => Auth::id(),
                // Which state the operator said this order is now in. Null
                // means they left it to the module's own placed status.
                'applied_order_status_id' => $chosenStatus?->id,
            ],
        ];
        if ($meta['booking_history'] === []) {
            unset($meta['booking_history']);
        }

        // The write and the status move either both land or neither does: an
        // order carrying a courier name while still sitting in To Pack would
        // offer to be booked again over a parcel that has already left.
        $applied = null;
        DB::transaction(function () use ($order, $courier, $tracking, $trackingLink, $meta, $note, $chosenStatus, $choice, &$applied) {
            // forceFill for courier_meta / courier_booked_at only (extension-
            // owned, not in core Order::$fillable). The three columns that
            // matter here are core's own and long-standing; they are written
            // in the same call so the model saves once.
            $order->forceFill([
                'shipping_courier_id' => $courier?->id,
                'tracking_number'     => $tracking,
                'tracking_link'       => $trackingLink,
                'courier_meta'        => json_encode($meta),
                'courier_booked_at'   => now(),
            ])->save();

            // One sentence, built once, with the courier's tracking anchor
            // riding on the end exactly as withTracking() builds it
            // everywhere else. It becomes the history row AND, through the
            // doorway, the customer email: no second, shorter version to
            // drift out of sync with it. Built here, after the forceFill
            // above, so $order already carries the fresh tracking_number and
            // tracking_link withTracking() reads.
            $prefix = ($courier
                    ? 'Shipment recorded with ' . $courier->name . ($tracking ? '' : ', no tracking number')
                    : 'Updated from fulfillment')
                . '.' . ($note ? ' Note: ' . $note : '');
            $comment = OrderNotificationService::withTracking($order, $prefix);

            // Moves it by the same shared helper an integrated booking uses,
            // so a manual parcel walks the placement tabs exactly like every
            // other order: same OrderStatusChanger call, same history row
            // shape, same stock rules, same notification behaviour. The only
            // difference is WHICH status, which the operator may name.
            $applied = self::applyBookedStatus($order, $courier?->name, $tracking, $comment, $chosenStatus, $choice);
        });

        return [
            'success'         => true,
            'courier'         => $courier?->name,
            'tracking_number' => $tracking,
            // Null when the order was already on that status, in which case
            // nothing moved and the flash has nothing to report.
            'status'          => $applied?->name,
        ];
    }

    /**
     * The undo: forget a recorded shipment and put the order back where it was
     * before it was recorded, so it can be recorded again (wrong courier,
     * wrong number) or booked with an integrated courier instead.
     *
     * Offered on To Handover only, which is the same placement Cancel booking
     * is offered on and for the same reason: it is the step that undoes the
     * move recording made. An order the admin has since advanced by hand is
     * past this, and its tracking is edited on the order page, which has
     * always been able to.
     *
     * @return array{success:bool, error?:string, code?:string, courier?:string, status?:string}
     */
    public function clearManual(Order $order): array
    {
        if (!self::isManuallyShipped($order)) {
            return ['success' => false, 'code' => 'not_manual', 'error' => "Order #{$order->id} has no recorded shipment to clear."];
        }

        if (!in_array(self::tabBucket($order), [self::UNBOOKED_PLACEMENT, self::BOOKED_PLACEMENT], true)) {
            $label = self::TAB_LABELS[self::BOOKED_PLACEMENT];
            return [
                'success' => false,
                'code'    => 'placement_blocked',
                'error'   => "Order #{$order->id} has moved past {$label}. Set its status back to one placed under {$label} to clear the shipment, or edit its tracking on the order page.",
            ];
        }

        $courierName = self::manualCourierName($order);
        $tracking = $order->tracking_number;

        $meta = $order->courier_meta ? (json_decode($order->courier_meta, true) ?: []) : [];
        $history = (isset($meta['booking_history']) && is_array($meta['booking_history'])) ? $meta['booking_history'] : [];
        $preBookingStatusId = isset($meta['pre_booking_order_status_id']) ? (int) $meta['pre_booking_order_status_id'] : null;

        // Same audit shape a cancelled courier booking leaves behind, so one
        // reader covers both: what shipped, by whom, and that a human undid it.
        $history[] = [
            'courier'                  => null,
            'shipping_courier_id'      => (int) $order->shipping_courier_id,
            'courier_name'             => $courierName,
            'tracking_number'          => $tracking,
            'manual'                   => true,
            'note'                     => $meta['manual_shipment']['note'] ?? null,
            'previous_order_status_id' => $preBookingStatusId,
            // Which status the operator put this order in when they recorded
            // it (null = the module's own placed one), so the audit row says
            // what was actually chosen and not merely what was undone.
            'applied_order_status_id'  => $meta['manual_shipment']['applied_order_status_id'] ?? null,
            'admin_cancelled'          => true,
            'cancelled_at'             => now()->toIso8601String(),
        ];

        $comment = 'Recorded shipment cleared: ' . $courierName
            . ($tracking ? ' (' . $tracking . ')' : '') . '.';

        $target = self::unbookedStatus($preBookingStatusId);

        DB::transaction(function () use ($order, $history, $target, $comment) {
            $order->forceFill([
                'shipping_courier_id' => null,
                'tracking_number'     => null,
                'tracking_link'       => null,
                // booking_history is the ONE key that survives, exactly as it
                // does through a cancelled courier booking.
                'courier_meta'        => json_encode(['booking_history' => $history]),
                'courier_booked_at'   => null,
            ])->save();

            if ($target && (int) $order->order_status_id !== (int) $target->id) {
                // Same reasoning as cancelBooking(): undoing a recorded
                // shipment reverts to where it was before, and the customer
                // was never told about it through this internal move either.
                OrderStatusChanger::apply(
                    $order,
                    $target->id,
                    $comment . ' Order status set to ' . $target->name . ', back under To Ship, To Pack.',
                    NotifyChoice::silent(),
                    Auth::id(),
                );

                return;
            }

            // Nowhere to move it to, or already there: the audit row still has
            // to exist, and it says what actually happened.
            OrderHistory::create([
                'order_id'        => $order->id,
                'order_status_id' => $order->order_status_id,
                'comment'         => $comment . ' Order status unchanged (' . ($order->status?->name ?? 'its current status') . ').',
                'notify_customer' => false,
            ]);
        });

        return [
            'success' => true,
            'courier' => $courierName,
            'status'  => $target?->name,
        ];
    }

    /**
     * What to call the courier on a manual shipment. The order's own snapshot
     * first (it survives the courier being renamed or deleted, which is the
     * whole point of Order's snapshot columns), then the live row for the
     * pre-snapshot orders that predate it.
     */
    private static function manualCourierName(Order $order): string
    {
        return $order->shipping_courier_name
            ?: ($order->shippingCourier?->name ?: 'another courier');
    }
}
