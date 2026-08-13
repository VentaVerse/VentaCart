# Venta admin design system

The written system of record for `/admin`. Its absence is why three
foundations grew that shared no vocabulary, so read it before adding a screen
and update it when you add a primitive.

Everything below describes what is **built**, not what was intended. Class
names, token names and measured figures are copied from the running system.
Where the code and this file disagree, the code is the bug or this file is
stale; fix one of them in the same commit.

Scope: `resources/views/admin/**`, `resources/views/components/admin/**`,
`resources/css/admin/**`, and the admin views of every extension under
`extensions/*/resources/views/`. The storefront and its three themes are a
different system and are not covered here.

---

## 1. Where things live

| | Path |
|---|---|
| Tokens | `resources/css/admin/tokens.css` |
| Ground and helpers | `resources/css/admin/base.css` |
| Primitives | `resources/css/admin/components/*.css` |
| Shell chrome | `resources/css/admin/layout/shell.css` |
| Page residue | `resources/css/admin/components/day-sheet.css` |
| Import order | `resources/css/admin/admin.css` |
| Blade components | `resources/views/components/admin/**` |
| The enforcement surface | `resources/views/admin/ui-kit.blade.php` at `/admin/dashboard/ui-kit` |

An extension keeps its own residue in its own stylesheet, pushed with
`@vite` from its view, and picked up by `getThemeEntries()` in
`vite.config.js`. It uses the shared primitives and invents none of its own:
`extensions/courier-fulfillment/resources/css/fulfillment.css` is 140 lines and
`extensions/banners/resources/css/banners.css` is 104.

### Two rules the whole layer depends on

1. **Everything is stated at one class.** No selector in the layer nests
   under `.v-admin` to buy weight. `.v-admin` means one thing only: the ground
   the `<body>` wears and the scope `tokens.css` and `base.css` hang off. A
   rule that must win over a primitive wins on **document order**, never on
   specificity. Adding `.v-admin .v-thing` to make something stick is the
   defect, not the fix.
2. **`admin.css` is linked after `app.css`.** A one-class primitive therefore
   outranks a Tailwind utility of the same weight. This is why
   `<input class="v-control border-v-strong">` still resolves to
   `var(--border-control)`, and why `.v-switch--split` can override
   `inline-flex`. Do not reorder the two bundles.

The trap this pair replaces: a de-nested primitive at one class silently
losing to an older `.v-admin`-nested rule on the same element. The tell is a
1px type change nobody sees in review.

---

## 2. Tokens

Two layers, both in `tokens.css`. `--bl-*` is the Blotter vocabulary carried
verbatim from VentaSync so the two products read alike. Below it is Venta's
own bridge (`--surface`, `--accent`, `--border`, ...), which
`tailwind.config.js` maps to the `v-*` utility classes every view writes
(`bg-v-surface`, `text-v-accent`, `border-v-border`, `rounded-v-lg`).

**Bridge names are frozen. Only values move.** Repointing a bridge value
re-dresses every screen with no markup churn; renaming one breaks 105 views.

### Surfaces

| Bridge | Light | Dark | What it is |
|---|---|---|---|
| `--canvas` | `#EEF2F8` | `#0A0F17` | the tinted ground the page sits on |
| `--surface` | `#FFFFFF` | `#111823` | a raised sheet |
| `--surface-sunken` | `#F5F8FC` | `#0D131C` | a well inside a sheet |
| `--surface-hover` | `#F0F4FA` | `#1A2432` | the row and tile hover |
| `--border` | `#EAEEF5` | `#1E2836` | a divider |
| `--border-strong` | `#DFE5EE` | `#2C3850` | a louder divider, the panel head rule |
| `--border-control` | `#7F8A9F` | `#606F8B` | the edge of a control, and only that |
| `--text` | `#141B26` | `#EDF1F7` | body |
| `--text-secondary` | `#4A5468` | `#A7B2C4` | supporting |
| `--text-muted` | `#5F6B85` | `#8B96A9` | labels, notes, trails |

The ground is tinted so a white sheet reads as raised. There are exactly
**two elevations**: `--bl-lift` for a resting sheet, `--bl-lift-2` for
something deliberately louder or genuinely floating (menu, modal, filled
figure). A view does not get to invent a third.

