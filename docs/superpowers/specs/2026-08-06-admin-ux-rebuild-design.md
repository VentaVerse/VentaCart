# Venta Admin UX Rebuild — Design Spec

**Date:** 2026-08-06
**Branch:** `venta-v2`
**Scope:** Admin panel only. The storefront (all three themes) is untouched.

## Why

The current admin was assembled page by page with no design system: one 217-line
layout with the nav hardcoded inline, no admin component library (only Breeze
starter components), and 77 views / ~16k lines of hand-copied Tailwind utility
markup that drifted apart — uneven buttons, inconsistent spacing, generic
gray/indigo defaults. It reads as a prototype, not a product. Since Venta will
be sold (self-hosted OSS + hosted SaaS), the admin *is* the product surface;
the logic underneath is already built and stays.

This is a foundation rebuild, not a restyle: design tokens + a component
library first, then every page re-expressed on top of them.

## Decisions (settled during brainstorming)

| Decision | Choice | Rationale |
|---|---|---|
| Stack | Blade + Alpine + Tailwind v3 (unchanged) | The quality gap vs. references is the missing design-system layer, not the framework. Keeps all controller/route logic and the shared storefront build. No Livewire, no SPA. |
| Visual identity | **VentaSync family** — port the `ventasync-v2.1` token system from `/home/ubuntu/ventasync/preyes-erp` | Product-group consistency (Venta + VentaSync + ERP read as one suite); the system is already WCAG-audited with full light/dark parity. |
| Depth | Reskin all pages; genuinely redesign the pain screens (Payments+Restrictions, Shipping, Fulfillment, Modules, SEO) | Most CRUD pages have sound workflows; the named screens have structural UX problems. |
| Information architecture | Slim sidebar + searchable Settings hub | Principle: **sidebar = e-commerce operations; Settings hub = module + site configuration.** AgilaCP / VentaSync / Bagisto all converge on this pattern. |
| Build strategy | Big bang on `venta-v2`, merged when complete | Owner's call: one full reveal. Mitigation: weekly `main → venta-v2` merges to absorb drift in small doses. |
| HCI layer | Enforced by the component library | Feedback on every action, structurally — not per-page effort. |

Rejected: Livewire/Inertia rewrite (visual ceiling identical, cost and risk
higher); keeping the broad 24-item sidebar (the Extensions junk drawer grows
with every courier); a distinct "Midnight Console" identity (breaks family
consistency; would need its own contrast audit).

## 1. Foundation

### Tokens — `resources/css/admin/tokens.css`

Port of VentaSync v2 (`resources/css/base/tokens.css` in that repo): surfaces
(canvas / surface / surface-sunken / surface-hover), border + border-strong,
3-step text ramp, green accent (+hover/weak/text), danger/warning/info, spacing
scale (4–48px), radii 4/6/10px, motion timings (120ms/200ms cubic-bezier),
32px control/row heights, 216px sidebar width. Every token defined twice:
light on `.v-admin`, dark under `:root[data-theme="dark"] .v-admin`.

- Scope is a `.v-admin` class on the admin `<body>` — not `:root` — so admin
  tokens can never bleed into storefront pages sharing the Vite bundle.
- `data-theme` is set on `<html>` by a pre-paint inline script (no flash),
  persisted in `localStorage`, three-way: light / dark / system.
- The VentaSync contrast audit transfers with the values. Any **new** color
  must be checked against all four backgrounds (worst case: `surface-hover`)
  in both themes before entering this file, per the same method documented in
  the source file's audit comment.
- `prefers-reduced-motion` collapses motion tokens to 1ms.

### Tailwind bridge

Stay on Tailwind v3 (shared storefront build — no v4 migration risk).
`tailwind.config.js` maps token variables into the theme
(`colors: { canvas: 'var(--canvas)', surface: 'var(--surface)', accent: … }`,
plus spacing/radius/font entries), so components write
`bg-surface border-border text-secondary` and both themes work automatically.
Structural CSS (shell, tables, hub grid, palette) lives in dedicated files
under `resources/css/admin/` — **no CSS in Blade files, ever** (standing
project rule). New Vite entries: `resources/css/admin/admin.css` (imports
tokens + components) alongside the existing `app.css`.

### Fonts

Geist variable (UI) + JetBrains Mono (numerals) — woff2 files copied from the
VentaSync repo into `resources/fonts/`, self-hosted, `font-display: swap`.
All money, quantities, and IDs render in mono with `tabular-nums`.

### Component library — `resources/views/components/admin/` (`<x-admin.*>`)

