# Venta admin design record

> **On the name (owner ruling 2026-08-21, card #133, LANDED):** the
> system was built under the internal codename "v"; the product is
> Venta 2, so on 2026-08-21 the whole vocabulary collapsed to the
> version-free `v-` prefix and the `admin` directories, the legacy v2
> layer was deleted, and this file replaced the old DESIGN.md as the
> one record. Never add version-indexed names anywhere.

The system of record for the v admin rebuild, written from the built
world. Scope: `resources/css/admin/**`, `resources/js/admin/**`,
`resources/views/components/admin/**`, and every page migrated to a
v shell. The v2 layer is deleted; every page answers to this record. Where this file and the code disagree, one of them is
fixed in the same commit.

## The world

ERP cousin, restated from zero. Identity carried: the azure slab
(`--v-slab #1B3D5F`), brass as the only action color (`--v-act-fill
#C0873A`, word stop `#8A5F1F`), Archivo for interface and JetBrains
Mono rationed to identifiers and measurements. Everything else is
v's own: tinted paper `#EDF1F7` under white sheets, two lifts, radii
6/10/14, 36px controls, 130/230ms motion, light and dark themes both
stated in `tokens.css` with AA-checked stops.

## The two shells

- `x-admin.shell`: no longer chrome of its own. Owner ruling
  2026-08-17, sharpened twice in one day: first the lean object nav
  was rejected for the classic grouped structure, then the deeper
  verdict landed, that TWO chrome templates for one admin (different
  slab widths, a topbar without the palette search, the account not
  clickable, no theme switch) is "redundant, unnecessary, useless
  and WRONG". The component is now a thin wrapper that renders the
  classic `x-admin.shell` (one slab template, one topbar with
  palette, theme switch and account, one toast layer, one command
  palette) around a `.v .v-embed` ground div carrying the v
  stylesheet. The v Alpine pieces (vForm, vSecret) live in
  `resources/js/admin/register.js` and are registered by BOTH
  bundles (`registerV()`), so the save doctrine runs on whichever
  Alpine the page booted and no page ever loads two. The v dark
  tokens flip with the classic theme switch. The classic sidebar carries work-waiting counts
  (App\Support\NavBadges, cached 60s: Fulfillment to-ship, Reviews
  pending) through the shell's existing badge lookup, adopted from
  the v nav evaluation as its one surviving benefit; the
  slab-to-strip structure itself was declined for work areas.
- `x-admin.settings-shell` / `x-admin.seo-shell` (via
  `space-shell`): conformed to the one chrome (owner ruling
  2026-08-17). A space keeps what makes it a space, its grouped
  context nav (248px, from `admin-settings-nav` / `admin-seo-nav`),
  as a second column beside the classic sidebar, inside the classic
  shell. The icon strip, the separate top band, and the seat-crossing
  animation are gone with the separate document that needed them.
  Below 1024px the context nav becomes the select picker. General is
  general; a module's settings live with the module.

## The save doctrine (the reason v exists)

One page is one save scope. `x-admin.form` renders the form, CSRF,
method spoof, and the save bar. A page opts out only by owner ruling,
with `:guard="false"`: the form then runs on `vFormPlain` (never dirty,
no leave guard, Save always in the header). The customer editor is the
one such page (2026-08-29).

- `vForm` (admin.js) snapshots the form's serialized fields on
  load, flips `dirty` on any input/change, and un-dirties when edits
  revert to the baseline.
- The save bar (`.v-savebar`) is a slab-colored band that descends
  from the top edge when dirty: brass dot, "Unsaved changes"
  (role="status"), quiet Discard, brass Save. Inert while hidden.
- `beforeunload` guards navigation while dirty. Submit releases the
  guard; Discard reloads, because the server's truth is the only
  clean state worth restoring.
- Dynamic editors (rows added/removed outside input events) call the
  parent's `touch()` from markup expressions. Inside `vForm`, the
  form element lives in a closure, never `this.$el`: a method invoked
  through a child scope chain sees the child's element.
- Flash messages state persisted facts only: real counts, dropped
  rows named. A toast never lies.
- Validation failures round-trip the merchant's edits: dynamic
  editors seed from `old(...)` before falling back to the database.

## Layout law: the measure centers

Content lives in a 1080px measure, sized for the standard laptop and
1080p majority, and it CENTERS in whatever space the nav columns
leave. No v surface anchors content off-center; slack on an
ultrawide splits evenly and is the normal cost of a bounded measure,
never a reason to stretch or hug. One measure everywhere, tables and
forms alike (owner-directed 2026-08-16).

## Spaces (the context nav, kept; the chrome, unified)

`x-admin.space-shell` no longer owns a document. It renders the
classic shell with the space's grouped context nav as a second
column beside the main sidebar; Settings and SEO wear it through
thin wrappers (`settings-shell`, `seo-shell`) that only resolve
their registries (`admin-settings-nav`, `admin-seo-nav`). The
condensed icon rail, the space top band, and the seat-crossing
animation retired with the separate chrome (owner ruling
2026-08-17: one admin, one chrome; the crossing had nothing left to
cross). A space never subtracts from the main sidebar; its parent
stays visible and lit (SEO under Marketing). Trap to remember:
Blade directives (@js) never compile inside COMPONENT-TAG
attributes; use {{ }} interpolation there (this bug shipped and was
caught in verification).

## Nav components

- `x-admin.rail`: deleted entirely. The full rendition went with
  the one-nav ruling; the condensed icon strip went when the spaces
  conformed to the one chrome and stopped needing a stand-in for the
  main menu. One nav template remains: the classic slab.
  `config/admin3-nav.php` was deleted with the rail.
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
  app.js/admin.js) so script work never delays the first frame
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
  PILLS (.v-irail__tip): a slab-colored pill beside the hovered or
  focused icon, 60ms reveal, replacing the browser's slow native
  title tooltips (removed from the rail; aria-labels stay). Scoped
  to the rail by owner choice. The
  shells' pre-paint head scripts decide it (`v-cross`/`v-cross`)
  from navigation.activation against window.__vSettingsRoot.
  Transform and opacity only: deterministic in every browser, never
  freezes input. Within Settings and between work pages NOTHING
  moves. Desktop only; reduced motion collapses everything. In the
  main menu Settings is a flat door item, not a dropdown;
  `AdminNav::sidebarGroups()` (the old dropdown expansion) is gone.
