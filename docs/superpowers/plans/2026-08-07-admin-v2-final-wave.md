# Admin v2 Final Wave Implementation Plan (Plan 4 of 4)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrate all 56 remaining old-layout views onto the admin-v2 shell, apply the Plan-3 carry-over fixes, redesign the Dashboard, reskin admin auth, then demolish the old layout — completing spec §4 (`docs/superpowers/specs/2026-08-06-admin-ux-rebuild-design.md`). After this plan, zero admin pages render the old design and `venta-v2` is merge-ready.

**Architecture:** Wave-based mechanical migration governed by the shared protocol file `docs/superpowers/plans/2026-08-07-admin-v2-reskin-protocol.md` — every wave task's implementer reads it first; it encodes the porting procedure, the toggle/checkbox and row-form patterns, the design-system grep gate, and the per-wave test contract (`tests/Feature/AdminV2/ReskinWaveTest.php`). Waves ordered: fixes → Sales → Catalog → Customers+Content → Marketing+Reviews → settings spokes (2 waves) → shipping child-forms + banners → Dashboard → auth + demolition → sweep.

**Tech Stack:** unchanged (Laravel 12, Blade + x-admin components, Alpine, Tailwind v3 v-* bridge, PHPUnit 11).

## Global Constraints

Everything in `docs/superpowers/plans/2026-08-07-admin-v2-reskin-protocol.md` binds every task below. Additional plan-level constraints:
- Suite baseline: 26 pre-existing failures; all `tests/Feature/AdminV2/` green after every task.
- Browser checks: port 8001 only (8000 is another app), dev@venta.test / venta_dev_2026, prefix `opensesame`.
- Commits: `feat(admin-v2): …` / `fix(admin-v2): …`, no Co-Authored-By.
- The old layout `resources/views/layouts/admin.blade.php` stays alive until Task 11 — earlier tasks must not touch it (views merely stop using it).

---

### Task 1: Plan-3 carry-over fixes

**Files:** `resources/views/admin/extensions/seo/entities.blade.php` (hardcoded URLs), `resources/views/components/admin/tabs.blade.php` + `tabs/tab.blade.php` (attribute merge + unknown-tab fallback), `app/Http/Controllers/Admin/PaymentSettingController.php` (dead data loading), `app/Http/Controllers/Admin/PaymentMethodPageController.php` + `PaymentSettingController.php` (shared FAMILIES constant → `app/Support/PaymentFamilies.php`), `resources/views/admin/payment-settings/partials/availability.blade.php` (Delete → "Delete rule" label), `app/Http/Controllers/Admin/Extensions/SeoExtensionController.php` (delete unreachable bulkAccept product branch).
**Test:** existing suites cover all touched surfaces (PaymentsPageTest, SeoPagesTest, TabsComponentTest).

- [ ] **Step 1:** Read `/tmp/claude-1000/-home-ubuntu-venta/7144000b-8761-49e5-8cfc-21e95e9ba080/scratchpad/p3-final-review-report.md` §Low findings + the ledger lines quoted in the task dispatch for exact locations. Fix all six:
  1. entities.blade.php: replace hardcoded `/opensesame/extensions/seo/...` accept/reject URLs with `route('admin.extensions.seo.accept', ...)` / `.reject` (verify signature via route:list).
  2. `tabs.blade.php`: add Alpine `init()` fallback — if `tab` matches no panel name, reset to the default (collect panel names via a `names` prop or `$refs` scan; simplest: pass the valid names: `@props(['default','param'=>'tab','names'=>null])` and in init `if (this.names && !this.names.includes(this.tab)) this.tab = @js($default);` — update the two consumers to pass `:names="['config','availability']"`).
  3. `tabs.blade.php` + `tabs/tab.blade.php`: `$attributes` → `$attributes->merge(['class' => …])`.
  4. `PaymentSettingController@index`: stop loading order statuses/bank accounts/decrypting HitPay keys for the landing (cards-only data).
  5. Extract the duplicated method-family list into `App\Support\PaymentFamilies::LIST` (or equivalent) consumed by both payment controllers.
  6. Availability delete button text → "Delete rule" + confirm-dialog message stating it removes ALL conditions for the method. Delete the unreachable `bulkAccept` product branch.
