# Admin v2 Sales Channels & SEO Implementation Plan (Plan 3 of 4)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The three heavy redesigns from spec §3 (`docs/superpowers/specs/2026-08-06-admin-ux-rebuild-design.md`): Payments (methods + restrictions merged), Shipping (five pages unified around the real quote chain + rate tester), and the SEO workspace — plus two repairs the code mapping surfaced: the DHL `shipping_meta` key-mismatch bug and removal of the dead Shipping Zones page.

**Architecture:** Payments and Shipping become full new-shell pages reached from the Settings hub (their hub cards already point at `admin.payment-settings.index` / `admin.shipping-settings.index` — URLs unchanged). Payment methods render as cards with per-method detail pages (Configuration | Availability tabs — Availability absorbs the old Payment Restrictions page, whose routes redirect). Shipping renders the provider chain in its true evaluation order with per-provider panels, an Origin card (finally giving the orphaned `pickup_*` settings a UI), and a rate tester that calls `ShippingQuoteService::getQuotes()` server-side. SEO's 13-tab strip becomes a grouped sub-nav using the settings-shell pattern; the dashboard and review queue get real redesigns, remaining SEO pages get the shell + light reskin. All storage schemas, services, and the shared `admin.settings.update` endpoint are unchanged except where a task explicitly says otherwise.

**Tech Stack:** Laravel 12, Blade + `<x-admin.*>` components (Plans 1–2), Alpine.js, Tailwind v3 `v-*` bridge, PHPUnit 11.

## Global Constraints

- **Never put CSS inside Blade files**; only `v-*` token classes for colors/radii; new CSS in `resources/css/admin/components/*.css` imported via `admin.css`.
- **ABSOLUTE RULE: no destructive queries against the live database** (tinker or otherwise). Feature tests use `RefreshDatabase` (SQLite `:memory:`).
- Contracts that must not break (consumers verified in the code maps):
  - `PaymentRestrictionService::isAllowed()` signature and semantics (missing row = allowed; truthiness-gated numerics; base-key fallback for `bank_transfer_<id>` keys) — `app/Services/PaymentRestrictionService.php:25`.
  - `CheckoutService::getAvailablePaymentMethods()` / `placeOrder()` flows; settings-key convention `{baseKey}_success_status_id` (`app/Services/CheckoutService.php:222-229`).
  - `ShippingQuoteService::getQuotes(array $address, Collection $items, float $subtotal, array $context)` and quote-key formats `free_shipping`, `weight_based_{id}`, `flat_rate_{id}`, `dhl_{service}`.
  - `SettingController@update` shared endpoint (post with `group` + `settings[key]` + `_redirect`); its `ENCRYPTED_KEYS` list; blank-skips-encrypted-overwrite behavior.
  - Bank account CRUD (`admin.bank-accounts.*`, `BankAccountController`, `qr_image` on `bank-qr` public disk).
- Old admin layout (`resources/views/layouts/admin.blade.php`) untouched — its Payment/Shipping links keep working because URLs don't change.
- Suite baseline: 26 pre-existing failures; all `tests/Feature/AdminV2/` green. `php artisan view:clear` after Blade changes; `npm run build` after CSS/JS.
- Browser checks: local server port **8001** only (8000 is a different app), dev@venta.test / venta_dev_2026, prefix `opensesame`.
- Commit per task, `feat(admin-v2): …` / `fix(...)`, no Co-Authored-By.

---

### Task 1: `x-admin.tabs` component

**Files:**
- Create: `resources/views/components/admin/tabs.blade.php`, `resources/views/components/admin/tabs/tab.blade.php`, `resources/views/components/admin/tabs/panel.blade.php`
- Test: `tests/Feature/AdminV2/TabsComponentTest.php`

**Interfaces:**
- Produces:

```blade
<x-admin.tabs default="config" param="tab">
    <x-slot:nav>
        <x-admin.tabs.tab name="config">Configuration</x-admin.tabs.tab>
        <x-admin.tabs.tab name="availability">Availability</x-admin.tabs.tab>
    </x-slot:nav>
    <x-admin.tabs.panel name="config">…</x-admin.tabs.panel>
    <x-admin.tabs.panel name="availability">…</x-admin.tabs.panel>
</x-admin.tabs>
```

Alpine-driven: `x-data="{ tab: <initial> }"` where initial = `request()->query($param, $default)`; clicking a tab sets `tab` and pushes `?{param}=` into the URL via `history.replaceState` (so refresh/deep-link works); tab buttons get `aria-selected` and accent underline when active; panels `x-show` + `x-cloak`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\AdminV2;

use Tests\TestCase;