- `x-admin.context-nav`: any second menu column beside the rail
  (settings today, a module's sections tomorrow). Caller passes
  resolved groups with urls; the component lists them and knows
  nothing else.
- The shells compose these; nav markup lives nowhere else.

## Rules the layer depends on

1. Everything at one class (`v-` prefix). Element resets in
   `base.css` sit at `:where()` zero specificity so primitives always
   outrank them on document order. No Tailwind in v markup.
2. No CSS in Blade files, including `style=` attributes. Structural
   spacing uses the layer's spacers (`v-gap`, `v-gap-lg`,
   `v-span2`, `v-narrow`, `v-block`).
3. Icons come from `x-admin.icon`: one 24 grid, 1.75 stroke, one
   path per name. Adjacent nav items never share a glyph.
4. Browser surfaces are themed: selection, caret, scrollbars, one
   focus grammar (`--v-focus` ring at 3:1+).
5. Content arrives settled: no per-page entrance animation
   (`.v-settle` is a plain wrapper; its fade was retired 2026-08-16
   after it read as "the content animates, the nav does not"). The
   NAV owns arrival motion: the rail morph on view-transition
   crossings, and the `.v-arrive` slide-in entrance when no
   transition ran (hard entry, refresh, bookmark, or a browser
   without cross-document view transitions, e.g. Firefox); a
   settings-to-settings referrer replays nothing. Reduced motion
   collapses all of it.
