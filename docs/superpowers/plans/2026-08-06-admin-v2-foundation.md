# Admin v2 Foundation Implementation Plan (Plan 1 of 3)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Venta admin design foundation — tokens, fonts, the full `<x-admin.*>` component library, nav registry, new shell with ⌘K palette, and a ui-kit review page — per `docs/superpowers/specs/2026-08-06-admin-ux-rebuild-design.md` §1–§2.

**Architecture:** VentaSync v2 design tokens scoped to a `.v-admin` body class (light + dark via `data-theme` on `<html>`), bridged into Tailwind v3 under a `v` color namespace (the bare `surface` key is already taken by storefront themes). Anonymous Blade components in `resources/views/components/admin/`; structural CSS in `resources/css/admin/*.css`; Alpine behavior in `resources/js/admin/admin.js` registered on `alpine:init`. Old admin pages stay on the old layout untouched — Plans 2–3 migrate them.

**Tech Stack:** Laravel 12 / PHP 8.2, Blade anonymous components, Alpine.js 3, Tailwind CSS v3, Vite, PHPUnit 11.

## Global Constraints

- **Never put CSS inside Blade files** — all styling via Tailwind classes or files under `resources/css/admin/`.
- Token values are the VentaSync v2 set copied verbatim below; any NEW color must pass 4.5:1 against canvas, surface, surface-sunken, AND surface-hover in both themes before entering `tokens.css`.
- Do not modify the existing `surface`/`brand` Tailwind color keys, `resources/css/app.css`, `layouts/admin.blade.php`, or any storefront theme file.
- Admin routes live inside the existing `Route::middleware(['auth','admin.session.cap'])->prefix(config('admin.prefix','admin'))->name('admin.')` group in `routes/web.php` (~line 150).
- Permission checks use `$user->hasPermission(string $slug): bool` (`app/Models/User.php:49`). A `null` permission means always visible.
- Run `npm run build` after any CSS/JS change and `php artisan view:clear` after any Blade change, before testing.
- Tests: PHPUnit via `php artisan test --filter=<Name>`. Browser testing uses the Claude dev account (claude@newgearday.com / claude_dev_2026) — never the owner's account.
- Commit after each task; plain `feat(admin-v2): …` messages, no Co-Authored-By trailers.

---

### Task 1: Design tokens, fonts, and build plumbing

**Files:**
- Create: `resources/fonts/Geist.woff2`, `resources/fonts/JetBrainsMono.woff2` (copied from `/home/ubuntu/ventasync/preyes-erp/resources/fonts/`)
- Create: `resources/css/admin/tokens.css`, `resources/css/admin/base.css`, `resources/css/admin/admin.css`
- Create: `resources/js/admin/admin.js` (stub for now)
- Modify: `vite.config.js` (input array), `tailwind.config.js` (add `v` namespace)

**Interfaces:**
- Produces: CSS custom properties on `.v-admin` (`--canvas`, `--surface`, `--surface-sunken`, `--surface-hover`, `--border`, `--border-strong`, `--text`, `--text-secondary`, `--text-muted`, `--accent`, `--accent-hover`, `--accent-weak`, `--accent-text`, `--danger`, `--warning`, `--info`, `--font-ui`, `--font-mono`, `--radius-sm/md/lg`, `--motion-fast/base`, `--row-h`, `--control-h`, `--control-h-sm`, `--sidebar-w`); Tailwind classes `bg-v-canvas`, `bg-v-surface`, `bg-v-sunken`, `bg-v-hover`, `border-v-border`, `border-v-strong`, `text-v-text`, `text-v-secondary`, `text-v-muted`, `text-v-accent`, `bg-v-accent`, `text-v-danger`, `text-v-warning`, `text-v-info`, `font-ui`, `font-vmono`, `rounded-v-sm/md/lg`. All later tasks consume these.

- [ ] **Step 1: Copy fonts**

```bash
cp /home/ubuntu/ventasync/preyes-erp/resources/fonts/Geist.woff2 /home/ubuntu/ventasync/preyes-erp/resources/fonts/JetBrainsMono.woff2 /home/ubuntu/venta/resources/fonts/
```
(Create `resources/fonts/` if missing.)

- [ ] **Step 2: Create `resources/css/admin/tokens.css`**

```css
/*
 * Admin v2 design tokens — ported from VentaSync v2 (WCAG-audited there
 * against canvas/surface/sunken/hover in both themes; worst case is
 * surface-hover). Light and dark are peers: every token defined twice.
 * Scoped to .v-admin (the admin shell <body> class) so nothing leaks
 * into storefront pages sharing this Vite bundle. New colors must pass
 * 4.5:1 on all four backgrounds in both themes before entering this file.
 */

@font-face {
    font-family: "Geist";
    src: url("../../fonts/Geist.woff2") format("woff2-variations");
    font-weight: 100 900;
    font-display: swap;
}
@font-face {
    font-family: "JetBrains Mono";
    src: url("../../fonts/JetBrainsMono.woff2") format("woff2");
    font-weight: 400;
    font-display: swap;
}

.v-admin {
    --canvas:          #FBFBFA;
    --surface:         #FFFFFF;
    --surface-sunken:  #F5F5F3;
    --surface-hover:   #F2F2EF;
    --border:          #E7E5E1;
    --border-strong:   #D3D0CB;
    --text:            #1A1917;
    --text-secondary:  #6B6862;
    --text-muted:      #726D65;

    --accent:          #0A7A54;
    --accent-hover:    #096647;
    --accent-weak:     rgba(10,122,84,.05);
    --accent-text:     #FFFFFF;

    --danger:          #B4342A;
    --warning:         #926500;
    --info:            #2A5FA8;

    --font-ui:   "Geist", ui-sans-serif, system-ui, sans-serif;
    --font-mono: "JetBrains Mono", ui-monospace, SFMono-Regular, monospace;

    --radius-sm: 4px;
    --radius-md: 6px;
    --radius-lg: 10px;

    --shadow-menu:  0 4px 12px rgba(0,0,0,.10), 0 0 0 1px var(--border);
    --shadow-modal: 0 16px 48px rgba(0,0,0,.18), 0 0 0 1px var(--border);

    --motion-fast: 120ms cubic-bezier(.2,0,.38,.9);
    --motion-base: 200ms cubic-bezier(.2,0,.38,.9);

    --row-h: 32px;
    --control-h: 32px;
    --control-h-sm: 28px;
    --sidebar-w: 216px;
}

:root[data-theme="dark"] .v-admin {
    --canvas:          #0B0B0C;
    --surface:         #131315;
    --surface-sunken:  #0F0F11;
    --surface-hover:   #1A1A1D;
    --border:          #232326;
    --border-strong:   #34343A;
    --text:            #ECECEE;
    --text-secondary:  #9A9AA2;
    --text-muted:      #82828B;

    --accent:          #3FBF8F;
    --accent-hover:    #55CB9F;
    --accent-weak:     rgba(63,191,143,.12);
    --accent-text:     #06231A;

    --danger:          #E5675C;
    --warning:         #E0A73B;
    --info:            #6C9BE0;

    --shadow-menu:  0 4px 12px rgba(0,0,0,.40), 0 0 0 1px var(--border);
    --shadow-modal: 0 16px 48px rgba(0,0,0,.55), 0 0 0 1px var(--border);
}

@media (prefers-reduced-motion: reduce) {
    .v-admin { --motion-fast: 1ms; --motion-base: 1ms; }
}
```

- [ ] **Step 3: Create `resources/css/admin/base.css`**

```css
.v-admin {
    background: var(--canvas);
    color: var(--text);
    font-family: var(--font-ui);
    font-size: 13px;
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
}
.v-admin ::selection { background: var(--accent-weak); }
.v-admin .v-num { font-family: var(--font-mono); font-variant-numeric: tabular-nums; }
.v-admin :focus-visible {
    outline: 2px solid var(--accent);
    outline-offset: 1px;
    border-radius: var(--radius-sm);
}
[x-cloak] { display: none !important; }
```

- [ ] **Step 4: Create `resources/css/admin/admin.css`**

```css
@import "./tokens.css";
@import "./base.css";
```
(Component CSS files are appended to this import list by later tasks.)

- [ ] **Step 5: Create stub `resources/js/admin/admin.js`**

```js
// Admin v2 behavior. Everything registers on alpine:init so this file can
// load before app.js (which calls Alpine.start()).
document.addEventListener('alpine:init', () => {});
```

- [ ] **Step 6: Register Vite entries** — in `vite.config.js`, change the input array to:

```js
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/admin/admin.css',
                'resources/js/admin/admin.js',
                ...getThemeEntries(),
            ],
```

- [ ] **Step 7: Tailwind bridge** — in `tailwind.config.js`, inside `theme.extend`, add sibling keys to the existing `colors` entries (do NOT touch `brand`/`surface`):

```js
            colors: {
                // …existing brand/surface keys stay exactly as-is…
                v: {
                    canvas: 'var(--canvas)',
                    surface: 'var(--surface)',
                    sunken: 'var(--surface-sunken)',
                    hover: 'var(--surface-hover)',
                    border: 'var(--border)',
                    strong: 'var(--border-strong)',
                    text: 'var(--text)',
                    secondary: 'var(--text-secondary)',
                    muted: 'var(--text-muted)',
                    accent: 'var(--accent)',
                    'accent-hover': 'var(--accent-hover)',
                    'accent-weak': 'var(--accent-weak)',
                    'accent-text': 'var(--accent-text)',
                    danger: 'var(--danger)',
                    warning: 'var(--warning)',
                    info: 'var(--info)',
                },
            },
            fontFamily: {
                // …existing sans/heading/body stay…
                ui: 'var(--font-ui)',
                vmono: 'var(--font-mono)',
            },
            borderRadius: {
                'v-sm': 'var(--radius-sm)',
                'v-md': 'var(--radius-md)',
                'v-lg': 'var(--radius-lg)',
            },
```

Usage note for all later tasks: `bg-v-surface border border-v-border text-v-secondary rounded-v-lg` etc.

- [ ] **Step 8: Build and verify**

Run: `npm run build`
Expected: build succeeds; `public/build/manifest.json` contains entries for `resources/css/admin/admin.css` and `resources/js/admin/admin.js`; both font files appear in `public/build/assets/`.

- [ ] **Step 9: Commit**

