---
target: admin settings area
total_score: 21
max_score: 40
na_heuristics: 
p0_count: 1
p1_count: 2
timestamp: 2026-08-16T05-15-30Z
slug: resources-views-admin-settings
---
# Critique: Venta Admin Settings (resources/views/admin/settings)

Method: dual-agent. Score 21/40 (Acceptable). All 10 heuristics scored; none n/a.

## Heuristics
1 Visibility 2 (no dirty-state; one toast lies) | 2 Real world 3 | 3 User control 1 (no unsaved guard, no undo) | 4 Consistency 2 (violates own one-save-bar rule; secret handling inconsistent) | 5 Error prevention 1 (multi-form silent loss, 10 pages) | 6 Recognition 3 | 7 Flexibility 2 | 8 Minimalist 3 | 9 Recovery 1 (false-success toast, DB-verified) | 10 Help 3

## Priority issues
- [P0] Multi-form silent data loss, reproduced + DB-verified on localization/geo-zone-edit; pattern inventory: sections/mail (3 forms), sections/general, sections/checkout, sections/security, theme/index (8 tab forms in one DOM), shipping/dhl, email-templates/edit, stock-notifications/index, payments/partials/config-bank-transfer. Fix: one form per page for co-editable fields; global dirty-guard in admin.js (input -> dirty flag, beforeunload, cross-form submit confirm); non-navigating test buttons. Counter-examples already in codebase: shipping/weight-based (Save all methods), modules/seo/settings (one form, three cards).
- [P1] False success feedback: "1 entries saved" toast with 0 rows persisted; empty entry rows dropped server-side silently; loadZones() swallows failures. Validate rows; report actual persisted count.
- [P1] Test buttons navigate + test stale saved values (mail, dhl, email-templates, stock-notifications). Convert to fetch; test current form values.
- [P2] SMTP password plaintext in DOM (sections/mail.blade.php:26 :value binding); x-admin.secret exists and Turnstile uses it; missing autocomplete attrs.
- [P2] Un-confirmed high-stakes: theme Switch (live storefront, no confirm); Colors-tab Revert also reverts fonts.

## Detector
CLI: 3 findings, all overused-font, all false positives (TinyMCE email content_style). Browser: gpt-thin-border-wide-shadow 23-54/page = committed design-system lift idiom (false positive); undersized-ui-text real minors: "CL" avatar 10.5px (every page), "Required" 10px (x12 on theme).

## Persona highlights
Alex: theme tab-switch loses color edits on Save fonts; no Ctrl+S. Sam: lying toast announced as success; row-edit drops focus to body; 33/400-option unsearchable selects. Riley: duplicate-country entries unguarded; Alpine :selected-in-x-for select-sync gotcha on zone preselect.

## Minor
System sidebar menu (21 items) duplicates settings rail 1:1; Couriers&Tracking vs Fulfillment hub near-collision; Google Fonts CDN dead on LAN; nav label vs page title mismatch (Checkout & Stock / Checkout).
