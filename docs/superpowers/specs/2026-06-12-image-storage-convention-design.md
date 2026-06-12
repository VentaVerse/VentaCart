# Image Storage Convention — Design Spec

- **Date:** 2026-06-12
- **Status:** Draft for review
- **Scope:** Standalone Venta product (fresh installs) + this sandbox (newgearday dev). Other live installs (dragon-switch, production newgearday) are **out of scope** — the operator migrates those manually with the same command.

## 1. Problem

Venta inherited OpenCart's `catalog/` umbrella as a shared image namespace, but new code diverged:

- **ERP API** writes product images to `products/{brand-slug}/` (correct).
- **Admin product save** (`moveImagesToDir`) relocates them into `catalog/{brand-slug}/` and updates the DB row **only if the file move succeeds**. On re-save, `Storage::exists()` returns false (file already moved on a prior save), the DB update is silently skipped, and the row ends up pointing at `products/{brand}/…` while the file sits at `catalog/{brand}/…` → broken `<img>`.

Two halves with mismatched conventions (`products/` vs `catalog/`) glued by brittle, lossy relocation. `catalog/` is an OpenCart-era artifact that should not exist on a fresh Venta install.

## 2. Goals

1. Retire `catalog/` entirely. **Hard cutover, no backwards-compat layer.**
2. Entity-specific top-level folders, created lazily (no scaffolding on fresh install).
3. Uploads are **context-driven**: the entity being edited determines the destination — no folder picker in the normal flow, mass upload supported.
4. Preserve OpenCart's per-image flexibility: each image reference is an independent pointer; a product may reference images from any location. Auto-filing governs only where **new uploads land**, never where references may **point**.
5. Kill the silent-failure corruption.
6. One idempotent migration command to move existing files + rewrite DB rows in lockstep, and to heal already-corrupted rows.

## 3. Canonical folder convention

Under `storage/app/public/` (served at `/storage/…`), all **lazily created**:

| Entity | Folder | Notes |
|---|---|---|
| Product primary + gallery | `products/{brand-slug}/` | brand-level (matches API); brand-less → `products/_unbranded/` |
| Brand logos | `brands/` | flat |
| Category images | `categories/` | flat |
| Blog featured + inline | `blog/` | flat |
| Banner backgrounds | `banners/` | flat |
| Temp staging (pre-attach) | `_tmp/{token}/` | neutral root; swept by existing temp cleanup |

`catalog/` is **never written** after this change. A fresh install creates none of these folders until the first relevant upload.

**Out of scope / unchanged conventions:** `bank-qr/` (payment QR, already non-catalog), `stores/{id}/` (the separate `MediaController` library — left as-is, not folded in), theme logos/favicons.

## 4. Upload & manager model

### 4.1 Two distinct interactions
- **Upload (new files):** mass drag-drop directly on the entity being edited. Auto-filed to that entity's folder. **No folder decision, no navigation.** This is the 95% path.
- **Reuse (existing files):** a browse pane lets the operator pick an existing image from **any** folder. The DB row points there; the file is **not** moved. This preserves OpenCart's "image 1 from folder X, secondary from folder Y" capability without OpenCart's per-image navigation tedium.

### 4.2 Opening the manager on a product (EDIT)
- **Gallery (primary view):** the `product_images` rows for *this* product, ordered by `sort_order`. **Folder-independent** — always exactly this product's images, wherever the files sit.
- **Browse/add pane:** opens at `products/{brand-slug}/` (brand folder). New uploads/drops land here. Operator may navigate up to reuse images from elsewhere.
- **Granularity:** brand-level (`products/{brand}/`), matching the API. *Not* per-product subfolders.

### 4.3 Media page
Kept as a **scoped manage/reuse view** — entity-scoped tabs (Product images, Brand logos, …), search by name, find-unused → bulk delete. **Not** a raw `/public` file explorer and **not** a date-bucketed pool. It is never the default landing for uploads and never an ambiguous drop target.

## 5. Read side — no changes

