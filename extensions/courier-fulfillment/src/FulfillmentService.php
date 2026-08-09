<?php

namespace Extensions\CourierFulfillment;

use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Services\OrderStatusChanger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
     * This REPLACES the tabs-v3 blend (stage-vs-phase arbitration, the
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

        $configured = Setting::where('group', 'courier')->where('key', $key)->value('value');
        if ($configured && in_array((int) $configured, $ids, true)) {
            $status = OrderStatus::find((int) $configured);
            if ($status) {
                return $status;
            }
        }

        if ($preferredName !== null) {
            $named = OrderStatus::whereIn('id', $ids)->where('name', $preferredName)->first();
            if ($named) {
                return $named;
            }
        }

        return OrderStatus::whereIn('id', $ids)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->first();
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
     * Notify is OFF: the customer is told about a shipment by the status
     * map's own notify flag or the order page, never by this internal move.
     */
    public static function applyBookedStatus(Order $order, ?string $courierName = null, ?string $tracking = null): ?OrderStatus
    {
        $status = self::bookedStatus();
        if (!$status || (int) $order->order_status_id === (int) $status->id) {
            return null;
        }

        $comment = 'Courier booking confirmed'
            . ($courierName ? ' with ' . $courierName : '')
            . ($tracking ? ' (' . $tracking . ')' : '')
            . '. Order status set to ' . $status->name . ' by fulfillment.';

        // History row + status update + stock adjustment either all land or
        // none do, same as every other status change in the app.
        DB::transaction(function () use ($order, $status, $comment) {
            OrderStatusChanger::apply($order, $status->id, $comment, false, Auth::id());
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
        // Ordering is load-bearing (kept verbatim from tabs-v3's F3 fix):
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
                'error'   => 'No order status is placed under To Handover, so a booked order would have nowhere to go. Place one under Settings, Modules, Courier Fulfillment, Status placement.',
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
        // fulfillment-tabs-v3 Task 4: an admin-initiated booking cancel
        // resets the courier fields but persists the audit trail under
        // courier_meta.booking_history (FulfillmentController::cancel()) —
        // carried forward here so a rebook's fresh meta never wipes it.
        $previousMeta = $order->courier_meta ? (json_decode($order->courier_meta, true) ?: []) : [];

        $result = $courier->createShipment($order, $opts);
        if (!($result['success'] ?? false)) {
            return ['success' => false, 'code' => 'booking_failed', 'error' => $result['error'] ?? 'Booking failed.'];
        }

        $meta = $result['meta'] ?? [];
        $meta['previous_tracking_number'] = $previousTracking;
        $meta['pre_booking_order_status_id'] = $preBookingStatusId;
        if (isset($previousMeta['booking_history']) && is_array($previousMeta['booking_history'])) {
            $meta['booking_history'] = $previousMeta['booking_history'];
        }

        // forceFill bypasses mass-assignment: courier_* columns are extension-owned
        // and not in core Order::$fillable; courier_meta has no cast so encode it.
        $order->forceFill([
            'courier_provider'        => $courier->getKey(),
            'courier_tracking_number' => $result['tracking_number'] ?? null,
            'courier_status'          => $result['status'] ?? null,
            'courier_meta'            => json_encode($meta),
            'courier_booked_at'       => now(),
            // Core snapshot field (Order Historical Snapshots): the extension is the
            // only writer of core tracking_number; no tracking_link yet at booking time.
            'tracking_number'         => $result['tracking_number'] ?? null,
            'tracking_link'           => null, // stale link from a prior booking/manual entry must not point at the new shipment; the poll re-stamps it when the courier link appears
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
}
