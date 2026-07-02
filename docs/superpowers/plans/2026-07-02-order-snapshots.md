# Order Historical-Data Snapshots — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Orders keep permanent copies of payment title, item photo path, tracking link, and country names — history never changes when config (payment methods, products, couriers, countries) is renamed or deleted.

**Architecture:** One migration adds nullable snapshot columns; every order **writer** stamps them at write time; every **reader** prefers snapshot → falls back to today's live derivation (old rows render unchanged). The fulfillment extension feeds core tracking fields one-way (extension → core generic columns), same pattern as `StatusMapper`. Spec: `docs/superpowers/specs/2026-07-02-order-snapshots-design.md`.

**Tech Stack:** Laravel 12, PHP 8.5, MySQL, Blade (3 storefront themes must stay in sync). Tests: PHPUnit by explicit file path only.

## Global Constraints

- **NEVER run DB-refreshing/seeding tests** — the dev DB is live-copied data. Unit tests must be pure (no queries); everything else verifies via read-only tinker or the browser.
- All new columns **nullable**; old rows must render exactly as today via accessor fallbacks. No data backfill in migrations (one-off tinker at deploy per house rule).
- `order_products.image` is a **path** snapshot; never copy files. Old order_products rows are NOT backfilled (substituted history is forbidden).
- Storefront Blade edits must hit **all 3 themes** (`stage-ready`, `clean-light`, `crimson-dusk`) in the same task.
- After Blade changes: `php artisan view:clear` (+ `view:cache` as a compile check, then clear again). No CSS/JS changes → no `npm run build`.
- Commits: author Paolo, **NO** `Co-Authored-By` line.
- Run tests by explicit path: `./vendor/bin/phpunit tests/Unit/OrderSnapshotAccessorsTest.php` (the default suite has pre-existing unrelated failures — ignore them, never "fix" them).

---

### Task 1: Migration + fillable + snapshot-preferring accessors

**Files:**
- Create: `database/migrations/2026_07_02_100001_add_order_snapshot_columns.php`
- Modify: `app/Models/Order.php` (fillable ~line 11-25; accessors at 110-129 and 131-138)
- Modify: `app/Models/OrderProduct.php` (fillable)
- Test: `tests/Unit/OrderSnapshotAccessorsTest.php`

**Interfaces:**
- Produces: columns `orders.payment_method_title`, `orders.tracking_link`, `orders.shipping_country_name`, `orders.billing_country_name`, `order_products.image` (all nullable strings). Accessor contract: `$order->payment_method_label` prefers `payment_method_title`; `$order->tracking_link` (accessor name unchanged) prefers the raw stored column; both fall back to current live derivation.

- [ ] **Step 1: Write the failing unit test** (pure — `setRawAttributes`, no DB, no relations touched when snapshot present)

Create `tests/Unit/OrderSnapshotAccessorsTest.php`:
```php
<?php

namespace Tests\Unit;

use App\Models\Order;
use PHPUnit\Framework\TestCase;

class OrderSnapshotAccessorsTest extends TestCase
{
    public function test_payment_label_prefers_snapshot_title(): void
    {
        $order = new Order();
        $order->setRawAttributes(['payment_method' => 'bank_transfer_4', 'payment_method_title' => 'Maya']);
        $this->assertSame('Maya', $order->payment_method_label);
    }

    public function test_payment_label_falls_back_for_plain_keys_without_snapshot(): void
    {
        $order = new Order();
        $order->setRawAttributes(['payment_method' => 'cod', 'payment_method_title' => null]);
        $this->assertSame('Cash on Delivery', $order->payment_method_label);
    }

    public function test_tracking_link_prefers_stored_column(): void
    {
        $order = new Order();
        $order->setRawAttributes(['tracking_link' => 'https://track.example/ABC', 'tracking_number' => 'ABC']);
        $this->assertSame('https://track.example/ABC', $order->tracking_link);
    }

    public function test_tracking_link_null_when_no_snapshot_and_no_tracking_number(): void
    {
        $order = new Order();
        $order->setRawAttributes(['tracking_link' => null, 'tracking_number' => null]);
        $this->assertNull($order->tracking_link);
    }
}
```

