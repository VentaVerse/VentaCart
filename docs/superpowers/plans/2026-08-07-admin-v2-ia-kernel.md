# Admin IA Restructure & Extension Kernel Implementation Plan (Plan 5)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Execute `docs/superpowers/specs/2026-08-07-admin-ia-and-extension-kernel-design.md`: one grammar for URL = files = navigation, plus the extension kernel with Banners as first tenant. Behavior-frozen throughout — no admin workflow changes.

**Architecture:** Kernel first (it defines where extension URLs mount), then the route monolith splits and URIs restructure (route NAMES frozen — they are the stable API), then controllers and views physically move in mechanical, test-gated batches, then navigation derives from the path. Old URLs 301 via a dedicated redirects file.

**Tech Stack:** unchanged. Test harness: full AdminV2 suite (91 tests) + new `UrlGrammarTest` (URIs + redirects) + `ExtensionLifecycleTest`.

## Global Constraints

- **Route names are FROZEN.** Any diff hunk changing a `->name(...)` string or a `route('...')` call's name argument is a defect (exception: none). URIs, file paths, namespaces move freely behind them.
- **Behavior-frozen:** no controller logic, validation, query, or Blade-form change. Moves + `namespace`/`use`/`view()`/`@include`/`@extends` reference updates only. `git diff --stat` per task should show renames (use `git mv` so history follows).
- Suite baseline: 26 pre-existing failures; all AdminV2 green after EVERY task. `php artisan view:clear` after Blade moves; `npm run build` only if CSS/JS entries move (they don't).
- ABSOLUTE RULE: no destructive live-DB queries. New migrations run only in RefreshDatabase tests until the owner deliberately migrates.
- Design-system protocol still binds any NEW markup (Modules lifecycle UI): `docs/superpowers/plans/2026-08-07-admin-v2-reskin-protocol.md`.
- Commit per task `feat(admin-v2): …`/`refactor(admin-v2): …`, no Co-Authored-By. COMMIT ONLY task files; exclude `package-lock.json`, `docs/Screenshot*`, `docs/*.zip`, `docs/superpowers/plans/2026-05-01-*`, `public/pricing-mockup.html`.
- Local server port 8001 (8000 is another app); dev@venta.test / venta_dev_2026; prefix `opensesame`.

---

### Task 1: Extension kernel — registry, manager, manifest

**Files:**
- Create: migration `create_extensions_table` (`name` unique, `version`, `enabled` bool default true, `installed_at`), `app/Services/ExtensionManager.php`, `app/Models/Extension.php`, `extensions/banners/extension.json`
- Modify: `bootstrap/providers.php` (REMOVE the hardcoded `Extensions\Banners\BannerServiceProvider`, ADD `App\Providers\ExtensionServiceProvider`), create that provider; `composer.json` (replace the banners-specific PSR-4 entry with generic `"Extensions\\": "extensions/"` mapping each package's `src/` via per-extension convention — inspect the current entry first and pick the cleanest generic mapping that keeps `Extensions\Banners\…` resolving; document it), `config/admin.php` or new `config/extensions.php` (path setting)
- Test: `tests/Feature/AdminV2/ExtensionLifecycleTest.php`

**Interfaces:**
- `extension.json` schema: `{"name": "banners", "title": "Banners", "version": "1.0.0", "description": "…", "provider": "Extensions\\Banners\\BannerServiceProvider", "icon": "media", "type": "feature"}`.
- `ExtensionManager` (singleton): `discovered(): Collection` (manifests on disk, cached per-request), `registry(): Collection` (DB rows; empty-safe pre-migration via `Schema::hasTable` guard), `bootEnabled(): void` (register providers for installed+enabled — called from `ExtensionServiceProvider::register()`), `state(string $name): string` (`not_installed|enabled|disabled|missing_files|needs_migration`), `install(string $name): void` (validate manifest → run `php artisan migrate --path=extensions/{name}/database/migrations` via Artisan::call with `--force` — TEST-ONLY until owner migrates live → upsert row), `enable/disable(string $name)`, `uninstall(string $name): void` (`migrate:rollback --path=…` → delete row).
- Boot ordering: `ExtensionServiceProvider` must register extension providers during `register()` so their `boot()` hooks (routes, views, nav merges) run in the normal provider lifecycle. Guard: if the `extensions` table doesn't exist yet (fresh install mid-migrate), boot NOTHING and don't crash — banners must keep working on existing deploys ONLY after its row exists, so the migration seeds a row for `banners` (installed, enabled) since it's already live. Per the no-install-specific-migrations rule, that seeding belongs in the migration itself ONLY as data continuity for existing installs — document with a comment; fresh installs get banners via the Modules page.
- [ ] Steps: failing tests first (`test_discovery_reads_manifest`, `test_boot_skips_disabled`, `test_install_uninstall_runs_and_rolls_back_migrations` using a FIXTURE extension in `tests/fixtures/extensions/demo/` with one trivial migration + provider, `ExtensionManager` pointed at the fixture path via config override), then implement, then verify banners still serves (`admin.banners.index` route exists when row enabled — feature test with RefreshDatabase seeding the row). Commit.

---

### Task 2: Modules page lifecycle UI

**Files:** Modify `app/Http/Controllers/Admin/ModuleController.php` (cards now derive from `ExtensionManager::discovered()`+`registry()` for type=extension entries, keeping the existing hardcoded FEATURE cards — stock alerts, SEO, abandoned carts — as core-feature cards without lifecycle buttons), `resources/views/admin/modules/index.blade.php` (per-extension card gains Install / Enable / Disable / Uninstall buttons per `state()`; uninstall behind `x-admin.confirm-dialog` with explicit "removes this extension's database tables" message); add POST routes `admin.modules.install|enable|disable|uninstall` (`{name}` param validated against `discovered()`).
**Test:** extend `ExtensionLifecycleTest` — HTTP round-trip: install fixture via POST → state enabled; disable → provider not booted (its route 404s next request — assert via fresh app instance or route existence); uninstall → row gone.
- [ ] TDD steps, then commit.

---

### Task 3: Route monolith split + URL grammar + redirects

**Files:**
- Create: `routes/admin.php` (the single `Route::middleware(['auth','admin.session.cap'])->prefix(config('admin.prefix','admin'))->name('admin.')` group requiring section files), `routes/admin/{dashboard,sales,catalog,customers,content,marketing,settings,redirects}.php`
- Modify: `routes/web.php` (admin group replaced by `require routes/admin.php`; storefront untouched), `bootstrap/app.php` ONLY if route registration needs it (check how web.php is loaded — Laravel 12 `withRouting`), `extensions/banners/src/BannerServiceProvider.php` + `extensions/banners/routes/*` (mount under `config('admin.prefix').'/settings/modules/banners'` with the admin middleware stack — the kernel-mount convention; route names unchanged)
- Test: `tests/Feature/AdminV2/UrlGrammarTest.php`

**Interfaces / the URI map:** implement the spec's canonical map EXACTLY (spec §Part 1). Mechanics: move each route definition into its section file changing ONLY the URI string's prefix segments (e.g. `Route::get('orders', …)` → in sales.php under `Route::prefix('sales')`), names untouched. SEO workspace: `extensions/seo/…` → `marketing/seo/…`; SEO engine settings routes → `settings/modules/seo/…`; AC config routes → `settings/modules/abandoned-carts/…`; shipping child forms under `settings/shipping/…`; everything else per the map.
**Redirects:** `routes/admin/redirects.php` — inside the admin group, a table-driven list: `Route::redirect($old, $new, 301)` is NOT usable (needs runtime prefix); use `Route::get('{old-path}', fn () => redirect()->route($name, [], 301))` entries, or one catch-all mapper array. Cover every changed URI family incl. parameterized ones (`orders/{order}/edit` → named-route redirect preserving params via closure args). Also register bare legacy `admin/banners/*` → new home (outside the prefix group, with auth middleware).
**UrlGrammarTest:** for a representative page per section: `$this->get('/opensesame/sales/orders')->assertOk()` (seeded admin), old URL asserts 301 + Location = new URL; plus `test_no_admin_routes_left_in_web_php` (introspect route list: every `admin.*`-named route's URI starts with the prefix and matches the grammar's section roots).
- [ ] TDD steps; run FULL suite (route changes ripple); commit.

---

### Task 4: Controller moves — operational sections

**Files:** `git mv` into `app/Http/Controllers/Admin/{Sales,Catalog,Customers,Content,Marketing}/`: Order, OrderStatus→(stays Settings, Task 5), AbandonedCart, Review → Sales; Product, Category, Brand, Media, Tag → Catalog; Customer, CustomerGroup → Customers; Page, Blog, BlogCategory → Content; Coupon + `Extensions/SeoExtensionController`+`SeoRedirectController`+`SeoNotFoundController` → Marketing/Seo. Update `namespace` lines, all `use` imports referencing moved classes (grep `App\Http\Controllers\Admin\` across `routes/`, `app/`), and the section route files.
**Constraint check:** `php -l` clean; `grep -rn "Admin\\\\OrderController"` etc. finds zero stale references.
- [ ] Steps: move batch → fix references → `php artisan test --filter=AdminV2` green → commit (`refactor(admin-v2): controllers into section namespaces (operational)`).

---

### Task 5: Controller moves — settings section

**Files:** `git mv` into `Admin/Settings/`: SettingsHub, SettingsSection, Setting, PaymentSetting, PaymentMethodPage, PaymentRestriction, BankAccount, ShippingSetting, ShippingTest, FlatRate, WeightBasedMethod, FreeShippingRule, ShippingCourier, OrderStatus, EmailTemplate, StockNotification, Localization, User, RolePermission, Theme (+ HomepageSection, NavMenu, FooterColumn, Currency), ApiSyncLog, Module, Palette stays root (cross-cutting), Dashboard stays root. Same reference-fix discipline as Task 4.
- [ ] Steps identical to Task 4; commit.

---

### Task 6: View directory moves

**Files:** `git mv` per the spec's map: `admin/orders→admin/sales/orders`, `abandoned-carts→sales/abandoned-carts`, `reviews→sales/reviews`, `products|categories|brands|media|tags→catalog/…`, `customers|customer-groups→customers/…`, `pages|blog|blog-categories→content/…`, `coupons→marketing/coupons`, `extensions/seo→marketing/seo`, `payment-settings→settings/payments`, `shipping-settings→settings/shipping`, `flat-rates|weight-based-methods|free-shipping-rules→settings/shipping/…`, `order-statuses|shipping-couriers|email-templates|stock-notifications|localization|users|theme|api-sync-logs|modules→settings/…` (AC config views `abandoned-carts/{settings,rules,rule-form,templates,template-form}` → `settings/modules/abandoned-carts/`, splitting from the operational AC views). Update every `view('admin.…')`, `@include('admin.…')`, `@extends`, and Blade component references ACROSS controllers + views (grep-driven; the moved-view reference list is mechanical but long — verify with a script: every `view('admin.` string must resolve, assert via a test that renders route list pages or a `View::exists` sweep test added to UrlGrammarTest).
- [ ] Steps: move → fix references → `view:clear` → AdminV2 green + a `test_all_admin_views_resolve` sweep → commit.

---

### Task 7: Path-derived navigation

**Files:** Create `app/Support/Breadcrumbs.php` (URL segments → [{label,url}] via admin-nav + settings-nav registries + current page title); modify `resources/views/components/admin/page.blade.php` (auto-breadcrumb when none passed), `components/admin/shell.blade.php` + `settings-shell.blade.php` (active-state by longest URL-prefix match against registry URLs, replacing per-item `routeIs` patterns), `config/admin-nav.php` + `config/settings-nav.php` (keys renamed to equal URL segments where they diverge; `pattern` entries replaced by derived prefixes), remove now-redundant hand-written `:breadcrumb` arrays from section pages (grep sweep).
**Test:** extend UrlGrammarTest — breadcrumb assertions on 3 representative deep pages (settings/modules/banners page shows Settings→Modules→Banners trail).
- [ ] TDD steps; commit.

---

### Task 8: Verification sweep

- [ ] Full suite (baseline 26 held; AdminV2 + UrlGrammar + ExtensionLifecycle green). `npm run build && php artisan view:clear && php artisan config:clear && php artisan route:cache && php artisan route:clear` (route:cache round-trip proves cacheability, then clear for dev).
- [ ] Browser matrix (Playwright, port 8001, no destructive writes): walk Settings→Modules→Banners and confirm URL trail + breadcrumbs match at every step; the owner's original three complaints re-tested (payments restrictions path, settings back-navigation, modules→banners context); SEO workspace under marketing/seo with working back-link; 5 old bookmarked URLs 301 correctly in-browser; sidebar/hub/palette all reach the new URLs; light+dark spot-check; console clean. Screenshots to workspace.
- [ ] `git fetch origin && git merge origin/main --no-edit`; rerun AdminV2. Commit fixes or report clean.

---

## Self-review notes

- Spec coverage: kernel ✅ (T1-T2, banners tenant, providers.php freed), grammar ✅ (T3 URIs, T4-T5 controllers, T6 views), derived nav ✅ (T7), redirects ✅ (T3), DoD greps covered by UrlGrammarTest + sweep ✅ (T8). Route-name freeze enforced as a per-task reviewer check.
- Ordering rationale: kernel before routes (banners' mount uses it); routes before file moves (moves then update references against final route files); views after controllers (controller `view()` strings updated once, in T6).
- Known risks flagged for reviewers: composer PSR-4 generic mapping (T1 — must keep existing class names resolving), route:cache compatibility (T8 explicitly proves it), the AC config/operational view split (T6 — two destinations from one old folder).
