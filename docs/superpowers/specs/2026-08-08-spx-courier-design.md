# SPX (Shopee Xpress) Courier Extension — Design Spec

**Date:** 2026-08-08
**Branch:** `feat/quadx` (the fulfillment foundation lives here; nothing merges to main)
**Ground truth:** `docs/spx/` — harvested SPX Open API docs (guide.md, api.md, integration-brief.md) + `NSS OpenAPI UAT Order Creation Guide.pdf` (UAT address whitelist). UAT app credentials provided by Paolo (held outside the repo; entered via the encrypted courier settings UI only).

## Why

SPX has provided UAT docs and credentials — it's the first courier to land on the polished courier-agnostic foundation, proving the "one folder + one register line" promise. QuadX awaits prod keys; LBC awaits our permission request.

## What SPX changes about our assumptions (from the docs)

1. **Two-tier credentials:** app (`app_id`+`secret` → HMAC-SHA256 `check-sign` header over `{app_id}_{timestamp}_{random_num}_{payload}`, exact-byte payload discipline) plus a **shipper account** (`user_id`+`user_secret` in every business request body), created once via the 3.1 Create Account API or provided by SPX.
2. **Create is asynchronous:** `batch_create_order` → `batch_no` → poll `get_order_create_result` until `task_status=4`; success is read from `fail_list`/`fail_count`, never task status.
3. **AWB links are perishable** (30 minutes / 10 opens): the PDF must be downloaded and stored server-side at fetch time.
4. **Webhooks demand a 200 within 3 seconds** or SPX permanently stops sending; callbacks are HMAC-signed with the same scheme.
5. **Tracking lags creation** 1–3s (error 13251) — retry with backoff.
6. **UAT is address-whitelisted:** only the 10 lanes in the NSS PDF route in UAT.

## Design

### Adapter (`extensions/courier-fulfillment/src/Couriers/Spx/`)
- `SpxClient` — signed HTTP client: builds the exact payload string, signs it, sends it byte-identical; base URLs per mode (UAT/production, from the docs); response envelope (`ret_code`/`message`/`data`) unwrapping with error-code mapping (13002 cancel-too-late, 13251 tracking-lag, etc. per the docs' error table). Unit-tested against the verified HMAC test vector in `integration-brief.md`.
- `SpxFulfillment implements CourierFulfillmentInterface` — key `spx`; registered with one line in the provider (validating the README recipe).
- **Async create handled honestly:** `createShipment()` submits the batch, then polls with backoff within a bounded budget (~15s). If completed → tracking number as normal. If still pending → persist `batch_no` in `courier_meta` with courier_status `creating`; `PollCourierStatus` gains a generic "resolve pending creations" pass (interface-level: a nullable `resolvePendingCreation(Order): ?string` method, no-op default for QuadX) that completes it on the next run. The queue shows "Creating…" honestly.
- **AWB:** `printLabel()` fetches via the async label batch flow, downloads the PDF to `storage/app/courier-labels/spx/{tracking}.pdf` on first request, then serves the stored file (new authenticated route within the extension). Interface contract note added: printLabel may serve a stored asset rather than redirect.
- **Tracking:** `batch_search_order`; `routes[]` → the existing timeline; 13251 retried with backoff.
- **Cancel:** `batch_cancel_order` with a real JSON array; pre-pickup only — 13002 surfaced as a friendly "already picked up" message; `cancelableStatuses()` derived from the docs' status codes.
- **Statuses:** `mappableStatuses()` from the docs' Order-status-code table; admin maps them to Venta statuses via the existing per-courier mapping UI; `statusPhase()` buckets them onto the 4-step stepper.
- **COD & addresses:** `cod_collection`/`cod_amount` driven by the existing `cod_payment_methods` mapping; address fields mapped from order shipping fields to SPX's state/city/district/town hierarchy via the 5.1 Address API data (cached), mirroring the QuadX resolver pattern.

### Settings (schema-driven — appears automatically)
`spx_enabled`, `spx_mode` (uat|production), `spx_app_id`, `spx_secret_key` (encrypted), `spx_user_id`, `spx_user_secret` (encrypted), defaults as needed. Card actions: **Test Connection** (cheap authenticated call) and **Create shipper account** (calls 3.1 once when user_id is empty, stores results encrypted). UAT-mode note on the card links to the whitelist PDF.

### Webhook (+ the deferred F2 fix)
SPX callback route under the existing `{provider}` webhook dispatch: verify the HMAC signature, **respond 200 immediately, process via a queued job** (the 3-second rule). While in there, fix the deferred F2: the tracking-number fallback lookup becomes courier-scoped (`courier_provider` filter) — protecting QuadX and SPX from each other's tracking-number collisions.

### Verification strategy
- All feature tests `Http::fake` (payload-shape assertions per the documented schemas, async-create both paths, label storage, webhook signature+queue, cancel errors).
- **Live UAT round-trip as the final gate** (sanctioned real outbound to SPX UAT only): creds entered via the encrypted settings UI (never committed), one booking on a whitelisted lane → poll result → tracking → AWB download → cancel. Evidence screenshots. Dev DB order used for the test gets its courier fields cleared afterward (additive columns only — no destructive ops beyond resetting the test order's own courier_* fields).

## Definition of done
- SPX card appears on the settings page, queue offers SPX, README recipe held (folder + register line — any interface change is called out as foundation evolution, not recipe violation).
- Signer proven against the doc test vector; async create, perishable AWB, 3s webhook ack all handled as designed; F2 fixed.
- Full suites green (Fulfillment/AdminV2/baseline 26); live UAT round-trip completed with evidence.
- QuadX behavior untouched (grep + suites).

## Out of scope
Production SPX cutover (needs prod creds + real lanes); LBC; shipping-rate quoting via SPX's Shipping Fee API at checkout (fulfillment-side only for now — card it separately if wanted); merging to main.