`--border` and `--border-strong` are **dividers**: they separate two things
that are each visible on their own, so 1.16:1 and 1.27:1 are correct for them
and WCAG 1.4.11 does not apply. `--border-control` is the **component**: an
empty text field has no other evidence it exists, so its edge clears 3:1 on
every ground the admin has (light 3.10 to 3.48, dark 3.09 to 3.79).

### Colour has two jobs, and they never mix

**State: what something is doing.** `--warning` amber, `--danger` red,
`--info` blue, plus `--bl-good` green. State appears as a word plus a square
mark (`x-admin.badge`), as a wash (`--bl-attn-wash`), and never as a metric
block fill.

**Identity: which measure you are reading.** Six accents, cyan, violet,
amber, green, rose, indigo. A figure keeps its hue whether the news is good or
bad. Nothing goes red because it is low. The six are exposed as
`.v-stat--{name}`, `.v-slice-{0..5}` and `.v-stroke-{0..5}`.

Two accents carry a third stop each because one colour cannot do two jobs:
`--bl-a-amber-mark` for amber as a ring arc or swatch on a white sheet, and
the `--bl-a-*-fill` set for a filled block carrying white text. The fill stops
deliberately **do not flip** between themes: a saturated block brings its own
ground.

Brass is action. `--accent` (`--bl-brand-deep`) is every brass **word**;
`--accent-mark` (`--bl-brand`) is the mark, the fill and the active edge;
`--focus` is the ring alone, because the mark stop is only 2.76:1 on the
tinted ground and a focus indicator must clear 3:1 against what surrounds it.

### Material

```
--radius-sm   4px    the square dot
--radius-md   11px   a control
--radius-lg   16px   a panel
--radius-pill 999px

--row-h        34px  a table row
--control-h    32px  a control
--control-h-sm 28px  a small control, and the floor for a row action
--sidebar-w    216px the slab

--bl-page-gutter 30px (16px at <=700px)
--bl-pad         20px a panel's own padding
--bl-col-pad     20px a column's inset from a divider
--v-bleed        published BY `.v-panel__body` as its own inline
                 padding (20px, 16px at <=719px) and read by anything
                 inside the body that has to reach the sheet's inside
                 edge. Defaults to 0, which is right for a bar with no
                 sheet to reach the edge of.

--motion-fast 120ms cubic-bezier(.2,.7,.3,1)
--motion-base 220ms cubic-bezier(.2,.7,.3,1)
```

`prefers-reduced-motion` collapses both motion tokens to 1ms at the token
level, so a component gets it for free.

### Type

Archivo for interface, self-hosted, variable across weight 400 to 700 and
**width 62 to 125%**, which is what lets a column label run condensed and body
text run normal from one file. JetBrains Mono is **rationed** to identifiers
and measurements: SKUs, order references, tracking numbers, money, counts.
Monospace as a costume for "technical" is a refusal.

| Role | Size | Weight | Class |
|---|---|---|---|
| Page title | `clamp(25px, 3.4vw, 33px)`, `-0.025em`, balanced | 640 | `.v-day__title` |
| Standing line | 14px | 400 | `.v-day__standing` |
| Trail | 12px | 400 | `.v-day__trail` |
| Panel title | 13px | 650 | `.v-subhead` |
| Rail group heading | 12.5px | 650 | `.v-sidenav__label` |
| Body | 13px | 400 | default |
| Supporting | 12.5px | 400 | |
| Column label | 11.5px, `0.005em`, sentence case | 500 | `.v-label` |
| Figure value | 28px | 680 | `.v-stat__v` + `.v-num` |

Labels are **sentence case at normal width**. The wide-tracked uppercase
micro-cap was the single most dating detail in the build this replaced; the
only survivor is `.v-req` ("Required"), where the shout is the point.

---

## 3. The two shells

Every admin page renders inside `x-admin.shell` (or `x-admin.auth-shell` for
sign-in) and reaches `x-admin.page` for its head. Two workspace shells sit
between them and are **correct composition, not a bypass**:

- **`x-admin.settings-shell`** wraps `x-admin.workspace` over
  `App\Support\SettingsNav`, then `x-admin.page`. It takes no width
  prop: settings is one workspace, and every screen in it renders the
  same work column. The opt-in `wide` it used to take was applied by
  27 of 34 callers, so the seven that missed it sat 229px narrower than
  the screen the reader had just come from.
- **`x-admin.seo-shell`** does the same over the SEO registry.