```bash
git add resources/fonts resources/css/admin resources/js/admin vite.config.js tailwind.config.js package-lock.json public/build 2>/dev/null || true
git add resources/fonts resources/css/admin resources/js/admin vite.config.js tailwind.config.js
git commit -m "feat(admin-v2): design tokens, fonts, and build plumbing"
```
(If `public/build` is gitignored, the first `git add` silently skips it — check `.gitignore` first and only add source files.)

---

### Task 2: Navigation registry

**Files:**
- Create: `config/admin-nav.php`, `app/Support/AdminNav.php`
- Test: `tests/Feature/AdminV2/AdminNavTest.php`

**Interfaces:**
- Consumes: `User::hasPermission(string $slug): bool`; existing `admin.*` route names.
- Produces: `AdminNav::groups(?User $user): array` — list of `['label' => string|null, 'items' => [['label','route','icon','permission','keywords','pattern'], …]]` filtered by permission; `AdminNav::current(): ?array` (item matching the current route, for breadcrumbs); `AdminNav::paletteEntries(?User $user): array` — flat `[['label','url','group','keywords'], …]`. Config shape: each item `['label' => 'Products', 'route' => 'admin.products.index', 'icon' => 'products', 'pattern' => 'admin.products*', 'permission' => null, 'keywords' => ['catalog','items']]`.

- [ ] **Step 1: Write the failing test** — `tests/Feature/AdminV2/AdminNavTest.php`

```php
<?php

namespace Tests\Feature\AdminV2;

use App\Support\AdminNav;
use Tests\TestCase;

class AdminNavTest extends TestCase
{
    public function test_groups_returns_dashboard_and_all_sections(): void
    {
        $groups = AdminNav::groups(null); // null user = no permission filtering
        $labels = collect($groups)->pluck('label')->all();

        $this->assertContains('Sales', $labels);
        $this->assertContains('Catalog', $labels);
        $this->assertContains('System', $labels);
    }

    public function test_every_item_route_exists(): void
    {
        foreach (AdminNav::groups(null) as $group) {
            foreach ($group['items'] as $item) {
                $this->assertTrue(
                    app('router')->has($item['route']),
                    "Route {$item['route']} for '{$item['label']}' is not defined"
                );
            }
        }
    }

    public function test_palette_entries_are_flat_and_resolvable(): void
    {
        $entries = AdminNav::paletteEntries(null);
        $this->assertNotEmpty($entries);
        foreach ($entries as $e) {
            $this->assertArrayHasKey('label', $e);
            $this->assertArrayHasKey('url', $e);
            $this->assertStringStartsWith('http', $e['url']);
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AdminNavTest`
Expected: FAIL — `Class "App\Support\AdminNav" not found`

- [ ] **Step 3: Create `config/admin-nav.php`**

Sidebar structure from spec §2. The `icon` value is a name resolved by `x-admin.icon` (Task 3). Fulfillment is commented out until its route lands (Plan 2/feat-quadx merge) — an item whose route doesn't exist would fail the registry test by design.

```php
<?php

// Single source of truth for admin navigation. The sidebar, breadcrumbs,
// and command palette all render from this file. 'permission' of null =
// visible to every admin; otherwise checked via User::hasPermission().
// 'keywords' are palette/search synonyms, never displayed.
return [
    [
        'label' => null, // ungrouped
        'items' => [
            ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'dashboard', 'pattern' => 'admin.dashboard', 'permission' => null, 'keywords' => ['home', 'overview']],
        ],
    ],
    [
        'label' => 'Sales',
        'items' => [
            ['label' => 'Orders', 'route' => 'admin.orders.index', 'icon' => 'orders', 'pattern' => 'admin.orders*', 'permission' => null, 'keywords' => ['sales', 'invoices']],
            // ['label' => 'Fulfillment', 'route' => 'admin.fulfillment.index', 'icon' => 'fulfillment', 'pattern' => 'admin.fulfillment*', 'permission' => null, 'keywords' => ['quadx', 'lbc', 'spx', 'booking', 'waybill']], // enable when route exists
            ['label' => 'Abandoned Carts', 'route' => 'admin.abandoned-carts.index', 'icon' => 'cart', 'pattern' => 'admin.abandoned-carts*', 'permission' => null, 'keywords' => ['recovery']],
            ['label' => 'Reviews', 'route' => 'admin.reviews.index', 'icon' => 'reviews', 'pattern' => 'admin.reviews*', 'permission' => null, 'keywords' => ['ratings', 'stars']],
        ],
    ],
    [
        'label' => 'Catalog',
        'items' => [
            ['label' => 'Products', 'route' => 'admin.products.index', 'icon' => 'products', 'pattern' => 'admin.products*', 'permission' => null, 'keywords' => ['items', 'sku', 'inventory']],
            ['label' => 'Categories', 'route' => 'admin.categories.index', 'icon' => 'categories', 'pattern' => 'admin.categories*', 'permission' => null, 'keywords' => []],
            ['label' => 'Brands', 'route' => 'admin.brands.index', 'icon' => 'brands', 'pattern' => 'admin.brands*', 'permission' => null, 'keywords' => ['manufacturers']],
            ['label' => 'Media', 'route' => 'admin.media.index', 'icon' => 'media', 'pattern' => 'admin.media*', 'permission' => null, 'keywords' => ['images', 'files']],
            ['label' => 'Tags', 'route' => 'admin.tags.index', 'icon' => 'tags', 'pattern' => 'admin.tags*', 'permission' => null, 'keywords' => []],
        ],
    ],
    [
        'label' => 'Customers',
        'items' => [
            ['label' => 'Customers', 'route' => 'admin.customers.index', 'icon' => 'customers', 'pattern' => 'admin.customers*', 'permission' => null, 'keywords' => ['buyers', 'accounts']],
            ['label' => 'Customer Groups', 'route' => 'admin.customer-groups.index', 'icon' => 'groups', 'pattern' => 'admin.customer-groups*', 'permission' => null, 'keywords' => ['resellers', 'wholesale']],
        ],
    ],
    [
        'label' => 'Content',
        'items' => [
            ['label' => 'Pages', 'route' => 'admin.pages.index', 'icon' => 'pages', 'pattern' => 'admin.pages*', 'permission' => null, 'keywords' => ['cms', 'static']],
            ['label' => 'Blog Posts', 'route' => 'admin.blog.index', 'icon' => 'blog', 'pattern' => 'admin.blog.*', 'permission' => null, 'keywords' => ['articles', 'news']],
            ['label' => 'Blog Categories', 'route' => 'admin.blog-categories.index', 'icon' => 'categories', 'pattern' => 'admin.blog-categories*', 'permission' => null, 'keywords' => []],
        ],
    ],
    [
        'label' => 'Marketing',
        'items' => [
            ['label' => 'Coupons', 'route' => 'admin.coupons.index', 'icon' => 'coupons', 'pattern' => 'admin.coupons*', 'permission' => null, 'keywords' => ['discount', 'promo', 'voucher']],
            ['label' => 'SEO', 'route' => 'admin.extensions.seo.dashboard', 'icon' => 'seo', 'pattern' => 'admin.extensions.seo*', 'permission' => null, 'keywords' => ['search', 'meta', 'suggestions']],
        ],
    ],
    [
        'label' => 'System',
        'items' => [
            ['label' => 'Settings', 'route' => 'admin.settings.index', 'icon' => 'settings', 'pattern' => 'admin.settings*', 'permission' => null, 'keywords' => ['configuration', 'preferences', 'hub']],
        ],
    ],
];
```

If any route name above doesn't match the codebase (verify with `php artisan route:list --name=admin`), fix the config — not the test.

- [ ] **Step 4: Create `app/Support/AdminNav.php`**

```php
<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

class AdminNav
{
    /** Groups with items the given user may see. Null user = no filtering. */
    public static function groups(?User $user = null): array
    {
        return collect(config('admin-nav'))
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

    /** The nav item matching the current route, for breadcrumbs/titles. */
    public static function current(): ?array
    {
        foreach (config('admin-nav') as $group) {
            foreach ($group['items'] as $item) {
                if (Route::currentRouteName() && request()->routeIs($item['pattern'])) {
                    return $item + ['group' => $group['label']];
                }
            }
        }

        return null;
    }

    /** Flat destination list for the command palette. */
    public static function paletteEntries(?User $user = null): array
    {
        $entries = [];
        foreach (static::groups($user) as $group) {
            foreach ($group['items'] as $item) {
                $entries[] = [
                    'label' => $item['label'],
                    'url' => route($item['route']),
                    'group' => $group['label'] ?? 'General',
                    'keywords' => $item['keywords'],
                ];
            }
        }

        return $entries;
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=AdminNavTest`
Expected: PASS (3 tests). If `test_every_item_route_exists` fails, correct route names in `config/admin-nav.php` against `php artisan route:list --name=admin`.

- [ ] **Step 6: Commit**

```bash
git add config/admin-nav.php app/Support/AdminNav.php tests/Feature/AdminV2/AdminNavTest.php
git commit -m "feat(admin-v2): navigation registry with permission filtering"
```

---

### Task 3: Core primitives — icon, button, badge

**Files:**
- Create: `resources/views/components/admin/icon.blade.php`, `resources/views/components/admin/button.blade.php`, `resources/views/components/admin/badge.blade.php`
- Test: `tests/Feature/AdminV2/CorePrimitivesTest.php`

**Interfaces:**
- Produces: `<x-admin.icon name="products" class="w-4 h-4"/>` (names: dashboard, orders, fulfillment, cart, reviews, products, categories, brands, media, tags, customers, groups, pages, blog, coupons, seo, settings, search, sun, moon, monitor, check, x, warning, info, chevron-down, chevron-right, plus, trash, external, logout, menu); `<x-admin.button variant="primary|secondary|danger|ghost" size="md|sm" :loading="false" type="…">` renders `<button>`, or `<a>` when `href` present; `<x-admin.badge tone="neutral|success|warning|danger|info">`.

- [ ] **Step 1: Write the failing test** — `tests/Feature/AdminV2/CorePrimitivesTest.php`

