# Checkout System Design

## Flow Overview

Multi-step wizard checkout with persistent order summary sidebar.

### Steps

1. **Account Info** (guest only) — Collects email + name. Auto-skips for logged-in customers. Only shown when `checkout.guest_allowed` setting is ON.
2. **Delivery Address** — Card-style address selector with default pre-selected. Edit inline. "Add new address" creates new card. Optional "Use a different billing address" toggle expands a second address section.
3. **Payment Method** — Selectable payment methods. Bank transfer at launch, HitPay added later. "Place Order" button.

### Order Summary Sidebar (all steps)

- Item list (image, name, variant, qty, line total)
- Subtotal
- Shipping method selection (radio buttons)
- Shipping cost (updates on method change)
- Total

### After Order

- Confirmation page with order summary
- Serves as return URL for payment gateway redirects (HitPay etc.)
- "View Order Details" button links to `/account/orders/{order}`
- Confirmation email sent (if mail configured)

---

## Stock Validation

- Block add-to-cart if product/variant quantity < requested amount
- No reservation system, no re-check at checkout

## Address System

- Saved addresses displayed as selectable cards
- Default address auto-selected for repeat customers
- Each card has edit action (inline form)
- "Add new address" creates a new card with form
- Optional "Use a different billing address" — separate address section
- Address cascading dropdowns reused from existing account address system (Country > Province > City > Barangay for PH)

## Shipping (placeholder)

- Radio buttons in order summary sidebar
- Flat rate / free shipping placeholder until shipping module built
- Shipping cost line updates when method selected
- Future: weight-based rates, geo zones

## Payment Methods

- Extensible system — each method is a class implementing a common interface
- **Bank transfer** (launch): Places order as "Pending", shows bank details on confirmation page
- **HitPay** (future): Redirects to gateway, returns to confirmation page with status
- Payment method availability can vary by customer group (future)

## Email

- Admin settings for mail transport: PHP mail or SMTP (host, port, username, password, encryption)
- Configurable email templates in admin
- Order confirmation email sent after successful order

## Guest Checkout

- `checkout.guest_allowed` setting in admin (boolean toggle, default OFF)
- Setting exists from day one, guest flow implemented later
- When ON: Step 1 collects email + name, order stored with email but no customer_id
- When OFF: Redirect to login page with "return to checkout" after auth

## Order Creation Flow

1. Validate cart is not empty
2. Validate selected address
3. Validate selected payment + shipping method
4. In DB transaction:
   - Create Order with customer snapshot, address snapshot, payment/shipping method, totals
   - Create OrderProducts from cart items (snapshot all fields)
   - Create initial OrderHistory entry (status: Pending)
   - Clear cart
5. Send confirmation email (if configured)
6. Redirect to confirmation page

## New Database Requirements

- `billing_first_name, billing_last_name, billing_address_1, billing_city, billing_district, billing_barangay, billing_state, billing_postcode, billing_country_code` columns on orders table (nullable — only populated when different from shipping)
- Mail settings in `settings` table (group: `mail`)
- Email templates in `settings` table or new `email_templates` table
- `checkout.guest_allowed` in settings table

## New Routes

```
GET  /checkout           → CheckoutController@index     (name: checkout.index)
POST /checkout/address   → CheckoutController@saveAddress
POST /checkout/place     → CheckoutController@placeOrder
GET  /checkout/confirm/{order} → CheckoutController@confirmation
```

## New Files

- `app/Http/Controllers/Storefront/CheckoutController.php`
- `app/Services/CheckoutService.php` — order creation logic
- `app/Services/PaymentMethods/BankTransferPayment.php`
- `app/Services/PaymentMethods/PaymentMethodInterface.php`
- `resources/views/storefront/checkout/index.blade.php` — wizard container
- `resources/views/storefront/checkout/steps/address.blade.php`
- `resources/views/storefront/checkout/steps/payment.blade.php`
- `resources/views/storefront/checkout/confirmation.blade.php`
- Migration: add billing address columns to orders table
- Migration: add checkout/mail settings to seeder
