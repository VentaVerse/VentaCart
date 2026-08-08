# Fulfillment Workflow v2 — Design Spec

**Date:** 2026-08-08
**Branch:** `feat/quadx` (nothing merges to main)
**Predecessor:** `2026-08-08-fulfillment-polish-design.md` (executed). Companion: `2026-08-08-spx-courier-design.md` (SPX builds ON TOP of this workflow).
**Ground truth for the interface:** the ERP's fulfilment surface at `/home/ubuntu/ventasync/preyes-erp` (branch ventasync-v2.1) — audited 2026-08-08. Venta copies its grammar so both platforms read identically to the same operator.

## Why (owner's directives)

1. **Unified look with VentaSync** — the ERP's fulfilment hub/panels are the reference interface; Lazada/Shopee/TikTok statuses prove a common courier status spine exists. Returns get a different page and process.
2. **Per-courier settings pages** — "Forcing them into 1 page tells us that we are forcing our settings to them. We are the ones who should adjust." Different couriers, different settings, endpoints, workflows.
3. **Courier-first workflow** — step one of every booking is the operator choosing the courier; the workflow then takes the shape of that courier (QuadX: pouch + who-pays; SPX: pouch presets + who-pays + pickup slot + fee estimate).

## Part 1 — The common status spine

Cross-checking Shopee (`UNPAID…READY_TO_SHIP…PROCESSED…SHIPPED…COMPLETED`), Lazada (`pending…packed…ready_to_ship…shipped…delivered…confirmed`), and TikTok (`AWAITING_SHIPMENT…AWAITING_COLLECTION…IN_TRANSIT…DELIVERED…COMPLETED`) confirms the owner's hypothesis: every courier shares one physical journey. Venta's phase model becomes:

| Phase bucket | Meaning | Tone |
|---|---|---|
| `to_book` | Seller's move — book the shipment | **success/accent** (the ONLY accent) |
| `booking` | Create in flight (async couriers) | info |
| `to_handover` | Booked; parcel awaiting courier collection/drop-off | info |
| `in_transit` | Courier has it | info |
| `delivered` | At the buyer | neutral (settled ≠ green) |
| `failed` | Delivery failed / lost / damaged | danger |
| `cancelled` | Booking cancelled | danger |
| `returning` | Coming back to the seller | warning (chaseable) |