- [ ] **Step 2:** `php artisan view:clear && php artisan test --filter=AdminV2` — all green.
- [ ] **Step 3:** Commit `fix(admin-v2): plan-3 carry-over fixes`.

---

### Task 2: Sales wave — Orders

**Views:** `orders/index`, `orders/show` (read-only + status history + tracking per spec §"Order detail"), `orders/form` (Details + Products tabs — big, heavy Alpine for dynamic quoting).
**Protocol applies.** Gotchas: `orders/form` re-quotes shipping/payment on item & customer change (recent fix `6abf1fa`) — its Alpine/JS payloads must survive exactly; `orders/show` posts status changes (StockService side effects — do NOT alter payloads); index has filters + courier dropdown. Use `x-admin.tabs` for the form's tabs. Round-trip test: status change POST on show.

---

### Task 3: Sales wave — Abandoned Carts + Reviews

**Views:** `abandoned-carts/{index,show,notifications,recovered,rules,rule-form,templates,template-form,settings}`, `reviews/{index,show}`.
**Protocol applies.** Gotchas: abandoned-carts settings posts group `abandoned_cart` via its own controller (not settings.update) — keep; rules/templates are CRUD pairs; reviews/show has approve/reply actions. The settings page becomes visually consistent with hub sections (it's a hub destination) — wrap in `x-admin.settings-shell` is NOT possible (it's not a registry section); use plain shell + page with breadcrumb to Settings.

---

### Task 4: Catalog wave — Products

