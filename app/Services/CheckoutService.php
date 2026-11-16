<?php

namespace App\Services;

use App\Exceptions\CartLinkTakenException;
use App\Exceptions\CartPricesChangedException;
use App\Exceptions\OrderAlreadyBeingPlacedException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderStatus;
use App\Services\PaymentMethods\PaymentMethodInterface;
use App\Services\PaymentMethods\PaymentResult;
use App\Services\ActivityLogger;
use App\Services\PaymentRestrictionService;
use App\Mail\OrderConfirmationMail;
use App\Support\NotifyChoice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    private int $storeId;

    public function __construct()
    {
        $this->storeId = (int) config('app.store_id', 1);
    }

    /**
     * Get available payment methods, optionally filtered by restrictions.
     *
     * @param array|null $address          Shipping address ['state'=>..., 'city'=>..., 'country_id'=>...]
     * @param float|null $subtotal         Order subtotal
     * @param array      $restrictionContext  Extra context: weight, quantity, customer_group_id, category_ids, brand_ids, shipping_method_key
     */
    public function getAvailablePaymentMethods(?array $address = null, ?float $subtotal = null, array $restrictionContext = []): array
    {
        $methods = \App\Services\PaymentManager::enabledMethods();

        // Apply payment restrictions whenever we know the cart subtotal. The
        // address may be null (customer hasn't picked or saved one yet), that
        // is fine: only the geo-zone check needs an address, and isAllowed skips
        // it when absent. Every other dimension (customer group, total, quantity,
        // weight, currency) MUST still apply here, or group-restricted methods
        // (e.g. reseller-only bank transfer) leak to the wrong customers.
        if ($subtotal !== null) {
            $currencyCode = app(\App\Services\ThemeService::class)->getActiveCurrency()?->code;
            $methods = array_values(array_filter($methods, function (PaymentMethodInterface $method) use ($address, $subtotal, $restrictionContext, $currencyCode) {
                return PaymentRestrictionService::isAllowed(
                    $method->getKey(),
                    $address ?? [],
                    $subtotal,
                    $restrictionContext['weight'] ?? null,
                    $restrictionContext['quantity'] ?? null,
                    $restrictionContext['customer_group_id'] ?? null,
                    $restrictionContext['category_ids'] ?? [],
                    $restrictionContext['brand_ids'] ?? [],
                    $restrictionContext['shipping_method_key'] ?? null,
                    $currencyCode
                );
            }));
        }

        return $methods;
    }

    /**
     * The shipping quotes for a saved address and the cart's items. The
     * checkout page, its rates refresh and order placement all ask here, so
     * the quote a customer picks is computed from the same input as the
     * quote the order is charged.
     */
    public function shippingQuotesFor(CustomerAddress $address, Collection $items, float $subtotal): array
    {
        return app(\App\Services\Shipping\ShippingQuoteService::class)->getQuotes([
            'province' => $address->state,
            'city' => $address->city,
            'barangay' => $address->barangay,
            'country_id' => $address->country_id ?? null,
            'postcode' => $address->postcode ?? '',
        ], $items, $subtotal);
    }

    /**
     * The live quote the customer's chosen key names, or a validation
     * error. There is no fallback to a cost or name sent by the browser:
     * anything the form carries can be edited, so a method that cannot be
     * quoted now (rates changed, the address no longer qualifies, or a rate
     * provider is unreachable) is refused and the customer chooses again.
     *
     * @return array{key: string, name: string, cost: float|int}
     */
    private function resolveShippingQuote(CustomerAddress $address, Collection $items, float $subtotal, string $key): array
    {
        $quotes = $this->shippingQuotesFor($address, $items, $subtotal);

        if ($quotes === []) {
            throw ValidationException::withMessages([
                'shipping_method' => 'We could not get delivery options for this address right now. Please try again in a few minutes.',
            ]);
        }

        $matched = collect($quotes)->first(
            fn ($quote) => isset($quote['key'], $quote['name'], $quote['cost'])
                && (string) $quote['key'] === $key
                && is_numeric($quote['cost'])
                && (float) $quote['cost'] >= 0
        );

        if ($matched === null) {
            throw ValidationException::withMessages([
                'shipping_method' => 'That delivery option is no longer available. Choose one again.',
            ]);
        }

        return $matched;
    }

    public function getPaymentMethod(string $key): ?PaymentMethodInterface
    {
        $method = \App\Services\PaymentManager::get($key);
        return ($method && $method->isEnabled()) ? $method : null;
    }

    /**
     * Turn the cart into an order, once.
     *
     * Place order can arrive twice for the same cart (a double click, two
     * tabs, a browser retrying on a slow network). Two guards keep that to
     * one order. A per-cart cache lock makes the second request wait for
     * the first to finish, so it can be answered with the first's outcome.
     * The real guarantee is in the database: the order and the cart's link
     * to it (carts.placed_order_id) are written in one transaction under the
     * cart's row lock, and a request that finds the cart already linked to
     * an order it is a repeat of is sent to that order instead of placing
     * another.
     *
     * The result carries 'duplicate' => true when the order is that earlier
     * one: its payment result then points at the order's payment page while
     * it is unpaid, or at nothing (its confirmation) once it is placed.
     *
     * @return array{order: Order, payment: PaymentResult, duplicate: bool}
     */
    public function placeOrder(
        Customer $customer,
        CustomerAddress $shippingAddress,
        ?array $billingAddress,
        string $paymentMethodKey,
        string $shippingMethodKey,
        Cart $cart,
        ?string $comment = null,
        ?string $courierComment = null
    ): array {
        return $this->holdingCart($cart, fn () => $this->placeOrderFromCart(
            $customer, $shippingAddress, $billingAddress, $paymentMethodKey,
            $shippingMethodKey, $cart, $comment, $courierComment
        ));
    }

    /**
     * The order a submit for this now-empty cart repeats: one the cart
     * became moments ago and that is placed. A cart is emptied once its
     * order is placed, so a second Place order that arrives afterwards
     * finds nothing to order and is sent to the order it already made.
     */
    public function recentlyPlacedOrder(Cart $cart): ?Order
    {
        $cart = Cart::find($cart->id);
        if (! $cart || ! $cart->placed_order_id) {
            return null;
        }

        $order = $this->linkedOrder($cart);

        return $order && $order->order_status_id !== null && $this->withinRepeatWindow($order)
            ? $order
            : null;
    }

    /**
     * Unlink the cart from an order that will not be paid this way (the
     * payment gateway refused it, or the shopper came back from a payment
     * that failed or was cancelled), so the next Place order makes a new
     * order, exactly as it did before carts were linked to their orders.
     */
    public function releaseCart(Order $order): void
    {
        Cart::where('placed_order_id', $order->id)->update([
            'placed_order_id' => null,
            'placed_lines_key' => null,
            'placed_request_key' => null,
        ]);
    }

    /**
     * Run $place while holding this cart's placement lock, when the cache
     * store can lock. A request that cannot get the lock in time goes on
     * without it: the database check inside the order transaction still
     * decides, and answers that the order is being placed.
     */
    private function holdingCart(Cart $cart, \Closure $place): array
    {
        $store = \Illuminate\Support\Facades\Cache::store();
        if (! $store->getStore() instanceof \Illuminate\Contracts\Cache\LockProvider) {
            return $place();
        }

        $lock = $store->lock('checkout-placement:' . $cart->id, (int) config('checkout.placement.lock_seconds', 90));

        try {
            $lock->block((int) config('checkout.placement.wait_seconds', 40));
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            return $place();
        }

        try {
            return $place();
        } finally {
            $lock->release();
        }
    }

    private function placeOrderFromCart(
        Customer $customer,
        CustomerAddress $shippingAddress,
        ?array $billingAddress,
        string $paymentMethodKey,
        string $shippingMethodKey,
        Cart $cart,
        ?string $comment,
        ?string $courierComment
    ): array {
        // A repeat of a submit that already made an order is answered with
        // that order before anything else is checked: the first request may
        // have emptied the cart, and "your cart is empty" would tell the
        // shopper nothing about the order they just placed.
        $current = Cart::find($cart->id) ?? $cart;
        $lines = $current->items()->get();
        $linesKey = $this->linesKey($lines);
        $requestKey = $this->requestKey(
            $linesKey, $current, $paymentMethodKey, $shippingMethodKey, $shippingAddress,
            $billingAddress, $comment, $courierComment
        );

        $previous = $this->repeatedPlacement($current, $linesKey, $requestKey, $lines->isEmpty());
        if ($previous !== null) {
            return $previous;
        }

        // A line switched off since it was added (its product or its
        // variant) is not for sale, whatever stock it shows. Asked first:
        // it is about the cart, whichever way the shopper pays.
        $unavailable = $cart->items()->with(['product', 'productVariant'])->get()
            ->filter(fn ($item) => $item->isUnavailable())
            ->map(fn ($item) => $item->name . ($item->variant_label ? ' (' . $item->variant_label . ')' : ''));

        if ($unavailable->isNotEmpty()) {
            throw new \InvalidArgumentException(
                'These items are no longer available: ' . $unavailable->implode(', ') . '. Please remove them from your cart before checkout.'
            );
        }

        // A saved address from before district and barangay were required
        // (a Manila address that skipped its district, say) cannot ship.
        // Refused out loud, so the customer edits it instead of a parcel
        // leaving without a barangay.
        $rules = app(\App\Services\Address\DeepAddressRules::class);
        $addressProblem = $rules->checkoutMessage($shippingAddress->country_id, $shippingAddress->only(['state', 'city', 'district', 'barangay']))
            ?? ($billingAddress ? $rules->checkoutMessage($billingAddress['country_id'] ?? null, $billingAddress, 'billing') : null);

        if ($addressProblem) {
            throw new \InvalidArgumentException($addressProblem);
        }

        $paymentMethod = $this->getPaymentMethod($paymentMethodKey);
        if (!$paymentMethod) {
            throw new \InvalidArgumentException('Invalid payment method.');
        }

        $items = $cart->items()->with(['product.specials', 'productVariant'])->get();
        if ($items->isEmpty()) {
            throw new \InvalidArgumentException('Cart is empty.');
        }

        // A line is charged what core prices it at now, never what it cost
        // when it was added: a special may have ended, or the merchant may
        // have changed a price. Any difference, up or down, stops the order
        // so the shopper sees the new total before paying it. Asked before
        // the subtotal, so shipping, payment rules and coupons below are
        // all worked out on prices that are current.
        $customerGroupId = $customer->customer_group_id;
        $changes = $this->linePriceChanges($items, $customerGroupId);
        if ($changes !== []) {
            $this->refuseForPriceChanges($changes);
        }

        // Block checkout if cart contains out-of-stock items
        $blockOutOfStock = \App\Models\Setting::where('group', 'checkout')
            ->where('key', 'block_out_of_stock')
            ->value('value') ?? '1';

        if ($blockOutOfStock === '1') {
            $outOfStock = [];
            foreach ($items as $item) {
                $product = $item->product;
                if (!$product || !$product->track_stock) {
                    continue;
                }

                if ($item->productVariant) {
                    if ($item->productVariant->quantity < $item->quantity) {
                        $label = $item->name . ($item->variant_label ? ' (' . $item->variant_label . ')' : '');
                        $outOfStock[] = $label;
                    }
                } elseif ($product->quantity < $item->quantity) {
                    $outOfStock[] = $item->name;
                }
            }

            if (!empty($outOfStock)) {
                throw new \InvalidArgumentException(
                    'The following items are out of stock or have insufficient quantity: ' . implode(', ', $outOfStock) . '. Please update your cart before checkout.'
                );
            }
        }

        $subtotal = $items->sum(function ($item) {
            return $item->price * $item->quantity;
        });

        // The server alone decides shipping: the method, its name and its
        // cost come from a quote computed here for this cart and address,
        // never from the form. Resolved before the payment check so that
        // payment rules keyed on the shipping method see a real method.
        $shippingMethod = $this->resolveShippingQuote($shippingAddress, $items, (float) $subtotal, $shippingMethodKey);
        $shippingMethodKey = (string) $shippingMethod['key'];
        $shippingCost = (float) $shippingMethod['cost'];

        // Verify payment restrictions
        $addressData = [
            'state' => $shippingAddress->state,
            'city' => $shippingAddress->city,
            'country_id' => $shippingAddress->country_id ?? null,
        ];
        $totalWeight = $items->sum(function ($item) {
            return ($item->product?->weight ?? 0) * $item->quantity;
        });
        $totalQuantity = $items->sum('quantity');
        $cartCategoryIds = $items->pluck('product.category_id')->filter()->unique()->values()->all();
        $cartBrandIds = $items->pluck('product.brand_id')->filter()->unique()->values()->all();

        if (!PaymentRestrictionService::isAllowed(
            $paymentMethodKey, $addressData, $subtotal,
            $totalWeight, $totalQuantity, $customerGroupId,
            $cartCategoryIds, $cartBrandIds, $shippingMethodKey,
            app(\App\Services\ThemeService::class)->getActiveCurrency()?->code
        )) {
            throw new \InvalidArgumentException('Selected payment method is not available for this order.');
        }

        // Order totals pipeline: every registered total extension
        // (coupon, store credit, ...) contributes its lines against this
        // context; a provider may also adjust the shipping cost (a
        // free-shipping coupon zeroes it). The mandatory arithmetic,
        // subtotal + shipping + lines, floored at zero, is
        // TotalsContext::total(), core and not disableable.
        $totalsCtx = \App\Services\OrderTotals\OrderTotals::collect(new \App\Services\OrderTotals\TotalsContext(
            $customer,
            $items,
            $subtotal,
            $shippingCost,
            ['cart' => $cart],
        ));
        $shippingCost = $totalsCtx->shippingCost;
        $total = $totalsCtx->total();
        $couponLine = $totalsCtx->line('coupon');

        // Build shipping meta from the matched provider
        $shippingMeta = null;
        foreach (app(\App\Services\Shipping\ShippingQuoteService::class)->getProviders() as $provider) {
            if (str_starts_with($shippingMethodKey, $provider->getKey() . '_') || $shippingMethodKey === $provider->getKey()) {
                $shippingMeta = $provider->buildShippingMeta($shippingMethodKey, $shippingMethod);
                break;
            }
        }

        // The method's own "Order status on checkout" (every method page
        // lists the statuses and nothing else; the merchant picks one and
        // saves). A page never saved falls back to the store's Default
        // status, the one the merchant marked with the radio on Order
        // Status and cannot delete: not a name, not a guess.
        $defaultStatus = \App\Services\PaymentManager::checkoutStatus($paymentMethod)
            ?? OrderStatus::where('store_id', $this->storeId)->where('is_default', true)->first();

        $lateChanges = [];
        $repeat = null;
        try {
            $order = DB::transaction(function () use (
                $customer, $shippingAddress, $billingAddress, $paymentMethodKey, $paymentMethod,
                $shippingMethod, $shippingMethodKey, $shippingMeta, $items, $subtotal, $shippingCost, $total,
                $comment, $cart, $totalsCtx, $couponLine, $courierComment, $customerGroupId, $linesKey, $requestKey,
                &$lateChanges, &$repeat
            ) {
                // The cart row is locked for the rest of the transaction, so
                // a second request for this cart waits here until this one
                // commits, and then reads the link written below.
                $locked = Cart::whereKey($cart->id)->lockForUpdate()->first();
                if (! $locked) {
                    throw new \InvalidArgumentException('Cart is empty.');
                }

                // Shipping quotes and coupons take time, and another request
                // for this cart may have placed its order meanwhile.
                $repeat = $this->repeatedPlacement($locked, $linesKey, $requestKey, false);
                if ($repeat !== null) {
                    return null;
                }

                // A special can end or a price can change meanwhile too. The
                // lines are priced once more; on any difference nothing is
                // written and the order is refused below.
                $lateChanges = $this->lateCartChanges($cart, $items, $customerGroupId);
                if ($lateChanges !== []) {
                    return null;
                }

                $order = $this->writeOrder(
                    $customer, $shippingAddress, $billingAddress, $paymentMethodKey, $paymentMethod,
                    $shippingMethod, $shippingMeta, $items, $subtotal, $shippingCost, $total,
                    $comment, $totalsCtx, $couponLine, $courierComment
                );

                // Link the cart to its order, only if the link is still the
                // one read under the lock. Zero rows means another request
                // linked it first: everything above is rolled back.
                $linked = Cart::whereKey($cart->id)
                    ->when(
                        $locked->placed_order_id === null,
                        fn ($query) => $query->whereNull('placed_order_id'),
                        fn ($query) => $query->where('placed_order_id', $locked->placed_order_id),
                    )
                    ->update([
                        'placed_order_id' => $order->id,
                        'placed_lines_key' => $linesKey,
                        'placed_request_key' => $requestKey,
                    ]);

                if ($linked !== 1) {
                    throw new CartLinkTakenException();
                }

                return $order;
            });
        } catch (CartLinkTakenException) {
            $order = null;
            $repeat = $this->repeatedPlacement(Cart::find($cart->id) ?? $cart, $linesKey, $requestKey, false)
                ?? throw new OrderAlreadyBeingPlacedException();
        }

        if ($repeat !== null) {
            return $repeat;
        }

        if ($order === null) {
            $this->refuseForPriceChanges($lateChanges);
        }

        try {
            $paymentResult = $paymentMethod->processPayment($order);
        } catch (\Throwable $e) {
            $this->releaseCart($order);
            throw $e;
        }

        // Failed API call (e.g. HitPay rejected): return without clearing
        // cart, and unlinked from it, so placing again makes a new order.
        if (!$paymentResult->success) {
            $this->releaseCart($order);
            return ['order' => $order, 'payment' => $paymentResult, 'duplicate' => false];
        }

        // Redirect-based payments (e.g. HitPay): webhook confirms and sets
        // status. The payment page is kept, so a repeated submit is sent
        // back to it rather than opening a second payment.
        if ($paymentResult->redirectUrl) {
            $order->forceFill(['payment_url' => $paymentResult->redirectUrl])->save();
            return ['order' => $order, 'payment' => $paymentResult, 'duplicate' => false];
        }

        // Immediate payments (e.g. bank transfer, COD): confirm order now
        $this->confirmOrder($order, $defaultStatus);
        $cart->items()->delete();

        return ['order' => $order, 'payment' => $paymentResult, 'duplicate' => false];
    }

    /**
     * Write the order and its lines, and run each total line's side effects
     * (burn the coupon use, deduct the credit). Called inside the order
     * transaction.
     */
    private function writeOrder(
        Customer $customer,
        CustomerAddress $shippingAddress,
        ?array $billingAddress,
        string $paymentMethodKey,
        PaymentMethodInterface $paymentMethod,
        array $shippingMethod,
        mixed $shippingMeta,
        Collection $items,
        float|int $subtotal,
        float|int $shippingCost,
        float|int $total,
        ?string $comment,
        \App\Services\OrderTotals\TotalsContext $totalsCtx,
        ?array $couponLine,
        ?string $courierComment
    ): Order {
        $orderData = [
            'store_id' => $this->storeId,
            'customer_id' => $customer->id,
            'order_status_id' => null,
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'shipping_first_name' => $shippingAddress->first_name,
            'shipping_last_name' => $shippingAddress->last_name,
            'shipping_address_1' => $shippingAddress->address_1,
            'shipping_city' => $shippingAddress->city,
            'shipping_district' => $shippingAddress->district,
            'shipping_barangay' => $shippingAddress->barangay,
            'shipping_state' => $shippingAddress->state,
            'shipping_postcode' => $shippingAddress->postcode,
            'shipping_country_id' => $shippingAddress->country_id,
            'shipping_phone' => $shippingAddress->phone,
            'payment_method' => $paymentMethodKey,
            'payment_method_title' => $paymentMethod?->getName(),
            'shipping_method' => $shippingMethod['name'],
            'shipping_meta' => $shippingMeta,
            'shipping_country_name' => \App\Models\Country::find($shippingAddress->country_id ?? null)?->name,
            'subtotal' => $subtotal,
            'shipping_cost' => $shippingCost,
            'total' => $total,
            'total_lines' => $totalsCtx->lines,
            'coupon_code' => $couponLine['extras']['code'] ?? null,
            'coupon_discount' => $couponLine ? -$couponLine['amount'] : 0,
            'comment' => $comment,
            'courier_comment' => $courierComment,
        ];

        if ($billingAddress) {
            $orderData = array_merge($orderData, [
                'billing_first_name' => $billingAddress['first_name'],
                'billing_last_name' => $billingAddress['last_name'],
                'billing_address_1' => $billingAddress['address_1'],
                'billing_city' => $billingAddress['city'],
                'billing_district' => $billingAddress['district'] ?? null,
                'billing_barangay' => $billingAddress['barangay'] ?? null,
                'billing_state' => $billingAddress['state'] ?? null,
                'billing_postcode' => $billingAddress['postcode'] ?? null,
                'billing_country_id' => $billingAddress['country_id'],
                'billing_country_name' => \App\Models\Country::find($billingAddress['country_id'] ?? null)?->name,
            ]);
        }

        $order = Order::create($orderData);

        foreach ($items as $item) {
            OrderProduct::create([
                'order_id' => $order->id,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'name' => $item->name,
                'sku' => $item->sku,
                // The variant's OWN photo when it has one. A colourway is
                // what the buyer chose and what the packer pulls off the
                // shelf, so the line has to carry that picture and not the
                // parent's. Falls back to the product for a variant with no
                // photo of its own, and for a product with no variants.
                'image' => $item->productVariant?->image ?: $item->product?->image,
                'variant_label' => $item->variant_label,
                'price' => $item->price,
                'quantity' => $item->quantity,
                'total' => $item->price * $item->quantity,
                'is_custom' => false,
            ]);
        }

        // Each contributed line's side effects (burn the coupon use,
        // deduct the credit) run here, inside the transaction.
        \App\Services\OrderTotals\OrderTotals::confirm($order, $totalsCtx->lines);

        return $order;
    }

    /**
     * The lines whose stored price is not what core prices them at now.
     *
     * @return array<int, array{item_id: int, label: string, was: float, now: float}>
     */
    private function linePriceChanges(Collection $items, ?int $customerGroupId): array
    {
        $changes = [];

        foreach ($items as $item) {
            $now = CartService::currentLinePrice($item, $customerGroupId);
            if ($now === null || CartService::samePrice((float) $item->price, $now)) {
                continue;
            }

            $changes[] = [
                'item_id' => (int) $item->id,
                'label' => $item->name . ($item->variant_label ? ' (' . $item->variant_label . ')' : ''),
                'was' => (float) $item->price,
                'now' => $now,
            ];
        }

        return $changes;
    }

    /**
     * Save the current prices on the cart lines, then refuse the order.
     * The cart and checkout pages read the saved prices, so the shopper's
     * next look shows the new total.
     *
     * @param  array<int, array{item_id: int, label: string, was: float, now: float}>  $changes
     */
    private function refuseForPriceChanges(array $changes): never
    {
        foreach ($changes as $change) {
            CartItem::whereKey($change['item_id'])->update(['price' => $change['now']]);
        }

        throw new CartPricesChangedException($changes);
    }

    /**
     * Run inside the order transaction, with the cart locked: read its
     * lines again and price them again. Returns the price changes found, empty
     * when the order may be written. A cart whose lines or quantities
     * moved since this request read them is refused outright, because the
     * order copies the lines it read.
     *
     * @return array<int, array{item_id: int, label: string, was: float, now: float}>
     */
    private function lateCartChanges(Cart $cart, Collection $items, ?int $customerGroupId): array
    {
        $fresh = $cart->items()->with(['product.specials', 'productVariant'])->get()->keyBy('id');

        $sameLines = $fresh->count() === $items->count()
            && $items->every(fn ($item) => $fresh->has($item->id)
                && (int) $fresh[$item->id]->quantity === (int) $item->quantity);

        if (! $sameLines) {
            throw new \InvalidArgumentException('Your cart changed while the order was being placed. Please review it and try again.');
        }

        // Each freshly read line, carrying the price the subtotal above
        // was built from, against what core prices it at now.
        $lines = $items->map(function ($item) use ($fresh) {
            $line = $fresh[$item->id];
            $line->price = $item->price;

            return $line;
        });

        return $this->linePriceChanges($lines, $customerGroupId);
    }

    /**
     * The earlier placement this request repeats, answered with that
     * order, or null when this request should place one.
     *
     * - An order that is placed (it has a status) is repeated by any submit
     *   while the cart still holds the lines it was made from, or is empty
     *   because placing it emptied it. One cart's lines become one order.
     * - An unpaid order waiting on its payment page is repeated only by the
     *   same submit (same lines, same choices): the shopper is sent back to
     *   that page. A shopper who went back and changed the address or the
     *   payment method is placing a new order, as before.
     * - An unpaid order with no payment page yet is still being placed by
     *   another request, and this one is refused until that one finishes.
     *
     * Each answer holds only for a short while after the order was made,
     * so an order left behind (a placement that died, a payment abandoned
     * long ago) never keeps the cart from placing again.
     *
     * @return array{order: Order, payment: PaymentResult, duplicate: bool}|null
     */
    private function repeatedPlacement(Cart $cart, string $linesKey, string $requestKey, bool $cartEmpty): ?array
    {
        if (! $cart->placed_order_id) {
            return null;
        }

        $order = $this->linkedOrder($cart);
        if (! $order) {
            return null;
        }

        if ($order->order_status_id !== null) {
            $repeats = $this->withinRepeatWindow($order)
                && ($cartEmpty || hash_equals((string) $cart->placed_lines_key, $linesKey));

            return $repeats
                ? ['order' => $order, 'payment' => new PaymentResult(success: true), 'duplicate' => true]
                : null;
        }

        if ($order->payment_url === null) {
            $stillPlacing = $order->created_at
                && $order->created_at->gt(now()->subSeconds((int) config('checkout.placement.lock_seconds', 90)));

            if ($stillPlacing) {
                throw new OrderAlreadyBeingPlacedException();
            }

            return null;
        }

        $repeats = ! $cartEmpty
            && $this->withinRepeatWindow($order)
            && hash_equals((string) $cart->placed_request_key, $requestKey);

        return $repeats
            ? ['order' => $order, 'payment' => new PaymentResult(success: true, redirectUrl: $order->payment_url), 'duplicate' => true]
            : null;
    }

    /** The order the cart is linked to, when it is this cart's customer's own. */
    private function linkedOrder(Cart $cart): ?Order
    {
        return Order::whereKey($cart->placed_order_id)
            ->where('store_id', $cart->store_id)
            ->where('customer_id', $cart->customer_id)
            ->first();
    }

    private function withinRepeatWindow(Order $order): bool
    {
        return $order->created_at !== null
            && $order->created_at->gt(now()->subSeconds((int) config('checkout.placement.repeat_window_seconds', 600)));
    }

    /**
     * A fingerprint of the cart's lines: which lines, how many of each and
     * at what price. A line added, removed, changed or re-added afterwards
     * gives a different fingerprint.
     */
    private function linesKey(Collection $lines): string
    {
        $parts = $lines
            ->sortBy('id')
            ->map(fn (CartItem $line) => [
                (int) $line->id,
                (int) $line->product_id,
                $line->product_variant_id === null ? null : (int) $line->product_variant_id,
                (int) $line->quantity,
                number_format((float) $line->price, 2, '.', ''),
                $line->created_at?->getTimestamp(),
            ])
            ->values()
            ->all();

        return hash('sha256', json_encode($parts));
    }

    /**
     * A fingerprint of one Place order submit: the cart's lines and every
     * choice the shopper made on the checkout page.
     */
    private function requestKey(
        string $linesKey,
        Cart $cart,
        string $paymentMethodKey,
        string $shippingMethodKey,
        CustomerAddress $shippingAddress,
        ?array $billingAddress,
        ?string $comment,
        ?string $courierComment
    ): string {
        if ($billingAddress !== null) {
            ksort($billingAddress);
        }

        return hash('sha256', json_encode([
            $linesKey,
            $paymentMethodKey,
            $shippingMethodKey,
            (int) $shippingAddress->id,
            $billingAddress,
            (string) $comment,
            (string) $courierComment,
            (string) $cart->coupon_code,
        ]));
    }

    /**
     * Confirm an order: sets status, creates history, adjusts stock, sends emails.
     * Called when payment is confirmed (immediately for Bank/COD, via webhook for HitPay).
     */
    public function confirmOrder(Order $order, ?OrderStatus $status = null): void
    {
        $status = $status ?? OrderStatus::where('store_id', $order->store_id)
            ->where('is_default', true)
            ->first();

        if ($status) {
            OrderStatusChanger::apply(
                $order,
                $status->id,
                'Order confirmed.',
                // The confirmation email below is this method's own; a
                // status email would say the same thing twice in the same
                // second. Channels still follow the store's ticks, so a
                // store that texts on its first status does. apply() stamps
                // notify_customer true regardless, because this email IS
                // how the customer was told.
                NotifyChoice::emailHandledElsewhere(),
            );
        }

        $order->load('products', 'status');

        ActivityLogger::log(
            $order->customer_id,
            'order_placed',
            "Placed order #{$order->id} for " . number_format($order->total, 2),
            $order,
            ['total' => $order->total]
        );

        // A mail failure must never abort a confirmed order
        try {
            self::sendOrderConfirmationEmail($order);
        } catch (\Throwable $e) {
            Log::error("Order #{$order->id}: confirmation email failed", ['error' => $e->getMessage()]);
        }

        try {
            self::sendAdminOrderNotification($order);
        } catch (\Throwable $e) {
            Log::error("Order #{$order->id}: admin notification failed", ['error' => $e->getMessage()]);
        }
    }

    public static function sendOrderConfirmationEmail(Order $order): void
    {
        $order->loadMissing('products.product');
        $currency = (string) app(ThemeService::class)->getDefaultCurrency()?->symbol;

        $itemsHtml = self::buildOrderItemsHtml($order, $currency);
        $addressHtml = self::buildShippingAddressHtml($order);
        $shippingCostLabel = $order->shipping_cost > 0
            ? $currency . number_format($order->shipping_cost, 2)
            : '<span style="color: #4ade80;">FREE</span>';

        $sent = TemplateMailService::send('order_confirmation', $order->email, [
            'customer_name' => $order->first_name . ' ' . $order->last_name,
            'order_id' => $order->id,
            'subtotal' => $currency . number_format($order->subtotal, 2),
            'shipping_method' => $order->courier_label,
            'shipping_cost' => $shippingCostLabel,
            'order_total' => $currency . number_format($order->total, 2),
            'order_items' => $itemsHtml,
            'shipping_address' => $addressHtml,
            'payment_method' => $order->payment_method_label,
            'payment_details' => self::buildPaymentDetailsHtml($order),
            'comment' => self::buildCommentHtml($order, 'Order Note'),
            'store_name' => config('app.name'),
        ]);

        // Fallback to Blade mailable if template is disabled or missing
        if (!$sent) {
            try {
                Mail::to($order->email)->send(new \App\Mail\OrderConfirmationMail($order));
            } catch (\Throwable $e) {
                Log::warning('Order confirmation email failed: ' . $e->getMessage());
            }
        }
    }

    public static function sendAdminOrderNotification(Order $order): void
    {
        // New-order alerts go to the store's own email (Settings > General).
        $adminEmail = \App\Support\StoreIdentity::email();
        if (!$adminEmail) return;

        $order->loadMissing('products.product');
        $currency = (string) app(ThemeService::class)->getDefaultCurrency()?->symbol;

        $itemsHtml = self::buildOrderItemsHtml($order, $currency);
        $addressHtml = self::buildShippingAddressHtml($order);
        $shippingCostLabel = $order->shipping_cost > 0
            ? $currency . number_format($order->shipping_cost, 2)
            : '<span style="color: #4ade80;">FREE</span>';

        TemplateMailService::send('new_order_admin', $adminEmail, [
            'order_id' => $order->id,
            'customer_name' => $order->first_name . ' ' . $order->last_name,
            'customer_email' => $order->email,
            'payment_method' => $order->payment_method_label,
            'subtotal' => $currency . number_format($order->subtotal, 2),
            'shipping_method' => $order->courier_label,
            'shipping_cost' => $shippingCostLabel,
            'order_total' => $currency . number_format($order->total, 2),
            'order_items' => $itemsHtml,
            'shipping_address' => $addressHtml,
            'comment' => self::buildCommentHtml($order, 'Customer Note'),
        ]);
    }

    private static function buildPaymentDetailsHtml(Order $order): string
    {
        $label = $order->payment_method_label;
        $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 16px 0;">'
            . '<tr><td style="padding: 16px 20px; background-color: #222222; border-radius: 6px;">'
            . '<p style="margin: 0 0 6px 0; font-size: 11px; font-weight: 700; color: #999; text-transform: uppercase; letter-spacing: 1px;">Payment Method</p>'
            . '<p style="margin: 0; font-size: 14px; color: #e8e8e8;">' . e($label) . '</p>';

        if (str_starts_with($order->payment_method, 'bank_transfer')) {
            $bankAcct = null;
            if (preg_match('/^bank_transfer_(\d+)$/', $order->payment_method, $m)) {
                $bankAcct = class_exists(\Extensions\PaymentBankTransfer\Models\BankAccount::class) ? \Extensions\PaymentBankTransfer\Models\BankAccount::find((int) $m[1]) : null;
            }
            if ($bankAcct) {
                $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top: 12px; border-top: 1px solid #333;">'
                    . '<tr><td style="padding: 8px 0 2px 0; font-size: 13px; color: #999;">Bank:</td>'
                    . '<td style="padding: 8px 0 2px 0; font-size: 13px; color: #e8e8e8; text-align: right;">' . e($bankAcct->bank_name) . '</td></tr>'
                    . '<tr><td style="padding: 2px 0; font-size: 13px; color: #999;">Account Name:</td>'
                    . '<td style="padding: 2px 0; font-size: 13px; color: #e8e8e8; text-align: right;">' . e($bankAcct->account_name) . '</td></tr>'
                    . '<tr><td style="padding: 2px 0 8px 0; font-size: 13px; color: #999;">Account Number:</td>'
                    . '<td style="padding: 2px 0 8px 0; font-size: 13px; color: #e8e8e8; text-align: right;">' . e($bankAcct->account_number) . '</td></tr>'
                    . '<tr><td colspan="2" style="padding: 8px 0 0 0; font-size: 13px; color: #999; border-top: 1px solid #333;">Instructions:</td></tr>'
                    . '<tr><td colspan="2" style="padding: 4px 0 0 0; font-size: 13px; color: #e8e8e8; line-height: 1.5;">'
                    . ($bankAcct->instructions ? nl2br(e($bankAcct->instructions)) : '<span style="color: #666;">-</span>')
                    . '</td></tr></table>';

                if ($bankAcct->qr_image) {
                    $qrUrl = url('/storage/' . $bankAcct->qr_image);
                    $html .= '<div style="margin-top: 12px; text-align: center;">'
                        . '<img src="' . e($qrUrl) . '" alt="QR Code" style="width: 100%; max-width: 240px; height: auto; border-radius: 6px; display: inline-block;">'
                        . '</div>';
                }
            }
        }

        $html .= '</td></tr></table>';
        return $html;
    }

    private static function buildCommentHtml(Order $order, string $heading = 'Order Note'): string
    {
        $noteText = $order->comment
            ? nl2br(e($order->comment))
            : '<span style="color: #666;">-</span>';

        $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 12px 0; background-color: #222222; border-radius: 6px;">'
            . '<tr><td style="padding: 16px 20px;">'
            . '<p style="margin: 0 0 6px 0; font-size: 11px; font-weight: 700; color: #999; text-transform: uppercase; letter-spacing: 1px;">' . e($heading) . '</p>'
            . '<p style="margin: 0; font-size: 13px; color: #e8e8e8; line-height: 1.5;">' . $noteText . '</p>'
            . '</td></tr></table>';

        // The courier message rides the same {comment} placeholder so no
        // email template changes, and only appears when one was written.
        if ($order->courier_comment) {
            $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 12px 0; background-color: #222222; border-radius: 6px;">'
                . '<tr><td style="padding: 16px 20px;">'
                . '<p style="margin: 0 0 6px 0; font-size: 11px; font-weight: 700; color: #999; text-transform: uppercase; letter-spacing: 1px;">Courier Note</p>'
                . '<p style="margin: 0; font-size: 13px; color: #e8e8e8; line-height: 1.5;">' . nl2br(e($order->courier_comment)) . '</p>'
                . '</td></tr></table>';
        }

        return $html;
    }

    private static function buildOrderItemsHtml(Order $order, string $currency): string
    {
        $html = '';
        foreach ($order->products as $item) {
            $imgHtml = '';
            if ($item->image) {
                $imgUrl = url('/storage/' . $item->image);
                $imgHtml = '<td style="width: 50px; padding: 4px 8px 4px 0; vertical-align: middle;">'
                    . '<img src="' . e($imgUrl) . '" alt="" width="50" height="50" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px; display: block;">'
                    . '</td>';
            }

            $textHtml = e($item->name)
                . ($item->variant_label ? ' (' . e($item->variant_label) . ')' : '')
                . ' &times; ' . $item->quantity
                . ' = ' . $currency . number_format($item->total, 2);

            $html .= '<table cellpadding="0" cellspacing="0" border="0" style="margin: 2px 0; width: 100%;"><tr>'
                . $imgHtml
                . '<td style="font-size: 13px; color: #ccc; vertical-align: middle;">' . $textHtml . '</td>'
                . '</tr></table>';
        }
        return $html;
    }

    private static function buildShippingAddressHtml(Order $order): string
    {
        $lines = [];
        $lines[] = e($order->shipping_first_name . ' ' . $order->shipping_last_name);
        if ($order->shipping_address_1) {
            $lines[] = e($order->shipping_address_1);
        }
        // Extension fields (e.g. PH address): rendered only if populated
        if ($order->shipping_barangay) {
            $lines[] = e($order->shipping_barangay);
        }
        $cityLine = '';
        if ($order->shipping_district) {
            $cityLine = e($order->shipping_district) . ', ';
        }
        $cityLine .= e($order->shipping_city ?? '');
        if ($cityLine) {
            $lines[] = $cityLine;
        }
        $stateLine = '';
        if ($order->shipping_state) {
            $stateLine .= e($order->shipping_state);
        }
        if ($order->shipping_postcode) {
            $stateLine .= ($stateLine ? ' ' : '') . e($order->shipping_postcode);
        }
        if ($stateLine) {
            $lines[] = $stateLine;
        }
        $country = $order->shipping_country_id ? \App\Models\Country::find($order->shipping_country_id) : null;
        $countryName = $order->shipping_country_name ?? $country?->name;
        if ($countryName) {
            $lines[] = e($countryName);
        }
        if ($order->shipping_phone) {
            $phoneCode = '';
            if ($country && $country->phone_code) {
                $phoneCode = '+' . $country->phone_code . ' ';
            }
            $lines[] = 'Phone: ' . $phoneCode . e($order->shipping_phone);
        }
        return implode('<br>', $lines);
    }
}