Both normalise their own registry into the `groups` array
`x-admin.workspace` takes, which is what lets one component serve two
registries that disagree about how a destination names itself.

`x-admin.workspace` renders `.v-workspace` (rail plus work column, one flex,
one breakpoint), `.v-sidenav` (the rail, active state on
`aria-current="page"`), `.v-sidenav__back` and, below the breakpoint,
`.v-sidenav__picker` (the same list as a `<select>`).
`.v-workspace__main--narrow` caps the work column at a measure, which a column
of fields wants and a table does not.

### The page head

One primitive, `page-head.css`, rendered by one component, `x-admin.page`.

| Part | Class | What it is |
|---|---|---|
| Band | `.v-day__head` | the head, on the paper rather than inside a sheet |
| Trail | `.v-day__trail` | ancestors only, 12px, spanning both columns |
| Title | `.v-day__title` | the page's own name |
| Standing | `.v-day__standing` | one optional line, a **reading**, never an instruction |
| Actions | `.v-day__actions` | the page's action set, aligned with the title |
| Column | `.v-day` | the flow column, opt-in |

The head is a grid, `minmax(0,1fr) auto`, with the trail on its own row
spanning both columns, so the actions align with the **title** on every page
whether or not it has a trail. Below 640 the actions take their own row.

The trail is derived from the request path by `App\Support\Breadcrumbs` and
prints **ancestors only**; the page names itself in the `<h1>` underneath.
A chevron sits between ancestors and never after the last one. Pass
`:breadcrumb="[...]"` only for a trail a URL cannot encode, and
`:breadcrumb="[]"` to suppress it.

**Known gap:** `Breadcrumbs::forPath` returns nothing for a page mounted under
a registry section that is also a root-level item (`/dashboard/ui-kit`). Such
a page passes its own `:breadcrumb`.

`.v-day` is **not** applied automatically. A page that composes its own
vertical rhythm (the fulfillment queue) omits it and still gets the head's
16px clearance, because `.v-day__head` carries its own `margin-bottom` and
`.v-day > .v-day__head` zeroes it.

`.v-settle` staggers the first four children of whatever carries it. It is the
page's **one authored moment**; do not add a second entrance elsewhere.

---

## 4. The primitives

Every family below is one CSS file and, where it has a Blade face, one
component. All of them render in the UI kit.

### Container

| Class | Component | Notes |
|---|---|---|
| `.v-sheet` | | the material: `--surface`, `--border`, `--radius-lg`, `--bl-lift`. Says nothing about content |
| `.v-panel` | `x-admin.panel` | a sheet that knows what it holds. Clips, so a row hover is cut by its own corner |
| `.v-panel__head` | | 15/20/12 padding, a `.v-subhead` title, and a 1px `--border-strong` rule struck in from the left |
| `.v-panel__body` | | the gutter. `flush` drops it, for a table, list or feed reaching the edge |
| `.v-panel__foot`, `.v-panel__note`, `.v-panel__link`, `.v-panel__blank` | | |
| `.v-panel--open` | `x-admin.card` | does not clip, because a card holds fields and a field's hint bubble opens below it |

`x-admin.card` is the v2.0 prop API (`title`, `description`, `headerActions`,
`footer`) rendering the same panel. **No caller needs editing**; new work
should reach for `x-admin.panel`, which adds `note`, `link` and `flush`.

**A surface inside a surface is stripped in the layer.** A `.v-sheet` or
`.v-panel` with a `.v-sheet` or `.v-panel` ancestor drops its background,
its border, its radius and its elevation, because the plane it needed to
read as raised is already gone and all that is left is the doubled edge.
`x-admin.table` still has `bare` and it still means the same thing; it is
simply no longer the only thing standing between a page and a doubled
border, which is what sixteen settings routes proved it was. These four
selectors are the layer's only descendant rules, and they are context
rather than weight: a sheet cannot know from its own class list whether
something already painted the ground under it.

**Elevation comes from the primitives alone.** There are two lifts and five
things that may take one: `.v-sheet` and `.v-panel` rest at `--bl-lift`
(stated in `panel.css`, beside the rest of their material), `.v-modal` and
`.v-palette` float at `--bl-lift-2`, and `.v-menu` takes the resting lift
because it opens onto a page that is still fully lit. Nothing else is
raised. A view that wants a raised container says `v-sheet` or `v-panel`;
it does not assemble one out of utilities.

