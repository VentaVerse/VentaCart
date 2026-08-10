# Pricing Precedence — Sandbox Demo Brief

**Status:** Brief for a sandbox prototype. Not yet a final design spec — the goal is a working demo to validate the model before committing to it in production.

**Audience:** A Claude Code agent working in a sandbox copy of this repo with database access. Read this brief cold; you do not need any prior conversation context.

**Database fixture:** `2026-05-01-pricing-precedence-database.sql.gz` (sibling file in this directory). Restore with:

```bash
gunzip -c 2026-05-01-pricing-precedence-database.sql.gz | mysql -u <user> -p <database>
```

It contains the full production data needed for the demo (orders, products, variants, specials, group prices, customers, customer groups). Bulk reference tables (`ngd_cities`, `ngd_ph_barangays`, `ngd_zones`, `ngd_geo_zone_entries`, `ngd_sessions`, `ngd_order_histories`) are present as **schema-only** to keep the file small — re-seed those locally if any feature you touch needs them. The carts table contains only the last 24 hours of activity (carts older than that were pruned in source before the dump).

---

## TL;DR

There is a silent pricing bug in this Laravel 12 e-commerce app (Venta / NewGearDay.com). When a product has both a **product-level special** for a customer group AND **per-variant group prices** for that same group, the product special silently overrides the variant prices. The result: variants of different sizes/values all get charged the same flat special price, and the admin has no visible way to detect this.

Build a sandbox prototype that fixes the precedence bug **and** restructures the admin pricing UI so the misconfiguration becomes impossible by construction.

---

## Reproducer — Order #29848

Real production order. Customer: Faye Carumba (id=3037), customer group 2 (Resellers).

Product 1779 ("Qable Solderless Instrument Cable Kit") was bought as two different variants:

| Variant | Length | Variant base price | Reseller variant price (`product_variant_group_prices`) | **Charged on order** | Should have charged |
|---|---|---|---|---|---|
| 107 | 10 Ft | ₱720 | ₱540 | **₱535** (×2 = 1,070) | ₱540 (×2 = 1,080) |
| 108 | 20 Ft | ₱1,000 | ₱750 | **₱535** (×2 = 1,070) | ₱750 (×2 = 1,500) |

Both variants got the same ₱535 — that came from a row in `product_specials` (`display_mode=override`, `customer_group_id=2`, dates 2022→2099). The variant 108 reseller was undercharged by ₱215 each, ₱430 on this order alone.

Verify the data in the sandbox:

```php
// Tinker
\App\Models\Order::with(['products', 'customer.customerGroup'])->find(29848);
\App\Models\ProductVariant::with('groupPrices')->where('product_id', 1779)->get();
\App\Models\ProductSpecial::where('product_id', 1779)->get();
```

---

## Where the bug lives

`app/Services/CartService.php`:

- `resolvePrice()` at lines 322–354 — picks the lowest of (variant group price already resolved into `$basePrice`) vs (any active product special). So any product special priced lower than the variant group price wins, regardless of whether the special was meant to apply to that variant.
- `refreshPrices()` at lines 213–228 — same flow, runs on every cart load.
- `add()` at lines 75–82 then 126 — same flow at add-to-cart time.

The `<` comparison on line 349 (`if ($best && $best->price < $bestPrice)`) is also worth a glance — equal-priced specials silently fail to apply, but that's a side issue.

---

## Root conceptual problem

`product_specials` is doing two jobs at once:

1. **Real promotions** — dated sales, e.g. `display_mode=sale`, dates 2025-11-08 → 2025-11-15.
2. **Catalog group prices in disguise** — `display_mode=override` rows with absurdly far end dates (2099, 2030), used as *"this is the reseller price for this product"* because there's no per-product per-group price table for non-variant products, and entering the equivalent in the Variants tab would require multiple rows.

Meanwhile `product_variant_group_prices` exists, but only at variant granularity, and it lives under a different admin tab (Variants), so admins reach for the Pricing tab's special-pricing form instead.

The two tables answer different questions but get treated as if they answer the same one. There's no clean precedence rule because the rules aren't even ranking the same kind of thing.

---

## Conceptual model to implement

Two layers, separate concerns.