class TabsComponentTest extends TestCase
{
    public function test_tabs_render_nav_and_panels_with_default_active(): void
    {
        $this->get('/'); // seed request
        $this->blade(<<<'BLADE'
            <x-admin.tabs default="config" param="tab">
                <x-slot:nav>
                    <x-admin.tabs.tab name="config">Configuration</x-admin.tabs.tab>
                    <x-admin.tabs.tab name="availability">Availability</x-admin.tabs.tab>
                </x-slot:nav>
                <x-admin.tabs.panel name="config">CONFIG BODY</x-admin.tabs.panel>
                <x-admin.tabs.panel name="availability">AVAIL BODY</x-admin.tabs.panel>
            </x-admin.tabs>
            BLADE)
            ->assertSeeInOrder(['Configuration', 'Availability', 'CONFIG BODY', 'AVAIL BODY'])
            ->assertSee("tab: 'config'", false)
            ->assertSee('aria-selected', false)
            ->assertSee('x-cloak', false);
    }
}
```

- [ ] **Step 2: Run to verify it fails** (`php artisan test --filter=TabsComponentTest`).

- [ ] **Step 3: Implement**

`tabs.blade.php`:

```blade
@props(['default', 'param' => 'tab'])
<div x-data="{
        tab: @js(request()->query($param, $default)),
        param: @js($param),
        pick(name) {
            this.tab = name;
            const url = new URL(window.location);
            url.searchParams.set(this.param, name);
            history.replaceState({}, '', url);
        },
    }" {{ $attributes }}>
    <div class="flex items-center gap-1 border-b border-v-border" role="tablist">{{ $nav }}</div>
    <div class="pt-5">{{ $slot }}</div>
</div>
```

`tabs/tab.blade.php`:

```blade
@props(['name'])
<button type="button" role="tab" @click="pick('{{ $name }}')"
        :aria-selected="tab === '{{ $name }}' ? 'true' : 'false'"
        :class="tab === '{{ $name }}'
            ? 'border-v-accent text-v-accent'
            : 'border-transparent text-v-secondary hover:text-v-text'"
        class="-mb-px border-b-2 px-3 py-2 text-[13px] font-medium transition-colors"
        {{ $attributes }}>
    {{ $slot }}
</button>
```

`tabs/panel.blade.php`:

```blade
@props(['name'])
<div x-show="tab === '{{ $name }}'" x-cloak role="tabpanel" {{ $attributes }}>{{ $slot }}</div>
```

- [ ] **Step 4: Run test to verify it passes**, then **Step 5: Commit** (`feat(admin-v2): tabs component`).

---

### Task 2: DHL shipping_meta bug fix + dead Shipping Zones removal

**Files:**
- Modify: `app/Services/Shipping/DhlShippingProvider.php` (getKey), `routes/web.php` (remove shipping-zones routes), `resources/views/admin/shipping-zones/` (delete directory), `app/Http/Controllers/Admin/ShippingZoneController.php` (delete), `app/Models/ShippingZoneRate.php` (delete), `app/Models/GeoZone.php` (remove `rates()` relation), `resources/views/layouts/admin.blade.php` — **exception to the untouched rule, minimal**: delete the single `admin.shipping-zones*` pattern entry from the Extensions group nav array (a dead link otherwise)
- Create: migration `drop_shipping_zone_rates_table`
- Test: `tests/Feature/AdminV2/ShippingRepairsTest.php`

**Interfaces:**
- Consumes: `CheckoutService` provider-matching loop (`app/Services/CheckoutService.php:210-218`): `str_starts_with($key, $provider->getKey() . '_') || $key === $provider->getKey()`.
- Produces: `DhlShippingProvider::getKey()` returns `'dhl'` (was `'dhl_express'`) so quote keys `dhl_{service}` finally match and DHL orders get `shipping_meta`. Shipping Zones page, controller, model, routes, and the orphaned `shipping_zone_rates` table are gone.

- [ ] **Step 1: Impact scan (required, before any change)** — grep for every usage: `grep -rn "dhl_express\|ShippingZoneRate\|shipping_zone_rates\|shipping-zones" app/ resources/ routes/ database/ config/ tests/`. Expected consumers of `getKey()`: the `CheckoutService` matching loop and possibly logs. `PaymentRestrictionController::getFormData()` hardcodes shipping method key `'dhl'` already — confirming `'dhl'` is the intended family key. If the scan finds anything else reading `dhl_express` or the zone-rate model beyond `ShippingZoneController`/`GeoZone::rates()`, STOP and report NEEDS_CONTEXT with the list.

- [ ] **Step 2: Write the failing test**

```php
<?php

namespace Tests\Feature\AdminV2;

