# Theme Module & Storefront Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build the admin Theme Module and customer-facing storefront for a Laravel 12 e-commerce platform, replacing OpenCart's Journal theme.

**Architecture:** Settings table (key-value) + dedicated tables for homepage sections, nav items, footer columns. ThemeService caches all settings. CSS variables injected from DB. Storefront renders dynamically from config.

**Tech Stack:** Laravel 12, Blade, Tailwind CSS, Alpine.js, Vite, MySQL

**Design Doc:** `docs/plans/2026-03-06-theme-module-storefront-design.md`

---

## Phase 1: Foundation (Database + Models + ThemeService)

### Task 1: Create migrations for theme tables

**Files:**
- Create: `database/migrations/2026_03_06_200000_create_settings_table.php`
- Create: `database/migrations/2026_03_06_200001_create_homepage_sections_table.php`
- Create: `database/migrations/2026_03_06_200002_create_nav_menu_items_table.php`
- Create: `database/migrations/2026_03_06_200003_create_footer_columns_table.php`
- Create: `database/migrations/2026_03_06_200004_create_currencies_table.php`
- Create: `database/migrations/2026_03_06_200005_create_tags_tables.php`
- Create: `database/migrations/2026_03_06_200006_create_blog_tables.php`

**Tables:**
- `settings` — id, group, key, value (text), timestamps. Unique on (group, key).
- `homepage_sections` — id, type, title, config (json), sort_order, is_enabled (default true), timestamps.
- `nav_menu_items` — id, label, type, category_id (nullable FK), url (nullable), new_tab (default false), sort_order, is_enabled (default true).
- `footer_columns` — id, title, type, config (json), sort_order, is_enabled (default true).
- `currencies` — id, code (varchar 3), name, symbol (varchar 10), rate (decimal 12,6), is_default (default false), is_enabled (default true), updated_at.
- `tags` — id, name, slug (unique), timestamps.
- `product_tag` — product_id FK, tag_id FK, primary (product_id, tag_id).
- `blog_categories` — id, name, slug (unique), sort_order (default 0), timestamps.
- `blog_posts` — id, title, slug (unique), content (longtext), excerpt (text nullable), featured_image (nullable), blog_category_id (nullable FK), view_count (default 0), is_published (default false), published_at (nullable), meta_title (nullable), meta_description (text nullable), timestamps.
- `blog_post_tag` — blog_post_id FK, tag_id FK, primary (blog_post_id, tag_id).

**Step 1:** Create all migration files.
**Step 2:** Run `php artisan migrate`.
**Step 3:** Verify all tables created.

### Task 2: Create Eloquent models

**Files:**
- Create: `app/Models/Setting.php`
- Create: `app/Models/HomepageSection.php`
- Create: `app/Models/NavMenuItem.php`
- Create: `app/Models/FooterColumn.php`
- Create: `app/Models/Currency.php`
- Create: `app/Models/Tag.php`
- Create: `app/Models/BlogCategory.php`
- Create: `app/Models/BlogPost.php`
- Modify: `app/Models/Product.php` — add `tags()` BelongsToMany relationship

**Details:**
- `Setting`: fillable [group, key, value], no timestamps false, scope `scopeGroup($q, $group)`
- `HomepageSection`: fillable [type, title, config, sort_order, is_enabled], cast config as array, is_enabled as boolean
- `NavMenuItem`: fillable [label, type, category_id, url, new_tab, sort_order, is_enabled], cast new_tab/is_enabled as boolean, belongs to Category (nullable), no timestamps
- `FooterColumn`: fillable [title, type, config, sort_order, is_enabled], cast config as array, is_enabled as boolean, no timestamps
- `Currency`: fillable [code, name, symbol, rate, is_default, is_enabled], cast rate as decimal, booleans, timestamps false (only updated_at)
- `Tag`: fillable [name, slug], has `products()` BelongsToMany, has `blogPosts()` BelongsToMany
- `BlogCategory`: fillable [name, slug, sort_order], has `posts()` HasMany
- `BlogPost`: fillable [title, slug, content, excerpt, featured_image, blog_category_id, view_count, is_published, published_at, meta_title, meta_description], cast is_published boolean, published_at datetime, belongs to BlogCategory, has `tags()` BelongsToMany via blog_post_tag
- Product: add `tags()` return `$this->belongsToMany(Tag::class, 'product_tag')`

### Task 3: Create ThemeService

