# Order Historical-Data Snapshots — Design Spec

- **Date:** 2026-07-02
- **Status:** Approved in discussion; awaiting spec review
- **Principle (owner's words):** orders are historical records — they must keep displaying what was true *at order time*, even when the config that produced them (payment methods, products, couriers, countries) is later renamed or deleted. A missing photo is honest history; a substituted photo is corrupted history — **null beats wrong**.
- **Model:** OpenCart-style dual storage — machine key stays, human snapshot rides along. Verified against the real OC schema (`ngdadm_old`): OC snapshots `payment_method` (title text) + `payment_code` (slug) and `shipping_country`/`shipping_zone` (name text), resolves order-status names live, has no item images.

## What stays LIVE (deliberately)
- **Order status name** (`order_status_id` → relation) — statuses are operational config; renames *should* propagate. Same convention as OC.
- **Product thumbnails' fallback + storefront catalog links** — see §Item photo.
- **Country relations** (`shipping_country_id`) — kept for logic (couriers, filtering); only the *display name* is snapshotted.

## New columns (one migration, all nullable — old rows unaffected)
| Table | Column | Type |
|---|---|---|
| `orders` | `payment_method_title` | string |
| `orders` | `tracking_link` | string (plain-text URL) |
| `orders` | `shipping_country_name` | string |
| `orders` | `billing_country_name` | string |
| `order_products` | `image` | string (path) |

## 1. Payment title
- **Write:** at order creation.
  - Storefront: `CheckoutService::placeOrder()` (order data built at `CheckoutService.php:267-294`) — title = the chosen method instance's `getName()`.
  - Admin order entry: `OrderController::store()` — resolve the picked key via the available-methods list; if the key resolves, title = `getName()`; if the admin typed a free-text method (currently allowed), title = that raw text.
- **Read:** `Order::getPaymentMethodLabelAttribute()` (`Order.php:110`) prefers `payment_method_title` when non-empty; falls back to today's live derivation for pre-snapshot rows. All 12 existing display sites keep working unchanged (they already call the accessor).

## 2. Item photo (path snapshot — owner chose A over file-copy)
- **Write:** at `OrderProduct` creation — `CheckoutService.php:312-325` (checkout) and `OrderController::syncProducts()` (`OrderController.php:739-764`, used by admin create/update). Value = the product's primary image path (`products.image`) at that moment; null if none.
- **Read:** the 3 image display sites (`emails/order-confirmation.blade.php:198`, `CheckoutService::buildOrderItemsHtml()` at `CheckoutService.php:516`, `admin/orders/show.blade.php:168`) switch to `$item->image ?? $item->product?->image` — snapshot first, live fallback for old rows, clean placeholder/nothing when both gone.
- **Perf:** add `products.product:id,image` eager-load where order items render (kills the current N+1 on the admin order page).
- Storefront account order views don't render images today — unchanged (out of scope).

## 3. Tracking link (plain text) + the fulfillment seam
- **Write — manual (core):** wherever `tracking_number`/`shipping_courier_id` are set — `OrderController::addHistory()` (`:305-308`), `updateTracking()` (`:390-393`), and create/update when supplied — also resolve `ShippingCourier::getTrackingLink($number)` and store the resulting URL text into `orders.tracking_link` (null when tracking cleared or courier has no template).
- **Write — fulfillment extension (decoupled writer, same pattern as `StatusMapper`):** when a courier booking produces a tracking number, `FulfillmentService::book()` also writes core `tracking_number`; when a real tracker URL becomes available (e.g. QuadX `tracking_links.delivery`, which appears post-booking), the poll/refresh merge step also writes core `tracking_link`. Extension → core generic fields only; core never references the extension. Last writer wins. Uninstalling the extension leaves all links intact as text.
- **Read:** rewrite `Order::getTrackingLinkAttribute()` (`Order.php:131`) to prefer the raw `tracking_link` attribute, falling back to live courier-template resolution for old rows. Update the two display sites that bypass the accessor (`admin/orders/show.blade.php:241` builds the link manually) to use the accessor.
- **Order-page cleanliness rule:** the order detail page renders ONE tracking block fed by these generic fields — it never grows extension-specific sections. Rich courier detail (stepper, timeline, AWB) stays on the Fulfillment page.

## 4. Country names — also fixes a live bug
- **Bug found during scoping:** 8 view references still read `$order->shipping_country_code` / `billing_country_code` — columns **dropped** by `2026_04_01_100003_convert_country_code_to_country_id.php`. They silently render empty today: `admin/orders/show.blade.php:118,138`, `checkout/confirmation.blade.php:85` (3 themes), `account/order-detail.blade.php:46,62` (3 themes).
- **Write:** at order creation, both writers store `shipping_country_name` (+ `billing_country_name` when billing present) resolved from the country id.
- **Read:** the 8 broken references + the correct-but-live ones (`emails/order-confirmation.blade.php:19`, `CheckoutService.php:567`) switch to snapshot-first (`$order->shipping_country_name ?? $order->shippingCountry?->name`).

## Backfill (live-deploy one-off, tinker — NOT a migration, per house rule)
Best-effort, non-destructive, run once per environment after deploy:
- `shipping_country_name`/`billing_country_name` from the FKs (pure win — those fields display empty today).
- `payment_method_title` via the current label accessor for rows whose key still resolves (skips the nulled legacy rows).
- `tracking_link` from courier templates where `tracking_number` + courier still exist.
- `order_products.image`: **NOT backfilled** (decided) — stamping today's photo onto old orders would be exactly the "substituted history" this spec forbids; old rows keep the live fallback, same behavior as today.

## Out of scope
- Snapshotting order-status names (stays live by decision).
- Copying image files (path-only by decision).
- Storefront account pages gaining item images.
- Any change to the fulfillment page's own rich tracking UI.

## Acceptance
- New order (checkout + admin): row carries title, country names, item image paths.
- Delete the paid-with bank account → old order still shows its payment title.
- Delete/replace a product or its photo → old order shows the frozen path's image, or nothing — never a different product's photo.
- Set tracking, then delete the courier → order still shows the clickable link.
- Book via fulfillment → core order page shows the tracking number + FarEye link with no fulfillment-specific markup.
- Admin order page: country names visible again (bug fixed); no N+1 for item images.
- Old (pre-snapshot) rows: render identically to today via accessor fallbacks; better after the one-off backfill.
