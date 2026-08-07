# Fulfillment Polish Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Execute `docs/superpowers/specs/2026-08-08-fulfillment-polish-design.md` on branch `feat/quadx`: kernel adoption, URL-grammar homes, design-system re-shell, and a courier-agnostic shared layer proven by an "add a courier" checklist SPX will consume next.

**Architecture:** Port first (manifest → routes/nav → queue re-shell), then de-QuadX (schema-driven settings → status/tracking derivation), then feature tests and sweep. The extension stays self-contained under `extensions/courier-fulfillment/`; core files change only where the spec says (nav registries uncommented, `config('venta.extension_seeders')` repointed).

**Tech Stack:** unchanged. Reference protocol for all view work: `docs/superpowers/plans/2026-08-07-admin-v2-reskin-protocol.md`.

## Global Constraints

- Branch `feat/quadx`; **nothing merges to main**. Suite baseline: 26 pre-existing failures; AdminV2 (174) + new tests green after every task.
- The reskin protocol binds all Blade work (design system only, v-* tokens, no CSS in Blade, toggle-vs-checkbox controller check, behavior-frozen payloads).
- Route names `admin.fulfillment.*` and the extension's existing settings keys/values are FROZEN (QuadX staging verification of 2026-06-08 must remain reproducible; no data migration).
- DB rules: no destructive queries against the live dev DB. Sanctioned exception: installing/enabling courier-fulfillment via the Modules page on dev (its migration is additive + hasColumn-guarded). Uninstall (which rolls back orders columns) is verified in RefreshDatabase tests ONLY — never against dev.
- No real outbound HTTP in tests (`Http::fake`); QuadX staging credentials are never committed.
- Commit per task, no Co-Authored-By. COMMIT ONLY task files; exclude `package-lock.json`, `docs/Screenshot*`, `docs/*.zip`, `docs/superpowers/plans/2026-05-01-*`, `public/pricing-mockup.html`.
- Port 8001 only; dev@venta.test / venta_dev_2026; prefix `opensesame`.

---

### Task 1: Kernel adoption

