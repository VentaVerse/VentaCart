# Admin Information Architecture & Extension Kernel — Design Spec (Plan 5)

**Date:** 2026-08-07
**Branch:** `venta-v2` (continues the admin rebuild; Plans 1–4 complete)
**Scope:** Admin URL restructure + physical file reorganization + extension kernel. No storefront changes; no behavior changes to any admin workflow.

## Why

Live usage of the rebuilt admin exposed a structural problem the visual rebuild couldn't fix: navigation says "Settings → Modules → Banners" but the URL jumps `opensesame/settings` → `opensesame/modules` → `admin/banners` — three unrelated roots (the last with a hardcoded wrong prefix). The owner's diagnosis, confirmed by studying Bagisto 2.4 and OpenCart 3.0.5 sources (uploaded to `docs/`): **the URL is not just a URL — it is the file structure and the foundation of UX navigation.** OpenCart's legendary coherence comes from one rule: the route string IS the file path, and permissions/extensions derive from that same tree. Bagisto expresses the same idea in Laravel: `admin/{section}/{group}/{entity}/{verb}`, route files/controllers/views mirroring URL segments, nav+ACL+config as registries keyed off route names.

Separately, Venta's stated goal is OpenCart-grade modularity: extensions installed and uninstalled cleanly. Today installing an extension means hand-editing core files (`bootstrap/providers.php`, `composer.json` — how banners was wired). Laravel needs no OCMOD equivalent (OCMOD was file-patching to compensate for a missing module system; OpenCart 3 itself was replacing it with events): the native answer is provider-based self-registration + shared registries + a discovery/lifecycle kernel. That kernel is missing; this spec adds it.

Both halves land together because restructuring extension mounting without the kernel would re-couple extensions in prettier folders.

## Part 1 — The grammar: one path for URL, files, and navigation

**Rule:** `{admin-prefix}/{section}/{entity}/{verb}` where `{section}` is exactly a sidebar group or `settings`, and that same path names the route file, the controller namespace, and the view folder.

### URL map (canonical, complete)

```
{p} = config('admin.prefix')  — e.g. opensesame

{p}/dashboard
{p}/sales/orders            /create /{order}/edit /{order}
{p}/sales/abandoned-carts   (operational: list, show, notifications, recovered)
{p}/sales/reviews           /{review}
{p}/catalog/products        /categories /brands /media /tags        (+ CRUD verbs)
{p}/customers/customers     /groups
{p}/content/pages           /blog /blog-categories
{p}/marketing/coupons
{p}/marketing/seo/…         (SEO workspace: dashboard, review, slugs, entities/{type},
                             search-console, redirects, 404s, strategy)
{p}/settings                              hub
{p}/settings/{section}                    key-value config pages (general, mail, checkout,
                                          currencies, api, security, automations)
{p}/settings/payments                     landing; /settings/payments/{method}?tab=…
{p}/settings/shipping                     chain page (+ child forms: /settings/shipping/flat-rates/…,
                                          /settings/shipping/weight-based/…, /settings/shipping/free-rules/…)
{p}/settings/order-statuses · couriers · email-templates · stock-alerts
{p}/settings/localization                 (+ geo-zone edit)
{p}/settings/users · permissions · theme · api-logs
{p}/settings/modules                      extension manager (install/enable lifecycle UI)
{p}/settings/modules/{extension}/…        every extension's admin pages
       modules/banners/…                  banners (groups, editor, slides)
       modules/abandoned-carts/…          AC configuration (settings, rules, templates)
       modules/seo/…                      SEO engine config (claude, rules, strategy, gsc)
{p}/profile · login · logout              auth/account chrome
```

Judgment calls encoded (owner-approved direction): SEO *workspace* is daily work → `marketing/seo`; SEO *engine config* → `settings/modules/seo`. Abandoned-carts *operations* → `sales/abandoned-carts`; its *configuration* → `settings/modules/abandoned-carts`. Couriers are configuration → `settings/couriers`. Verb vocabulary follows Bagisto: bare index, `create`, `{id}/edit`, method-disambiguated PUT/DELETE on the same URI, `view/{id}`-style read pages where they exist today.

### File map (mirrors the URL, three trees)

```
routes/admin.php                      — single prefix+middleware group, requires per-section files
routes/admin/{dashboard,sales,catalog,customers,content,marketing,settings}.php
app/Http/Controllers/Admin/{Sales,Catalog,Customers,Content,Marketing,Settings}/…Controller.php
resources/views/admin/{sales,catalog,customers,content,marketing,settings}/{entity}/….blade.php
```

Every existing controller moves into its section namespace; every view directory moves under its section folder. Shared admin chrome stays put (`components/admin/*`, `resources/css/admin/*`, `resources/js/admin/*`). The `admin/partials/*` shared partials move to `admin/partials/` (unchanged) — they are cross-section by nature.

**Route names do not change.** They are the stable API every view/controller/test already uses (`route('admin.orders.index')`), which is what makes the physical reorganization mechanically safe. Naming alignment of route names to the new grammar is explicitly out of scope (a later, optional pass).

