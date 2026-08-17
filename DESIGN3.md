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

- `x-admin3.shell`: no longer chrome of its own. Owner ruling
  2026-08-17, sharpened twice in one day: first the lean object nav
  was rejected for the classic grouped structure, then the deeper
  verdict landed, that TWO chrome templates for one admin (different
  slab widths, a topbar without the palette search, the account not
  clickable, no theme switch) is "redundant, unnecessary, useless
  and WRONG". The component is now a thin wrapper that renders the
  classic `x-admin.shell` (one slab template, one topbar with
  palette, theme switch and account, one toast layer, one command
  palette) around a `.v3 .v3-embed` ground div carrying the v3
  stylesheet. The v3 Alpine pieces (v3Form, v3Secret) live in
  `resources/js/admin3/register.js` and are registered by BOTH
  bundles (`registerV3()`), so the save doctrine runs on whichever
  Alpine the page booted and no page ever loads two. The v3 dark
  tokens flip with the classic theme switch. The classic sidebar carries work-waiting counts
  (App\Support\NavBadges, cached 60s: Fulfillment to-ship, Reviews
  pending) through the shell's existing badge lookup, adopted from
  the v3 nav evaluation as its one surviving benefit; the
  slab-to-strip structure itself was declined for work areas.
- `x-admin3.settings-shell` / `x-admin3.seo-shell` (via
  `space-shell`): conformed to the one chrome (owner ruling
  2026-08-17). A space keeps what makes it a space, its grouped
  context nav (248px, from `admin3-settings-nav` / `admin3-seo-nav`),
  as a second column beside the classic sidebar, inside the classic
  shell. The icon strip, the separate top band, and the seat-crossing
  animation are gone with the separate document that needed them.
  Below 1024px the context nav becomes the select picker. General is
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

## Layout law: the measure centers

Content lives in a 1080px measure, sized for the standard laptop and
1080p majority, and it CENTERS in whatever space the nav columns
leave. No v3 surface anchors content off-center; slack on an
ultrawide splits evenly and is the normal cost of a bounded measure,
never a reason to stretch or hug. One measure everywhere, tables and
forms alike (owner-directed 2026-08-16).

## Spaces (the context nav, kept; the chrome, unified)

`x-admin3.space-shell` no longer owns a document. It renders the
classic shell with the space's grouped context nav as a second
column beside the main sidebar; Settings and SEO wear it through
thin wrappers (`settings-shell`, `seo-shell`) that only resolve
their registries (`admin3-settings-nav`, `admin3-seo-nav`). The
condensed icon rail, the space top band, and the seat-crossing
animation retired with the separate chrome (owner ruling
2026-08-17: one admin, one chrome; the crossing had nothing left to
cross). A space never subtracts from the main sidebar; its parent
stays visible and lit (SEO under Marketing). Trap to remember:
Blade directives (@js) never compile inside COMPONENT-TAG
attributes; use {{ }} interpolation there (this bug shipped and was
caught in verification).

## Nav components

- `x-admin3.rail`: deleted entirely. The full rendition went with
  the one-nav ruling; the condensed icon strip went when the spaces
  conformed to the one chrome and stopped needing a stand-in for the
  main menu. One nav template remains: the classic slab.
  `config/admin3-nav.php` is deleted.
- Motion architecture, final form: NO view transitions anywhere.
  The opt-in was tried as a frame-hold against the white inter-page
  flash and measurably EXTENDED the blank gap instead (owner saw it
  worse than main; screencast frame analysis confirmed the document
  swap gap shrank from 244ms to 176ms when the opt-in was removed).
  Two defenses replace it: (1) theme-boot paints the html element
  with the app's own SILHOUETTE pre-CSS (azure nav band + paper,
  per theme; shells override --boot-band, settings uses 56px), so
  any between-pages frame wears the app's frame, never white;
  (2) Alpine starts one frame after first paint (double rAF in
  app.js/admin3.js) so script work never delays the first frame
  past the browser's native paint holding. Do not reintroduce
  @view-transition.
