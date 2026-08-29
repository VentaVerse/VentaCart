<?php

namespace Extensions\ErpSync\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ErpOrderController extends Controller
{
    use \App\Http\Controllers\Api\LogsApiCalls;

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 20), 100);

        $query = Order::query()
            ->withCount('products as item_count');

        if ($request->filled('status_id')) {
            $query->where('order_status_id', $request->input('status_id'));
        } else {
            $query->whereNotNull('order_status_name');
        }

        if ($request->filled('since')) {
            $query->where('created_at', '>=', $request->input('since'));
        }

        // What changed since the ERP last looked, bookings included: a
        // booking made in this admin touches updated_at, so the ERP's
        // minute-by-minute pull can ask for only those rows instead of
        // re-reading the store.
        if ($request->filled('updated_since')) {
            $query->where('updated_at', '>=', $request->input('updated_since'));
        }

        if ($request->filled('customer_email')) {
            $query->where('email', $request->input('customer_email'));
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $data = $orders->through(function ($order) {
            return [
                'id' => $order->id,
                'order_number' => $order->id,
                'status' => $order->order_status_name,
                // The group this order was placed under, informational only.
                // A snapshot on the order rather than a lookup, so it answers
                // what the buyer was at the time and does not change when a
                // customer is later moved between groups.
                'customer_group' => $order->customer_group_name,
                'customer_name' => $order->full_name,
                // The customer's message from checkout, on the LIST as well as
                // the detail, so the ERP can flag an order that carries one
                // without fetching every order. Informational, never written
                // back through this API.
                'comment' => $order->comment,
                'courier_comment' => $order->courier_comment,
                'customer_email' => $order->email,
                'total' => $order->total,
                'item_count' => $order->item_count,
                'courier' => $this->courierPayload($order),
                'created_at' => $order->created_at?->toIso8601String(),
                'updated_at' => $order->updated_at?->toIso8601String(),
            ];
        });

        return $this->logAndRespond($request, $data);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $order = Order::with([
            'customer:id,first_name,last_name,email',
            'products',
            'histories',
        ])->find($id);

        if (!$order) {
            return $this->logAndRespond($request, ['error' => 'Order not found'], 404);
        }

        $data = [
            'id' => $order->id,
            'order_number' => $order->id,
            'status' => $order->order_status_name,
            'status_id' => $order->order_status_id,
            // Null on the 24,237 migrated orders that never captured it, and
            // deliberately not backfilled from the customer: not one of them
            // has a customer attached, so there is nothing to fall back to
            // and a guess would read as fact.
            'customer_group' => $order->customer_group_name,
            'customer' => $order->customer ? [
                'id' => $order->customer->id,
                'name' => $order->customer->first_name . ' ' . $order->customer->last_name,
                'email' => $order->customer->email,
            ] : null,
            'contact' => [
                'name' => $order->full_name,
                'email' => $order->email,
                'phone' => $order->phone,
            ],
            'shipping_address' => [
                'first_name' => $order->shipping_first_name,
                'last_name' => $order->shipping_last_name,
                'address_1' => $order->shipping_address_1,
                'city' => $order->shipping_city,
                'district' => $order->shipping_district,
                'barangay' => $order->shipping_barangay,
                'state' => $order->shipping_state,
                'postcode' => $order->shipping_postcode,
                'country_code' => $order->shippingCountry?->iso2,
            ],
            'billing_address' => $order->billing_first_name ? [
                'first_name' => $order->billing_first_name,
                'last_name' => $order->billing_last_name,
                'address_1' => $order->billing_address_1,
                'city' => $order->billing_city,
                'district' => $order->billing_district,
                'barangay' => $order->billing_barangay,
                'state' => $order->billing_state,
                'postcode' => $order->billing_postcode,
                'country_code' => $order->billingCountry?->iso2,
            ] : null,
            'items' => $order->products->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'variant_label' => $item->variant_label,
                    'price' => $item->price,
                    'quantity' => $item->quantity,
                    'total' => $item->total,
                    'is_custom' => $item->is_custom,
                ];
            }),
            'totals' => [
                'subtotal' => $order->subtotal,
                'shipping' => $order->shipping_cost,
                'total' => $order->total,
            ],
            'payment_method' => $order->payment_method_label,
            'payment_method_key' => $order->payment_method,
            'payment_reference' => $order->payment_reference,
            'shipping_method' => $order->shipping_method,
            'tracking_number' => $order->tracking_number,
            'courier' => $this->courierPayload($order),
            'comment' => $order->comment,
            'courier_comment' => $order->courier_comment,
            'history' => $order->histories->map(function ($h) {
                return [
                    'id' => $h->id,
                    'status' => $h->order_status_name,
                    'comment' => $h->comment,
                    'created_at' => $h->created_at?->toIso8601String(),
                ];
            }),
            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
        ];

        return $this->logAndRespond($request, $data);
    }

    /**
     * The courier booking on this order, as the ERP needs to read it.
     *
     * Three cases, told apart the way FulfillmentService tells them apart:
     * an integrated booking carries a provider key; a shipment recorded by
     * hand carries a courier from the store's own list and no provider; and
     * an order nobody has booked carries neither. The columns come from the
     * courier-fulfillment extension's migration and are simply null on an
     * install without it, so this reads as "no booking" there too.
     *
     * @return array{provider: ?string, tracking_number: ?string, status: ?string, booked_at: ?string, manual: bool, manual_courier: ?string}
     */
    private function courierPayload(Order $order): array
    {
        $provider = $order->getAttribute('courier_provider');
        $manual = $provider === null && $order->shipping_courier_id !== null;

        return [
            'provider' => $provider,
            'tracking_number' => $manual
                ? $order->tracking_number
                : $order->getAttribute('courier_tracking_number'),
            'status' => $order->getAttribute('courier_status'),
            'booked_at' => ($at = $order->getAttribute('courier_booked_at'))
                ? \Illuminate\Support\Carbon::parse($at)->toIso8601String()
                : null,
            'manual' => $manual,
            'manual_courier' => $manual
                ? ($order->shipping_courier_name ?: $order->shippingCourier?->name)
                : null,
        ];
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $order = Order::find($id);

        if (!$order) {
            return $this->logAndRespond($request, ['error' => 'Order not found'], 404);
        }

        $validated = $request->validate([
            'status_id' => 'required|integer|exists:order_statuses,id',
            'comment' => 'nullable|string|max:1000',
        ]);

        $order->update(['order_status_id' => $validated['status_id']]);

        OrderHistory::create([
            'order_id' => $order->id,
            'order_status_id' => $validated['status_id'],
            'comment' => $validated['comment'] ?? 'Status updated via ERP API',
            'notify_customer' => false,
        ]);

        $data = [
            'id' => $order->id,
            'status' => $order->order_status_name,
            'status_id' => $order->order_status_id,
            'updated_at' => $order->updated_at?->toIso8601String(),
        ];

        return $this->logAndRespond($request, $data);
    }
}
