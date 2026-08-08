# Fulfillment Placement Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development. Checkbox steps.

**Goal:** Execute `docs/superpowers/specs/2026-08-08-fulfillment-placement-design.md`: editable status→tab placement (hardcoded actions), per-courier Status Flow page with tab overrides, booking echo.

## Global Constraints
- Branch `feat/quadx`; nothing merges to main. Baseline: 23 pre-existing failures (full suite 520 passed at start).
- NO EM DASHES anywhere. Reskin protocol. Http::fake. No credentials. QuadX payload/client frozen.
- No destructive live-DB queries. Sanctioned one-offs (run, never committed): placement backfill on order_statuses (single column) per the spec's name defaults.
- ORDER 29850 + shipment SPEPH065515515378 FROZEN (owner's SPX evidence): never modify; throwaway orders for any live interaction.
- Commit per task, author Paolo, no Co-Authored-By. Port 8001, dev@venta.test / venta_dev_2026, prefix /opensesame.

### Task 1: placement column + CRUD + defaults
Migration: `fulfillment_stage` → `fulfillment_placement` enum(unpaid,to_ship,in_transit,delivered,failed,cancelled); schema conversion unpaid→unpaid, cancelled→cancelled, ready→to_ship (MySQL enum change + rename: verify sqlite test compat; two-step migration if needed). Order Statuses page dropdown gains the six options (labels = tab names); controller validation updated. Seeder name-defaults per spec (Delivered/Complete→delivered; Shipped/Order Being Shipped→in_transit). Dev-DB one-off: apply the same name refinement (report resulting placement per status). All fulfillment_stage code references renamed to fulfillment_placement (grep-total, incl. tests).
- [ ] TDD, commit `feat(fulfillment): status placement mapping`.

### Task 2: membership + gates + action matrix on placement
FulfillmentService::tabBucketForParts()/phaseTally(): unbooked → placement verbatim; booked → courier phase; cancelled placement absolute (keep ce04305 courier-state matrix). Booking/estimate gate: only to_ship placement (message: "Set its status to one placed under To Ship before booking."), keep F3 idempotent ordering. View action matrix per the spec's owner law: placement-driven rows without courier state in In Transit/Delivered/Failed are view-only. Returns page placement-aware (non-cancelled placements). Tests: placement matrix (each of 6 placements x booked/unbooked), 24k-Delivered scenario (seeded), custom-status round-trip, gate refusals, ERP already_booked intact, ce04305 matrix tests still green (update mechanically where stage→placement renamed).
- [ ] TDD, commit `feat(fulfillment): placement-driven membership and gates`.

### Task 3: Status Flow page + tab overrides
Per-courier Status Flow grid (location per spec, implementer picks + documents): rows = adapter mappableStatuses + any unknown statuses seen (from stored courier_status values + status-labels cache); columns = Venta-status map (moved here; old location redirects or links), NEW tab override select (options = 6 tabs + returning; default = adapter statusPhase; stored `{courier}_phase_overrides` map; custom badge + reset per row; settings-as-fallback), notify flag. statusPhase resolution: override → adapter default (implement in the shared layer so tally/rows/returns all honor it; adapters untouched). Unknown statuses route to adapter fallback bucket until re-homed. Tests: override round-trip + reset; unknown status appears + re-homes; tally honors override; QuadX/SPX adapters untouched (diff scope).
- [ ] TDD, commit `feat(fulfillment): per-courier status flow page with tab overrides`.

### Task 4: booking echo
Hub setting `booked_order_status_id` (select scoped to to_ship/in_transit placements + "None (default)"); on successful booking (sync path + async resolution in PollCourierStatus/resolvePendingCreation), apply via OrderStatusChanger (notify off) unless order already at that status. Tests: echo on sync book, echo on async resolution, none-setting no-op, no double-history on repeat polls.
- [ ] TDD, commit `feat(fulfillment): status echo after booking`.

### Task 5: sweep
Suites (baseline 23), build+clears, grep gates (fulfillment_stage zero outside migrations/docs), browser walk: Delivered tab now holds the 24,620 legacy orders and To Pack only genuine work (record counts), custom-status live round-trip with a throwaway status (create, place, verify, delete), gate refusal on a delivered-placed order (throwaway order only), Status Flow grid + override + reset, booking-echo setting visible, light+dark, console. NEVER touch order 29850/SPEPH065515515378. Screenshots + report.
- [ ] Commit fixes or report clean.

## Self-review notes
- Owner's law: actions hardcoded (T2 matrix), membership editable (T1+T3): directly traceable.
- Risks: enum conversion on MySQL vs sqlite tests (T1 two-step); placement rename totality (T1 grep); override layer must not touch adapters (T3, QuadX freeze); echo double-fire on poll loops (T4 idempotency guard).
