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
- `x-admin3.settings-shell`: inside Settings the work nav condenses,
  never disappears: a 56px azure icon rail on the far left showing
  the SAME parents as the main menu in the same order (Dashboard,
  Sales, Catalog, Customers, Content, Marketing; Settings active at
  its foot), derived from `config/admin-nav.php` so the two can
  never drift. A parent has no page of its own: its icon jumps to
  the group's first destination and the tooltip names the parent.
  Beside it, the grouped settings nav (248px) from
  `config/admin3-settings-nav.php` (Store, Commerce, Communication,
  System). Below 1024px the rail
  hides, the top band grows a back arrow, and the nav becomes a
  select picker. General is general; a module's settings live with
  the module. (Owner-directed revision of the original full-screen
  Shopify takeover, 2026-08-16.)

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

## Layout law: the measure centers

Content lives in a 1080px measure, sized for the standard laptop and
1080p majority, and it CENTERS in whatever space the nav columns
leave. No v3 surface anchors content off-center; slack on an
ultrawide splits evenly and is the normal cost of a bounded measure,
never a reason to stretch or hug. One measure everywhere, tables and
forms alike (owner-directed 2026-08-16).

## Nav components

- `x-admin3.rail`: THE main nav, both renditions of it. Full azure
  sidebar by default; `condensed` renders the 56px icon strip of
  main-menu parents derived from `config/admin-nav.php` (the v2 main
  menu's registry), so the rail can never drift from what the person
  saw before entering Settings. The full rendition still reads
  `admin3-nav.php` pending the first work-area migration. Full mode
  expects the work shell's Alpine `nav` drawer boolean in scope.
- Motion architecture (settled after five owner rounds): view
  transitions do ONE job, holding frames. Every admin navigation
  runs a bare 120ms root crossfade so the browser never paints the
  blank white inter-page frame (the flicker in every MPA), and
  theme-boot paints html itself canvas-colored for cold loads. NO
  named view-transition pairs exist anywhere: snapshot morphs raced
  servers, caches, and browsers through four failed rounds, and a
  pair on the settings-nav active item produced an unasked highlight
  animation. Do not add pairs back.
- The Easypanel nav move is LIVE-DOM animation, deterministic in
  every browser (Firefox included) and never freezing input. The
  shells' pre-paint head scripts compare navigation.activation.from
  (referrer fallback) against window.__vSettingsRoot and set one
  class: `v3-cross` on the settings shell when the previous page was
  outside Settings, `v-cross` on the work shell when it was inside.
  Entering: the icon rail arrives at 216px with real parent-label
  spans showing, narrows to 56px over 500ms ease-in-out while
  labels fade in the first 150ms; icon centers hold at 27.5px in
  both states (8px rail padding + 10px item padding + 19px icon);
  the settings nav and content ride the reflow leftward. Leaving:
  the slab arrives at 56px inside its fixed grid column and widens
  back, labels fading in near the end, sweeping rightward over the
  canvas. Settings-to-settings navigations animate NOTHING.
  Desktop only; reduced motion collapses everything. In the main
  menu Settings is a flat door item, not a dropdown;
  `AdminNav::sidebarGroups()` (the old dropdown expansion) is gone.
- `x-admin3.context-nav`: any second menu column beside the rail
  (settings today, a module's sections tomorrow). Caller passes
  resolved groups with urls; the component lists them and knows
  nothing else.
- The shells compose these; nav markup lives nowhere else.

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
5. Content arrives settled: no per-page entrance animation
   (`.v3-settle` is a plain wrapper; its fade was retired 2026-08-16
   after it read as "the content animates, the nav does not"). The
   NAV owns arrival motion: the rail morph on view-transition
   crossings, and the `.v3-arrive` slide-in entrance when no
   transition ran (hard entry, refresh, bookmark, or a browser
   without cross-document view transitions, e.g. Firefox); a
   settings-to-settings referrer replays nothing. Reduced motion
   collapses all of it.
6. No em dashes anywhere, comments included.
7. Both themes, 390 to 1440, every page; the v2 gates (compiled-view
   lint, build, suite) still apply.
8. A list page's rows are plain server HTML, visible before any
   JavaScript. Per-row x-if edit templates hide the data until
   Alpine boots (owner-reported blank courier list on slow loads)
   and stall boot at scale (250-row localization). The idiom is one
   shared editor panel above the table, prefilled from an @js()
   payload on Edit. x-if stays for modals and interaction-gated
   editors only.

## Doctrine mechanics added in round two

- One submit may carry several setting groups
  (`groups[checkout][...]` + `groups[stock][...]`);
  `SettingController::updateGroups` validates everything before the
  first write.
- `window.v3Post(url, data)`: every in-page action button (test
  sends, generators) POSTs JSON, never navigates, and toasts the
  server's own verdict.
- Test buttons test the values as typed: `testMail` accepts an
  `override` block applied to this request's mailer only, with a 10s
  transport cap so a dead host answers fast.
- `x-admin3.secret` (logic in `Alpine.data('v3Secret')`): a stored
  key rendered as twelve bullets plus its last-4 tail (passwords:
  bullets only), read-only at rest. View fetches the plaintext on
  demand through the hard-allowlisted `settings.reveal-secret`
  endpoint; nothing decrypted ever rides in a rendered page. Edit
  replaces; blank on submit means keep. Cancel re-checks the form on
  the next tick so a clean form never shows the save bar.
- Destructive in-page actions (mark removal) run in place through
  `v3Post` behind a two-step arm-confirm button (4s disarm), so
  typed edits elsewhere on the page survive them.

## Migrated so far

- Settings landing (`admin/settings/hub.blade.php`): grouped
  reading-density rows with search and a zero-match empty state.
- Geo zone editor (`admin/settings/localization/geo-zone-edit`): the
  doctrine's proof page; one form saves zone and entries in one
  transaction (`LocalizationController::updateGeoZone`).
- General (`sections/general`): identity, marks, and defaults in one
  save (`StoreIdentityController::update` carries the groups payload).
- Notifications (`sections/mail`): server + sender in one save, the
  password masked, and a test that never navigates.
- Checkout & Stock (`sections/checkout`): two setting groups, one save.
- Security (`sections/security`): admin path + Turnstile in one save,
  the secret key masked.

- Currencies, API & Sync (sections): row-record saves through the
  id'd-form idiom, reveal-safe token notice, confirm dialogs.
- Orders (statuses), Couriers & Tracking: editable row tables with
  per-record saves and add panels.
- Email Templates (index + editor): submit-toggle in the list;
  TinyMCE wired into the dirty doctrine (editor edits summon the
  save bar); test send in place via v3Post; copyable placeholders.
- Users (index + form), Permissions: CRUD table, doctrine form, and
  the role matrix under one save.

- Stock Alerts: stat figures, tab bar (?tab= preserved), bulk
  select with a live-count confirm, and the alert template under
  the doctrine with TinyMCE wired in.

- Modules: extensions and core features as directory rows carrying
  state badges, action forms, and uninstall confirms.

- Theme: the critique's worst multi-save offender rebuilt as ONE
  doctrine form spanning seven settings tabs (colors, fonts, header,
  product page, category page + filters, badges, blog); record tabs
  (navigation, homepage sections, footer columns, theme options) are
  sibling panels with record-scoped forms; the storefront switch and
  the colors-and-fonts revert both confirm first; drag reorder toasts
  its verdict.

- Shipping: numbered provider chain, rate tester posting via v3Post,
  pickup address card; DHL, flat rate, free shipping (+ rules with
  picklist conditions), and weight-based (bulk one-save with
  expandable drag cards) each carry their own doctrine form.

- Payments: directory rows with enable toggles and restriction
  summaries; per-method pages tab Configuration / Availability.
  Enable toggles submit through hidden sibling forms (the
  form="" attribute idiom) so they never nest inside the doctrine
  form. The availability rule is a record-scoped editor whose
  summary resolves ids to names; bank accounts are row records
  with a v3 edit page. HitPay credential hints show only the
  last-4 tail, with neutral wording when decryption fails.

- Localization: countries, zones, and geo zones under one tab bar.
  250 country rows stay plain HTML with ONE shared editor panel;
  per-row x-if edit templates at that scale stall Alpine's boot.
  Toggles post through v3Post with optimistic flip and rollback.

- Automations: crontab notice with copy, schedule table with
  optimistic toggles and dimmed disabled rows, a hand-rolled v3
  modal for the schedule editor (live preview text + cron), and a
  JS-resolving run-now confirm. The x-admin3.confirm component only
  fits form-action confirms; JS-callback confirms hand-roll the
  same v3-overlay/v3-modal structure.

Every page inside Settings now renders on the v3 shell. Outside
Settings, api-sync-logs and profile edit still carry the v2 shell
until their areas migrate.