use App\Services\Shipping\DhlShippingProvider;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ShippingRepairsTest extends TestCase
{
    public function test_dhl_provider_key_matches_its_quote_key_prefix(): void
    {
        $provider = new DhlShippingProvider();
        // Quote keys are "dhl_{service}"; CheckoutService matches getKey().'_' prefix.
        $this->assertSame('dhl', $provider->getKey());
        $this->assertTrue(str_starts_with('dhl_p', $provider->getKey() . '_'));
    }

    public function test_shipping_zones_routes_are_gone(): void
    {
        $this->assertFalse(Route::has('admin.shipping-zones.index'));
        $this->assertFalse(Route::has('admin.shipping-zones.edit'));
    }
}
```

(If `DhlShippingProvider`'s constructor requires arguments, instantiate via `app()`.)

- [ ] **Step 3: Run to verify it fails.**

- [ ] **Step 4: Apply the changes.** getKey one-liner; delete controller/model/views/routes; remove `GeoZone::rates()`; remove the nav pattern entry in the old layout (do not touch anything else in that file); migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('shipping_zone_rates');
    }

    public function down(): void
    {
        // Intentionally irreversible: the table was orphaned (no quote provider
        // ever read it). Restore from backup if ever needed.
    }
};
```

- [ ] **Step 5: Run tests + full AdminV2 filter** (all green; the migration runs in RefreshDatabase). **Step 6: Commit** (`fix(shipping): dhl meta key mismatch; remove dead shipping-zones feature`).

---

### Task 3: Payments landing page — method cards

**Files:**
- Modify: `app/Http/Controllers/Admin/PaymentSettingController.php` (add data for cards; keep everything else), `resources/views/admin/payment-settings/index.blade.php` (full rewrite, new shell)
- Test: `tests/Feature/AdminV2/PaymentsPageTest.php`

**Interfaces:**
- Consumes: settings group `payment` keys (`bank_transfer_enabled`, `hitpay_enabled`, `cod_enabled`, `*_display_name`, `cod_sort_order`); `PaymentMethodRestriction` rows (for an availability summary chip); `x-admin.settings-shell` is NOT used (Payments is a full page: use `x-admin.shell` + `x-admin.page` with breadcrumb to Settings).
- Produces: `admin.payment-settings.index` renders one card per method family (`bank_transfer`, `hitpay`, `cod`): display name (with default fallback), enable state as an `x-admin.toggle` inside a tiny per-card form posting `admin.settings.update` (`group=payment`, `settings[<family>_enabled]`, `_redirect` back), an availability chip ("Available everywhere" when no active restriction row; else `x-admin.badge tone=warning` "Restricted" + the row's `getConditionsSummary()` first line), and a "Manage →" button to Task 4's detail route `admin.payment-settings.show` with the family key.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\AdminV2;

use App\Models\PaymentMethodRestriction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_renders_method_cards_in_new_shell(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.payment-settings.index'))
            ->assertOk()
            ->assertSee('v-admin', false)
            ->assertSee('Bank Transfer')->assertSee('Cash on Delivery')->assertSee('HitPay')
            ->assertSee('Manage');
    }

    public function test_restricted_method_shows_availability_chip(): void
    {
        $this->actingAs(User::factory()->create());
        PaymentMethodRestriction::create([
            'payment_method' => 'cod',
            'min_total' => 500,
            'is_active' => true,
        ]);

        $this->get(route('admin.payment-settings.index'))->assertSee('Restricted');
    }
}
```

- [ ] **Step 2: Run to verify it fails.**

- [ ] **Step 3: Implement.** Controller: read display names via the same fallback chain the payment classes use (`Setting` group `payment`, `<family>_display_name`, defaults 'Bank Transfer' / 'HitPay (Card / GCash / GrabPay / Maya)' / 'Cash on Delivery'); enabled from `<family>_enabled === '1'`; restriction row per family (`PaymentMethodRestriction::whereIn('payment_method', [...])`). Register the detail route NOW as a placeholder (same trick as Plan 2 Task 1): `Route::get('payment-settings/{method}', fn () => abort(404))->name('payment-settings.show');` placed AFTER the index route. View: card grid (pattern: Modules page `resources/views/admin/modules/index.blade.php`), per-card enable-toggle form (auto-submit on change: `x-on:change="$el.closest('form').submit()"`), availability chip, Manage button. NOTE: the toggle-only form must also carry `<input type="hidden" name="settings[{family}_enabled]" value="0">` semantics — `x-admin.toggle` already posts hidden 0 + checkbox 1; name the toggle `settings[{family}_enabled]`.

- [ ] **Step 4: Run tests** (+ AdminV2 filter). **Step 5: Commit** (`feat(admin-v2): payments landing with method cards`).

---

### Task 4: Payment method detail — Configuration tab

**Files:**
- Create: `app/Http/Controllers/Admin/PaymentMethodPageController.php`, `resources/views/admin/payment-settings/show.blade.php`, `resources/views/admin/payment-settings/partials/{config-bank-transfer,config-hitpay,config-cod}.blade.php`
- Modify: `routes/web.php` (replace placeholder with controller)
- Test: extend `tests/Feature/AdminV2/PaymentsPageTest.php`

**Interfaces:**
- Consumes: everything the OLD `payment-settings/index.blade.php` tabs contained — READ IT FIRST (`git show HEAD:resources/views/admin/payment-settings/index.blade.php` if already rewritten by Task 3 — use `git show <task-3-base>:...`; Task 3's implementer must not delete the old file's knowledge: consult git history). Exact settings keys per method (from the code map): bank_transfer: `bank_transfer_enabled/display_name/success_status_id` + `bank_accounts` CRUD (posts to `admin.bank-accounts.store`, multipart, fields `bank_name, account_name, account_number, qr_image, instructions, is_active, sort_order`; edit via `admin.bank-accounts.edit` page — keep linking to it); hitpay: `hitpay_enabled/display_name/mode(sandbox|production)/sandbox_api_key/sandbox_salt/production_api_key/production_salt/success_status_id` (4 encrypted keys masked exactly like the old controller: `PaymentSettingController::ENCRYPTED_KEYS` + mask pattern); cod: `cod_enabled/display_name/success_status_id/sort_order`. `$orderStatuses` for the success-status selects (store-scoped, sort_order).
- Produces: `admin.payment-settings.show` (`{method}` in `bank_transfer|hitpay|cod`, else 404) rendering `x-admin.shell` + `x-admin.page` (breadcrumb Settings → Payments) + `x-admin.tabs` (Configuration | Availability; Availability panel filled by Task 5 — placeholder card "Availability rules — next task" for now). Configuration = the method's forms rebuilt on `x-admin.*` components, all posting to their EXISTING endpoints (`admin.settings.update` group=payment with `_redirect` to the detail page; bank account forms to `admin.bank-accounts.*`). Encrypted key inputs: password fields, masked current value as help text, blank keeps (endpoint behavior).

- [ ] **Step 1: Extend the test**

```php
    public function test_detail_page_renders_config_tab_per_method(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.payment-settings.show', 'cod'))
            ->assertOk()->assertSee('Configuration')->assertSee('Availability')
            ->assertSee('settings[cod_display_name]', false);

        $this->get(route('admin.payment-settings.show', 'hitpay'))
            ->assertOk()->assertSee('settings[hitpay_mode]', false);

        $this->get(route('admin.payment-settings.show', 'nope'))->assertNotFound();
    }