```
  ┌─────────────────────────────────────────────────────────┐
  │  Layer 2 — PROMOTIONS (always time-bounded)             │
  │                                                         │
  │  Each promotion has:                                    │
  │    • Scope:    Whole product   OR   one specific variant│
  │    • Audience: All customers    OR   one customer group │
  │    • Window:   start date  →  end date  (REQUIRED)      │
  │    • Discount kind:                                     │
  │        (a) Percentage off the regular price             │
  │        (b) Fixed amount off the regular price           │
  │        (c) Absolute price                               │
  │            ⚠ ONLY valid when scope = single variant     │
  │            (forbidden at product scope, since variants  │
  │             have different regular prices)              │
  └─────────────────────────────────────────────────────────┘
                        ▲ overrides regular price
                          ONLY when active AND lower
  ┌─────────────────────────────────────────────────────────┐
  │  Layer 1 — REGULAR PRICE (always)                       │
  │                                                         │
  │  Per (variant × customer group), or                     │
  │  per (product × customer group) for products with no    │
  │  variants.                                              │
  │                                                         │
  │  Permanent until the admin changes it.                  │
  │  No dates, no priorities.                               │
  └─────────────────────────────────────────────────────────┘
```

### Precedence rule (mechanical, no if/else thicket)

```
For each cart item (variant × customer group):

  1. regular_price =
       variant_group_price[variant_id, group_id]     // Layer 1, most specific
       ?? product_group_price[product_id, group_id]  // Layer 1, product-wide
       ?? variant.price                              // base variant price
       ?? product.price                              // base product price

  2. promo_price =
       lowest applicable active promotion's effective price
       for THIS variant × THIS group
       (compute it: percentage → regular × (1 − x);
                    fixed     → regular − x;
                    absolute  → as-is)

  3. final_price = min(regular_price, promo_price)
```

Key invariants:

- A promotion can only overwrite the regular price for the same `(variant, group)` it applies to.
- A product-level promo with percentage or fixed-amount discount automatically scales across variants — each variant uses its own regular price as the base.
- A product-level promo with absolute price is **forbidden** by validation; the admin must either pick a variant or switch to percentage/fixed.

---

## What to build in the sandbox

### 1. Schema additions

- New table `product_group_prices` (analogous to `product_variant_group_prices` but for non-variant products):
  ```
  id, product_id (fk), customer_group_id (fk), price (decimal:2), timestamps
  unique(product_id, customer_group_id)
  ```
- Keep `product_variant_group_prices` as-is.
- `product_specials` becomes purely promotional. Add:
  - `product_variant_id` (nullable fk) — for variant-scoped promos.
  - `discount_type` enum: `'percentage' | 'fixed_amount' | 'absolute_price'`.
  - `discount_value` decimal:2.
  - Drop or repurpose existing `price` column once migrated.
  - Drop `display_mode` (no longer needed — promos always slash; admin can reuse base price for non-slash display by simply not creating a promo).
  - `date_start` and `date_end` become NOT NULL.
  - Add validation: `discount_type='absolute_price'` requires `product_variant_id IS NOT NULL`.

### 2. One-time migration script (idempotent, reversible)

Walk every existing `product_specials` row:

- If `display_mode='override'` and `date_end > 2030-01-01` (i.e., a fake-promo masquerading as a catalog price):
  - For products with variants: insert a `product_variant_group_prices` row for **every active variant** of the product at this `customer_group_id`, with the special's `price`. Skip if a row already exists for that `(variant, group)` — existing variant group prices win.
  - For products without variants: insert one `product_group_prices` row.
  - Delete the `product_specials` row.
- Otherwise (real promo with reasonable end date): convert to the new shape.
  - Existing `price` becomes `discount_type='absolute_price'`, `discount_value=price`, `product_variant_id=NULL`.
  - Note this case may now be invalid under the new validation if the product has variants — flag these for manual review (do not auto-delete).

Output a report: rows migrated to catalog prices, rows kept as promos, rows flagged for review.

### 3. Admin Pricing tab — restructured

Single tab on `resources/views/admin/products/form.blade.php`. Two clearly separated sections.

**Section A — Regular Prices**

For products **without** variants:

```
┌─ Regular Prices ─────────────────────────────────┐
│  Base price: [ ₱   720.00 ]                       │
│                                                   │
│  Customer group overrides (optional):             │
│    Default      [ —          ]  (uses base)      │
│    Resellers    [ ₱   540.00 ]                   │
│    VIP          [ ₱   500.00 ]                   │
└───────────────────────────────────────────────────┘
```

For products **with** variants — matrix:

```
┌─ Regular Prices ─────────────────────────────────────────────┐
│                  │  Default  │  Resellers  │  VIP            │
│  ────────────────┼───────────┼─────────────┼──────────────   │
│  10 Ft  (107)    │  720.00   │  540.00     │  500.00         │
│  20 Ft  (108)    │ 1000.00   │  750.00     │  700.00         │
│  30 Ft  (109)    │ 1280.00   │  960.00     │  900.00         │
└───────────────────────────────────────────────────────────────┘
                                   ↑ blank cells inherit fallback
```