```php
<?php

namespace Tests\Feature\AdminV2;

use Tests\TestCase;

class CorePrimitivesTest extends TestCase
{
    public function test_button_renders_variants_and_loading_state(): void
    {
        $this->blade('<x-admin.button variant="primary">Save</x-admin.button>')
            ->assertSee('Save')->assertSee('bg-v-accent', false);

        $this->blade('<x-admin.button variant="danger" size="sm">Delete</x-admin.button>')
            ->assertSee('Delete')->assertSee('bg-v-danger', false);

        // loading buttons disable themselves and expose a spinner bound to Alpine
        $this->blade('<x-admin.button x-bind:data-loading="saving">Go</x-admin.button>')
            ->assertSee('v-btn-spinner', false);
    }

    public function test_button_renders_as_link_when_href_given(): void
    {
        $this->blade('<x-admin.button href="/x">Open</x-admin.button>')
            ->assertSee('<a ', false)->assertSee('href="/x"', false);
    }

    public function test_icon_renders_svg_for_known_name(): void
    {
        $this->blade('<x-admin.icon name="products" class="w-4 h-4" />')
            ->assertSee('<svg', false)->assertSee('w-4 h-4', false);
    }

    public function test_badge_tones(): void
    {
        $this->blade('<x-admin.badge tone="success">Complete</x-admin.badge>')
            ->assertSee('Complete')->assertSee('text-v-accent', false);
        $this->blade('<x-admin.badge tone="danger">Failed</x-admin.badge>')
            ->assertSee('text-v-danger', false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CorePrimitivesTest`
Expected: FAIL — `Unable to locate a class or view for component [admin.icon]` (and siblings).

- [ ] **Step 3: Create `resources/views/components/admin/icon.blade.php`**

Heroicons-outline 24×24 path per name; unknown names render the `info` icon. Keep every `d="…"` on one line.

```blade
@props(['name'])
@php
$paths = [
    'dashboard' => 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75',
    'orders' => 'M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z',
    'fulfillment' => 'M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12',
    'cart' => 'M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z',
    'reviews' => 'M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z',
    'products' => 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z',
    'categories' => 'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z',
    'brands' => 'M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z M6 6h.008v.008H6V6z',
    'media' => 'M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M2.25 18V6a2.25 2.25 0 012.25-2.25h15A2.25 2.25 0 0121.75 6v12A2.25 2.25 0 0119.5 20.25H4.5A2.25 2.25 0 012.25 18z',
    'tags' => 'M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z',
    'customers' => 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
    'groups' => 'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z',
    'pages' => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
    'blog' => 'M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 01-2.25 2.25M16.5 7.5V18a2.25 2.25 0 002.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 002.25 2.25h13.5M6 7.5h3v3H6v-3z',
    'coupons' => 'M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 010 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 010-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375z',
    'seo' => 'M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z',
    'settings' => 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
    'search' => 'M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z',
    'sun' => 'M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z',
    'moon' => 'M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z',
    'monitor' => 'M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25',
    'check' => 'M4.5 12.75l6 6 9-13.5',
    'x' => 'M6 18L18 6M6 6l12 12',
    'warning' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z',
    'info' => 'M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z',
    'chevron-down' => 'M19.5 8.25l-7.5 7.5-7.5-7.5',
    'chevron-right' => 'M8.25 4.5l7.5 7.5-7.5 7.5',
    'plus' => 'M12 4.5v15m7.5-7.5h-15',
    'trash' => 'M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0',
    'external' => 'M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25',
    'logout' => 'M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9',
    'menu' => 'M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5',
];
@endphp
<svg {{ $attributes }} fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $paths[$name] ?? $paths['info'] }}" />
</svg>
```

- [ ] **Step 4: Create `resources/views/components/admin/button.blade.php`**

```blade
@props(['variant' => 'primary', 'size' => 'md', 'href' => null, 'type' => 'button'])
@php
$base = 'v-btn inline-flex items-center justify-center gap-1.5 font-medium rounded-v-md transition-colors select-none disabled:opacity-50 disabled:pointer-events-none';
$sizes = [
    'md' => 'h-[var(--control-h)] px-3.5 text-[13px]',
    'sm' => 'h-[var(--control-h-sm)] px-2.5 text-xs',
];
$variants = [
    'primary'   => 'bg-v-accent text-v-accent-text hover:bg-v-accent-hover',
    'secondary' => 'bg-v-surface text-v-text border border-v-border hover:bg-v-hover',
    'danger'    => 'bg-v-danger text-white hover:opacity-90',
    'ghost'     => 'text-v-secondary hover:text-v-text hover:bg-v-hover',
];
$classes = "$base {$sizes[$size]} {$variants[$variant]}";
@endphp
@if ($href)
<a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
<button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
    <svg class="v-btn-spinner hidden w-3.5 h-3.5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"/><path fill="currentColor" class="opacity-75" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
    {{ $slot }}
</button>
@endif
```

Add to `resources/css/admin/admin.css` a new import line `@import "./components/buttons.css";` and create `resources/css/admin/components/buttons.css`:

```css
/* data-loading is set by forms while submitting (see Task 4 form-actions). */
.v-admin .v-btn[data-loading="true"] { pointer-events: none; opacity: .7; }
.v-admin .v-btn[data-loading="true"] .v-btn-spinner { display: inline-block; }
```

- [ ] **Step 5: Create `resources/views/components/admin/badge.blade.php`**

```blade
@props(['tone' => 'neutral'])
@php
$tones = [
    'neutral' => 'bg-v-sunken text-v-secondary',
    'success' => 'bg-v-accent-weak text-v-accent',
    'warning' => 'bg-[color-mix(in_srgb,var(--warning)_12%,transparent)] text-v-warning',
    'danger'  => 'bg-[color-mix(in_srgb,var(--danger)_12%,transparent)] text-v-danger',
    'info'    => 'bg-[color-mix(in_srgb,var(--info)_12%,transparent)] text-v-info',
];
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold {$tones[$tone]}"]) }}>{{ $slot }}</span>
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan view:clear && php artisan test --filter=CorePrimitivesTest`
Expected: PASS (4 tests).

- [ ] **Step 7: Commit**

```bash
git add resources/views/components/admin resources/css/admin tests/Feature/AdminV2/CorePrimitivesTest.php
git commit -m "feat(admin-v2): icon, button, badge primitives"
```

---

### Task 4: Form components

**Files:**
- Create: `resources/views/components/admin/field.blade.php`, `input.blade.php`, `select.blade.php`, `textarea.blade.php`, `checkbox.blade.php`, `toggle.blade.php`, `radio-group.blade.php`, `form-actions.blade.php` (all under `resources/views/components/admin/`)
- Modify: `resources/js/admin/admin.js` (submit-guard), `resources/css/admin/admin.css` (+forms import)
- Create: `resources/css/admin/components/forms.css`
- Test: `tests/Feature/AdminV2/FormComponentsTest.php`

**Interfaces:**
- Consumes: `x-admin.button` (Task 3).
- Produces: `<x-admin.field name="email" label="Email" help="Optional hint">…control…</x-admin.field>` (renders label, slot, help, and `@error($name)`); `<x-admin.input name="…" type="text" :value="…"/>`; `<x-admin.select name="…">options slot</x-admin.select>`; `<x-admin.textarea name="…" rows="4"/>`; `<x-admin.checkbox name="…" :checked="false" label="…"/>`; `<x-admin.toggle name="…" :checked="false"/>` (hidden 0 + checkbox 1); `<x-admin.radio-group name="…" :options="['k' => 'Label']" :value="…"/>`; `<x-admin.form-actions>` sticky save bar whose slot holds buttons. Forms opt into the submit-guard with `data-v-form`.

- [ ] **Step 1: Write the failing test** — `tests/Feature/AdminV2/FormComponentsTest.php`

```php
<?php

namespace Tests\Feature\AdminV2;

use Tests\TestCase;

class FormComponentsTest extends TestCase
{
    public function test_field_renders_label_help_and_error(): void
    {
        $this->withViewErrors(['email' => 'The email is invalid.'])
            ->blade('<x-admin.field name="email" label="Email address" help="We never share this."><x-admin.input name="email" /></x-admin.field>')
            ->assertSee('Email address')
            ->assertSee('We never share this.')
            ->assertSee('The email is invalid.');
    }

    public function test_input_keeps_old_value_and_flags_error_state(): void
    {
        $this->withViewErrors(['sku' => 'Taken.'])
            ->blade('<x-admin.input name="sku" value="ABC-1" />')
            ->assertSee('value="ABC-1"', false)
            ->assertSee('border-v-danger', false);
    }

    public function test_toggle_posts_zero_when_off(): void
    {
        $this->blade('<x-admin.toggle name="active" />')
            ->assertSee('type="hidden"', false)
            ->assertSee('value="0"', false)
            ->assertSee('type="checkbox"', false);
    }

    public function test_radio_group_marks_current_value(): void
    {
        $this->blade('<x-admin.radio-group name="mode" :options="[\'sale\' => \'Sale\', \'override\' => \'Override\']" value="override" />')
            ->assertSee('Sale')->assertSee('Override')
            ->assertSeeInOrder(['value="override"', 'checked'], false);
    }

    public function test_form_actions_renders_sticky_bar(): void
    {
        $this->blade('<x-admin.form-actions><x-admin.button type="submit">Save</x-admin.button></x-admin.form-actions>')
            ->assertSee('v-form-actions', false)->assertSee('Save');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=FormComponentsTest`
Expected: FAIL — component views not found.

- [ ] **Step 3: Create the components**

`field.blade.php`:

```blade
@props(['name', 'label' => null, 'help' => null])
<div {{ $attributes->merge(['class' => 'space-y-1.5']) }}>
    @if ($label)
        <label for="{{ $name }}" class="block text-[13px] font-medium text-v-text">{{ $label }}</label>
    @endif
    {{ $slot }}
    @if ($help)
        <p class="text-xs text-v-muted">{{ $help }}</p>
    @endif
    @error($name)
        <p class="text-xs text-v-danger">{{ $message }}</p>
    @enderror
</div>
```

`input.blade.php`:

```blade
@props(['name', 'type' => 'text', 'value' => null])
<input type="{{ $type }}" name="{{ $name }}" id="{{ $name }}" value="{{ old($name, $value) }}"
    {{ $attributes->merge(['class' => 'v-control w-full h-[var(--control-h)] px-3 text-[13px] bg-v-surface text-v-text border rounded-v-md placeholder:text-v-muted '
        . ($errors->has($name) ? 'border-v-danger' : 'border-v-border')]) }}>
```

`select.blade.php`:

```blade
@props(['name', 'value' => null])
<select name="{{ $name }}" id="{{ $name }}"
    {{ $attributes->merge(['class' => 'v-control w-full h-[var(--control-h)] px-2.5 text-[13px] bg-v-surface text-v-text border rounded-v-md '
        . ($errors->has($name) ? 'border-v-danger' : 'border-v-border')]) }}>
    {{ $slot }}
</select>
```

