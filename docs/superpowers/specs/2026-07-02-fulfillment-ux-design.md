# Fulfillment UX & Features — Design Spec

- **Date:** 2026-07-02
- **Status:** Approved for spec review
- **Context:** The QuadX courier integration (POC/MVP) is done and verified against staging — book / fetch / track / poll / ERP-triggered booking all work. This spec covers turning it into a usable admin feature: a settings home, a gated operational page, an admin-friendly booking UX, and automatic core-order-status updates driven by courier status.
- **Scope:** the `courier-fulfillment` extension only. Venta owns courier fulfillment (per [[project_quadx_integration]] — the ERP triggers booking but never talks to couriers). Shopee/Lazada-style consolidated UX is intentionally reserved to the ERP; Venta uses the stepper approach below.

## Goal
Make fulfillment operable by a non-technical admin: enable it, configure the courier API, map courier statuses to Venta order statuses, and book shipments from a scannable page that shows each order's fulfillment progress — with the core Sales order status updating automatically as the courier moves the parcel.

## A. Menu restructure
Today "Fulfillment" sits under **Extensions** and opens the operational list. Split it:

- **Extensions → Fulfillment** → the **settings** page (`admin.fulfillment.settings`). Always visible when the extension is installed (gated menu link, same `$fulfillmentInstalled` pattern).
- **Sales → Order Fulfillment** → the **operational list** (`admin.fulfillment.index`). Visible **only when the Enable toggle is ON**.

Routes already exist; this is re-pointing the Extensions link to settings, adding the Sales link, and gating the Sales link on the enable setting.

## B. Settings page (Extensions → Fulfillment)
Three sections:

1. **Enable Fulfillment** — a master on/off toggle stored as setting `group=courier, key=fulfillment_enabled` (default **off**; admin turns it on after configuring the API). This is the switch that shows/hides the Sales → Order Fulfillment menu.

2. **Courier API (QuadX)** — mode (staging/production), API key, secret. Fields remain visible when already saved but are **masked (`••••••`)**; submitting blank leaves the stored value unchanged (only a non-blank value overwrites — reuse the existing settings allowlist pattern so `webhook_token` etc. can't be clobbered). A **Test Connection** button calls a cheap authed GET (`/orders/statuses`) and reports ✓ connected / ✗ with the error. (Webhook URL block stays, dormant — the future push option.)

3. **Status mapping** — a curated table of QuadX **lifecycle** statuses (for_pickup, picked_up, in_transit, out_for_delivery, delivered, failed_pickup, failed_delivery, returned, canceled). Each row has a dropdown to the Venta core order status it should set **and** a "notify customer" checkbox (see §D). Pre-filled with smart defaults (e.g. `picked_up → Shipped`, `out_for_delivery → Order Being Shipped`, `delivered → Delivered`, `canceled → Canceled`; notify off by default). A "Refresh from QuadX" button re-pulls the live status list (`getStatuses()`) so new QuadX statuses can be added to the map. Stored as JSON setting `group=courier, key=quadx_status_map` — `{ "<quadx_status>": { "order_status_id": <int>, "notify": <bool> } }`. Unmapped statuses → update the courier column only, core Sales status untouched.

## C. Order Fulfillment page (Sales → Order Fulfillment)
Scannable list; each row carries a **compact inline mini-stepper**; clicking a row opens a **detail drawer** with the full stepper and actions.

- **Columns:** Order (#/date) · Customer (name + city) · **Order status** (core, colored badge) · Items (count) · **mini-stepper** · Tracking # · live courier status · Action. **Payment method column removed.** No order-status restriction on booking (unpaid orders can be booked).
- **Stepper phases:** Pending → Booked → Shipped → Delivered, derived (coarse) from `courier_status`. Fork states (`failed_pickup`, `failed_delivery`, `returned`, `canceled`) render the current node red/⚠.
- **Filters:** To Book / In Transit / Delivered / All + search (order #, customer, tracking #).
- **Drawer:** big horizontal stepper; for an **unbooked** order → the Book form (courier · service · parcel · who-pays-shipping) + **Book Shipment** button (the "simply book" goal); for a **booked** order → Print AWB · Refresh status · Cancel booking, plus items, **estimated delivery date**, and a **clickable tracking link** (opens QuadX's tracker).
- **Design risk (validate during build):** the inline mini-stepper widens the row. Keep it a fixed compact width (4 small nodes, no labels inline — labels only in the drawer). If it still crowds at common widths, fall back to a status **pill** on the row and reserve the full stepper for the drawer.

## D. Status-update engine
On every status update (the 10-min `courier:poll-status` poll now; the dormant webhook later), for each affected order:
1. Set `courier_status` (fulfillment column) — as today.
2. Look up the configured `quadx_status_map`. If the new status is mapped, update the core `order_status_id` **and** write an `order_histories` row so the Sales order reflects fulfillment.

**Two independent mappings, kept separate:**
- **Visual phase** (Pending/Booked/Shipped/Delivered + fork) — fixed, in code, via a courier-provided method (`statusPhase(string): array` on `CourierFulfillmentInterface`) so the stepper is courier-agnostic and never depends on admin config.
- **Core status** (QuadX status → Venta order status) — the admin-configured `quadx_status_map` from settings.

**Customer notification:** intermediate status changes write history **silently** (`notify_customer = false`). Terminal-ish transitions (e.g. delivered) may set `notify_customer = true` — configurable per mapped row in the status-mapping table (a "notify customer" checkbox alongside each mapping). Default off.

## Data / schema
No migration needed — all new state is settings rows:
- `courier.fulfillment_enabled` (bool)
- `courier.quadx_status_map` (JSON: status → {order_status_id, notify})
- Existing `courier_*` order columns unchanged.

## Out of scope
- Bulk actions (book many / bulk AWB print) — future (QuadX supports bulk AWB).
- Buyer-facing tracking on the storefront order page/email — separate, already noted as recommended-next.
- Webhook (push) activation — stays dormant; polling is the chosen path.
- ERP-side UX (Shopee/Lazada consolidated view) — reserved to the ERP.
- Multi-courier UI beyond what already exists (QuadX is the only enabled courier).

## Testing / acceptance
- **Menu gating:** enable off → no Sales → Order Fulfillment link; on → link appears. Extensions → Fulfillment always shows settings.
- **Settings:** API saved + masked; blank submit preserves stored secret; Test Connection reports ✓ against staging. Status map saves; "Refresh from QuadX" repopulates the list.
- **List:** rows show correct core status, mini-stepper phase, tracking #, live status; filters + search work; no payment column.
- **Book:** unbooked order books from the drawer (idempotent — no double-book); tracking # + status appear; booking works regardless of order payment status.
- **Status engine:** a QuadX status change (verified live: `picked_up`/`out_for_delivery`/`delivered`) flips `courier_status`, advances the stepper, and — when mapped — updates the core order status + adds history; unmapped leaves core untouched; notify fires only where configured.
