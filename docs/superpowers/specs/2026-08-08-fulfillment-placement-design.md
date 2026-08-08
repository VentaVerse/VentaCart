# Fulfillment Placement: core status decides the tab

**Date:** 2026-08-08
**Branch:** `feat/quadx`. Supersedes the earlier draft of this filename. Predecessor: tabs v3 (executed through `ce04305`).

## The owner's architecture (his framing, adopted verbatim as law)

> "The courier status will not touch the fulfillment. The fulfillment page is merely an optional additive, an API integration with a better interface. Imagine there is no fulfillment module: there is only the order page with status updates and the courier API. We book the courier, the courier status updates, and the ecommerce updates the ORDER STATUS by mapping. That is the whole connection between the ecommerce and the courier. So we tell in the settings what order status goes to what tab."

Three things exist, and only three:

1. **Core order status** (CRUD, owner-defined): the real state of the order. The only status that matters to the customer, to stock, to email, to reports.
2. **Courier status** (not ours): raw words from SPX/QuadX. Received, displayed, and mapped. Nothing more.
3. **Tabs**: NOT statuses. Groups where core statuses land. Each group has fixed actions and fixed information.

Two mappings, one direction:

```
courier status --(per-courier map, exists today)--> CORE ORDER STATUS --(placement setting)--> tab
```

**The guardrail for every future decision:** if a behavior would not work from the plain order page with just the courier integration and the courier-to-status map, it does not belong in the fulfillment module. The fulfillment page adds interface, never logic.

## What changes from tabs v3

Tabs v3 made tab membership a blend of order-status stage AND live courier state (`isActivelyBooked`, courier phase, creating, etc.). That blend is the complexity the owner rejected. It goes away:

- **Tab membership reads core order status placement. Nothing else.** No courier-state precedence, no stage-vs-phase arbitration, no per-courier phase overrides (an idea from the superseded draft: dropped).
- The courier's influence on tabs becomes indirect and honest: courier status maps to a core status, and that status's placement decides the tab. Exactly the chain that would exist with no fulfillment module at all.
- `statusPhase()` stays in the courier adapters for what it is genuinely for: the 4-step progress stepper, timeline rendering, cancelable/terminal decisions on a shipment. It no longer decides tab membership.

## Design

### 1. Placement replaces stage

`order_statuses.fulfillment_stage` (unpaid|ready|cancelled) becomes `order_statuses.fulfillment_placement` with one value per tab:

`unpaid | to_pack | to_handover | in_transit | delivered | failed | cancelled`

Seven values, one per visible tab, with To Pack and To Handover being the two sub-tabs under To Ship (To Ship itself is a heading, never a placement).

Edited on the existing Order Statuses page. Conversion: unpaid to unpaid, cancelled to cancelled, ready to to_pack; then name-based refinement in the seeder and a dev-DB one-off: Delivered and Complete to `delivered`; Shipped and In Transit to Manila to `in_transit`; Order Being Shipped, Done Pickup, Start Pickup to `to_handover`. Owner adjusts anything afterward.

This is the fix for the motivating defect: 24,620 OpenCart-era orders whose core status is "Delivered" currently sit in To Pack because the old vocabulary could not express "already delivered". After placement they sit in Delivered, and To Pack shows only genuine work.

### 2. Membership: one rule

An order appears in the tab its core status is placed in. Full stop.

No exceptions, no precedence table, no courier-state arbitration. A booked order sits in To Handover because BOOKING SET ITS STATUS to one placed there, not because the system peeked at courier fields. The To Ship count is simply To Pack plus To Handover.

### 3. Actions: hardcoded per tab (the owner's law)

| Tab | Actions |
|---|---|
| Unpaid | Cancel order |
| To Ship / To Pack | Book, Cancel order |
| To Ship / To Handover | Print waybill, Tracking, Cancel booking (while the courier still allows it) |
| In Transit | Tracking (view) |
| Delivered | Tracking (view) |
| Failed | Tracking (view), Rebook when the order has no live booking |
| Cancelled | none; Book returns only if the owner changes the status to one placed under To Ship |

Rows without courier data simply show no courier actions. Booking is offered and permitted only for `to_pack`-placed orders (server-side too), which also prevents booking a Delivered-placed legacy order.

### 4. Booking sets a core status (required for the chain to work)

Booking is our action; no courier status announces it. Module setting `booked_order_status_id` (fulfillment settings hub, options scoped to `to_handover`-placed statuses, default the "Order Being Shipped" match when placed there, else none). On a successful booking (sync or async resolution) the order's status advances through the shared `OrderStatusChanger` (history entry, stock rules, notify off). If unset, booked orders stay in To Pack and the settings page says so plainly.

Cancel booking symmetrically returns the order to a `to_pack`-placed status: the status it held before booking when the booking history recorded it, else the setting `unbooked_order_status_id`.

### 5. Settings surfaces (no new pages)

- **Order Statuses page** (existing): each status row gains the placement select, labeled by tab name, with the live order count beside it, so regrouping shows its scale before and after.
- **Courier page** (existing): the courier-status to core-status map stays exactly where it is, plus the notify flag. Unmapped courier statuses change nothing; the raw value still displays on the order row.
- **Fulfillment hub** (existing): the two booking-status settings.

Nothing else, and no drag-and-drop invention: the mapping is one dropdown on a page the owner already edits.

## Out of scope
New tabs; per-courier tab overrides; RMA; moving the Book button onto the order page (a natural future step under this architecture, not now); merge to main.

## Verification
Suites green (baseline 23). Browser proof: Delivered tab holds the 24,620 legacy orders, To Pack holds only genuine work; a custom status (e.g. "Repacking") created and placed under To Ship appears there; booking a To Pack order moves it to To Handover via its core status (visible on the order page too); cancel booking returns it; a courier status update maps to a core status and the row moves accordingly. Order 29850 and shipment SPEPH065515515378 stay frozen.
