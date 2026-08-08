# Fulfillment Workflow v2 + SPX Courier Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Execute BOTH `docs/superpowers/specs/2026-08-08-fulfillment-workflow-v2-design.md` (ERP-unified interface, common status spine, courier-first booking, per-courier settings pages, returns page) and `docs/superpowers/specs/2026-08-08-spx-courier-design.md` (SPX as the second courier), proven by a live UAT round-trip through the v2 flow.

**Architecture:** Workflow v2 first on QuadX alone (W1–W4) so the courier-first flow, spine, and per-courier settings pages exist and are proven against the frozen courier before SPX arrives; then SPX (T1–T4) drops into ready-made slots; then sweep + live gate. Interface evolves ONLY in the five sanctioned places listed in the workflow spec — each with a QuadX-safe default.

**Tech Stack:** unchanged. Ground truth: `docs/spx/api.md` + `integration-brief.md` for all SPX shapes (copy, never invent); the workflow-v2 spec's Part 2 for interface grammar; reskin protocol binds all view work.

## Global Constraints

- Branch `feat/quadx`; nothing merges to main. Suites: Fulfillment (35 at start) + AdminV2 209 green after every task; full-suite baseline 26 held.
- QuadX FROZEN in behavior and stored settings keys/values (its `statusPhase()` re-bucketing and settings-page relocation are the two sanctioned touches — raw maps/labels/payloads byte-identical; grep + suite proof per task).
- `Http::fake` everywhere except Task 6's sanctioned live-UAT gate. Credentials NEVER in code/tests/fixtures/commits — live gate enters them via the encrypted settings UI at runtime.
- Design system + reskin protocol bind view work. No destructive live-DB queries (T6's cleanup of its own test order's courier_* fields is the sanctioned, order-id-scoped exception).
- Commit per task, no Co-Authored-By, usual exclusions (`package-lock.json`, `docs/Screenshot*`, `docs/*.zip`, `docs/superpowers/plans/2026-05-01-*`, `public/pricing-mockup.html`).
- Port 8001; dev@venta.test / venta_dev_2026; prefix `opensesame`.

---

## Phase W — Workflow v2 (QuadX-only, SPX-ready)

### Task W1: Common status spine

**Files:** `src/Contracts/CourierFulfillmentInterface.php` — `statusPhase()` docblock re-specifies the 8 buckets (`to_book, booking, to_handover, in_transit, delivered, failed, cancelled, returning`); `Couriers/QuadX/QuadXFulfillment.php` re-buckets its raw statuses onto the spine (raw map/labels untouched); a small `src/Support/PhaseTone.php` (or equivalent) maps phase → tone per the spec table (success ONLY for to_book; delivered neutral; failed/cancelled danger; returning warning; rest info) with the tone philosophy documented in the class docblock verbatim-adapted from the ERP's ChannelStatusTone.
**Tests:** every QuadX raw status lands in exactly one bucket; tone map total (unknown phase → neutral); FulfillmentService phase queries return the new buckets; existing 4-step stepper derivation still resolves for the happy path.
- [ ] TDD, commit `feat(fulfillment): common 8-phase status spine with tone model`.

### Task W2: Queue on the ERP grammar + courier-first drawer

**Files:** `extensions/courier-fulfillment/resources/views/index.blade.php` + extension CSS — re-shell per spec Part 2: cmdbar (Poll statuses), segment bar with per-phase mono counts + far-right Returns aside link, "Parcels waiting for handover" per-courier chips on that tab, filters row (search/per-page, autosubmit), two-tier `co-band`/`co-itemrow` table (band per order; item rows with thumbs/SKU; total/courier/status/actions rowspanned; status = tone badge + raw label), visible-buttons action cell, mono pager. **Drawer becomes two-step**: step 1 = enabled-courier choice cards (name, mode chip; fee-estimate slot renders only when `estimateFee()` returns non-null); step 2 = the chosen courier's form from its option methods (QuadX: today's fields byte-identical payload), Back ghost + Book primary. Interface gains sanctioned #4 `estimateFee(Order, array): ?Money` (default null) and #5 `getPickupSlots(Order): array` (default `[]`) — QuadX inherits defaults, so step 2 for QuadX shows neither.
**Tests:** queue renders all phase tabs with counts from seeded orders; handover chips; two-step drawer markup (step 1 lists exactly enabled couriers); QuadX booking POST payload byte-identical (whitespace-normalized diff bar from the Orders wave); filter/tab params preserved.
- [ ] TDD, commit `feat(fulfillment): erp-grammar queue and courier-first booking drawer`.

