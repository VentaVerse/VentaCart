# Checkout System Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a multi-step checkout wizard (Address > Payment > Confirmation) with order summary sidebar, stock validation, bank transfer payment, and order confirmation email.

**Architecture:** Multi-step Alpine.js wizard on a single Blade view. CheckoutController handles step rendering and order placement. CheckoutService encapsulates order creation logic. Payment methods are classes implementing a common interface. Shipping method selection lives in the order summary sidebar.

**Tech Stack:** Laravel 12, Alpine.js, Blade, MySQL, Tailwind CSS (via storefront.css)

---

## Task 1: Database Migration — Billing Address + Checkout Settings

**Files:**
- Create: `database/migrations/2026_03_06_100000_add_billing_and_checkout_fields.php`
- Modify: `app/Models/Order.php` (add billing fields to $fillable)
- Modify: `database/seeders/DatabaseSeeder.php` (add checkout settings)

**Step 1: Create migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_district')->nullable()->after('shipping_city');
            $table->string('shipping_barangay')->nullable()->after('shipping_district');

            // Billing address (nullable — only when different from shipping)
            $table->string('billing_first_name')->nullable()->after('tracking_number');
            $table->string('billing_last_name')->nullable()->after('billing_first_name');
            $table->text('billing_address_1')->nullable()->after('billing_last_name');
            $table->string('billing_city')->nullable()->after('billing_address_1');
            $table->string('billing_district')->nullable()->after('billing_city');
            $table->string('billing_barangay')->nullable()->after('billing_district');
            $table->string('billing_state')->nullable()->after('billing_barangay');
            $table->string('billing_postcode')->nullable()->after('billing_state');
            $table->string('billing_country_code', 3)->nullable()->after('billing_postcode');
        });

        // Checkout settings
        DB::table('settings')->insert([
            ['group' => 'checkout', 'key' => 'guest_allowed', 'value' => '0'],
            ['group' => 'checkout', 'key' => 'shipping_flat_rate', 'value' => '150'],
            ['group' => 'checkout', 'key' => 'shipping_free_threshold', 'value' => '3000'],
        ]);

        // Mail settings
        DB::table('settings')->insert([
            ['group' => 'mail', 'key' => 'driver', 'value' => 'smtp'],
            ['group' => 'mail', 'key' => 'host', 'value' => ''],
            ['group' => 'mail', 'key' => 'port', 'value' => '587'],
            ['group' => 'mail', 'key' => 'username', 'value' => ''],
            ['group' => 'mail', 'key' => 'password', 'value' => ''],
            ['group' => 'mail', 'key' => 'encryption', 'value' => 'tls'],
            ['group' => 'mail', 'key' => 'from_address', 'value' => ''],
            ['group' => 'mail', 'key' => 'from_name', 'value' => ''],
        ]);

        // Bank transfer settings
        DB::table('settings')->insert([
            ['group' => 'payment', 'key' => 'bank_transfer_enabled', 'value' => '1'],
            ['group' => 'payment', 'key' => 'bank_transfer_instructions', 'value' => 'Please transfer to the following bank account:\n\nBank: BDO\nAccount Name: New Gear Day\nAccount Number: 1234567890\n\nPlease include your order number as reference.'],
        ]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_district', 'shipping_barangay',
                'billing_first_name', 'billing_last_name', 'billing_address_1',
                'billing_city', 'billing_district', 'billing_barangay',
                'billing_state', 'billing_postcode', 'billing_country_code',
            ]);
        });

        DB::table('settings')->where('group', 'checkout')->delete();
        DB::table('settings')->where('group', 'mail')->delete();
        DB::table('settings')->where('group', 'payment')->delete();
    }
};
```

**Step 2: Update Order model fillable**

Add to `app/Models/Order.php` $fillable array:
```php
'shipping_district', 'shipping_barangay',
'billing_first_name', 'billing_last_name', 'billing_address_1',
'billing_city', 'billing_district', 'billing_barangay',
'billing_state', 'billing_postcode', 'billing_country_code',
```

**Step 3: Run migration**

```bash
php artisan migrate
```

**Step 4: Commit**

```
feat: add billing address columns and checkout/mail/payment settings
```

---

## Task 2: Stock Validation in CartService

**Files:**
- Modify: `app/Services/CartService.php` (add stock check in `add()`)

**Step 1: Add stock validation to `add()` method**

In `CartService::add()`, after loading the product and variant (around line 51), add stock check:

```php
// After line 51 ($product = Product::where...)
// Check stock availability
$availableQty = $product->quantity;
if ($variantId) {
    // variant loaded later, so move stock check after variant load
}
```

Actually, insert after the variant loading block (after line 75), before special pricing:

```php
// Check stock
$stockQty = $variant ? $variant->quantity : $product->quantity;
if ($stockQty <= 0) {
    throw ValidationException::withMessages([
        'quantity' => 'This product is currently out of stock.',
    ]);
}

// Check if cart already has some and total would exceed stock
$cart = $this->getCart();
$existingQuery = $cart->items()->where('product_id', $productId);
if ($variantId) {
    $existingQuery->where('product_variant_id', $variantId);
} else {
    $existingQuery->whereNull('product_variant_id');
}
$existingQty = $existingQuery->value('quantity') ?? 0;

if (($existingQty + $quantity) > $stockQty) {
    throw ValidationException::withMessages([
        'quantity' => "Only {$stockQty} available (you already have {$existingQty} in your cart).",
    ]);
}
```

Note: Move the `$cart = $this->getCart()` call and existing-item lookup earlier, before stock check, to avoid duplicate code. Refactor the method to check stock before creating/updating the item.

**Step 2: Commit**

```
feat: add stock validation when adding to cart
```

---

## Task 3: CheckoutService + Payment Method Interface

**Files:**
- Create: `app/Services/CheckoutService.php`
- Create: `app/Services/PaymentMethods/PaymentMethodInterface.php`
- Create: `app/Services/PaymentMethods/BankTransferPayment.php`

**Step 1: Create PaymentMethodInterface**

```php
<?php

namespace App\Services\PaymentMethods;

use App\Models\Order;

interface PaymentMethodInterface
{
    public function getKey(): string;
    public function getName(): string;
    public function getDescription(): string;
    public function isEnabled(): bool;
    public function processPayment(Order $order): PaymentResult;
}
```

**Step 2: Create PaymentResult value object**

```php
<?php