```

- [ ] **Step 2: Run to verify it fails.** **Step 3: Implement** — controller loads the same data the old `PaymentSettingController@index` did (copy its masking code), scoped per method; view uses tabs; partials per method following the Task-4-of-Plan-2 mail-section form pattern (`resources/views/admin/settings/sections/mail.blade.php`). Bank transfer's account list = `x-admin.table` with QR thumbnail, edit link, delete via confirm-dialog; "Add account" form multipart in a card. HitPay mode select drives sandbox/production credential cards via Alpine `x-show` (wrap both; show by `settings[hitpay_mode]` select binding — `x-data="{ mode: '<current>' }"` + `x-model="mode"` on the select).

- [ ] **Step 4: Run tests** (+ AdminV2). **Step 5: Commit** (`feat(admin-v2): payment method detail configuration tab`).

---

### Task 5: Availability tab — restrictions merged in

**Files:**
- Modify: `app/Http/Controllers/Admin/PaymentMethodPageController.php` (availability data), `resources/views/admin/payment-settings/show.blade.php` (fill Availability panel), `routes/web.php` (retire payment-restrictions GET pages)
- Create: `resources/views/admin/payment-settings/partials/availability.blade.php`
- Test: extend `PaymentsPageTest`

**Interfaces:**
- Consumes: `PaymentRestrictionController`'s existing `store/update/destroy/toggleActive` endpoints and validation (UNCHANGED — the Availability form posts to them); `getFormData()` datasets (brands, categories root+children flattened, customerGroups, geoZones, hardcoded shippingMethods `free_shipping|weight_based|flat_rate|dhl`, currencies) — move that method's logic into a shared support (`app/Support/PaymentRestrictionFormData.php`, same array shapes) used by both controllers so nothing drifts; `PaymentMethodRestriction::getConditionsSummary()`; the dual-listbox partial `admin.partials.dual-listbox` (reuse as-is this plan; restyling it is Plan 4).
- Produces: the Availability panel shows (a) a readable rule summary card — sentences built from the restriction row (one bullet per active condition family, e.g. "Minimum order total: ₱500", "Hidden for geo zones: Luzon, Visayas"; "No restrictions — available everywhere" when no row), (b) active toggle posting `admin.payment-restrictions.toggle`, (c) the full edit form (numeric limits grid + include/exclude dual-listboxes + shipping-method/currency pickers) posting to `admin.payment-restrictions.store` or `.update` exactly as the old form did — field names identical (`geo_zone_ids[]`, `exclude_*`, `min_total`… from the old `form.blade.php`), READ the old views first. GET routes `admin.payment-restrictions.index/create/edit` become 302s to the corresponding `admin.payment-settings.show` page (`?tab=availability`); resource POST/PUT/DELETE/toggle routes stay. Redirect-after-save in `PaymentRestrictionController` store/update/destroy: change targets from `admin.payment-restrictions.index` to `route('admin.payment-settings.show', [$method, 'tab' => 'availability'])` — the ONLY controller change allowed there.
- Old views `resources/views/admin/payment-restrictions/{index,form}.blade.php`: delete after the redirects land.

- [ ] **Step 1: Extend the test**

```php
    public function test_availability_tab_shows_rule_sentences_and_form(): void
    {
        $this->actingAs(User::factory()->create());
        PaymentMethodRestriction::create(['payment_method' => 'cod', 'min_total' => 500, 'is_active' => true]);

        $this->get(route('admin.payment-settings.show', ['method' => 'cod', 'tab' => 'availability']))
            ->assertOk()->assertSee('Minimum order total')->assertSee('min_total', false);
    }

    public function test_old_restrictions_pages_redirect_into_payments(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(route('admin.payment-restrictions.index'))
            ->assertRedirect(route('admin.payment-settings.index'));
    }

    public function test_restriction_save_roundtrip_still_works(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post(route('admin.payment-restrictions.store'), [
            'payment_method' => 'hitpay', 'min_total' => 100, 'is_active' => 1,
        ])->assertRedirect(route('admin.payment-settings.show', ['method' => 'hitpay', 'tab' => 'availability']));
        $this->assertDatabaseHas('payment_method_restrictions', ['payment_method' => 'hitpay']);
    }