- The crossing move is Venta's own, named "taking a seat", built
  inside the world's motion tokens (130/230ms), not any reference
  product's. Furniture never travels: the rail, the slab, and both
  grounds appear seated instantly. What animates is the menu
  contents. Entering Settings the menu's children slide in 26px and
  settle over 180ms (cubic-bezier(0.16,1,0.3,1)) with a 25ms
  cascade capped at 100ms (owner correction round: more pronounced
  and faster than the first 12px/230ms cut), then the brass wash
  IGNITES on the active item, a 130ms stamp delayed 140ms (the one
  branded moment; plays only when a section is active, so the hub
  stays quiet). Leaving, the slab's brand and nav take their seat
  as one piece, same clock. The icon rail's labels are INSTANT
  PILLS (.v3-irail__tip): a slab-colored pill beside the hovered or
  focused icon, 60ms reveal, replacing the browser's slow native
  title tooltips (removed from the rail; aria-labels stay). Scoped
  to the rail by owner choice. The
  shells' pre-paint head scripts decide it (`v3-cross`/`v-cross`)
  from navigation.activation against window.__vSettingsRoot.
  Transform and opacity only: deterministic in every browser, never
  freezes input. Within Settings and between work pages NOTHING
  moves. Desktop only; reduced motion collapses everything. In the
  main menu Settings is a flat door item, not a dropdown;
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
6b. No instructional text prints on a page. A field's explanation
   lives behind `x-admin3.help`, the question mark beside the label
   (hover or focus reveals it); `x-admin3.field` routes its `hint`
   prop there automatically, and `x-admin3.panel` routes its `note`
   prop the same way. Inputs carry NO example placeholders ("e.g.
   ..." belongs in a hint when the format genuinely needs showing,
   otherwise nowhere); placeholders that show live state (the
   current default, the signed-in email) are state, not instruction,
   and stay. Inside a toggle's label the mark guards against
   activating the control. Validation errors, dynamic tails, and
   conditional warnings stay inline; only instruction hides.
7. Both themes, 390 to 1440, every page; the v2 gates (compiled-view
   lint, build, suite) still apply.
8. A list page's rows are plain server HTML, visible before any
   JavaScript. Per-row x-if edit templates hide the data until
   Alpine boots (owner-reported blank courier list on slow loads)
   and stall boot at scale (250-row localization). x-if stays for
   modals and interaction-gated editors only.
8b. Editing a record navigates to a DEDICATED page with its own
   doctrine form (couriers set the pattern, owner ruling: the
   populate-a-panel-above shared editor was near-invisible
   feedback). Localization's shared editors are the remaining users
   of the old idiom, pending the same treatment.
8c. Control vocabulary: the SWITCH (label.v3-toggle with track) is
   the only boolean-state control, everywhere; a bare checkbox
   (v3-check) means multi-selection (picklists, bulk select, the
   permissions matrix); a radio means one-of-many (default status).
   The hidden-0 companion input always rides the switch.

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

## Save-scope law (owner ruling, 2026-08-16)

ONE saving process everywhere (owner ruling, same day, superseding
the earlier directory exception): every STATE control on a page,
toggles included, rides that page's doctrine form and save bar.
Nothing auto-saves. Payments and shipping directories, the payment
method pages' header toggles, email templates, automations, and
localization's three tabs all converted; bulk row endpoints
(update-all) carry tables, and the shared settings.update endpoint
carries grouped keys. Only true one-shot ACTIONS stay instant:
Run now, Delete, Set default, Regenerate, test sends; each is a
verb with its own confirm or toast, never a switch. Record editors
opened by an explicit Edit still save per record (their Save is an
action on that record). The test: a switch or checkbox NEVER talks
to the server by itself.

## Savebar standardization sweep (owner directive, 2026-08-17)

Every form whose fields are typeable without pressing Edit first wears
x-admin3.form, no exceptions and no inline Save buttons. Swept in this
round: the payment availability rule (was a plain form with Save rule),
and the theme page's per-section, footer bulk, and theme-options forms.
Still legitimately OUTSIDE the bar: Add-row create forms (verbs), the
automations schedule modal, slug/redirect/localization editors gated
behind an Edit press, and module Install/Enable verbs. v2 pages
(abandoned carts) convert when their area migrates.

Multiple doctrine forms per page each carry their own fixed savebar;
only a dirty form's bar shows, and editing two at once overlaps them,
which is accepted (payments index set the precedent).

Mechanics added: `.v3-passthru` (display: contents) on the confirm
component's root so hidden confirm wrappers never eat a settle-owl slot;
tab content wrappers on tabbed pages carry `.v3-settle` themselves
(payments show was missing it, cards sat too close). Record creation
gets dedicated pages: bank accounts now have create + edit pages, and
the populate-above Add panel is gone.