`storage_url()` (and the JS `window.storageUrl`) simply prepend `/storage/` and URL-encode. **Prefix-agnostic** — no read-side code assumes `catalog/`. Moving paths cannot break rendering. The only `catalog/` read assumption is cosmetic folder-navigation JS in `admin/blog/form.blade.php:340`, updated in §6.

## 6. Code changes (write side only)

1. **`app/Http/Controllers/Admin/ProductController.php`** — delete `getCatalogDir()` (L549), `moveImagesToDir()` (L557), `moveFileToDir()` (L579) and the call site at L544–546. Admin save persists the upload path **as-is** (no relocation). This removes the silent-failure path entirely.

2. **`app/Http/Controllers/Admin/ProductImageController.php`** — make the uploader **root-aware** (see §7). Replace the `catalog/` / `catalog/{folder}` destinations (L34, 38, 91, 95) with `{root}/{subfolder}`. Temp staging `catalog/_tmp/{token}` → `_tmp/{token}`. Thumbnail naming (`{filename}_thumb.{ext}`, same dir) is unchanged. The **`browse`/list** endpoint (reuse pane) must also default to the caller's `root` (same allowlist) so editing a product browses `products/…`, not `catalog/…`.

3. **`app/Http/Controllers/Api/ErpProductController.php`** — image destinations already correct (`products/{brand-slug}/`, fallback `products/`). **Only change:** brand-less fallback `products/` → `products/_unbranded/` for consistency with the admin uploader. No API contract change — the ERP never sends a path; Venta derives it from the product's brand.

4. **Entity forms pass their root** to the shared uploader/picker:
   - `products/partials/image_manager.blade.php` → root `products`, subfolder = brand slug
   - `partials/single_image_picker.blade.php` (brands, categories) → root `brands` / `categories`
   - `blog/form.blade.php` → root `blog`; update the L340 `catalog/` prefix JS
   - banner form → root `banners`

5. **Verify no other `catalog/` writers/readers remain.** After the change, `git grep "'catalog/"` / `git grep '"catalog/'` must return matches **only inside `venta:migrate-image-paths`**.

## 7. Root-aware uploader contract

The uploader accepts:
- `root` — **server-side allowlisted** to `{products, brands, categories, blog, banners}`. Anything else → 422. (Prevents path traversal / arbitrary roots; the existing `folder` sanitization is insufficient as a security boundary for a new free parameter.)
- `folder` — optional subfolder, sanitized as today (`[^a-zA-Z0-9_\-\/]` stripped). For products = brand slug; empty for flat-root entities.
- `pim_token` — unchanged; routes to `_tmp/{token}/` staging.

Destination = `root` + (`folder` ? `/folder` : ''); brand-less product = `products/_unbranded`. Returns `{ ok, path, url }` exactly as today.

## 8. Migration command — `php artisan venta:migrate-image-paths`

### 8.1 Flags
- **default = dry-run** (`--pretend` implied). Prints planned moves + DB updates, changes nothing.
- `--execute` — perform changes. **Requires `--i-have-a-backup`**, else refuse with a message to back up the DB first.

### 8.2 Coverage (column-driven)
Each column maps `catalog/REST` → `{entity-root}/REST` (**reprefix-preserving-subpath** — collision-safe, predictable; does *not* re-derive by brand):

| Column | New prefix |
|---|---|
| `product_images.path` | `products/REST` |
| `products.image` | `products/REST` |
| `brands.image` | `brands/REST` |
| `categories.image` | `categories/REST` |
| `blog_posts.featured_image` | `blog/REST` |
| `banners.bg_image` | `banners/REST` |

(Migrated legacy subpaths are kept under the new root, e.g. `catalog/Mogami/x.jpg` → `products/Mogami/x.jpg`. New uploads use the flat/brand convention from §3. Both coexist under the root.)

### 8.3 Pre-flight (before any write)
- Destination roots writable.
- **Basename/destination collision** detection — two distinct sources mapping to the same destination → list and **abort**.
- **Cross-entity shared source** detection — the same physical `catalog/…` file referenced by columns mapping to different roots (e.g. a product image also used as a blog featured image). For such files: move once for the first owner, **copy** for the others (so all references resolve), and log a warning. (Move-once would break the other reference.)

