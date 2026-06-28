<?php

namespace Extensions\CourierFulfillment\Http\Controllers;

use App\Http\Controllers\Api\LogsApiCalls;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Extensions\CourierFulfillment\CourierManager;
use Extensions\CourierFulfillment\FulfillmentService;
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

    private function defaultCourierKey(): ?string
    {
        $enabled = CourierManager::enabled();
        return $enabled ? $enabled[0]->getKey() : null;
    }
}
