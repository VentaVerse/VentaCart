<?php

namespace Extensions\CourierFulfillment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Extensions\CourierFulfillment\CourierManager;
use Extensions\CourierFulfillment\FulfillmentService;
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
        $primary  = $couriers[0] ?? null;

        $filter = $request->query('filter', 'to_book');
        $query  = Order::query()
            ->whereNotNull('order_status_id') // Ghost/abandoned orders (no status) are never fulfilled — same default as the core Orders page.
            ->with('status:id,name')
            ->withCount('products')
            ->latest();

        if ($filter === 'to_book') {
            $query->whereNull('courier_tracking_number');
        } elseif ($filter === 'in_transit') {
            $query->whereNotNull('courier_tracking_number')
                ->where(function ($q) {
                    $q->whereNull('courier_status')->orWhere('courier_status', '!=', 'delivered');
                });
        } elseif ($filter === 'delivered') {
            $query->where('courier_status', 'delivered');
        }
        // 'all' — no extra constraint.

        if ($s = $request->query('q')) {
            $query->where(function ($x) use ($s) {
                if (is_numeric($s)) {
                    $x->orWhere('id', $s);
                }
                $x->orWhere('courier_tracking_number', 'like', "%{$s}%")
                    ->orWhereRaw("CONCAT(shipping_first_name,' ',shipping_last_name) like ?", ["%{$s}%"]);
            });
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

        return view('courier-fulfillment::index', compact('orders', 'couriers', 'primary', 'courierOptions', 'filter'));
    }

    public function book(Request $request, Order $order)
    {
        $data = $request->validate([
            'courier'          => 'required|string',
            'service'          => 'required|string',
            'parcel'           => 'required|string',
            'shipping_payment' => 'required|string',
            'insure'           => 'sometimes|boolean',
        ]);

        if (FulfillmentService::isActivelyBooked($order)) {
            return back()->with('error', "Order #{$order->id} is already booked ({$order->courier_tracking_number}).");
        }

        // Shared booking core (same path the ERP API uses).
        $res = app(\Extensions\CourierFulfillment\FulfillmentService::class)->book($order, $data);

        if (!$res['success']) {
            return back()->with('error', "Booking failed: " . ($res['error'] ?? 'unknown error'));
        }

        return back()->with('success', "Booked — tracking {$res['tracking_number']}.");
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

        $order->forceFill(['courier_status' => 'canceled'])->save();
        \Extensions\CourierFulfillment\StatusMapper::apply($order, 'canceled');
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

        if (!empty($fill)) {
            // courier_* columns aren't in core Order::$fillable — must forceFill.
            $order->forceFill($fill)->save();
        }
        if (!empty($result['status'])) {
            \Extensions\CourierFulfillment\StatusMapper::apply($order, $result['status']);
        }
        return back()->with('success', "Status: " . ($result['status'] ?? 'unchanged'));
    }
}