There used to be a shim in `base.css` that granted the resting lift to any
element carrying `bg-v-surface` and `rounded-v-lg` together. **It is
retired and must not be reintroduced.** It failed twice over. Two class
names could invent a third elevation, with no way for a view that pairs a
surface colour with the panel radius to say "and do not raise this". And
at `(0,3,0)` an attribute pair outranked the layer's own nesting: it beat
`.v-panel .v-sheet` at `(0,2,0)`, so a table inside a panel correctly lost
its ground, border and radius to the rule above and then kept its shadow
anyway, leaving a drop shadow under nothing that read as a second sheet
floating in front of the first. It also beat `.v-admin .v-modal`, quietly
demoting every modal in the admin to the resting lift.

The same rule applies to the elevation utilities: `shadow-v-lift` and
`shadow-v-lift-2` belong on the small floating idioms that have no
primitive (a chip over an image, a toast, an autocomplete list, the
primary button). A container that is a card, a dialog or a drawer takes
the class that says so.

`id` on a panel names the heading and points the `<section>` at it with
`aria-labelledby`, so a panel is announced by its own title.

### Measure

| Class | Component | Notes |
|---|---|---|
| `.v-stat`, `.v-stat--{accent}` | `x-admin.figure` | the filled measure block. `accent` is **identity**, guarded against unknown values |
| `.v-money`, `.v-money--zero` | `x-admin.money` | the mark travels with its digits, set smaller |
| `.v-band`, `.v-band--pulse`, `.v-bandblock` | | the comparison band |
| `.v-fig`, `.v-fig--head`, `.v-fig__name/__cell/__when/__plain` | | one row of the band |
| `.v-change--ahead/--behind/--level` | | the change word, with an arrow on the two directions and never colour alone |
| `.v-facts`, `.v-usual` | | readings as a strip and as one sentence |

There is no `x-admin.stat`. It was deleted: its `tone` prop was state where
the figure's `accent` is identity.

### Table

One implementation, `x-admin.table`, four density props, all default false.

| Prop | What it does |
|---|---|
| `ruled` | divides rows with a cell border instead of `divide-y`; **required** for rowspans |
| `roomy` | verification density, for rows carrying an image someone looks at |
| `tiered` | the two-tier grammar: `.v-tier` band above `.v-subrow` line items |
| `bare` | drops the sheet, for a table already inside a panel |

The table always sits in `.v-table-wrap` with `overflow-x: auto` carrying the
sheet's radius. **Tables never clip; they scroll.** A raw `<th>` inside
`.v-table` gets the column-label treatment for free; alignment and colour are
stated through `:where()` so `align="right"` and a colour utility still win.

Cell idioms: `.v-cellstack` (facts stacked), `.v-lead` and `.v-sub` (the lead
line and the identifier under it), `.v-ref` (an identifier that links),
`.v-qty`, `.v-amount` / `.v-amount__sub`, `.v-none` (a missing value),
`.v-quietlink`, and the column widths `.v-col-qty`, `.v-col-amount`,
`.v-col-act`.

Row actions go in `.v-rowactions` inside a `.v-col-act` cell, which drops its
own padding so 28px of button lands inside a 34px row. `.v-rowactions__danger`
recolours a ghost button. Measured floor across the admin: **28px high,
43.7px wide**.

### Navigation and control

| Class | Component | Notes |
|---|---|---|
| `.v-segbar`, `.v-segment`, `.v-segment__item`, `.v-segment__count` | `x-admin.segment`, `x-admin.segment.item` | the trough. State on `aria-selected` |
| | `x-admin.tabs` | the Alpine tab set, rendered on the segment bar, `?tab=` preserved |
| `.v-toolbar`, `.v-toolbar__row/__actions` | `x-admin.toolbar` | the command bar every index needs |
| `.v-filters`, `.v-filters__*` | `x-admin.filters` | |
| `.v-tally`, `.v-tally__item` | | a count strip |
| `.v-pager`, `.v-pager__count/__nav/__page` | `x-admin.pager` | stepped and numbered, one component |
| `.v-sidenav*` | `x-admin.workspace` | above |

Every selection control in the system keys its state on an **attribute**, not
a class, so the CSS and the assistive technology read the same thing:
`aria-selected` on segments, tabs and picker options, `aria-current="page"` on
the rail, `aria-checked` on a button-driven switch, `aria-pressed` on a tile.

