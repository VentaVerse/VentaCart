<?php

namespace Extensions\Coupons;

use App\Models\Cart;
use App\Models\Order;
use App\Services\OrderTotals\OrderTotalProviderInterface;
use App\Services\OrderTotals\TotalsContext;
use Extensions\Coupons\Models\Coupon;
use Extensions\Coupons\Services\CouponService;

/**
 * The coupon line in the totals pipeline. Validation is called WITHOUT
 * the shipping cost, exactly like the storefront apply/preview path: a
 * free-shipping coupon zeroes the context's shipping cost and the
 * discount line carries only the non-shipping part. (The pre-pipeline
 * checkout both zeroed shipping AND subtracted a discount that included
 * it, so free-shipping coupons charged less than the preview showed.)
 */
class CouponTotalProvider implements OrderTotalProviderInterface
{
    public function getKey(): string
    {
        return 'coupon';
    }

    public function collect(TotalsContext $ctx): void
    {
        $cart = $ctx->input['cart'] ?? null;
        if (! $cart || ! $cart->coupon_code) {
            return;
        }

        $items = $cart->items()->with(['product.categories', 'productVariant'])->get();
        $result = app(CouponService::class)->validate($cart->coupon_code, $ctx->customer, $items, $ctx->subtotal);

        if (! $result->success) {
            // No longer valid at placement time: drop it silently and
            // charge full price, same posture as before the pipeline.
            $cart->update(['coupon_code' => null]);

            return;
        }

        if ($result->freeShipping) {
            $ctx->shippingCost = 0.0;
        }

        $ctx->addLine('coupon', 'Coupon (' . $result->coupon->code . ')', -$result->discount, [
            'code' => $result->coupon->code,
            'coupon_id' => $result->coupon->id,
            'cart_id' => $cart->id,
            'free_shipping' => $result->freeShipping,
        ]);
    }

    public function confirm(Order $order, array $line): void
    {
        $coupon = Coupon::find($line['extras']['coupon_id'] ?? null);
        if ($coupon) {
            app(CouponService::class)->redeem($coupon, $order->customer, $order, -$line['amount']);
        }

        if ($cartId = $line['extras']['cart_id'] ?? null) {
            Cart::where('id', $cartId)->update(['coupon_code' => null]);
        }
    }
}