`textarea.blade.php`:

```blade
@props(['name', 'rows' => 4, 'value' => null])
<textarea name="{{ $name }}" id="{{ $name }}" rows="{{ $rows }}"
    {{ $attributes->merge(['class' => 'v-control w-full px-3 py-2 text-[13px] bg-v-surface text-v-text border rounded-v-md placeholder:text-v-muted '
        . ($errors->has($name) ? 'border-v-danger' : 'border-v-border')]) }}>{{ old($name, $value) }}</textarea>
```

`checkbox.blade.php`:

```blade
@props(['name', 'checked' => false, 'label' => null, 'value' => 1])
<label class="inline-flex items-center gap-2 text-[13px] text-v-text cursor-pointer">
    <input type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked(old($name, $checked))
        {{ $attributes->merge(['class' => 'w-4 h-4 rounded-v-sm border-v-strong text-v-accent focus:ring-v-accent bg-v-surface']) }}>
    @if ($label)<span>{{ $label }}</span>@endif
</label>
```

`toggle.blade.php` (hidden 0 so unchecked posts `0`, the extraction AgilaCP never did):

```blade
@props(['name', 'checked' => false])
<span class="v-toggle inline-flex">
    <input type="hidden" name="{{ $name }}" value="0">
    <label class="relative inline-flex items-center cursor-pointer">
        <input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $checked)) class="sr-only peer" {{ $attributes }}>
        <span class="w-9 h-5 rounded-full bg-v-strong peer-checked:bg-v-accent transition-colors"></span>
        <span class="absolute left-0.5 top-0.5 w-4 h-4 rounded-full bg-white shadow peer-checked:translate-x-4 transition-transform"></span>
    </label>
</span>
```

`radio-group.blade.php`:

```blade
@props(['name', 'options' => [], 'value' => null])
<div {{ $attributes->merge(['class' => 'space-y-2']) }}>
    @foreach ($options as $key => $label)
        <label class="flex items-center gap-2 text-[13px] text-v-text cursor-pointer">
            <input type="radio" name="{{ $name }}" value="{{ $key }}" @checked((string) old($name, $value) === (string) $key)
                class="w-4 h-4 border-v-strong text-v-accent focus:ring-v-accent bg-v-surface">
            <span>{{ $label }}</span>
        </label>
    @endforeach
</div>
```

`form-actions.blade.php`:

```blade
<div {{ $attributes->merge(['class' => 'v-form-actions sticky bottom-0 z-10 -mx-6 mt-8 px-6 py-3 bg-v-surface border-t border-v-border flex items-center justify-end gap-2']) }}>
    {{ $slot }}
</div>
```

- [ ] **Step 4: Submit-guard in `resources/js/admin/admin.js`** — replace the stub body:

```js
// Admin v2 behavior. Registered on alpine:init so this file can load
// before app.js (which calls Alpine.start()).
document.addEventListener('alpine:init', () => {});

// Any form marked data-v-form: on submit, flip its submit buttons into the
// loading state (spinner + no double-submit). Guard against re-entry.
document.addEventListener('submit', (e) => {
    const form = e.target.closest('form[data-v-form]');
    if (!form) return;
    if (form.dataset.vSubmitting === 'true') { e.preventDefault(); return; }
    form.dataset.vSubmitting = 'true';
    form.querySelectorAll('button[type="submit"]').forEach((b) => b.setAttribute('data-loading', 'true'));
});
```

- [ ] **Step 5: Create `resources/css/admin/components/forms.css`** and add `@import "./components/forms.css";` to `admin.css`:

```css
.v-admin .v-control { transition: border-color var(--motion-fast), box-shadow var(--motion-fast); }
.v-admin .v-control:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-weak); }
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan view:clear && php artisan test --filter=FormComponentsTest`
Expected: PASS (5 tests).

- [ ] **Step 7: Commit**

```bash
git add resources/views/components/admin resources/js/admin resources/css/admin tests/Feature/AdminV2/FormComponentsTest.php
git commit -m "feat(admin-v2): form component set with error states and submit guard"
```

---

### Task 5: Containers — card, stat, empty-state

**Files:**
- Create: `resources/views/components/admin/card.blade.php`, `stat.blade.php`, `empty-state.blade.php`
- Test: `tests/Feature/AdminV2/ContainerComponentsTest.php`

**Interfaces:**
- Consumes: `x-admin.icon`, `x-admin.button`.
- Produces: `<x-admin.card title="…" description="…">body {{-- optional footer slot --}}</x-admin.card>` with named slots `header-actions` and `footer`; `<x-admin.stat label="TODAY" value="₱48,230" tone="default|accent|warning|danger"/>`; `<x-admin.empty-state icon="products" title="No products yet" description="…">action slot</x-admin.empty-state>`.

- [ ] **Step 1: Write the failing test** — `tests/Feature/AdminV2/ContainerComponentsTest.php`

```php
<?php

namespace Tests\Feature\AdminV2;

use Tests\TestCase;

class ContainerComponentsTest extends TestCase
{
    public function test_card_renders_title_body_and_footer(): void
    {
        $this->blade('<x-admin.card title="Store details" description="Basic info."><p>Body here</p><x-slot:footer>Foot</x-slot:footer></x-admin.card>')
            ->assertSeeInOrder(['Store details', 'Basic info.', 'Body here', 'Foot']);
    }

    public function test_stat_renders_mono_value(): void
    {
        $this->blade('<x-admin.stat label="TODAY" value="₱48,230" />')
            ->assertSee('TODAY')->assertSee('₱48,230')->assertSee('v-num', false);
    }

    public function test_empty_state_renders_icon_title_and_action(): void
    {
        $this->blade('<x-admin.empty-state icon="products" title="No products yet" description="Add your first product."><x-admin.button>Add product</x-admin.button></x-admin.empty-state>')
            ->assertSee('No products yet')->assertSee('Add your first product.')->assertSee('Add product');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ContainerComponentsTest`
Expected: FAIL — component views not found.

- [ ] **Step 3: Create the components**

`card.blade.php`:

```blade
@props(['title' => null, 'description' => null])
<section {{ $attributes->merge(['class' => 'bg-v-surface border border-v-border rounded-v-lg']) }}>
    @if ($title || isset($headerActions))
        <header class="flex items-start justify-between gap-3 px-6 pt-5 pb-0">
            <div>
                @if ($title)<h2 class="text-[15px] font-semibold text-v-text">{{ $title }}</h2>@endif
                @if ($description)<p class="mt-0.5 text-[13px] text-v-secondary">{{ $description }}</p>@endif
            </div>
            @isset($headerActions)<div class="flex items-center gap-2">{{ $headerActions }}</div>@endisset
        </header>
    @endif
    <div class="px-6 py-5">{{ $slot }}</div>
    @isset($footer)
        <footer class="px-6 py-3 border-t border-v-border bg-v-sunken rounded-b-v-lg">{{ $footer }}</footer>
    @endisset
</section>
```

`stat.blade.php`:

```blade
@props(['label', 'value', 'tone' => 'default'])
@php
$tones = ['default' => 'text-v-text', 'accent' => 'text-v-accent', 'warning' => 'text-v-warning', 'danger' => 'text-v-danger'];
@endphp
<div {{ $attributes->merge(['class' => 'bg-v-surface border border-v-border rounded-v-lg px-4 py-3']) }}>
    <p class="text-[10px] font-semibold uppercase tracking-widest text-v-muted">{{ $label }}</p>
    <p class="v-num mt-1 text-xl font-medium {{ $tones[$tone] }}">{{ $value }}</p>
</div>
```

`empty-state.blade.php`:

```blade
@props(['icon' => 'info', 'title', 'description' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center text-center py-12 px-6']) }}>
    <div class="w-10 h-10 rounded-v-lg bg-v-sunken flex items-center justify-center text-v-muted">
        <x-admin.icon :name="$icon" class="w-5 h-5" />
    </div>
    <h3 class="mt-3 text-[15px] font-semibold text-v-text">{{ $title }}</h3>
    @if ($description)<p class="mt-1 max-w-sm text-[13px] text-v-secondary">{{ $description }}</p>@endif
    @if (trim($slot))<div class="mt-4">{{ $slot }}</div>@endif
</div>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan view:clear && php artisan test --filter=ContainerComponentsTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add resources/views/components/admin tests/Feature/AdminV2/ContainerComponentsTest.php
git commit -m "feat(admin-v2): card, stat, empty-state containers"
```

---

### Task 6: Data table

**Files:**
- Create: `resources/views/components/admin/table.blade.php`, `resources/views/components/admin/table/th.blade.php`, `resources/views/components/admin/table/row.blade.php`, `resources/views/components/admin/table/cell.blade.php`
- Create: `resources/css/admin/components/table.css` (+import in `admin.css`)
- Test: `tests/Feature/AdminV2/TableComponentTest.php`

**Interfaces:**
- Produces:

```blade
<x-admin.table>
    <x-slot:head>
        <x-admin.table.th sortable field="name">Name</x-admin.table.th>
        <x-admin.table.th align="right">Total</x-admin.table.th>
    </x-slot:head>
    <x-admin.table.row href="{{ route('admin.orders.show', $order) }}">
        <x-admin.table.cell>…</x-admin.table.cell>
        <x-admin.table.cell align="right" mono>…</x-admin.table.cell>
    </x-admin.table.row>
    <x-slot:footer>{{ $orders->links() }}</x-slot:footer>
</x-admin.table>
```

Sortable headers link to the current URL with `?sort=<field>&dir=asc|desc` (toggling dir when already sorted by that field); row `href` makes the whole row clickable via a stretched link in the first cell.

- [ ] **Step 1: Write the failing test** — `tests/Feature/AdminV2/TableComponentTest.php`

```php
<?php

namespace Tests\Feature\AdminV2;

use Tests\TestCase;

class TableComponentTest extends TestCase
{
    public function test_table_renders_head_rows_and_footer(): void
    {
        $this->blade(<<<'BLADE'
            <x-admin.table>
                <x-slot:head><x-admin.table.th>Name</x-admin.table.th></x-slot:head>
                <x-admin.table.row><x-admin.table.cell>Ernie Ball</x-admin.table.cell></x-admin.table.row>
                <x-slot:footer>PAGINATION</x-slot:footer>
            </x-admin.table>
            BLADE)
            ->assertSeeInOrder(['Name', 'Ernie Ball', 'PAGINATION'])
            ->assertSee('overflow-x-auto', false);
    }

    public function test_sortable_header_toggles_direction(): void
    {
        $this->get('/'); // seed a request so url()->current() works
        $html = $this->blade('<x-admin.table.th sortable field="name">Name</x-admin.table.th>', [])->toHtml();
        $this->assertStringContainsString('sort=name', $html);
        $this->assertStringContainsString('dir=asc', $html);
    }

    public function test_mono_cell_uses_numeric_font(): void
    {
        $this->blade('<x-admin.table.cell mono>₱2,450</x-admin.table.cell>')
            ->assertSee('v-num', false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TableComponentTest`