### Form

`x-admin.field` is label, control, one prose line, error, in that order,
directly under the control the message belongs to. Never a summary at the top
of a form. The association contract is one convention: the control takes
`id="{name}"`, the prose is `{name}-hint`, the error is `{key}-error`, and the
controls point `aria-describedby` at them through `@aware`.

| Class | Component |
|---|---|
| `.v-control` (input, select, textarea) | `x-admin.input`, `x-admin.select`, `x-admin.textarea` |
| `.v-field__label`, `.v-field__hint`, `.v-field__error`, `.v-req` | `x-admin.field` |
| `.v-toggle`, `.v-toggle__track/__knob` | `x-admin.toggle` |
| `.v-switch`, `.v-switch__text`, `.v-switch--lead`, `.v-switch--top` | `x-admin.toggle label="..."` |
| `.v-toggle--btn` | `x-admin.switch` (a toggle that posts or submits) |
| `.v-secret`, `.v-secret__row/__box/__acts` | `x-admin.secret`; a stored key, masked and read only at rest, revealed by View, editable by Edit, and never printed outside the box that owns it |
| `.v-checklist` | a bounded scroll box of checkboxes |
| `.v-check`, `.v-check--bare` | `x-admin.checkbox`; the 24px target around a 16px box, square when no words widen it |
| `.v-picker`, `.v-picker__opt/__mid` | `admin/partials/dual-listbox` |
| `.v-form-actions` | `x-admin.form-actions`; stated in the layer, and it bleeds by `--v-bleed` rather than by a fixed number |
| `.v-nospin` | strips number-input spinners in a numeric grid |

**Every discrete control clears 24px, and the mark inside it does not
have to.** A checkbox stays 16px inside a 24px `.v-check`; the switch
stays a 36x20 pill inside a 24px label; the hint keeps a 16px ring and
a transparent 24px pad; a chip's remove cross keeps 12px and takes the
same pad. Growing the mark instead is the wrong repair: a 24px
checkbox in a 34px row shouts over the row it belongs to.

**Never wrap `x-admin.toggle` in your own `<label>`.** The component renders
its own, so an outer one nests two labels, which is invalid. Pass `label` and
the words carry their own `for`, making the two labels siblings pointing at
one input. One `.v-form-actions` per page: two sticky bars pinned to the same
edge read as one control that moved.

### Everything else

| Class | Component | Notes |
|---|---|---|
| `.v-btn` + `--primary/--secondary/--ghost/--danger`, `data-loading`, `:disabled` | `x-admin.button` | |
| `.v-badge` + six tones, `.v-badge__dot`, `.v-badge__label` | `x-admin.badge` | a square mark and a coloured word on an uncoloured background. `dot="false"` drops the mark, never the word |
| `.v-hint`, `.v-hint__btn`, `.v-hint__bubble`, `.v-hint--end` | `x-admin.hint` | the tooltip. 16px mark, 24px target, described by `aria-describedby` at all times |
| `.v-empty*` | `x-admin.empty-state` | stands **in place of** what is missing |
| `.v-notice*` | `x-admin.empty-state variant="notice"` | the blocking setup band, above what it blocks, carrying its own way out |
| `.v-flash*` | | something good already happened |
| `.v-slate*` | `x-admin.slate` | a record card: mark, name, state, one line, controls. `href` makes the whole card the target; a slate carrying controls renders as a `<section>`. `.v-slate__link` is a line inside one that is itself a destination |
| `.v-badgebtn`, `.v-badge__x` | | a badge that is the control flipping it, and a chip's remove control. Both keep their mark and take the 24px target |
| `.v-choice*` | `x-admin.choice`, `x-admin.choice.card` | any pick-one set, down or (with `cols`) up to four across. Four states, each carrying its own **word**: available, and three the `state` prop names. Two of those are inert `<div>`s; the third, "could not check", is greyed and **still a real button**, because something we could not reach has refused nothing. A card across is **1:1** and gives the mark every pixel its words have not claimed (198 square with four across at 1440, a 233x164 plate); one column drops to 4:3 so three options still fit a 390 screen without scrolling. `.v-choice__mark` is the plate a card wears: a logo bound to it by whoever owns the artwork, or `--drawn` (holding a `.v-choice__glyph`) for an option nobody ships one for. The plate states its own inset in percent, so a binding states artwork and ground and never a size. Decorative, so it is `aria-hidden`, and drained in all three unavailable states so a full-colour logo never reads as the pickable card. The words sit **under** the plate, never over it: text on the artwork needs a scrim, and a scrim heavy enough to carry the amber state word measured 2.49:1 on it in the light theme and erased the bottom third of the mark it sat on |
| `.v-line*`, `.v-item*`, `.v-auto*` | `x-admin.line`, `x-admin.item` | list-row idioms |
| `.v-feed`, `.v-entry*` | `x-admin.feed.entry` | activity and history |
| `.v-tiles`, `.v-tile*`, `.v-tray`, `.v-drop` | | a grid of things you look at rather than read. A tile carries no border and no elevation at rest |
| `.v-plot*`, `.v-plotcard` | `admin/partials/pulse-plot` | inline SVG on the tokens: no canvas, no library, no request, both themes free |
| `.v-ring*`, `.v-breakdown*`, `.v-rank*`, `.v-mix*`, `.v-chan*`, `.v-split*`, `.v-tsw` | | data displays |
| `.v-menu*`, `.v-drop*` | | |
| `.v-modal*` | `x-admin.modal`, `x-admin.confirm-dialog` | |
| `.v-palette*` | `x-admin.command-palette` | |
| `.v-label`, `.v-subhead`, `.v-mono`, `.v-num`, `.v-scroll`, `.v-sr` | | `base.css` helpers, also at one class |

