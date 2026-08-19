<?php

namespace Extensions\CourierFulfillment\Http\Controllers;

use App\Http\Controllers\Api\LogsApiCalls;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Extensions\CourierFulfillment\CourierManager;
use Extensions\CourierFulfillment\FulfillmentService;
use Extensions\CourierFulfillment\ServiceabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ERP-facing booking endpoint: ERP -> Venta -> courier.
 *
 * The ERP is the order hub but does NOT talk to couriers; Venta owns the courier
 * integration. The ERP POSTs here to trigger a booking for a Venta order, Venta
 * executes it against QuadX (via the shared FulfillmentService) and returns the
 * tracking number. Idempotent — a repeat call returns the existing booking.
 *
 * Auth + logging match the rest of the ERP API (erp.auth + LogsApiCalls).
 */
class ErpFulfillmentController extends Controller
{
    use LogsApiCalls;

    public function book(Request $request, int $id, FulfillmentService $service): JsonResponse
    {
        $order = Order::find($id);
        if (!$order) {
            return $this->logAndRespond($request, ['error' => 'Order not found'], 404);
        }

        // Sensible defaults so the ERP can book with a minimal payload; any field
        // may be overridden. buyer_payment left null => the courier derives it
        // from whether the order is COD.
        $opts = [
            'courier'          => $request->input('courier', $this->defaultCourierKey()),
            'service'          => $request->input('service', 'next_day'),
            'parcel'           => $request->input('parcel', 'box'),
            'buyer_payment'    => $request->input('buyer_payment'),
            'shipping_payment' => $request->input('shipping_payment', 'net_off'),
            // Bookings are uninsured by default; ERP may opt in explicitly.
            'insure'           => $request->boolean('insure'),
        ];

        $res = $service->book($order, $opts);

        if (!($res['success'] ?? false)) {
            $status = ($res['code'] ?? '') === 'courier_unavailable' ? 409 : 422;
            return $this->logAndRespond($request, [
                'error' => $res['error'] ?? 'Booking failed',
                'code'  => $res['code'] ?? null,
            ], $status);
        }

        return $this->logAndRespond($request, [
            'success'         => true,
            'already_booked'  => $res['already_booked'] ?? false,
            'order_id'        => $order->id,
            'courier'         => $res['courier'] ?? null,
            'tracking_number' => $res['tracking_number'] ?? null,
            'status'          => $res['status'] ?? null,
        ]);
    }

    /**
     * Cancel this order's courier booking. The full state transition
     * (booking_history snapshot, courier-field reset, status back to
     * To Pack) is FulfillmentService::cancelBooking() — the same code
     * the admin queue runs, reported as JSON.
     */
    public function cancelBooking(Request $request, int $id): JsonResponse
    {
        $order = Order::find($id);
        if (!$order) {
            return $this->logAndRespond($request, ['error' => 'Order not found'], 404);
        }

        $res = FulfillmentService::cancelBooking($order);

        if (!$res['success']) {
            return $this->logAndRespond($request, ['error' => $res['error']], 422);
        }

        return $this->logAndRespond($request, [
            'success' => true,
            'order_id' => $order->id,
            'tracking_number' => $res['tracking'] ?? null,
            'order_status' => $res['order_status'] ?? null,
            'message' => $res['message'],
        ]);
    }

    /**
     * Live tracking pull. Refreshes Venta's own courier_status /
     * timeline / links on the way through (same shared path as the
     * admin's Track button), then hands the ERP the result.
     */
    public function tracking(Request $request, int $id): JsonResponse
    {
        $order = Order::find($id);
        if (!$order) {
            return $this->logAndRespond($request, ['error' => 'Order not found'], 404);
        }

        $res = FulfillmentService::refreshTracking($order);

        if (!$res['success']) {
            return $this->logAndRespond($request, ['error' => $res['error']], 422);
        }

        return $this->logAndRespond($request, [
            'success' => true,
            'order_id' => $order->id,
            'courier' => $order->courier_provider,
            'tracking_number' => $order->courier_tracking_number,
            'status' => $res['status'],
            'timeline' => $res['timeline'],
            'links' => $res['links'],
        ]);
    }