namespace App\Services\PaymentMethods;

class PaymentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $message = null,
    ) {}
}
```

Create file: `app/Services/PaymentMethods/PaymentResult.php`

**Step 3: Create BankTransferPayment**

```php
<?php

namespace App\Services\PaymentMethods;

use App\Models\Order;

class BankTransferPayment implements PaymentMethodInterface
{
    public function getKey(): string
    {
        return 'bank_transfer';
    }

    public function getName(): string
    {
        return 'Bank Transfer';
    }

    public function getDescription(): string
    {
        return 'Pay via bank transfer. Order will be confirmed after payment is verified.';
    }

    public function isEnabled(): bool
    {
        return (bool) theme('payment.bank_transfer_enabled');
    }

    public function processPayment(Order $order): PaymentResult
    {
        // Bank transfer = no processing, order stays pending
        return new PaymentResult(success: true);
    }
}
```

**Step 4: Create CheckoutService**

```php
<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\OrderProduct;
use App\Models\OrderStatus;
use App\Services\PaymentMethods\BankTransferPayment;
use App\Services\PaymentMethods\PaymentMethodInterface;
use App\Services\PaymentMethods\PaymentResult;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    private int $storeId;

    public function __construct()
    {
        $this->storeId = (int) config('app.store_id', 1);
    }

    public function getAvailablePaymentMethods(): array
    {
        $methods = [
            new BankTransferPayment(),
        ];

        return array_filter($methods, fn($m) => $m->isEnabled());
    }

    public function getPaymentMethod(string $key): ?PaymentMethodInterface
    {
        foreach ($this->getAvailablePaymentMethods() as $method) {
            if ($method->getKey() === $key) {
                return $method;
            }
        }
        return null;
    }

    public function getShippingMethods(float $subtotal): array
    {
        $flatRate = (float) theme('checkout.shipping_flat_rate', 150);
        $freeThreshold = (float) theme('checkout.shipping_free_threshold', 3000);

        $methods = [];

        // Free shipping (if threshold met)
        if ($freeThreshold > 0 && $subtotal >= $freeThreshold) {
            $methods[] = [
                'key' => 'free',
                'name' => 'Free Shipping',
                'cost' => 0,
            ];
        }

        // Flat rate
        $methods[] = [
            'key' => 'flat_rate',
            'name' => 'Standard Shipping',
            'cost' => $flatRate,
        ];

        return $methods;
    }

    public function placeOrder(
        Customer $customer,
        CustomerAddress $shippingAddress,
        ?array $billingAddress,
        string $paymentMethodKey,
        string $shippingMethodKey,
        Cart $cart,
        ?string $comment = null
    ): array {
        $paymentMethod = $this->getPaymentMethod($paymentMethodKey);
        if (!$paymentMethod) {
            throw new \InvalidArgumentException('Invalid payment method.');
        }

        $items = $cart->items()->with(['product', 'productVariant'])->get();
        if ($items->isEmpty()) {
            throw new \InvalidArgumentException('Cart is empty.');
        }

        $subtotal = $items->sum(fn($item) => $item->price * $item->quantity);
        $shippingMethods = $this->getShippingMethods($subtotal);
        $shippingMethod = collect($shippingMethods)->firstWhere('key', $shippingMethodKey);
        if (!$shippingMethod) {
            $shippingMethod = $shippingMethods[0] ?? ['key' => 'flat_rate', 'name' => 'Standard Shipping', 'cost' => 0];
        }

        $shippingCost = $shippingMethod['cost'];
        $total = $subtotal + $shippingCost;

        $defaultStatus = OrderStatus::where('store_id', $this->storeId)
            ->where('is_default', true)
            ->first();

        $order = DB::transaction(function () use (
            $customer, $shippingAddress, $billingAddress, $paymentMethodKey,
            $shippingMethod, $items, $subtotal, $shippingCost, $total,
            $defaultStatus, $comment
        ) {
            $orderData = [
                'store_id' => $this->storeId,
                'customer_id' => $customer->id,
                'order_status_id' => $defaultStatus?->id,
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
                'shipping_country_code' => $shippingAddress->country_code,
                'payment_method' => $paymentMethodKey,
                'shipping_method' => $shippingMethod['name'],
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'total' => $total,
                'comment' => $comment,
            ];

            // Billing address (only if different from shipping)
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
                    'billing_country_code' => $billingAddress['country_code'],
                ]);
            }

            $order = Order::create($orderData);

            // Create order products from cart items
            foreach ($items as $item) {
                OrderProduct::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'model' => $item->product?->model,
                    'variant_label' => $item->variant_label,
                    'price' => $item->price,
                    'quantity' => $item->quantity,
                    'total' => $item->price * $item->quantity,
                    'is_custom' => false,
                ]);
            }

            // Initial order history
            OrderHistory::create([
                'order_id' => $order->id,
                'order_status_id' => $defaultStatus?->id,
                'comment' => 'Order placed via storefront.',
            ]);

            // Clear cart
            $cart->items()->delete();

            return $order;
        });

        // Process payment
        $paymentResult = $paymentMethod->processPayment($order);

        return ['order' => $order, 'payment' => $paymentResult];
    }
}
```

**Step 5: Commit**

```
feat: add CheckoutService and payment method interface with bank transfer
```

---

## Task 4: CheckoutController

**Files:**
- Create: `app/Http/Controllers/Storefront/CheckoutController.php`
- Modify: `routes/web.php` (add checkout routes)

**Step 1: Create CheckoutController**

```php
<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\CustomerAddress;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckoutController extends Controller
{
    public function __construct(
        private CartService $cartService,
        private CheckoutService $checkoutService,
    ) {}

    public function index()
    {
        $customer = Auth::guard('customer')->user();
        $items = $this->cartService->getItems();

        if ($items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $addresses = $customer->addresses()->latest()->get();
        $defaultAddress = $customer->addresses()->where('is_default', true)->first();
        $countries = Country::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'iso2', 'name']);

        $subtotal = $this->cartService->getSubtotal();
        $shippingMethods = $this->checkoutService->getShippingMethods($subtotal);
        $paymentMethods = $this->checkoutService->getAvailablePaymentMethods();

        return view('storefront.checkout.index', compact(
            'items', 'addresses', 'defaultAddress', 'countries',
            'subtotal', 'shippingMethods', 'paymentMethods'
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
            'country_code' => ['required', 'string', 'size:2'],
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
        $cart = $this->cartService->getCart();
        $items = $cart->items()->with(['product', 'productVariant'])->get();

        if ($items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $validated = $request->validate([
            'shipping_address_id' => ['required', 'exists:customer_addresses,id'],
            'payment_method' => ['required', 'string'],
            'shipping_method' => ['required', 'string'],
            'different_billing' => ['nullable', 'boolean'],
            'billing_first_name' => ['required_if:different_billing,1', 'nullable', 'string', 'max:255'],
            'billing_last_name' => ['required_if:different_billing,1', 'nullable', 'string', 'max:255'],
            'billing_address_1' => ['required_if:different_billing,1', 'nullable', 'string', 'max:1000'],
            'billing_city' => ['required_if:different_billing,1', 'nullable', 'string', 'max:255'],
            'billing_district' => ['nullable', 'string', 'max:255'],
            'billing_barangay' => ['nullable', 'string', 'max:255'],
            'billing_state' => ['nullable', 'string', 'max:255'],
            'billing_postcode' => ['nullable', 'string', 'max:20'],
            'billing_country_code' => ['required_if:different_billing,1', 'nullable', 'string', 'size:2'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        // Verify shipping address belongs to customer
        $shippingAddress = CustomerAddress::where('id', $validated['shipping_address_id'])
            ->where('customer_id', $customer->id)
            ->firstOrFail();

        $billingAddress = null;
        if ($request->boolean('different_billing')) {
            $billingAddress = [
                'first_name' => $validated['billing_first_name'],
                'last_name' => $validated['billing_last_name'],
                'address_1' => $validated['billing_address_1'],
                'city' => $validated['billing_city'],
                'district' => $validated['billing_district'] ?? null,
                'barangay' => $validated['billing_barangay'] ?? null,
                'state' => $validated['billing_state'] ?? null,
                'postcode' => $validated['billing_postcode'] ?? null,
                'country_code' => $validated['billing_country_code'],
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
            );

            $order = $result['order'];
            $payment = $result['payment'];

            // If payment requires redirect (e.g., HitPay), redirect
            if ($payment->redirectUrl) {
                return redirect()->away($payment->redirectUrl);
            }

            return redirect()->route('checkout.confirmation', $order);

        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['checkout' => $e->getMessage()]);
        }
    }

    public function confirmation(int $orderId)
    {
        $customer = Auth::guard('customer')->user();
        $order = $customer->orders()
            ->with(['products', 'status'])
            ->where('id', $orderId)
            ->firstOrFail();

        $bankInstructions = null;
        if ($order->payment_method === 'bank_transfer') {
            $bankInstructions = theme('payment.bank_transfer_instructions');
        }

        return view('storefront.checkout.confirmation', compact('order', 'bankInstructions'));
    }
}
```

**Step 2: Add checkout routes to `routes/web.php`**

Add import at top:
```php
use App\Http\Controllers\Storefront\CheckoutController as StorefrontCheckoutController;
```

Add routes after cart routes, inside `customer.auth` middleware or as a new group:
```php
// Checkout (authenticated customers)
Route::middleware('customer.auth')->group(function () {
    Route::get('/checkout', [StorefrontCheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout/address', [StorefrontCheckoutController::class, 'saveAddress'])->name('checkout.address.save');
    Route::post('/checkout/place', [StorefrontCheckoutController::class, 'placeOrder'])->name('checkout.place');
    Route::get('/checkout/confirmation/{order}', [StorefrontCheckoutController::class, 'confirmation'])->name('checkout.confirmation');
});
```

**Step 3: Update cart view "Proceed to Checkout" link**

In `resources/views/storefront/cart/index.blade.php` line 79, change:
```blade
<a href="{{ route('home') }}" class="btn-checkout block text-center mb-3">PROCEED TO CHECKOUT</a>
```
to:
```blade
<a href="{{ route('checkout.index') }}" class="btn-checkout block text-center mb-3">PROCEED TO CHECKOUT</a>
```

**Step 4: Commit**

```
feat: add CheckoutController with routes and wire up cart checkout link
```

---

## Task 5: Checkout Views — Step Wizard + Address Step

**Files:**
- Create: `resources/views/storefront/checkout/index.blade.php`
- Modify: `resources/css/storefront.css` (add checkout styles)
- Modify: `resources/js/storefront.js` (add checkout Alpine component)

**Step 1: Create checkout wizard view**

The checkout is a single Blade file with Alpine.js managing steps. Steps:
1. Delivery Address (card selector + add new + optional billing)
2. Payment Method (radio buttons + place order)

Order summary sidebar with shipping method radios is visible on all steps.

Create `resources/views/storefront/checkout/index.blade.php`:

```blade
@php
    $addressesJson = $addresses->toJson();
    $defaultAddressId = $defaultAddress?->id;
    $itemsJson = $items->toJson();
    $shippingMethodsJson = json_encode($shippingMethods);
    $paymentMethodsData = collect($paymentMethods)->map(fn($m) => [
        'key' => $m->getKey(),
        'name' => $m->getName(),
        'description' => $m->getDescription(),
    ])->values()->toJson();
@endphp

<x-store-layout title="Checkout">
    <div class="container-store py-10" x-data="checkoutWizard(
        {{ $addressesJson }},
        {{ $defaultAddressId ?? 'null' }},
        {{ $itemsJson }},
        {{ $subtotal }},
        {{ $shippingMethodsJson }},
        {{ $paymentMethodsData }}
    )">
        <h1 class="section-title reveal">CHECKOUT</h1>

        {{-- Step Indicator --}}
        <div class="checkout-steps reveal">
            <button class="checkout-step" :class="{ 'active': step === 1, 'completed': step > 1 }" @click="step > 1 && goToStep(1)">
                <span class="checkout-step-number" x-text="step > 1 ? '✓' : '1'"></span>
                <span class="checkout-step-label">Delivery Address</span>
            </button>
            <div class="checkout-step-line" :class="{ 'completed': step > 1 }"></div>
            <button class="checkout-step" :class="{ 'active': step === 2 }">
                <span class="checkout-step-number">2</span>
                <span class="checkout-step-label">Payment</span>
            </button>
        </div>

        @if ($errors->any())
            <div class="account-flash-error mb-6 reveal">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- Main Content --}}
            <div class="lg:col-span-2">

                {{-- Step 1: Delivery Address --}}
                <div x-show="step === 1" x-transition>
                    <h2 class="checkout-section-title">DELIVERY ADDRESS</h2>

                    {{-- Existing addresses as cards --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6" x-show="addresses.length > 0">
                        <template x-for="addr in addresses" :key="addr.id">
                            <div class="checkout-address-card"
                                 :class="{ 'selected': selectedAddressId === addr.id }"
                                 @click="selectAddress(addr.id)">
                                <div class="flex items-start justify-between mb-2">
                                    <span class="text-[#E8E8E8] text-sm font-medium" x-text="addr.first_name + ' ' + addr.last_name"></span>
                                    <template x-if="addr.is_default">
                                        <span class="account-badge-default text-[10px]">DEFAULT</span>
                                    </template>
                                </div>
                                <div class="text-xs text-[#999] space-y-0.5">
                                    <p x-text="addr.address_1"></p>
                                    <p x-show="addr.barangay" x-text="addr.barangay"></p>
                                    <p x-show="addr.district" x-text="addr.district"></p>
                                    <p>
                                        <span x-text="addr.city"></span><span x-show="addr.state">, <span x-text="addr.state"></span></span>
                                        <span x-show="addr.postcode" x-text="' ' + addr.postcode"></span>
                                    </p>
                                    <p class="text-[#777]" x-text="addr.country_code"></p>
                                </div>
                                <button @click.stop="editAddress(addr)" class="checkout-card-edit">Edit</button>
                            </div>
                        </template>

                        {{-- Add new address card --}}
                        <div class="checkout-address-card add-new" @click="showNewAddressForm = true; editingAddress = null">
                            <div class="flex flex-col items-center justify-center h-full text-[#555] hover:text-[var(--color-primary)] transition-colors">
                                <svg class="w-8 h-8 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4"/></svg>
                                <span class="text-sm" style="font-family: var(--font-heading); letter-spacing: 0.1em;">ADD NEW ADDRESS</span>
                            </div>
                        </div>
                    </div>

                    {{-- No addresses state --}}
                    <div x-show="addresses.length === 0 && !showNewAddressForm" class="account-card text-center py-8 mb-6">
                        <p class="text-[#555] mb-4">You haven't added any addresses yet.</p>
                        <button @click="showNewAddressForm = true" class="account-btn-primary">ADD ADDRESS</button>
                    </div>

                    {{-- New/Edit address form (inline) --}}
                    <div x-show="showNewAddressForm" x-transition class="account-card mb-6" x-data="addressForm({
                        countryCode: editingAddress?.country_code || 'PH',
                        state: editingAddress?.state || '',
                        city: editingAddress?.city || '',
                        district: editingAddress?.district || '',
                        barangay: editingAddress?.barangay || '',
                    })">
                        <h3 class="text-sm tracking-[0.15em] text-[#E8E8E8] mb-4" style="font-family: var(--font-heading);" x-text="editingAddress ? 'EDIT ADDRESS' : 'NEW ADDRESS'"></h3>

                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="account-label">First Name <span class="text-[var(--color-secondary)]">*</span></label>
                                    <input type="text" x-model="newAddr.first_name" class="account-input w-full">
                                </div>
                                <div>
                                    <label class="account-label">Last Name <span class="text-[var(--color-secondary)]">*</span></label>
                                    <input type="text" x-model="newAddr.last_name" class="account-input w-full">
                                </div>
                            </div>

                            <div>
                                <label class="account-label">Address <span class="text-[var(--color-secondary)]">*</span></label>
                                <input type="text" x-model="newAddr.address_1" class="account-input w-full" placeholder="Street address, building, unit">
                            </div>

                            {{-- Country --}}
                            <div>
                                <label class="account-label">Country <span class="text-[var(--color-secondary)]">*</span></label>
                                <select x-model="countryCode" @change="onCountryChange(); newAddr.country_code = countryCode" class="account-input w-full">
                                    <option value="">-- Select Country --</option>
                                    @foreach ($countries as $country)
                                        <option value="{{ $country->iso2 }}">{{ $country->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- PH: Province --}}
                            <div x-show="countryCode === 'PH'">
                                <label class="account-label">Province <span class="text-[var(--color-secondary)]">*</span></label>
                                <select x-model="state" @change="onProvinceChange(); newAddr.state = state" class="account-input w-full">
                                    <option value="">-- Select Province --</option>
                                    <template x-for="p in provinces" :key="p.id">
                                        <option :value="p.name" x-text="p.name"></option>
                                    </template>
                                </select>
                            </div>

                            {{-- PH: City --}}
                            <div x-show="countryCode === 'PH' && provinces.length > 0">
                                <label class="account-label">City / Municipality <span class="text-[var(--color-secondary)]">*</span></label>
                                <select x-model="city" @change="onCityChange(); newAddr.city = city" class="account-input w-full">
                                    <option value="">-- Select City --</option>
                                    <template x-for="c in cities" :key="c.id">
                                        <option :value="c.name" x-text="c.name"></option>
                                    </template>
                                </select>
                            </div>

                            {{-- PH: District --}}
                            <div x-show="countryCode === 'PH' && districts.length > 0">
                                <label class="account-label">District</label>
                                <select x-model="district" @change="onDistrictChange(); newAddr.district = district" class="account-input w-full">
                                    <option value="">-- Select District --</option>
                                    <template x-for="d in districts" :key="d.id">
                                        <option :value="d.name" x-text="d.name"></option>
                                    </template>
                                </select>
                            </div>

                            {{-- PH: Barangay --}}
                            <div x-show="countryCode === 'PH' && barangays.length > 0">
                                <label class="account-label">Barangay</label>
                                <select x-model="barangay" @change="newAddr.barangay = barangay" class="account-input w-full">
                                    <option value="">-- Select Barangay --</option>
                                    <template x-for="b in barangays" :key="b.id">
                                        <option :value="b.name" x-text="b.name"></option>
                                    </template>
                                </select>
                            </div>

                            {{-- Non-PH: State --}}
                            <div x-show="countryCode !== 'PH' && countryCode !== ''">
                                <label class="account-label">State / Region</label>
                                <template x-if="zones.length > 0">
                                    <select x-model="state" @change="newAddr.state = state" class="account-input w-full">
                                        <option value="">-- Select State --</option>
                                        <template x-for="z in zones" :key="z.id">
                                            <option :value="z.name" x-text="z.name"></option>
                                        </template>
                                    </select>
                                </template>
                                <template x-if="zones.length === 0">
                                    <input type="text" x-model="state" @input="newAddr.state = state" class="account-input w-full">
                                </template>
                            </div>

                            {{-- Non-PH: City text --}}
                            <div x-show="countryCode !== 'PH' && countryCode !== ''">
                                <label class="account-label">City <span class="text-[var(--color-secondary)]">*</span></label>
                                <input type="text" x-model="city" @input="newAddr.city = city" class="account-input w-full">
                            </div>

                            {{-- Postcode --}}
                            <div>
                                <label class="account-label">Postcode / ZIP</label>
                                <input type="text" x-model="newAddr.postcode" class="account-input w-full max-w-[200px]">
                            </div>

                            <div class="flex items-center gap-3 pt-4 border-t border-[#2A2A2A]">
                                <button @click="saveNewAddress()" class="account-btn-primary" :disabled="savingAddress">
                                    <span x-show="!savingAddress" x-text="editingAddress ? 'UPDATE' : 'SAVE ADDRESS'"></span>
                                    <span x-show="savingAddress">SAVING...</span>
                                </button>
                                <button @click="showNewAddressForm = false" class="account-btn-secondary">Cancel</button>
                            </div>
                        </div>
                    </div>

                    {{-- Different billing address toggle --}}
                    <div class="mb-6" x-show="selectedAddressId">
                        <label class="flex items-center gap-2 text-sm text-[#777] cursor-pointer">
                            <input type="checkbox" x-model="differentBilling" class="accent-[var(--color-primary)]">
                            Use a different billing address
                        </label>
                    </div>

                    {{-- Billing address form (shown when toggled) --}}
                    <div x-show="differentBilling" x-transition class="account-card mb-6" x-data="addressForm({
                        countryCode: 'PH', state: '', city: '', district: '', barangay: ''
                    })">
                        <h3 class="text-sm tracking-[0.15em] text-[#E8E8E8] mb-4" style="font-family: var(--font-heading);">BILLING ADDRESS</h3>
                        {{-- Same address fields structure as above but bound to billing model --}}
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="account-label">First Name <span class="text-[var(--color-secondary)]">*</span></label>
                                    <input type="text" x-model="$root.billing.first_name" class="account-input w-full">
                                </div>
                                <div>
                                    <label class="account-label">Last Name <span class="text-[var(--color-secondary)]">*</span></label>
                                    <input type="text" x-model="$root.billing.last_name" class="account-input w-full">
                                </div>
                            </div>
                            <div>
                                <label class="account-label">Address <span class="text-[var(--color-secondary)]">*</span></label>
                                <input type="text" x-model="$root.billing.address_1" class="account-input w-full">
                            </div>
                            <div>
                                <label class="account-label">Country <span class="text-[var(--color-secondary)]">*</span></label>
                                <select x-model="countryCode" @change="onCountryChange(); $root.billing.country_code = countryCode" class="account-input w-full">
                                    <option value="">-- Select Country --</option>
                                    @foreach ($countries as $country)
                                        <option value="{{ $country->iso2 }}">{{ $country->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div x-show="countryCode === 'PH'">
                                <label class="account-label">Province</label>
                                <select x-model="state" @change="onProvinceChange(); $root.billing.state = state" class="account-input w-full">
                                    <option value="">-- Select Province --</option>
                                    <template x-for="p in provinces" :key="p.id"><option :value="p.name" x-text="p.name"></option></template>
                                </select>
                            </div>
                            <div x-show="countryCode === 'PH' && provinces.length > 0">
                                <label class="account-label">City</label>
                                <select x-model="city" @change="onCityChange(); $root.billing.city = city" class="account-input w-full">
                                    <option value="">-- Select City --</option>
                                    <template x-for="c in cities" :key="c.id"><option :value="c.name" x-text="c.name"></option></template>
                                </select>
                            </div>
                            <div x-show="countryCode !== 'PH' && countryCode !== ''">
                                <label class="account-label">State / Region</label>
                                <input type="text" x-model="state" @input="$root.billing.state = state" class="account-input w-full">
                            </div>
                            <div x-show="countryCode !== 'PH' && countryCode !== ''">
                                <label class="account-label">City <span class="text-[var(--color-secondary)]">*</span></label>
                                <input type="text" x-model="city" @input="$root.billing.city = city" class="account-input w-full">
                            </div>
                            <div>
                                <label class="account-label">Postcode / ZIP</label>
                                <input type="text" x-model="$root.billing.postcode" class="account-input w-full max-w-[200px]">
                            </div>
                        </div>
                    </div>

                    {{-- Continue button --}}
                    <button @click="goToStep(2)" class="btn-checkout" :disabled="!selectedAddressId">
                        CONTINUE TO PAYMENT
                    </button>
                </div>

                {{-- Step 2: Payment --}}
                <div x-show="step === 2" x-transition>
                    <h2 class="checkout-section-title">PAYMENT METHOD</h2>

                    <div class="space-y-3 mb-6">
                        <template x-for="pm in paymentMethods" :key="pm.key">
                            <label class="checkout-payment-option" :class="{ 'selected': selectedPayment === pm.key }">
                                <input type="radio" x-model="selectedPayment" :value="pm.key" class="accent-[var(--color-primary)]">
                                <div>
                                    <span class="text-[#E8E8E8] text-sm" x-text="pm.name" style="font-family: var(--font-heading); letter-spacing: 0.05em;"></span>
                                    <p class="text-[#777] text-xs mt-0.5" x-text="pm.description"></p>
                                </div>
                            </label>
                        </template>
                    </div>

                    {{-- Order comment --}}
                    <div class="mb-6">
                        <label class="account-label">Order Notes (optional)</label>
                        <textarea x-model="comment" class="account-input w-full" rows="3" placeholder="Special instructions for your order..."></textarea>
                    </div>

                    <button @click="submitOrder()" class="btn-checkout" :disabled="!selectedPayment || placing">
                        <span x-show="!placing">PLACE ORDER</span>
                        <span x-show="placing">PROCESSING...</span>
                    </button>
                </div>
            </div>

            {{-- Order Summary Sidebar --}}
            <div>
                <div class="order-summary-box sticky top-6">
                    <h3 class="text-lg tracking-[0.15em] mb-6 text-[#E8E8E8]" style="font-family: var(--font-heading);">ORDER SUMMARY</h3>

                    {{-- Items --}}
                    <div class="space-y-3 mb-4 max-h-[300px] overflow-y-auto checkout-items-scroll">
                        <template x-for="item in items" :key="item.id">
                            <div class="flex gap-3 text-sm">
                                <div class="w-12 h-12 flex-shrink-0 rounded bg-[#1A1A1A] overflow-hidden">
                                    <template x-if="item.product && item.product.images && item.product.images.length">
                                        <img :src="'/storage/' + item.product.images[0].path" :alt="item.name" class="w-full h-full object-cover">
                                    </template>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[#C8C8C8] text-xs truncate" x-text="item.name"></p>
                                    <p class="text-[#777] text-[10px]" x-show="item.variant_label" x-text="item.variant_label"></p>
                                    <p class="text-[#777] text-[10px]" x-text="'Qty: ' + item.quantity"></p>
                                </div>
                                <span class="text-[#E8E8E8] text-xs whitespace-nowrap" style="font-family: var(--font-heading);">
                                    {{ $defaultCurrency?->symbol ?? '₱' }}<span x-text="(item.price * item.quantity).toFixed(2)"></span>
                                </span>
                            </div>
                        </template>
                    </div>

                    <hr class="divider-glow my-4">

                    {{-- Subtotal --}}
                    <div class="flex justify-between text-sm mb-3">
                        <span class="text-[#888]">Subtotal</span>
                        <span class="text-[#E8E8E8]" style="font-family: var(--font-heading);">{{ $defaultCurrency?->symbol ?? '₱' }}<span x-text="subtotal.toFixed(2)"></span></span>
                    </div>

                    {{-- Shipping method selection --}}
                    <div class="mb-3">
                        <span class="text-[#888] text-sm block mb-2">Shipping</span>
                        <div class="space-y-2">
                            <template x-for="sm in shippingMethods" :key="sm.key">
                                <label class="flex items-center justify-between text-sm cursor-pointer group">
                                    <span class="flex items-center gap-2">
                                        <input type="radio" x-model="selectedShipping" :value="sm.key" @change="updateTotal()" class="accent-[var(--color-primary)]">
                                        <span class="text-[#999] group-hover:text-[#C8C8C8] transition-colors" x-text="sm.name"></span>
                                    </span>
                                    <span class="text-[#E8E8E8]" style="font-family: var(--font-heading);" x-text="sm.cost === 0 ? 'FREE' : '{{ $defaultCurrency?->symbol ?? '₱' }}' + sm.cost.toFixed(2)"></span>
                                </label>
                            </template>
                        </div>
                    </div>

                    <hr class="divider-glow my-4">

                    {{-- Total --}}
                    <div class="flex justify-between">
                        <span class="text-[#E8E8E8]" style="font-family: var(--font-heading); letter-spacing: 0.1em;">TOTAL</span>
                        <span class="text-xl text-[#E8E8E8]" style="font-family: var(--font-heading);">{{ $defaultCurrency?->symbol ?? '₱' }}<span x-text="total.toFixed(2)"></span></span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Hidden form for order submission --}}
        <form x-ref="orderForm" method="POST" action="{{ route('checkout.place') }}" class="hidden">
            @csrf
            <input type="hidden" name="shipping_address_id" :value="selectedAddressId">
            <input type="hidden" name="payment_method" :value="selectedPayment">
            <input type="hidden" name="shipping_method" :value="selectedShipping">
            <input type="hidden" name="different_billing" :value="differentBilling ? 1 : 0">
            <input type="hidden" name="billing_first_name" :value="billing.first_name">
            <input type="hidden" name="billing_last_name" :value="billing.last_name">
            <input type="hidden" name="billing_address_1" :value="billing.address_1">
            <input type="hidden" name="billing_city" :value="billing.city">
            <input type="hidden" name="billing_district" :value="billing.district">
            <input type="hidden" name="billing_barangay" :value="billing.barangay">
            <input type="hidden" name="billing_state" :value="billing.state">
            <input type="hidden" name="billing_postcode" :value="billing.postcode">
            <input type="hidden" name="billing_country_code" :value="billing.country_code">
            <input type="hidden" name="comment" :value="comment">
        </form>
    </div>
</x-store-layout>
```

**Step 2: Add Alpine.js `checkoutWizard` component to `resources/js/storefront.js`**

```js
Alpine.data('checkoutWizard', (addresses, defaultAddressId, items, subtotal, shippingMethods, paymentMethods) => ({
    step: 1,
    addresses: addresses,
    items: items,
    subtotal: subtotal,
    shippingMethods: shippingMethods,
    paymentMethods: paymentMethods,
    selectedAddressId: defaultAddressId,
    selectedShipping: shippingMethods[0]?.key || 'flat_rate',
    selectedPayment: paymentMethods[0]?.key || '',
    differentBilling: false,
    showNewAddressForm: addresses.length === 0,
    editingAddress: null,
    savingAddress: false,
    placing: false,
    comment: '',
    billing: { first_name: '', last_name: '', address_1: '', city: '', district: '', barangay: '', state: '', postcode: '', country_code: 'PH' },
    newAddr: { first_name: '', last_name: '', address_1: '', city: '', district: '', barangay: '', state: '', postcode: '', country_code: 'PH' },

    get shippingCost() {
        const method = this.shippingMethods.find(m => m.key === this.selectedShipping);
        return method ? method.cost : 0;
    },

    get total() {
        return this.subtotal + this.shippingCost;
    },

    updateTotal() { /* reactive via getters */ },

    goToStep(n) {
        if (n === 2 && !this.selectedAddressId) return;
        this.step = n;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    selectAddress(id) {
        this.selectedAddressId = id;
    },

    editAddress(addr) {
        this.editingAddress = addr;
        this.newAddr = { ...addr };
        this.showNewAddressForm = true;
    },

    async saveNewAddress() {
        this.savingAddress = true;
        try {
            const body = { ...this.newAddr };
            if (!this.editingAddress) {
                body.is_default = this.addresses.length === 0 ? 1 : 0;
            }

            // For editing, we'd call the account address update endpoint
            // For creating, we call the checkout save address endpoint
            const url = this.editingAddress
                ? `/account/addresses/${this.editingAddress.id}`
                : '/checkout/address';
            const method = this.editingAddress ? 'PUT' : 'POST';

            const res = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(body),
            });

            const data = await res.json();
            if (data.success || data.addresses) {
                this.addresses = data.addresses || this.addresses;
                if (data.address) {
                    this.selectedAddressId = data.address.id;
                }
                this.showNewAddressForm = false;
                this.editingAddress = null;
                this.newAddr = { first_name: '', last_name: '', address_1: '', city: '', district: '', barangay: '', state: '', postcode: '', country_code: 'PH' };
            }
        } catch (e) {
            console.error('Save address failed:', e);
        }
        this.savingAddress = false;
    },

    submitOrder() {
        if (!this.selectedAddressId || !this.selectedPayment) return;
        this.placing = true;
        this.$refs.orderForm.submit();
    },
}));
```

**Step 3: Add checkout CSS to `resources/css/storefront.css`**

```css
/* Checkout */
.checkout-steps {
    @apply flex items-center justify-center gap-0 mb-10;
}

.checkout-step {
    @apply flex items-center gap-2 px-4 py-2 text-sm text-[#555] transition-colors cursor-default;
    font-family: var(--font-heading);
    letter-spacing: 0.1em;
}

.checkout-step.active {
    @apply text-[var(--color-primary)];
}

.checkout-step.completed {
    @apply text-[#888] cursor-pointer;
}

.checkout-step.completed:hover {
    @apply text-[var(--color-primary)];
}

.checkout-step-number {
    @apply w-7 h-7 flex items-center justify-center rounded-full text-xs border border-[#333];
}

.checkout-step.active .checkout-step-number {
    border-color: var(--color-primary);
    color: var(--color-primary);
}

.checkout-step.completed .checkout-step-number {
    border-color: var(--color-primary);
    background: var(--color-primary);
    color: #0D0D0D;
}

.checkout-step-label {
    @apply hidden sm:inline;
}

.checkout-step-line {
    @apply w-12 h-px bg-[#333];
}

.checkout-step-line.completed {
    background: var(--color-primary);
}

.checkout-section-title {
    @apply text-lg tracking-[0.15em] mb-6 text-[#E8E8E8];
    font-family: var(--font-heading);
}

.checkout-address-card {
    @apply p-4 rounded border border-[#2A2A2A] bg-[#141414] cursor-pointer transition-all relative min-h-[140px];
}

.checkout-address-card:hover {
    border-color: #444;
}

.checkout-address-card.selected {
    border-color: var(--color-primary);
    box-shadow: 0 0 12px rgba(245, 166, 35, 0.15);
}

.checkout-address-card.add-new {
    @apply border-dashed flex items-center justify-center;
}

.checkout-address-card.add-new:hover {
    border-color: var(--color-primary);
}

.checkout-card-edit {
    @apply absolute bottom-3 right-3 text-xs text-[#555] hover:text-[var(--color-primary)] transition-colors;
    font-family: var(--font-heading);
    letter-spacing: 0.05em;
}

.checkout-payment-option {
    @apply flex items-start gap-3 p-4 rounded border border-[#2A2A2A] bg-[#141414] cursor-pointer transition-all;
}

.checkout-payment-option:hover {
    border-color: #444;
}

.checkout-payment-option.selected {
    border-color: var(--color-primary);
}

.checkout-items-scroll::-webkit-scrollbar {
    width: 4px;
}

.checkout-items-scroll::-webkit-scrollbar-thumb {
    @apply bg-[#333] rounded;
}
```

**Step 4: Build and clear**

```bash
npm run build && php artisan view:clear
```

**Step 5: Commit**

```
feat: add checkout wizard views with address cards, payment selection, and order summary sidebar
```

---

## Task 6: Confirmation Page

**Files:**
- Create: `resources/views/storefront/checkout/confirmation.blade.php`

**Step 1: Create confirmation view**

```blade
<x-store-layout title="Order Confirmed">
    <div class="container-store py-10">
        <div class="max-w-2xl mx-auto">
            <div class="text-center mb-8 reveal">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full border-2 border-[var(--color-primary)] flex items-center justify-center">
                    <svg class="w-8 h-8 text-[var(--color-primary)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
                <h1 class="section-title mb-2">ORDER CONFIRMED</h1>
                <p class="text-[#888]">Thank you for your order!</p>
                <p class="text-[#555] text-sm mt-1">Order #{{ $order->id }}</p>
            </div>

            {{-- Order Summary --}}
            <div class="account-card reveal mb-6">
                <h3 class="checkout-section-title">ORDER SUMMARY</h3>

                <div class="space-y-3 mb-4">
                    @foreach ($order->products as $item)
                        <div class="flex justify-between text-sm">
                            <div>
                                <span class="text-[#C8C8C8]">{{ $item->name }}</span>
                                @if ($item->variant_label)
                                    <span class="text-[#777] text-xs"> - {{ $item->variant_label }}</span>
                                @endif
                                <span class="text-[#555] text-xs"> x{{ $item->quantity }}</span>
                            </div>
                            <span class="text-[#E8E8E8]" style="font-family: var(--font-heading);">₱{{ number_format($item->total, 2) }}</span>
                        </div>
                    @endforeach
                </div>

                <hr class="divider-glow my-4">

                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-[#888]">Subtotal</span>
                        <span class="text-[#E8E8E8]" style="font-family: var(--font-heading);">₱{{ number_format($order->subtotal, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-[#888]">Shipping ({{ $order->shipping_method }})</span>
                        <span class="text-[#E8E8E8]" style="font-family: var(--font-heading);">
                            @if ($order->shipping_cost == 0) FREE @else ₱{{ number_format($order->shipping_cost, 2) }} @endif
                        </span>
                    </div>
                    <hr class="divider-glow my-2">
                    <div class="flex justify-between text-base">
                        <span class="text-[#E8E8E8]" style="font-family: var(--font-heading); letter-spacing: 0.1em;">TOTAL</span>
                        <span class="text-[#E8E8E8] text-xl" style="font-family: var(--font-heading);">₱{{ number_format($order->total, 2) }}</span>
                    </div>
                </div>
            </div>

            {{-- Shipping Address --}}
            <div class="account-card reveal mb-6">
                <h3 class="checkout-section-title">SHIPPING ADDRESS</h3>
                <div class="text-sm text-[#999] space-y-0.5">
                    <p class="text-[#E8E8E8]">{{ $order->shipping_first_name }} {{ $order->shipping_last_name }}</p>
                    <p>{{ $order->shipping_address_1 }}</p>
                    @if ($order->shipping_barangay)<p>{{ $order->shipping_barangay }}</p>@endif
                    @if ($order->shipping_district)<p>{{ $order->shipping_district }}</p>@endif
                    <p>{{ $order->shipping_city }}@if ($order->shipping_state), {{ $order->shipping_state }}@endif @if ($order->shipping_postcode) {{ $order->shipping_postcode }}@endif</p>
                    <p class="text-[#777]">{{ $order->shipping_country_code }}</p>
                </div>
            </div>

            {{-- Payment Info --}}
            <div class="account-card reveal mb-6">
                <h3 class="checkout-section-title">PAYMENT</h3>
                <p class="text-sm text-[#999] mb-2">
                    @if ($order->payment_method === 'bank_transfer') Bank Transfer @else {{ $order->payment_method }} @endif
                </p>
                <p class="text-sm text-[#E8E8E8]">Status: <span class="text-[var(--color-primary)]">{{ $order->status?->name ?? 'Pending' }}</span></p>

                @if ($bankInstructions)
                    <div class="mt-4 p-4 rounded bg-[#1A1A1A] border border-[#2A2A2A]">
                        <p class="text-xs text-[#888] mb-2" style="font-family: var(--font-heading); letter-spacing: 0.1em;">BANK TRANSFER INSTRUCTIONS</p>
                        <div class="text-sm text-[#C8C8C8] whitespace-pre-line">{{ $bankInstructions }}</div>
                    </div>
                @endif
            </div>

            {{-- Actions --}}
            <div class="flex flex-col sm:flex-row items-center gap-4 justify-center reveal">
                <a href="{{ route('account.orders.show', $order) }}" class="btn-checkout inline-block text-center">VIEW ORDER DETAILS</a>
                <a href="{{ route('home') }}" class="text-sm text-[#555] hover:text-[var(--color-primary)] transition-colors">Continue Shopping</a>
            </div>
        </div>
    </div>
</x-store-layout>
```

**Step 2: Build and clear**

```bash
npm run build && php artisan view:clear
```

**Step 3: Commit**

```
feat: add order confirmation page with summary, address, and bank transfer instructions
```

---

## Task 7: Order Confirmation Email

**Files:**
- Create: `app/Mail/OrderConfirmationMail.php`
- Create: `resources/views/emails/order-confirmation.blade.php`
- Modify: `app/Services/CheckoutService.php` (send email after order)

**Step 1: Create Mailable**

```bash
php artisan make:mail OrderConfirmationMail
```

Then update `app/Mail/OrderConfirmationMail.php`:

```php
<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Order Confirmation #' . $this->order->id,
        );
    }

    public function content(): Content
    {
        $bankInstructions = null;
        if ($this->order->payment_method === 'bank_transfer') {
            $bankInstructions = theme('payment.bank_transfer_instructions');
        }

        return new Content(
            view: 'emails.order-confirmation',
            with: ['order' => $this->order, 'bankInstructions' => $bankInstructions],
        );
    }
}
```

**Step 2: Create email template**

Create `resources/views/emails/order-confirmation.blade.php` — a simple HTML email with order summary, shipping address, payment info, and bank instructions if applicable. Match the dark theme style but keep it email-safe (inline CSS, table-based layout).

**Step 3: Add email sending to CheckoutService**

After the transaction completes and payment is processed, add:

```php
use App\Mail\OrderConfirmationMail;
use Illuminate\Support\Facades\Mail;

// After $paymentResult = $paymentMethod->processPayment($order);
try {
    $order->load('products', 'status');
    Mail::to($order->email)->send(new OrderConfirmationMail($order));
} catch (\Exception $e) {
    // Log but don't fail the order
    \Log::warning('Order confirmation email failed: ' . $e->getMessage());
}
```

**Step 4: Commit**

```
feat: add order confirmation email with bank transfer instructions
```

---

## Task 8: Admin Settings for Checkout, Payment, and Mail

**Files:**
- Modify: `resources/views/admin/theme/index.blade.php` (add Checkout tab or separate settings page)
- Modify: `app/Http/Controllers/Admin/ThemeController.php` (handle new settings groups)

This task adds admin UI tabs for:
- **Checkout** settings (guest_allowed toggle, shipping flat rate, free threshold)
- **Payment** settings (bank transfer toggle + instructions)
- **Mail** settings (driver, SMTP host/port/user/pass/encryption, from address/name)

These use the existing `settings` table and `ThemeController::updateSettings()` pattern — just new groups/keys. Add as new tabs in the theme admin page.

**Step 1: Add tabs and form fields to theme admin**

Add tabs: "Checkout", "Payment", "Mail" to the existing 12-tab theme admin.

**Step 2: Commit**

```
feat: add admin settings tabs for checkout, payment, and mail configuration
```

---

## Task 9: Update Cart "Proceed to Checkout" for Auth Check

**Files:**
- Modify: `resources/views/storefront/cart/index.blade.php`

**Step 1: Add auth check to checkout button**

The button should redirect to login if not authenticated, with a redirect back to checkout after login:

```blade
@auth('customer')
    <a href="{{ route('checkout.index') }}" class="btn-checkout block text-center mb-3">PROCEED TO CHECKOUT</a>
@else
    <a href="{{ route('customer.login', ['redirect' => 'checkout']) }}" class="btn-checkout block text-center mb-3">PROCEED TO CHECKOUT</a>
@endauth
```

**Step 2: Handle redirect in CustomerAuthController**

In `login()` method, after successful auth, check for redirect param:
```php
if ($request->input('redirect') === 'checkout') {
    return redirect()->route('checkout.index');
}
```

**Step 3: Commit**

```
feat: redirect unauthenticated checkout to login with return to checkout
```

---

## Task 10: Build, Test, and Memory Update

**Step 1: Full build**
```bash
npm run build && php artisan view:clear && php artisan cache:clear
```

**Step 2: Manual test flow**
1. Add products to cart
2. Go to cart, click "Proceed to Checkout"
3. If not logged in, verify redirect to login
4. After login, verify redirect to checkout
5. Select/add delivery address
6. Continue to payment step
7. Select bank transfer, place order
8. Verify confirmation page with order summary + bank instructions
9. Verify order appears in account order history
10. Verify email sent (if SMTP configured)

**Step 3: Update memory files**

Update `MEMORY.md`, `roadmap.md`, `file-map.md` with new checkout files and progress.

**Step 4: Commit**

```
feat: checkout system complete — multi-step wizard with bank transfer
```