Icons come from `x-admin.icon`: one 24 grid, 1.75 stroke, one path per name.
Add a name to the component rather than drawing an SVG in a view. No unicode
glyph ever stands in for an icon.

---

## 5. The accessibility floor

WCAG AA, held in **both themes**, verified by reading computed values in a
browser and never by sampling a screenshot.

**Text: 4.5:1.** Worst measured cases across the dashboard, the UI kit, an
index, a form, an order detail and a settings screen:

| | Light | Dark |
|---|---:|---:|
| Trail link | **4.76** | 6.43 |
| Column label (`.v-label`) | **4.76** | 5.97 |
| Notice title | 5.09 | 7.72 |
| Badge, danger | 5.20 | 6.10 |
| Field hint, chip sub, hint button | 5.35 | 5.97 |
| Badge, success | 5.35 | 8.05 |
| Standing line | 6.77 | 8.97 |
| Panel title, switch text | 17.29 | 15.72 |
| Page title | 15.39 | 16.94 |

Worst text case in the system: **4.76:1 light, 5.97:1 dark**.

**Non-text: 3:1.** Control edges 3.10 to 3.48 light and 3.09 to 3.79 dark; the
focus ring 3.29 to 3.70 light and higher in dark, and white on the slab at
10.20. The `--border`/`--border-strong` dividers sit at 1.16 and 1.27 and are
exempt: they reinforce a separation the layout already states.

**Colour never carries meaning alone.** A badge always prints its word beside
its mark. A change is a direction word plus an arrow. A ring segment has its
label and figure in the table beside it. A picker highlight is named by the
pane it sits in and by the arrow buttons.

**Target size: 24x24 minimum** for a discrete control. Row actions measure 28
high; the hint button keeps a 16px mark with a transparent 24px pad; the rail
back link carries `min-height: 24px`. Inline links inside a sentence or a
breadcrumb trail are the documented exception.

Plus: one `<h1>` per page (the page head's title), panels announced by
`aria-labelledby`, `role="dialog"` with `aria-modal` and a focus contract on
every overlay, no native `confirm()` or `alert()`, and `.v-sr` for text that
only assistive technology needs.

---

## 6. Conventions a contributor must follow

1. **No CSS in a Blade file.** No `<style>` block, and no static `style=`
   attribute. An Alpine `:style` binding carrying a record's own data (a
   layer's position, a slide's colour) is the one exception, because that
   value is data and cannot be named in a stylesheet.
2. **Block-form `@php` only.** An inline `@php(...)` placed before any
   `@php ... @endphp` block in the same file silently swallows the markup
   between them and 500s the page. `view:cache` does not catch it. If a file
   needs both, the block comes first.
