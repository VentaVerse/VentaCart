# Fulfillment Module Polish — Design Spec

**Date:** 2026-08-08
**Branch:** `feat/quadx` (venta-v2 merged in at `389b7e6`; nothing goes to main)
**Scope:** Port the courier-fulfillment extension onto the rebuilt admin (kernel + URL grammar + design system) and make its shared layer genuinely courier-agnostic, so SPX (UAT docs in hand) drops in as a folder, and LBC/QuadX-production follow when unblocked.

## Why

The fulfillment module was built pre-rebuild: its two admin views extend the deleted old layout, it registers via the hand-wiring the kernel abolished, and it lives at a flat `/fulfillment` URL outside the grammar. Meanwhile its architecture is already ~80% courier-agnostic (14-method `CourierFulfillmentInterface`, `CourierManager` registry, generic booking service with idempotency guards, provider-routed webhooks, generic poll command) — but the remaining 20% hardcodes QuadX in exactly the places a second courier needs: the settings page is a single QuadX card, the settings controller's key allowlists are QuadX-named, the status-map setting keys are literal `quadx_*` strings in code, and the queue view reads two QuadX-shaped structures directly.

Business context: QuadX production keys not yet provided (staging-verified only); SPX has provided UAT docs; LBC is blocked on a permission request from our side. Polishing the module now is the prerequisite for all three.

## Decisions

| Decision | Choice |
|---|---|
| Where work happens | `feat/quadx`, continuing the [TESTING] phase; main untouched |
| Extension identity | Kernel tenant: `extensions/courier-fulfillment/extension.json`, installed/enabled via the Modules page like banners; no `bootstrap/providers.php`/`composer.json` entries (already dropped in the merge) |
| Queue URL | `{p}/sales/fulfillment` — operational, Sales sidebar group (reserved slot uncommented) |
| Settings URL | `{p}/settings/modules/courier-fulfillment` — hub card under Sales channels (reserved slot uncommented) |
| Route names | Keep the extension's existing `admin.fulfillment.*` names (URIs move; names are the stable API) |
| Courier-agnostic settings | One settings page looping `CourierManager::all()` — each registered courier renders its own card (credentials, staging/live mode, defaults, status map, test connection). Key names derive from `{provider}` at runtime; since QuadX's provider key is `quadx`, existing stored `quadx_*` settings values remain valid — **no data migration** |
| SPX readiness proof | "Add a courier" checklist documented in the extension's README: one `Couriers/{Name}/` folder + one `CourierManager::register()` line; settings card, queue option, webhook route, poll coverage all appear automatically |

## Part 1 — Port (kernel + grammar + shell)

1. **Manifest + lifecycle:** `extension.json` (`name: courier-fulfillment`, provider class, icon `fulfillment`, type `courier`). Registry row via the Modules page (dev) — no continuity seed needed (feature was never live on this DB). Verify install/enable/disable/uninstall end-to-end incl. the extension's orders-columns migration running on install. Seeder autoload: the automation seeder lives outside `src/` — move it under `src/Database/Seeders/` so the kernel's runtime autoloader covers it, and re-point `config('venta.extension_seeders')` accordingly.
2. **Routes:** extension's `routes/web.php` mounts through `config('admin.prefix')` under the grammar homes above; webhook route stays public-path with token auth (`hash_equals`, unchanged); ERP api route unchanged.
3. **Views re-shelled** per the reskin protocol (behavior-frozen: identical form payloads, Alpine drawer logic verbatim, class swaps + component adoption):
   - Queue (435 lines): `x-admin.shell` + `page`, filter tabs → `x-admin.tabs` or pill row (implementer judgment per protocol), table → `x-admin.table` (row-click opens drawer), stepper/timeline restyled with tokens, booking drawer forms on `x-admin.field/select/input`, confirm-dialog for Cancel booking.
   - Settings (183 → per-courier loop): `x-admin.settings-shell wide`, one card per registered courier + a module-level card (`fulfillment_enabled`, COD payment mapping, webhook URL copy). Encrypted keys masked, blank-keeps semantics (established pattern).
4. **Nav:** uncomment both reserved slots; breadcrumbs/active state derive from path automatically. Palette keywords: quadx, booking, waybill, awb, tracking, courier.

## Part 2 — De-QuadX the shared layer

1. **Settings controller:** `ALLOWED_KEYS`/`ENCRYPTED_KEYS` become derived per provider from a `CourierFulfillmentInterface` addition (e.g. `settingsSchema(): array` declaring fields + which are secret + which are mode-scoped) — the controller and the settings view both render from the schema. Module-level keys (`fulfillment_enabled`, `cod_payment_methods`, webhook token) stay static.
2. **Status mapping:** `StatusMapper` reads `{provider}_status_map`; queue view's status humanization reads `{provider}_status_labels` — both derived from the courier key, no literals. History comments already generic.
3. **Queue view leaks:** tracking-link extraction moves behind the interface (new `trackingLinks(Order): array` or fold into existing `getTracking`) so the view never inspects a courier-shaped `courier_meta` blob.
4. **Feature tests** (the current 97 lines are pure-function only): booking round-trip with `Http::fake` (idempotency guard asserted), webhook ingest → status map → order history, queue page render + filter, settings save (schema-driven allowlist + encrypted mask + blank-keeps), ERP book-courier endpoint auth. All in `tests/Feature/AdminV2/FulfillmentTest.php` following house conventions.

## Definition of done

- Modules page installs/enables courier-fulfillment; disable makes `sales/fulfillment` 404 and the nav entries vanish; uninstall rolls back its orders columns.
- Queue lives at `{p}/sales/fulfillment` in the new shell with correct breadcrumbs/active state; settings at `{p}/settings/modules/courier-fulfillment`; both design-system-clean (grep gate).
- `grep -rn "quadx" extensions/courier-fulfillment/src/{FulfillmentService,StatusMapper,CourierManager,Http}` returns zero literals outside `Couriers/QuadX/` (settings schema + status keys + tracking links all provider-derived).
- QuadX still works against staging exactly as live-verified 2026-06-08: same payloads, same settings values, no data migration.
- The "add a courier" README checklist exists and is honest — validated by the SPX plan consuming it next.
- AdminV2 suite green incl. new FulfillmentTest; 26-failure baseline held.

## Out of scope

SPX implementation (next plan — grounded in the UAT docs once provided); QuadX production cutover (blocked on their keys — the staging/production toggle already exists); LBC (blocked on our permission request); any checkout/storefront coupling (the module has none, keep it that way); merging anything to main.
