# Fulfillment Placement Mapping: editable status-to-tab, hardcoded actions

**Date:** 2026-08-08 (owner design, his words define the law)
**Branch:** `feat/quadx`. Predecessor: tabs v3 (executed, incl. final fix wave `ce04305`).

## The owner's law

> "What is hardcoded: the actions for each tab. Unpaid: Cancel action. To Pack: book action. To Handover and everything else, no more action. A cancelled tab will also have no action, not unless the order status changes to where To Pack is mapped. What is editable: what order status goes to what tabs."

The cancelled-stage mapping from tabs v3 was the prototype of this mechanism; this plan generalizes it.

## Motivating defect

24,620 orders with core status "Delivered" (OpenCart-era, no courier data) sit in To Pack, because the 3-way stage vocabulary cannot express "already delivered" and the ready+unbooked→To Pack rule is code. The mapping must become expressive enough that no core status is ever forced into the wrong tab.

## Design

### 1. `fulfillment_stage` becomes `fulfillment_placement`

Enum: `unpaid | to_ship | in_transit | delivered | failed | cancelled` (the tab list). Migration converts the column (unpaid→unpaid, cancelled→cancelled, ready→to_ship) schema-side; name-based defaults refine the obvious cases in the seeder and the dev-DB one-off: Delivered, Complete → `delivered`; Shipped, Order Being Shipped → `in_transit`; everything else ready → `to_ship`. Owner adjusts freely afterward on the Order Statuses page (the existing dropdown, new options).

### 2. Membership rules (generalizing tabs v3's ruled precedence)

- **Unbooked order (no courier state): placement decides the tab entirely.** A custom "Repacking" status placed to_ship sits in To Pack; a Delivered-placed order sits in Delivered; and so on. To Ship placement buckets to the To Pack sub-tab.
- **Booked order: courier phase decides** (physical reality wins), translated per adapter as today.
- **Cancelled placement stays absolute** (tabs v3 owner ruling), with the ce04305 courier-state action matrix intact.
- Returns page unchanged (courier returning/failed phases, non-cancelled placements).

### 3. Actions: hardcoded per tab, exactly the owner's statement

- Unpaid: Cancel order only.
- To Pack: Book + Cancel order.
- To Handover / In Transit / Delivered / Failed: courier-state actions only where courier state exists (Tracking, Cancel booking while cancelable, Print waybill per tabs v3); a PLACEMENT-driven row with no courier state in these tabs is view-only (no actions: "no more action").
- Cancelled: per ce04305 matrix (view-only unless courier state).
- Booking gate: only `to_ship`-placed orders are bookable (server-side; generalizes the unpaid/cancelled gate; also prevents booking a Delivered-placed legacy order).

### 4. Per-courier Status Flow page (the courier-side mapping in one place)

New settings page per courier (`settings/modules/courier-fulfillment/{courier}/status-flow` or a section on the courier page; implementer picks lighter, documents): one grid, one row per raw courier status:
- **→ Venta order status** (the existing `{courier}_status_map`, editable here; the old mapping table location redirects/moves here).
- **→ Tab** (NEW: per-status tab override, default = adapter's `statusPhase()` bucket, stored as `{courier}_phase_overrides`; settings-as-fallback pattern: untouched rows read "Default (In Transit)" style, changed rows get a custom badge + reset-to-default).
- **Notify customer** (existing flag).
Unknown raw statuses (arriving via refresh/webhook that the adapter doesn't list) auto-appear in the grid routed to the adapter's fallback bucket, re-homeable without code.
`statusPhase()` resolution order: override setting → adapter default.

### 5. "Status after booking" echo

Optional module setting `booked_order_status_id` on the hub (select over to_ship/in_transit-placed statuses, default none): when a booking succeeds (sync or async resolution), the core status advances through OrderStatusChanger (history + stock, notify off). Completes the forward chain: book → core echoes → courier events → map → core → placement agrees.

## Out of scope
New tabs; per-store action changes; RMA; merge to main.

## Verification
Suites (baseline 23); browser walk proving the 24,620 Delivered orders land in the Delivered tab and To Pack shows only genuine work; custom-status round-trip (create status, place it, order appears there); booking gate on delivered-placed order; override + reset round-trip; unknown-status auto-listing; order 29850 + SPEPH065515515378 untouched.