Expected: FAIL — component views not found.

- [ ] **Step 3: Create the components**

`table.blade.php`:

```blade
<div {{ $attributes->merge(['class' => 'bg-v-surface border border-v-border rounded-v-lg']) }}>
    <div class="overflow-x-auto">
        <table class="v-table w-full text-[13px]">
            @isset($head)
                <thead><tr class="bg-v-sunken">{{ $head }}</tr></thead>
            @endisset
            <tbody class="divide-y divide-v-border">{{ $slot }}</tbody>
        </table>
    </div>
    @isset($footer)
        <div class="px-4 py-2.5 border-t border-v-border">{{ $footer }}</div>
    @endisset
</div>
```

`table/th.blade.php`:

```blade
@props(['sortable' => false, 'field' => null, 'align' => 'left'])
@php
$alignClass = $align === 'right' ? 'text-right' : 'text-left';
$currentSort = request('sort');
$currentDir = request('dir', 'asc');
$nextDir = ($currentSort === $field && $currentDir === 'asc') ? 'desc' : 'asc';
@endphp
<th {{ $attributes->merge(['class' => "px-4 h-[var(--row-h)] {$alignClass} text-[10px] font-semibold uppercase tracking-widest text-v-muted whitespace-nowrap"]) }}>
    @if ($sortable && $field)
        <a href="{{ request()->fullUrlWithQuery(['sort' => $field, 'dir' => $nextDir]) }}" class="inline-flex items-center gap-1 hover:text-v-text">
            {{ $slot }}
            @if ($currentSort === $field)
                <x-admin.icon :name="$currentDir === 'asc' ? 'chevron-down' : 'chevron-right'" class="w-3 h-3" />
            @endif
        </a>
    @else
        {{ $slot }}
    @endif
</th>
```

`table/row.blade.php`:

```blade
@props(['href' => null])
<tr {{ $attributes->merge(['class' => 'v-row ' . ($href ? 'relative cursor-pointer' : '')]) }} @if($href) data-href="{{ $href }}" @endif>
    {{ $slot }}
</tr>
```

`table/cell.blade.php`:

```blade
@props(['align' => 'left', 'mono' => false])
<td {{ $attributes->merge(['class' => 'px-4 py-2 align-middle ' . ($align === 'right' ? 'text-right ' : '') . ($mono ? 'v-num ' : '')]) }}>{{ $slot }}</td>
```

- [ ] **Step 4: Row-click behavior in `resources/js/admin/admin.js`** — append:

```js
// Rows with data-href navigate on click, unless the click hit an
// interactive element inside the row (link, button, input).
document.addEventListener('click', (e) => {
    const row = e.target.closest('tr[data-href]');
    if (!row || e.target.closest('a, button, input, select, label')) return;
    window.location.assign(row.dataset.href);
});
```

- [ ] **Step 5: Create `resources/css/admin/components/table.css`** and import in `admin.css`:

```css
.v-admin .v-table .v-row { transition: background-color var(--motion-fast); }
.v-admin .v-table .v-row:hover { background: var(--surface-hover); }
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan view:clear && php artisan test --filter=TableComponentTest`
Expected: PASS (3 tests).

- [ ] **Step 7: Commit**

```bash
git add resources/views/components/admin resources/css/admin resources/js/admin tests/Feature/AdminV2/TableComponentTest.php
git commit -m "feat(admin-v2): data table with sortable headers and row links"
```

---

### Task 7: Feedback — toasts, modal, confirm-dialog

**Files:**
- Create: `resources/views/components/admin/toasts.blade.php`, `modal.blade.php`, `confirm-dialog.blade.php`
- Modify: `resources/js/admin/admin.js` (toast store)
- Test: `tests/Feature/AdminV2/FeedbackComponentsTest.php`

**Interfaces:**
- Consumes: `x-admin.icon`, `x-admin.button`.
- Produces: an Alpine store `$store.toasts` with `push(message, tone = 'success')`; `<x-admin.toasts/>` rendered once by the shell, which also converts `session('success')`/`session('error')` into toasts; `<x-admin.modal name="edit-thing" title="…">…</x-admin.modal>` opened by `$dispatch('open-modal', 'edit-thing')`, closed by `$dispatch('close-modal', 'edit-thing')` or Escape/backdrop; `<x-admin.confirm-dialog name="delete-thing" title="Delete this?" message="This cannot be undone." confirm="Delete" :action="route(...)" method="DELETE"/>` — a modal wrapping a real form POST with method spoofing.

- [ ] **Step 1: Write the failing test** — `tests/Feature/AdminV2/FeedbackComponentsTest.php`

```php
<?php

namespace Tests\Feature\AdminV2;

use Tests\TestCase;

class FeedbackComponentsTest extends TestCase
{
    public function test_toasts_container_bridges_session_flash(): void
    {
        session()->flash('success', 'Product saved.');
        $this->blade('<x-admin.toasts />')
            ->assertSee('Product saved.')
            ->assertSee('$store.toasts', false);
    }

    public function test_modal_renders_hidden_with_name_binding(): void
    {
        $this->blade('<x-admin.modal name="edit-brand" title="Edit brand">Body</x-admin.modal>')
            ->assertSee('Edit brand')->assertSee('Body')
            ->assertSee('open-modal', false)->assertSee('x-cloak', false);
    }

    public function test_confirm_dialog_wraps_a_spoofed_form(): void
    {
        $this->blade('<x-admin.confirm-dialog name="del" title="Delete?" message="Gone forever." confirm="Delete" action="/admin/things/1" method="DELETE" />')
            ->assertSee('Gone forever.')
            ->assertSee('name="_method"', false)
            ->assertSee('value="DELETE"', false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=FeedbackComponentsTest`
Expected: FAIL — component views not found.

- [ ] **Step 3: Toast store in `resources/js/admin/admin.js`** — replace the empty `alpine:init` listener:

```js
document.addEventListener('alpine:init', () => {
    window.Alpine.store('toasts', {
        items: [],
        counter: 0,
        push(message, tone = 'success') {
            const id = ++this.counter;
            this.items.push({ id, message, tone });
            setTimeout(() => this.dismiss(id), 4500);
        },
        dismiss(id) {
            this.items = this.items.filter((t) => t.id !== id);
        },
    });
});
```

- [ ] **Step 4: Create the components**

`toasts.blade.php`:

```blade
<div x-data class="fixed top-4 right-4 z-[100] flex flex-col gap-2 w-72"
     aria-live="polite">
    {{-- Bridge session flash into the store exactly once per page load --}}
    @if (session('success'))
        <div x-init="$store.toasts.push(@js(session('success')), 'success')" hidden>{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div x-init="$store.toasts.push(@js(session('error')), 'danger')" hidden>{{ session('error') }}</div>
    @endif
    <template x-for="toast in $store.toasts.items" :key="toast.id">
        <div class="flex items-start gap-2.5 px-3.5 py-2.5 bg-v-surface border border-v-border rounded-v-md shadow-lg text-[13px] text-v-text"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-x-4"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-end="opacity-0">
            <x-admin.icon name="check" x-show="toast.tone === 'success'" class="w-4 h-4 text-v-accent mt-0.5 shrink-0" />
            <x-admin.icon name="warning" x-show="toast.tone === 'danger'" class="w-4 h-4 text-v-danger mt-0.5 shrink-0" />
            <span class="flex-1" x-text="toast.message"></span>
            <button type="button" class="text-v-muted hover:text-v-text" @click="$store.toasts.dismiss(toast.id)">
                <x-admin.icon name="x" class="w-3.5 h-3.5" />
            </button>
        </div>
    </template>
</div>
```

`modal.blade.php`:

```blade
@props(['name', 'title' => null, 'maxWidth' => 'md'])
@php $widths = ['sm' => 'max-w-sm', 'md' => 'max-w-md', 'lg' => 'max-w-2xl']; @endphp
<div x-data="{ open: false }"
     x-on:open-modal.window="if ($event.detail === '{{ $name }}') open = true"
     x-on:close-modal.window="if ($event.detail === '{{ $name }}') open = false"
     x-on:keydown.escape.window="open = false"
     x-show="open" x-cloak
     class="fixed inset-0 z-[90] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50" @click="open = false"></div>
    <div x-show="open" x-transition
         class="relative w-full {{ $widths[$maxWidth] }} bg-v-surface border border-v-border rounded-v-lg shadow-2xl"
         role="dialog" aria-modal="true" @if($title) aria-label="{{ $title }}" @endif>
        @if ($title)
            <header class="flex items-center justify-between px-5 pt-4">
                <h2 class="text-[15px] font-semibold text-v-text">{{ $title }}</h2>
                <button type="button" class="text-v-muted hover:text-v-text" @click="open = false">
                    <x-admin.icon name="x" class="w-4 h-4" />
                </button>
            </header>
        @endif
        <div class="px-5 py-4">{{ $slot }}</div>
    </div>
</div>
```

`confirm-dialog.blade.php`:

```blade
@props(['name', 'title', 'message', 'confirm' => 'Confirm', 'action', 'method' => 'POST'])
<x-admin.modal :name="$name" :title="$title" maxWidth="sm">
    <p class="text-[13px] text-v-secondary">{{ $message }}</p>
    <form method="POST" action="{{ $action }}" data-v-form class="mt-4 flex justify-end gap-2">
        @csrf
        @if (! in_array(strtoupper($method), ['GET', 'POST']))
            @method($method)
        @endif
        <x-admin.button variant="secondary" type="button" @click="open = false">Cancel</x-admin.button>
        <x-admin.button variant="danger" type="submit">{{ $confirm }}</x-admin.button>
    </form>
</x-admin.modal>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan view:clear && php artisan test --filter=FeedbackComponentsTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add resources/views/components/admin resources/js/admin tests/Feature/AdminV2/FeedbackComponentsTest.php
git commit -m "feat(admin-v2): toast store, modal, confirm dialog"
```