**Files:**
- Create: `app/Services/ThemeService.php`
- Create: `app/Providers/ThemeServiceProvider.php`
- Modify: `bootstrap/providers.php` — register ThemeServiceProvider

**ThemeService:**
- Constructor loads all settings from DB, caches as nested array `[$group][$key] = $value`
- `get($key, $default = null)` — dot notation e.g. `get('colors.primary')`
- `getGroup($group)` — returns all settings in a group as array
- `set($group, $key, $value)` — update or insert setting
- `setMany($group, $data)` — bulk set for a group
- `clearCache()` — forget cache, reload from DB
- Uses `Cache::rememberForever('theme_settings', ...)` with manual invalidation
- Also loads and caches: homepage sections, nav items, footer columns, currencies
- `getHomepageSections()`, `getNavItems()`, `getFooterColumns()`, `getActiveCurrencies()`

**ThemeServiceProvider:**
- Singleton binding for ThemeService
- Register `theme()` global helper function
- Share theme data with all storefront views via View Composer

### Task 4: Create default settings seeder

**Files:**
- Create: `database/seeders/ThemeSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php` — add ThemeSeeder

**Seeds default settings for NGD-style store:**
- identity: site_name "New Gear Day", logo null, favicon null
- colors: primary "#E2293D", secondary "#0F3A8D", nav_bg "#0F3A8D", nav_text "#FFFFFF", footer_bg "#1E293B", footer_text "#FFFFFF", announcement_bg "#E2293D", announcement_text "#FFFFFF"
- fonts: heading "Oswald", body "Roboto Slab"
- header: announcement_enabled false, announcement_text "", announcement_link "", topbar_links JSON [{"label":"Delivery & Payment","url":"/delivery"},{"label":"FAQ","url":"/faq"}]
- badges: new_count "10", hot_count "5"
- filters: price_enabled "1", availability_enabled "1", brand_enabled "1", rating_enabled "0", on_sale_enabled "1", tags_enabled "1"
- product_page: show_ratings "1", show_stock "1", show_manufacturer "1", show_badges "1", thumbnail_position "left", below_modules JSON ["related","you_might_like","same_brand","related_articles"]
- category_page: products_per_page "12", columns_mobile "2", columns_tablet "3", columns_desktop "4", default_sort "default"
- blog: posts_per_page "12", default_view "grid", excerpt_length "150", show_view_count "1", show_related_articles "1"

**Seeds sample homepage sections:**
1. banner (Hero Slider) — sort 1, enabled
2. brands (Our Brands) — sort 2, enabled
3. products (New Arrivals, filter: new, limit 12) — sort 3, enabled
4. products (On Sale, filter: specials, limit 6) — sort 4, enabled

**Seeds sample nav items:**
- Guitars (category type, sort 1)
- Effects Pedals (category type, sort 2)
- Blog (custom type, url /blog, sort 3)

**Seeds sample footer columns:**
1. Information (links type) — About Us, Privacy Policy, Terms
2. Customer Service (links type) — Contact Us, Returns, Sitemap
3. Follow Us (social type) — Facebook, Instagram

**Seeds default currency:**
- PHP, Philippine Peso, ₱, rate 1.000000, is_default true, is_enabled true

**Step:** Run `php artisan db:seed --class=ThemeSeeder`

---

## Phase 2: Admin Theme Module

### Task 5: Theme admin controller and routes

**Files:**
- Create: `app/Http/Controllers/Admin/ThemeController.php`
- Modify: `routes/web.php` — add theme routes

**ThemeController methods:**
- `index()` — loads all settings, homepage sections, nav items, footer columns, currencies. Returns tabbed view.
- `updateSettings(Request $request)` — validates and bulk-saves settings by group. Clears ThemeService cache.
- `uploadLogo(Request $request)` / `uploadFavicon(Request $request)` — image upload, saves path to settings.

**Routes (inside admin auth group):**
```
Route::get('/theme', [ThemeController::class, 'index'])->name('theme.index');
Route::post('/theme/settings', [ThemeController::class, 'updateSettings'])->name('theme.settings.update');
Route::post('/theme/upload-logo', [ThemeController::class, 'uploadLogo'])->name('theme.upload-logo');
Route::post('/theme/upload-favicon', [ThemeController::class, 'uploadFavicon'])->name('theme.upload-favicon');
```

### Task 6: Homepage sections admin CRUD

**Files:**
- Create: `app/Http/Controllers/Admin/HomepageSectionController.php`
- Modify: `routes/web.php` — add section routes

