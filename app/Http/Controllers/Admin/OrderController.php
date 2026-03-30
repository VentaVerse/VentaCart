<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\ShippingCourier;
use Illuminate\Http\Request;
use App\Services\StockService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with(['customer', 'status']);

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->input('store_id'));
        }

        if ($request->input('order_status_id') === 'missing') {
            $query->whereNull('order_status_id');
        } elseif ($request->filled('order_status_id')) {
            $query->where('order_status_id', $request->input('order_status_id'));
        } else {
            // By default, hide missing orders (NULL status)
            $query->whereNotNull('order_status_id');
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('id', $search)
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('tracking_number', 'like', "%{$search}%");
            });
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();
        $statuses = OrderStatus::where('store_id', (int) config('app.store_id', 1))->orderBy('sort_order')->get();

        return view('admin.orders.index', compact('orders', 'statuses'));
    }

    public function create()
    {
        $statuses = OrderStatus::where('store_id', (int) config('app.store_id', 1))->orderBy('sort_order')->get();
        $couriers = ShippingCourier::where('store_id', (int) config('app.store_id', 1))->where('is_active', true)->orderBy('sort_order')->get();
        $countries = Country::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.orders.form', [
            'order' => null,
            'statuses' => $statuses,
            'couriers' => $couriers,
            'countries' => $countries,
        ]);
    }

    public function show(Order $order)
    {
        $order->load(['customer', 'status', 'products', 'histories.status', 'histories.createdByUser', 'shippingCourier']);
        $order->load('shipment.events');
        $statuses = OrderStatus::where('store_id', (int) config('app.store_id', 1))->orderBy('sort_order')->get();
        $couriers = ShippingCourier::where('store_id', (int) config('app.store_id', 1))->where('is_active', true)->orderBy('sort_order')->get();
        $dphEnabled = \App\Services\DeliveryPhService::isEnabled();

        return view('admin.orders.show', compact('order', 'statuses', 'couriers', 'dphEnabled'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateOrder($request);

        $order = DB::transaction(function () use ($request, $validated) {
            $validated['created_by'] = Auth::id();
            $validated['subtotal'] = 0;

            $order = Order::create($validated);

            $this->syncProducts($order, $request);
            $this->recalculateTotals($order);

            // Adjust stock based on initial status
            $initialStatus = OrderStatus::find($order->order_status_id);
            if ($initialStatus) {
                StockService::adjustForStatusChange($order, $initialStatus);
            }

            // Create initial history entry
            $notifyCustomer = $request->boolean('notify_customer');
            $order->histories()->create([
                'order_status_id' => $order->order_status_id,
                'created_by' => Auth::id(),
                'comment' => 'Order created manually from admin.',
                'notify_customer' => $notifyCustomer,
            ]);

            return $order;
        });

        // Send emails after transaction commits
        $order->load('products', 'status');
        if ($request->boolean('notify_customer') && $order->email) {
            \App\Services\CheckoutService::sendOrderConfirmationEmail($order);
        }
        if ($request->boolean('notify_admin')) {
            \App\Services\CheckoutService::sendAdminOrderNotification($order);
        }

        return redirect()->route('admin.orders.index')
            ->with('success', 'Order created successfully.');
    }

    public function edit(Order $order)
    {
        $order->load(['customer', 'status', 'products', 'histories.status', 'histories.createdByUser', 'shippingCourier']);
        $order->load('shipment.events');
        $statuses = OrderStatus::where('store_id', (int) config('app.store_id', 1))->orderBy('sort_order')->get();
        $couriers = ShippingCourier::where('store_id', (int) config('app.store_id', 1))->where('is_active', true)->orderBy('sort_order')->get();
        $countries = Country::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $dphEnabled = \App\Services\DeliveryPhService::isEnabled();

        $existingItems = $order->products->map(function ($item) {
            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'name' => $item->name,
                'sku' => $item->sku ?? '',
                'model' => $item->model ?? '',
                'variant_label' => $item->variant_label ?? '',
                'price' => $item->price,
                'quantity' => $item->quantity,
                'is_custom' => $item->is_custom,
            ];
        })->toArray();

        return view('admin.orders.form', compact('order', 'statuses', 'couriers', 'countries', 'existingItems', 'dphEnabled'));
    }

    public function update(Request $request, Order $order)
    {
        $validated = $this->validateOrder($request, $order->id);

        DB::transaction(function () use ($request, $order, $validated) {
            $statusChanged = (int) $order->order_status_id !== (int) $validated['order_status_id'];

            $order->update($validated);

            $this->syncProducts($order, $request);
            $this->recalculateTotals($order);

            if ($statusChanged) {
                $newStatus = OrderStatus::find($validated['order_status_id']);
                if ($newStatus) {
                    StockService::adjustForStatusChange($order, $newStatus);
                }
            }
        });

        return redirect()->route('admin.orders.edit', $order)
            ->with('success', 'Order updated successfully.');
    }

    public function destroy(Order $order)
    {
        $order->delete();

        return redirect()->route('admin.orders.index')
            ->with('success', 'Order deleted successfully.');
    }

    public function addHistory(Request $request, Order $order)
    {
        $validated = $request->validate([
            'order_status_id' => ['required', 'exists:order_statuses,id'],
            'comment' => ['nullable', 'string'],
            'notify_customer' => ['boolean'],
        ]);

        $oldStatus = $order->status?->name ?? 'Unknown';
        $validated['created_by'] = Auth::id();
        $validated['notify_customer'] = $request->has('notify_customer');

        $order->histories()->create($validated);
        $order->update(['order_status_id' => $validated['order_status_id']]);

        $newStatus = OrderStatus::find($validated['order_status_id']);
        if ($newStatus) {
            StockService::adjustForStatusChange($order, $newStatus);
        }

        // Send email notification if requested
        if ($validated['notify_customer'] && $order->email) {
            \App\Services\TemplateMailService::send('order_status_update', $order->email, [
                'customer_name' => $order->first_name . ' ' . $order->last_name,
                'order_id' => $order->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus?->name ?? 'Unknown',
                'comment' => $validated['comment'] ?? '',
                'store_name' => config('app.name'),
            ]);
        }

        return redirect()->route('admin.orders.show', $order)
            ->with('success', 'Order history added.');
    }

    public function deleteHistory(Order $order, int $historyId)
    {
        $order->histories()->where('id', $historyId)->delete();

        return redirect()->route('admin.orders.show', $order)
            ->with('success', 'History entry deleted.');
    }

    public function updateTracking(Request $request, Order $order)
    {
        $validated = $request->validate([
            'shipping_courier_id' => ['nullable', 'exists:shipping_couriers,id'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'notify_customer' => ['nullable', 'boolean'],
        ]);

        $hadTracking = !empty($order->tracking_number);
        $notify = $request->boolean('notify_customer');

        $order->update([
            'shipping_courier_id' => $validated['shipping_courier_id'],
            'tracking_number' => $validated['tracking_number'],
        ]);

        // Send shipping notification if new tracking number and notify is checked
        if ($notify && !empty($validated['tracking_number']) && $order->email) {
            $courier = $order->shippingCourier;
            \App\Services\TemplateMailService::send('shipping_notification', $order->email, [
                'customer_name' => $order->first_name . ' ' . $order->last_name,
                'order_id' => $order->id,
                'tracking_number' => $validated['tracking_number'],
                'carrier_name' => $courier?->name ?? $order->shipping_method ?? 'Courier',
                'store_name' => config('app.name'),
            ]);
        }

        return redirect()->route('admin.orders.show', $order)
            ->with('success', 'Tracking information updated.');
    }

    public function searchProducts(Request $request)
    {
        $request->validate([
            'store_id' => ['required', 'exists:stores,id'],
            'q' => ['required', 'string', 'min:1'],
        ]);

        $search = $request->input('q');
        $storeId = $request->input('store_id');

        $products = Product::where('store_id', $storeId)
            ->where('is_active', true)
            ->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('model', 'like', "%{$search}%");
            })
            ->with(['variants' => function ($q) {
                $q->where('is_active', true);
            }, 'brand'])
            ->limit(15)
            ->get();

        $results = [];
        foreach ($products as $product) {
            if ($product->has_variants) {
                foreach ($product->variants as $variant) {
                    $results[] = [
                        'product_id' => $product->id,
                        'product_variant_id' => $variant->id,
                        'name' => $product->name,
                        'sku' => $variant->sku,
                        'model' => $product->model,
                        'variant_label' => $variant->name,
                        'price' => $variant->price,
                        'stock' => $variant->quantity,
                    ];
                }
            } else {
                $results[] = [
                    'product_id' => $product->id,
                    'product_variant_id' => null,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'model' => $product->model,
                    'variant_label' => null,
                    'price' => $product->price,
                    'stock' => $product->quantity,
                ];
            }
        }

        return response()->json($results);
    }

    public function searchCustomers(Request $request)
    {
        $request->validate([
            'store_id' => ['required', 'exists:stores,id'],
            'q' => ['required', 'string', 'min:1'],
        ]);

        $search = $request->input('q');

        $customers = Customer::where('store_id', $request->input('store_id'))
            ->where('is_active', true)
            ->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            })
            ->with('addresses')
            ->limit(10)
            ->get()
            ->map(function ($c) {
                $defaultAddr = $c->addresses->where('is_default', true)->first();
                return [
                    'id' => $c->id,
                    'name' => $c->full_name,
                    'email' => $c->email,
                    'phone' => $c->phone,
                    'first_name' => $c->first_name,
                    'last_name' => $c->last_name,
                    'address' => $defaultAddr ? [
                        'first_name' => $defaultAddr->first_name,
                        'last_name' => $defaultAddr->last_name,
                        'address_1' => $defaultAddr->address_1,
                        'city' => $defaultAddr->city,
                        'district' => $defaultAddr->district,
                        'barangay' => $defaultAddr->barangay,
                        'state' => $defaultAddr->state,
                        'postcode' => $defaultAddr->postcode,
                        'country_code' => $defaultAddr->country_code,
                    ] : null,
                    'addresses' => $c->addresses->map(function ($a) {
                        return [
                            'id' => $a->id,
                            'label' => $a->address_1 . ', ' . $a->city . ($a->state ? ', ' . $a->state : '') . ($a->is_default ? ' (Default)' : ''),
                            'first_name' => $a->first_name,
                            'last_name' => $a->last_name,
                            'address_1' => $a->address_1,
                            'city' => $a->city,
                            'district' => $a->district,
                            'barangay' => $a->barangay,
                            'state' => $a->state,
                            'postcode' => $a->postcode,
                            'country_code' => $a->country_code,
                            'phone' => $a->phone,
                            'is_default' => $a->is_default,
                        ];
                    })->values(),
                ];
            });

        return response()->json($customers);
    }

    protected function validateOrder(Request $request, ?int $orderId = null): array
    {
        $isCreate = $orderId === null;
        $r = $isCreate ? 'required' : 'nullable';

        return $request->validate([
            'store_id' => ['required', 'exists:stores,id'],
            'customer_id' => [$r, 'exists:customers,id'],
            'order_status_id' => ['required', 'exists:order_statuses,id'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'shipping_first_name' => [$r, 'string', 'max:255'],
            'shipping_last_name' => [$r, 'string', 'max:255'],
            'shipping_address_1' => [$r, 'string'],
            'shipping_city' => [$r, 'string', 'max:255'],
            'shipping_district' => ['nullable', 'string', 'max:255'],
            'shipping_barangay' => ['nullable', 'string', 'max:255'],
            'shipping_state' => ['nullable', 'string', 'max:255'],
            'shipping_postcode' => ['nullable', 'string', 'max:50'],
            'shipping_country_code' => [$r, 'string', 'max:3'],
            'shipping_phone' => ['nullable', 'string', 'max:30'],
            'billing_first_name' => ['nullable', 'string', 'max:255'],
            'billing_last_name' => ['nullable', 'string', 'max:255'],
            'billing_address_1' => ['nullable', 'string'],
            'billing_city' => ['nullable', 'string', 'max:255'],
            'billing_district' => ['nullable', 'string', 'max:255'],
            'billing_barangay' => ['nullable', 'string', 'max:255'],
            'billing_state' => ['nullable', 'string', 'max:255'],
            'billing_postcode' => ['nullable', 'string', 'max:50'],
            'billing_country_code' => ['nullable', 'string', 'max:3'],
            'billing_phone' => ['nullable', 'string', 'max:30'],
            'payment_method' => [$r, 'string', 'max:255'],
            'shipping_method' => [$r, 'string', 'max:255'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'shipping_courier_id' => ['nullable', 'exists:shipping_couriers,id'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'comment' => ['nullable', 'string'],
            'items' => $isCreate ? ['required', 'array', 'min:1'] : ['nullable', 'array'],
            'items.*.name' => ['required', 'string'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);
    }

    protected function syncProducts(Order $order, Request $request): void
    {
        $order->products()->delete();

        $items = $request->input('items', []);
        foreach ($items as $item) {
            if (empty($item['name'])) {
                continue;
            }

            $price = (float) ($item['price'] ?? 0);
            $qty = (int) ($item['quantity'] ?? 1);

            $order->products()->create([
                'product_id' => $item['product_id'] ?: null,
                'product_variant_id' => $item['product_variant_id'] ?: null,
                'name' => $item['name'],
                'sku' => $item['sku'] ?? null,
                'model' => $item['model'] ?? null,
                'variant_label' => $item['variant_label'] ?? null,
                'price' => $price,
                'quantity' => $qty,
                'total' => $price * $qty,
                'is_custom' => !empty($item['is_custom']),
            ]);
        }
    }

    protected function recalculateTotals(Order $order): void
    {
        $subtotal = $order->products()->sum('total');
        $order->update([
            'subtotal' => $subtotal,
            'total' => $subtotal + ($order->shipping_cost ?? 0),
        ]);
    }
}