```

- [ ] **Step 2: Run to verify it fails.** **Step 3: Implement** (sentence builder as small Blade partial logic from the model's cast arrays + `getConditionsSummary()` for the compact line; form field names copied from the old `form.blade.php` — including the bespoke shipping-method Alpine picker block, ported as-is). **Step 4: Run tests + AdminV2.** **Step 5: Commit** (`feat(admin-v2): availability tab absorbs payment restrictions`).

---

### Task 6: Shipping page — provider chain + origin

**Files:**
- Modify: `app/Http/Controllers/Admin/ShippingSettingController.php` (add chain/provider data), `resources/views/admin/shipping-settings/index.blade.php` (full rewrite, new shell)
- Test: `tests/Feature/AdminV2/ShippingPageTest.php`

**Interfaces:**
- Consumes: real chain order Free Shipping → Weight Based → Flat Rate → DHL (`ShippingQuoteService::getProviders()`); per-provider enable flags (`weight_based_enabled`, `flat_rate_enabled`, `dhl_enabled` settings; free shipping = `FreeShippingRule::where('is_active', true)->exists()` — display-only state, no toggle); config data: `$flatRates` (with geo zone + group names), `$weightMethods` (+tier accordion posting to EXISTING `admin.weight-based-methods.bulk-update`), free-shipping rules list (link to `admin.free-shipping-rules.index` + inline toggle via `admin.free-shipping-rules.toggle`), DHL credentials (masked `dhl_site_id`/`dhl_password` — old controller's pattern — + `dhl_account_number`) and `admin.settings.test-dhl`; orphaned `pickup_*` keys (address, barangay, city, province, postal_code, contact_name, contact_number, contact_email — group `shipping`).
- Produces: one page, new shell, breadcrumb Settings → Shipping: (1) **chain strip** — numbered provider cards in evaluation order with enable toggles (auto-submit forms → `admin.settings.update` group=shipping) and the free-shipping-overrides-all rule stated on the Free Shipping card; (2) per-provider **config panels** below (anchor-linked from the strip): flat-rates table (+create/edit links to existing `admin.flat-rates.*`), weight-based accordion (port the old tier editor markup onto new components, keep `bulk-update` payload identical — READ the old view first), free-shipping rules table with toggle, DHL card (credentials + account + test button); (3) **Origin card** — the `pickup_*` fields as a proper form (posting `admin.settings.update` group=shipping) with help text "Used as the origin for courier rate quotes (DHL) and future fulfillment bookings."

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\AdminV2;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_renders_chain_in_real_order_with_origin_card(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.shipping-settings.index'))
            ->assertOk()->assertSee('v-admin', false)
            ->assertSeeInOrder(['Free Shipping', 'Weight Based', 'Flat Rate', 'DHL'])
            ->assertSee('Origin')->assertSee('settings[pickup_city]', false);
    }
}
```