### Task W3: Per-courier settings pages

**Files:** extension routes — `settings/modules/courier-fulfillment` stays (hub), add `settings/modules/courier-fulfillment/{courier}` (route name new under `admin.fulfillment.settings.*`, grammar-conformant); `CourierSettingsController` splits hub (module keys: `fulfillment_enabled`, `cod_payment_methods`, webhook token) vs per-courier (that courier's schema keys + its Test Connection / Refresh statuses / status-mapping UI move here); `settings.blade.php` → hub with courier cards (name, enabled, mode chip, credential-completeness hint); new `settings-courier.blade.php` rendered from `settingsSchema()` + per-courier action slots. Old combined-page anchors 301 per house convention. SettingsNav/Breadcrumbs pick up the sub-page (`Settings › Modules › Courier Fulfillment › QuadX`).
**Tests:** hub shows module fields + one card per registered courier; per-courier page saves ONLY that courier's keys through the derived allowlist (FakeCourier proof: saving fake's page never touches quadx_*); masking + blank-keeps intact; status-map save round-trip from the new location; 301s; UrlGrammarTest additions.
- [ ] TDD, commit `feat(fulfillment): settings hub with per-courier pages`.

### Task W4: Returns monitoring page

**Files:** route `admin/sales/fulfillment/returns` (name under `admin.fulfillment.returns`), controller method listing orders in `returning`/`failed` phases (order no link, courier, raw status + tone badge, last timeline event, created), view on the same grammar (segment aside on the queue links here; this page's aside links back "All shipments"); empty state honest ("Nothing coming back").
**Tests:** page 200 + breadcrumb; only returning/failed-phase orders listed; queue aside link present.
- [ ] TDD, commit `feat(fulfillment): returns monitoring page`. Also: add roadmap card "RMA / customer returns process" to the boards backlog (one high-level card, no sub-cards).

---

## Phase T — SPX on the v2 foundation

### Task T1: SpxClient + signer + settings schema + registration

**Files:** `extensions/courier-fulfillment/src/Couriers/Spx/{SpxClient,SpxFulfillment}.php`; one `CourierManager::register` line. SpxFulfillment starts with `getKey()='spx'`, `getName()`, `isEnabled()` (flag + credential completeness), full `settingsSchema()` (`spx_enabled`, `spx_mode` uat|production, `spx_app_id`, `spx_secret_key` encrypted, `spx_user_id`, `spx_user_secret` encrypted, `spx_pouch_presets` JSON with S/M/L defaults), `testConnection()` (cheap authenticated call from the docs), stubs for the rest.
**SpxClient:** base URLs per mode; JSON-encode body ONCE → sign those exact bytes (HMAC-SHA256 over `{app_id}_{timestamp}_{random_num}_{payload}`, hex) → send the SAME string via `Http::withBody()`; headers per docs; envelope unwrap with typed `SpxApiException` carrying `ret_code`.
**Tests (TDD):** signer against the VERIFIED TEST VECTOR in integration-brief.md; SPX card appears on the W3 settings hub + its per-courier page renders from schema (incl. pouch-presets editor); testConnection graceful without creds; QuadX settings untouched.
- [ ] TDD, commit `feat(spx): signed client, schema, registration`.

### Task T2: Booking, cancel, tracking, fees

**Files:** `SpxFulfillment` fills `createShipment/cancelShipment/getTracking/terminalStatuses/cancelableStatuses/statusPhase/mappableStatuses/getServiceTypes/getParcelOptions/getShippingPaymentOptions/trackingLinks` + v2 methods `estimateFee` (2.1 `batch_check_order`) and `getPickupSlots` (3.2 pickup-time endpoint); interface gains sanctioned #1 `resolvePendingCreation(Order): ?string` default null; `PollCourierStatus` gains the pending-creation pass (courier_status `creating` → resolve via `batch_no` in courier_meta).
**Shapes from docs/spx/api.md verbatim:** 1.2 batch_create_order (sender/deliver hierarchy via 5.1 Address API data cached settings-style; fulfillment_info: `payment_role` from who-pays choice, `cod_collection`/`cod_amount` from cod_payment_methods mapping; parcel dims from the chosen pouch preset OVERRIDING product dims, or custom dims); polling get_order_create_result bounded ~15s → else persist `creating`+batch_no; batch_cancel_order (real array; 13002 friendly); batch_search_order (13251 backoff; routes[] → timeline); status-codes table → mappableStatuses + 8-phase statusPhase; 2.2 `batch_get_asf` actual fee surfaced into courier_meta post-booking.
**Tests:** Http::fake round-trips asserting payload shapes; pouch preset dims land in parcel_info; async both paths; cancel success+13002; tracking success+13251-retry; estimateFee and getPickupSlots shapes; mixed-queue FakeCourier still green.
- [ ] TDD, commit `feat(spx): booking, cancellation, tracking, fees with async create`.

### Task T3: AWB storage, webhook, F2 fix

**Files:** `printLabel()` — async label batch → PDF to `storage/app/courier-labels/spx/{tracking}.pdf` → authenticated streaming route; interface docblock note (sanctioned #2). Webhook: SPX branch of `{provider}` dispatch — HMAC verify, **200 immediately + queued processing** (mechanism chosen for sub-3s ack on this stack, documented). F2: tracking-number fallback lookup gains `courier_provider` scope; README caveat → "fixed".
**Tests:** label download/store/serve-cached; webhook signature pass/fail; fast-ack asserted; F2 collision test (same tracking no, two providers).
- [ ] TDD, commit `feat(spx): perishable AWB storage, signed fast-ack webhook, courier-scoped lookups`.

### Task T4: SPX in the v2 UX

**Files:** verify/adjust only where needed: step 1 shows SPX card when enabled; step 2 renders SPX's form (pouch presets + custom-dims escape, who-pays, pickup slots, estimated fee before Book); "Creating…" (booking phase) honest on chip/stepper; actual fee (`batch_get_asf`) shown on booked orders where present; SPX settings page: UAT-mode note linking `docs/spx/NSS OpenAPI UAT Order Creation Guide.pdf`, Create-shipper-account action (3.1 when user_id empty, stores encrypted).
**Tests:** step-2 SPX render (Http::fake for slots/fee); creating-state render; create-account round-trip; actual-fee display.
- [ ] TDD, commit `feat(spx): booking flow and settings page UX`.

---

### Task 5: Fake-HTTP verification sweep

- [ ] Full suites; `npm run build` + clears. Browser (Playwright, 8001): queue tabs/counts, two-step drawer (QuadX step 2 unchanged; SPX step 2 with dummy creds via UI, flipped back after), returns page, settings hub → both courier pages, breadcrumbs, light+dark, console clean, screenshots. QuadX grep + suite frozenness proof. Commit fixes or report clean.

### Task 6: Live UAT gate (sanctioned real outbound; owner-in-the-loop)

- [ ] Enter SPX UAT creds via the encrypted settings UI (from coordinator at runtime). Test Connection → success.
- [ ] If `spx_user_id` empty: run Create shipper account once (values stay encrypted in DB, unlogged).
- [ ] Dev test order on UAT-whitelist lane #3 (Intramuros Barangay 654 → Ermita Barangay 659), within limits; book through the REAL v2 flow: choose SPX → pouch preset → see estimated fee → book.
- [ ] Async create resolves → tracking number; poll/track (expect 13251 backoff to engage); AWB downloads and opens; actual fee appears; cancel pre-pickup (success); timeline + returns page spot-check.
- [ ] Evidence screenshots + report (doc deviations → docs/spx/index.md quirks). Cleanup: clear that one order's courier_* fields (scoped); creds stay (owner's call).
- [ ] Commit fixes as `fix(spx): uat gate fixes`.

---

## Self-review notes

- Directive coverage: unified ERP look → W2 (+W3/W4 same grammar); common status spine → W1 (validated against Shopee/Lazada/TikTok vocab); returns separate → W4; per-courier settings → W3; courier-first workflow → W2 step 1; pouch-for-SPX with dims override → T1 presets + T2 parcel_info + T4 UI; who-pays → existing option method (QuadX) + payment_role (SPX); "how SPX charges me" → estimateFee pre-book + batch_get_asf actual.
- Interface evolution ledger (all five sanctioned, all QuadX-safe defaults): resolvePendingCreation (T2), printLabel note (T3), statusPhase re-bucket (W1), estimateFee (W2/T2), getPickupSlots (W2/T2).
- Risks for reviewers: W2 payload byte-identity for QuadX through a rebuilt drawer (the hard gate); W3 allowlist derivation must stay per-courier airtight; T1 signer byte-exactness; T3 ack mechanism on no-worker dev; T6 only real-HTTP task.