| Group | Components |
|---|---|
| Layout | `shell` (sidebar+topbar+slot), `page` (title, breadcrumb, actions slot), `card` (header/footer slots) |
| Forms | `input`, `select`, `textarea`, `toggle`, `checkbox`, `radio-group`, `field` (label + help text + error wrapper), `form-actions` (sticky save bar) |
| Data | `table` (sortable headers, bulk-select, pagination footer, `overflow-x-auto`), `badge` (status tones), `stat`, `empty-state` (icon + explanation + primary action) |
| Feedback | `toast` stack, `modal`, `confirm-dialog`, `spinner`; buttons take a loading state |
| Navigation | `tabs`, `dropdown`, `command-palette`, settings `hub`, settings `section-shell` |

HCI contract enforced by construction:

- every input renders its own `@error` message beneath itself;
- every form submit disables its button and shows a spinner;
- every destructive action routes through `confirm-dialog`;
- every action outcome surfaces as a toast (session flash and AJAX both);
- every list has an `empty-state` that says what to do next;
- everything keyboard-reachable with visible focus rings.

### UI kit — `/admin/ui-kit`

Local-environment-only route rendering every component in both themes with all
variants/states. Built in stage 2 and kept current; it is the review gate
before any page migrates and the living documentation for future extension
work (LBC, SPX, third-party developers).

## 2. Shell & navigation

### Sidebar (216px, sunken surface, collapsible groups)

```
Dashboard
SALES        Orders · Fulfillment · Abandoned Carts · Reviews
CATALOG      Products · Categories · Brands · Media · Tags
CUSTOMERS    Customers · Customer Groups
CONTENT      Pages · Blog Posts · Blog Categories
MARKETING    Coupons · SEO
SYSTEM       Settings   → the hub
```

- Defined once in `config/admin-nav.php`: label, route, icon, permission,
  keywords. Sidebar, breadcrumbs, and command palette all render from this
  registry. Inline SVG icon set as a shared `x-admin.icon` component.
- Active item: `accent-weak` tint + accent text (the audited composite).
  Groups collapse; state persists in `localStorage`; active group auto-opens.
- Permission-filtered: destinations the admin can't access disappear.
- **Fulfillment** (new) is the operational booking queue — QuadX now, LBC/SPX
  later as tabs/filters within it, not separate sidebar entries. Courier
  *configuration* lives in the Settings hub.
- SEO in MARKETING is the content/suggestion queue the owner works daily; the
  engine's configuration lives in the hub.

### Topbar (44px, sticky)

Breadcrumb trail from the nav registry · global search opening the **⌘K
command palette** (jump to any page or setting via registry keywords; search
products / orders / customers by name or number) · "View store" link · theme
toggle · user menu.

### Mobile

Sidebar becomes an overlay drawer; hub grid collapses 3→2→1 columns; section
sub-sidebar becomes a `<select>`; forms single-column; tables scroll inside
their own container. Breakpoints exercised at 375 / 768 / 1440.

### Feedback

All session flash messages and AJAX outcomes render as toasts (top-right,
auto-dismiss, undo action where applicable). Inline banners are removed.

## 3. Settings hub & redesigned screens

### Hub — `/admin/settings`

Searchable card grid grouped as:

- **Store** — General · Localization · Theme
- **Sales channels** — Payments · Shipping · Fulfillment
- **Operations** — Order Statuses · Couriers & Tracking · Email Templates · Stock Alerts · Automation
- **Access & system** — Users & Permissions · API & Sync · Modules · SEO engine

Each card: icon, name, one-line description, hidden keyword synonyms ("gcash"
→ Payments, "smtp" → General/Mail). Driven by `config/settings-nav.php` —
same registry pattern as the sidebar, permission-filtered, live Alpine search
that hides empty groups and shows an empty state.

Opening a card lands in the **section shell**: sticky sub-sidebar of all
sections (current highlighted, "All Settings" back link), breadcrumb,
`max-w-2xl` form column inside a full-width card, sticky save bar.

The current General settings tabs (General / Currency / Checkout / Mail / API
/ Cron Jobs) become proper hub sections instead of tabs on one page — Mail and
Currency merge into General's section group; API moves to API & Sync; Cron
Jobs becomes Automation.

### Redesigned screens

