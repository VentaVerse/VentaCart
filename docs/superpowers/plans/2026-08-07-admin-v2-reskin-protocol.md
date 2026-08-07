# Admin v2 Reskin Wave Protocol (shared by every Plan 4 wave task)

Read this ENTIRE file before touching any view in your wave. It is the
distilled, review-proven procedure from Plans 2–3 (settings sections, SEO
wave). Deviating from it is what got prior tasks bounced.

## The rule

A wave task migrates each listed view from the old layout
(`<x-admin-layout>` → `resources/views/layouts/admin.blade.php`) onto the
new shell (`<x-admin.shell title="…"><x-admin.page title="…" :breadcrumb="…">`)
with **identical workflows**: every form action, field name, filter/query
param, pagination payload, and route call survives byte-for-byte. Only the
presentation layer changes.

## Procedure per view

1. READ the old view completely first. Inventory: forms (action route +
   every input name), filters/sort params, pagination, Alpine components,
   `@push` scripts, TinyMCE init, file-upload inputs (`enctype`), modals,
   bulk-select payloads.
2. Rewrite onto `x-admin.*` components: `page` (+`actions` slot for the
   primary button), `card`, `table`/`table.th`(sortable where the old view
   sorted)/`table.row`(href for row-click detail pages)/`table.cell`(mono
   for money/qty/IDs), `field`/`input`/`select`/`textarea`/`checkbox`/
   `toggle`/`radio-group`, `form-actions` (sticky save), `badge` (status
   tones), `empty-state` (every index gets one), `confirm-dialog` (every
   destroy), `tabs` (where the old view had tabs), `button`.
3. Keep Alpine logic (`x-data` blocks, variant editors, dynamic rows)
   as-is unless it styles via classes — then swap classes only.
4. `data-v-form` on every form (double-submit guard + spinner).
5. Toggles vs checkboxes: `x-admin.toggle` posts hidden-0 + 1. If the
   receiving controller checks `isset()`/`has()` instead of the value, use
   `x-admin.checkbox` (no hidden 0) — this exact mismatch was caught in
   Plan 3 Task 6. READ the controller's validation before choosing.
6. Forms inside table rows: use the `form="id"` attribute pattern
   (empty form element outside the `<table>`, inputs carry `form=`),
   proven in `sections/currencies.blade.php` — NEVER nest a table inside
   a row (bounced in Plan 2 Task 7 with measured 100px column drift).
7. TinyMCE views: keep the exact `@push` script blocks and textarea IDs.
8. Flash banners → drop; the shell's toast system renders session flash.

## Hard constraints

- Design system ONLY: `v-*` token classes, zero `bg-white`/`gray-*`/
  `blue-*`/`indigo-*`/`green-*`/`amber-*`/`red-*` etc. Grep every
  rewritten view for `-(gray|blue|indigo|green|red|amber|yellow|purple|white)-`
  and `style=`/`<style>` before committing — Plan 3 Task 7 was bounced
  for exactly this. Exception: status-tone via `x-admin.badge`, warnings
  via `text-v-warning` etc.
- Never put CSS inside Blade files. If a view genuinely needs structural
  CSS, add a file under `resources/css/admin/components/` + import in
  `admin.css` + `npm run build`.
- Controllers/routes/services UNTOUCHED unless the task explicitly allows
  a change. Additive read-only queries in the page's own controller
  method are permitted when the redesign needs a count/lookup the old
  view lacked (precedent: Plan 3 Tasks 8–9) — document each in your report.
- ABSOLUTE RULE: no destructive queries against the live database.
  Feature tests use RefreshDatabase (SQLite :memory:).
- Commit ONLY your task's files. Exclude: package-lock.json,
  docs/Screenshot*, docs/superpowers/plans/2026-05-01-*,
  public/pricing-mockup.html.

## Testing per wave

Extend `tests/Feature/AdminV2/ReskinWaveTest.php` (create in the first
wave task with the class skeleton) with one method per wave:
`test_<wave>_pages_render_in_new_shell()` — actingAs an admin User
factory, GET every migrated route, `assertOk()` + `assertSee('v-admin', false)`.
Seed minimal models where a route needs them (read how existing AdminV2
tests seed; store_id=1 rows need a stores row). Where the old view had a
save round-trip worth pinning (one per wave, pick the riskiest form),
add a POST/PUT round-trip test asserting the database write — field
names exactly as the controller validates.

Run per wave: `php artisan view:clear && php artisan test --filter=ReskinWaveTest`
then `php artisan test --filter=AdminV2` (all green; baseline 26
pre-existing failures elsewhere is not yours to fix).

## Report

Write the full report to the SDD workspace path given in your dispatch:
per-view inventory→port table, controller queries added (if any),
deviations + why, test output. Final message: ONLY status, commit hash,
one-line test summary, concerns.