    /**
     * Shipping label. When the courier's printLabel() cached the PDF
     * locally (SPX pattern — the provider link is perishable), the file
     * streams straight back on this authenticated call; otherwise the
     * provider's own presigned URL is returned for the ERP to fetch.
     */
    public function label(Request $request, int $id)
    {
        $order = Order::find($id);
        if (!$order) {
            return $this->logAndRespond($request, ['error' => 'Order not found'], 404);
        }

        $courier = CourierManager::get((string) $order->courier_provider);
        if (!$courier || !$order->courier_tracking_number) {
            return $this->logAndRespond($request, ['error' => 'No courier booking to print'], 422);
        }

        $format = (string) $request->query('format', 'zebra');
        $result = $courier->printLabel($order->courier_tracking_number, $format);

        if (!($result['success'] ?? false) || empty($result['url'])) {
            return $this->logAndRespond($request, ['error' => 'Label not available: ' . ($result['error'] ?? 'no URL returned')], 422);
        }

        $cached = "courier-labels/{$order->courier_provider}/{$order->courier_tracking_number}.pdf";
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        if ($disk->exists($cached)) {
            return $disk->download($cached, "label-order-{$order->id}.pdf");
        }

        return $this->logAndRespond($request, [
            'success' => true,
            'order_id' => $order->id,
            'url' => $result['url'],
        ]);
    }

    /**
     * Who will carry this parcel? Every configured courier's verdict at
     * once, same ServiceabilityService pass the booking modal uses
     * (bounded per-courier timeout; a refusal reads as "could not
     * check", never a hard error).
     */
    public function serviceability(Request $request, int $id): JsonResponse
    {
        $order = Order::find($id);
        if (!$order) {
            return $this->logAndRespond($request, ['error' => 'Order not found'], 404);
        }

        if (FulfillmentService::bookingBlockedReason($order) !== null) {
            return $this->logAndRespond($request, ['success' => true, 'couriers' => [], 'all_refused' => false]);
        }

        $verdicts = ServiceabilityService::forOrder($order);

        return $this->logAndRespond($request, [
            'success' => true,
            'order_id' => $order->id,
            'couriers' => array_map(fn ($v) => $v->toArray(), $verdicts),
            'all_refused' => ServiceabilityService::allRefused($verdicts),
        ]);
    }

    /**
     * Fee estimate for a booking the ERP is about to make. Quote-only,
     * creates nothing; same placement gate as booking itself.
     */
    public function estimate(Request $request, int $id): JsonResponse
    {
        $order = Order::find($id);
        if (!$order) {
            return $this->logAndRespond($request, ['error' => 'Order not found'], 404);
        }

        $blocked = FulfillmentService::bookingBlockedReason($order);
        if ($blocked !== null) {
            return $this->logAndRespond($request, ['error' => $blocked], 422);
        }

        $courierKey = (string) $request->input('courier', $this->defaultCourierKey());
        $courier = CourierManager::get($courierKey);
        if (!$courier || !$courier->isEnabled()) {
            return $this->logAndRespond($request, ['error' => 'Selected courier is not available'], 409);
        }

        $opts = collect($request->only([
            'service', 'parcel', 'shipping_payment', 'length_cm', 'width_cm',
            'height_cm', 'weight_kg', 'pickup_slot', 'pickup_address_id',
        ]))->filter(fn ($v) => $v !== null)->all();
        $opts['insure'] = $request->boolean('insure');

        $estimate = $courier->estimateFee($order, $opts);
        if (!$estimate) {
            return $this->logAndRespond($request, ['error' => 'An estimate is not available yet. Check the pickup address and this order\'s shipping address are complete.'], 422);
        }

        return $this->logAndRespond($request, [
            'success' => true,
            'order_id' => $order->id,
            'courier' => $courierKey,
            // `amount` is the shipping fee, unchanged. Insurance is reported
            // beside it rather than folded into it, so an ERP reading this
            // field keeps getting the figure it has always got.
            'amount' => $estimate->amount,
            'currency' => $estimate->currency,
            'label' => $estimate->label ?? ($estimate->currency . ' ' . number_format($estimate->amount, 2)),
            'insurance' => $estimate->insurance ?? null,
            'total' => $estimate->total ?? $estimate->amount,
        ]);
    }

    /**
     * Pickup slots for a courier that offers them (SPX). Empty for
     * couriers without slot booking — same contract as the admin modal.
     */
    public function pickupSlots(Request $request, int $id): JsonResponse
    {
        $order = Order::find($id);
        if (!$order) {
            return $this->logAndRespond($request, ['error' => 'Order not found'], 404);
        }

        $courier = CourierManager::get((string) $request->query('courier', $this->defaultCourierKey() ?? ''));
        if (!$courier || !$courier->isEnabled()) {
            return $this->logAndRespond($request, ['error' => 'Selected courier is not available'], 409);
        }

        return $this->logAndRespond($request, [
            'success' => true,
            'order_id' => $order->id,
            'courier' => $courier->getKey(),
            'slots' => $courier->getPickupSlots($order),
        ]);
    }

    private function defaultCourierKey(): ?string
    {
        $enabled = CourierManager::enabled();
        return $enabled ? $enabled[0]->getKey() : null;
    }
}