---

### Task 8: Theme boot and toggle

**Files:**
- Create: `resources/views/components/admin/theme-boot.blade.php`, `resources/views/components/admin/theme-toggle.blade.php`
- Test: `tests/Feature/AdminV2/ThemeTest.php`

**Interfaces:**
- Produces: `<x-admin.theme-boot/>` — inline `<script>` for `<head>` that sets `data-theme` on `<html>` pre-paint from `localStorage.vTheme` (`light` | `dark` | `system`, default `system`; `system` resolves via `prefers-color-scheme`); `<x-admin.theme-toggle/>` — three-way Alpine cycle button that persists `localStorage.vTheme` and re-applies immediately. This is the ONE permitted inline `<script>` (it must run before CSS paints; it contains no styling).

- [ ] **Step 1: Write the failing test** — `tests/Feature/AdminV2/ThemeTest.php`

```php
<?php

namespace Tests\Feature\AdminV2;

use Tests\TestCase;

class ThemeTest extends TestCase
{
    public function test_theme_boot_sets_data_theme_before_paint(): void
    {
        $this->blade('<x-admin.theme-boot />')
            ->assertSee('vTheme', false)
            ->assertSee('data-theme', false)
            ->assertSee('prefers-color-scheme', false);
    }

    public function test_theme_toggle_cycles_three_states(): void
    {
        $this->blade('<x-admin.theme-toggle />')
            ->assertSee('system', false)->assertSee('light', false)->assertSee('dark', false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ThemeTest`
Expected: FAIL — component views not found.

- [ ] **Step 3: Create `theme-boot.blade.php`**

```blade
<script>
    (function () {
        var pref = localStorage.getItem('vTheme') || 'system';
        var dark = pref === 'dark' || (pref === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    })();
</script>
```

- [ ] **Step 4: Create `theme-toggle.blade.php`**

```blade
<div x-data="{
        pref: localStorage.getItem('vTheme') || 'system',
        cycle() {
            this.pref = this.pref === 'system' ? 'light' : (this.pref === 'light' ? 'dark' : 'system');
            localStorage.setItem('vTheme', this.pref);
            this.apply();
        },
        apply() {
            const dark = this.pref === 'dark' || (this.pref === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
        },
    }">
    <button type="button" @click="cycle()" class="p-1.5 rounded-v-md text-v-secondary hover:text-v-text hover:bg-v-hover"
            :title="'Theme: ' + pref">
        <x-admin.icon name="sun" x-show="pref === 'light'" class="w-4 h-4" />
        <x-admin.icon name="moon" x-show="pref === 'dark'" x-cloak class="w-4 h-4" />
        <x-admin.icon name="monitor" x-show="pref === 'system'" x-cloak class="w-4 h-4" />
    </button>
</div>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan view:clear && php artisan test --filter=ThemeTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add resources/views/components/admin tests/Feature/AdminV2/ThemeTest.php
git commit -m "feat(admin-v2): pre-paint theme boot and three-way toggle"
```

---

### Task 9: Shell and page components

**Files:**
- Create: `resources/views/components/admin/shell.blade.php`, `resources/views/components/admin/page.blade.php`
- Create: `resources/css/admin/layout/shell.css` (+import in `admin.css`)
- Test: `tests/Feature/AdminV2/ShellTest.php`

**Interfaces:**
- Consumes: `AdminNav::groups()/current()` (Task 2); `x-admin.icon`, `x-admin.toasts`, `x-admin.theme-boot`, `x-admin.theme-toggle`; `x-admin.command-palette` (Task 10 — include the tag now, Task 10 supplies the view; until then keep it commented with `{{-- palette: enabled in Task 10 --}}`).
- Produces: full-document layout component:

```blade
<x-admin.shell title="Products">
    <x-admin.page title="Products" :breadcrumb="[['label' => 'Catalog']]">
        <x-slot:actions><x-admin.button href="…">Add product</x-admin.button></x-slot:actions>
        …content…
    </x-admin.page>
</x-admin.shell>
```

`shell` renders `<html>` with `x-admin.theme-boot` in head, `<body class="v-admin …">`, sidebar from `AdminNav::groups(Auth::user())` (collapsible groups persisted to `localStorage.vNavOpen`, active group auto-open, active item `bg-v-accent-weak text-v-accent`), 44px sticky topbar (breadcrumb slot area, search button dispatching `open-palette`, View-store link to `route('home')` `target="_blank"`, theme toggle, user block with logout form), mobile overlay drawer, `<x-admin.toasts/>`, `@stack('head')` + `@stack('scripts')`. Vite assets: `@vite(['resources/css/app.css', 'resources/css/admin/admin.css', 'resources/js/admin/admin.js', 'resources/js/app.js'])`.

- [ ] **Step 1: Write the failing test** — `tests/Feature/AdminV2/ShellTest.php`

```php
<?php

namespace Tests\Feature\AdminV2;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_shell_renders_nav_groups_theme_boot_and_toasts(): void
    {
        $this->actingAs(User::factory()->create());

        $html = $this->blade('<x-admin.shell title="Test">Hello content</x-admin.shell>')->toHtml();

        $this->assertStringContainsString('v-admin', $html);
        $this->assertStringContainsString('Hello content', $html);
        $this->assertStringContainsString('Catalog', $html);   // nav group
        $this->assertStringContainsString('vTheme', $html);     // theme boot
        $this->assertStringContainsString('$store.toasts', $html);
    }

    public function test_page_renders_title_breadcrumb_and_actions(): void
    {
        $this->blade('<x-admin.page title="Products" :breadcrumb="[[\'label\' => \'Catalog\']]"><x-slot:actions>ACT</x-slot:actions>BODY</x-admin.page>')
            ->assertSeeInOrder(['Catalog', 'Products', 'ACT', 'BODY']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ShellTest`
Expected: FAIL — component views not found.

- [ ] **Step 3: Create `shell.blade.php`**

```blade
@props(['title' => 'Admin'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — {{ config('app.name', 'Venta') }}</title>
    <x-admin.theme-boot />
    @vite(['resources/css/app.css', 'resources/css/admin/admin.css', 'resources/js/admin/admin.js', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="v-admin min-h-screen" x-data="{ mobileNav: false }">
    <div class="flex min-h-screen">
        {{-- Mobile overlay --}}
        <div x-show="mobileNav" x-transition.opacity x-cloak class="fixed inset-0 z-40 bg-black/50 lg:hidden" @click="mobileNav = false"></div>

        {{-- Sidebar --}}
        <aside :class="mobileNav ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
               class="v-sidebar fixed inset-y-0 z-50 flex w-[var(--sidebar-w)] flex-col bg-v-sunken border-r border-v-border transition-transform duration-200">
            <div class="flex h-11 items-center px-4 border-b border-v-border">
                <a href="{{ route('admin.dashboard') }}" class="text-[15px] font-semibold text-v-text">{{ config('app.name') }}</a>
                <button class="ml-auto lg:hidden text-v-muted hover:text-v-text" @click="mobileNav = false">
                    <x-admin.icon name="x" class="w-4 h-4" />
                </button>
            </div>
            <nav class="v-nav flex-1 overflow-y-auto px-2 py-2"
                 x-data="{ open: JSON.parse(localStorage.getItem('vNavOpen') || '{}'),
                           toggle(g) { this.open[g] = !this.isOpen(g); localStorage.setItem('vNavOpen', JSON.stringify(this.open)); },
                           isOpen(g) { return this.open[g] !== false; } }">
                @foreach (\App\Support\AdminNav::groups(Auth::user()) as $group)
                    @php $active = collect($group['items'])->contains(fn ($i) => request()->routeIs($i['pattern'])); @endphp
                    @if ($group['label'])
                        <button type="button" @click="toggle('{{ $group['label'] }}')"
                                class="mt-3 flex w-full items-center justify-between px-2 py-1 text-[10px] font-semibold uppercase tracking-widest text-v-muted hover:text-v-secondary">
                            {{ $group['label'] }}
                            <x-admin.icon name="chevron-down" class="w-3 h-3 transition-transform" x-bind:class="isOpen('{{ $group['label'] }}') || {{ $active ? 'true' : 'false' }} ? '' : '-rotate-90'" />
                        </button>
                    @endif
                    <div @if ($group['label']) x-show="isOpen('{{ $group['label'] }}') || {{ $active ? 'true' : 'false' }}" @endif class="space-y-0.5">
                        @foreach ($group['items'] as $item)
                            @php $on = request()->routeIs($item['pattern']); @endphp
                            <a href="{{ route($item['route']) }}"
                               class="flex items-center gap-2.5 rounded-v-md px-2 py-1.5 text-[13px] transition-colors
                                   {{ $on ? 'bg-v-accent-weak text-v-accent font-semibold' : 'text-v-secondary hover:bg-v-hover hover:text-v-text' }}">
                                <x-admin.icon :name="$item['icon']" class="w-[18px] h-[18px] shrink-0 {{ $on ? '' : 'opacity-60' }}" />
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </nav>
            <div class="border-t border-v-border p-3">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-v-accent-weak text-xs font-bold text-v-accent">
                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[13px] font-medium text-v-text">{{ Auth::user()->name }}</p>
                        <p class="truncate text-[11px] text-v-muted">{{ Auth::user()->role?->name ?? 'Admin' }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-v-muted hover:text-v-text" title="Sign out">
                            <x-admin.icon name="logout" class="w-4 h-4" />
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        {{-- Main --}}
        <div class="flex-1 lg:ml-[var(--sidebar-w)]">
            <header class="sticky top-0 z-20 flex h-11 items-center gap-3 border-b border-v-border bg-v-canvas/80 px-4 backdrop-blur sm:px-6">
                <button class="lg:hidden text-v-secondary hover:text-v-text" @click="mobileNav = true">
                    <x-admin.icon name="menu" class="w-5 h-5" />
                </button>
                <div class="min-w-0 flex-1" id="v-topbar-slot"></div>
                <button type="button" x-data @click="$dispatch('open-palette')"
                        class="hidden sm:inline-flex items-center gap-2 rounded-v-md border border-v-border bg-v-sunken px-2.5 py-1 text-xs text-v-muted hover:text-v-secondary">
                    <x-admin.icon name="search" class="w-3.5 h-3.5" /> Search <kbd class="font-vmono text-[10px]">⌘K</kbd>
                </button>
                <a href="{{ route('home') }}" target="_blank" class="hidden sm:inline-flex items-center gap-1.5 text-xs text-v-secondary hover:text-v-text">
                    <x-admin.icon name="external" class="w-3.5 h-3.5" /> View store
                </a>
                <x-admin.theme-toggle />
            </header>
            <main class="mx-auto max-w-[1440px] px-4 py-6 sm:px-6">
                {{ $slot }}
            </main>
        </div>
    </div>
    <x-admin.toasts />
    {{-- <x-admin.command-palette /> : enabled in Task 10 --}}
    @stack('scripts')
</body>
</html>
```

