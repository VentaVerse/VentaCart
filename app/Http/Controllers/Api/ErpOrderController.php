<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ErpOrderController extends Controller
{
    use LogsApiCalls;

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 20), 100);

        $query = Order::query()
            ->with('status:id,name')
            ->withCount('products as item_count');

        if ($request->filled('status_id')) {
            $query->where('order_status_id', $request->input('status_id'));
        }

        if ($request->filled('since')) {
            $query->where('created_at', '>=', $request->input('since'));
        }

        if ($request->filled('customer_email')) {
            $query->where('email', $request->input('customer_email'));
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $data = $orders->through(function ($order) {
            return [
                'id' => $order->id,
                'order_number' => $order->id,
                'status' => $order->status?->name,
                'customer_name' => $order->full_name,
                'customer_email' => $order->email,
                'total' => $order->total,
                'item_count' => $order->item_count,
                'created_at' => $order->created_at?->toIso8601String(),
            ];
        });

        return $this->logAndRespond($request, $data);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $order = Order::with([
            'status:id,name',
            'customer:id,first_name,last_name,email',
            'products',
            'histories.status:id,name',
        ])->find($id);

        if (!$order) {
            return $this->logAndRespond($request, ['error' => 'Order not found'], 404);
        }

        $data = [
            'id' => $order->id,
            'order_number' => $order->id,
            'status' => $order->status?->name,
            'status_id' => $order->order_status_id,
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
                'country_code' => $order->shipping_country_code,
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
                'country_code' => $order->billing_country_code,
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
            'payment_method' => $order->payment_method,
            'payment_reference' => $order->payment_reference,
            'shipping_method' => $order->shipping_method,
            'tracking_number' => $order->tracking_number,
            'comment' => $order->comment,
            'history' => $order->histories->map(function ($h) {
                return [
                    'id' => $h->id,
                    'status' => $h->status?->name,
                    'comment' => $h->comment,
                    'created_at' => $h->created_at?->toIso8601String(),
                ];
            }),
            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
        ];

        return $this->logAndRespond($request, $data);
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

        $order->load('status:id,name');

        $data = [
            'id' => $order->id,
            'status' => $order->status?->name,
            'status_id' => $order->order_status_id,
            'updated_at' => $order->updated_at?->toIso8601String(),
        ];

        return $this->logAndRespond($request, $data);
    }
}