### Navigation derives from the path

- **Breadcrumbs become automatic:** a shared breadcrumb builder maps URL segments to labels via the nav registries (`sales` → Sales group label, `orders` → item label, deeper = page title). `x-admin.page` consumes it; hand-written breadcrumb arrays are removed.
- **Active state by path prefix** (Bagisto's lesson): sidebar/sub-sidebar highlight from longest matching registered URL prefix, replacing per-item `routeIs` patterns where they now diverge from the hierarchy.
- The two registries stay the single sources of truth (`config/admin-nav.php` sidebar = operational sections; `config/settings-nav.php` = the settings tree). Registry keys are renamed where needed to equal their URL segment — key = path segment becomes an invariant.

### Legacy URLs

Every URI that changes gets a 301 in a dedicated `routes/admin/redirects.php` (old path → `route(name)`), covering: all flat resource URLs (`{p}/orders` → `{p}/sales/orders`, etc.), `{p}/modules`, `{p}/extensions/seo/*`, `{p}/abandoned-carts/settings|rules|templates`, and the banners `admin/banners/*` paths. The redirect file is temporary scaffolding, deletable after the next major release.

## Part 2 — The extension kernel

### Manifest + discovery

Each extension is a self-contained folder:

```
extensions/{name}/
├── extension.json        {"name","title","version","description","provider","icon","type"}
├── src/…ServiceProvider.php    registers routes/views/migrations/registry entries in boot()
├── routes/admin.php            mounted by the KERNEL under {p}/settings/modules/{name}
├── database/migrations/
└── resources/views/
```

`ExtensionManager` (app/Services) scans `extensions/*/extension.json` at boot, cross-references the `extensions` DB table (name, version, enabled, installed_at — the `oc_extension` analog), and registers the provider for every installed+enabled extension. **`bootstrap/providers.php` and root `composer.json` are never edited again per-extension** (a generic `Extensions\` PSR-4 root covers all; banners' current hand-wiring is removed). Filesystem presence + DB registry = the truth, exactly OpenCart's model: file present but not installed → shows "Install"; row without files → flagged broken.

### Lifecycle (the Modules page becomes real)

- **Install:** validate manifest → run the extension's migrations → insert registry row (enabled) → its provider boots on next request; nav/settings-nav entries appear because the provider merges them.
- **Disable/Enable:** flip the row; provider not booted when disabled (pages 404 via the kernel, nav entries vanish).
- **Uninstall:** confirm-dialog with explicit data warning → rollback the extension's own migrations → delete row. Core never migrates an extension's tables.
- Version display + a `needs_migration` state when manifest version > registry version (updater groundwork).

### Registry seams (how extensions plug in without touching core)

An extension's provider may merge into: `admin-nav` (operational pages — rare), `settings-nav` (its settings card, auto-keyed `modules/{name}`), the Modules page card list (derived from manifests — replaces `ModuleController`'s hardcoded array), `PaymentManager`, the shipping provider chain, `Automation` rows (registered on install). Storefront view-injection (widget/slot events — the OCMOD replacement for themes) is explicitly deferred to the developer-platform roadmap; the kernel defines nothing storefront-side yet.

### First tenants

1. **Banners** migrates onto the kernel (manifest added, hand-wiring removed, URLs move to `settings/modules/banners`, prefix bug dies structurally) — proves install/enable/disable/uninstall end-to-end.
2. **Abandoned-carts config** and **SEO engine config** adopt the *mount convention only* (they are core features, not uninstallable extensions — they get the URL home without a manifest).
3. **QuadX** adopts the kernel when its branch is ported post-merge (its nav slots already reserved).

## Migration strategy

Wave-based, same machine as Plans 1–4, behavior-frozen throughout (the full AdminV2 suite + a new URL/redirect test class are the harness):
1. Kernel first (ExtensionManager, registry table, Modules lifecycle UI) with banners as proof.
2. Route monolith → per-section files + URI restructure + redirect map (names untouched).
3. Controller moves per section (namespace + import updates — mechanical).
4. View directory moves per section (`view('admin.orders…')` references updated — mechanical).
5. Breadcrumb/active-state derivation; registry key alignment.
6. Full sweep (suite + browser matrix + redirect spot-checks).

## Definition of done

- Address bar always equals the navigation trail; breadcrumbs render on every admin page from the path; back to parent always one click.
- `grep -r "admin/banners" routes/ extensions/` finds nothing hardcoded; every extension URL flows through `config('admin.prefix')`.
- Route files, controller namespaces, and view folders all mirror the URL grammar; zero admin routes left in `routes/web.php`.
- Banners installs, disables, and uninstalls from the Modules page with migrations handled; `bootstrap/providers.php` contains no per-extension entries.
- Every old admin URL 301s to its new home; AdminV2 suite green; 26-failure baseline untouched.

## Out of scope

Route-name renaming; storefront hook/slot system, marketplace, extension scaffolding CLI (developer-platform card VENT-12); auto-updater integration (card VENT-10); moving core features into extensions.
