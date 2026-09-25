<?php

namespace App\Http\Controllers\Admin\Sales;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\OrderNotificationService;
use App\Models\ShippingCourier;
use Illuminate\Http\Request;
use App\Services\OrderStatusChanger;
use App\Services\StockService;
use App\Support\NotifyChoice;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        // No eager loads: the index renders only columns the order owns.
        $query = Order::query();

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->input('store_id'));
        }

        if ($request->input('order_status_id') === 'missing') {
            // "Missing" means the order never reached a status at all, so it
            // carries NEITHER the snapshot nor the foreign key.
            //
            // Testing the snapshot alone is what emptied this list on live
            // (2026-08-20): the migration that added order_status_name
            // backfilled nothing, so 27,706 real orders read as ghosts and
            // vanished. A real order always has order_status_id even before
            // its snapshot is written; a genuine ghost has neither. Requiring
            // both keeps the original meaning (a deleted status row nulls the
            // foreign key but leaves the snapshot, so real orders stay real)
            // while making a missed backfill cosmetic instead of catastrophic.
            $query->whereNull('order_status_name')->whereNull('order_status_id');
        } elseif ($request->filled('order_status_id')) {
            // Filtering BY a status is a live question about a status that
            // still exists, so it stays on the foreign key.
            $query->where('order_status_id', $request->input('order_status_id'));
        } else {
            // Everything that is not a genuine ghost. See the comment above.
            $query->where(function ($q) {
                $q->whereNotNull('order_status_name')->orWhereNotNull('order_status_id');
            });
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            // Same name rule as the customers list. An order number and a
            // tracking number are matched whole, and a numeric search still
            // finds the order by id.
            $query->where(function ($q) use ($search) {
                if (is_numeric($search)) {
                    $q->orWhere('id', (int) $search);
                }
                $q->orWhere(fn ($n) => \App\Support\NameSearch::apply($n, $search, ['email', 'tracking_number']));
            });
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();
        $statuses = OrderStatus::where('store_id', (int) config('app.store_id', 1))->orderBy('sort_order')->get();

        return view('admin.sales.orders.index', compact('orders', 'statuses'));
    }

    public function create()
    {
        $statuses = OrderStatus::where('store_id', (int) config('app.store_id', 1))->orderBy('sort_order')->get();
        $couriers = ShippingCourier::where('store_id', (int) config('app.store_id', 1))->where('is_active', true)->orderBy('sort_order')->get();
        $countries = Country::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.sales.orders.form', [
            'order' => null,
            'statuses' => $statuses,
            'couriers' => $couriers,
            'countries' => $countries,
        ]);
    }

    public function show(Order $order)
    {
        // customer stays only because the page links to its profile; every
        // value rendered here now comes off the order itself.
        // products.product:id only says whether the product still exists,
        // which is what decides if the line's name is a link to its edit page.
        $order->load(['customer:id', 'products.product:id', 'histories']);
        $statuses = OrderStatus::where('store_id', (int) config('app.store_id', 1))->orderBy('sort_order')->get();
        $couriers = ShippingCourier::where('store_id', (int) config('app.store_id', 1))->where('is_active', true)->orderBy('sort_order')->get();

        return view('admin.sales.orders.show', compact('order', 'statuses', 'couriers'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateOrder($request);

        $newCustomerPassword = null;

        // Snapshot human-facing facts at order time (order-snapshots spec).
        $validated['payment_method_title'] = $this->resolvePaymentTitle($request->input('payment_method'));
        $validated['shipping_country_name'] = Country::find($validated['shipping_country_id'] ?? null)?->name;
        $validated['billing_country_name'] = Country::find($validated['billing_country_id'] ?? null)?->name;

        $order = DB::transaction(function () use ($request, $validated, &$newCustomerPassword) {
            $validated['created_by'] = Auth::id();
            $validated['subtotal'] = 0;

            // Create new customer if requested
            if ($request->boolean('is_new_customer') && empty($validated['customer_id'])) {
                $storeId = (int) config('app.store_id', 1);
                $newCustomerPassword = Str::random(12);

                $defaultGroup = \App\Models\CustomerGroup::where('is_default', true)->first();

                $customer = Customer::create([
                    'store_id' => $storeId,
                    'customer_group_id' => $defaultGroup?->id,
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'email' => $validated['email'],
                    'password' => $newCustomerPassword,
                    'is_active' => true,
                ]);

                $validated['customer_id'] = $customer->id;

                // Save shipping address to customer profile
                if (!empty($validated['shipping_address_1'])) {
                    $customer->addresses()->create([
                        'first_name' => $validated['shipping_first_name'] ?? $validated['first_name'],
                        'last_name' => $validated['shipping_last_name'] ?? $validated['last_name'],
                        'address_1' => $validated['shipping_address_1'],
                        'city' => $validated['shipping_city'] ?? '',
                        'district' => $validated['shipping_district'] ?? null,
                        'barangay' => $validated['shipping_barangay'] ?? null,
                        'state' => $validated['shipping_state'] ?? '',
                        'postcode' => $validated['shipping_postcode'] ?? '',
                        'country_id' => $validated['shipping_country_id'] ?? null,
                        'phone' => $validated['shipping_phone'] ?? null,
                        'is_default' => true,
                    ]);
                }
            }

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

        // Send account creation email to new customer
        if ($newCustomerPassword) {
            \App\Services\TemplateMailService::send('account_created_by_admin', $order->email, [
                'customer_name' => $order->first_name . ' ' . $order->last_name,
                'email' => $order->email,
                'password' => $newCustomerPassword,
                'store_name' => config('app.name'),
                'login_url' => route('customer.login'),
            ]);
        }

        $message = 'Order created successfully.';
        if ($newCustomerPassword) {
            $message .= ' New customer account created and credentials emailed.';
        }

        return redirect()->route('admin.orders.index')
            ->with('success', $message);
    }

    public function edit(Order $order)
    {
        // products.product and customer are live links on purpose here: the edit
        // screen reprices against today's specials and today's customer group.
        $order->load(['customer', 'products.product']);
        $statuses = OrderStatus::where('store_id', (int) config('app.store_id', 1))->orderBy('sort_order')->get();
        $couriers = ShippingCourier::where('store_id', (int) config('app.store_id', 1))->where('is_active', true)->orderBy('sort_order')->get();
        $countries = Country::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        $customerGroupId = $order->customer?->customer_group_id;
        // The name the order owns, not the group's current name. The badge on
        // this page is history, while $customerGroupId above drives live
        // repricing and so must stay a live link.
        $customerGroupName = $order->customer_group_name;

        $now = now();
        $existingItems = $order->products->map(function ($item) use ($customerGroupId, $now) {
            $image = $item->image ? thumb_url($item->image) : null;
            $groupPrice = null;

            if ($customerGroupId && $item->product) {
                // Customer-group pricing flows through product_specials
                $item->product->loadMissing('specials');
                $bestSpecial = $item->product->specials
                    ->filter(function ($s) use ($now, $customerGroupId) {
                        if ($s->date_start && $s->date_start > $now) return false;
                        if ($s->date_end && $s->date_end < $now) return false;
                        if ($s->customer_group_id !== null && $s->customer_group_id != $customerGroupId) return false;
                        return true;
                    })
                    ->sortBy([['priority', 'asc'], ['price', 'asc']])
                    ->first();

                if ($bestSpecial) {
                    $groupPrice = (float) $bestSpecial->price;
                }
            }

            // Resolve base price and display mode
            $basePrice = $item->product_variant_id
                ? ((float) (\App\Models\ProductVariant::find($item->product_variant_id)?->price ?? $item->price))
                : ((float) ($item->product?->price ?? $item->price));

            $displayMode = $item->product ? $this->resolveDisplayMode($item->product, $customerGroupId) : null;
            $groupPriceMode = ($groupPrice !== null && $groupPrice < $basePrice) ? ($displayMode ?? 'override') : 'regular';

            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'name' => $item->name,
                'sku' => $item->sku ?? '',
                'variant_label' => $item->variant_label ?? '',
                'price' => $item->price,
                'quantity' => $item->quantity,
                'is_custom' => $item->is_custom,
                'image' => $image,
                'group_price' => $groupPrice ?? $basePrice,
                'group_price_mode' => $groupPriceMode,
                'base_price' => $basePrice,
            ];
        })->toArray();

        return view('admin.sales.orders.form', compact('order', 'statuses', 'couriers', 'countries', 'existingItems', 'customerGroupId', 'customerGroupName'));
    }

    public function update(Request $request, Order $order)
    {
        $validated = $this->validateOrder($request, $order->id);

        // Re-snapshot human-facing facts only when their underlying fields changed.
        if ($request->has('payment_method')) {
            $validated['payment_method_title'] = $this->resolvePaymentTitle($request->input('payment_method'));
        }
        if (array_key_exists('shipping_country_id', $validated)) {
            $validated['shipping_country_name'] = Country::find($validated['shipping_country_id'])?->name;
        }
        if (array_key_exists('billing_country_id', $validated)) {
            $validated['billing_country_name'] = Country::find($validated['billing_country_id'])?->name;
        }

        $productsChanged = false;

        DB::transaction(function () use ($request, $order, $validated, &$productsChanged) {
            $statusChanged = (int) $order->order_status_id !== (int) $validated['order_status_id'];

            // Detect if products changed before sync
            $oldProducts = $order->products->map(fn($p) => $p->product_id . '-' . $p->product_variant_id . '-' . $p->quantity . '-' . $p->price)->sort()->values()->toJson();

            // The lines themselves, not just their fingerprint: if they change
            // while this order's units are out of inventory, stock has to be
            // settled against what was actually deducted. Loaded here because
            // syncProducts() deletes the rows.
            $oldLines = $order->products->load('product', 'productVariant');

            // order_status_id is excluded here and applied below, through the
            // one doorway every other status change uses: a mass update that
            // set it early would make OrderStatusChanger::apply() read the
            // order as already on its new status and skip the history row,
            // the stock adjustment and the announcement entirely.
            $order->update(Arr::except($validated, ['order_status_id']));

            $this->syncProducts($order, $request);
            $this->recalculateTotals($order);

            $order->load('products');
            $newProducts = $order->products->map(fn($p) => $p->product_id . '-' . $p->product_variant_id . '-' . $p->quantity . '-' . $p->price)->sort()->values()->toJson();
            $productsChanged = $oldProducts !== $newProducts;

            // Order matters: settle the lines FIRST, under the flag as it
            // stands, so stock reflects the new lines. Only then does the
            // status change apply its own rule, which reads those same new
            // lines. Doing it the other way round would deduct or return a
            // list that was about to be replaced.
            if ($productsChanged) {
                StockService::reconcileLines($order, $oldLines);
            }

            if ($statusChanged) {
                // Same doorway every other status change uses: history row +
                // status update + stock adjustment + the one announcement.
                // This form carries no per-change notify tick, so the
                // store's own ticks on the target status decide, exactly as
                // an ERP call or a courier poll landing here would.
                OrderStatusChanger::apply(
                    $order,
                    (int) $validated['order_status_id'],
                    null,
                    NotifyChoice::follow(),
                    Auth::id(),
                );
            }

            // Stock moving with nothing to show for it is how this went
            // unnoticed for so long. If units moved, the order says so.
            if ($productsChanged && $order->fresh()->stock_adjusted) {
                \App\Models\OrderHistory::create([
                    'order_id'        => $order->id,
                    'order_status_id' => $order->order_status_id,
                    'comment'         => 'Products edited while stock was deducted. Inventory reconciled: the previous items were returned and the current items taken out.',
                    'notify_customer' => false,
                ]);
            }
        });

        // Resend order confirmation if products changed
        if ($productsChanged && $order->email) {
            $order->load('products');
            \App\Services\CheckoutService::sendOrderConfirmationEmail($order);
        }

        return redirect()->route('admin.orders.edit', $order)
            ->with('success', 'Order updated successfully.' . ($productsChanged ? ' Confirmation email resent.' : ''));
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
            'channels' => ['array'],
            'channels.*' => ['string', 'max:32'],
            'shipping_courier_id' => ['nullable', 'exists:shipping_couriers,id'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['created_by'] = Auth::id();
        $validated['notify_customer'] = $request->has('notify_customer');

        // Update courier/tracking on the order if provided
        $courierChanged = false;
        if ($request->filled('shipping_courier_id') || $request->filled('tracking_number')) {
            $order->update([
                'shipping_courier_id' => $validated['shipping_courier_id'] ?? $order->shipping_courier_id,
                'tracking_number' => $validated['tracking_number'] ?? $order->tracking_number,
            ]);
            $order->refresh();
            $order->update([
                'tracking_link' => ($order->tracking_number && $order->shippingCourier)
                    ? $order->shippingCourier->getTrackingLink($order->tracking_number)
                    : null,
            ]);
            $courierChanged = true;
        }

        // Build comment with tracking link if tracking was provided. Through
        // the shared builder, so the fulfilment queue says this the same way.
        $comment = $validated['comment'] ?? '';
        if ($courierChanged && !empty($order->tracking_number)) {
            $comment = OrderNotificationService::withTracking($order, $comment);
        }

        $validated['comment'] = $comment ?: null;

        // Shared status-change core (history row + status update + stock
        // adjustment + the one announcement) — the same mechanics the
        // fulfillment queue's Cancel order action applies
        // (App\Services\OrderStatusChanger). The operator's own ticks on
        // this page are the explicit choice; the doorway sends, once.
        OrderStatusChanger::apply(
            $order,
            (int) $validated['order_status_id'],
            $validated['comment'],
            NotifyChoice::explicit(
                (bool) $validated['notify_customer'],
                $validated['channels'] ?? [],
            ),
            $validated['created_by'],
        );

        return redirect()->route('admin.orders.show', $order)
            ->with('success', 'Order updated.');
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
        $order->refresh();
        $order->update([
            'tracking_link' => ($order->tracking_number && $order->shippingCourier)
                ? $order->shippingCourier->getTrackingLink($order->tracking_number)
                : null,
        ]);

        if ($notify) {
            OrderNotificationService::shipped($order);
        }

        return redirect()->route('admin.orders.show', $order)
            ->with('success', 'Tracking information updated.');
    }

    // validateCoupon moved to the coupons extension (route name
    // admin.orders.validate-coupon frozen; the order form's coupon box
    // renders only while that route exists).

    public function searchProducts(Request $request)
    {
        $request->validate([
            'store_id' => ['required', 'exists:stores,id'],
            'q' => ['required', 'string', 'min:1'],
        ]);

        $search = $request->input('q');
        $storeId = $request->input('store_id');

        $customerGroupId = $request->input('customer_group_id');

        $customerGroupId = $customerGroupId ? (int) $customerGroupId : null;

        $products = Product::where('store_id', $storeId)
            ->where('is_active', true)
            ->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            })
            ->with(['variants' => function ($q) {
                $q->where('is_active', true);
            }, 'brand', 'specials'])
            ->limit(15)
            ->get();

        $results = [];
        foreach ($products as $product) {
            $image = $product->image ? storage_url($product->image) : null;

            if ($product->has_variants) {
                foreach ($product->variants as $variant) {
                    // The row IS the colourway, so it wears the colourway's
                    // photo. Only a variant with none of its own falls back to
                    // the parent picture.
                    $variantImage = $variant->image ? storage_url($variant->image) : $image;
                    $basePrice = (float) $variant->price;

                    // Resolve via CartService (handles specials + date + quantity + group)
                    $resolvedPrice = \App\Services\CartService::resolvePrice($product, $basePrice, 1, $customerGroupId);

                    // Determine display mode from the matching special
                    $displayMode = $this->resolveDisplayMode($product, $customerGroupId);

                    $results[] = [
                        'product_id' => $product->id,
                        'product_variant_id' => $variant->id,
                        'name' => $product->name,
                        'sku' => $variant->sku,
                        'variant_label' => $variant->name,
                        'price' => $resolvedPrice,
                        'base_price' => $basePrice,
                        'group_price' => $resolvedPrice,
                        'group_price_mode' => $resolvedPrice < $basePrice ? ($displayMode ?? 'override') : 'regular',
                        'stock' => $variant->quantity,
                        'image' => $variantImage,
                    ];
                }
            } else {
                $basePrice = (float) $product->price;
                $resolvedPrice = \App\Services\CartService::resolvePrice($product, $basePrice, 1, $customerGroupId);
                $displayMode = $this->resolveDisplayMode($product, $customerGroupId);

                $results[] = [
                    'product_id' => $product->id,
                    'product_variant_id' => null,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'variant_label' => null,
                    'price' => $resolvedPrice,
                    'base_price' => $basePrice,
                    'group_price' => $resolvedPrice,
                    'group_price_mode' => $resolvedPrice < $basePrice ? ($displayMode ?? 'override') : 'regular',
                    'stock' => $product->quantity,
                    'image' => $image,
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
            // The picker searches names the same way the lists do, so a
            // customer findable in one screen is findable in all three.
            ->where(fn ($q) => \App\Support\NameSearch::apply($q, $search, ['email']))
            ->with(['addresses', 'customerGroup'])
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
                    'customer_group_id' => $c->customer_group_id,
                    'customer_group_name' => $c->customerGroup?->name,
                    'address' => $defaultAddr ? [
                        'first_name' => $defaultAddr->first_name,
                        'last_name' => $defaultAddr->last_name,
                        'address_1' => $defaultAddr->address_1,
                        'city' => $defaultAddr->city,
                        'district' => $defaultAddr->district,
                        'barangay' => $defaultAddr->barangay,
                        'state' => $defaultAddr->state,
                        'postcode' => $defaultAddr->postcode,
                        'country_id' => $defaultAddr->country_id,
                        'phone' => $defaultAddr->phone,
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
                            'country_id' => $a->country_id,
                            'phone' => $a->phone,
                            'is_default' => $a->is_default,
                        ];
                    })->values(),
                ];
            });

        return response()->json($customers);
    }

    public function availableShipping(Request $request): \Illuminate\Http\JsonResponse
    {
        $address = [
            'country_id' => $request->input('country_id'),
            'province' => $request->input('state', ''),
            'state' => $request->input('state', ''),
            'city' => $request->input('city', ''),
            'barangay' => $request->input('barangay', ''),
            'postcode' => $request->input('postcode', ''),
        ];

        $customerGroupId = $request->input('customer_group_id');
        $brandIds = [];
        $categoryIds = [];

        $items = collect($request->input('items', []))->map(function ($item) use (&$brandIds, &$categoryIds) {
            $product = \App\Models\Product::with('categories')->find($item['product_id'] ?? null);
            $variant = !empty($item['product_variant_id']) ? \App\Models\ProductVariant::find($item['product_variant_id']) : null;

            if ($product) {
                if ($product->brand_id) $brandIds[] = $product->brand_id;
                $categoryIds = array_merge($categoryIds, $product->categories->pluck('id')->all());
            }

            return (object) [
                'product_id' => $item['product_id'] ?? null,
                'quantity' => (int) ($item['quantity'] ?? 1),
                'price' => (float) ($item['price'] ?? 0),
                'weight' => (float) ($variant?->weight ?? $product?->weight ?? 0),
                'length' => (float) ($product?->length ?? 0),
                'width' => (float) ($product?->width ?? 0),
                'height' => (float) ($product?->height ?? 0),
            ];
        });

        $subtotal = $items->sum(fn($i) => $i->price * $i->quantity);

        $quoteService = app(\App\Services\Shipping\ShippingQuoteService::class);
        $quotes = $quoteService->getQuotes($address, $items, $subtotal, [
            'customer_group_id' => $customerGroupId ? (int) $customerGroupId : null,
            'brand_ids' => array_values(array_unique($brandIds)),
            'category_ids' => array_values(array_unique($categoryIds)),
            'items' => $items,
        ]);

        return response()->json(['methods' => $quotes]);
    }

    public function availablePayments(Request $request): \Illuminate\Http\JsonResponse
    {
        $address = [
            'country_id' => $request->input('country_id'),
            'state' => $request->input('state', ''),
            'city' => $request->input('city', ''),
            'barangay' => $request->input('barangay', ''),
            'postcode' => $request->input('postcode', ''),
        ];

        $subtotal = (float) $request->input('subtotal', 0);
        $weight = (float) $request->input('weight', 0);
        $quantity = (int) $request->input('quantity', 0);
        $customerGroupId = $request->input('customer_group_id');
        $categoryIds = $request->input('category_ids', []);
        $brandIds = $request->input('brand_ids', []);
        $shippingMethodKey = $request->input('shipping_method_key');

        $checkoutService = app(\App\Services\CheckoutService::class);
        $methods = $checkoutService->getAvailablePaymentMethods($address, $subtotal, [
            'weight' => $weight,
            'quantity' => $quantity,
            'customer_group_id' => $customerGroupId,
            'category_ids' => $categoryIds,
            'brand_ids' => $brandIds,
            'shipping_method_key' => $shippingMethodKey,
        ]);

        $result = collect($methods)->map(function ($m) {
            return [
                'key' => $m->getKey(),
                'name' => $m->getName(),
                'description' => $m->getDescription(),
            ];
        })->values();

        return response()->json(['methods' => $result]);
    }

    /** Human title for a picked payment key; free-text methods snapshot as themselves. */
    private function resolvePaymentTitle(?string $key): ?string
    {
        if (!$key) {
            return null;
        }
        foreach (app(\App\Services\CheckoutService::class)->getAvailablePaymentMethods() as $m) {
            if ($m->getKey() === $key) {
                return $m->getName();
            }
        }
        return $key; // admin typed a free-text method — the text IS the title
    }

    protected function validateOrder(Request $request, ?int $orderId = null): array
    {
        $isCreate = $orderId === null;
        $r = $isCreate ? 'required' : 'nullable';

        return $request->validate([
            'store_id' => ['required', 'exists:stores,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
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
            'shipping_country_id' => [$r, 'integer', 'exists:countries,id'],
            'shipping_phone' => ['nullable', 'string', 'max:30'],
            'billing_first_name' => ['nullable', 'string', 'max:255'],
            'billing_last_name' => ['nullable', 'string', 'max:255'],
            'billing_address_1' => ['nullable', 'string'],
            'billing_city' => ['nullable', 'string', 'max:255'],
            'billing_district' => ['nullable', 'string', 'max:255'],
            'billing_barangay' => ['nullable', 'string', 'max:255'],
            'billing_state' => ['nullable', 'string', 'max:255'],
            'billing_postcode' => ['nullable', 'string', 'max:50'],
            'billing_country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'billing_phone' => ['nullable', 'string', 'max:30'],
            'payment_method' => [$r, 'string', 'max:255'],
            'shipping_method' => [$r, 'string', 'max:255'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'shipping_courier_id' => ['nullable', 'exists:shipping_couriers,id'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'comment' => ['nullable', 'string'],
            'coupon_code' => ['nullable', 'string', 'max:32'],
            'coupon_discount' => ['nullable', 'numeric', 'min:0'],
            'items' => $isCreate ? ['required', 'array', 'min:1'] : ['nullable', 'array'],
            'items.*.name' => ['required', 'string'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);
    }

    private function resolveDisplayMode(Product $product, ?int $customerGroupId): ?string
    {
        $now = now();
        $best = $product->specials
            ->filter(function ($s) use ($now, $customerGroupId) {
                if ($s->date_start && $s->date_start > $now) return false;
                if ($s->date_end && $s->date_end < $now) return false;
                if ($s->customer_group_id !== null && $s->customer_group_id != $customerGroupId) return false;
                return true;
            })
            ->sortBy([['priority', 'asc'], ['price', 'asc']])
            ->first();

        return $best?->display_mode;
    }

    protected function syncProducts(Order $order, Request $request): void
    {
        $order->products()->delete();

        $items = $request->input('items', []);

        // One lookup per table for the whole basket, never a find per line.
        // The variant's own photo wins where there is one: the snapshot has to
        // remember the colourway that was actually sold.
        $images = Product::whereIn('id', collect($items)->pluck('product_id')->filter()->unique())
            ->pluck('image', 'id');
        $variantImages = ProductVariant::whereIn('id', collect($items)->pluck('product_variant_id')->filter()->unique())
            ->pluck('image', 'id');

        foreach ($items as $item) {
            if (empty($item['name'])) {
                continue;
            }

            $price = (float) ($item['price'] ?? 0);
            $qty = (int) ($item['quantity'] ?? 1);

            $order->products()->create([
                // ?? before ?:, because neither key is required by the
                // validation rules above: a client that posts a line without
                // them (the ERP, a script, a form that omits the hidden
                // input) used to raise "Undefined array key" and 500 the save.
                'product_id' => ($item['product_id'] ?? null) ?: null,
                'product_variant_id' => ($item['product_variant_id'] ?? null) ?: null,
                'name' => $item['name'],
                'sku' => $item['sku'] ?? null,
                'variant_label' => $item['variant_label'] ?? null,
                'price' => $price,
                'quantity' => $qty,
                'total' => $price * $qty,
                'is_custom' => !empty($item['is_custom']),
                'image' => ($variantImages[$item['product_variant_id'] ?? null] ?? null)
                    ?: ($images[$item['product_id'] ?? null] ?? null),
            ]);
        }
    }

    /**
     * Every deduction the order carries, not just the coupon. Store credit is
     * spent from the customer's balance at checkout and never handed back, so
     * a recompute that dropped it billed the customer again for money they had
     * already paid in credit.
     */
    protected function recalculateTotals(Order $order): void
    {
        $subtotal = (float) $order->products()->sum('total');
        $adjustments = array_sum(array_column($order->adjustmentLines(), 'amount'));
        $order->update([
            'subtotal' => $subtotal,
            'total' => max(0, round($subtotal + (float) ($order->shipping_cost ?? 0) + $adjustments, 2)),
        ]);
    }
}