**Views:** `products/index`, `products/form` (the single biggest admin page — tabs, variant rows, image manager, specials, SEO fields, TinyMCE).
**Protocol applies.** Gotchas: keep the existing tab structure and ALL Alpine variant/special/image logic verbatim (class swaps only); `x-admin.tabs` only if it can wrap the existing tab switching without rewriting the Alpine — otherwise keep the old tab mechanism restyled (implementer's call, document it); index has search/filters/bulk + quick-create route. Round-trip test: product update with a variant payload (copy an existing passing pattern if one exists in tests/).

---

### Task 5: Catalog wave — Categories, Brands, Media, Tags

**Views:** `categories/{index,form}`, `brands/{index,form}`, `media/index`, `tags/{index,form}`.
**Protocol applies.** Gotchas: categories form has parent-tree select + TinyMCE; media/index is the uploader/browser — keep its JS payloads (chunked upload endpoints) intact; brands form has image upload (enctype).

---

### Task 6: Customers + Content wave

**Views:** `customers/{index,form}`, `customer-groups/{index,form}`, `pages/{index,form}`, `blog/{index,form}`, `blog-categories/{index,form}`.
**Protocol applies.** Gotchas: customers form includes the addressForm Alpine component (defined in theme.js — admin pages load admin.js/app.js; verify where the admin address editor's JS lives and keep whatever it references); blog/pages forms carry TinyMCE + SEO fields + slug inputs.

---

### Task 7: Marketing wave — Coupons + shipping child-forms + bank account

**Views:** `coupons/{index,form}`, `flat-rates/form`, `weight-based-methods/form`, `free-shipping-rules/{index,form}`, `payment-settings/edit-bank-account`.
**Protocol applies.** Gotchas: these shipping/payment child forms are reached FROM already-migrated v2 pages — their `_redirect`/return targets must keep pointing at the new parents; free-shipping-rules form has dual-listbox includes (port as-is like Plan 3 did); coupon form has date pickers + usage limits.

---

### Task 8: Settings spokes wave 1 — Localization, Users, Permissions, Email Templates

**Views:** `localization/{index,geo-zone-edit}`, `users/{index,form,permissions}`, `email-templates/{index,edit}`.
**Protocol applies.** Gotchas: localization/index is a multi-tab monster (countries/zones/geo-zones incl. the geo-zone CRUD Plan 3 preserved) — keep every form/payload, `x-admin.tabs` for its tabs; permissions page is a role×permission matrix (checkbox grid — form= pattern if rows need it); email-templates/edit has TinyMCE + variable reference + test-send.

---

### Task 9: Settings spokes wave 2 — Theme, Stock Alerts, Couriers, API Logs

**Views:** `theme/index` (large: theme switcher, colors/fonts, homepage sections, nav menus, footer, logo/favicon — many sub-forms), `stock-notifications/index`, `shipping-couriers/index`, `api-sync-logs/index`.
**Protocol applies.** Gotchas: theme page posts to many routes (theme.settings.update, sections.*, nav.*, footer.*, upload-logo/favicon multipart) — inventory ALL before rewriting; couriers index uses inline row CRUD (form= pattern); api-sync-logs has a destroy-all (confirm-dialog).

---

### Task 10: Dashboard redesign

**Views:** `dashboard.blade.php` (rewrite). Controller: `Admin\DashboardController` — additive queries permitted per protocol.
**Produces:** the showcase page, built last per spec §4: `x-admin.stat` row (today's revenue ₱ mono, orders today, pending count, low-stock count), recent orders `x-admin.table` (row-click to order, status badges), pending work card (reviews awaiting, SEO suggestions pending, abandoned carts recoverable — each linking to its page), automation health strip (failed automations surface with `danger` badge), storefront link card. Read the existing controller for what's already computed; add only what's missing. Both themes verified via screenshot in Task 12.

---

### Task 11: Admin auth reskin + demolition

**Files:** admin auth views (locate via `php artisan route:list --name=login` → `Auth\AuthenticatedSessionController` view + its layout — likely `resources/views/auth/login.blade.php` + `layouts/guest.blade.php`; reskin the ADMIN login experience onto tokens — a minimal `.v-admin`-scoped centered card; the customer-facing storefront auth is themed separately and must NOT change — verify which views serve which guard before touching anything, report if shared).
**Demolition (only after `grep -rl 'x-admin-layout' resources/ extensions/` returns EMPTY):** delete `resources/views/layouts/admin.blade.php`, `app/View/Components/AdminLayout.php` (verify path via grep), dead `SettingController::index()` method, unused Breeze admin components (verify each with grep before deleting: `nav-link`, `responsive-nav-link`, `dropdown-link` etc. — delete only zero-reference files), the old `layouts/navigation.blade.php` if unreferenced. Run the full AdminV2 suite + `php artisan view:clear && npm run build` after.
**Test:** ReskinWaveTest gains `test_old_admin_layout_is_gone()` asserting the layout file + component class no longer exist, and a login-page render test.

---

### Task 12: Final verification sweep

1. `php artisan test` — baseline 26 held; AdminV2 all green. `npm run build && php artisan view:clear && php artisan config:clear`.
2. Browser matrix (Playwright, port 8001, no destructive writes, restore anything flipped): every sidebar destination + settings hub card renders in the new shell; spot interaction checks — order status change UI, product form tabs + variant row add, media upload dialog opens, permissions matrix save, theme page section editor opens, dashboard links; ⌘K jumps to Products/Orders/a settings section; light + dark on dashboard/orders/products-form; 375px on dashboard, orders index, product form; console clean throughout. Screenshots (dashboard light+dark 1440, orders index, product form, 375 dashboard) → SDD workspace.
3. `git fetch origin && git merge origin/main --no-edit` + AdminV2 rerun.
4. Commit fixes as `fix(admin-v2): final wave verification fixes` or report clean.

---

## Self-review notes

- **Spec §4 coverage:** waves cover all 56 remaining old-layout views (inventoried 2026-08-07: sales 12, catalog 9, customers/content 10, marketing+child-forms 8, spokes 9, banners 4 — banners extension views fold into Task 7? NO: banners are in Task 7's list? Correction: banners extension views (4: index, editor, group-form, slides) belong to a wave — assigned to **Task 9** alongside other spokes (extension UI, reached from Modules). Task 9's implementer includes `extensions/banners/resources/views/{index,editor,group-form,slides}` with the same protocol; the banners editor is one of the heavier Alpine pages, port its JS intact.) Dashboard (T10), auth + demolition (T11), sweep (T12). Carry-overs (T1).
- **Placeholder scan:** wave tasks intentionally carry inventories + gotchas rather than full markup — sanctioned by the proven Plan-2-T4/Plan-3-T10 pattern where the protocol file + read-first rule replaced inline code; the protocol file is written and committed alongside this plan.
- **Consistency:** ReskinWaveTest named identically across tasks; protocol path referenced identically; Task 11's demolition gate (empty grep) prevents premature deletion.