## Extension sweep (owner directive, 2026-08-17, second round)

The courier-fulfillment admin surfaces now wear v3: the module hub,
status placement, per-courier pages (schema-field partial rewritten in
v3 vocabulary), and pickup addresses (index + form). The banners GROUP
form is v3 and is the first consumer of the full-rail x-admin3.shell.
Pickup addresses were also DECOUPLED from core Shipping (owner: core
shipping is the manual process; the module hub owns the link), and
ShippingPageTest now guards the decoupling.

New doctrine mechanic: v3Form.rebaseline() — an async widget that
hydrates field values after load (the pickup address cascade restoring
saved dropdowns) calls it when hydration settles, so restored state is
clean state, not a phantom edit. Dynamic editors that mutate hidden
serialized inputs (the SPX pouch-preset rows) call touch() on add and
remove, because Alpine :value bindings fire no input events.

Secrets on v3 courier pages are never rendered, even masked: the field
shows a password box whose help mark names the identifying tail; blank
keeps the stored value (persist() already skips empty encrypted keys).

Still v2, each waiting for its own wave: the reviews pages (Sales
area) and the abandoned-carts pages (their module's wave).

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

- Extensions (wave 0): a top-level area beside Settings, never
  inside it. The sidebar's Extensions group (between Marketing and
  Settings) holds All Extensions, the directory at /extensions;
  every extension's settings mount at extensions/{name} (banners,
  courier-fulfillment, abandoned-carts, seo), with path-preserving
  301s from the old settings/modules tree. ONE list (owner ruling:
  no Extensions/Core split, and no Uninstall button, because you do
  not uninstall your own platform).
  Rows sort alphabetically; the filesystem difference shows only in
  a row's actions: extensions/ modules offer Install/Update (their
  migrations) + Enable/Disable via ExtensionManager, app/ features
  offer the master switch (App\Support\CoreFeatures, settings group
  `features`, opt-out defaults like fulfillment's): off hides the
  nav entry (admin-nav `enabled_setting`) and no-ops the scheduled
  commands, while Configure stays reachable. The uninstall lifecycle
  survives in ExtensionManager (and its routes) for third-party
  extensions that arrive from outside the repo.

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

- Banners: groups index, slides list (bg colour as an SVG rect
  presentation attribute), and the slide editor as a doctrine form
  around an inner-div Alpine canvas; layer JSON and image picks
  signal dirty through bubbled change events.

- Orders: index with a GET filter bar and tone-mapped status badges;
  order view as v3-editcols (facts, addresses, products + ledger,
  tracking left; the Update-order verb form and the history feed
  right); order create/edit as ONE doctrine form spanning Details /
  Products tabs. The customer and product pickers are v3-combo
  menus, Existing/New customer is a v3-seg switch, and line items
  are editable rows in a v3-table with a v3-facts totals box. The
  async hydration chain (address cascades, then method fetches)
  awaits fully and then dispatches v3-rebaseline, so a restored
  order loads clean. Naming: `.v3-facts` is the generic label/value
  dl; the flush totals strip under a products table is `.v3-ledger`
  (one class, one shape — the earlier double definition broke both).

- Fulfillment queue + returns: the flagship work surface. Counted
  tab bar as v3-tabs anchors (data-count-for hooks preserved for the
  tab-set tests), the To Ship sub-bar, the handover tally, the
  two-tier table (v3-tier band + item rows + rowspanned fact cells),
  the detail drawer (v3-drawer: stepper, event list, kv summaries),
  and the booking modal (v3-choicecard verdict cards with courier
  marks, the courier form, the manual record form). All actions are
  verbs — no doctrine form on the page. Confirm dialogs moved to
  x-admin3.confirm ($dispatch('v3-confirm', name)). The module's
  fulfillment.css retokened to v3 vars; only widths, marks, and the
  pickup-page floors stay page-specific.

New shared piece: x-admin3.pager — the stepped "N to M of T" pager
(range left, Previous/Next right, hrefs echoed exactly once). Every
v3 index page now uses it; a bare {{ links() }} renders the stock
Tailwind view whose classes are dead in the admin3 shell. And
.v3-modal carries white-space: normal, so a confirm rendered inside
a nowrap table action cell wraps its message (the queue's regression
test pins this at the component level now).

Every page inside Settings now renders on the v3 shell. Outside
Settings, reviews, api-sync-logs, and profile edit still carry the
v2 shell until their areas migrate.
