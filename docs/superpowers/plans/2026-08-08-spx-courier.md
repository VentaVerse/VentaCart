# SPX Courier Extension Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Execute `docs/superpowers/specs/2026-08-08-spx-courier-design.md` — SPX as the second courier on the fulfillment foundation, proven by a live UAT round-trip.

**Architecture:** Client+signer first (the risky cryptography, test-vector-proven), then the adapter in two halves (booking/cancel/tracking, then AWB+webhook+F2), then fake-HTTP feature tests, then the live UAT gate. Interface evolves in exactly two sanctioned places (`resolvePendingCreation`, printLabel stored-asset contract note) — each with a QuadX-safe default.

**Tech Stack:** unchanged. Ground truth for every payload/endpoint/error code: `docs/spx/api.md` + `integration-brief.md` — implementers copy shapes from there, never invent.

## Global Constraints

- Branch `feat/quadx`; nothing merges to main. Suites: Fulfillment 35 + AdminV2 209 green after every task; full-suite baseline 26 held.
- QuadX FROZEN: its behavior, settings keys/values, and tests must be untouched (grep + suite proof per task).
- `Http::fake` everywhere except Task 6's sanctioned live-UAT gate. Credentials NEVER appear in code, tests, fixtures, or commits — the live gate enters them via the encrypted settings UI at runtime.
- Design system + reskin protocol bind any view work. No destructive live-DB queries (Task 6's cleanup of its own test order's courier_* fields is the sanctioned exception, scoped to that one order id).
- Commit per task, no Co-Authored-By, usual exclusions (`package-lock.json`, `docs/Screenshot*`, `docs/*.zip`, `docs/superpowers/plans/2026-05-01-*`, `public/pricing-mockup.html` — note `docs/spx/*.pdf` IS committed already, leave it).
- Port 8001; dev@venta.test / venta_dev_2026; prefix `opensesame`.

---

### Task 1: SpxClient + signer + settings schema + registration

**Files:** create `extensions/courier-fulfillment/src/Couriers/Spx/{SpxClient,SpxFulfillment}.php`; one `CourierManager::register(SpxFulfillment::class)` line in the provider. SpxFulfillment starts with: `getKey()='spx'`, `getName()`, `isEnabled()` (flag + credential completeness like QuadX), full `settingsSchema()` per the spec (`spx_enabled`, `spx_mode` uat|production, `spx_app_id`, `spx_secret_key` encrypted, `spx_user_id`, `spx_user_secret` encrypted), `testConnection()` (cheap authenticated call — pick from docs, e.g. address or pickup-time endpoint), stubs throwing `NotImplemented` for the rest (Task 2/3 fill them).
**SpxClient:** base URLs per mode from `docs/spx/integration-brief.md`; request builder that (1) JSON-encodes the body ONCE, (2) signs those exact bytes (HMAC-SHA256 over `{app_id}_{timestamp}_{random_num}_{payload}`, hex), (3) sends the SAME string via `Http::withBody()` — never re-serialized; headers per docs (`app-id`, `timestamp`, `random-num`, `check-sign`); envelope unwrap (`ret_code`/`message`/`data`) with a typed `SpxApiException` carrying the code.
**Tests (TDD):** the signer against the VERIFIED TEST VECTOR in integration-brief.md (this is the point of the task — byte-exactness proven); settings card renders for SPX automatically (FakeCourier pattern precedent); testConnection graceful without creds; QuadX settings untouched.
- [ ] TDD, commit `feat(spx): signed client, schema, registration`.

---

### Task 2: Booking, cancel, tracking

**Files:** `SpxFulfillment` fills `createShipment/cancelShipment/getTracking/terminalStatuses/cancelableStatuses/statusPhase/mappableStatuses/getServiceTypes/getParcelOptions/getShippingPaymentOptions/trackingLinks`; interface gains `resolvePendingCreation(Order $order): ?string` with a default no-op (QuadXFulfillment inherits/returns null); `PollCourierStatus` gains the pending-creation pass (orders with courier_status `creating` → resolve via batch_no in courier_meta).
**Shapes from docs/spx/api.md verbatim:** 1.2 batch_create_order (payload incl. sender/deliver info hierarchy, fulfillment_info with cod_collection/cod_amount from the existing cod_payment_methods mapping, parcel dims/weight from the booking form), 1.x get_order_create_result polling (bounded ~15s in-request backoff → else persist `creating` + batch_no), batch_cancel_order (real JSON array; 13002 → friendly message), batch_search_order (13251 backoff; routes[] → timeline entries), status codes table → mappableStatuses + statusPhase buckets. Address mapping: 5.1 Address API data cached (settings-cache pattern like quadx_status_labels) feeding state/city/district/town resolution from order fields, QuadX-resolver-style.
**Tests:** Http::fake round-trips asserting payload shapes against the documented schemas; async both paths (fast-complete and pending→poll-command-completes); cancel success + 13002; tracking success + 13251-retry; mixed-queue FakeCourier test still green (per-row derivation).
- [ ] TDD, commit `feat(spx): booking, cancellation, tracking with async create`.

