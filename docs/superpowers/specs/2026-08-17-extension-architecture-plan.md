# Extension Architecture: core is the engine, everything else is a package

Owner ruling, 2026-08-17. Core is what a store cannot sell without: the
end-to-end ordering system and inventory. Everything a store can live
without is an extension: a packaged feature shipped in the repo, with a
real merchant-facing lifecycle (install, enable, disable) through
Venta's extension mechanism. This corrects the original build, which
installed optional capability directly into app/.

Reference triangulation (all inspected from primary sources on disk):

- OpenCart (newgearday-old): merchant toggles via typed extension
  categories (Order Totals, Payments, Shipping, Modules), but dev-side
  chaos (OCMOD patches core files; controllers dropped into core dirs).
- Bagisto 2.4 (docs/bagisto-2.4.zip): 40 packages, zero toggles, and
  cosmetic separation only (Admin hard-references optional packages in
  18 files; the CMS admin controller lives inside the Admin package;
  the admin menu hardcodes optional entries).
- Venta target: both halves, with the discipline neither had. Mandatory
  can never be switched off; optional always can.

## Vocabulary

One word: **Extension**. "Module" retires (OpenCart's "Modules" is just
one extension type). The code already agrees: extensions/,
extension.json, ExtensionManager, the extensions table.

## What stays core (app/): the engine

Catalog (products, variants, categories, brands, tags, media, pricing,
badges), inventory (StockService), cart + checkout (CartService,
CheckoutService, address system, geo zones), orders (statuses, history,
snapshots), customers (accounts, groups, auth), the payment FRAMEWORK
(PaymentManager registry, restrictions engine, checkout integration),
the shipping FRAMEWORK (quote chain, couriers registry), storefront
engine (theme system, presets, core pages), platform (settings, users,
permissions, email templates, mail, localization, currencies,
dashboard, automations, ExtensionManager itself).

Frameworks stay; METHODS become extensions, exactly OC's split
(Extensions > Payments / Shipping hold the methods; checkout holds the
machinery).

