<?php

namespace Extensions\Coupons\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use Extensions\Coupons\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StorefrontCouponController extends Controller
{
    public function __construct(
        private CartService $cartService,
        private CouponService $couponService,
    ) {}

    public function apply(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'max:50'],
        ]);

        $customer = Auth::guard('customer')->user();
        $cart = $this->cartService->findCart();
        $items = $cart ? $cart->items()->with(['product.categories', 'productVariant'])->get() : collect();

        if (!$cart || $items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Your cart is empty.',
            ], 422);
        }

        $subtotal = $items->sum(fn($item) => $item->price * $item->quantity);

        $result = $this->couponService->apply(
            $request->input('code'),
            $cart,
            $customer,
            $items,
            $subtotal
        );

        if (!$result->success) {
            return response()->json([
                'success' => false,
                'message' => $result->message,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result->message,
            'discount' => $result->discount,
            'free_shipping' => $result->freeShipping,
            'coupon_code' => $result->coupon->code,
        ]);
    }

    public function remove(Request $request): JsonResponse
    {
        $cart = $this->cartService->findCart();
        if ($cart) {
            $this->couponService->remove($cart);
        }

        return response()->json([
            'success' => true,
            'message' => 'Coupon removed.',
        ]);
    }
}