3. **A Blade directive inside an attribute passed to a COMPONENT is
   never compiled.** `<x-admin.slate x-show="@js($x)">` reaches the
   browser as the literal source text and Alpine throws a SyntaxError
   on it; the settings hub shipped 21 of them and its search filtered
   nothing. Build the expression in PHP and bind it (`:x-show="..."`).
   A valueless directive has the same problem in reverse: `@click.stop`
   on a component renders as `@click.stop="@click.stop"`, so write
   `@click.stop=""`.
4. **No em dashes.** Interface copy, documentation, commit messages.
5. **No instructional interface text.** No help card, no paragraph under a
   control explaining the control. Name the control well. A genuinely
   non-obvious consequence goes in `x-admin.hint`; a single short line of
   consequence may go in `x-admin.field`'s `help`, which renders as visible
   prose, so keep it to one line. An empty state's `description` and a
   `.v-panel__blank` line are sanctioned: they say what is not there.
6. **Every copy button goes through `window.vCopy(text)`**
   (`resources/js/admin/admin.js`), which returns a promise for a
   boolean. `navigator.clipboard` exists only in a secure context and
   the admin is browsed over plain http on a LAN address as often as
   over https, so a bare `navigator.clipboard.writeText(...)` throws
   before it copies and Alpine swallows the error: six copy buttons
   shipped that way and every one of them did nothing, silently, on
   the owner's own machine. The helper tries the async API, falls back
   to a selection and `execCommand`, and when neither works says so in
   a toast. Flip a "Copied" label only on a true return; a button that
   could not copy must not claim it did.
7. **Colour never alone**, tables scroll, icons from the component.
8. **Both themes and 390 to 1440**, every time. Open the UI kit in both.
9. **Real data.** The kit's fixtures are the longest real NGD product title
   (103 characters), the longest real customer name (53), a nine-digit peso
   total, a zero and a 27,709. A primitive that has not met them is untested:
   the figure band's 390 layout collapsed under exactly that nine-digit
   figure and nothing else caught it.
10. **Any new primitive lands in the UI kit in the same commit**, in its real
    states, and gains a class-name assertion in `tests/Feature/AdminV2/UiKitTest.php`.

---

## 7. The gates

Run all four before committing anything that touches a view or the layer.

**Compiled-view lint.** The only thing that catches the `@php` fatal above:

```bash
php artisan view:clear && php artisan view:cache
for f in storage/framework/views/*.php; do
    php -l "$f" >/dev/null 2>&1 || echo "PARSE ERROR: $(tail -1 "$f")"
done
```

Silence is a pass. It compiles all 285 views.

**Build.** `npm run build` clean, then `php artisan view:clear`.

**Tests.** `php artisan test --testsuite=Feature --filter=AdminV2` is green
(569 passing). The full suite lands at 862 passed / 23 failed, which is the
pre-existing storefront-auth baseline. A test pinning a class name or a token
value gets **updated deliberately, never weakened**.

**Eyes.** The UI kit at 1440 light, 1440 dark and 390, plus the screen you
changed. Captures live in `.superpowers/design/shots/`.

---

## 8. Known exceptions

Each is deliberate, each is small, and each should shrink rather than grow.

- `resources/css/admin/components/day-sheet.css` (105 lines) holds what is
  genuinely only the dashboard's: its pulse-band titles, one money-alignment
  exception, the ledger's "Bought" clamp, and which ledger column that page
  can afford to drop when narrow.
- `extensions/courier-fulfillment/resources/css/fulfillment.css` (140 lines) is
  measurements of this module's own content and bindings to its own couriers:
  four column widths, the two widths its booking sheet takes, the marker on a
  manually arranged parcel, one inert field, and which artwork each courier's
  `.v-choice__mark` plate carries. "Courier" and "status" are the module's
  vocabulary, 170px is not a measurement this system has an opinion about, and
  neither is whose logo is whose. The tile those logos sit on, its size, its
  radius and how it greys are the layer's.
- `tinymce.css` overrides a third-party skin and needs `!important` to reach
  it.
- The automations edit modal is hand-rolled rather than `x-admin.modal`,
  because its body reads its own Alpine editor state. It carries `x-dialog`,
  so it has the same focus contract every modal has.
- `resources/views/auth/register.blade.php` and
  `resources/views/auth/verify-email.blade.php` are Breeze scaffolding on
  `<x-guest-layout>` with raw Tailwind greys and **no route reaches them**.
  They are not part of this system. Delete them with their controllers, or
  route and convert them; either is a decision.