Small fry that stays core by judgment call (extraction ceremony
outweighs payoff): Turnstile, Impersonation, Recently Viewed, ECB
currency cron, contact page. Coupons stay core until the totals
pipeline (#124) exists; then application becomes a stage while
management stays in Marketing (OC's split, minus OC's uninstallable
Sub-Total foot-gun; mandatory stages are never disableable).

## What becomes an extension

Already extensions: banners, courier-fulfillment.

To convert from app/:

| Extension | Contents today | Wave |
|---|---|---|
| stock-alerts | DONE (first conversion): waitlist + notified admin, storefront subscribe, stock:notify, stock:check-low | 1 |
| abandoned-carts | DONE (second conversion): ops pages in Sales via manifest admin_nav, config (settings/rules/templates) under Extensions, cart:notify, template seeder, full v3 rebuild | 1 |
| seo | DONE (wave 1 closes): full 9-page space, both crons, redirects + 404 middleware guarded in core, adopt-on-install; CoreFeatures shim deleted with it | 1 |
| payment-bank-transfer | DONE: method factory, accounts model/CRUD/QR, webhook-free, adopt-on-install | 2 |
| payment-cod | DONE: method + config view | 2 |
| payment-hitpay | DONE: method, webhook route + controller, masked-key composer | 2 |
| shipping-flat-rate | DONE: provider, model, CRUD, adopt-on-install | 2 |
| shipping-weight-based | DONE: provider, models, CRUD, pivots, adopt-on-install | 2 |
| shipping-free | DONE: provider, rules model/CRUD, badge source (guarded core refs) | 2 |
| shipping-dhl | DONE: provider, API service, credentials page | 2 |
| reviews | DONE: all three surfaces (admin, storefront token page, ERP push), review:send, v3 page rebuild folded in, guarded rating aggregates | 3 |
| blog | DONE: admin + storefront, both nav items via manifest, SEO extension's blog arm guarded cross-extension | 3 |
| erp-sync | DONE (wave 3 closes): the four push controllers + api/v1 group; DEVIATION: token, erp.auth, sync logs, shared traits stay core as shared API framework (reviews' endpoint rides it) | 3 |

## Wave 0: the mechanism (prerequisite for everything)

1. **Self-registration.** An extension contributes its own admin nav
   items, settings cards, and schedules from its manifest or service
   provider; core config registries stop hardcoding extension entries
   (done: fulfillment and abandoned-carts both self-register via their
   manifests' admin_nav; config/admin-nav.php carries no extension rows).
   Registry merge is runtime, guarded, and cached; disabled extension =
   entries gone. This closes the last decoupling gap.
2. **Typed provider contracts.** DONE (wave 2): PaymentManager and
   ShippingQuoteService both carry the CourierManager-style sign-in
   sheet; method extensions register from their providers (quote
   providers with a chain sequence). Registration IS the enable: the
   old *_enabled settings retired via continuity migrations. The
   standard button set (Install / Uninstall / Enable-Disable /
   Configure) renders on every listing through the shared
   ExtensionCard + lifecycle-actions partial.
   Guardrail: the checkout settings page and dashboard warn when zero
   payment or shipping methods are enabled; disabling the last one is
   allowed but loudly stated (a store that cannot check out says so).
3. **Adopt-on-install migrations.** Converted extensions' tables already
   hold live data. Their install migrations guard with Schema::hasTable
   and adopt, never recreate; fresh installs create cleanly. No one-off
   data migrations shipped (standing rule).
4. **The Extensions menu.** New top-level nav group "Extensions" in the
   classic sidebar (between Marketing and Settings), with FOUR
   children plus Themes (owner rulings: couriers are part of shipping;
   2026-08-18 added the residual category and made the theme editor an
   extension): All Extensions (the full rundown with
   each row's category named), Modules (the residual category page,
   OpenCart's word for everything that is not a payment or shipping
   concern; manifest type drives the mapping: feature -> Modules,
   courier/shipping -> Shipping, payment -> Payments), Payments, and
   Shipping (rate methods section + couriers section). Categories the
   references carry that Venta deliberately does NOT: captcha (core,
   Cloudflare Turnstile in Settings > Security), feeds/analytics
   (core settings). Individual extensions never get menu items; only
   typed categories do, so the menu never sprawls. Feature extensions
   (Blog, SEO, Reviews...) appear as directory rows only, their
   operations in the work nav. Settings goes back to being the
   website's core settings only. Crossings by labeled buttons both
   ways. 'modules' is a reserved extension name (the category page's
   URL segment). The theme EDITOR is an extension (type theme, nav item
   Themes, mounted at extensions/theme, ships installed on every store
   because a store needs its look on day one); the theme SYSTEM
   (ThemeService, rendering, the homepage/nav/footer tables) stays core
   per the framework-stays-core doctrine.
5. **Rename.** Modules -> Extensions everywhere in UI (page titles, nav
   labels, breadcrumbs, hub cards). Route names migrate
   (admin.modules.* -> admin.extensions.*) with redirects; tests repin.
6. **Vendor directory decision.** extensions/ stays flat for now with
   `author` in the manifest; a vendor level (extensions/venta/...) is
   deferred until third-party extensions are real. (Bagisto's
   packages/Webkul is their vendor dir; we get the same later without
   restructuring twice.)

## Conversion recipe (per extension, waves 1-3)

1. Create extensions/{name}/ with extension.json, service provider,
   src/ (controllers, models, services, commands), routes/, resources/
   (views), database/migrations (adopt-on-install).
2. Move code from app/, update namespaces to Extensions\{Name}\.
3. Register nav + settings + schedules through the wave-0 mechanism.
4. Retire the CoreFeatures shim for that feature; the extension
   lifecycle's enable/disable takes over (owner requirement: on/off is
   the extension mechanism itself).
5. Keep all feature tests, add lifecycle tests (adopt-install, enable,
   disable; uninstall stays UI-hidden for repo-shipped extensions).
6. Verify storefront + admin + crons; commit per extension.

## Sequencing against the admin v3 migration

The admin migration (Reviews pages, Products wave) continues in
parallel; extension conversion does not block it. Recommended order:
Wave 0 first (it changes nav and the Modules page, so doing it before
more page migrations avoids rework), then interleave Wave 1 with the
Products wave, then Waves 2-3. Reviews' page migration folds into its
extension conversion (wave 3) rather than being done twice.

## Cards

#123 (extensionize, superseded by this spec's waves), #124 (totals
pipeline), #122 (ops/settings splits, absorbed by wave 0's menu), #121
(savebar leftovers, absorbed by wave 1 abandoned-carts), developer
platform card (self-registration is its first two items).

## Anti-goals: this is not WordPress

Design rationale, not an owner decree: Paolo voiced distaste for the
WordPress model and left the call to the architecture. These are the
architect's guardrails against the failure modes that made WordPress
what it is; any of them can be revisited as an ordinary design
discussion. The folder-plus-switch gesture is shared with every
healthy extension system (VS Code, browsers, OpenCart at its best).
What this design refuses is what makes WordPress WordPress:

1. **Core stays the whole store.** The engine is complete and
   non-optional; a zero-extensions install still sells. Nothing
   essential ever migrates out of app/. (The owner's core definition,
   enforced.)
2. **Typed contracts only, never generic hooks.** An extension is a
   courier, a payment method, a rate provider, a feature with declared
   nav entries and schedules. There is NO filter-anything API: an
   extension cannot rewrite core behavior, intercept another
   extension, or inject into arbitrary pages. Each new extension
   capability arrives as a new NAMED contract, deliberately, in this
   spec, never as a convenience hook. Hook creep is how WordPress
   happened; refuse it one request at a time.
3. **No arbitrary code installed from inside the admin.** Repo-shipped
   and reviewed today; any future marketplace is curated and versioned
   against the manifest's compatibility declaration.
