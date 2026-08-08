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

    /** True when the order has a tracking number AND its status isn't canceled. */
    public static function isActivelyBooked(Order $order): bool
    {
        return (bool) $order->courier_tracking_number
            && !in_array((string) $order->courier_status, self::CANCELED_STATUSES, true);
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
        if (!$order->courier_tracking_number) {
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
        $courier = CourierManager::get((string) ($opts['courier'] ?? ''));
        if (!$courier || !$courier->isEnabled()) {
            return ['success' => false, 'code' => 'courier_unavailable', 'error' => 'Selected courier is not available.'];
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
