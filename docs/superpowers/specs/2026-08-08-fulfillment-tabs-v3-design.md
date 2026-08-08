# Fulfillment Tabs v3: Unpaid-first, To Ship with sub-tabs, real order cancel

**Date:** 2026-08-08 (owner directives, verbatim intent)
**Branch:** `feat/quadx`. Predecessor: workflow v2 spec (executed). ERP precedent: Shopee/Lazada/TikTok all use Unpaid, then To Ship with To Pack / To Handover sub-steps.

## Owner directives

1. Cancel order (the real cancel) must also land in the Cancelled tab. ALL orders appear on the fulfillment page.
2. To Book rows get a Cancel button that cancels the ORDER (the real cancel), distinct from Cancel booking.
3. Rename To Book to **To Ship**, with two SUB-TABS: **To Pack** (where fulfillment starts) and **To Handover** (moved down from top level).
4. Rename not just labels: URLs and code variables too.
5. New **Unpaid** tab, FIRST of all tabs. Unpaid orders have exactly ONE action: Cancel (the real cancel). Rationale: gateways auto-advance paid orders to To Ship; manual/reseller orders sit Unpaid until the owner manually sets the core status to Processing, which moves them to To Ship.
6. (From the same conversation) Cancel booking returns the order to To Ship > To Pack, rebookable; booking history preserved in courier meta. Courier-side cancellations the admin did NOT initiate surface in Cancelled as exceptions.
7. Booking top-level tab is dropped (owner found it meaningless): `creating` orders render inside To Ship > To Handover with the Creating badge.

## Design

### Order-status stages drive the order-level tabs

New column `order_statuses.fulfillment_stage` enum `unpaid | ready | cancelled`, default `ready`, editable on the existing Order Statuses admin page (same pattern as stock_action). Seeder defaults by name; existing dev DB set by a one-off (scoped UPDATE of the new column only):
- `unpaid`: Unpaid, Pending, Quote Ready for Payment
- `cancelled`: Canceled, Canceled Reversal, Denied, Expired, Failed, Refunded, Reversed, Voided, Chargeback
- everything else: `ready`

### Tab model (order-status stage first, then courier phase)

| Tab | Membership | Actions |
|---|---|---|
| **Unpaid** (first) | stage = unpaid | Cancel order ONLY |
| **To Ship** | stage = ready, courier phase in {to_pack, creating, to_handover} | sub-tab dependent |
| To Ship > **To Pack** (default sub) | ready + unbooked (phase to_pack) | Book, Cancel order |
| To Ship > **To Handover** | ready + booked awaiting collection (to_handover) or creating (with Creating badge) | Print waybill, Tracking, Cancel booking |
| **In Transit** | ready + in_transit | Tracking, Cancel booking (while cancelable) |
| **Delivered** | ready + delivered | Tracking |
| **Failed** | ready + failed (incl. creation_failed) | Rebook, Tracking, error shown |
| **Cancelled** | stage = cancelled (real order cancels) OR courier-cancelled shipments the admin did not initiate (exceptions, e.g. webhook 7001) | order-cancelled: view only; courier-cancelled exception: Rebook, Tracking |
| Returns (aside link) | unchanged | unchanged |

Stage precedence: unpaid and cancelled stages claim the order regardless of courier state; ready orders bucket by courier phase.

### Renames (labels, URLs, variables)

- Spine bucket `to_book` renames to `to_pack` everywhere: PhaseTone, FulfillmentService, controller, views, adapters' statusPhase, tests. Tone rules keep: success spent only on to_pack; unpaid = warning (waiting on someone the seller CAN chase, per the ERP tone philosophy).
- URL filters: `?filter=unpaid | to_ship | in_transit | delivered | failed | cancelled` with `?sub=to_pack|to_handover` under to_ship (default to_pack). Legacy `?filter=to_book` and `?filter=to_handover` redirect/alias to the new equivalents (301 per house convention). Route names unchanged.
- Top-level Booking tab removed; `booking` phase value retained internally for creating orders (renders inside To Handover sub-tab).

### Cancel semantics

- **Cancel order** (new action, Unpaid + To Pack rows): confirm dialog, sets the core order status to the module setting `cancel_order_status_id` (default: the status named Canceled; configurable on the fulfillment settings hub), through the SAME status-change path the order page uses (history entry + StockService adjustment + optional notify). If the order has an active booking, refuse with a clear message (cancel the booking first).
- **Cancel booking** (existing): after courier confirms, courier fields reset (order returns to To Pack), previous booking appended to `courier_meta.booking_history` (tracking no, courier, timeline snapshot, cancelled_at). Admin-initiated flag recorded so this shipment does NOT surface in Cancelled.
- **Courier-side cancel** (webhook/poll delivers a cancelled-phase status with no admin-initiated flag): stays on the order, surfaces in Cancelled as an exception with Rebook.

### Out of scope
Order-page cancel UX changes; refund flows; RMA (card #52); merging to main.

## Verification
Suites green (new baseline 23); QuadX payload frozen; browser walk of every tab/sub-tab against the dev DB; legacy filter URLs redirect; Unpaid rows show exactly one action.
