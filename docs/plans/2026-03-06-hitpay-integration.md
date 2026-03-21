# HitPay Payment Integration Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add HitPay as a payment method using their Payment Request API (external redirect), with admin-configurable sandbox/production mode, encrypted credentials, webhook verification, and configurable order status on successful payment.

**Architecture:** New `HitPayPayment` class implements existing `PaymentMethodInterface`. Order is created with default status before redirect. HitPay webhook updates order status on payment confirmation. All API calls server-side, credentials encrypted with Laravel's `encrypt()`.

**Tech Stack:** Laravel 12, HitPay Payment Request API v1 (REST), HMAC-SHA256 webhook verification

---

### Task 1: Migration - Add payment_reference to orders

**Files:**
- Create: `database/migrations/2026_03_06_300000_add_payment_reference_to_orders.php`
- Modify: `app/Models/Order.php` (add to $fillable)

**Step 1: Create migration**

```php
Schema::table('orders', function (Blueprint $table) {
    $table->string('payment_reference')->nullable()->after('payment_method');
});
```

**Step 2: Add `payment_reference` to Order model $fillable array**

**Step 3: Run migration**

```bash
php artisan migrate
```

---

### Task 2: Admin Settings UI - HitPay section in Payment tab

**Files:**
- Modify: `resources/views/admin/settings/index.blade.php`
- Modify: `app/Http/Controllers/Admin/SettingController.php`

**What to build in the Payment tab (below existing bank transfer section):**

- Divider + "HitPay" heading
- Enable/disable toggle checkbox
- Mode selector: Sandbox / Production (Alpine toggle, `x-model="hitpayMode"`)
- Sandbox fields (shown when mode=sandbox): API Key, Salt
- Production fields (shown when mode=production): API Key, Salt
- Success Order Status dropdown (populated from `order_statuses` table)
- All API key/salt fields masked with `type="password"` + a "Change" button pattern:
  - If value exists in DB, show `********xxxx` (last 4 chars) as placeholder in a disabled field, with a "Change" button
  - Clicking "Change" reveals the actual input field
  - Empty field name means "don't update" (controller skips empty encrypted fields)

**Settings keys (group: `payment`):**
- `hitpay_enabled` (0/1)
- `hitpay_mode` (sandbox/production)
- `hitpay_sandbox_api_key` (encrypted)
- `hitpay_sandbox_salt` (encrypted)
- `hitpay_production_api_key` (encrypted)
- `hitpay_production_salt` (encrypted)
- `hitpay_success_status_id` (order_status id)

**Controller changes:**
- Pass `$orderStatuses` to the view
- On save: detect keys ending in `_api_key` or `_salt` - if non-empty, `encrypt()` before storing. If empty, skip (don't overwrite).

---

### Task 3: HitPayPayment service class

**Files:**
- Create: `app/Services/PaymentMethods/HitPayPayment.php`

**Implements `PaymentMethodInterface`:**
- `getKey()`: returns `'hitpay'`
- `getName()`: returns `'HitPay'` (or card/ewallet description)
- `getDescription()`: returns payment description text
- `isEnabled()`: checks `payment.hitpay_enabled` setting
- `processPayment(Order $order)`: Creates HitPay Payment Request via API, returns `PaymentResult` with `redirectUrl`

**`processPayment` logic:**
1. Determine mode from settings (`hitpay_mode`)
2. Get API key for current mode, `decrypt()` it
3. POST to HitPay API endpoint:
   - Sandbox: `https://api.sandbox.hit-pay.com/v1/payment-requests`
   - Production: `https://api.hit-pay.com/v1/payment-requests`
4. Request body:
   - `amount`: order total
   - `currency`: from app config (e.g. PHP)
   - `email`: customer email
   - `name`: config('app.name') - store name per site
   - `purpose`: "Order #" . order id
   - `reference_number`: order id
   - `redirect_url`: route to return page
   - `webhook`: route to webhook endpoint
5. Parse response, store `payment_request_id` in `order.payment_reference`
6. Return `PaymentResult(success: true, redirectUrl: $response['url'])`
7. On API failure: return `PaymentResult(success: false, message: error)`

**Security:**
- All HTTP calls use Laravel's `Http::withToken()` (bearer auth)
- Timeout set to 30 seconds
- SSL verification enabled (default)

---

### Task 4: Webhook controller

**Files:**
- Create: `app/Http/Controllers/Storefront/HitPayWebhookController.php`
- Modify: `routes/web.php`

**Route:** `POST /hitpay/webhook` (no auth middleware, no CSRF - excluded)

**Webhook handler logic:**
1. Read POST data from HitPay
2. Verify HMAC-SHA256 signature:
   - Get salt for current mode, `decrypt()` it
   - Sort all POST params alphabetically (excluding `hmac`)
   - Concatenate key-value pairs with `|` separator
   - Generate HMAC-SHA256 hash using salt
   - Compare with received `hmac` field using `hash_equals()`
   - Reject with 401 if mismatch
3. Find order by `reference_number`
4. Verify order exists and `payment_reference` matches `payment_request_id`
5. Verify `amount` matches `order.total` and `currency` matches
6. Check `status` field:
   - `completed`: Update order status to configured success status, add OrderHistory entry, send confirmation email
   - `failed`/`expired`: Update to appropriate status, add history
7. Idempotency: if order already has the target status, skip (don't re-process)
8. Return 200 OK

**CSRF exclusion:** Add webhook route to `VerifyCsrfToken` middleware exception or use `withoutMiddleware`.

---

### Task 5: Return URL controller

**Files:**
- Modify: `app/Http/Controllers/Storefront/CheckoutController.php`
- Modify: `routes/web.php`

**Route:** `GET /checkout/hitpay/return` (customer.auth middleware)

**Logic:**
1. Read `reference` (order id) and `status` from query params
2. Find order belonging to current customer
3. If payment completed (order status matches success status): redirect to confirmation page
4. If pending/failed: redirect to confirmation page with appropriate message (webhook may not have arrived yet, show "payment being processed" message)

---

### Task 6: Register HitPay in CheckoutService

**Files:**
- Modify: `app/Services/CheckoutService.php`

**Changes:**
- Import `HitPayPayment`
- Add to `getAvailablePaymentMethods()` array
- In `placeOrder()`: if payment result has redirectUrl, do NOT send confirmation email yet (webhook handles it for HitPay). Move email sending logic: only send for non-redirect payments (bank transfer). For redirect payments, the webhook sends the email after confirmation.

---

### Task 7: Update checkout flow for redirect payments

**Files:**
- Modify: `app/Http/Controllers/Storefront/CheckoutController.php`
- Modify: `app/Services/CheckoutService.php`

**Key changes:**
- `CheckoutService::placeOrder()`: Don't clear cart for redirect payments. Cart cleared by webhook on success, or by return handler.
- `CheckoutController::placeOrder()`: Handle `PaymentResult.success === false` (API call failed) - redirect back with error.
- Confirmation view: handle HitPay orders (no bank instructions, show payment status).

---

### Task 8: Build and test

```bash
npm run build && php artisan view:clear
```

Manual test flow:
1. Configure HitPay sandbox credentials in admin
2. Add product to cart, checkout, select HitPay
3. Verify redirect to HitPay sandbox
4. Complete test payment
5. Verify webhook updates order status
6. Verify customer redirected to confirmation page
7. Verify order confirmation email sent
