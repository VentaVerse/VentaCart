<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\CustomerAddress;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\CouponService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckoutController extends Controller
{
    public function __construct(
        private CartService $cartService,
        private CheckoutService $checkoutService,
        private CouponService $couponService,
    ) {}

    public function index()
    {
        $customer = Auth::guard('customer')->user();
        $items = $this->cartService->getItems();

        if ($items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $addresses = $customer->addresses()->latest()->get();
        $defaultAddress = $customer->addresses()->where('is_default', true)->first()
            ?? $addresses->first();
        $countries = Country::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'iso2', 'name', 'phone_code']);

        $subtotal = $this->cartService->getSubtotal();

        $quoteService = app(\App\Services\Shipping\ShippingQuoteService::class);
        if ($defaultAddress) {
            $shippingMethods = $quoteService->getQuotes([
                'province' => $defaultAddress->state,
                'city' => $defaultAddress->city,
                'barangay' => $defaultAddress->barangay,
                'country_id' => $defaultAddress->country_id ?? null,
                'postcode' => $defaultAddress->postcode ?? '',
            ], $items, $subtotal);
        } else {
            $shippingMethods = [];
        }

        $addressData = $defaultAddress ? [
            'state' => $defaultAddress->state,
            'city' => $defaultAddress->city,
            'country_id' => $defaultAddress->country_id ?? null,
        ] : null;

        $paymentMethods = $this->checkoutService->getAvailablePaymentMethods($addressData, $subtotal, [
            'customer_group_id' => $customer->customer_group_id,
        ]);

        // Coupon validation
        $couponCode = null;
        $couponDiscount = 0;
        $couponFreeShipping = false;
        $couponError = null;

        $cart = $this->cartService->findCart();
        if ($cart && $cart->coupon_code) {
            $cartItems = $cart->items()->with(['product.categories', 'productVariant'])->get();
            $couponResult = $this->couponService->validate(
                $cart->coupon_code,
                $customer,
                $cartItems,
                $subtotal
            );

            if ($couponResult->success) {
                $couponCode = $couponResult->coupon->code;
                $couponDiscount = $couponResult->discount;
                $couponFreeShipping = $couponResult->freeShipping;
            } else {
                // Coupon no longer valid — clear it from cart
                $this->couponService->remove($cart);
                $couponError = $couponResult->message;
            }
        }

        return view('checkout.index', compact(
            'customer', 'items', 'addresses', 'defaultAddress', 'countries',
            'subtotal', 'shippingMethods', 'paymentMethods',
            'couponCode', 'couponDiscount', 'couponFreeShipping', 'couponError'
        ));
    }

    public function saveAddress(Request $request)
    {
        $customer = Auth::guard('customer')->user();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'address_1' => ['required', 'string', 'max:1000'],
            'city' => ['required', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'phone' => ['required', 'string', 'max:30'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('is_default') || $customer->addresses()->count() === 0) {
            $customer->addresses()->update(['is_default' => false]);
            $validated['is_default'] = true;
        }

        $address = $customer->addresses()->create($validated);

        return response()->json([
            'success' => true,
            'address' => $address,
            'addresses' => $customer->addresses()->latest()->get(),
        ]);
    }

    public function placeOrder(Request $request)
    {
        $customer = Auth::guard('customer')->user();
        $cart = $this->cartService->findCart();
        $items = $cart ? $cart->items()->with(['product', 'productVariant'])->get() : collect();

        if (!$cart || $items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $validated = $request->validate([
            'shipping_address_id' => ['required', 'exists:customer_addresses,id'],
            'payment_method' => ['required', 'string'],
            'shipping_method' => ['required', 'string'],
            'different_billing' => ['nullable'],
            'billing_first_name' => ['required_if:different_billing,1', 'nullable', 'string', 'max:255'],
            'billing_last_name' => ['required_if:different_billing,1', 'nullable', 'string', 'max:255'],
            'billing_address_1' => ['required_if:different_billing,1', 'nullable', 'string', 'max:1000'],
            'billing_city' => ['required_if:different_billing,1', 'nullable', 'string', 'max:255'],
            'billing_district' => ['nullable', 'string', 'max:255'],
            'billing_barangay' => ['nullable', 'string', 'max:255'],
            'billing_state' => ['nullable', 'string', 'max:255'],
            'billing_postcode' => ['nullable', 'string', 'max:20'],
            'billing_country_id' => ['required_if:different_billing,1', 'nullable', 'integer', 'exists:countries,id'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'shipping_method_name' => ['nullable', 'string', 'max:255'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $shippingAddress = CustomerAddress::where('id', $validated['shipping_address_id'])
            ->where('customer_id', $customer->id)
            ->firstOrFail();

        $billingAddress = null;
        if ($request->input('different_billing') == '1') {
            $billingAddress = [
                'first_name' => $validated['billing_first_name'],
                'last_name' => $validated['billing_last_name'],
                'address_1' => $validated['billing_address_1'],
                'city' => $validated['billing_city'],
                'district' => $validated['billing_district'] ?? null,
                'barangay' => $validated['billing_barangay'] ?? null,
                'state' => $validated['billing_state'] ?? null,
                'postcode' => $validated['billing_postcode'] ?? null,
                'country_id' => $validated['billing_country_id'],
            ];
        }

        try {
            $result = $this->checkoutService->placeOrder(
                customer: $customer,
                shippingAddress: $shippingAddress,
                billingAddress: $billingAddress,
                paymentMethodKey: $validated['payment_method'],
                shippingMethodKey: $validated['shipping_method'],
                cart: $cart,
                comment: $validated['comment'] ?? null,
                shippingMethodName: $validated['shipping_method_name'] ?? null,
                shippingCostOverride: isset($validated['shipping_cost']) ? (float) $validated['shipping_cost'] : null,
            );

            $order = $result['order'];
            $payment = $result['payment'];

            if (!$payment->success) {
                return back()->withErrors(['checkout' => $payment->message ?? 'Payment processing failed.']);
            }

            if ($payment->redirectUrl) {
                return redirect()->away($payment->redirectUrl);
            }

            return redirect()->route('checkout.confirmation', $order);

        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['checkout' => $e->getMessage()]);
        }
    }

    public function getShippingRates(Request $request)
    {
        $request->validate([
            'address_id' => 'required|exists:customer_addresses,id',
        ]);

        $customer = Auth::guard('customer')->user();
        $address = $customer->addresses()->findOrFail($request->input('address_id'));
        $items = $this->cartService->getItems();
        $subtotal = $this->cartService->getSubtotal();

        $quoteService = app(\App\Services\Shipping\ShippingQuoteService::class);
        $quotes = $quoteService->getQuotes([
            'province' => $address->state,
            'city' => $address->city,
            'barangay' => $address->barangay,
            'country_id' => $address->country_id ?? null,
            'postcode' => $address->postcode ?? '',
        ], $items, $subtotal);

        return response()->json(['methods' => $quotes]);
    }

    public function hitpayReturn(Request $request)
    {
        $customer = Auth::guard('customer')->user();
        $paymentRequestId = $request->query('reference');
        $status = $request->query('status');

        if (!$paymentRequestId) {
            return redirect()->route('account.orders');
        }

        // HitPay sends payment_request_id as 'reference', not our order ID
        $order = $customer->orders()
            ->where('payment_reference', $paymentRequestId)
            ->first();

        if (!$order) {
            return redirect()->route('account.orders');
        }

        // Customer didn't complete payment — send back to checkout
        if ($status !== 'completed') {
            return redirect()->route('checkout.index')
                ->with('error', 'Payment was not completed. Your cart has been preserved — please try again.');
        }

        // Payment completed — clear cart (webhook may not have fired yet)
        $cart = $customer->cart;
        if ($cart) {
            $cart->items()->delete();
        }

        // If webhook hasn't processed yet, update status and send emails now
        $successStatusId = \App\Models\Setting::where('group', 'payment')
            ->where('key', 'hitpay_success_status_id')->value('value');

        if ($successStatusId && (int) $order->order_status_id !== (int) $successStatusId) {
            $newStatus = \App\Models\OrderStatus::find($successStatusId);
            $order->update(['order_status_id' => $successStatusId]);

            \App\Models\OrderHistory::create([
                'order_id' => $order->id,
                'order_status_id' => $successStatusId,
                'comment' => 'Payment confirmed via HitPay (return URL).',
            ]);

            if ($newStatus) {
                \App\Services\StockService::adjustForStatusChange($order, $newStatus);
            }

            $order->load('products', 'status');
            \App\Services\CheckoutService::sendOrderConfirmationEmail($order);
            \App\Services\CheckoutService::sendAdminOrderNotification($order);
        }

        return redirect()->route('checkout.confirmation', $order);
    }

    public function confirmation(int $orderId)
    {
        $customer = Auth::guard('customer')->user();
        $order = $customer->orders()
            ->with(['products', 'status'])
            ->where('id', $orderId)
            ->firstOrFail();

        $bankInstructions = null;
        if (str_starts_with($order->payment_method, 'bank_transfer')) {
            $bankInstructions = \App\Services\PaymentMethods\BankTransferPayment::getInstructionsForOrder($order);
        }

        return view('checkout.confirmation', compact('order', 'bankInstructions'));
    }
}
