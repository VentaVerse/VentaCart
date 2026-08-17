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
| stock-alerts | waitlist + notified admin, storefront subscribe, stock:notify, stock:check-low | 1 |
| abandoned-carts | carts list, notify rules, cart:notify | 1 |
| seo | 9-page space, AI suggestions + cron, redirects, 404s, Search Console, slugs, sitemap (+ /sitemap.xml route) | 1 |
| payment-bank-transfer | bank accounts + QR config, checkout method | 2 |
| payment-cod | COD method | 2 |
| payment-hitpay | HitPay adapter + webhook | 2 |
| shipping-flat-rate | flat rates | 2 |
| shipping-weight-based | weight matrix methods | 2 |
| shipping-free | free shipping + rules | 2 |
| shipping-dph / shipping-dhl | DPH explorer + DHL rates | 2 |
| reviews | moderation, storefront reviews, review:send, ERP review endpoint | 3 |
| blog | posts + categories, storefront blog | 3 |
| erp-sync | Erp* API endpoints, sync logs, token | 3 |

## Wave 0: the mechanism (prerequisite for everything)

1. **Self-registration.** An extension contributes its own admin nav
   items, settings cards, and schedules from its manifest or service
   provider; core config registries stop hardcoding extension entries
   (today fulfillment + abandoned-carts sit in config/admin-nav.php).
   Registry merge is runtime, guarded, and cached; disabled extension =
   entries gone. This closes the last decoupling gap.
2. **Typed provider contracts.** PaymentManager and ShippingQuoteService
   accept providers registered by extensions (the CourierManager pattern,
   which is the proven reference). Per-provider enable + sort order.
   Guardrail: the checkout settings page and dashboard warn when zero
   payment or shipping methods are enabled; disabling the last one is
   allowed but loudly stated (a store that cannot check out says so).
3. **Adopt-on-install migrations.** Converted extensions' tables already
   hold live data. Their install migrations guard with Schema::hasTable
   and adopt, never recreate; fresh installs create cleanly. No one-off
   data migrations shipped (standing rule).
4. **The Extensions menu.** New top-level nav group "Extensions" in the
   classic sidebar (between Marketing and Settings), with THREE
   children (owner ruling: couriers are part of shipping):
   All Extensions (the directory, relocated out of Settings),
   Payments, and Shipping (rate methods section + couriers section).
   Individual extensions never get menu items; only typed categories
   do, so the menu never sprawls. Feature extensions (Blog, SEO,
   Reviews...) appear as directory rows only, their operations in the
   work nav. Settings goes back to being the website's core settings
   only. Crossings by labeled buttons both ways.
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
