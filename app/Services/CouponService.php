<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CouponService
{
    /**
     * Validate and calculate a coupon against the current cart context.
     */
    public function validate(string $code, ?Customer $customer, Collection $cartItems, float $subtotal, float $shippingCost = 0): CouponResult
    {
        $coupon = Coupon::where('code', strtoupper(trim($code)))->first();

        if (!$coupon) {
            return CouponResult::fail('Coupon code not found.');
        }

        if (!$coupon->is_active) {
            return CouponResult::fail('This coupon is no longer active.');
        }

        // Date range
        $now = now()->startOfDay();
        if ($coupon->date_start && $now->lt($coupon->date_start)) {
            return CouponResult::fail('This coupon is not yet valid.');
        }
        if ($coupon->date_end && $now->gt($coupon->date_end)) {
            return CouponResult::fail('This coupon has expired.');
        }

        // Customer eligibility
        if ($coupon->customer_eligibility === 'logged_in' && !$customer) {
            return CouponResult::fail('You must be logged in to use this coupon.');
        }
        if ($coupon->customer_eligibility === 'guest' && $customer) {
            return CouponResult::fail('This coupon is for guest checkout only.');
        }

        // Customer group
        if ($coupon->customer_group_id !== null) {
            $customerGroupId = $customer?->customer_group_id;
            if ($customerGroupId === null || (int) $customerGroupId !== (int) $coupon->customer_group_id) {
                return CouponResult::fail('This coupon is not available for your account.');
            }
        }

        // Minimum order total
        if ($coupon->min_order_total && $subtotal < (float) $coupon->min_order_total) {
            $min = number_format((float) $coupon->min_order_total, 2);
            return CouponResult::fail("Minimum order total of ₱{$min} required.");
        }

        // Global usage limit
        if ($coupon->usage_limit !== null && $coupon->times_used >= $coupon->usage_limit) {
            return CouponResult::fail('This coupon has reached its usage limit.');
        }

        // Per-customer usage limit (null = unlimited)
        if ($customer && $coupon->usage_per_customer !== null) {
            $customerUsage = CouponUsage::where('coupon_id', $coupon->id)
                ->where('customer_id', $customer->id)
                ->count();
            if ($customerUsage >= $coupon->usage_per_customer) {
                return CouponResult::fail('You have already used this coupon.');
            }
        }

        // Product/category/brand restrictions
        $restrictions = $coupon->restrictions;
        if ($restrictions->isNotEmpty()) {
            $qualifies = $this->checkRestrictions($restrictions, $cartItems);
            if (!$qualifies) {
                return CouponResult::fail('This coupon does not apply to any items in your cart.');
            }
        }

        // Calculate discount
        $discount = $this->calculate($coupon, $cartItems, $subtotal, $shippingCost);
        $freeShipping = $coupon->type === 'free_shipping' || $coupon->free_shipping;

        return CouponResult::ok($coupon, $discount, $freeShipping);
    }

    /**
     * Calculate the actual discount amount.
     */
    public function calculate(Coupon $coupon, Collection $cartItems, float $subtotal, float $shippingCost = 0): float
    {
        // Get qualifying subtotal (restricted items only, or full subtotal)
        $qualifyingSubtotal = $this->getQualifyingSubtotal($coupon, $cartItems, $subtotal);

        $discount = match ($coupon->type) {
            'percent' => $qualifyingSubtotal * ((float) $coupon->discount_value / 100),
            'fixed' => min((float) $coupon->discount_value, $qualifyingSubtotal),
            'free_shipping' => $shippingCost,
        };

        // Apply max discount cap for percent type
        if ($coupon->type === 'percent' && $coupon->max_discount !== null) {
            $discount = min($discount, (float) $coupon->max_discount);
        }

        // If coupon also gives free shipping (on top of percent/fixed discount)
        if ($coupon->type !== 'free_shipping' && $coupon->free_shipping) {
            $discount += $shippingCost;
        }

        // Never exceed the order total
        $discount = min($discount, $subtotal + $shippingCost);

        return round($discount, 2);
    }

    /**
     * Apply a coupon to a cart (persists the code on the cart record).
     */
    public function apply(string $code, Cart $cart, ?Customer $customer, Collection $cartItems, float $subtotal, float $shippingCost = 0): CouponResult
    {
        $result = $this->validate($code, $customer, $cartItems, $subtotal, $shippingCost);

        if ($result->success) {
            $cart->update(['coupon_code' => $result->coupon->code]);
        }

        return $result;
    }

    /**
     * Remove coupon from cart.
     */
    public function remove(Cart $cart): void
    {
        $cart->update(['coupon_code' => null]);
    }

    /**
     * Record coupon redemption. Call inside the order creation transaction.
     * Uses atomic increment to prevent race conditions on usage limits.
     */
    public function redeem(Coupon $coupon, ?Customer $customer, Order $order, float $discountAmount): void
    {
        CouponUsage::create([
            'coupon_id' => $coupon->id,
            'customer_id' => $customer?->id,
            'order_id' => $order->id,
            'discount_amount' => $discountAmount,
        ]);

        // Atomic increment — prevents race condition on concurrent redemptions
        DB::table('coupons')
            ->where('id', $coupon->id)
            ->increment('times_used');
    }

    /**
     * Generate a unique coupon code.
     */
    public static function generateCode(string $prefix = '', int $length = 8): string
    {
        do {
            $code = strtoupper($prefix . ($prefix ? '-' : '') . Str::random($length));
        } while (Coupon::where('code', $code)->exists());

        return $code;
    }

    /**
     * Check if cart items satisfy product/category/brand restrictions.
     */
    protected function checkRestrictions(Collection $restrictions, Collection $cartItems): bool
    {
        $productIds = $restrictions->where('restriction_type', 'product')->pluck('restriction_id')->toArray();
        $categoryIds = $restrictions->where('restriction_type', 'category')->pluck('restriction_id')->toArray();
        $brandIds = $restrictions->where('restriction_type', 'brand')->pluck('restriction_id')->toArray();

        foreach ($cartItems as $item) {
            $product = $item->product;
            if (!$product) {
                continue;
            }

            // Product match
            if ($productIds && in_array($product->id, $productIds)) {
                return true;
            }

            // Category match
            if ($categoryIds) {
                $itemCategoryIds = $product->categories ? $product->categories->pluck('id')->toArray() : [];
                if (array_intersect($categoryIds, $itemCategoryIds)) {
                    return true;
                }
            }

            // Brand match
            if ($brandIds && $product->brand_id && in_array($product->brand_id, $brandIds)) {
                return true;
            }
        }

        // If restrictions exist but nothing matched
        return false;
    }

    /**
     * Get the subtotal of items that qualify under the coupon's restrictions.
     * If no restrictions, full subtotal qualifies.
     */
    protected function getQualifyingSubtotal(Coupon $coupon, Collection $cartItems, float $fullSubtotal): float
    {
        $restrictions = $coupon->restrictions;

        if ($restrictions->isEmpty()) {
            return $fullSubtotal;
        }

        $productIds = $restrictions->where('restriction_type', 'product')->pluck('restriction_id')->toArray();
        $categoryIds = $restrictions->where('restriction_type', 'category')->pluck('restriction_id')->toArray();
        $brandIds = $restrictions->where('restriction_type', 'brand')->pluck('restriction_id')->toArray();

        $qualifying = 0;

        foreach ($cartItems as $item) {
            $product = $item->product;
            if (!$product) {
                continue;
            }

            $matches = false;

            if ($productIds && in_array($product->id, $productIds)) {
                $matches = true;
            }

            if (!$matches && $categoryIds) {
                $itemCategoryIds = $product->categories ? $product->categories->pluck('id')->toArray() : [];
                if (array_intersect($categoryIds, $itemCategoryIds)) {
                    $matches = true;
                }
            }

            if (!$matches && $brandIds && $product->brand_id && in_array($product->brand_id, $brandIds)) {
                $matches = true;
            }

            if ($matches || (!$productIds && !$categoryIds && !$brandIds)) {
                $qualifying += $item->price * $item->quantity;
            }
        }

        return $qualifying;
    }
}