- [ ] **Step 2: Run to verify it fails.** **Step 3: Implement** (READ old view for weight-based tier editor + DHL field names before rewriting; redirect `_redirect` targets = shipping page). **Step 4: Run tests + AdminV2.** **Step 5: Commit** (`feat(admin-v2): unified shipping page with provider chain and origin`).

---

### Task 7: Rate tester

**Files:**
- Create: `app/Http/Controllers/Admin/ShippingTestController.php`, `resources/views/admin/shipping-settings/partials/rate-tester.blade.php` (included at top of the shipping page)
- Modify: `routes/web.php` (POST `shipping-settings/test` → `admin.shipping-settings.test`), `resources/views/admin/shipping-settings/index.blade.php` (include partial)
- Test: extend `ShippingPageTest`

**Interfaces:**
- Consumes: `ShippingQuoteService::getQuotes(array $address, Collection $items, float $subtotal, array $context)`; address keys `country_id`, `province`, `city`, `postcode`; items = plain objects with `weight/length/width/height/quantity` (per `aggregateDimensions()`); countries list for the select (`Country` model — verify name).
- Produces: a card with a form (country select defaulting PH, province, city, weight kg, subtotal ₱, optional quantity) POSTing to `admin.shipping-settings.test`, which calls `getQuotes()` with one synthetic item and returns the SAME page (`back()->with('rate_test', [...])`) carrying: every quote (key, name, cost, service_type), which one wins (first after the service's own free-shipping override), and an explanation line ("Free shipping matched — paid methods suppressed" when override fired; "No methods quoted — check provider enablement and zones" when empty). The partial renders the flashed result table (mono costs, winner badged `success`).

- [ ] **Step 1: Extend the test**

```php
    public function test_rate_tester_returns_quotes_flash(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->from(route('admin.shipping-settings.index'))
            ->post(route('admin.shipping-settings.test'), [
                'country_id' => \App\Models\Country::value('id') ?? 1,
                'province' => 'Metro Manila', 'city' => 'Quezon City',
                'weight' => 1.5, 'subtotal' => 2500,
            ]);

        $response->assertRedirect(route('admin.shipping-settings.index'));
        $response->assertSessionHas('rate_test');
    }
```

(Seed a flat rate or accept an empty-quotes result — `rate_test` flashes either way with the explanation.)

- [ ] **Step 2: Run to verify it fails.** **Step 3: Implement**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Shipping\ShippingQuoteService;
use Illuminate\Http\Request;

class ShippingTestController extends Controller
{
    public function __invoke(Request $request, ShippingQuoteService $quotes)
    {
        $data = $request->validate([
            'country_id' => ['required', 'integer'],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'weight' => ['required', 'numeric', 'min:0.01', 'max:1000'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $item = (object) [
            'weight' => (float) $data['weight'],
            'length' => 10, 'width' => 10, 'height' => 10,
            'quantity' => $data['quantity'] ?? 1,
        ];

        $address = [
            'country_id' => $data['country_id'],
            'province' => $data['province'] ?? '',
            'city' => $data['city'] ?? '',
            'postcode' => $data['postcode'] ?? '',
        ];

        $results = $quotes->getQuotes($address, collect([$item]), (float) $data['subtotal']);

        $freeMatched = collect($results)->contains(fn ($q) => ($q['service_type'] ?? null) === 'free_shipping');

        return back()->with('rate_test', [
            'input' => $data,
            'quotes' => $results,
            'winner' => $results[0]['key'] ?? null,
            'explanation' => $results === []
                ? 'No methods quoted — check provider enablement, zones, and weight tiers.'
                : ($freeMatched ? 'A free-shipping rule matched — all paid methods are suppressed.' : 'Methods sorted by the quote chain; the customer sees all of these.'),
        ]);
    }
}
```

Partial renders form + flashed results. **Step 4: Run tests + AdminV2.** **Step 5: Commit** (`feat(admin-v2): shipping rate tester`).

---

### Task 8: SEO workspace — navigation + dashboard

**Files:**
- Create: `resources/views/admin/extensions/seo/partials/nav-v2.blade.php` (replaces the 13-tab strip usage)
- Modify: `resources/views/admin/extensions/seo/dashboard.blade.php` (rewrite, new shell), `resources/views/admin/extensions/seo/partials/tabs.blade.php` (delete after all SEO views migrate — Task 10)
- Test: `tests/Feature/AdminV2/SeoPagesTest.php`

**Interfaces:**
- Consumes: existing `SeoExtensionController@dashboard` data (health scores per entity type, pendingCount, recent suggestions, GSC top queries) — controller UNCHANGED this task; `Automation` row `seo_generate` (last_run_at, last_run_status, enabled) — add ONLY that query to the dashboard method; routes `admin.extensions.seo.*` unchanged.
- Produces: `nav-v2` — a grouped sub-nav (settings-shell visual pattern, but SEO-local): **Work** (Dashboard, Review, Slugs) · **Content** (Products, Categories, Brands, Blog, Pages via the `entities` route) · **Traffic** (Search Console, Redirects, 404s) · **Setup** (Strategy, Settings); active detection via `request()->routeIs`/params. Dashboard rewrite: `x-admin.stat` row (health score, pending count, redirects live, 404s this month), an **Automation status card** (seo_generate enabled/last run/next run + "Runs via Automations" link — the cron visibility the spec demands), recent suggestions as `x-admin.table` with entity badges, GSC queries table when connected.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\AdminV2;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_new_shell_with_grouped_nav_and_cron_card(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.extensions.seo.dashboard'))
            ->assertOk()->assertSee('v-admin', false)
            ->assertSeeInOrder(['Work', 'Content', 'Traffic', 'Setup'])
            ->assertSee('Automation');
    }
}
```

- [ ] **Step 2: Run to verify it fails** (may require seeding whatever the dashboard needs — read the controller first; if it 500s on empty tables, fix the test setup, not the controller). **Step 3: Implement.** **Step 4: Run tests + AdminV2.** **Step 5: Commit** (`feat(admin-v2): seo workspace navigation and dashboard`).

---

### Task 9: SEO review queue redesign

**Files:**
- Modify: `resources/views/admin/extensions/seo/review.blade.php` (rewrite)
- Test: extend `SeoPagesTest`

**Interfaces:**
- Consumes: `SeoExtensionController@review` (pending grouped by entity_type, counts) — read it; existing action routes `accept`, `reject`, `bulk-accept` (field names from the old view — READ IT); `SeoSuggestion` columns current_/suggested_ meta title/description/keywords + og + slug.
- Produces: queue page in the new shell + nav-v2: entity-type filter chips (count badges), suggestion cards each showing a **field-by-field diff** (current → suggested, changed fields highlighted with `bg-v-accent-weak`, unchanged collapsed), per-card Accept (primary) / Reject (ghost) posting the existing routes, sticky bulk bar when checkboxes selected ("Accept 12 selected" → `bulk-accept` with the old view's field names), empty state ("Queue clear — next generation run: {automation schedule}").

- [ ] **Step 1: Extend test**

```php
    public function test_review_queue_shows_diff_and_bulk_bar(): void
    {
        $this->actingAs(User::factory()->create());
        \App\Models\SeoSuggestion::create([
            'entity_type' => 'product', 'entity_id' => 1,
            'current_meta_title' => 'Old Title', 'suggested_meta_title' => 'New Better Title',
            'status' => 'pending', 'generated_at' => now(),
        ]);

        $this->get(route('admin.extensions.seo.review'))
            ->assertOk()->assertSee('Old Title')->assertSee('New Better Title')
            ->assertSee('bulk', false);
    }
```

(Adjust creation to the model's actual fillable/required columns — check the model + migrations first.)

- [ ] **Step 2: Run to verify it fails.** **Step 3: Implement.** **Step 4: Run tests + AdminV2.** **Step 5: Commit** (`feat(admin-v2): seo review queue with field diffs`).

---

### Task 10: SEO remaining pages — shell reskin

**Files:**
- Modify: `resources/views/admin/extensions/seo/{entities,slugs,search-console,redirects,not-found,strategy,settings}.blade.php` (wrap in new shell + nav-v2, port forms/tables onto `x-admin.*`; workflows unchanged), delete `partials/tabs.blade.php`
- Test: extend `SeoPagesTest`

**Interfaces:**
- Consumes: each view's existing forms/routes/fields (READ each before rewriting; controller untouched). This is a mechanical reskin wave — same treatment Plan 2 Task 4 gave the settings sections; the entities view (397 lines) is the largest, keep its filters/pagination payloads identical.
- Produces: all SEO pages on the new shell with nav-v2; zero old-layout SEO pages; the 13-tab partial deleted; legacy duplicate routes `admin.seo.*` (old product-only screen, `routes/web.php:338-342`) removed along with `SeoController` and its view IF the impact grep (`grep -rn "admin.seo\.\|SeoController" app/ resources/ routes/`) confirms nothing else links to them — otherwise report.

- [ ] **Step 1: Extend test** — a loop asserting every SEO route renders 200 in the new shell:

```php
    public function test_all_seo_pages_render_in_new_shell(): void
    {
        $this->actingAs(User::factory()->create());
        $routes = [
            route('admin.extensions.seo.review'),
            route('admin.extensions.seo.slugs'),
            route('admin.extensions.seo.search-console'),
            route('admin.extensions.seo.redirects.index'),
            route('admin.extensions.seo.not-found.index'),
            route('admin.extensions.seo.strategy'),
            route('admin.extensions.seo.settings'),
            route('admin.extensions.seo.entities', 'products'),
        ];
        foreach ($routes as $url) {
            $this->get($url)->assertOk()->assertSee('v-admin', false);
        }
    }
```

(Verify route names/params with `php artisan route:list --name=seo` first; adjust.)

- [ ] **Step 2: Run to verify it fails.** **Step 3: Implement wave.** **Step 4: Run tests + AdminV2.** **Step 5: Commit** (`feat(admin-v2): seo pages on new shell; retire legacy seo screen`).

---

### Task 11: Hub/nav wiring + verification sweep

**Files:** `config/settings-nav.php` (SEO Engine card keeps pointing at seo settings; confirm Payments/Shipping cards' descriptions still accurate), fixes only.

- [ ] **Step 1:** `php artisan test` — baseline 26 held, AdminV2 all green. `npm run build && php artisan view:clear`.
- [ ] **Step 2: Browser sweep** (Playwright, port 8001, dev@venta.test, prefix `opensesame`; no destructive DB writes — restore any setting you flip):
  1. Settings hub → Payments card → landing cards render; toggle COD off/on (restore); Manage → COD detail; Configuration saves display name (restore); Availability tab shows rules; save a min_total 1 restriction on hitpay then DELETE it via the UI (test data only).
  2. Old URL `/opensesame/payment-restrictions` redirects into Payments.
  3. Shipping page: chain order correct, origin card present, weight-based accordion opens, DHL test button behaves (expected failure without creds is fine — assert graceful flash).
  4. Rate tester: submit Metro Manila / 1.5kg / ₱2500 → results table or honest "no methods" explanation renders.
  5. SEO dashboard: grouped nav, automation card; Review queue: diff highlighting; entities/redirects/settings pages render in shell.
  6. ⌘K: "payments" and "seo" reach the new pages.
  7. Mobile 375px + dark theme spot-checks on payments landing, shipping page, seo dashboard. Console clean.
  8. Screenshots (payments landing, cod detail availability, shipping chain, rate-test result, seo dashboard, seo review — light 1440 + one dark) → plan workspace dir.
- [ ] **Step 3:** `git fetch origin && git merge origin/main --no-edit`, rerun AdminV2.
- [ ] **Step 4:** Commit fixes (`fix(admin-v2): sales-channels verification fixes`) or note clean.

---

## Self-review notes

- **Spec §3 coverage:** Payments merge ✅ (T3-T5: cards, config tabs, availability with sentences + rule form; restrictions page retired with redirects; service/storage contracts untouched). Shipping unification ✅ (T6: real chain FreeShipping→WeightBased→FlatRate→DHL — the spec's DPH mention is obsolete, provider was deleted in migration `2026_05_01_000001`; chain order visible; origin card gives pickup_* keys their first UI). Rate tester ✅ (T7, supersedes the DHL-only test as the general tool while keeping `test-dhl` for credential checks). SEO ✅ (T8-T10: 13-tab strip → grouped nav, dashboard + cron visibility, review queue with diffs + bulk; other pages shell-reskinned; legacy admin.seo.* removed). Repairs ✅ (T2: DHL meta bug, dead zones). Fulfillment settings: still deferred — the QuadX branch hasn't merged into venta-v2; its hub card stays commented (noted for Plan 4 or the quadx merge).
- **Read-first requirements** are explicit wherever old markup/payloads must be preserved (T4, T5, T6, T9, T10) with the exact keys/routes embedded from the code maps as the checklist.
- **Type consistency:** tabs component API (T1) matches usage in T4/T5; placeholder-route trick matches the proven Plan-2 pattern; `PaymentRestrictionFormData` support extraction keeps `getFormData()` shapes identical for both consumers.
- **Scope check:** three subsystems but sequential and independently shippable; each task gates on its own tests. SEO controller changes limited to one additive query (T8).