**Payments** — methods + restrictions unified. Method list as cards (enable
toggle, drag order, inline label edit — labels keep the settings-fallback
pattern from `ab3a263`). Method detail: *Configuration* tab (accounts, QR
uploads for bank transfer) and *Availability* tab — the restrictions scoped to
that method, rendered as readable sentences ("Hide when: shipping country is
not Philippines"). Rule builder: condition family → operator → value with live
sentence preview. The standalone Payment Restrictions page is retired; the
`payment_method_restrictions` table and `PaymentRestrictionService` are
unchanged.

**Shipping** — Shipping Settings, Flat Rates, Weight-Based, Shipping Zones,
and Free Shipping Rules unify into one section whose provider list mirrors the
actual quote chain (FreeShipping → DPH → WeightBased → DHL → FlatRate). Each
provider is a card with its own config panel; chain order is visible with the
free-shipping-overrides-all rule stated where it applies. Includes a **rate
tester**: enter address + weight + total, see which methods quote, which wins,
and why (calls `ShippingQuoteService` server-side). Geo zone management stays
its own screen, reachable from providers that reference zones.

**Fulfillment (settings side)** — one card per courier extension (QuadX now;
LBC/SPX slot in later), each with credentials, staging/live toggle, and a
connection-test button. Follows the generic disableable extension pattern
established in the QuadX branch (commit `070550a`).

**Modules** — extension manager: card per module with icon, description,
version, enable toggle, and "Configure →" deep link to its settings section.
States: enabled / disabled / not configured.

**SEO** — working queue: suggestions with status (pending / applied /
dismissed), bulk approve, per-product diff ("current title → suggested
title"), and cron visibility (last run, next run). Engine configuration
(model, prompt scope) is a hub card under Access & system.

**Order Statuses** (light rework): list gains a flow strip showing each
status's `stock_action` (decrement / increment / none) so inventory side
effects are visible.

All other pages (~60): straight reskin — same fields, same flow, new
components. The products form is the largest single migration and keeps its
existing tab structure and Alpine variant logic.

## 4. Build order, testing, done

### Build order

1. Foundation: tokens, fonts, Tailwind bridge, `.v-admin` scope, theme toggle, ui-kit route
2. Component library, reviewed in the ui-kit in both themes before use
3. Shell: nav registry, sidebar, topbar, breadcrumbs, ⌘K palette, toasts, mobile drawer
4. Settings hub + section shell
5. The five redesigns (+ Order Statuses flow strip)
6. CRUD reskins in waves: Sales → Catalog → Customers → Content → Marketing → remaining settings spokes → Dashboard last
7. Demolition: old `layouts/admin.blade.php`, Breeze admin leftovers, dead files; admin auth pages reskinned to match
8. Final sweep: every page in light + dark at 375/768/1440, then merge

Throughout: weekly `main → venta-v2` merges; `npm run build` +
`php artisan view:clear` per batch.

### Testing

- Playwright per migrated page with the Claude dev account (never the owner's):
  load, create/edit/delete round-trip, validation errors, toasts, both themes,
  three viewports.
- Existing Laravel feature tests untouched for reskins (controllers/routes
  unchanged). The five redesigned screens change controllers (e.g. merged
  payments+restrictions) and get updated feature tests.
- New colors require the four-background contrast check before entering
  `tokens.css`.

### Definition of done

- All 77 admin views render through the new shell and `<x-admin.*>` components;
  old layout deleted; zero pages on the old system
- No CSS in Blade files; no hand-typed button/input/table markup outside the
  component library
- Settings hub covers every configuration destination; sidebar matches §2
- ⌘K palette reaches every registered destination
- Both themes and all three viewports pass the Playwright sweep
- `/admin/ui-kit` documents every component and variant

### Out of scope

Storefront themes; any controller/service/model change beyond the five
redesigned screens; multi-store; the theme-to-presets consolidation
(separate roadmap item); coupon system work.

## Reference material

- VentaSync v2 system (branch `ventasync-v2.1` at `/home/ubuntu/ventasync/preyes-erp`):
  `resources/css/base/tokens.css` (values + audit method), `layouts/shell.blade.php`,
  `resources/views/components/ui/*`, `resources/views/ui-kit-v2.blade.php`,
  `app/Support/Navigation.php`. Its legacy system (`layouts/app.blade.php`,
  `base/variables.css`) must not be copied.
- AgilaCP settings pattern (`/home/ubuntu/agilacp`): `config/settings-nav.php`,
  `app/Support/SettingsNav.php`, `components/settings/shell.blade.php`,
  `admin/settings/index.blade.php`.
- Bagisto configuration hub screenshot: `docs/Screenshot from 2026-08-06 15-54-43.png`.
- Brainstorm mockups: `.superpowers/brainstorm/57972-1786003469/content/`.