If `route('home')` doesn't exist, use `url('/')` — verify with `php artisan route:list --name=home`.

- [ ] **Step 4: Create `page.blade.php`**

```blade
@props(['title', 'breadcrumb' => []])
<div>
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            @if ($breadcrumb !== [])
                <nav class="mb-0.5 flex items-center gap-1 text-xs text-v-muted">
                    @foreach ($breadcrumb as $crumb)
                        @if (isset($crumb['url']))<a href="{{ $crumb['url'] }}" class="hover:text-v-secondary">{{ $crumb['label'] }}</a>
                        @else<span>{{ $crumb['label'] }}</span>@endif
                        <x-admin.icon name="chevron-right" class="w-3 h-3" />
                    @endforeach
                </nav>
            @endif
            <h1 class="text-lg font-semibold text-v-text">{{ $title }}</h1>
        </div>
        @isset($actions)<div class="flex items-center gap-2">{{ $actions }}</div>@endisset
    </div>
    {{ $slot }}
</div>
```

- [ ] **Step 5: Create `resources/css/admin/layout/shell.css`** and import in `admin.css`:

```css
.v-admin .v-nav { scrollbar-width: thin; scrollbar-color: var(--border-strong) transparent; }
.v-admin .v-sidebar { will-change: transform; }
@media (min-width: 1024px) {
    .v-admin .v-sidebar { transform: none !important; }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan view:clear && php artisan test --filter=ShellTest`
Expected: PASS (2 tests).

- [ ] **Step 7: Build and commit**

```bash
npm run build && php artisan view:clear
git add resources/views/components/admin resources/css/admin tests/Feature/AdminV2/ShellTest.php
git commit -m "feat(admin-v2): shell layout and page header components"
```

---

### Task 10: Command palette

**Files:**
- Create: `resources/views/components/admin/command-palette.blade.php`
- Create: `app/Http/Controllers/Admin/PaletteController.php`
- Modify: `routes/web.php` (palette route inside the admin group), `resources/js/admin/admin.js` (palette component), `resources/views/components/admin/shell.blade.php` (uncomment the palette tag)
- Create: `resources/css/admin/components/palette.css` (+import)
- Test: `tests/Feature/AdminV2/CommandPaletteTest.php`

**Interfaces:**
- Consumes: `AdminNav::paletteEntries()` (Task 2), `x-admin.modal` pattern (own markup), Products/Orders/Customers Eloquent models.
- Produces: `<x-admin.command-palette/>` opened via `open-palette` window event or ⌘K/Ctrl+K; route `admin.palette.search` (`GET`, `?q=`) returning `{"results": [{"label": …, "sub": …, "url": …, "group": "Products|Orders|Customers"}]}` (max 5 per group); Alpine component `vPalette(destinations, searchUrl)`.

- [ ] **Step 1: Write the failing test** — `tests/Feature/AdminV2/CommandPaletteTest.php`

```php
<?php

namespace Tests\Feature\AdminV2;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommandPaletteTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_endpoint_requires_auth(): void
    {
        $this->get(route('admin.palette.search', ['q' => 'x']))->assertRedirect();
    }

    public function test_search_returns_grouped_results(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->getJson(route('admin.palette.search', ['q' => 'zzz-no-match']));
        $response->assertOk()->assertJson(['results' => []]);
    }

    public function test_palette_component_embeds_destinations(): void
    {
        $this->actingAs(User::factory()->create());
        $this->blade('<x-admin.command-palette />')
            ->assertSee('vPalette', false)
            ->assertSee('Dashboard'); // registry destination serialized into the page
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CommandPaletteTest`
Expected: FAIL — route `admin.palette.search` not defined.

- [ ] **Step 3: Create `app/Http/Controllers/Admin/PaletteController.php`**

Verify model namespaces first (`ls app/Models | grep -iE 'product|order|customer'`); adjust imports if they differ.

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaletteController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $results = [];

        foreach (Product::query()->where('name', 'like', "%{$q}%")->limit(5)->get() as $p) {
            $results[] = ['label' => $p->name, 'sub' => 'Product', 'url' => route('admin.products.edit', $p), 'group' => 'Products'];
        }
        foreach (Order::query()->where('id', 'like', "%{$q}%")->orWhere('customer_name', 'like', "%{$q}%")->limit(5)->get() as $o) {
            $results[] = ['label' => "Order #{$o->id}", 'sub' => $o->customer_name ?? '', 'url' => route('admin.orders.show', $o), 'group' => 'Orders'];
        }
        foreach (Customer::query()->where('email', 'like', "%{$q}%")->orWhere('first_name', 'like', "%{$q}%")->orWhere('last_name', 'like', "%{$q}%")->limit(5)->get() as $c) {
            $results[] = ['label' => trim("{$c->first_name} {$c->last_name}"), 'sub' => $c->email, 'url' => route('admin.customers.edit', $c), 'group' => 'Customers'];
        }

        return response()->json(['results' => $results]);
    }
}
```

Column names (`customer_name`, `first_name`…) must be verified against the actual schema (`php artisan tinker --execute="print_r(Schema::getColumnListing('orders'));"` etc.) and corrected — the shape of the JSON is the contract, not the column names.

- [ ] **Step 4: Register the route** — inside the existing admin group in `routes/web.php` (after the dashboard route):

```php
    Route::get('palette/search', [\App\Http\Controllers\Admin\PaletteController::class, 'search'])->name('palette.search');
```

- [ ] **Step 5: Palette Alpine component in `resources/js/admin/admin.js`** — add inside the `alpine:init` listener, after the toast store:

```js
    window.Alpine.data('vPalette', (destinations, searchUrl) => ({
        open: false,
        q: '',
        results: [],
        selected: 0,
        searchTimer: null,
        init() {
            window.addEventListener('open-palette', () => this.show());
            window.addEventListener('keydown', (e) => {
                if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                    e.preventDefault();
                    this.show();
                }
            });
        },
        show() {
            this.open = true;
            this.q = '';
            this.results = this.pageMatches('');
            this.selected = 0;
            this.$nextTick(() => this.$refs.input.focus());
        },
        pageMatches(q) {
            const needle = q.toLowerCase();
            return destinations
                .filter((d) => !needle
                    || d.label.toLowerCase().includes(needle)
                    || d.keywords.some((k) => k.toLowerCase().includes(needle)))
                .slice(0, 8)
                .map((d) => ({ label: d.label, sub: d.group, url: d.url, group: 'Pages' }));
        },
        search() {
            this.results = this.pageMatches(this.q);
            this.selected = 0;
            clearTimeout(this.searchTimer);
            if (this.q.length < 2) return;
            this.searchTimer = setTimeout(async () => {
                const res = await fetch(`${searchUrl}?q=${encodeURIComponent(this.q)}`, { headers: { Accept: 'application/json' } });
                if (!res.ok) return;
                const data = await res.json();
                this.results = [...this.pageMatches(this.q), ...data.results];
            }, 200);
        },
        go() {
            const hit = this.results[this.selected];
            if (hit) window.location.assign(hit.url);
        },
        move(delta) {
            const max = this.results.length - 1;
            this.selected = Math.min(Math.max(this.selected + delta, 0), Math.max(max, 0));
        },
    }));
```

- [ ] **Step 6: Create `command-palette.blade.php`**

```blade
<div x-data="vPalette(@js(\App\Support\AdminNav::paletteEntries(Auth::user())), @js(route('admin.palette.search')))"
     x-show="open" x-cloak
     x-on:keydown.escape.window="open = false"
     class="fixed inset-0 z-[95] flex items-start justify-center pt-[15vh] px-4">
    <div class="absolute inset-0 bg-black/50" @click="open = false"></div>
    <div class="v-palette relative w-full max-w-lg rounded-v-lg bg-v-surface border border-v-border shadow-2xl overflow-hidden">
        <div class="flex items-center gap-2 border-b border-v-border px-4">
            <x-admin.icon name="search" class="w-4 h-4 text-v-muted shrink-0" />
            <input x-ref="input" x-model="q" @input="search()"
                   @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.enter.prevent="go()"
                   type="text" placeholder="Jump to a page, or search products, orders, customers…"
                   class="w-full bg-transparent py-3 text-[13px] text-v-text placeholder:text-v-muted border-0 focus:ring-0">
        </div>
        <ul class="max-h-72 overflow-y-auto py-1">
            <template x-for="(r, i) in results" :key="i">
                <li>
                    <a :href="r.url" @mouseenter="selected = i"
                       class="flex items-center justify-between px-4 py-2 text-[13px]"
                       :class="selected === i ? 'bg-v-accent-weak text-v-accent' : 'text-v-text'">
                        <span x-text="r.label"></span>
                        <span class="text-xs text-v-muted" x-text="r.sub"></span>
                    </a>
                </li>
            </template>
            <li x-show="results.length === 0" class="px-4 py-6 text-center text-[13px] text-v-muted">Nothing matches.</li>
        </ul>
    </div>
</div>
```

- [ ] **Step 7: Enable in shell** — in `shell.blade.php`, replace `{{-- <x-admin.command-palette /> : enabled in Task 10 --}}` with `<x-admin.command-palette />`.

- [ ] **Step 8: Create `resources/css/admin/components/palette.css`** (+import in `admin.css`):

```css
.v-admin .v-palette { animation: v-palette-in var(--motion-base) both; }
@keyframes v-palette-in { from { opacity: 0; transform: translateY(-8px); } }
```

- [ ] **Step 9: Run test to verify it passes**

Run: `php artisan view:clear && php artisan test --filter=CommandPaletteTest`
Expected: PASS (3 tests).

- [ ] **Step 10: Build and commit**

```bash
npm run build
git add resources/views/components/admin app/Http/Controllers/Admin/PaletteController.php routes/web.php resources/js/admin resources/css/admin tests/Feature/AdminV2/CommandPaletteTest.php
git commit -m "feat(admin-v2): command palette with page jump and record search"
```

---

### Task 11: UI kit page

**Files:**
- Create: `resources/views/admin/ui-kit.blade.php`
- Modify: `routes/web.php` (local-only route in the admin group)
- Test: `tests/Feature/AdminV2/UiKitTest.php`

**Interfaces:**
- Consumes: every component from Tasks 3–10.
- Produces: route `admin.ui-kit` (local environment only) rendering all components in all variants — the living style guide and review gate.

- [ ] **Step 1: Write the failing test** — `tests/Feature/AdminV2/UiKitTest.php`

```php
<?php