- Tone philosophy adopted verbatim from the ERP's `ChannelStatusTone`: **accent is spent on exactly one thing — the operator's move right now.** Warning = waiting on someone you CAN chase; info = someone you can't; neutral = settled; danger = ended badly.
- `CourierFulfillmentInterface::statusPhase()` re-buckets onto this 8-phase spine (extends today's 4-step). QuadX's raw statuses re-mapped accordingly (behavior of the raw map/labels frozen — only the phase bucketing extends). The 4-step happy-path stepper in the drawer stays (book → handover → transit → delivered); `failed`/`cancelled`/`returning` render as terminal badge states on it, not steps.
- Raw courier statuses remain first-class: stored verbatim, displayed via `{provider}_status_labels`, admin-mapped to Venta order statuses via the existing per-courier mapping UI. The spine is a DISPLAY/QUEUE grouping layer, not a data migration.

## Part 2 — The queue on the ERP grammar

`admin/sales/fulfillment` re-shells onto the ERP panel skeleton, expressed in Venta's v-admin tokens (same family — the look converges by construction):

1. **Command bar** (`x-cmdbar` equivalent): "Poll courier statuses" action + hint; all controls on one 32px baseline.
2. **Segment bar**: tabs = the spine phases that have members — To Book | Booking | To Handover | In Transit | Delivered | Failed | Cancelled — each with a mono count; far-right **aside link → Returns** (chevron; deliberately NOT a tab, per the ERP rule).
3. **"Parcels waiting for handover"** courier tallies (per-courier count chips) above the table when the To Handover tab has members — the ERP's `co-couriers` pattern.
4. **Filters row**: search order no / customer, per-page; autosubmit selects.
5. **Table** in the two-tier `co-band`/`co-itemrow` idiom: a band row per order (customer / order no link / item count / created), item rows beneath with thumbs+SKU, order-level cells (total, courier, status, actions) rowspanned. Status cell = badge (toned by spine) + raw courier status label.
6. **Actions all visible buttons** — Book / Print waybill / Tracking / Cancel per state; no overflow menus ("this is the packing desk's working surface").
7. **Pager**: "1 to N of M" mono left, Prev/Next right.

### The courier-first booking flow (two-step modal)

Booking happens in a **modal** (owner's call, 2026-08-08 — the ERP's arrange-shipment `co-modal` pattern, not a drawer). The Book/Rebook row and detail actions open it, titled with the order number.

- **Step 1 — "How will this order ship?"**: one large choice card per **enabled** courier (name, mode chip if UAT/staging, fee estimate placeholder). Only step 1 is universal.
- **Step 2 — the courier's own form**, rendered from the courier's option methods:
  - **QuadX**: service type, pouch (native catalog), who-pays, COD auto-derived — exactly today's fields, restyled.
  - **SPX**: pouch preset (see below) or custom dims, who-pays (`payment_role` sender/receiver), COD auto-derived (`cod_collection`/`cod_amount`), pickup time slot (from SPX's pickup-time endpoint), and an **estimated shipping fee** (SPX `batch_check_order`) shown before the operator commits.
  - Back (ghost) + "Book shipment" (primary, disabled until valid) — the Shopee arrange-shipment modal pattern.
- The existing drawer remains the **viewing** surface (stepper, timeline, tracking, cancel); booking moves out of it into the modal.
- **Pouches are a first-class generic concept**: `getParcelOptions()` already serves them. For couriers with no native pouch catalog (SPX), Venta defines **pouch presets** (label + L×W×H + max weight), configurable on that courier's settings page, seeded with sensible defaults (Small/Medium/Large). Choosing a pouch **overrides** product-computed dimensions — the owner's explicit requirement ("not limited or restricted to the dimension of the product").
- Post-booking, orders show the **actual** shipping fee where the courier exposes it (SPX `batch_get_asf`) — estimated vs actual, honestly labeled.

### Interface evolutions (sanctioned list — each with a QuadX-safe default)

1. `resolvePendingCreation(Order): ?string` — default null (from the SPX spec).
2. `printLabel()` stored-asset contract note (from the SPX spec).
3. `statusPhase()` re-bucketed onto the 8-phase spine (QuadX updated in its own adapter).
4. `estimateFee(Order, array $options): ?Money` — default null ("not supported" → step 2 simply shows no estimate).
5. `getPickupSlots(Order): array` — default `[]` (no slot picker rendered).

## Part 3 — Returns: separate page, separate process

- New page `admin/sales/fulfillment/returns` (linked ONLY from the segment-bar aside + breadcrumb), listing orders whose courier status maps to `returning`/`failed` phases: order, courier, raw status, last timeline event, link to the order.
- The ERP precedent: returns have their own status vocabulary and never sit as an eighth tab. For Venta v2 this page is a **monitoring surface** (what's coming back, what failed). The full RMA process (customer-initiated returns, refund workflow, restock guidance) is a distinct product feature → **roadmap card, out of scope here**.

## Part 4 — Per-courier settings pages

- `settings/modules/courier-fulfillment` becomes a **hub**: module-level settings only (fulfillment enabled, COD payment-method mapping, webhook token) + one card per registered courier (name, enabled state, mode chip, credential-completeness hint) linking to…
- `settings/modules/courier-fulfillment/{courier}` — that courier's OWN page, rendered from its `settingsSchema()` plus its courier-specific sections/actions (QuadX: modes + creds + Test Connection + Refresh statuses; SPX: mode + two-tier creds + Create shipper account + pouch presets editor + UAT whitelist note). Status-mapping UI moves onto the courier's page too — it's per-courier by nature.
- Same split precedent as shipping settings (`settings/shipping/{module}`). Old combined-page anchors 301 to the hub. Settings keys/values FROZEN — this is a page split, not a data change.

## Verification

- All existing suites green (Fulfillment, AdminV2 209, baseline 26); QuadX raw behavior frozen (grep + suites).
- Browser walk: queue tabs with counts, courier-first booking modal both steps (QuadX form intact), viewing drawer, returns page, settings hub → per-courier pages, breadcrumbs, light+dark.
- SPX spec's live UAT gate then exercises the whole v2 flow end-to-end (courier choice → pouch preset → fee estimate → book → handover → cancel).

## Out of scope

Full RMA/returns process (card it); checkout-side rate quoting; ERP-side changes; LBC; merging to main.