6. No em dashes anywhere, comments included.
6b. No instructional text prints on a page. A field's explanation
   lives behind `x-admin.help`, the question mark beside the label
   (hover or focus reveals it); `x-admin.field` routes its `hint`
   prop there automatically, and `x-admin.panel` routes its `note`
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
8c. Control vocabulary: the SWITCH (label.v-toggle with track) is
   the only boolean-state control, everywhere; a bare checkbox
   (v-check) means multi-selection (picklists, bulk select, the
   permissions matrix); a radio means one-of-many (default status).
   The hidden-0 companion input always rides the switch.

## Doctrine mechanics added in round two

- One submit may carry several setting groups
  (`groups[checkout][...]` + `groups[stock][...]`);
  `SettingController::updateGroups` validates everything before the
  first write.
- `window.vPost(url, data)`: every in-page action button (test
  sends, generators) POSTs JSON, never navigates, and toasts the
  server's own verdict.
- Test buttons test the values as typed: `testMail` accepts an
  `override` block applied to this request's mailer only, with a 10s
  transport cap so a dead host answers fast.
- `x-admin.secret` (logic in `Alpine.data('vSecret')`): a stored
  key rendered as twelve bullets plus its last-4 tail (passwords:
  bullets only), read-only at rest. View fetches the plaintext on
  demand through the hard-allowlisted `settings.reveal-secret`
  endpoint; nothing decrypted ever rides in a rendered page. Edit
  replaces; blank on submit means keep. Cancel re-checks the form on
  the next tick so a clean form never shows the save bar.
- Destructive in-page actions (mark removal) run in place through
  `vPost` behind a two-step arm-confirm button (4s disarm), so
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
x-admin.form, no exceptions and no inline Save buttons. Swept in this
round: the payment availability rule (was a plain form with Save rule),
and the theme page's per-section, footer bulk, and theme-options forms.
Still legitimately OUTSIDE the bar: Add-row create forms (verbs), the
automations schedule modal, slug/redirect/localization editors gated
behind an Edit press, and module Install/Enable verbs.

Multiple doctrine forms per page each carry their own fixed savebar;
only a dirty form's bar shows, and editing two at once overlaps them,
which is accepted (payments index set the precedent).

Mechanics added: `.v-passthru` (display: contents) on the confirm
component's root so hidden confirm wrappers never eat a settle-owl slot;
tab content wrappers on tabbed pages carry `.v-settle` themselves
(payments show was missing it, cards sat too close). Record creation
gets dedicated pages: bank accounts now have create + edit pages, and
the populate-above Add panel is gone.

## Extension sweep (owner directive, 2026-08-17, second round)

The courier-fulfillment admin surfaces now wear v: the module hub,
status placement, per-courier pages (schema-field partial rewritten in
v vocabulary), and pickup addresses (index + form). The banners GROUP
form is v and is the first consumer of the full-rail x-admin.shell.
Pickup addresses were also DECOUPLED from core Shipping (owner: core
shipping is the manual process; the module hub owns the link), and
ShippingPageTest now guards the decoupling.

New doctrine mechanic: vForm.rebaseline() — an async widget that
hydrates field values after load (the pickup address cascade restoring
saved dropdowns) calls it when hydration settles, so restored state is
clean state, not a phantom edit. Dynamic editors that mutate hidden
serialized inputs (the SPX pouch-preset rows) call touch() on add and
remove, because Alpine :value bindings fire no input events.

Secrets on v courier pages are never rendered, even masked: the field
shows a password box whose help mark names the identifying tail; blank
keeps the stored value (persist() already skips empty encrypted keys).

Still v2, waiting for its own wave: the reviews pages (Sales area).

## Extension automations (owner ruling, 2026-08-30)