**Files:** create `extensions/courier-fulfillment/extension.json` (`name: courier-fulfillment`, title "Courier Fulfillment", version 1.0.0, provider `Extensions\CourierFulfillment\CourierFulfillmentServiceProvider`, icon `fulfillment`, type `courier`); move `extensions/courier-fulfillment/database/seeders/CourierFulfillmentAutomationSeeder.php` → `src/Database/Seeders/` (namespace to match the runtime autoloader root `Extensions\CourierFulfillment\` → `src/`), update the provider's `config('venta.extension_seeders')` push accordingly; verify the provider's `loadMigrationsFrom`/`loadViewsFrom`/`loadRoutesFrom` paths against how banners does it post-kernel.
**Tests:** extend `tests/Feature/AdminV2/ExtensionLifecycleTest.php`: courier-fulfillment appears in `discovered()`; install runs its orders-columns migration (assert columns exist post-install, RefreshDatabase); uninstall rolls them back; disable → `admin.fulfillment.index` unregistered. Modules page shows the card with lifecycle buttons.
- [ ] TDD, commit `feat(fulfillment): kernel adoption — manifest, lifecycle, seeder autoload`.

---

### Task 2: URL grammar + navigation

**Files:** `extensions/courier-fulfillment/routes/web.php` — queue routes mount at `config('admin.prefix').'/sales/fulfillment'` (names `admin.fulfillment.*` unchanged), settings routes at `…'/settings/modules/courier-fulfillment'` (names unchanged); webhook + api routes untouched. `config/admin-nav.php` — uncomment the Fulfillment Sales entry (route `admin.fulfillment.index`, keywords booking/waybill/awb/tracking/quadx/courier). `config/settings-nav.php` — uncomment the Fulfillment hub card pointing at the settings route. Old flat `/fulfillment` URLs → 301 entries per house convention.
**Tests:** UrlGrammarTest additions — both pages 200 under new URIs (extension installed in-test), old URIs 301, grammar conformance holds, breadcrumb trail on the queue (`Sales › Fulfillment`) and settings (`Settings › Modules › Courier Fulfillment` — verify the Breadcrumbs extension-title derivation picks up the manifest).
- [ ] TDD, commit `feat(fulfillment): grammar homes and reserved nav slots`.

---

### Task 3: Queue re-shell

**Files:** `extensions/courier-fulfillment/resources/views/index.blade.php` (435 lines) → new shell per the reskin protocol. Filter tabs (To Book / In Transit / Delivered / All) via `x-admin.tabs` or pill row (implementer judgment, document); order table → `x-admin.table` with row-click opening the existing Alpine drawer (delegation-guard aware); 4-step stepper + timeline restyled with tokens; booking form controls → `x-admin.field/select/input/checkbox` (check the controller's boolean handling per protocol rule 5); Cancel booking → `x-admin.confirm-dialog`; Book/Rebook buttons with data-v-form loading states. **Drawer Alpine logic and every payload byte-identical** — the Orders-wave review method (whitespace-normalized JS diff) is the bar.
**Tests:** queue renders in shell with seeded orders across phases; filter param preserved.
- [ ] TDD, commit `feat(fulfillment): queue on the design system`.

---

### Task 4: Schema-driven settings

**Files:** `src/Contracts/CourierFulfillmentInterface.php` — add `settingsSchema(): array` (field key, label, type [text|password|select|toggle], options, `encrypted` flag, `mode_scoped` flag, help); implement in `Couriers/QuadX/QuadXFulfillment.php` declaring exactly today's keys (`quadx_mode`, per-mode api/secret pairs, defaults, `quadx_enabled` …) so stored values keep working. `src/Http/Controllers/CourierSettingsController.php` — ALLOWED_KEYS/ENCRYPTED_KEYS derived from every registered courier's schema + the static module-level keys (`fulfillment_enabled`, `cod_payment_methods`, webhook token stays server-generated); masking + blank-keeps semantics unchanged. `resources/views/settings.blade.php` → `x-admin.settings-shell wide`: module card + one card per `CourierManager::all()` rendered from its schema (Test Connection + Refresh statuses stay per-courier actions).
**Tests:** settings save round-trip through the derived allowlist; unknown keys discarded; encrypted mask + blank-keeps; second-courier proof — register a tiny FakeCourier in-test and assert its card/fields render and save with `{fake}_` keys untouched by QuadX's.
- [ ] TDD, commit `feat(fulfillment): schema-driven per-courier settings`.

---

### Task 5: De-QuadX status + tracking, README

**Files:** `src/StatusMapper.php` — `{provider}_status_map` derived from courier key (QuadX's key is `quadx` → stored data valid as-is); queue view's status humanization reads `{provider}_status_labels` the same way; tracking-link extraction moves behind the interface (add `trackingLinks(Order $order): array` with the QuadX implementation reading its own `courier_meta` shape; view loops the result). `extensions/courier-fulfillment/README.md` — the honest "add a courier" checklist (folder + register line; what appears automatically; what the courier class must implement incl. `settingsSchema` and `trackingLinks`).
**Gate:** `grep -rn "quadx" src/{FulfillmentService,StatusMapper,CourierManager}.php src/Http/` → zero literals outside `Couriers/QuadX/`.
**Tests:** StatusMapper with FakeCourier keys; view renders FakeCourier tracking links.
- [ ] TDD, commit `feat(fulfillment): courier-agnostic status and tracking`.

---

### Task 6: Feature-test hardening

**Files:** `tests/Feature/AdminV2/FulfillmentTest.php` — booking round-trip with `Http::fake` (asserts payload shape + idempotency guard blocks a double-book), rebook-after-cancel, webhook ingest → status map applied → order history + StockService path (assert via order state, not mocks where feasible), ERP book-courier endpoint auth (erp.auth pass/fail), poll command with faked responses honoring `terminalStatuses()`.
- [ ] TDD (these are new tests over frozen behavior — failures indicate REAL latent bugs; report any found rather than bending tests), commit `test(fulfillment): booking, webhook, poll, erp coverage`.

---

### Task 7: Verification sweep

- [ ] Full suite (baseline 26; all fulfillment/kernel/grammar tests green). `npm run build`, view/config clears.
- [ ] Browser (Playwright, port 8001): install courier-fulfillment from the Modules page against dev (sanctioned); walk Sales → Fulfillment (queue renders, tabs filter, drawer opens with booking form — do NOT submit a real booking), Settings → hub card → per-courier settings (QuadX card shows masked staging creds if present; do NOT alter stored values — note before/restore anything touched); breadcrumbs/active state on both; disable → nav entries vanish + queue 404s → re-enable; light+dark spot; console clean; screenshots to workspace.
- [ ] `git merge venta-v2 --no-edit` freshness (venta-v2 may receive testing-phase fixes), rerun AdminV2. Commit fixes or report clean.

---

## Self-review notes

- Spec coverage: Part 1 → T1-T3; Part 2 → T4-T5; tests → T6; DoD greps/lifecycle/browser → gates in T1, T5, T7. README in T5 (recipe exists only after de-QuadX defines it).
- Sequencing: queue re-shell (T3) precedes the queue's de-QuadX leak fix (T5) so the leak fix is a small targeted diff on the new markup; settings schema (T4) precedes StatusMapper derivation (T5) because the schema establishes the per-provider key convention.
- Risks flagged for reviewers: T1 seeder namespace vs runtime autoloader; T2 breadcrumb manifest-title derivation for extensions; T4 must not invalidate stored quadx_* values (FROZEN constraint); T6 may surface real latent bugs in never-feature-tested flows — they go to the review loop, not under the rug.