**Section B — Promotions**

```
┌─ Promotions ─────────────────────────────────────────────────┐
│  + Add promotion                                             │
│  ────────────────────────────────────────────────────────────│
│  Scope:     ( ) Whole product   ( ) Specific variant [▼]     │
│  Audience:  ( ) All customers   ( ) Group [▼]                │
│  Discount:  ( ) % off  [ 10 ]                                │
│             ( ) ₱ off  [    ]                                │
│             ( ) Absolute price [    ]   ← disabled when      │
│                                            Scope = product   │
│  Active:    [2026-05-01] → [2026-05-08]                      │
│  Min qty:   [ 1 ]                                            │
└───────────────────────────────────────────────────────────────┘
```

The UI must physically prevent the broken state:
- "Absolute price" radio is disabled (with an inline tooltip explaining why) whenever Scope is set to "Whole product".
- Date fields are required.

### 4. Effective-price preview panel

Below or beside the Regular Prices matrix, a read-only summary:

```
┌─ What customers will pay today ───────────────────────────────┐
│                  │  Default          │  Resellers             │
│  ────────────────┼───────────────────┼───────────────────     │
│  10 Ft  (107)    │  720.00           │  540.00                │
│  20 Ft  (108)    │ 1000.00           │  750.00                │
│  30 Ft  (109)    │ 1152.00 (10% off) │  960.00                │
│                                                               │
│  Active promo: "Holiday Sale" — 30 Ft only, Default group,    │
│  May 1–7.                                                     │
└───────────────────────────────────────────────────────────────┘
```

Computes the same precedence rule as the cart, in real time as the admin edits. This is the safety net — it makes silent misconfiguration visually impossible.

### 5. Update `CartService`

- Rewrite `resolvePrice()` and `refreshPrices()` to follow the precedence rule above.
- The `<` vs `<=` issue on line 349 goes away naturally — the new rule is "promo wins if it's lower than the regular price for the same (variant, group)".
- Keep `add()` consistent.

### 6. Test against the reproducer

In the sandbox, after migration:
- Faye Carumba (group 2) adds variant 107 to cart → price ₱540.
- Adds variant 108 → price ₱750.
- Adds variant 109 → price ₱960.
- Admin creates a promotion: "Resellers, 5% off whole product, May 1–7" → variant 107 becomes ₱513, variant 108 becomes ₱712.50, variant 109 becomes ₱912.

---

## What NOT to do in the demo

- **Do not run the migration on production data.** Migration script must be reviewed manually before that.
- **Do not redesign the storefront product page.** Out of scope. Only the admin form and the cart pricing service.
- **Do not introduce a unified "pricing rules engine" abstraction.** Keep regular prices and promotions as two distinct concepts. The whole point is to *separate* them.
- **Do not extend promotions to coupon-code or free-shipping discounts.** Those exist as separate systems and are out of scope.
- **Do not add backwards-compatibility shims** in `CartService` for the old special-pricing behavior. After migration, the old shape is gone; the code should reflect that cleanly.

---

## Project context

- Laravel 12, Alpine.js for admin form interactivity, Tailwind for styling.
- Models of interest: `Product`, `ProductVariant`, `ProductSpecial`, `ProductVariantGroupPrice`, `CustomerGroup`, `Customer`, `Order`, `OrderProduct`.
- Admin form: `resources/views/admin/products/form.blade.php` (currently 905 lines, tabbed Alpine component — Pricing tab and Variants tab).
- Cart pricing: `app/Services/CartService.php`.
- Test data already in DB: order #29848, product 1779 with variants 107/108/109, customer group 2 (Resellers), customer Faye Carumba (id=3037).

---

## Definition of done for the demo

- [ ] Migration script written, dry-run tested against sandbox DB, output report saved.
- [ ] New `product_group_prices` table created.
- [ ] `product_specials` schema updated with new columns and constraints.
- [ ] Pricing tab in admin form rebuilt with Regular Prices section + Promotions section + effective-price preview.
- [ ] `CartService::resolvePrice` / `refreshPrices` / `add` rewritten to follow the new precedence rule.
- [ ] Reproducer scenario for order #29848 passes: Reseller buying variants 107 and 108 of product 1779 sees ₱540 and ₱750 in cart.
- [ ] Promotion scenario: a 5% Resellers promo applied to product 1779 yields the correct discounted prices on every variant.
- [ ] No tests removed; new tests added for: precedence rule, promo type validation (absolute-price-at-product-scope rejected), migration idempotency.