One heartbeat for the whole store: a single `* * * * * php artisan
schedule:run` crontab line, and a row in `automations` per job carrying
its own on/off switch and schedule. An extension that needs a scheduled
job seeds its row (firstOrCreate, so a merchant's edits survive) and
drops `<x-admin.automation key="..." title="..." />` into its own
settings page, INSIDE that page's doctrine form. The component renders
the switch, the frequency editor, the last run, and a Run now verb, and
posts under `automation[<key>][...]`; the extension's controller hands
the request to `AutomationInput::apply($key, $request->all())`. The
global Settings, Automations page still lists every row: same data, a
second door. When the heartbeat has not fired in an hour the component
says so and shows the crontab line, because "this job is off" and
"nothing on this server runs any job" are fixed in different places.
The fulfillment module's Status updates panel is the first consumer and
heals its own missing row on every hub load.

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
- Orders (statuses), Couriers: editable row tables with per-record
  saves and add panels. Couriers moved OUT of Settings on 2026-08-28
  (owner ruling): it is the store's courier directory, order vocabulary
  like statuses, and core (works with no fulfillment module installed),
  so it lives under Sales at /sales/couriers on the classic shell, with a
  pointer from the Fulfillment space's context nav. Route names froze.
- Email Templates (index + editor): submit-toggle in the list;
  TinyMCE wired into the dirty doctrine (editor edits summon the
  save bar); test send in place via vPost; copyable placeholders.
- Users (index + form), Permissions: CRUD table, doctrine form, and
  the role matrix under one save.

- Stock Alerts: stat figures, tab bar (?tab= preserved), bulk
  select with a live-count confirm, and the alert template under
  the doctrine with TinyMCE wired in.

- Abandoned Carts (extension, all nine views): ops worklist in Sales
  (stats, filterbar, pager, confirm dialogs) with the tab trio and one
  labeled Settings crossing; config trio (settings, rules, templates)
  behind Extensions with its own tab bar and Carts-workspace crossing;
  the settings toggles, rule form, and template form (TinyMCE) all
  under the doctrine savebar.

- Extensions (wave 0, complete): a top-level area beside Settings,
  never inside it. The sidebar's Extensions group (between Marketing
  and Settings) holds its three children per the blessed draft: All
  Extensions (the directory at /extensions), Payments (the
  payment-methods category, re-homed from Settings with bank
  accounts and restrictions), and Shipping (the numbered rate chain,
  the rate tester, and the Couriers section — owner ruling: couriers
  are part of shipping). Every extension's settings mount at
  extensions/{name}; path-preserving 301s cover the old
  settings/modules, settings/payments, and settings/shipping trees.
  Settings is core-only again (Store / Operations / Access groups).
  The guardrail: disabling the last payment or shipping method is
  allowed but the category page says loudly that the storefront
  cannot check out. ONE directory list, no Uninstall button. An
  extension's own gates read its own manifest, never a core registry
  (the fulfillment configure gate is the precedent).
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

- Shipping: numbered provider chain, rate tester posting via vPost,
  pickup address card; DHL, flat rate, free shipping (+ rules with
  picklist conditions), and weight-based (bulk one-save with
  expandable drag cards) each carry their own doctrine form.

- Payments: directory rows with enable toggles and restriction
  summaries; per-method pages tab Configuration / Availability.
  Owner ruling 2026-08-18: a side nav earns its column only when
  sections are many (Theme, the settings space); two or three
  sections take the v-tabs bar.
  Enable toggles submit through hidden sibling forms (the
  form="" attribute idiom) so they never nest inside the doctrine
  form. The availability rule is a record-scoped editor whose
  summary resolves ids to names; bank accounts are row records
  with a v edit page. HitPay credential hints show only the
  last-4 tail, with neutral wording when decryption fails.

- User Groups: each group carries its own permission config
  (OpenCart-style, replaced the all-groups matrix). Permission
  fieldsets sit under headings that mirror the admin sidebar
  (Overview / Sales / Catalog / Customers / Extensions / System) so
  the matrix reads like the nav it gates; unmapped groups from
  extensions land under Extensions automatically. Per-block All/None
  plus panel-level Grant all/Clear all dispatch real change events so
  the save bar arms. The edit head states who holds the access
  (member-count link into the filtered Users directory); reducing
  your OWN group's permissions passes a capture-phase confirm before
  vForm sees the submit. Super Admin renders checked-and-disabled
  (muted), server re-syncs it to all permissions on every save, and
  old('permissions') only speaks after a real validation round-trip.

