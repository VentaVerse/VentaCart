<?php

namespace Extensions\CourierFulfillment;

use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\Setting;

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
     * "no tracking number => never booked => to_book" short-circuit —
     * defined once here for phaseFor() and the queue controller's
     * tally/filter mirrors of that same rule.
     */
    public const ASYNC_CREATE_STATUSES = ['creating', 'creation_failed'];

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
     * Support\PhaseTone for the tone each bucket carries) — the future queue
     * tabs (fulfillment-workflow-v2 Task W2) group orders by this.
     *
     * `to_book` is resolved HERE, not by any courier: an order with no
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
        // that was never booked at all) is still 'to_book' as before.
        //
        // Final-review fix (Finding 2): same treatment for
        // 'creation_failed' — a permanently failed async create is ALSO
        // tracking-number-less, and short-circuiting it to to_book made
        // statusPhase('creation_failed') => 'failed' dead code: the order
        // silently blended into To Book with no badge, tab, or rendered
        // creation_error anywhere. Both bridging statuses now route
        // through the courier's own spine mapping (ASYNC_CREATE_STATUSES).
        if (!$order->courier_tracking_number
            && !in_array((string) $order->courier_status, self::ASYNC_CREATE_STATUSES, true)) {
            return 'to_book';
        }

        return self::phaseForParts($order->courier_provider, $order->courier_status);
    }

    /**
     * Same resolution as phaseFor() for an already-BOOKED order, but from the
     * raw column values instead of an Order instance — lets the queue
     * controller bucket a GROUP BY (courier_provider, courier_status) tally
     * into phases without hydrating one model per order. Callers own the
     * "no tracking number => to_book" short-circuit; this only answers for
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

        // Captured before booking: null on a first-ever booking, the old
        // (canceled) tracking number on a rebook.
        $previousTracking = $order->courier_tracking_number;

        $result = $courier->createShipment($order, $opts);
        if (!($result['success'] ?? false)) {
            return ['success' => false, 'code' => 'booking_failed', 'error' => $result['error'] ?? 'Booking failed.'];
        }

        $meta = $result['meta'] ?? [];
        $meta['previous_tracking_number'] = $previousTracking;

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

        return [
            'success'         => true,
            'already_booked'  => false,
            'courier'         => $courier->getKey(),
            'tracking_number' => $result['tracking_number'] ?? null,
            'status'          => $result['status'] ?? null,
        ];
    }
}