namespace Tests\Feature\AdminV2;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UiKitTest extends TestCase
{
    use RefreshDatabase;

    public function test_ui_kit_renders_every_component_section(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.ui-kit'))
            ->assertOk()
            ->assertSeeInOrder(['Buttons', 'Badges', 'Forms', 'Cards', 'Table', 'Feedback', 'Empty states']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=UiKitTest`
Expected: FAIL — route not defined.

- [ ] **Step 3: Register the route** — inside the admin group in `routes/web.php`:

```php
    if (app()->environment('local')) {
        Route::view('ui-kit', 'admin.ui-kit')->name('ui-kit');
    }
```

(The test environment reports `testing`, not `local` — if the test fails on a 404 for this reason, change the guard to `app()->environment(['local', 'testing'])`.)

- [ ] **Step 4: Create `resources/views/admin/ui-kit.blade.php`**

```blade
<x-admin.shell title="UI Kit">
    <x-admin.page title="UI Kit" :breadcrumb="[['label' => 'System']]">
        <div class="space-y-8">
            <x-admin.card title="Buttons" description="All variants and sizes. Toggle themes with the topbar switch.">
                <div class="flex flex-wrap items-center gap-3">
                    <x-admin.button variant="primary">Primary</x-admin.button>
                    <x-admin.button variant="secondary">Secondary</x-admin.button>
                    <x-admin.button variant="danger">Danger</x-admin.button>
                    <x-admin.button variant="ghost">Ghost</x-admin.button>
                    <x-admin.button variant="primary" size="sm">Small</x-admin.button>
                    <x-admin.button variant="primary" data-loading="true">Loading</x-admin.button>
                    <x-admin.button href="#">Link button</x-admin.button>
                </div>
            </x-admin.card>

            <x-admin.card title="Badges">
                <div class="flex flex-wrap gap-2">
                    <x-admin.badge tone="neutral">Draft</x-admin.badge>
                    <x-admin.badge tone="success">Complete</x-admin.badge>
                    <x-admin.badge tone="warning">Processing</x-admin.badge>
                    <x-admin.badge tone="danger">Failed</x-admin.badge>
                    <x-admin.badge tone="info">Shipped</x-admin.badge>
                </div>
            </x-admin.card>

            <x-admin.card title="Forms" description="Field wrapper, controls, error states.">
                <form data-v-form class="max-w-xl space-y-5" onsubmit="return false;">
                    <x-admin.field name="demo_name" label="Store name" help="Shown in the storefront header.">
                        <x-admin.input name="demo_name" value="NewGearDay" />
                    </x-admin.field>
                    <x-admin.field name="demo_country" label="Country">
                        <x-admin.select name="demo_country">
                            <option>Philippines</option>
                            <option>Singapore</option>
                        </x-admin.select>
                    </x-admin.field>
                    <x-admin.field name="demo_notes" label="Notes">
                        <x-admin.textarea name="demo_notes" rows="3" />
                    </x-admin.field>
                    <div class="flex items-center gap-6">
                        <x-admin.toggle name="demo_active" :checked="true" />
                        <x-admin.checkbox name="demo_agree" label="Send review emails" :checked="true" />
                    </div>
                    <x-admin.radio-group name="demo_mode" :options="['sale' => 'Sale price (strikethrough)', 'override' => 'Override (clean replacement)']" value="sale" />
                    <x-admin.form-actions>
                        <x-admin.button variant="secondary">Cancel</x-admin.button>
                        <x-admin.button variant="primary" type="submit">Save changes</x-admin.button>
                    </x-admin.form-actions>
                </form>
            </x-admin.card>

            <x-admin.card title="Cards & stats">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <x-admin.stat label="Today" value="₱48,230" />
                    <x-admin.stat label="Pending" value="14" tone="warning" />
                    <x-admin.stat label="Failed syncs" value="2" tone="danger" />
                </div>
            </x-admin.card>

            <x-admin.card title="Table">
                <x-admin.table>
                    <x-slot:head>
                        <x-admin.table.th sortable field="customer">Customer</x-admin.table.th>
                        <x-admin.table.th>Status</x-admin.table.th>
                        <x-admin.table.th align="right">Total</x-admin.table.th>
                    </x-slot:head>
                    <x-admin.table.row>
                        <x-admin.table.cell>Miguel Santos</x-admin.table.cell>
                        <x-admin.table.cell><x-admin.badge tone="warning">Processing</x-admin.badge></x-admin.table.cell>
                        <x-admin.table.cell align="right" mono>₱2,450.00</x-admin.table.cell>
                    </x-admin.table.row>
                    <x-admin.table.row>
                        <x-admin.table.cell>Ana Reyes</x-admin.table.cell>
                        <x-admin.table.cell><x-admin.badge tone="info">Shipped</x-admin.badge></x-admin.table.cell>
                        <x-admin.table.cell align="right" mono>₱899.00</x-admin.table.cell>
                    </x-admin.table.row>
                    <x-slot:footer><span class="text-xs text-v-muted">Showing 2 of 2</span></x-slot:footer>
                </x-admin.table>
            </x-admin.card>

            <x-admin.card title="Feedback">
                <div class="flex flex-wrap gap-3" x-data>
                    <x-admin.button variant="secondary" @click="$store.toasts.push('Saved successfully.', 'success')">Success toast</x-admin.button>
                    <x-admin.button variant="secondary" @click="$store.toasts.push('Something went wrong.', 'danger')">Error toast</x-admin.button>
                    <x-admin.button variant="secondary" @click="$dispatch('open-modal', 'demo-modal')">Open modal</x-admin.button>
                    <x-admin.button variant="danger" @click="$dispatch('open-modal', 'demo-confirm')">Delete something</x-admin.button>
                </div>
                <x-admin.modal name="demo-modal" title="Demo modal">
                    <p class="text-[13px] text-v-secondary">Modal body content. Press Escape or click outside to close.</p>
                </x-admin.modal>
                <x-admin.confirm-dialog name="demo-confirm" title="Delete this item?" message="This action cannot be undone." confirm="Delete" action="#" method="POST" />
            </x-admin.card>

            <x-admin.card title="Empty states">
                <x-admin.empty-state icon="products" title="No products yet" description="Products added here appear in your storefront immediately.">
                    <x-admin.button variant="primary"><x-admin.icon name="plus" class="w-3.5 h-3.5" /> Add product</x-admin.button>
                </x-admin.empty-state>
            </x-admin.card>
        </div>
    </x-admin.page>
</x-admin.shell>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan view:clear && php artisan test --filter=UiKitTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/views/admin/ui-kit.blade.php routes/web.php tests/Feature/AdminV2/UiKitTest.php
git commit -m "feat(admin-v2): ui-kit living style guide page"
```

---

### Task 12: Full verification sweep

**Files:**
- Modify: none expected; fix regressions found.

- [ ] **Step 1: Full test suite**

Run: `php artisan test`
Expected: everything green, including all pre-existing tests (the old admin is untouched, so any failure there is a regression you introduced — fix it).

- [ ] **Step 2: Production build + caches**

Run: `npm run build && php artisan view:clear && php artisan config:clear`
Expected: clean build.

- [ ] **Step 3: Browser verification (Playwright MCP, Claude dev account)**

Log in at the dev URL admin as claude@newgearday.com / claude_dev_2026 and open `/{admin-prefix}/ui-kit`:

1. Page renders with sidebar, topbar, all sections — no console errors.
2. Toggle theme through light → dark → system: tokens flip, no unstyled flash on reload.
3. Press ⌘K (or Ctrl+K): palette opens; type "prod" → Products page entry appears; Enter navigates.
4. Click "Success toast" and "Error toast": toasts appear top-right and auto-dismiss.
5. "Open modal" and "Delete something": modal/confirm appear; Escape closes.
6. Submit the demo form: the save button shows spinner state (page won't navigate — `onsubmit` returns false).
7. Resize to 375×812: sidebar collapses to drawer; menu button opens it; table scrolls horizontally.
8. Screenshot ui-kit in both themes at 1440 and 375 widths for the record.
9. Open any OLD admin page (e.g. Products index): confirms the old layout still renders untouched.

Expected: all pass. Fix and re-verify anything that doesn't.

- [ ] **Step 4: Merge freshness check**

```bash
git fetch origin && git merge origin/main --no-edit
php artisan test
```
Expected: clean merge (or resolve trivially), tests still green.

- [ ] **Step 5: Commit any fixes**

```bash
git add -A && git commit -m "fix(admin-v2): verification sweep fixes" || echo "nothing to fix"
```

---

## Self-review notes

- **Spec coverage (Plan 1 scope = spec §1–§2):** tokens ✅ (T1), Tailwind bridge ✅ (T1, `v` namespace — documented deviation: bare `surface` key already used by storefront), fonts ✅ (T1), component library ✅ (T3–T8: all §1 components except settings `hub`/`section-shell` and `tabs`/`dropdown` — hub + section-shell are Plan 2 features per the split; tabs + dropdown are consumed first by Plan 2 screens and are deferred there deliberately), ui-kit ✅ (T11), nav registry ✅ (T2), shell/sidebar/topbar ✅ (T9), palette ✅ (T10), theme boot/toggle ✅ (T8), toasts ✅ (T7), mobile ✅ (T9 + T12 step 7).
- **Fulfillment nav item** ships commented-out until its route exists (registry test enforces resolvable routes).
- **Type consistency:** `AdminNav::groups/current/paletteEntries` signatures match between T2 (definition) and T9/T10 (consumption); toast store API (`push/dismiss`) matches between T7 JS and T7/T11 Blade; `data-v-form`/`data-loading` contract matches T3 CSS ↔ T4 JS ↔ T11 usage.
- **Route/column caveats** are called out inline (T2 route names, T10 model columns, T11 environment guard) with the verification command to run — the contract is fixed, the literals are checked against the codebase at execution time.