### 8.4 Atomic per-row loop
For each row with a `catalog/…` (or already-corrupted) path:
1. Read current DB path.
2. Compute new path per the table above.
3. Resolve source: prefer the file at the DB path; **if missing, check the silent-failure case** (DB already says `products/{…}` but file is at `catalog/{…}`) and treat the legacy file as the source. If neither exists → log skip, continue.
4. `Storage::disk('public')->move(old, new)` (a rename on the same disk — fast).
5. On success, update the row's path **in the same iteration**. **Never touch the DB without a successful move.**
6. On failure → log, continue.
7. **Thumbnails:** when moving `foo.{ext}`, also move sibling `foo_thumb.{ext}` if present.

### 8.5 Properties
- **Heals the silent-failure damage** (rows where DB says `products/{brand}` but file is at `catalog/{brand}`) via step 3.
- **Idempotent** — re-run on a migrated/clean install finds nothing, exits 0.
- **Verbose summary:** counts per entity and per brand — moved / skipped (missing source) / failed / already-correct.
- **Cleanup:** after all moves, recursively remove **empty** `catalog/` subdirectories; skip any dir still containing files (defensive).

## 9. Sandbox migration plan

Code cutover and data migration are separate steps with different risk.

1. **Ship code cutover (§6)** — touches no data. Existing `catalog/` images still resolve (reads are prefix-agnostic); new uploads land in entity folders. Verify a fresh ERP push + admin save round-trip writes/persists `products/{brand}/` with no relocation.
2. **`venta:migrate-image-paths --pretend`** on the sandbox — review ~7,006 planned moves, confirm zero collisions.
3. **Back up** the sandbox DB (+ snapshot `storage/app/public/`).
4. **`--execute --i-have-a-backup`** — move files, rewrite rows in lockstep, empty `catalog/`.
5. **Verify** storefront galleries/thumbnails render; **re-run** → 0 moves (idempotent).

Sandbox footprint (newgearday dev DB) to migrate: `product_images.path` 5,074 · `products.image` 1,875 · `brands.image` 31 · `blog_posts.featured_image` 25 · `banners.bg_image` 1. (`categories.image` 0.)

## 10. Testing

- **Feature test — silent-failure repair:** seed a row where DB path = `products/{brand}/x.jpg` but the file is at `catalog/{brand}/x.jpg`; run `--execute`; assert file at canonical path and DB unchanged-but-correct.
- **Feature test — `--pretend`:** previews moves, makes zero filesystem/DB changes.
- **Feature test — idempotency:** second run reports 0 moves, exit 0.
- **Unit test — path mapping:** `catalog/REST` → `{root}/REST` per column; brand-less product → `products/_unbranded/`.
- **Feature test — root allowlist:** uploader rejects a non-allowlisted `root` (422).
- **Acceptance — round-trip:** ERP-push an image to a fresh product, then save in admin → DB path and file location match, no silent failure.

## 11. Acceptance criteria

- Fresh install: no `catalog/` folder ever created; all uploads land in entity folders.
- `git grep "'catalog/"` / `'"catalog/'` after the change → matches only inside `venta:migrate-image-paths`.
- ERP push and admin save both write `products/{brand-slug}/`; no silent-failure path exists.
- `--pretend` on the sandbox previews moves with no false positives.
- `--execute --i-have-a-backup` moves files, updates rows in lockstep, leaves zero `catalog/…` references in the DB, empties the `catalog/` tree.
- Re-run reports 0 moves, exits cleanly.
- Silent-failure repair covered by an automated test.

## 12. Assumptions / open items

- Blog featured-image folder = `blog/` (confirmed). Brand-less product fallback = `products/_unbranded/` (confirmed direction).
- The separate `MediaController` (`stores/{id}/`) stays independent — not folded into this convention.
- dragon-switch and production newgearday are migrated by the operator separately using the same command; not exercised here.