- [ ] **Step 2: Run test, verify FAIL**

Run: `./vendor/bin/phpunit tests/Unit/OrderSnapshotAccessorsTest.php`
Expected: FAIL — `test_payment_label_prefers_snapshot_title` gets `'Bank Transfer'`-ish output (live derivation), `test_tracking_link_prefers_stored_column` returns null/queries.

- [ ] **Step 3: Migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Order historical snapshots: orders keep permanent copies of the human-facing
 * facts that were true at order time (payment title, tracking link, country
 * names, item photo path), so later config changes/deletions never rewrite
 * history. See docs/superpowers/specs/2026-07-02-order-snapshots-design.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_method_title')->nullable()->after('payment_method');
            $table->string('tracking_link', 2048)->nullable()->after('tracking_number');
            $table->string('shipping_country_name')->nullable()->after('shipping_country_id');
            $table->string('billing_country_name')->nullable()->after('billing_country_id');
        });

        Schema::table('order_products', function (Blueprint $table) {
            $table->string('image', 2048)->nullable()->after('sku');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_method_title', 'tracking_link', 'shipping_country_name', 'billing_country_name']);
        });
        Schema::table('order_products', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }
};
```

- [ ] **Step 4: Fillable additions**

In `app/Models/Order.php` `$fillable`, add alongside their siblings: `'payment_method_title'`, `'tracking_link'`, `'shipping_country_name'`, `'billing_country_name'`.
In `app/Models/OrderProduct.php` `$fillable`, add `'image'` next to `'sku'`.

- [ ] **Step 5: Accessor rewrites** (in `app/Models/Order.php`)

Replace the body-opening of `getPaymentMethodLabelAttribute()` (line ~110) so the snapshot short-circuits everything:
```php
    public function getPaymentMethodLabelAttribute(): string
    {
        // Snapshot first — what was true at order time (see order-snapshots spec).
        $title = $this->attributes['payment_method_title'] ?? null;
        if ($title) {
            return $title;
        }

        $key = $this->payment_method;
        // ... existing live-derivation body unchanged from here ...
```

Replace `getTrackingLinkAttribute()` (line ~131):
```php
    public function getTrackingLinkAttribute(): ?string
    {
        // Snapshot first — survives courier deletion/edits.
        $stored = $this->attributes['tracking_link'] ?? null;
        if ($stored) {
            return $stored;
        }

        if (!$this->tracking_number || !$this->shippingCourier) {
            return null;
        }

        return $this->shippingCourier->getTrackingLink($this->tracking_number);
    }
```

- [ ] **Step 6: Migrate + test PASS + schema check**

Run: `php artisan migrate --force` → the one new migration runs.
Run: `./vendor/bin/phpunit tests/Unit/OrderSnapshotAccessorsTest.php` → PASS (4 tests).
Run (read-only): `php artisan tinker --execute='foreach(["payment_method_title","tracking_link","shipping_country_name","billing_country_name"] as $c){echo $c.": ".(\Illuminate\Support\Facades\Schema::hasColumn("orders",$c)?"ok":"MISSING").PHP_EOL;} echo "order_products.image: ".(\Illuminate\Support\Facades\Schema::hasColumn("order_products","image")?"ok":"MISSING").PHP_EOL;'`

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_02_100001_add_order_snapshot_columns.php app/Models/Order.php app/Models/OrderProduct.php tests/Unit/OrderSnapshotAccessorsTest.php
git commit -m "feat(orders): snapshot columns + snapshot-first label/tracking accessors"
```

---

### Task 2: Checkout writer stamps snapshots

**Files:**
- Modify: `app/Services/CheckoutService.php` — order data array (~267-294), `Order::create` (~310), `OrderProduct::create` loop (~312-325)

**Interfaces:**
- Consumes: Task 1 columns/fillable. The chosen method instance is already resolved in `placeOrder()` (via `getPaymentMethod($paymentMethodKey)` — read the method to find the variable; it exposes `getName()`).
- Produces: every storefront order carries `payment_method_title`, `shipping_country_name`, (`billing_country_name` when billing set), and each order_product carries `image`.

- [ ] **Step 1: Read `placeOrder()` fully** (`CheckoutService.php:100-360`) — find where the payment-method instance is resolved and where `$orderData` is assembled; find how the items loop sources product data (the cart items include the product model or product_id — check `CartService` item shape if unclear).

- [ ] **Step 2: Stamp the order snapshots**

In the `$orderData` array add (adapt variable names to what Step 1 found):
```php
            'payment_method_title'  => $paymentMethod?->getName(),
            'shipping_country_name' => \App\Models\Country::find($shippingAddress['country_id'] ?? null)?->name,
```
Where billing is merged (~296-308), also add `'billing_country_name' => \App\Models\Country::find($billingAddress['country_id'] ?? null)?->name`.

- [ ] **Step 3: Stamp the item image**

In the `OrderProduct::create([...])` loop add `'image' => <product primary image path or null>` — source it from the cart item's product model (`$item['product']->image ?? null` or equivalent per the actual item shape found in Step 1). Null-safe: custom/deleted products store null.

- [ ] **Step 4: Verify with a read-only dry run** (no orders created)

`php -l app/Services/CheckoutService.php` clean. Then confirm the Country lookup pattern matches how the file already resolves countries (`Country::find($order->shipping_country_id)` at ~567) — consistency check by grep, no execution.
(The real end-to-end check happens in Task 6's browser pass.)

- [ ] **Step 5: Commit**

```bash
git add app/Services/CheckoutService.php
git commit -m "feat(orders): checkout stamps payment title, country names, item image snapshots"
```

---

### Task 3: Admin order writers stamp snapshots (create/update/tracking)

**Files:**
- Modify: `app/Http/Controllers/Admin/OrderController.php` — `store()` (~81-178), `update()` (~find it near 230-260), `syncProducts()` (~739-764), `addHistory()` (~288-369, tracking write at 305-308), `updateTracking()` (~379-416, write at 390-393)

**Interfaces:**
- Consumes: Task 1 columns. `CheckoutService::getAvailablePaymentMethods()` for resolving a picked key → name.
- Produces: admin-created/edited orders carry the same snapshots; every tracking write also writes `tracking_link`.

- [ ] **Step 1: `store()` — payment title + country names**

After `$validated` passes and before `Order::create($validated)` (~line 128), add:
```php
        // Snapshot human-facing facts at order time (order-snapshots spec).
        $validated['payment_method_title'] = $this->resolvePaymentTitle($request->input('payment_method'));
        $validated['shipping_country_name'] = \App\Models\Country::find($validated['shipping_country_id'] ?? null)?->name;
        $validated['billing_country_name'] = \App\Models\Country::find($validated['billing_country_id'] ?? null)?->name;
```
Add the private helper to the controller:
```php
    /** Human title for a picked payment key; free-text methods snapshot as themselves. */
    private function resolvePaymentTitle(?string $key): ?string
    {
        if (!$key) {
            return null;
        }
        foreach (app(\App\Services\CheckoutService::class)->getAvailablePaymentMethods() as $m) {
            if ($m->getKey() === $key) {
                return $m->getName();
            }
        }
        return $key; // admin typed a free-text method — the text IS the title
    }
```

- [ ] **Step 2: `update()` — re-snapshot when the underlying fields change**

Where `update()` applies `$validated` to the order, mirror Step 1's three lines (only set `payment_method_title` when the request actually includes `payment_method`; only set each country name when its id field is present in `$validated`).

- [ ] **Step 3: `syncProducts()` — item image**

In the `$order->products()->create([...])` array (~752) add:
```php
                'image' => \App\Models\Product::find($item['product_id'] ?? null)?->image,
```
(One query per line item on an admin save — acceptable; do NOT add eager complexity here.)

- [ ] **Step 4: tracking writes also snapshot the link**

In `addHistory()` right after the `$order->update([...])` that sets courier/tracking (~305-308), and in `updateTracking()` after its write (~390-393), add the same resolution:
```php
            $order->update([
                'tracking_link' => ($order->tracking_number && $order->shippingCourier)
                    ? $order->shippingCourier->getTrackingLink($order->tracking_number)
                    : null,
            ]);
```
(`$order->refresh()` first if the courier/tracking were just changed on the same request — `addHistory` already refreshes at ~309; mirror that in `updateTracking` if absent.)

- [ ] **Step 5: Verify**

`php -l app/Http/Controllers/Admin/OrderController.php` clean; `php artisan route:list | grep orders` still resolves (boot check). Read-only tinker: `echo (new \App\Http\Controllers\Admin\OrderController() instanceof \App\Http\Controllers\Controller) ? "ok" : "no";` — skip if constructor needs DI; `php artisan about --only=environment` boots.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/OrderController.php
git commit -m "feat(orders): admin order writers stamp snapshot columns incl. tracking_link"
```

---

### Task 4: Fulfillment extension feeds core tracking (decoupled writer)

**Files:**
- Modify: `extensions/courier-fulfillment/src/FulfillmentService.php` (`book()` forceFill block)
- Modify: `extensions/courier-fulfillment/src/Console/Commands/PollCourierStatus.php` (the link-merge/status-change write)
- Modify: `extensions/courier-fulfillment/src/Http/Controllers/FulfillmentController.php` (`track()` link-merge write)

**Interfaces:**
- Consumes: Task 1 columns. Existing link extraction: getTracking result `links` (array with `delivery`/`pickup` FarEye URLs) already merged into `courier_meta` by poll/track.
- Produces: booking writes core `tracking_number`; whenever a real tracker URL is known, core `tracking_link` is written too. Extension→core one-way; core never references the extension.

- [ ] **Step 1: `FulfillmentService::book()`** — in the success `forceFill([...])`, add:
```php
            'tracking_number' => $result['tracking_number'] ?? null,
```
(Core field; the same forceFill already writes courier_*. Do NOT write `tracking_link` here — no URL exists at booking time.)
On REBOOK (the `$previousTracking` path), this correctly overwrites the old number.

- [ ] **Step 2: Poll + track — write core `tracking_link` when links appear**

Both files already compute/merge `$result['links']` into `courier_meta`. Wherever that merge happens, extend the same forceFill/save to also set:
```php
            'tracking_link' => (function ($links) {
                foreach ([($links['delivery'] ?? null), ($links['pickup'] ?? null)] as $u) {
                    if (is_string($u) && str_starts_with($u, 'http')) return $u;
                }
                return null;
            })($result['links'] ?? null) ?? $order->tracking_link,
```
Semantics: prefer delivery, then pickup; if the courier returned no links this cycle, KEEP the existing core value (never null-out a previously stored link). Read both files first — adapt to the exact merge code shape; keep one implementation per file, small and commented.

- [ ] **Step 3: Verify (safe, uses the existing linked demo order #29848)**

```bash
php artisan courier:poll-status
php artisan tinker --execute='$o=\App\Models\Order::find(29848); echo "core tracking_number=".var_export($o->tracking_number,true)." core tracking_link=".substr((string)$o->tracking_link,0,60).PHP_EOL;'
```
Expected: tracking_number stays as-is (29848 was linked manually — poll only writes on its merge paths; if unchanged, that's fine — note it), and after one poll with links available, `tracking_link` = the FarEye URL. If #29848 has no fresh link event this cycle, verify instead by `php -l` + code-reading and note for the browser pass.
Also: `./vendor/bin/phpunit extensions/courier-fulfillment/tests/` still 9/9.

- [ ] **Step 4: Commit**

```bash
git add extensions/courier-fulfillment/src/FulfillmentService.php extensions/courier-fulfillment/src/Console/Commands/PollCourierStatus.php extensions/courier-fulfillment/src/Http/Controllers/FulfillmentController.php
git commit -m "feat(courier): fulfillment feeds core tracking_number + tracking_link snapshots"
```

---

### Task 5: Readers — snapshot-first displays + country-bug fix + N+1 fix

**Files:**
- Modify: `resources/views/admin/orders/show.blade.php` (:118, :138 country; :168-169 image; :241-242 tracking)
- Modify: `resources/views/emails/order-confirmation.blade.php` (:19/76 country; :198-199 image)
- Modify: `app/Services/CheckoutService.php` (`buildOrderItemsHtml()` ~516 image; `buildShippingAddressHtml()` ~567 country)
- Modify (×3 themes): `resources/themes/{stage-ready,clean-light,crimson-dusk}/views/checkout/confirmation.blade.php` (:85 country) and `resources/themes/{...}/views/account/order-detail.blade.php` (:46, :62 country; :81-82 already accessor — no change)
- Modify: `app/Http/Controllers/Admin/OrderController.php` `show()` — eager load

**Interfaces:**
- Consumes: Task 1 columns + accessors.

- [ ] **Step 1: Country displays — snapshot-first everywhere**

Replace every dead `$order->shipping_country_code` / `$order->billing_country_code` reference (admin show :118/:138; 3× confirmation :85; 3× order-detail :46/:62) with:
```blade
{{ $order->shipping_country_name ?? $order->shippingCountry?->name }}
```
(billing variant where applicable). In `emails/order-confirmation.blade.php:19` and `CheckoutService.php:567`, keep the resolved-variable structure but source it snapshot-first: `$order->shipping_country_name ?? Country::find(...)?->name`.

- [ ] **Step 2: Item images — snapshot-first**

`admin/orders/show.blade.php:168-169`, `emails/order-confirmation.blade.php:198-199`, `CheckoutService::buildOrderItemsHtml()` (~516): change the image source to
```php
$item->image ?? $item->product?->image
```
(blade equivalent: `@php $img = $item->image ?? $item->product?->image; @endphp` then render `$img` or nothing).

- [ ] **Step 3: Tracking display via accessor**

`admin/orders/show.blade.php:241-242` currently calls `$order->shippingCourier?->getTrackingLink(...)` directly — replace with `$order->tracking_link` (the accessor now handles snapshot + fallback).

- [ ] **Step 4: N+1 fix**

In `OrderController::show()`, extend the eager-load to include `products.product:id,image` (read the current `->load(...)`/`with(...)` and add the relation).

- [ ] **Step 5: Verify**

`php artisan view:clear && php artisan view:cache 2>&1 | tail -2` (clean) `&& php artisan view:clear`. Read-only browser-less check: `curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8001/opensesame/orders` → 302 (route+boot fine). Full visual pass in Task 6.

- [ ] **Step 6: Commit**

```bash
git add resources/views/admin/orders/show.blade.php resources/views/emails/order-confirmation.blade.php app/Services/CheckoutService.php app/Http/Controllers/Admin/OrderController.php resources/themes/stage-ready/views/checkout/confirmation.blade.php resources/themes/clean-light/views/checkout/confirmation.blade.php resources/themes/crimson-dusk/views/checkout/confirmation.blade.php resources/themes/stage-ready/views/account/order-detail.blade.php resources/themes/clean-light/views/account/order-detail.blade.php resources/themes/crimson-dusk/views/account/order-detail.blade.php
git commit -m "feat(orders): snapshot-first displays; fix dead country_code refs (silent empty country bug) + item-image N+1"
```

---

### Task 6: Sandbox backfill (one-off, NOT committed) + end-to-end verification

**Files:** none committed — tinker only + controller's browser pass.

**Interfaces:** consumes everything above.

- [ ] **Step 1: One-off backfill (sandbox now; SAME script re-run on live at deploy — record in ledger)**

```bash
php artisan tinker --execute='
$n1 = 0;
\App\Models\Order::whereNull("shipping_country_name")->whereNotNull("shipping_country_id")->chunkById(500, function ($orders) use (&$n1) {
  $names = \App\Models\Country::pluck("name","id");
  foreach ($orders as $o) { $o->forceFill(["shipping_country_name"=>$names[$o->shipping_country_id] ?? null, "billing_country_name"=>$o->billing_country_id ? ($names[$o->billing_country_id] ?? null) : null])->saveQuietly(); $n1++; }
});
echo "country names backfilled: $n1".PHP_EOL;
$n2 = 0;
\App\Models\Order::whereNull("payment_method_title")->whereNotNull("payment_method")->chunkById(500, function ($orders) use (&$n2) {
  foreach ($orders as $o) { $t = $o->payment_method_label; if ($t) { $o->forceFill(["payment_method_title"=>$t])->saveQuietly(); $n2++; } }
});
echo "payment titles backfilled: $n2".PHP_EOL;
$n3 = 0;
\App\Models\Order::whereNull("tracking_link")->whereNotNull("tracking_number")->whereNotNull("shipping_courier_id")->chunkById(500, function ($orders) use (&$n3) {
  foreach ($orders as $o) { $u = $o->shippingCourier?->getTrackingLink($o->tracking_number); if ($u) { $o->forceFill(["tracking_link"=>$u])->saveQuietly(); $n3++; } }
});
echo "tracking links backfilled: $n3".PHP_EOL;
echo "images: intentionally NOT backfilled".PHP_EOL;'
```
(Note: the payment-title pass runs BEFORE any future method deletion, capturing today's resolvable labels. `saveQuietly` avoids model events.)

- [ ] **Step 2: Verification queries (read-only)**

```bash
php artisan tinker --execute='
echo "orders with country_name: ".\App\Models\Order::whereNotNull("shipping_country_name")->count()."/".\App\Models\Order::count().PHP_EOL;
echo "orders with payment title: ".\App\Models\Order::whereNotNull("payment_method_title")->count().PHP_EOL;
$o=\App\Models\Order::whereNotNull("payment_method_title")->latest("id")->first();
echo "sample #{$o->id}: title={$o->payment_method_title} country={$o->shipping_country_name}".PHP_EOL;'
```

- [ ] **Step 3: Browser pass (controller does this)** — admin order page shows country again (bug fix visible), payment label, tracking link; place a test checkout order (dev storefront account) and confirm the new row carries all snapshots incl. item image paths.

- [ ] **Step 4: Ledger/memory note** — append to `.superpowers/sdd/progress.md`: live-deploy one-off = Task 6 Step 1 script (run after migration, before any method/account deletion). No commit.

---

## Self-Review

- **Spec coverage:** §1 payment title → T1 (column/accessor) + T2/T3 (writers). §2 image → T1 + T2/T3 (writers) + T5 (readers, N+1). §3 tracking link → T1 + T3 (manual writers) + T4 (fulfillment feed) + T5 (reader). §4 country → T1 + T2/T3 + T5 (bug fix). Backfill → T6 (images excluded per spec). Accessor fallbacks → T1. All covered.
- **Placeholder scan:** T2 Steps 1-3 direct the implementer to adapt to the actual variable names in `placeOrder()`/cart item shape (discovery step, explicit) — acceptable, not a TBD. No TODO/TBD anywhere.
- **Type consistency:** column names identical across T1-T6; accessor names unchanged (`payment_method_label`, `tracking_link`); `$result['links']` shape matches the existing courier merge (delivery/pickup keys) used in T4.
