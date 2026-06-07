<?php

namespace Extensions\CourierFulfillment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Extensions\CourierFulfillment\CourierManager;
use Illuminate\Http\Request;

/**
 * The dedicated Fulfillment page. Self-contained in the extension — the generic
 * Sales > Order page is never touched. Lists orders, books them with a courier,
 * prints labels, cancels, refreshes tracking.
 */
class FulfillmentController extends Controller
{
    public function index(Request $request)
    {
        $couriers = CourierManager::enabled();

        $filter = $request->query('filter', 'to_book');
        $query = Order::query()->latest();

        if ($filter === 'to_book') {
            $query->whereNull('courier_tracking_number');
        } elseif ($filter === 'booked') {
            $query->whereNotNull('courier_tracking_number');
        }

        $orders = $query->paginate(25)->withQueryString();

        // Build provider option sets for the booking form (courier-agnostic).
        $courierOptions = [];
        foreach ($couriers as $c) {
            $courierOptions[$c->getKey()] = [
                'name'     => $c->getName(),
                'services' => $c->getServiceTypes(),
                'parcels'  => $c->getParcelOptions(),
                'payments' => $c->getShippingPaymentOptions(),
            ];
        }

        return view('courier-fulfillment::index', compact('orders', 'couriers', 'courierOptions', 'filter'));
    }

    public function book(Request $request, Order $order)
    {
        $data = $request->validate([
            'courier'          => 'required|string',
            'service'          => 'required|string',
            'parcel'           => 'required|string',
            'buyer_payment'    => 'required|in:cod,prepaid',
            'shipping_payment' => 'required|string',
        ]);

        $courier = CourierManager::get($data['courier']);
        if (!$courier || !$courier->isEnabled()) {
            return back()->with('error', 'Selected courier is not available.');
        }

        if ($order->courier_tracking_number) {
            return back()->with('error', "Order #{$order->id} is already booked ({$order->courier_tracking_number}).");
        }

        $result = $courier->createShipment($order, $data);

        if (!$result['success']) {
            return back()->with('error', "Booking failed: " . ($result['error'] ?? 'unknown error'));
        }

        $order->update([
            'courier_provider'        => $courier->getKey(),
            'courier_tracking_number' => $result['tracking_number'] ?? null,
            'courier_status'          => $result['status'] ?? null,
            'courier_meta'            => $result['meta'] ?? null,
            'courier_booked_at'       => now(),
        ]);

        return back()->with('success', "Booked with {$courier->getName()} — tracking {$result['tracking_number']}.");
    }

    public function cancel(Order $order)
    {
        $courier = CourierManager::get((string) $order->courier_provider);
        if (!$courier || !$order->courier_tracking_number) {
            return back()->with('error', 'No active courier booking on this order.');
        }

        $result = $courier->cancelShipment($order->courier_tracking_number);
        if (!$result['success']) {
            return back()->with('error', 'Cancel failed: ' . ($result['error'] ?? 'unknown error'));
        }

        $order->update(['courier_status' => 'cancelled']);
        return back()->with('success', "Cancelled booking for order #{$order->id}.");
    }

    public function label(Request $request, Order $order)
    {
        $courier = CourierManager::get((string) $order->courier_provider);
        if (!$courier || !$order->courier_tracking_number) {
            return back()->with('error', 'No courier booking to print.');
        }

        $format = $request->query('format', 'zebra');
        $result = $courier->printLabel($order->courier_tracking_number, $format);

        if (!$result['success'] || empty($result['url'])) {
            return back()->with('error', 'Label not available: ' . ($result['error'] ?? 'no URL returned'));
        }

        // Presigned courier URL (e.g. S3) — send the admin straight to the PDF.
        return redirect()->away($result['url']);
    }

    public function track(Order $order)
    {
        $courier = CourierManager::get((string) $order->courier_provider);
        if (!$courier || !$order->courier_tracking_number) {
            return back()->with('error', 'No courier booking to track.');
        }

        $result = $courier->getTracking($order->courier_tracking_number);
        if (!$result['success']) {
            return back()->with('error', 'Tracking failed: ' . ($result['error'] ?? 'unknown error'));
        }

        if (!empty($result['status'])) {
            $order->update(['courier_status' => $result['status']]);
        }
        return back()->with('success', "Status: " . ($result['status'] ?? 'unchanged'));
    }
}
