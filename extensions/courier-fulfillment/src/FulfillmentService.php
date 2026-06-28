<?php

namespace Extensions\CourierFulfillment;

use App\Models\Order;

/**
 * Shared booking core, used by BOTH the admin Fulfillment page and the ERP API
 * endpoint — one implementation, no duplication.
 *
 * Idempotent: if the order already carries a tracking number, it returns that
 * existing booking instead of creating a second shipment (the ERP may retry; a
 * courier order must never be double-booked — we saw 6 duplicate staging
 * bookings for one order from exactly this hazard).
 */
class FulfillmentService
{
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

        // Idempotency guard — already booked => return the existing shipment.
        if ($order->courier_tracking_number) {
            return [
                'success'         => true,
                'already_booked'  => true,
                'courier'         => $order->courier_provider,
                'tracking_number' => $order->courier_tracking_number,
                'status'          => $order->courier_status,
            ];
        }

        $result = $courier->createShipment($order, $opts);
        if (!($result['success'] ?? false)) {
            return ['success' => false, 'code' => 'booking_failed', 'error' => $result['error'] ?? 'Booking failed.'];
        }

        // forceFill bypasses mass-assignment: courier_* columns are extension-owned
        // and not in core Order::$fillable; courier_meta has no cast so encode it.
        $order->forceFill([
            'courier_provider'        => $courier->getKey(),
            'courier_tracking_number' => $result['tracking_number'] ?? null,
            'courier_status'          => $result['status'] ?? null,
            'courier_meta'            => isset($result['meta']) ? json_encode($result['meta']) : null,
            'courier_booked_at'       => now(),
        ])->save();

        return [
            'success'         => true,
            'already_booked'  => false,
            'courier'         => $courier->getKey(),
            'tracking_number' => $result['tracking_number'] ?? null,
            'status'          => $result['status'] ?? null,
        ];
    }
}
