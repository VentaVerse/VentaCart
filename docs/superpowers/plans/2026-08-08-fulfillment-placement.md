# Fulfillment Placement Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development. Checkbox steps.

**Goal:** Execute `docs/superpowers/specs/2026-08-08-fulfillment-placement-design.md`: tab membership reads core order status placement and nothing else; courier influences tabs only by mapping its status to a core status; booking sets a core status; actions stay hardcoded per tab.

**Architecture note for every task:** this SIMPLIFIES tabs v3. Code that blends courier state into membership is removed, not extended. `statusPhase()` remains for stepper/timeline/cancelable decisions only.

## Global Constraints
- Branch `feat/quadx`; nothing merges to main. Baseline: 23 pre-existing failures (full suite 520 passed at plan start).
- NO EM DASHES anywhere. Reskin protocol binds views. Http::fake in tests. QuadX payload/client frozen.
- No destructive live-DB queries. Sanctioned one-offs (run, never committed): placement backfill on `order_statuses` (that column only).
- ORDER 29850 + shipment SPEPH065515515378 FROZEN (owner's SPX evidence). Any live interaction uses a fresh throwaway order.
- Commit per task, author Paolo, no Co-Authored-By. Port 8001, dev@venta.test / venta_dev_2026, prefix /opensesame. Admin login is by USERNAME.

### Task 1: placement column, CRUD, defaults
Migration converts `fulfillment_stage` to `fulfillment_placement` enum(unpaid,to_pack,to_handover,in_transit,delivered,failed,cancelled) default to_pack, mapping unpaid to unpaid, cancelled to cancelled, ready to to_pack. Portable across MySQL (dev) and sqlite (tests): add-new-column, UPDATE-convert, drop-old approach is acceptable and its rationale documented in the migration docblock (converting our own column added on this same branch, not user-data cleanup). Order Statuses page: select with the six tab-named options + live order count per status; controller validation; help text (one line per placement, no em dashes). Seeder name defaults per spec. Dev-DB one-off after migrating: apply name refinement, report the 28-row mapping. Rename every `fulfillment_stage` reference in code and tests (grep zero outside migrations/docs).
- [ ] TDD, commit `feat(fulfillment): status placement mapping`.

### Task 2: membership by placement only (the simplification)
`FulfillmentService`: replace `tabBucketForParts()`/`tabBucket()` stage-plus-courier logic with a placement lookup. `FulfillmentController::phaseTally()` becomes one GROUP BY over placement (join order_statuses, no courier-state arms). To Ship is a heading whose count is to_pack plus to_handover, rendering the existing sub-bar. Remove courier-phase membership, the cancelled-arm union, the `booking`-collapse, and the ce04305 stage-gated action matrix; replace with the spec's fixed per-tab action table (courier actions render only when the order carries courier data). Booking/estimate gate: placement to_pack only, keeping the idempotent already-booked ordering from tabs v3 F3. Returns page: orders whose live courier state is returning or failed, independent of placement, documented as a monitoring surface.
**Interface note:** `statusPhase()` stays for stepper/timeline/cancelable; it must no longer be consulted for membership (grep-prove).
- [ ] TDD, commit `feat(fulfillment): membership by core status placement`.

### Task 3: booking sets status, cancel returns it
Hub settings `booked_order_status_id` (options scoped to to_handover-placed statuses, default the "Order Being Shipped" match if placed there, else none, with a plain note when unset) and `unbooked_order_status_id` (to_pack-placed, default first). Successful booking (sync and async resolution) applies the booked status via `OrderStatusChanger` (notify off, no double-apply when already there). Cancel booking applies the unbooked status after the courier confirms, alongside the existing reset-and-archive behavior from tabs v3. Tests: sync echo, async echo, unset no-op, no duplicate history on repeat polls, cancel returns the order to To Pack by status.
- [ ] TDD, commit `feat(fulfillment): booking and cancellation set the core status`.

### Task 4: sweep
Suites (baseline 23), build + clears, grep gates (`fulfillment_stage` zero outside migrations/docs; no membership call to `statusPhase`). Browser walk (ONE page, sequential; NEVER touch order 29850): Delivered tab holds the 24,620 legacy orders and To Pack only genuine work (record counts before/after); create a throwaway "Repacking" status placed under To Ship, verify a throwaway order appears there, then delete both; book that throwaway order and watch its core status change on the order page and its row move to To Handover; cancel the booking and watch it return; Status map page unchanged; light and dark; console clean. Screenshots + report.
- [ ] Commit fixes or report clean.

## Self-review notes
- The owner's law traces directly: actions hardcoded (Task 2 table), membership editable (Task 1 select), courier touches tabs only through the status map (Task 2 removes every other path).
- Risks: enum conversion portability across MySQL and sqlite (Task 1); removing courier-state membership will delete or rewrite several tabs-v3 tests, which is correct and must be done deliberately, never by weakening assertions; the booked-status setting is load-bearing (unset means booked orders visibly stay in To Pack, so the settings note and the sweep must both cover it).