**Methods:**
- `store(Request $request)` — create section with type, title, default config
- `update(Request $request, HomepageSection $section)` — update title, config, is_enabled
- `destroy(HomepageSection $section)` — delete
- `reorder(Request $request)` — accepts array of {id, sort_order}, bulk updates

**Routes:**
```
Route::post('/theme/sections', ...)->name('theme.sections.store');
Route::put('/theme/sections/{section}', ...)->name('theme.sections.update');
Route::delete('/theme/sections/{section}', ...)->name('theme.sections.destroy');
Route::post('/theme/sections/reorder', ...)->name('theme.sections.reorder');
```

### Task 7: Nav menu items admin CRUD

**Files:**
- Create: `app/Http/Controllers/Admin/NavMenuController.php`
- Modify: `routes/web.php`

**Methods:** store, update, destroy, reorder (same pattern as sections)

**Routes:**
```
Route::post('/theme/nav', ...)->name('theme.nav.store');
Route::put('/theme/nav/{item}', ...)->name('theme.nav.update');
Route::delete('/theme/nav/{item}', ...)->name('theme.nav.destroy');
Route::post('/theme/nav/reorder', ...)->name('theme.nav.reorder');
```

### Task 8: Footer columns admin CRUD

**Files:**
- Create: `app/Http/Controllers/Admin/FooterColumnController.php`
- Modify: `routes/web.php`

**Same CRUD + reorder pattern.**

### Task 9: Currency admin controller

**Files:**
- Create: `app/Http/Controllers/Admin/CurrencyController.php`
- Create: `app/Services/CurrencyService.php`
- Modify: `routes/web.php`

**CurrencyService:**
- `fetchEcbRates()` — fetches XML from ECB, parses rates, returns array of code => rate
- `updateRates()` — fetches ECB, updates all enabled currencies in DB
- Rates are relative to EUR; convert to PHP-relative by dividing all rates by PHP rate