---

### Task 3: AWB storage, webhook, F2 fix

**Files:** `SpxFulfillment::printLabel()` — async label batch → download PDF to `storage/app/courier-labels/spx/{tracking}.pdf` (Storage facade, local disk) → serve via a new authenticated extension route (stream response); interface docblock updated: printLabel MAY serve a stored asset (contract note, no signature change). Webhook: SPX branch of the `{provider}` dispatch — verify HMAC per docs (same scheme; reject on mismatch), **return 200 immediately and process via a queued job** (`dispatch()->afterResponse()` acceptable if no queue worker runs on dev — check how the app queues; pick the mechanism that guarantees sub-3s ack on this stack and document it). F2 fix while in the webhook layer: the tracking-number fallback lookup adds a `courier_provider` scope (README caveat updated to "fixed").
**Tests:** label download + storage + second-request-serves-cached; webhook signature pass/fail; ack-fast mechanism asserted (response returned before processing side effects where testable); F2 — two orders with the same tracking number under different providers resolve to the right one.
- [ ] TDD, commit `feat(spx): perishable AWB storage, signed fast-ack webhook, courier-scoped lookups`.

---

### Task 4: Queue + settings UX polish for SPX

**Files:** verify (and only where needed, adjust) the queue drawer for SPX: courier options include SPX when enabled, service/parcel/payment options render from SPX's `get*Options()`, "Creating…" state renders honestly on the stepper/status chip, SPX tracking links render. Settings card: UAT-mode note linking `docs/spx/NSS OpenAPI UAT Order Creation Guide.pdf` (the whitelist), Create-shipper-account action (calls 3.1 when user_id empty, stores encrypted, schema-driven so it appears only for SPX via an `actions` schema extension OR a courier-specific partial — pick the lighter mechanism, document).
**Tests:** queue renders SPX options; creating-state render; settings action round-trip (Http::fake).
- [ ] TDD, commit `feat(spx): queue and settings UX`.

---

### Task 5: Fake-HTTP verification sweep

- [ ] Full suites (Fulfillment ~50 expected, AdminV2 all, baseline 26). `npm run build`, clears. Browser (Playwright, port 8001): settings shows SPX card (enter NO real creds in this task); queue drawer shows SPX in courier options once enabled-with-dummy-creds via the UI (flip back after); paused/creating states; console clean; screenshots. QuadX grep + suite proof of frozenness. Commit fixes or report clean.

---

### Task 6: Live UAT gate (sanctioned real outbound)

**Owner-in-the-loop task — coordinate, don't improvise:**
- [ ] Enter the SPX UAT credentials via the encrypted settings UI (from the coordinator at runtime — NEVER files/commits). Test Connection → expect success.
- [ ] If `spx_user_id` empty: run Create shipper account (3.1) once; record success in the report (values stay in the DB, encrypted, unlogged).
- [ ] Create a dev test order whose shipping address is UAT-whitelist lane #3 (Metro Manila Intramuros Barangay 654 → Metro Manila Ermita Barangay 659 — both-Manila lane, simplest), weight/dims within limits.
- [ ] Book it via the real queue drawer → async create resolves → tracking number appears; poll/track (expect propagation backoff to engage); download AWB (stored PDF opens); cancel the shipment (pre-pickup — expect success); verify the full timeline rendered.
- [ ] Evidence: screenshots at each step + the report. Cleanup: clear that one order's courier_* fields (sanctioned, scoped); leave settings creds in place (owner's call to keep).
- [ ] Commit any fixes surfaced as `fix(spx): uat gate fixes`. Report: what SPX's UAT actually returned vs the docs (deviations recorded in docs/spx/index.md quirks list).

---

## Self-review notes
- Spec coverage: two-tier creds ✅ T1, async create ✅ T2 (+interface accommodation w/ QuadX-safe default), AWB ✅ T3, 3s webhook ✅ T3, F2 ✅ T3, UAT whitelist ✅ T6 (lane #3), README recipe validation ✅ T1 registration + interface-evolution callouts.
- Risks for reviewers: signer byte-exactness (T1 test vector is the gate); interface addition must not break QuadX (default impl + frozen tests); webhook ack mechanism on a no-worker dev stack (T3 documents choice); T6 is the only real-HTTP task and touches the live dev DB only via the UI flows the owner sanctioned.
