# Cart Table Leak — Sandbox Fix Brief

**Status:** Brief for a sandbox fix. Independent of the pricing-precedence work in `2026-05-01-pricing-precedence-brief.md`.

**Audience:** A Claude Code agent working in a sandbox copy of this repo. Read this brief cold.

---

## TL;DR

The `ngd_carts` table is being polluted with empty rows from anonymous traffic. As of 2026-05-01 it contains 271,963 rows occupying 52 MB; **271,886 of those (99.96%) are empty guest carts that were created and never touched again** — a `created_at = updated_at` signature, no items in `ngd_cart_items`, and `session_id`s that don't even exist in `ngd_sessions` (orphaned). New rows are being added at 6,000–10,000 per day.

Fix the leak so the table stays small without manual cleanup.

---

## Root cause

`app/Services/CartService.php`:

```php
public function getCart(): Cart   // line 25
{
    // ...
    $this->cart = Cart::firstOrCreate(...)   // ← always creates if missing
}
```

`getCart()` is called from both write paths (where creating a cart is correct) **and** read paths (where it isn't). Read-path callers include:

- `getItems()` — line 189 of the service. Triggered by `CartController::index` (the `/cart` page) and `CartController::mini` (the header drop-down).
- `update(...)` and `remove(...)` service methods — they read `$cart->items()` to find the row, no need to create.
- `CouponController::apply` and `CouponController::remove` — both lines 26, 64.
- `CheckoutController::index` line 67 — checkout page.
- `CheckoutController::placeOrder` line 130 — order submission.

Every visit to `/cart` (or any of those endpoints) by a crawler/scraper that doesn't return a cookie creates a fresh `session_id`, hits a `getCart()`-using path, and leaves an empty cart row behind. Bots crawl `/cart` because the cart icon in the header is a link.

The companion call site that's already correct is `getCount()` (lines 237-258) — it uses `->first()` and returns `0` when no cart exists. That's the pattern to extend everywhere else.

---

## What to build

### 1. Split `getCart()` into two methods

```php
// CartService.php

/** Read-only lookup. Returns null if the visitor has no cart yet. */
public function findCart(): ?Cart
{
    if ($this->cart) return $this->cart;

    $customer = Auth::guard('customer')->user();

    if ($customer) {
        $this->cart = Cart::where('customer_id', $customer->id)
            ->where('store_id', $this->storeId)
            ->first();
    } else {
        $this->cart = Cart::where('session_id', session()->getId())
            ->where('store_id', $this->storeId)
            ->whereNull('customer_id')
            ->first();
    }

    return $this->cart;
}

/** Get or create. Use only when writing (adding items, applying coupons). */
public function getCart(): Cart
{
    // existing firstOrCreate body — unchanged
}
```

### 2. Refactor read paths in the service

- `getItems()` — call `findCart()`. If null, return `new Collection()`. Skip `refreshPrices()` when there's nothing to refresh.
- `getSubtotal()` — already uses `getItems()`, no change needed once `getItems()` is fixed.
- `update(int $cartItemId, int $quantity)` — call `findCart()`. If null, return `null` (consistent with the existing return type).
- `remove(int $cartItemId)` — call `findCart()`. If null, no-op.
- `clear()` — call `findCart()`. If null, no-op.

### 3. Update controller callers

- `CartController::update` and `CartController::remove` (lines 76, 124) — these use `$cart->id` to verify ownership of a `cart_item`. Replace with `findCart()` and `abort(403)` if null (the cart_item being acted on can't possibly belong to a non-existent cart).
- `CouponController::apply` (line 26) — replace with `findCart()`. If null OR the cart has no items, return the existing 422 "Your cart is empty." response.
- `CouponController::remove` (line 64) — replace with `findCart()`. If null, return the existing success response (idempotent — there was no coupon to remove).
- `CheckoutController::index` (line 67) — replace with `findCart()`. If null, the existing `$items` collection is already empty and the coupon block is skipped.
- `CheckoutController::placeOrder` (line 130) — replace with `findCart()`. If null, redirect to cart with the existing "Your cart is empty." flash.

### 4. Add a scheduled cleanup as a safety net

In case some new code path slips through and creates an empty cart, add a daily console command that purges abandoned empties:

```php
// app/Console/Commands/PruneEmptyCarts.php — schedule daily at 03:00
DB::table('ngd_carts')
    ->whereNull('customer_id')
    ->where('updated_at', '<', now()->subDay())
    ->whereNotExists(function ($q) {
        $q->select(DB::raw(1))->from('ngd_cart_items')
          ->whereColumn('cart_id', 'ngd_carts.id');
    })
    ->delete();
```

Two clauses are deliberate: only guests, only untouched for >24h, only with no items. Logged-in customers' carts are preserved (the abandoned-cart-notification feature needs them).

### 5. Verify

After deployment:
- Visit `/cart` while logged out, with browser cookies disabled (or via curl with no cookie jar). Confirm `SELECT COUNT(*) FROM ngd_carts WHERE customer_id IS NULL;` does not increment.
- Add a product to cart as a guest. Confirm exactly one row appears in `ngd_carts` and one in `ngd_cart_items`.
- Log in as a customer with no prior cart. Visit `/cart`. Confirm no row is created in `ngd_carts`.
- Log in, add a product. Confirm one customer-linked cart row.
- Run the `PruneEmptyCarts` command on a freshly populated test DB, confirm only the eligible rows are deleted.

---

## What NOT to do

- Don't break the abandoned-cart notification feature. Logged-in customers' carts must remain queryable by `App\Console\Commands\SendAbandonedCartNotifications`.
- Don't change `getCount()` — it's already correct.
- Don't conflate this with the pricing-precedence work in the sibling brief; they are independent.
- Don't add a `findCart()` call in `add()` — that path correctly wants `firstOrCreate`.

---

## Project context

- Laravel 12.
- Cart service: `app/Services/CartService.php`.
- Storefront controllers using the cart: `app/Http/Controllers/Storefront/CartController.php`, `CouponController.php`, `CheckoutController.php`, `CustomerAuthController.php` (the last one already uses `migrateGuestCart()` — leave it alone).
- Models: `App\Models\Cart`, `App\Models\CartItem`.

---

## Definition of done

- [ ] `findCart()` added; all read paths converted.
- [ ] All controller `getCart()` calls reviewed; only true write paths still use `getCart()`.
- [ ] `PruneEmptyCarts` console command + scheduled daily.
- [ ] Manual verification scenarios pass.
- [ ] Tests added for `findCart()` returning null when no cart exists, and for the read-path methods being safe under that condition.
