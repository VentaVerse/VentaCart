<?php

namespace Themes\Xelga\Sections;

use App\Services\CartService;
use App\Support\PaymentFamilies;
use Illuminate\Support\Facades\Route;
use App\Support\SchemaState;

/**
 * What the cart page and the cart drawer say around the lines, read from
 * core and its extensions without changing anything: the coupon on the
 * cart (as core's checkout would accept it), the amount that earns free
 * shipping, whether cash on delivery is on, and where checkout starts.
 */
final class CartSummary
{
    /** Whether shoppers may enter a coupon here: the coupons extension is on and they are signed in. */
    public static function couponsOn(): bool
    {
        return Route::has('checkout.coupon.apply');
    }

    public static function signedIn(): bool
    {
        return auth('customer')->check();
    }

    /**
     * The coupon on this shopper's cart, checked the way core's checkout
     * checks it. A coupon that no longer applies is simply not shown; the
     * checkout clears it.
     *
     * @return array{code: string, discount: float, free_shipping: bool}|null
     */
    public static function coupon(float $subtotal): ?array
    {
        $customer = auth('customer')->user();
        if (! self::couponsOn() || $customer === null || ! class_exists(\Extensions\Coupons\Services\CouponService::class)) {
            return null;
        }

        try {
            $cart = app(CartService::class)->findCart();
            if (! $cart || ! $cart->coupon_code) {
                return null;
            }
            $items = $cart->items()->with(['product.categories', 'productVariant'])->get();
            $result = app(\Extensions\Coupons\Services\CouponService::class)->validate($cart->coupon_code, $customer, $items, $subtotal);
            if (! $result->success) {
                return null;
            }

            return ['code' => (string) $result->coupon->code, 'discount' => (float) $result->discount, 'free_shipping' => (bool) $result->freeShipping];
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * The order total that earns free shipping for everyone, from the free
     * shipping extension's rules. Only a rule with no other condition (no
     * group, zone, brand, category or quantity limit) gives a plain
     * threshold a page can promise; with none, the note stays hidden.
     */
    public static function freeShippingFrom(): ?float
    {
        $model = 'Extensions\\ShippingFree\\Models\\FreeShippingRule';
        try {
            if (! class_exists($model) || ! SchemaState::hasTable('free_shipping_rules')) {
                return null;
            }
            $lowest = null;
            foreach ($model::where('is_active', true)->get() as $rule) {
                $limited = ! empty($rule->min_quantity)
                    || ! empty($rule->brand_ids) || ! empty($rule->category_ids)
                    || ! empty($rule->customer_group_ids) || ! empty($rule->shipping_zone_ids)
                    || ! empty($rule->exclude_brand_ids) || ! empty($rule->exclude_category_ids)
                    || ! empty($rule->exclude_customer_group_ids);
                $min = (float) ($rule->min_order_total ?? 0);
                if (! $limited && $min > 0) {
                    $lowest = $lowest === null ? $min : min($lowest, $min);
                }
            }

            return $lowest;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    public static function codOn(): bool
    {
        return isset(PaymentFamilies::enabledFamilies()['cod']);
    }

    /** @return list<string> the names of the payment methods switched on */
    public static function payments(): array
    {
        return array_values(array_map(fn (array $f) => (string) $f['default_name'], PaymentFamilies::enabledFamilies()));
    }

    /** Where Checkout leads: the checkout for a signed-in shopper, else sign in first (core has no guest checkout). */
    public static function checkoutUrl(): ?string
    {
        if (self::signedIn()) {
            return Route::has('checkout.index') ? route('checkout.index') : null;
        }

        return Route::has('customer.login') ? route('customer.login', ['redirect' => 'checkout']) : null;
    }

    /** The addresses the cart drawer script posts to, all core routes. */
    public static function endpoints(): array
    {
        $pattern = static fn (string $name) => Route::has($name) ? str_replace('/0', '/{id}', route($name, ['cartItem' => 0], false)) : null;

        return [
            'mini' => Route::has('cart.mini') ? route('cart.mini', [], false) : null,
            'update' => $pattern('cart.update'),
            'remove' => $pattern('cart.remove'),
            'coupon_apply' => self::couponsOn() ? route('checkout.coupon.apply', [], false) : null,
            'coupon_remove' => Route::has('checkout.coupon.remove') ? route('checkout.coupon.remove', [], false) : null,
            'cart' => Route::has('cart.index') ? route('cart.index', [], false) : null,
            'checkout' => self::checkoutUrl(),
            'login' => Route::has('customer.login') ? route('customer.login', [], false) : null,
            'estimate' => Route::has('cart.estimate-shipping') ? route('cart.estimate-shipping', [], false) : null,
            'countries' => Route::has('storefront.address.countries') ? route('storefront.address.countries', [], false) : null,
            'zones' => Route::has('storefront.address.zones') ? route('storefront.address.zones', [], false) : null,
            'providers' => Route::has('storefront.address.providers') ? route('storefront.address.providers', [], false) : null,
        ];
    }
}