**CurrencyController methods:**
- `store(Request $request)` — add currency
- `update(Request $request, Currency $currency)` — edit
- `destroy(Currency $currency)` — delete (can't delete default)
- `fetchRates()` — triggers ECB fetch, updates DB, returns JSON result

**Routes:**
```
Route::post('/theme/currencies', ...)->name('theme.currencies.store');
Route::put('/theme/currencies/{currency}', ...)->name('theme.currencies.update');
Route::delete('/theme/currencies/{currency}', ...)->name('theme.currencies.destroy');
Route::post('/theme/currencies/fetch-rates', ...)->name('theme.currencies.fetch-rates');
```

### Task 10: Admin theme view — tabbed interface

**Files:**
- Create: `resources/views/admin/theme/index.blade.php`
- Create: `resources/css/admin-theme.css` (if needed for color pickers, etc.)

**View structure:**
- Uses `<x-admin-layout>` with header "Theme Settings"
- Alpine.js `x-data` with `activeTab` state
- Tab navigation: Identity, Colors, Fonts, Header, Navigation, Homepage, Product Page, Category Page, Badges, Footer, Currency, Blog
- Each tab is a form section that POSTs to `theme.settings.update` with the group name
- Homepage tab has section list with add/edit/delete/reorder UI
- Navigation tab has menu item builder
- Footer tab has column manager
- Currency tab has currency list + "Fetch ECB Rates" button
- Color inputs use `type="color"` HTML5 pickers
- Font inputs use dropdowns with Google Fonts options

### Task 11: Update admin sidebar with Theme link

**Files:**
- Modify: `resources/views/layouts/admin.blade.php` — add Theme nav item before Settings, pointing to `admin.theme.index`

---

## Phase 3: Storefront Layout & Vite Setup

### Task 12: Storefront Vite entry points and Tailwind config

**Files:**
- Modify: `vite.config.js` — add storefront CSS/JS entry points
- Modify: `tailwind.config.js` — add storefront font families and brand colors
- Create: `resources/css/storefront.css` — storefront-specific styles using CSS variables
- Create: `resources/js/storefront.js` — Alpine.js + storefront components

**Tailwind additions:**
- fontFamily: heading (Oswald), body (Roboto Slab)
- colors: brand-red, brand-navy and their variants

**storefront.css:**
- Import Google Fonts (Oswald, Roboto Slab — but these will be dynamic later from settings)
- Tailwind directives
- CSS variable declarations read from inline `<style>` in layout
- Product card styles, badge styles, carousel styles, filter styles

**storefront.js:**
- Import Alpine.js
- Register storefront Alpine components: imageSlider, productCarousel, categoryFilters, currencySelector

### Task 13: Storefront layout component

**Files:**
- Create: `app/View/Components/StoreLayout.php`
- Create: `resources/views/storefront/layouts/store.blade.php`

**StoreLayout component:**
- Injects ThemeService
- Passes to view: settings, navItems, footerColumns, currencies

**store.blade.php structure:**
```
<!DOCTYPE html>
<head>
  - Dynamic title from settings
  - Favicon from settings
  - CSS variables injected from ThemeService colors/fonts
  - @vite storefront assets
  - Google Fonts link built from font settings
</head>
<body>
  - Announcement bar (if enabled)
  - Top bar (links from settings)
  - Header (logo, search, currency selector, cart)
  - Nav bar (from nav_menu_items)
  - {{ $slot }}
  - Footer (from footer_columns)
  - @stack('scripts')
</body>
```

---

## Phase 4: Storefront Pages

### Task 14: Homepage controller and view

**Files:**
- Create: `app/Http/Controllers/Storefront/HomeController.php`
- Create: `resources/views/storefront/home.blade.php`
- Modify: `routes/web.php` — add storefront routes (must not conflict with admin)

**HomeController:**
- `index()` — loads enabled homepage sections ordered by sort_order. For each section, preloads the data it needs (products for product sections, manufacturers for brands, etc.). Returns view.

**Route:** The storefront needs its own route group. Since admin is at `/admin`, storefront is at `/`. But currently `/` redirects to admin. Change: storefront at root, admin stays at `/admin`.

```
// Storefront routes (public)
Route::get('/', [Storefront\HomeController::class, 'index'])->name('home');
```

**home.blade.php:**
- Uses `<x-store-layout>`
- Loops through sections, includes partial per type

### Task 15: Homepage section partials

**Files:**
- Create: `resources/views/storefront/sections/banner.blade.php`
- Create: `resources/views/storefront/sections/products.blade.php`
- Create: `resources/views/storefront/sections/brands.blade.php`
- Create: `resources/views/storefront/sections/category_tabs.blade.php`
- Create: `resources/views/storefront/sections/text_block.blade.php`
- Create: `resources/views/storefront/sections/blog_posts.blade.php`
- Create: `resources/views/storefront/partials/product-card.blade.php`

**banner.blade.php:** Alpine.js image slider with autoplay, configurable width
**products.blade.php:** Product grid/carousel with responsive columns, uses product-card partial
**brands.blade.php:** Scrolling manufacturer logo carousel
**category_tabs.blade.php:** Tabbed product display, Alpine.js tab switching with AJAX or pre-loaded
**text_block.blade.php:** Simple title + content with alignment
**blog_posts.blade.php:** Recent blog post cards
**product-card.blade.php:** Reusable product card — image, badges (NEW/HOT/Sale%), name, price (with slashing), star rating placeholder

### Task 16: Category listing page

**Files:**
- Create: `app/Http/Controllers/Storefront/CategoryController.php`
- Create: `resources/views/storefront/category/index.blade.php`
- Create: `resources/views/storefront/partials/sidebar-filters.blade.php`
- Modify: `routes/web.php`

**CategoryController:**
- `show(Category $category)` — loads products with filters (price range, brand, availability, tags, on_sale), sorting, pagination. Loads filter options (brands in category, price min/max, tags). Returns view with sidebar + product grid.

**Route:** `Route::get('/category/{category:slug}', ...)->name('category.show');`

**Filters use query params:** `?min_price=&max_price=&brand[]=&sort=&view=grid`

**sidebar-filters.blade.php:** Alpine.js driven, reads enabled filters from ThemeService, price range slider, checkbox groups for brands/availability/tags

### Task 17: Product detail page

**Files:**
- Create: `app/Http/Controllers/Storefront/ProductController.php`
- Create: `resources/views/storefront/product/show.blade.php`
- Modify: `routes/web.php`

**ProductController:**
- `show(Product $product)` — loads product with images, variants, specials, manufacturer, categories, tags. Loads below-product modules (related products, same brand, etc.) based on theme settings. Increments view count. Returns view.

**Route:** `Route::get('/product/{product:slug}', ...)->name('product.show');`

**show.blade.php:**
- Image gallery with thumbnail strip (Alpine.js, position from settings)
- Product info section: title, badges, price (with sale slashing + countdown), stock, variant selector, qty + Add to Cart button
- Manufacturer logo
- Description tab
- Below-product carousels (configurable from settings)

### Task 18: Blog pages

**Files:**
- Create: `app/Http/Controllers/Storefront/BlogController.php`
- Create: `resources/views/storefront/blog/index.blade.php`
- Create: `resources/views/storefront/blog/show.blade.php`
- Create: `resources/views/storefront/blog/partials/sidebar.blade.php`
- Modify: `routes/web.php`

**BlogController:**
- `index(Request $request)` — lists published posts with category/tag filtering, pagination, grid/list toggle
- `show(BlogPost $post)` — single post, increments view_count, loads related posts

**Routes:**
```
Route::get('/blog', ...)->name('blog.index');
Route::get('/blog/{post:slug}', ...)->name('blog.show');
```

---

## Phase 5: Blog Admin CRUD

### Task 19: Blog admin controllers

**Files:**
- Create: `app/Http/Controllers/Admin/BlogController.php`
- Create: `app/Http/Controllers/Admin/BlogCategoryController.php`
- Create: `resources/views/admin/blog/index.blade.php`
- Create: `resources/views/admin/blog/form.blade.php`
- Create: `resources/views/admin/blog-categories/index.blade.php`
- Create: `resources/views/admin/blog-categories/form.blade.php`
- Modify: `routes/web.php`
- Modify: `resources/views/layouts/admin.blade.php` — add Blog to sidebar

**BlogController:** Standard CRUD — index (with search, filter by category/status, pagination), create, store, edit, update, destroy.
**BlogCategoryController:** Simple CRUD for blog categories.

**Routes:**
```
Route::resource('blog', Admin\BlogController::class)->except(['show']);
Route::resource('blog-categories', Admin\BlogCategoryController::class)->except(['show']);
```

---

## Phase 6: Tags Admin + Badge System

### Task 20: Tags management

**Files:**
- Create: `app/Http/Controllers/Admin/TagController.php`
- Create: `resources/views/admin/tags/index.blade.php`
- Create: `resources/views/admin/tags/form.blade.php`
- Modify: `resources/views/admin/products/form.blade.php` — add tags input (comma-separated or multi-select)
- Modify: `app/Http/Controllers/Admin/ProductController.php` — sync tags on save
- Modify: `routes/web.php`
- Modify: `resources/views/layouts/admin.blade.php` — add Tags to sidebar

### Task 21: Badge system

**Files:**
- Create: `database/migrations/2026_03_06_200007_create_custom_badges_table.php`
- Create: `app/Models/CustomBadge.php`
- Create: `app/Services/BadgeService.php`

**custom_badges table:** id, label, color, text_color, type (manufacturer/category/product), target_id, is_enabled, sort_order

**BadgeService:**
- `getBadgesForProduct(Product $product)` — returns array of badge objects
- Checks: NEW (is product in last X by created_at), HOT (is product in top X by order count), Sale (has active special with display_mode=sale, includes % and countdown), Custom (any custom badges assigned to this product/its category/its manufacturer)
- Cached per product, invalidated on relevant changes

---

## Phase 7: Currency System

### Task 22: ECB currency service and scheduled updates

**Files:**
- Modify: `app/Services/CurrencyService.php` (created in Task 9)
- Create: `app/Console/Commands/UpdateCurrencyRates.php`
- Modify: `routes/console.php` — schedule daily rate update

**CurrencyService.fetchEcbRates():**
- GET `https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml`
- Parse XML, extract currency codes and rates
- Convert to PHP-relative (divide each rate by PHP's EUR rate)
- Update enabled currencies in DB

**Artisan command:** `php artisan currency:update` — calls CurrencyService::updateRates()
**Schedule:** Daily at 4pm (ECB publishes ~2:15pm CET)

---

## Build & Verify After Each Phase

After each phase:
1. `php artisan migrate` (if new migrations)
2. `npm run build`
3. `php artisan view:clear`
4. `php artisan config:clear`
5. Manual verification in browser

---

## Commit Strategy

Commit after each task or logical group of tasks:
- "feat: add theme database tables and models"
- "feat: add ThemeService with caching"
- "feat: add theme admin controller and tabbed UI"
- "feat: add homepage section CRUD"
- "feat: add storefront layout with dynamic theming"
- "feat: add homepage with dynamic sections"
- "feat: add category listing with sidebar filters"
- "feat: add product detail page"
- "feat: add blog system (admin + storefront)"
- "feat: add tags and badge system"
- "feat: add currency system with ECB rates"