- Localization: countries, zones, and geo zones under one tab bar.
  250 country rows stay plain HTML with ONE shared editor panel;
  per-row x-if edit templates at that scale stall Alpine's boot.
  Toggles post through vPost with optimistic flip and rollback.

- Automations: crontab notice with copy, schedule table with
  optimistic toggles and dimmed disabled rows, a hand-rolled v
  modal for the schedule editor (live preview text + cron), and a
  JS-resolving run-now confirm. The x-admin.confirm component only
  fits form-action confirms; JS-callback confirms hand-roll the
  same v-overlay/v-modal structure.

- Banners: groups index, slides list (bg colour as an SVG rect
  presentation attribute), and the slide editor as a doctrine form
  around an inner-div Alpine canvas; layer JSON and image picks
  signal dirty through bubbled change events.

- Orders: index with a GET filter bar and tone-mapped status badges;
  order view as v-editcols (facts, addresses, products + ledger,
  tracking left; the Update-order verb form and the history feed
  right); order create/edit as ONE doctrine form spanning Details /
  Products tabs. The customer and product pickers are v-combo
  menus, Existing/New customer is a v-seg switch, and line items
  are editable rows in a v-table with a v-facts totals box. The
  async hydration chain (address cascades, then method fetches)
  awaits fully and then dispatches v-rebaseline, so a restored
  order loads clean. Naming: `.v-facts` is the generic label/value
  dl; the flush totals strip under a products table is `.v-ledger`
  (one class, one shape — the earlier double definition broke both).

- Fulfillment queue + returns: the flagship work surface. Counted
  tab bar as v-tabs anchors (data-count-for hooks preserved for the
  tab-set tests), the To Ship sub-bar, the handover tally, the
  two-tier table (v-tier band + item rows + rowspanned fact cells),
  the detail drawer (v-drawer: stepper, event list, kv summaries),
  and the booking modal (v-choicecard verdict cards with courier
  marks, the courier form, the manual record form). All actions are
  verbs — no doctrine form on the page. Confirm dialogs moved to
  x-admin.confirm ($dispatch('v-confirm', name)). The module's
  fulfillment.css retokened to v vars; only widths, marks, and the
  pickup-page floors stay page-specific.

New shared piece: x-admin.pager — the stepped "N to M of T" pager
(range left, Previous/Next right, hrefs echoed exactly once). Every
v index page now uses it; a bare {{ links() }} renders the stock
Tailwind view whose classes are dead in the admin shell. And
.v-modal carries white-space: normal, so a confirm rendered inside
a nowrap table action cell wraps its message (the queue's regression
test pins this at the component level now).

- Customers (index, groups, and the customer form as one doctrine
  form spanning General / Addresses / Activity tabs; the address
  cascades hydrate fully and rebaseline; the store-credit panel rides
  along in v dress).
- Content: Pages (TinyMCE wired to the doctrine), the blog extension
  (posts, categories, the featured-image browser on the v media
  modal), and the coupons work pages (list + form with v-combo
  restriction pickers).
- Catalog, the closing wave: products index and the six-tab product
  form as one doctrine form (brand combo, variant matrix and specials
  as v tables that touch() the bar, combination rebuilds
  rebaseline), categories as a nested v-dragrow list whose reorder
  toasts its verdict, brands, tags, and the media manager. The shared
  image picker and the product image manager re-shipped as v
  partials (single_image_picker, image_manager) with new
  tokens: v-mediagrid, v-imgpreview, v-imgcard, v-tilewrap /
  v-tileacts, v-dropwrap / v-dropzone, v-dragnest, v-tilemark,
  and control-level is-invalid.
- The day sheet (dashboard): its owner-approved composition kept
  whole (it already speaks the Blotter identity) and mounted on the
  v shell; the ui-kit specimen page likewise.

The v2-to-v shell conversion is COMPLETE: no admin page renders
x-admin.shell directly anymore. The classic chrome and the v2
primitive layer (x-admin.page, figure, money, panel, table) survive
as the shared chrome and the day sheet's vocabulary, both rendered
inside the one v ground.
