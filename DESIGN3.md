# Venta admin v3 design record

The system of record for the v3 admin rebuild, written from the built
world. Scope: `resources/css/admin3/**`, `resources/js/admin3/**`,
`resources/views/components/admin3/**`, and every page migrated to a
v3 shell. Pages still on the v2 shell answer to DESIGN.md until their
area migrates. Where this file and the code disagree, one of them is
fixed in the same commit.

## The world

ERP cousin, restated from zero. Identity carried: the azure slab
(`--v3-slab #1B3D5F`), brass as the only action color (`--v3-act-fill
#C0873A`, word stop `#8A5F1F`), Archivo for interface and JetBrains
Mono rationed to identifiers and measurements. Everything else is
v3's own: tinted paper `#EDF1F7` under white sheets, two lifts, radii
6/10/14, 36px controls, 130/230ms motion, light and dark themes both
stated in `tokens.css` with AA-checked stops.

## The two shells

- `x-admin3.shell`: the work surface. Azure slab left (232px) with
  the store's own name, a lean object nav (Home, Orders, Products,
  Customers, Content, Marketing), a Modules group (SEO, Fulfillment,
  Abandoned Carts), and Settings pinned at the slab's foot. Hairline
  topbar. Below 1024px the slab is a drawer behind a scrim.
  Registry: `config/admin3-nav.php`; items drop when their route is
  not registered. NOT yet mounted by any page; first consumer is the
  next migrated area.
- `x-admin3.settings-shell`: Settings takes the whole screen. Top
  band with "Back to admin"; grouped left nav (248px) from
  `config/admin3-settings-nav.php` (Store, Commerce, Communication,
  System); below 1024px the nav becomes a select picker. General is
  general; a module's settings live with the module.

## The save doctrine (the reason v3 exists)

One page is one save scope. `x-admin3.form` renders the form, CSRF,
method spoof, and the save bar; a page cannot opt out.

- `v3Form` (admin3.js) snapshots the form's serialized fields on
  load, flips `dirty` on any input/change, and un-dirties when edits
  revert to the baseline.
- The save bar (`.v3-savebar`) is a slab-colored band that descends
  from the top edge when dirty: brass dot, "Unsaved changes"
  (role="status"), quiet Discard, brass Save. Inert while hidden.
- `beforeunload` guards navigation while dirty. Submit releases the
  guard; Discard reloads, because the server's truth is the only
  clean state worth restoring.
- Dynamic editors (rows added/removed outside input events) call the
  parent's `touch()` from markup expressions. Inside `v3Form`, the
  form element lives in a closure, never `this.$el`: a method invoked
  through a child scope chain sees the child's element.
- Flash messages state persisted facts only: real counts, dropped
  rows named. A toast never lies.
- Validation failures round-trip the merchant's edits: dynamic
  editors seed from `old(...)` before falling back to the database.

## Rules the layer depends on

1. Everything at one class (`v3-` prefix). Element resets in
   `base.css` sit at `:where()` zero specificity so primitives always
   outrank them on document order. No Tailwind in v3 markup.
2. No CSS in Blade files, including `style=` attributes. Structural
   spacing uses the layer's spacers (`v3-gap`, `v3-gap-lg`,
   `v3-span2`, `v3-narrow`, `v3-block`).
3. Icons come from `x-admin3.icon`: one 24 grid, 1.75 stroke, one
   path per name. Adjacent nav items never share a glyph.
4. Browser surfaces are themed: selection, caret, scrollbars, one
   focus grammar (`--v3-focus` ring at 3:1+).
5. One authored entrance per page (`.v3-settle`), nothing else moves
   on arrival. Reduced motion collapses both motion tokens.
6. No em dashes anywhere, comments included.
7. Both themes, 390 to 1440, every page; the v2 gates (compiled-view
   lint, build, suite) still apply.

## Migrated so far

- Settings landing (`admin/settings/hub.blade.php`): grouped
  reading-density rows with search and a zero-match empty state.
- Geo zone editor (`admin/settings/localization/geo-zone-edit`): the
  doctrine's proof page; one form saves zone and entries in one
  transaction (`LocalizationController::updateGeoZone`).
