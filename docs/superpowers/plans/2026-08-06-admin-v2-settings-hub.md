# Admin v2 Settings Hub Implementation Plan (Plan 2 of 4)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the searchable Settings hub, the section shell, split the 586-line tabbed General-settings page into proper section pages, and redesign Modules and Order Statuses — per spec §3 (`docs/superpowers/specs/2026-08-06-admin-ux-rebuild-design.md`). The heavy redesigns (Payments, Shipping, SEO) are Plan 3.

**Architecture:** A `config/settings-nav.php` registry (mirroring Plan 1's `admin-nav.php` pattern) drives the hub card grid, the section sub-sidebar, and command-palette entries. `admin.settings.index` becomes the hub (legacy `?tab=` deep links 302 to their new section). New section pages render inside the Plan-1 `x-admin.shell` via a new `x-admin.settings-shell` wrapper. The shared write endpoint `admin.settings.update` (`SettingController@update`) is unchanged — section forms post to it with `_redirect`. Not-yet-migrated destinations (Payments, Shipping, Theme, Localization…) appear as hub cards linking to their existing old-layout pages, so the hub is complete from day one.

**Tech Stack:** Laravel 12, Blade + `<x-admin.*>` components (Plan 1), Alpine.js, Tailwind v3 `v-*` token bridge, PHPUnit 11.

## Global Constraints

- **Never put CSS inside Blade files** — hub/section CSS goes in `resources/css/admin/components/settings.css`, imported from `admin.css`.
- Use only `v-*` token-bridged Tailwind classes for colors/radii (no raw hex, no non-token grays); any NEW color must pass the 4-background contrast check in both themes before entering `tokens.css`.
- `SettingController@update` (`app/Http/Controllers/Admin/SettingController.php:66`) is the shared write endpoint for settings forms — do NOT modify it; post to it with hidden `group`, `settings[key]`, and `_redirect` fields. Blank values for its `ENCRYPTED_KEYS` skip overwrite by design.
- The `Setting` model has NO get/set helpers — read via `Setting::where('group', …)->pluck('value', 'key')`, write only through the shared endpoint.
- Old pages that post to `admin.settings.update` with `_tab`/`_redirect` (shipping-settings, payment-settings) must keep working — their redirect targets don't change in this plan.
- Permission checks use `$user->hasPermission(string $slug)`; `null` permission = always visible.
- `php artisan view:clear` after Blade changes; `npm run build` after CSS/JS changes; tests via `php artisan test --filter=<Name>`. Suite baseline: 26 pre-existing failures; all `tests/Feature/AdminV2/` must stay green.
- Browser checks use the local server on port **8001** (`php artisan serve --host=127.0.0.1 --port=8001` — port 8000 belongs to a different app) with the dev admin account dev@venta.test / venta_dev_2026. Admin prefix is `opensesame`.
- Commit per task, `feat(admin-v2): …`, no Co-Authored-By trailer.

---

### Task 1: Settings navigation registry

**Files:**
- Create: `config/settings-nav.php`, `app/Support/SettingsNav.php`
- Test: `tests/Feature/AdminV2/SettingsNavTest.php`

**Interfaces:**
- Consumes: `User::hasPermission()`; existing admin routes.
- Produces: `SettingsNav::groups(?User $user): array` — list of `['label' => string, 'items' => [...]]` where each item is `['key','label','description','icon','route','permission','keywords']` (route = a NAME resolved with `route()` by consumers); `SettingsNav::find(string $key): ?array` (item + `'group'` label, or null); `SettingsNav::paletteEntries(?User $user): array` — flat `[['label' => 'Settings: <label>', 'url', 'group' => 'Settings', 'keywords'], …]`. Section-type items (those rendered by Task 3's section controller) use route `admin.settings.section` and carry `'section' => '<key>'`; external items point at their existing route name and have `'section' => null`.

- [ ] **Step 1: Write the failing test** — `tests/Feature/AdminV2/SettingsNavTest.php`

```php
<?php

namespace Tests\Feature\AdminV2;

use App\Support\SettingsNav;
use Tests\TestCase;

class SettingsNavTest extends TestCase
{
    public function test_groups_returns_all_four_categories(): void
    {
        $labels = collect(SettingsNav::groups(null))->pluck('label')->all();
        $this->assertSame(['Store', 'Sales channels', 'Operations', 'Access & system'], $labels);
    }

    public function test_every_item_resolves_to_a_real_route(): void
    {
        foreach (SettingsNav::groups(null) as $group) {
            foreach ($group['items'] as $item) {
                $this->assertTrue(
                    app('router')->has($item['route']),
                    "Route {$item['route']} for settings card '{$item['label']}' is not defined"
                );
            }
        }
    }

    public function test_find_returns_item_with_group(): void
    {
        $item = SettingsNav::find('mail');
        $this->assertNotNull($item);
        $this->assertSame('Store', $item['group']);
        $this->assertSame('mail', $item['section']);
        $this->assertNull(SettingsNav::find('nonexistent'));
    }

    public function test_palette_entries_are_prefixed_and_flat(): void
    {
        $entries = SettingsNav::paletteEntries(null);
        $this->assertNotEmpty($entries);
        foreach ($entries as $e) {
            $this->assertStringStartsWith('Settings: ', $e['label']);
            $this->assertSame('Settings', $e['group']);
            $this->assertStringStartsWith('http', $e['url']);
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SettingsNavTest`
Expected: FAIL — `Class "App\Support\SettingsNav" not found`

- [ ] **Step 3: Create `config/settings-nav.php`**

Icon names must exist in `x-admin.icon` (32 names — reuse: settings, orders, fulfillment, media, customers, groups, seo, coupons, blog, pages, tags, cart, reviews, check, warning, info, external, search). Keywords are search synonyms, never displayed. Verify every route name against `php artisan route:list --name=admin` and fix the CONFIG on mismatch — the test enforces this.

```php
<?php

// Single source of truth for the Settings hub. Drives the hub card grid,
// the section sub-sidebar, and command-palette entries. 'section' non-null
// means the destination is a section page rendered by SettingsSectionController
// (route admin.settings.section); null means an external admin page that has
// not migrated into the section shell yet.
return [
    [
        'label' => 'Store',
        'items' => [
            ['key' => 'general', 'label' => 'General', 'description' => 'Store identity, timezone, and default meta tags.', 'icon' => 'settings', 'route' => 'admin.settings.section', 'section' => 'general', 'permission' => null, 'keywords' => ['site name', 'identity', 'timezone', 'meta', 'logo']],
            ['key' => 'mail', 'label' => 'Mail', 'description' => 'SMTP server, sender identity, and test sending.', 'icon' => 'pages', 'route' => 'admin.settings.section', 'section' => 'mail', 'permission' => null, 'keywords' => ['smtp', 'email', 'sender', 'from address']],
            ['key' => 'checkout', 'label' => 'Checkout & Stock', 'description' => 'Guest checkout, out-of-stock rules, low-stock alerts.', 'icon' => 'cart', 'route' => 'admin.settings.section', 'section' => 'checkout', 'permission' => null, 'keywords' => ['guest', 'out of stock', 'low stock', 'threshold']],
            ['key' => 'currencies', 'label' => 'Currencies', 'description' => 'Currency list, symbols, and exchange-rate updates.', 'icon' => 'coupons', 'route' => 'admin.settings.section', 'section' => 'currencies', 'permission' => null, 'keywords' => ['exchange rate', 'peso', 'php', 'forex', 'symbol']],
            ['key' => 'localization', 'label' => 'Localization', 'description' => 'Countries, zones, and geo zones.', 'icon' => 'media', 'route' => 'admin.localization.index', 'section' => null, 'permission' => null, 'keywords' => ['countries', 'provinces', 'geo zones', 'regions']],
            ['key' => 'theme', 'label' => 'Theme', 'description' => 'Storefront appearance, homepage sections, navigation.', 'icon' => 'tags', 'route' => 'admin.theme.index', 'section' => null, 'permission' => null, 'keywords' => ['storefront', 'colors', 'fonts', 'homepage', 'logo', 'favicon']],
        ],
    ],
    [
        'label' => 'Sales channels',
        'items' => [
            ['key' => 'payments', 'label' => 'Payments', 'description' => 'Bank transfer, HitPay, COD configuration and availability.', 'icon' => 'coupons', 'route' => 'admin.payment-settings.index', 'section' => null, 'permission' => null, 'keywords' => ['gcash', 'hitpay', 'cod', 'bank transfer', 'restrictions', 'qr']],
            ['key' => 'shipping', 'label' => 'Shipping', 'description' => 'Rates, methods, free-shipping rules, and DHL.', 'icon' => 'fulfillment', 'route' => 'admin.shipping-settings.index', 'section' => null, 'permission' => null, 'keywords' => ['rates', 'flat rate', 'weight', 'free shipping', 'dhl', 'zones']],
            // ['key' => 'fulfillment', 'label' => 'Fulfillment', 'description' => 'Courier integrations: QuadX, LBC, SPX.', 'icon' => 'fulfillment', 'route' => 'admin.fulfillment.settings', 'section' => null, 'permission' => null, 'keywords' => ['quadx', 'lbc', 'spx', 'waybill', 'booking']], // enable when the route exists (Plan 3 / feat-quadx merge)
        ],
    ],
    [
        'label' => 'Operations',
        'items' => [
            ['key' => 'order-statuses', 'label' => 'Order Statuses', 'description' => 'Status flow and automatic stock actions.', 'icon' => 'orders', 'route' => 'admin.order-statuses.index', 'section' => null, 'permission' => null, 'keywords' => ['workflow', 'stock action', 'decrement', 'pending']],
            ['key' => 'couriers', 'label' => 'Couriers & Tracking', 'description' => 'Courier list and tracking-URL templates.', 'icon' => 'fulfillment', 'route' => 'admin.shipping-couriers.index', 'section' => null, 'permission' => null, 'keywords' => ['tracking url', 'lbc', 'jnt', 'waybill']],
            ['key' => 'email-templates', 'label' => 'Email Templates', 'description' => 'Transactional email content and test sends.', 'icon' => 'pages', 'route' => 'admin.email-templates.index', 'section' => null, 'permission' => null, 'keywords' => ['order confirmation', 'notification', 'template']],
            ['key' => 'stock-alerts', 'label' => 'Stock Alerts', 'description' => 'Back-in-stock subscriber notifications.', 'icon' => 'warning', 'route' => 'admin.stock-notifications.index', 'section' => null, 'permission' => null, 'keywords' => ['back in stock', 'notify', 'subscribers']],
            ['key' => 'abandoned-carts', 'label' => 'Abandoned Carts', 'description' => 'Recovery email rules and cart settings.', 'icon' => 'cart', 'route' => 'admin.abandoned-carts.settings', 'section' => null, 'permission' => null, 'keywords' => ['recovery', 'reminder', 'cart email']],
            ['key' => 'automations', 'label' => 'Automations', 'description' => 'Scheduled jobs: schedules, status, run now.', 'icon' => 'settings', 'route' => 'admin.settings.section', 'section' => 'automations', 'permission' => null, 'keywords' => ['cron', 'schedule', 'jobs', 'sitemap', 'currency update']],
        ],
    ],
    [
        'label' => 'Access & system',
        'items' => [
            ['key' => 'users', 'label' => 'Users', 'description' => 'Admin accounts.', 'icon' => 'customers', 'route' => 'admin.users.index', 'section' => null, 'permission' => null, 'keywords' => ['admins', 'accounts', 'password']],
            ['key' => 'permissions', 'label' => 'Permissions', 'description' => 'Role-based access control.', 'icon' => 'groups', 'route' => 'admin.permissions.index', 'section' => null, 'permission' => null, 'keywords' => ['roles', 'access', 'rbac']],
            ['key' => 'security', 'label' => 'Security', 'description' => 'Admin URL prefix and Turnstile protection.', 'icon' => 'check', 'route' => 'admin.settings.section', 'section' => 'security', 'permission' => null, 'keywords' => ['turnstile', 'captcha', 'admin prefix', 'login']],
            ['key' => 'api', 'label' => 'API & Sync', 'description' => 'ERP token and sync logging.', 'icon' => 'external', 'route' => 'admin.settings.section', 'section' => 'api', 'permission' => null, 'keywords' => ['erp', 'token', 'sync logs', 'integration']],
            ['key' => 'modules', 'label' => 'Modules', 'description' => 'Installed feature modules and extensions.', 'icon' => 'categories', 'route' => 'admin.modules.index', 'section' => null, 'permission' => null, 'keywords' => ['extensions', 'plugins', 'banners']],
            ['key' => 'seo-engine', 'label' => 'SEO Engine', 'description' => 'Claude API, generation rules, and strategy.', 'icon' => 'seo', 'route' => 'admin.extensions.seo.settings', 'section' => null, 'permission' => null, 'keywords' => ['claude', 'meta generation', 'suggestions', 'gsc']],
        ],
    ],
];
```

- [ ] **Step 4: Create `app/Support/SettingsNav.php`**

```php
<?php

namespace App\Support;

use App\Models\User;

class SettingsNav
{
    /** Groups with items the given user may see. Null user = no filtering. */
    public static function groups(?User $user = null): array
    {
        return collect(config('settings-nav'))
            ->map(function (array $group) use ($user) {
                $group['items'] = array_values(array_filter(
                    $group['items'],
                    fn (array $item) => $item['permission'] === null
                        || ($user?->hasPermission($item['permission']) ?? true)
                ));

                return $group;
            })
            ->filter(fn (array $group) => $group['items'] !== [])
            ->values()
            ->all();
    }

    /** Item by key, with its group label attached. */
    public static function find(string $key): ?array
    {
        foreach (config('settings-nav') as $group) {
            foreach ($group['items'] as $item) {
                if ($item['key'] === $key) {
                    return $item + ['group' => $group['label']];
                }
            }
        }

        return null;
    }

    /** Resolve an item's URL (section pages carry a route parameter). */
    public static function url(array $item): string
    {
        return $item['section'] !== null
            ? route($item['route'], $item['section'])
            : route($item['route']);
    }

    /** Flat entries for the command palette, namespaced under "Settings". */
    public static function paletteEntries(?User $user = null): array
    {
        $entries = [];
        foreach (static::groups($user) as $group) {
            foreach ($group['items'] as $item) {
                $entries[] = [
                    'label' => 'Settings: ' . $item['label'],
                    'url' => static::url($item),
                    'group' => 'Settings',
                    'keywords' => $item['keywords'],
                ];
            }
        }

        return $entries;
    }
}
```

Note: `route('admin.settings.section', $section)` doesn't exist until Task 3. To keep this task's route-existence test green NOW, Task 1 also registers the route as a placeholder closure in `routes/web.php` inside the admin group (replaced by the real controller in Task 3):

```php
    Route::get('settings/section/{section}', fn (string $section) => abort(404))->name('settings.section');
```

Place it BEFORE the existing `admin.settings.update` POST line so route ordering stays unambiguous (different verbs anyway).

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=SettingsNavTest`
Expected: PASS (4 tests). Fix any route-name mismatches in the CONFIG (check with `php artisan route:list --name=admin`).

- [ ] **Step 6: Commit**

```bash
git add config/settings-nav.php app/Support/SettingsNav.php routes/web.php tests/Feature/AdminV2/SettingsNavTest.php
git commit -m "feat(admin-v2): settings navigation registry"
```

---

### Task 2: Settings hub page

**Files:**
- Create: `app/Http/Controllers/Admin/SettingsHubController.php`, `resources/views/admin/settings/hub.blade.php`
- Create: `resources/css/admin/components/settings.css` (+import in `resources/css/admin/admin.css`)
- Modify: `routes/web.php` — point `admin.settings.index` GET at `SettingsHubController@index` (the old `SettingController@index` stays in the codebase, unrouted, until Task 4 deletes its view)
- Test: `tests/Feature/AdminV2/SettingsHubTest.php`

**Interfaces:**
- Consumes: `SettingsNav::groups()/url()` (Task 1); `x-admin.shell`, `x-admin.page`, `x-admin.icon` (Plan 1).
- Produces: `admin.settings.index` renders the hub — card grid grouped by category, Alpine live search over label+description+keywords that hides empty groups, empty state. Legacy deep links `admin.settings.index?tab=<t>` 302 to the mapped section: general→general, currency→currencies, checkout→checkout, mail→mail, api→api, security→security, automations→automations.

- [ ] **Step 1: Write the failing test** — `tests/Feature/AdminV2/SettingsHubTest.php`

```php
<?php

namespace Tests\Feature\AdminV2;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_hub_renders_groups_and_cards(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSeeInOrder(['Store', 'Sales channels', 'Operations', 'Access & system'])
            ->assertSee('Payments')->assertSee('Automations')->assertSee('SEO Engine');
    }

    public function test_legacy_tab_param_redirects_to_section(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.settings.index', ['tab' => 'mail']))
            ->assertRedirect(route('admin.settings.section', 'mail'));
        $this->get(route('admin.settings.index', ['tab' => 'currency']))
            ->assertRedirect(route('admin.settings.section', 'currencies'));
    }

    public function test_hub_requires_auth(): void
    {
        $this->get(route('admin.settings.index'))->assertRedirect();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SettingsHubTest`
Expected: FAIL — hub view/controller don't exist (index currently renders the old tabbed page).

- [ ] **Step 3: Create `app/Http/Controllers/Admin/SettingsHubController.php`**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SettingsNav;
use Illuminate\Http\Request;

class SettingsHubController extends Controller
{
    /** Legacy ?tab= values from the old tabbed settings page → section keys. */
    private const TAB_MAP = [
        'general' => 'general',
        'currency' => 'currencies',
        'checkout' => 'checkout',
        'mail' => 'mail',
        'api' => 'api',
        'security' => 'security',
        'automations' => 'automations',
    ];

    public function index(Request $request)
    {
        $tab = $request->query('tab');
        if ($tab !== null && isset(self::TAB_MAP[$tab])) {
            return redirect()->route('admin.settings.section', self::TAB_MAP[$tab]);
        }

        return view('admin.settings.hub', [
            'groups' => SettingsNav::groups($request->user()),
        ]);
    }
}
```

- [ ] **Step 4: Update the route** — in `routes/web.php`, change the `admin.settings.index` GET to `[\App\Http\Controllers\Admin\SettingsHubController::class, 'index']`. Leave `admin.settings.update` and the test/generate routes pointing at `SettingController` untouched.

- [ ] **Step 5: Create `resources/views/admin/settings/hub.blade.php`**

```blade
<x-admin.shell title="Settings">
    <x-admin.page title="Settings">
        <x-slot:actions>
            <div x-data class="relative">
                <x-admin.icon name="search" class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-v-muted" />
                <input type="search" placeholder="Search settings…" x-ref="q"
                       @input="$dispatch('settings-search', $event.target.value)"
                       class="v-control h-[var(--control-h)] w-56 rounded-v-md border border-v-border bg-v-surface pl-8 pr-3 text-[13px] text-v-text placeholder:text-v-muted">
            </div>
        </x-slot:actions>

        <div x-data="{ q: '' }" x-on:settings-search.window="q = $event.detail.toLowerCase()" class="space-y-8">
            @foreach ($groups as $group)
                @php
                    $haystacks = collect($group['items'])->map(fn ($i) => strtolower($i['label'] . ' ' . $i['description'] . ' ' . implode(' ', $i['keywords'])));
                @endphp
                <section x-show="!q || @js($haystacks->values()) .some(h => h.includes(q))">
                    <h2 class="mb-3 text-[10px] font-semibold uppercase tracking-widest text-v-muted">{{ $group['label'] }}</h2>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($group['items'] as $item)
                            <a href="{{ \App\Support\SettingsNav::url($item) }}"
                               x-show="!q || @js(strtolower($item['label'] . ' ' . $item['description'] . ' ' . implode(' ', $item['keywords']))).includes(q)"
                               class="v-hub-card group flex items-start gap-3 rounded-v-lg border border-v-border bg-v-surface p-4 transition-colors hover:border-v-strong hover:bg-v-hover">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-v-md bg-v-sunken text-v-secondary group-hover:text-v-accent">
                                    <x-admin.icon :name="$item['icon']" class="w-[18px] h-[18px]" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-[13px] font-semibold text-v-text">{{ $item['label'] }}</span>
                                    <span class="mt-0.5 block text-xs leading-snug text-v-secondary">{{ $item['description'] }}</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endforeach

            <div x-show="q && !@js(collect($groups)->flatMap(fn ($g) => collect($g['items'])->map(fn ($i) => strtolower($i['label'] . ' ' . $i['description'] . ' ' . implode(' ', $i['keywords']))))->values()).some(h => h.includes(q))" x-cloak>
                <x-admin.empty-state icon="search" title="Nothing matches" description="No setting matches that search. Try a different word — cards also match hidden synonyms like “smtp” or “gcash”." />
            </div>
        </div>
    </x-admin.page>
</x-admin.shell>
```

- [ ] **Step 6: Create `resources/css/admin/components/settings.css`** and add `@import "./components/settings.css";` to `admin.css`:

```css
/* Staggered card reveal on hub load; respects reduced motion via tokens. */
.v-admin .v-hub-card { animation: v-hub-in var(--motion-base) both; }
.v-admin .v-hub-card:nth-child(2) { animation-delay: 40ms; }
.v-admin .v-hub-card:nth-child(3) { animation-delay: 80ms; }
.v-admin .v-hub-card:nth-child(4) { animation-delay: 120ms; }
.v-admin .v-hub-card:nth-child(5) { animation-delay: 160ms; }
.v-admin .v-hub-card:nth-child(6) { animation-delay: 200ms; }
@keyframes v-hub-in { from { opacity: 0; transform: translateY(6px); } }
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan view:clear && npm run build && php artisan test --filter=SettingsHubTest`
Expected: PASS (3 tests).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Admin/SettingsHubController.php resources/views/admin/settings/hub.blade.php resources/css/admin routes/web.php tests/Feature/AdminV2/SettingsHubTest.php
git commit -m "feat(admin-v2): searchable settings hub with legacy tab redirects"
```

---

### Task 3: Section shell + section controller

**Files:**
- Create: `resources/views/components/admin/settings-shell.blade.php`, `app/Http/Controllers/Admin/SettingsSectionController.php`
- Modify: `routes/web.php` (replace Task 1's placeholder closure with the controller)
- Test: `tests/Feature/AdminV2/SettingsSectionTest.php` (started here, extended in Task 4)

**Interfaces:**
- Consumes: `SettingsNav::groups()/find()/url()`; `x-admin.shell`, `x-admin.page`.
- Produces: `<x-admin.settings-shell active="mail">…form…</x-admin.settings-shell>` — renders inside `x-admin.shell`, with breadcrumb (Settings → section label), sticky left sub-sidebar (13.5rem) listing every hub destination grouped by category with the active one highlighted and an "All Settings" back link; below 1024px the sidebar is replaced by a `<select>` with `<optgroup>`s that navigates on change. Content column is `max-w-2xl`. `SettingsSectionController@show(string $section)`: 404 for unknown/non-section keys; loads per-section data (below) and renders `admin.settings.sections.<key>`.

Per-section data contract (`$settings` = `Setting::where('group', G)->pluck('value', 'key')` per listed group):

| Section | Groups loaded | Extra data |
|---|---|---|
| general | `general`, `identity` | — |
| mail | `mail` | — |
| checkout | `checkout`, `stock` | — |
| currencies | — | `$currencies = \App\Models\Currency::orderBy('sort_order')->get()` (verify model name via `ls app/Models | grep -i currenc`; adjust if it lives elsewhere) |
| api | `api` | `$maskedKeys['erp_token']` (decrypt + mask all but last 4, same pattern as `SettingController@index`) |
| security | `security` | `$maskedKeys['turnstile_secret_key']` |
| automations | — | `$automations = \App\Models\Automation::orderBy('id')->get()` |

- [ ] **Step 1: Write the failing test** — `tests/Feature/AdminV2/SettingsSectionTest.php`

```php
<?php

namespace Tests\Feature\AdminV2;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_section_404s(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(route('admin.settings.section', 'bogus'))->assertNotFound();
    }

    public function test_external_key_is_not_a_section(): void
    {
        $this->actingAs(User::factory()->create());
        // 'payments' exists in the registry but points at an external page
        $this->get(route('admin.settings.section', 'payments'))->assertNotFound();
    }

    public function test_mail_section_renders_in_shell_with_sub_sidebar(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.settings.section', 'mail'))
            ->assertOk()
            ->assertSee('v-admin', false)      // new shell
            ->assertSee('All Settings')        // sub-sidebar back link
            ->assertSee('SMTP');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SettingsSectionTest`
Expected: FAIL — placeholder route aborts 404 for everything including mail (third test fails).

- [ ] **Step 3: Create `app/Http/Controllers/Admin/SettingsSectionController.php`**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\Setting;
use App\Support\SettingsNav;
use Illuminate\Support\Facades\Crypt;

class SettingsSectionController extends Controller
{
    /** section key => settings groups to load */
    private const SECTION_GROUPS = [
        'general' => ['general', 'identity'],
        'mail' => ['mail'],
        'checkout' => ['checkout', 'stock'],
        'currencies' => [],
        'api' => ['api'],
        'security' => ['security'],
        'automations' => [],
    ];

    public function show(string $section)
    {
        $item = SettingsNav::find($section);
        abort_if($item === null || $item['section'] === null || ! isset(self::SECTION_GROUPS[$section]), 404);

        $settings = [];
        foreach (self::SECTION_GROUPS[$section] as $group) {
            $settings[$group] = Setting::where('group', $group)->pluck('value', 'key')->all();
        }

        $data = ['item' => $item, 'settings' => $settings];

        if ($section === 'currencies') {
            $data['currencies'] = \App\Models\Currency::orderBy('sort_order')->get();
        }
        if ($section === 'automations') {
            $data['automations'] = Automation::orderBy('id')->get();
        }
        if ($section === 'api') {
            $data['maskedKeys'] = $this->mask($settings['api'] ?? [], ['erp_token']);
        }
        if ($section === 'security') {
            $data['maskedKeys'] = $this->mask($settings['security'] ?? [], ['turnstile_secret_key']);
        }

        return view("admin.settings.sections.{$section}", $data);
    }

    private function mask(array $group, array $keys): array
    {
        $masked = [];
        foreach ($keys as $key) {
            if (! empty($group[$key])) {
                try {
                    $plain = Crypt::decryptString($group[$key]);
                    $masked[$key] = str_repeat('•', max(strlen($plain) - 4, 0)) . substr($plain, -4);
                } catch (\Throwable) {
                    $masked[$key] = '••••';
                }
            }
        }

        return $masked;
    }
}
```

(Verify the `Currency` model name/namespace before committing — `ls app/Models | grep -i currenc`; if currencies live on another model or table, adapt the one line and note it in your report.)

- [ ] **Step 4: Replace the placeholder route** — in `routes/web.php`:

```php
    Route::get('settings/section/{section}', [\App\Http\Controllers\Admin\SettingsSectionController::class, 'show'])->name('settings.section');
```

- [ ] **Step 5: Create `resources/views/components/admin/settings-shell.blade.php`**

```blade
@props(['active', 'title'])
@php $groups = \App\Support\SettingsNav::groups(Auth::user()); @endphp
<x-admin.shell :title="$title . ' — Settings'">
    <x-admin.page :title="$title" :breadcrumb="[['label' => 'Settings', 'url' => route('admin.settings.index')]]">
        <div class="flex gap-8">
            {{-- Sub-sidebar (desktop) --}}
            <nav class="hidden lg:block w-[13.5rem] shrink-0">
                <div class="sticky top-16 space-y-4">
                    <a href="{{ route('admin.settings.index') }}" class="flex items-center gap-1.5 text-xs font-medium text-v-secondary hover:text-v-text">
                        <x-admin.icon name="chevron-right" class="w-3 h-3 rotate-180" /> All Settings
                    </a>
                    @foreach ($groups as $group)
                        <div>
                            <p class="mb-1 px-2 text-[10px] font-semibold uppercase tracking-widest text-v-muted">{{ $group['label'] }}</p>
                            <div class="space-y-0.5">
                                @foreach ($group['items'] as $item)
                                    <a href="{{ \App\Support\SettingsNav::url($item) }}"
                                       class="block rounded-v-md px-2 py-1 text-[13px] transition-colors
                                           {{ $item['key'] === $active ? 'bg-v-accent-weak font-semibold text-v-accent' : 'text-v-secondary hover:bg-v-hover hover:text-v-text' }}">
                                        {{ $item['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </nav>

            {{-- Mobile: select navigator --}}
            <div class="lg:hidden w-full">
                <select x-data @change="window.location.assign($event.target.value)"
                        class="v-control mb-5 w-full h-[var(--control-h)] rounded-v-md border border-v-border bg-v-surface px-2.5 text-[13px] text-v-text" aria-label="Settings section">
                    @foreach ($groups as $group)
                        <optgroup label="{{ $group['label'] }}">
                            @foreach ($group['items'] as $item)
                                <option value="{{ \App\Support\SettingsNav::url($item) }}" @selected($item['key'] === $active)>{{ $item['label'] }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <div class="max-w-2xl">{{ $slot }}</div>
            </div>

            {{-- Content (desktop) --}}
            <div class="hidden lg:block min-w-0 max-w-2xl flex-1">{{ $slot }}</div>
        </div>
    </x-admin.page>
</x-admin.shell>
```

Note the slot renders twice (mobile + desktop wrappers). That duplicates form IDs in the DOM. Avoid that: instead of two slots, use ONE slot with responsive classes — replace the two content wrappers with a single `<div class="min-w-0 max-w-2xl flex-1">{{ $slot }}</div>` placed after the mobile select, and give the mobile select block `class="lg:hidden w-full"` WITHOUT wrapping the slot (structure: flex container holds [desktop nav | content column]; the select sits above the content inside the content column). Final structure:

```blade
        <div class="flex gap-8">
            <nav class="hidden lg:block w-[13.5rem] shrink-0"> …as above… </nav>
            <div class="min-w-0 max-w-2xl flex-1">
                <select … class="v-control lg:hidden mb-5 w-full …"> …optgroups as above… </select>
                {{ $slot }}
            </div>
        </div>
```

Use this single-slot structure; the double-slot sketch above is shown only to explain why.

- [ ] **Step 6: Create a minimal `resources/views/admin/settings/sections/mail.blade.php` now** (so this task is independently testable; the full form set lands in Task 4):

```blade
<x-admin.settings-shell active="mail" title="Mail">
    <x-admin.card title="SMTP" description="Outgoing mail server used for order and customer emails.">
        <p class="text-[13px] text-v-secondary">Configured in Task 4.</p>
    </x-admin.card>
</x-admin.settings-shell>
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan view:clear && php artisan test --filter=SettingsSectionTest`
Expected: PASS (3 tests).

- [ ] **Step 8: Commit**

```bash
git add resources/views/components/admin/settings-shell.blade.php app/Http/Controllers/Admin/SettingsSectionController.php resources/views/admin/settings/sections routes/web.php tests/Feature/AdminV2/SettingsSectionTest.php
git commit -m "feat(admin-v2): settings section shell and section controller"
```

---

### Task 4: The seven section pages

**Files:**
- Create: `resources/views/admin/settings/sections/{general,checkout,currencies,api,security,automations}.blade.php`; rewrite `sections/mail.blade.php` (Task 3's stub)
- Delete: `resources/views/admin/settings/index.blade.php` (the old 586-line tabbed page — its route already points at the hub)
- Keep: `resources/views/admin/settings/_automations_tab.blade.php` (wrapped, restyled in Plan 4)
- Test: extend `tests/Feature/AdminV2/SettingsSectionTest.php`

**Interfaces:**
- Consumes: section shell + controller (Task 3), `x-admin.{card,field,input,select,textarea,toggle,checkbox,form-actions,button}`; the shared endpoint `admin.settings.update` with hidden `group` + `_redirect`; existing action routes `admin.settings.test-mail`, `admin.settings.generate-erp-token`; theme currency routes `admin.theme.currencies.*` + `admin.theme.fetch-rates` (verify exact names via `php artisan route:list --name=theme`).
- Produces: each section page = one `<x-admin.card>` per settings group, each card containing its own `<form method="POST" action="{{ route('admin.settings.update') }}" data-v-form>` with `@csrf`, `<input type="hidden" name="group" value="…">`, `<input type="hidden" name="_redirect" value="{{ route('admin.settings.section', '<key>') }}">`, fields named `settings[<key>]`, and an `<x-admin.form-actions>` save bar. This REPLACES the old page's background-fetch hack (two groups were saved via a hidden `fetch()` before submitting) with honest per-card forms.

Settings keys per section (from the current tabbed page — the authoritative list):

- **general**: card "Identity" (group `identity`): `site_name` (input). Note under the card: logo/favicon are managed in Theme (link `route('admin.theme.index')`). Card "Defaults" (group `general`): `timezone` (select of `timezone_identifiers_list()`), `default_meta_title`, `default_meta_description` (textarea rows 2), `default_meta_keywords`, `google_site_verification`.
- **mail** (group `mail`): card "SMTP": `driver` (select: smtp, sendmail, log), `host`, `port` (type number), `username`, `password` (type password), `encryption` (select: none '', tls, ssl). Card "Sender": `from_address` (type email), `from_name`. Card "Test": separate form POSTing `admin.settings.test-mail` with a `to` email input and a secondary button "Send test email".
- **checkout**: card "Checkout" (group `checkout`): `guest_allowed` (toggle), `block_out_of_stock` (toggle). Card "Low-stock alerts" (group `stock`): `low_stock_alert_enabled` (toggle), `low_stock_threshold` (number), `low_stock_alert_email` (email).
- **currencies**: port the old Currency tab markup (old index.blade.php L177-266) onto `x-admin.table` + `x-admin.card`: list currencies (code, symbol, rate, updated) with the existing inline edit/create forms posting to the existing `admin.theme.currencies.*` routes unchanged, plus the "Fetch latest rates" button posting `admin.theme.fetch-rates`. Copy field names/routes exactly from the old markup (read it before deleting).
- **api** (group `api`): card "ERP token": masked value (`$maskedKeys['erp_token']` or "Not generated"), regenerate via existing form POST `admin.settings.generate-erp-token` behind an `<x-admin.confirm-dialog name="regen-token" …>` (regenerating breaks the ERP connection until it's updated there). Card "Sync logging": `api_sync_log_enabled` (toggle) + link "View API logs" → `route('admin.api-sync-logs.index')`.
- **security** (group `security`): card "Admin URL": `admin_prefix` (input, help text: current prefix + warning that you'll be redirected to the new URL after saving). Card "Turnstile": `turnstile_enabled` (toggle), `turnstile_site_key` (input), `turnstile_secret_key` (password input; if `$maskedKeys['turnstile_secret_key']` exists show it as placeholder-style help "Current: …last4"; blank = keep — the endpoint skips blank encrypted keys).
- **automations**: `<x-admin.settings-shell active="automations" title="Automations">` wrapping `@include('admin.settings._automations_tab')` (pass `$automations`). The include keeps its current internals; only its container changes.

- [ ] **Step 1: Extend the test** — append to `SettingsSectionTest`:

```php
    public function test_every_section_renders(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['general', 'mail', 'checkout', 'currencies', 'api', 'security', 'automations'] as $section) {
            $this->get(route('admin.settings.section', $section))->assertOk();
        }
    }

    public function test_mail_form_saves_through_shared_endpoint(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post(route('admin.settings.update'), [
            'group' => 'mail',
            '_redirect' => route('admin.settings.section', 'mail'),
            'settings' => ['host' => 'smtp.example.test', 'port' => '587'],
        ])->assertRedirect(route('admin.settings.section', 'mail'));

        $this->assertDatabaseHas('settings', ['group' => 'mail', 'key' => 'host', 'value' => 'smtp.example.test']);
    }

    public function test_old_tabbed_view_is_gone(): void
    {
        $this->assertFileDoesNotExist(resource_path('views/admin/settings/index.blade.php'));
    }
```

- [ ] **Step 2: Run to verify the new tests fail** (`test_every_section_renders` 404s/errors on missing views; `test_old_tabbed_view_is_gone` fails while the file exists).

- [ ] **Step 3: Build the section views.** Full reference implementation for `mail.blade.php` — the other sections follow this exact pattern with their own keys (the key lists above are the complete requirements):

```blade
<x-admin.settings-shell active="mail" title="Mail">
    <div class="space-y-6">
        <x-admin.card title="SMTP" description="Outgoing mail server used for order and customer emails.">
            <form method="POST" action="{{ route('admin.settings.update') }}" data-v-form class="space-y-5">
                @csrf
                <input type="hidden" name="group" value="mail">
                <input type="hidden" name="_redirect" value="{{ route('admin.settings.section', 'mail') }}">
                <x-admin.field name="settings[driver]" label="Driver">
                    <x-admin.select name="settings[driver]">
                        @foreach (['smtp' => 'SMTP', 'sendmail' => 'Sendmail', 'log' => 'Log (testing)'] as $v => $label)
                            <option value="{{ $v }}" @selected(($settings['mail']['driver'] ?? 'smtp') === $v)>{{ $label }}</option>
                        @endforeach
                    </x-admin.select>
                </x-admin.field>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-admin.field name="settings[host]" label="Host">
                        <x-admin.input name="settings[host]" :value="$settings['mail']['host'] ?? ''" />
                    </x-admin.field>
                    <x-admin.field name="settings[port]" label="Port">
                        <x-admin.input name="settings[port]" type="number" :value="$settings['mail']['port'] ?? '587'" />
                    </x-admin.field>
                    <x-admin.field name="settings[username]" label="Username">
                        <x-admin.input name="settings[username]" :value="$settings['mail']['username'] ?? ''" />
                    </x-admin.field>
                    <x-admin.field name="settings[password]" label="Password">
                        <x-admin.input name="settings[password]" type="password" :value="$settings['mail']['password'] ?? ''" />
                    </x-admin.field>
                </div>
                <x-admin.field name="settings[encryption]" label="Encryption">
                    <x-admin.select name="settings[encryption]">
                        @foreach (['' => 'None', 'tls' => 'TLS', 'ssl' => 'SSL'] as $v => $label)
                            <option value="{{ $v }}" @selected(($settings['mail']['encryption'] ?? 'tls') === $v)>{{ $label }}</option>
                        @endforeach
                    </x-admin.select>
                </x-admin.field>
                <x-admin.form-actions>
                    <x-admin.button variant="primary" type="submit">Save SMTP settings</x-admin.button>
                </x-admin.form-actions>
            </form>
        </x-admin.card>

        <x-admin.card title="Sender" description="The From identity on outgoing email.">
            <form method="POST" action="{{ route('admin.settings.update') }}" data-v-form class="space-y-5">
                @csrf
                <input type="hidden" name="group" value="mail">
                <input type="hidden" name="_redirect" value="{{ route('admin.settings.section', 'mail') }}">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-admin.field name="settings[from_address]" label="From address">
                        <x-admin.input name="settings[from_address]" type="email" :value="$settings['mail']['from_address'] ?? ''" />
                    </x-admin.field>
                    <x-admin.field name="settings[from_name]" label="From name">
                        <x-admin.input name="settings[from_name]" :value="$settings['mail']['from_name'] ?? ''" />
                    </x-admin.field>
                </div>
                <x-admin.form-actions>
                    <x-admin.button variant="primary" type="submit">Save sender</x-admin.button>
                </x-admin.form-actions>
            </form>
        </x-admin.card>

        <x-admin.card title="Send a test email" description="Verifies the configuration above by sending a real email.">
            <form method="POST" action="{{ route('admin.settings.test-mail') }}" data-v-form class="flex items-end gap-3">
                @csrf
                <div class="flex-1">
                    <x-admin.field name="to" label="Send to">
                        <x-admin.input name="to" type="email" placeholder="you@example.com" />
                    </x-admin.field>
                </div>
                <x-admin.button variant="secondary" type="submit">Send test email</x-admin.button>
            </form>
        </x-admin.card>
    </div>
</x-admin.settings-shell>
```

Build the other six sections the same way (cards per group, per-card forms, keys exactly as listed in the Interfaces table above). Before writing `currencies.blade.php`, READ the old `index.blade.php` currency tab (L177-266) and copy its field names and route calls verbatim into the new table markup. `automations.blade.php` is the two-line shell wrap of the existing include. Then DELETE `resources/views/admin/settings/index.blade.php`.

Check `admin.settings.test-mail`'s controller for the field name it expects (`to`? `email`?) via `SettingController` — match it.

- [ ] **Step 4: Run tests**

Run: `php artisan view:clear && php artisan test --filter=SettingsSectionTest`
Expected: PASS (7 tests).

- [ ] **Step 5: Full AdminV2 suite + build**

Run: `npm run build && php artisan test --filter=AdminV2`
Expected: all green.

- [ ] **Step 6: Commit**

```bash
git add resources/views/admin/settings tests/Feature/AdminV2/SettingsSectionTest.php
git rm resources/views/admin/settings/index.blade.php 2>/dev/null || true
git commit -m "feat(admin-v2): settings section pages replace the tabbed settings screen"
```

---

### Task 5: Command palette reaches settings

**Files:**
- Modify: `resources/views/components/admin/command-palette.blade.php`
- Test: extend `tests/Feature/AdminV2/CommandPaletteTest.php`

**Interfaces:**
- Consumes: `SettingsNav::paletteEntries()` (Task 1), `AdminNav::paletteEntries()` (Plan 1).
- Produces: palette destinations = AdminNav entries + SettingsNav entries merged, so typing "smtp" or "turnstile" jumps straight to the right section.

- [ ] **Step 1: Extend the test** — append to `CommandPaletteTest`:

```php
    public function test_palette_embeds_settings_destinations(): void
    {
        $this->actingAs(User::factory()->create());
        $this->blade('<x-admin.command-palette />')
            ->assertSee('Settings: Mail')
            ->assertSee('smtp', false);
    }
```

- [ ] **Step 2: Run to verify it fails** (`Settings: Mail` not present).

- [ ] **Step 3: Merge the entries** — in `command-palette.blade.php`, change the `x-data` argument from `@js(\App\Support\AdminNav::paletteEntries(Auth::user()))` to:

```blade
@js(array_merge(\App\Support\AdminNav::paletteEntries(Auth::user()), \App\Support\SettingsNav::paletteEntries(Auth::user())))
```

- [ ] **Step 4: Run tests**

Run: `php artisan view:clear && php artisan test --filter=CommandPaletteTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add resources/views/components/admin/command-palette.blade.php tests/Feature/AdminV2/CommandPaletteTest.php
git commit -m "feat(admin-v2): settings sections reachable from command palette"
```

---

### Task 6: Modules — honest extension manager

**Files:**
- Modify: `app/Http/Controllers/Admin/ModuleController.php` (rewrite), `resources/views/admin/modules/index.blade.php` (rewrite)
- Test: `tests/Feature/AdminV2/ModulesPageTest.php`

**Interfaces:**
- Consumes: `x-admin.shell/page/card/badge/button/icon`; `Automation` model; `Setting`; the banners extension provider (`Extensions\Banners\BannerServiceProvider`, registered in `bootstrap/providers.php`).
- Produces: `admin.modules.index` renders a card grid where every module shows name, description, icon, an HONEST status badge, live stats, and a "Configure →" link. No fake toggles: enable state is shown from the module's REAL existing switch and links to where that switch lives. Module list (hardcoded array is fine — there is no extension manifest yet, and inventing one is out of scope):

| Module | Status source | Configure link |
|---|---|---|
| Banners | `class_exists(\Extensions\Banners\BannerServiceProvider::class)` → installed/absent | `route('admin.banners.index')` if route exists, else hidden |
| Stock Alerts | `Setting` `stock.low_stock_alert_enabled` + automation `stock_notify` enabled | Checkout & Stock section + Stock Alerts page |
| SEO | automation `seo_generate` enabled ("auto-generation on/off") | SEO Engine settings |
| Abandoned Carts | `Setting` `abandoned_cart.enabled` | Abandoned Carts settings |

- [ ] **Step 1: Write the failing test** — `tests/Feature/AdminV2/ModulesPageTest.php`

```php
<?php

namespace Tests\Feature\AdminV2;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModulesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_modules_page_renders_cards_with_status(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.modules.index'))
            ->assertOk()
            ->assertSee('v-admin', false)   // new shell
            ->assertSee('Stock Alerts')->assertSee('Abandoned Carts')->assertSee('SEO')
            ->assertSee('Configure');
    }
}
```

- [ ] **Step 2: Run to verify it fails** (old view has no `v-admin`).

- [ ] **Step 3: Rewrite `ModuleController@index`**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AbandonedCart;
use App\Models\Automation;
use App\Models\SeoSuggestion;
use App\Models\Setting;
use App\Models\StockNotification;
use Illuminate\Support\Facades\Route;

class ModuleController extends Controller
{
    public function index()
    {
        $automations = Automation::whereIn('key', ['seo_generate', 'stock_notify'])->get()->keyBy('key');
        $flags = Setting::whereIn('group', ['stock', 'abandoned_cart'])->get()
            ->groupBy('group')->map(fn ($rows) => $rows->pluck('value', 'key'));

        $modules = [
            [
                'key' => 'banners',
                'name' => 'Banners',
                'description' => 'Homepage and promotional banner management.',
                'icon' => 'media',
                'enabled' => class_exists(\Extensions\Banners\BannerServiceProvider::class),
                'status_label' => class_exists(\Extensions\Banners\BannerServiceProvider::class) ? 'Installed' : 'Not installed',
                'stats' => null,
                'configure' => Route::has('admin.banners.index') ? route('admin.banners.index') : null,
            ],
            [
                'key' => 'stock-alerts',
                'name' => 'Stock Alerts',
                'description' => 'Back-in-stock customer emails and low-stock admin warnings.',
                'icon' => 'warning',
                'enabled' => (bool) ($automations['stock_notify']->enabled ?? false),
                'status_label' => ($automations['stock_notify']->enabled ?? false) ? 'Active' : 'Automation off',
                'stats' => StockNotification::count() . ' subscribers',
                'configure' => route('admin.stock-notifications.index'),
            ],
            [
                'key' => 'seo',
                'name' => 'SEO',
                'description' => 'AI meta suggestions, redirects, and Search Console.',
                'icon' => 'seo',
                'enabled' => (bool) ($automations['seo_generate']->enabled ?? false),
                'status_label' => ($automations['seo_generate']->enabled ?? false) ? 'Auto-generating' : 'Manual only',
                'stats' => SeoSuggestion::where('status', 'pending')->count() . ' pending suggestions',
                'configure' => route('admin.extensions.seo.settings'),
            ],
            [
                'key' => 'abandoned-carts',
                'name' => 'Abandoned Carts',
                'description' => 'Cart recovery email rules and tracking.',
                'icon' => 'cart',
                'enabled' => ($flags['abandoned_cart']['enabled'] ?? '0') === '1',
                'status_label' => (($flags['abandoned_cart']['enabled'] ?? '0') === '1') ? 'Active' : 'Disabled',
                'stats' => AbandonedCart::count() . ' tracked carts',
                'configure' => route('admin.abandoned-carts.settings'),
            ],
        ];

        return view('admin.modules.index', ['modules' => $modules]);
    }
}
```

Verify model names first (`ls app/Models | grep -iE 'abandoned|stocknotif|seosugg'`); adjust imports/counts to the real models (the JSON-shaped card contract is fixed, the stat queries are not).

- [ ] **Step 4: Rewrite `resources/views/admin/modules/index.blade.php`**

```blade
<x-admin.shell title="Modules">
    <x-admin.page title="Modules" :breadcrumb="[['label' => 'Settings', 'url' => route('admin.settings.index')]]">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($modules as $module)
                <x-admin.card>
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-v-md bg-v-sunken text-v-secondary">
                            <x-admin.icon :name="$module['icon']" class="w-5 h-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="text-[14px] font-semibold text-v-text">{{ $module['name'] }}</h3>
                                <x-admin.badge :tone="$module['enabled'] ? 'success' : 'neutral'">{{ $module['status_label'] }}</x-admin.badge>
                            </div>
                            <p class="mt-1 text-[13px] leading-snug text-v-secondary">{{ $module['description'] }}</p>
                            <div class="mt-3 flex items-center justify-between">
                                <span class="v-num text-xs text-v-muted">{{ $module['stats'] ?? '' }}</span>
                                @if ($module['configure'])
                                    <x-admin.button variant="ghost" size="sm" :href="$module['configure']">Configure →</x-admin.button>
                                @endif
                            </div>
                        </div>
                    </div>
                </x-admin.card>
            @endforeach
        </div>
    </x-admin.page>
</x-admin.shell>
```

- [ ] **Step 5: Run tests**

Run: `php artisan view:clear && php artisan test --filter=ModulesPageTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/ModuleController.php resources/views/admin/modules/index.blade.php tests/Feature/AdminV2/ModulesPageTest.php
git commit -m "feat(admin-v2): modules page as honest extension manager"
```

---

### Task 7: Order Statuses — reskin + stock-action visibility

**Files:**
- Modify: `resources/views/admin/order-statuses/index.blade.php` (rewrite onto new components; `OrderStatusController` unchanged)
- Test: `tests/Feature/AdminV2/OrderStatusesPageTest.php`

**Interfaces:**
- Consumes: `x-admin.shell/page/card/table/badge/field/input/select/checkbox/button/confirm-dialog`; existing routes `admin.order-statuses.{index,store,update,destroy}` and their exact form field names (`name`, `is_default`, `stock_action`, `sort_order` — verify against `OrderStatusController@store/update` before writing).
- Produces: same CRUD behavior, new skin, plus a **stock-action flow strip**: a legend card at the top explaining `decrement` (▼ stock committed) / `increment` (▲ stock returned) / none, and a per-row badge (`danger` ▼ Decrements / `success` ▲ Increments / neutral —) making inventory side effects visible. Inline edit rows keep the current pattern (each row is its own form). Default status shows a `success` "Default" badge; delete goes through `x-admin.confirm-dialog`.

- [ ] **Step 1: Write the failing test** — `tests/Feature/AdminV2/OrderStatusesPageTest.php`

```php
<?php

namespace Tests\Feature\AdminV2;

use App\Models\OrderStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_shows_stock_action_badges(): void
    {
        $this->actingAs(User::factory()->create());
        OrderStatus::create(['store_id' => 1, 'name' => 'Processing', 'stock_action' => 'decrement', 'sort_order' => 2]);
        OrderStatus::create(['store_id' => 1, 'name' => 'Refunded', 'stock_action' => 'increment', 'sort_order' => 11]);

        $this->get(route('admin.order-statuses.index'))
            ->assertOk()
            ->assertSee('v-admin', false)
            ->assertSee('Decrements')->assertSee('Increments')
            ->assertSee('Processing')->assertSee('Refunded');
    }

    public function test_create_and_update_round_trip_unchanged(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.order-statuses.store'), [
            'name' => 'Quality Check', 'stock_action' => 'decrement', 'sort_order' => 4,
        ])->assertRedirect();
        $this->assertDatabaseHas('order_statuses', ['name' => 'Quality Check', 'stock_action' => 'decrement']);
    }
}
```

If `OrderStatus::create` needs a `stores` row for the FK, create one in the test via the existing store/factory pattern (check how other tests seed `store_id = 1`; replicate).

- [ ] **Step 2: Run to verify it fails.**

- [ ] **Step 3: Rewrite the view.** Read `OrderStatusController@store/update` first for the exact validated field names and redirect behavior — the forms must post identical payloads. Structure:

```blade
<x-admin.shell title="Order Statuses">
    <x-admin.page title="Order Statuses" :breadcrumb="[['label' => 'Settings', 'url' => route('admin.settings.index')]]">
        <div class="space-y-5">
            <x-admin.card title="Stock actions" description="What happens to inventory when an order enters a status.">
                <div class="flex flex-wrap gap-4 text-[13px]">
                    <span class="flex items-center gap-2"><x-admin.badge tone="danger">▼ Decrements</x-admin.badge><span class="text-v-secondary">stock committed (e.g. Processing)</span></span>
                    <span class="flex items-center gap-2"><x-admin.badge tone="success">▲ Increments</x-admin.badge><span class="text-v-secondary">stock returned (e.g. Refunded, Canceled)</span></span>
                    <span class="flex items-center gap-2"><x-admin.badge tone="neutral">—</x-admin.badge><span class="text-v-secondary">no inventory effect</span></span>
                </div>
            </x-admin.card>

            <x-admin.table>
                <x-slot:head>
                    <x-admin.table.th>Status</x-admin.table.th>
                    <x-admin.table.th>Stock action</x-admin.table.th>
                    <x-admin.table.th>Sort</x-admin.table.th>
                    <x-admin.table.th align="right">Actions</x-admin.table.th>
                </x-slot:head>
                @foreach ($statuses as $status)
                    {{-- one inline-edit form per row, same field names the controller validates --}}
                    <x-admin.table.row>
                        …name input + Default badge, stock_action select, sort_order input, Save button
                        (all inside a per-row <form> posting to route('admin.order-statuses.update', $status)),
                        plus stock-action badge and a delete button dispatching
                        $dispatch('open-modal', 'delete-status-{{ $status->id }}')…
                    </x-admin.table.row>
                @endforeach
            </x-admin.table>
            @foreach ($statuses as $status)
                <x-admin.confirm-dialog name="delete-status-{{ $status->id }}" title="Delete “{{ $status->name }}”?"
                    message="Orders using this status keep their history; the status becomes unavailable for new changes."
                    confirm="Delete" :action="route('admin.order-statuses.destroy', $status)" method="DELETE" />
            @endforeach

            <x-admin.card title="Add status">
                …create form: name, stock_action select (None/Decrement/Increment), sort_order, is_default checkbox,
                posting to route('admin.order-statuses.store') with data-v-form…
            </x-admin.card>
        </div>
    </x-admin.page>
</x-admin.shell>
```

The `…` blocks above describe intent — write them out fully using the same `x-admin.field/input/select/checkbox/button` patterns shown verbatim in Task 4's mail section; the field names come from the controller you just read. A `<form>` cannot nest inside another `<form>` — keep each row's edit form self-contained and put the delete button OUTSIDE the edit form (sibling in the actions cell) since `confirm-dialog` renders its own form in the modal.

- [ ] **Step 4: Run tests**

Run: `php artisan view:clear && php artisan test --filter=OrderStatusesPageTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add resources/views/admin/order-statuses/index.blade.php tests/Feature/AdminV2/OrderStatusesPageTest.php
git commit -m "feat(admin-v2): order statuses reskin with stock-action visibility"
```

---

### Task 8: Verification sweep

**Files:** none expected; fix regressions found.

- [ ] **Step 1: Full suite**

Run: `php artisan test`
Expected: 26-failure pre-existing baseline held; ALL `AdminV2` tests green. Any new failure is yours to fix.

- [ ] **Step 2: Build + caches**

Run: `npm run build && php artisan view:clear && php artisan config:clear`

- [ ] **Step 3: Browser verification** (Playwright MCP, local server: `php artisan serve --host=127.0.0.1 --port=8001`, login dev@venta.test / venta_dev_2026, admin prefix `opensesame`):

1. `/opensesame/settings` — hub renders in the new shell, light theme by default; four groups; cards staggered in.
2. Search "smtp" → only Mail card remains, other groups hide; clear → all back; search "zzzz" → empty state.
3. Click Mail → section page with sub-sidebar, active highlight, All Settings back link; save SMTP host → toast appears, value persists on reload.
4. `/opensesame/settings?tab=mail` → redirects to the Mail section.
5. Mobile 375px: hub grid collapses to 1 column; section page shows the select navigator; selecting another section navigates.
6. `/opensesame/settings/section/automations` — automations table renders inside the shell; toggle + run-now still work.
7. `/opensesame/modules` — card grid, honest statuses, Configure links navigate.
8. `/opensesame/order-statuses` — legend card, per-row badges, inline edit saves, delete confirm-dialog opens and cancels.
9. Old pages that share the settings endpoint still save: change something in `/opensesame/shipping-settings` (e.g. toggle flat rate) and confirm it returns to the shipping page, not the hub.
10. Both themes spot-check on the hub + one section; console error check throughout.
11. Screenshots: hub (light+dark, 1440), mail section (light, 1440), hub mobile (375) → save under `.superpowers/sdd/2026-08-06-admin-v2-settings-hub/`.

- [ ] **Step 4: Merge freshness**

```bash
git fetch origin && git merge origin/main --no-edit && php artisan test --filter=AdminV2
```

- [ ] **Step 5: Commit any fixes**

```bash
git add -A && git commit -m "fix(admin-v2): settings hub verification fixes" || echo "nothing to fix"
```

---

## Self-review notes

- **Spec coverage (spec §3 hub portion):** hub card grid with registry ✅ (T1-T2), keyword search ✅ (T2), section shell with sub-sidebar + mobile select ✅ (T3), General-tabs split into sections ✅ (T4, incl. the double-fetch hack removal), palette reach ✅ (T5), Modules ✅ (T6, honest-status variant — no fake toggles since no enable mechanism exists; real switches are linked instead), Order Statuses flow strip ✅ (T7). Payments/Shipping/Fulfillment/SEO redesigns: deliberately Plan 3 (hub cards link to their existing pages meanwhile). Currency card naming: spec folded currency into General; the real code has a full CRUD tab, so it gets its own section — documented deviation, matches reality.
- **Contract safety:** `SettingController@update` untouched; shipping/payment pages posting to it keep their `_redirect` targets (T8 step 3.9 verifies). `_tab` mapping in the old controller becomes dead for settings pages but still serves shipping/payment tabs.
- **Type consistency:** `SettingsNav::groups/find/url/paletteEntries` signatures match between T1 (definition) and T2/T3/T5 (consumption); section keys in `SECTION_GROUPS` (T3) = section-type keys in the registry (T1) = view filenames (T4) = TAB_MAP targets (T2).
- **Route-name caveats** marked inline (currencies routes, test-mail field name, model names in T6) with the verification command — contract fixed, literals verified at execution time.
- **Placeholder scan:** T7 Step 3 uses two described-intent blocks; they name the exact pattern file (T4 mail section), the exact field-name source (the controller), and the nesting constraint — an implementer has everything needed. No TBDs.
