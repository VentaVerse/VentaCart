# Fulfillment Tabs v3 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development. Checkbox (`- [ ]`) steps.

**Goal:** Execute `docs/superpowers/specs/2026-08-08-fulfillment-tabs-v3-design.md`: Unpaid-first tabs driven by order-status stages, To Ship with To Pack/To Handover sub-tabs, to_book renamed to to_pack end to end, real order-cancel action, cancel-booking returns to To Pack, Cancelled = real cancels + courier exceptions.

## Global Constraints
- Branch `feat/quadx`; nothing merges to main. Suite baseline: 23 pre-existing failures; every fulfillment/AdminV2 suite green per task.
- QuadX booking payload/client frozen (statusPhase bucket RENAME to_book→to_pack is sanctioned; raw maps/labels byte-identical).
- NO EM DASHES in any string. Reskin protocol binds views. Http::fake in tests. No credentials. No destructive live-DB queries (sanctioned exceptions: one-off stage backfill on order_statuses setting ONLY the new column; nothing else).
- Commit per task, author Paolo, no Co-Authored-By. Port 8001, dev@venta.test / venta_dev_2026, prefix /opensesame.

### Task 1: fulfillment_stage on order statuses
Migration `order_statuses.fulfillment_stage` enum unpaid|ready|cancelled default ready (schema only). Order Statuses admin page gains the selector (design system, same row pattern as stock_action; help text explains the three stages). Seeder defaults by the spec's name lists. One-off tinker on dev DB (run, not committed): set stages by the spec's name lists, report the resulting mapping. Tests: column default; admin save round-trip; seeder stages.
- [ ] TDD, commit `feat(fulfillment): order-status fulfillment stages`.

### Task 2: spine rename + stage-aware tally
Rename spine bucket to_book→to_pack across PhaseTone, FulfillmentService (incl. ASYNC_CREATE handling), FulfillmentController, adapters (QuadX + SPX statusPhase), views, tests. Tally/membership becomes stage-first per the spec's table: unpaid stage → unpaid tab; cancelled stage → cancelled tab; ready stage → courier phase buckets (creating counts under to_handover for tab purposes; keep the `booking` internal phase for badges). Add `unpaid` tone = warning in PhaseTone. Tests: stage precedence (unpaid order with active booking still shows Unpaid? NO: spec says unpaid/cancelled stages claim the order regardless of courier state; assert that), phase renames total, QuadX raw maps byte-identical.
- [ ] TDD, commit `feat(fulfillment): stage-first tabs and to_pack rename`.

### Task 3: queue UI: Unpaid first, To Ship sub-tabs, URL filters
Segment bar: Unpaid | To Ship | In Transit | Delivered | Failed | Cancelled (+Returns aside). To Ship renders sub-segment (ERP x-segment--sub pattern, no trough) To Pack | To Handover with counts; default sub to_pack. URL: ?filter values per spec + ?sub= under to_ship; legacy filter=to_book → redirect to filter=to_ship&sub=to_pack, filter=to_handover → filter=to_ship&sub=to_handover, filter=booking → filter=to_ship&sub=to_handover (301 query-aware redirect in the controller). Handover courier chips move under the To Handover sub-tab. Creating rows render in To Handover sub with Creating badge. Tests: every tab/sub renders with correct membership counts from seeded orders across stages and phases; legacy URL redirects; default sub.
- [ ] TDD, commit `feat(fulfillment): unpaid-first tabs with to-ship sub-tabs`.

### Task 4: cancel semantics
(a) Cancel order action: POST route (admin.fulfillment.cancel-order), confirm dialog on Unpaid (ONLY action there) and To Pack rows; sets core status to module setting `cancel_order_status_id` (hub settings field, default = status named Canceled; validated against order_statuses) via the same path the order page uses (history + StockService + optional notify flag off by default); refuses with clear message when an active booking exists. (b) Cancel booking: on courier-confirmed cancel, append booking snapshot to courier_meta.booking_history, reset courier fields (order returns to To Pack), record admin_cancelled flag. (c) Courier-side cancels without the flag surface in Cancelled with Rebook; admin-initiated do not (they are back in To Pack). Tests: unpaid row renders exactly one action; cancel-order round-trip moves status + stock + history and lands the order in Cancelled; refusal with active booking; cancel-booking resets to To Pack with history appended; webhook 7001 without flag lands in Cancelled with Rebook. QuadX cancel flow behavior preserved (its own tests).
- [ ] TDD, commit `feat(fulfillment): real order cancel and booking-cancel reset`.

### Task 5: sweep
Full suites (baseline 23), npm run build + clears, browser walk (ONE page, sequential): every tab/sub against the 27k-order dev DB with counts and load time, unpaid single-action check, cancel-order dialog (do NOT confirm on any real order: use the UAT test order 29850 if needed), legacy URL redirects, light+dark, console clean, screenshots to the plan workspace. grep: zero `to_book` residue outside migrations/history docs.
- [ ] Commit fixes or report clean.

## Self-review notes
- Directive coverage: 1→T2/T4c, 2→T4a, 3→T3, 4→T2/T3 (URLs+variables), 5→T1/T2/T3 (unpaid first, single action), 6→T4b, 7→T3 (booking tab dropped).
- Risks: stage-first precedence vs existing phase tests (T2 rewrites them deliberately); legacy URL redirects must not break W2 tests (they get updated: they are not frozen); QuadX freeze is payload/client only, statusPhase rename sanctioned.
