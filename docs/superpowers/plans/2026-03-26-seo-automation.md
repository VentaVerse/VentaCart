# SEO Automation Extension — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build an Extensions > SEO admin section that uses Claude AI + Google Search Console to generate, review, and apply SEO meta tags across products, categories, brands, and blog posts.

**Architecture:** New controller under `Admin/Extensions/`, refactored `SeoGeneratorService` supporting polymorphic entity types, new migration adding `entity_type`/`entity_id` to suggestions table and `og_title`/`og_description` to all content tables. Existing old SEO routes kept as-is during transition.

**Tech Stack:** Laravel 12, Alpine.js (inline), Claude API (Sonnet 4.5), Google Search Console API

---

### Task 1: Database Migration — Add OG Fields and Polymorphic Suggestions

**Files:**
- Create: `database/migrations/2026_03_26_100001_extend_seo_for_all_entities.php`
- Modify: `app/Models/SeoSuggestion.php`
- Modify: `app/Models/Product.php`
- Modify: `app/Models/Category.php`
- Modify: `app/Models/Brand.php`
- Modify: `app/Models/BlogPost.php`

- [ ] **Step 1: Create migration**

```php
<?php
// database/migrations/2026_03_26_100001_extend_seo_for_all_entities.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Add polymorphic columns to seo_suggestions
        Schema::table('seo_suggestions', function (Blueprint $table) {
            $table->string('entity_type', 50)->default('product')->after('id');
            $table->unsignedBigInteger('entity_id')->nullable()->after('entity_type');
            $table->string('suggested_og_title', 255)->nullable()->after('suggested_meta_keywords');
            $table->string('suggested_og_description', 500)->nullable()->after('suggested_og_title');
            $table->string('current_og_title', 255)->nullable()->after('suggested_og_description');
            $table->string('current_og_description', 500)->nullable()->after('current_og_title');
            $table->index(['entity_type', 'entity_id', 'status']);
        });

        // Backfill entity_id from product_id
        DB::statement('UPDATE ' . DB::getTablePrefix() . 'seo_suggestions SET entity_id = product_id WHERE product_id IS NOT NULL');

        // Add OG fields to products
        Schema::table('products', function (Blueprint $table) {
            $table->string('og_title', 255)->nullable()->after('meta_keywords');
            $table->string('og_description', 500)->nullable()->after('og_title');
        });

        // Add OG fields to categories
        Schema::table('categories', function (Blueprint $table) {
            $table->string('og_title', 255)->nullable()->after('meta_keywords');
            $table->string('og_description', 500)->nullable()->after('og_title');
        });

        // Add OG fields to brands
        Schema::table('brands', function (Blueprint $table) {
            $table->string('og_title', 255)->nullable()->after('meta_keywords');
            $table->string('og_description', 500)->nullable()->after('og_title');
        });

        // Add OG fields to blog_posts
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->string('og_title', 255)->nullable()->after('meta_description');
            $table->string('og_description', 500)->nullable()->after('og_title');
        });
    }

    public function down(): void
    {
        Schema::table('seo_suggestions', function (Blueprint $table) {
            $table->dropIndex(['entity_type', 'entity_id', 'status']);
            $table->dropColumn(['entity_type', 'entity_id', 'suggested_og_title', 'suggested_og_description', 'current_og_title', 'current_og_description']);
        });
        Schema::table('products', function (Blueprint $table) { $table->dropColumn(['og_title', 'og_description']); });
        Schema::table('categories', function (Blueprint $table) { $table->dropColumn(['og_title', 'og_description']); });
        Schema::table('brands', function (Blueprint $table) { $table->dropColumn(['og_title', 'og_description']); });
        Schema::table('blog_posts', function (Blueprint $table) { $table->dropColumn(['og_title', 'og_description']); });
    }
};
```

- [ ] **Step 2: Run migration**

Run: `php artisan migrate`
Expected: Migration runs successfully, no errors.

- [ ] **Step 3: Update SeoSuggestion model**

Add new fields to fillable and add polymorphic relationship in `app/Models/SeoSuggestion.php`:

```php
protected $fillable = [
    'entity_type', 'entity_id',
    'product_id', // keep for backward compat
    'current_meta_title', 'suggested_meta_title',
    'current_meta_description', 'suggested_meta_description',
    'current_meta_keywords', 'suggested_meta_keywords',
    'current_og_title', 'suggested_og_title',
    'current_og_description', 'suggested_og_description',
    'status', 'generated_at',
];

public function entity(): \Illuminate\Database\Eloquent\Relations\MorphTo
{
    return $this->morphTo('entity', 'entity_type', 'entity_id');
}
```

Note: `entity_type` stores short names: `product`, `category`, `brand`, `blog_post`. The `entity()` morphTo must be registered in `AppServiceProvider` or use a morph map.

- [ ] **Step 4: Update entity models — add og fields to fillable**

In `app/Models/Product.php`, add `'og_title', 'og_description'` to `$fillable` array.

In `app/Models/Category.php`, add `'og_title', 'og_description'` to `$fillable` array.

In `app/Models/Brand.php`, add `'og_title', 'og_description'` to `$fillable` array.

In `app/Models/BlogPost.php`, add `'og_title', 'og_description'` to `$fillable` array.

- [ ] **Step 5: Register morph map in AppServiceProvider**

In `app/Providers/AppServiceProvider.php`, inside the `boot()` method, add:

```php
\Illuminate\Database\Eloquent\Relations\Relation::enforceMorphMap([
    'product' => \App\Models\Product::class,
    'category' => \App\Models\Category::class,
    'brand' => \App\Models\Brand::class,
    'blog_post' => \App\Models\BlogPost::class,
]);
```

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_03_26_100001_extend_seo_for_all_entities.php app/Models/SeoSuggestion.php app/Models/Product.php app/Models/Category.php app/Models/Brand.php app/Models/BlogPost.php app/Providers/AppServiceProvider.php
git commit -m "feat(seo): add OG fields and polymorphic suggestions for all entity types"
```

---

### Task 2: Refactor SeoGeneratorService for Multi-Entity Support

**Files:**
- Modify: `app/Services/SeoGeneratorService.php`

- [ ] **Step 1: Refactor service to support entity types**

Replace the entire `app/Services/SeoGeneratorService.php` with a version that supports products, categories, brands, and blog posts. Key changes:

1. New public method `generateForEntities(string $entityType, Collection $entities): array` — generates suggestions for any entity type
2. New public method `optimizeSingle(string $entityType, int $entityId): ?SeoSuggestion` — generates suggestion for a single entity (used by the "Optimize with AI" button)
3. Existing `generate()` method refactored to call `generateForEntities('product', ...)` for backward compatibility
4. Separate prompt builders per entity type: `buildProductPrompt()`, `buildCategoryPrompt()`, `buildBrandPrompt()`, `buildBlogPrompt()`
5. Each prompt includes OG title and OG description in addition to existing meta fields
6. GSC data matched per entity URL when available

The `callClaudeApi()` method remains largely the same but the response format now includes `og_title` and `og_description`.

Prompt template for products (updated):
```
You are an SEO specialist for an e-commerce store in the Philippines selling music gear.
Generate optimized meta tags and social media (Open Graph) tags for each product.

For each product, provide:
- meta_title: max 60 chars, include product name + key selling point
- meta_description: max 155 chars, compelling, include call-to-action
- og_title: max 60 chars, optimized for social media sharing (can differ from meta_title)
- og_description: max 200 chars, engaging for social media preview

Products:
{json product data}

{GSC data if available}

Respond ONLY with valid JSON: {"<id>": {"meta_title": "...", "meta_description": "...", "og_title": "...", "og_description": "..."}, ...}
```

Category prompt includes: category name, parent hierarchy, product count, top 5 product names, GSC URL data.

Brand prompt includes: brand name, product count, top 5 products, GSC URL data.

Blog prompt includes: post title, excerpt, category, tags, GSC URL data.

The `optimizeSingle()` method:
- Loads the entity with relationships
- Builds a single-entity prompt with richer context (full description, all relationships)
- Fetches GSC data specifically for that entity's URL
- Creates an SeoSuggestion with status 'pending'
- Returns the suggestion for preview

- [ ] **Step 2: Verify existing generate command still works**

Run: `php artisan seo:generate --help`
Expected: Command still registered and shows help.

- [ ] **Step 3: Commit**

```bash
git add app/Services/SeoGeneratorService.php
git commit -m "feat(seo): refactor SeoGeneratorService for multi-entity support with OG tags"
```

---

### Task 3: Routes and Controller — SEO Extension

**Files:**
- Create: `app/Http/Controllers/Admin/Extensions/SeoExtensionController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Create controller**

Create `app/Http/Controllers/Admin/Extensions/SeoExtensionController.php` with these methods:

- `dashboard()` — SEO health scores across all entity types, pending suggestion counts, recent activity, GSC snapshot
- `entities(Request $request, string $type)` — paginated list of entities with SEO status, pending suggestions. Accepts `type` param: products, categories, brands, blog
- `generate(Request $request)` — mass generate suggestions. Accepts `entity_type` and optional `entity_ids[]`
- `optimize(Request $request)` — single entity optimize via AJAX. Accepts `entity_type` and `entity_id`. Returns JSON with suggestion data for preview
- `accept(SeoSuggestion $suggestion)` — apply suggestion to entity
- `reject(SeoSuggestion $suggestion)` — mark as rejected
- `bulkAccept(Request $request)` — bulk accept selected suggestions
- `searchConsole()` — display GSC data
- `settings()` — GET settings form
- `saveSettings(Request $request)` — POST save settings
- `uploadGsc(Request $request)` — POST upload GSC credentials JSON

The `entities()` method determines the model class from `$type`:
```php
$modelClass = match($type) {
    'products' => Product::class,
    'categories' => Category::class,
    'brands' => Brand::class,
    'blog' => BlogPost::class,
    default => abort(404),
};
```

It queries entities with a subquery for pending suggestion count and SEO status (complete/partial/missing based on meta_title + meta_description presence).

- [ ] **Step 2: Add routes**

In `routes/web.php`, inside the admin route group (after existing SEO routes around line 297), add:

```php
// SEO Extension
Route::prefix('extensions/seo')->name('extensions.seo.')->group(function () {
    Route::get('/', [Extensions\SeoExtensionController::class, 'dashboard'])->name('dashboard');
    Route::get('/{type}', [Extensions\SeoExtensionController::class, 'entities'])->name('entities')->where('type', 'products|categories|brands|blog');
    Route::post('/generate', [Extensions\SeoExtensionController::class, 'generate'])->name('generate');
    Route::post('/optimize', [Extensions\SeoExtensionController::class, 'optimize'])->name('optimize');
    Route::post('/suggestions/{suggestion}/accept', [Extensions\SeoExtensionController::class, 'accept'])->name('accept');
    Route::post('/suggestions/{suggestion}/reject', [Extensions\SeoExtensionController::class, 'reject'])->name('reject');
    Route::post('/suggestions/bulk-accept', [Extensions\SeoExtensionController::class, 'bulkAccept'])->name('bulk-accept');
    Route::get('/search-console', [Extensions\SeoExtensionController::class, 'searchConsole'])->name('search-console');
    Route::get('/settings', [Extensions\SeoExtensionController::class, 'settings'])->name('settings');
    Route::post('/settings', [Extensions\SeoExtensionController::class, 'saveSettings'])->name('save-settings');
    Route::post('/settings/upload-gsc', [Extensions\SeoExtensionController::class, 'uploadGsc'])->name('upload-gsc');
});
```

Add the use statement at the top of web.php:
```php
use App\Http\Controllers\Admin\Extensions\SeoExtensionController;
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/Admin/Extensions/SeoExtensionController.php routes/web.php
git commit -m "feat(seo): add SEO extension controller and routes"
```

---

### Task 4: Admin Sidebar — Add SEO Under Extensions

**Files:**
- Modify: `resources/views/layouts/admin.blade.php`

- [ ] **Step 1: Add SEO menu item to Extensions group**

In `resources/views/layouts/admin.blade.php`, line 76, add `'admin.extensions.seo*'` to the Extensions group patterns array.

Then in the children array (after the API Logs entry on line 83), add:

```php
$link('admin.extensions.seo.dashboard', 'SEO', 'M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z', 'admin.extensions.seo*'),
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/layouts/admin.blade.php
git commit -m "feat(seo): add SEO link to admin sidebar under Extensions"
```

---

### Task 5: Views — Dashboard Tab

**Files:**
- Create: `resources/views/admin/extensions/seo/dashboard.blade.php`

- [ ] **Step 1: Create dashboard view**

The dashboard view uses the `<x-admin-layout>` component. It displays:

1. **Header**: "SEO Optimization" with tabs as links: Dashboard (active), Products, Categories, Brands, Blog, Search Console, Settings
2. **Health Score Cards**: 4 cards in a grid showing coverage for each entity type:
   - Products: X/Y with meta (percentage bar)
   - Categories: X/Y with meta
   - Brands: X/Y with meta
   - Blog Posts: X/Y with meta
3. **Pending Suggestions**: count badge with link to relevant entity tab
4. **Quick Actions**: "Generate All Suggestions" button (POST to generate route), "Accept All Pending" button
5. **Recent Activity**: Last 10 suggestions with status, entity name, timestamp
6. **GSC Snapshot**: Top 5 queries by impressions (or "Configure GSC" message with link to settings)

The controller passes: `$healthScores` (array with counts per type), `$pendingCount`, `$recentSuggestions`, `$gscQueries`.

Tab navigation is rendered as a shared partial or inline at the top, using simple anchor links to each tab's route.

- [ ] **Step 2: Commit**

```bash
git add resources/views/admin/extensions/seo/dashboard.blade.php
git commit -m "feat(seo): add SEO dashboard view with health scores and quick actions"
```

---

### Task 6: Views — Entity List Tab (Shared for Products/Categories/Brands/Blog)

**Files:**
- Create: `resources/views/admin/extensions/seo/entities.blade.php`

- [ ] **Step 1: Create entities list view**

A single view handles all entity types. The controller passes `$type`, `$entities` (paginated), `$pendingSuggestions` (keyed by entity_id).

Layout:
1. **Tab navigation** (same as dashboard, with current type highlighted)
2. **Filters bar**: search input, SEO status dropdown (All/Complete/Partial/Missing)
3. **Mass action bar** (shown when checkboxes selected): "Generate SEO for Selected" button, "Accept All Pending for Selected" button
4. **Table** with columns:
   - Checkbox
   - Image (small thumb — product image, or category/brand/blog featured image)
   - Name (linked to entity edit page)
   - SEO Status badge (Complete green / Partial yellow / Missing red)
   - Meta Title (truncated, or "—" if empty)
   - Pending suggestion indicator (orange dot)
   - Actions: "Optimize" button
5. **Expandable suggestion row**: When a pending suggestion exists, clicking the row expands an inline diff panel:
   - Current vs Suggested meta_title
   - Current vs Suggested meta_description
   - Current vs Suggested og_title
   - Current vs Suggested og_description
   - Accept / Reject buttons

The "Optimize" button triggers an AJAX call (Alpine.js) to the `optimize` route, which returns the suggestion JSON. The response is displayed in a modal with current vs suggested values and an "Apply" button.

Alpine.js inline component handles:
- Checkbox select all / select individual
- Mass action form submission
- Single optimize AJAX call + modal display
- Accept/reject AJAX calls with inline UI updates

- [ ] **Step 2: Commit**

```bash
git add resources/views/admin/extensions/seo/entities.blade.php
git commit -m "feat(seo): add entity list view with SEO status, mass actions, and inline suggestions"
```

---

### Task 7: Views — Settings Tab

**Files:**
- Create: `resources/views/admin/extensions/seo/settings.blade.php`

- [ ] **Step 1: Create settings view**

Settings page with tab navigation and form fields:

1. **Claude API Key**: password input, encrypted, masked preview if set
2. **Claude Model**: dropdown with options: `claude-sonnet-4-5-20250514` (default), `claude-sonnet-4-5-latest`
3. **GSC Credentials**: file upload (JSON), shows green "Uploaded" badge if file exists
4. **GSC Site URL**: text input (e.g., `https://newgearday.com`)
5. **Auto-accept**: toggle checkbox (default off)
6. **Schedule info**: read-only text showing "Runs monthly on the 1st at 3:00 AM" with note about cron requirement
7. **Generate Now**: button that triggers immediate full generation across all entity types
8. Save button

All settings saved to the `settings` table with group `seo`.

- [ ] **Step 2: Commit**

```bash
git add resources/views/admin/extensions/seo/settings.blade.php
git commit -m "feat(seo): add SEO extension settings view"
```

---

### Task 8: Views — Search Console Tab

**Files:**
- Create: `resources/views/admin/extensions/seo/search-console.blade.php`

- [ ] **Step 1: Create search console view**

Displays GSC data with tab navigation. Two tables:

1. **Top Queries** (last 28 days): query, clicks, impressions, CTR (color-coded), avg position
2. **Top Pages** (last 28 days): page URL (linked), clicks, impressions, CTR, avg position

Color coding: CTR < 2% red, 2-5% yellow, >5% green. Position < 10 green, 10-20 yellow, >20 red.

If GSC not configured, shows a message with link to Settings tab.

"Refresh" button to re-fetch data.

Controller passes `$queries` and `$pages` arrays from `GoogleSearchConsoleService`.

- [ ] **Step 2: Commit**

```bash
git add resources/views/admin/extensions/seo/search-console.blade.php
git commit -m "feat(seo): add Search Console data view"
```

---

### Task 9: "Optimize with AI" Button on Product Edit Page

**Files:**
- Modify: `resources/views/admin/products/form.blade.php`

- [ ] **Step 1: Add Optimize button to product SEO section**

In `resources/views/admin/products/form.blade.php`, after the meta_keywords field (around line 592), add an Alpine.js-powered "Optimize with AI" button:

```html
<div x-data="seoOptimizer()" class="mt-4 border-t border-gray-100 pt-4">
    <button type="button" @click="optimize()" :disabled="loading"
        class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 text-white text-sm font-medium rounded-lg hover:bg-purple-700 disabled:opacity-50 transition-colors">
        <svg x-show="!loading" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
        <svg x-show="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>
        <span x-text="loading ? 'Optimizing...' : 'Optimize with AI'"></span>
    </button>

    <!-- Preview Modal -->
    <div x-show="suggestion" x-cloak class="mt-4 bg-purple-50 border border-purple-200 rounded-lg p-4">
        <h4 class="text-sm font-semibold text-purple-800 mb-3">AI Suggestion Preview</h4>
        <div class="space-y-3 text-sm">
            <template x-for="field in ['meta_title', 'meta_description', 'og_title', 'og_description']" :key="field">
                <div>
                    <label class="text-xs font-medium text-gray-500 uppercase" x-text="field.replace(/_/g, ' ')"></label>
                    <div class="grid grid-cols-2 gap-2 mt-1">
                        <div class="bg-white rounded p-2 text-xs">
                            <span class="text-gray-400 block mb-0.5">Current:</span>
                            <span x-text="suggestion['current_' + field] || '(empty)'"></span>
                        </div>
                        <div class="bg-white rounded p-2 text-xs border-l-2 border-purple-400">
                            <span class="text-purple-600 block mb-0.5">Suggested:</span>
                            <span x-text="suggestion['suggested_' + field] || '(empty)'"></span>
                        </div>
                    </div>
                </div>
            </template>
        </div>
        <div class="flex gap-2 mt-4">
            <button type="button" @click="applySuggestion()" class="px-3 py-1.5 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700">Apply</button>
            <button type="button" @click="suggestion = null" class="px-3 py-1.5 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300">Dismiss</button>
        </div>
    </div>
</div>
```

The `seoOptimizer()` Alpine component (added to the page's `@push('scripts')`):
- `optimize()`: POST to `route('admin.extensions.seo.optimize')` with `entity_type: 'product'` and `entity_id: productId`
- On success: sets `suggestion` data, displays preview
- `applySuggestion()`: POST to accept route, then fills the form fields with suggested values (updates the meta_title, meta_description inputs on the page)

- [ ] **Step 2: Commit**

```bash
git add resources/views/admin/products/form.blade.php
git commit -m "feat(seo): add Optimize with AI button to product edit page"
```

---

### Task 10: "Optimize with AI" Button on Category, Brand, Blog Edit Pages

**Files:**
- Modify: `resources/views/admin/categories/form.blade.php`
- Modify: `resources/views/admin/brands/form.blade.php`
- Modify: `resources/views/admin/blog/form.blade.php`

- [ ] **Step 1: Add Optimize button to category edit page**

Same pattern as product edit (Task 9) but with `entity_type: 'category'`. Add after the meta fields section in the category form.

- [ ] **Step 2: Add Optimize button to brand edit page**

Same pattern with `entity_type: 'brand'`.

- [ ] **Step 3: Add Optimize button to blog edit page**

Same pattern with `entity_type: 'blog_post'`. Add in the SEO section of the blog form.

- [ ] **Step 4: Commit**

```bash
git add resources/views/admin/categories/form.blade.php resources/views/admin/brands/form.blade.php resources/views/admin/blog/form.blade.php
git commit -m "feat(seo): add Optimize with AI button to category, brand, and blog edit pages"
```

---

### Task 11: Update Storefront OG Tag Rendering

**Files:**
- Modify: `resources/themes/stage-ready/views/layouts/store.blade.php`
- Modify: `resources/themes/clean-light/views/layouts/store.blade.php`

- [ ] **Step 1: Update store layouts to use entity OG tags**

In both theme layouts, update the OpenGraph meta tag section to prefer entity-specific OG tags when available. The layout already has `$pageTitle` and `$metaDescription`. The storefront controllers need to pass `$ogTitle` and `$ogDescription` when available.

In the `<head>` section, update the OG tags:

```blade
@hasSection('og_title')
    <meta property="og:title" content="@yield('og_title')">
@else
    <meta property="og:title" content="{{ $ogTitle ?? $pageTitle }}">
@endif
@if ($ogDescription ?? $metaDescription ?? false)
    <meta property="og:description" content="{{ $ogDescription ?? $metaDescription }}">
@endif
```

- [ ] **Step 2: Update storefront controllers to pass OG data**

In `ProductController@show`, `CategoryController@show`, `BrandController@show`, `BlogController@show`: pass `$ogTitle` and `$ogDescription` from the entity's `og_title` and `og_description` fields to the view.

- [ ] **Step 3: Commit**

```bash
git add resources/themes/stage-ready/views/layouts/store.blade.php resources/themes/clean-light/views/layouts/store.blade.php app/Http/Controllers/Storefront/ProductController.php app/Http/Controllers/Storefront/CategoryController.php app/Http/Controllers/Storefront/BrandController.php app/Http/Controllers/Storefront/BlogController.php
git commit -m "feat(seo): render entity-specific OG tags on storefront pages"
```

---

### Task 12: Update Schedule and Final Integration

**Files:**
- Modify: `routes/console.php`
- Modify: `app/Console/Commands/GenerateSeoSuggestions.php`

- [ ] **Step 1: Update artisan command to generate for all entity types**

Update `GenerateSeoSuggestions.php` to call the service for products, categories, brands, and blog posts:

```php
public function handle(): int
{
    $service = new SeoGeneratorService();
    $results = [];

    foreach (['product', 'category', 'brand', 'blog_post'] as $type) {
        $result = $service->generateForType($type);
        $results[$type] = $result;
        $this->info("{$type}: {$result['suggestions']} suggestions generated");
    }

    $this->info('SEO generation complete.');
    return 0;
}
```

- [ ] **Step 2: Keep schedule as-is**

The existing monthly schedule in `routes/console.php` (line 21) already runs `seo:generate`, which now covers all entity types.

- [ ] **Step 3: Move GSC settings UI from Settings > API to Extensions > SEO > Settings**

Remove the GSC/Claude sections that were added earlier to `resources/views/admin/settings/index.blade.php` (the section we added in the API tab). These now live in the SEO extension settings tab.

- [ ] **Step 4: Final commit**

```bash
git add app/Console/Commands/GenerateSeoSuggestions.php routes/console.php resources/views/admin/settings/index.blade.php
git commit -m "feat(seo): update CLI command for all entities, clean up settings page"
```

---

### Task 13: Shared Tab Navigation Partial

**Files:**
- Create: `resources/views/admin/extensions/seo/partials/tabs.blade.php`

- [ ] **Step 1: Create tabs partial**

Shared tab navigation used across all SEO extension views:

```blade
@php
    $tabs = [
        ['route' => 'admin.extensions.seo.dashboard', 'label' => 'Dashboard', 'pattern' => 'admin.extensions.seo.dashboard'],
        ['route' => 'admin.extensions.seo.entities', 'routeParams' => ['type' => 'products'], 'label' => 'Products', 'pattern' => null, 'active' => ($type ?? '') === 'products'],
        ['route' => 'admin.extensions.seo.entities', 'routeParams' => ['type' => 'categories'], 'label' => 'Categories', 'pattern' => null, 'active' => ($type ?? '') === 'categories'],
        ['route' => 'admin.extensions.seo.entities', 'routeParams' => ['type' => 'brands'], 'label' => 'Brands', 'pattern' => null, 'active' => ($type ?? '') === 'brands'],
        ['route' => 'admin.extensions.seo.entities', 'routeParams' => ['type' => 'blog'], 'label' => 'Blog', 'pattern' => null, 'active' => ($type ?? '') === 'blog'],
        ['route' => 'admin.extensions.seo.search-console', 'label' => 'Search Console', 'pattern' => 'admin.extensions.seo.search-console'],
        ['route' => 'admin.extensions.seo.settings', 'label' => 'Settings', 'pattern' => 'admin.extensions.seo.settings'],
    ];
@endphp
<div class="border-b border-gray-200 mb-6">
    <nav class="flex gap-6">
        @foreach ($tabs as $tab)
            @php
                $isActive = isset($tab['active']) ? $tab['active'] : (isset($tab['pattern']) && request()->routeIs($tab['pattern']));
                $url = route($tab['route'], $tab['routeParams'] ?? []);
            @endphp
            <a href="{{ $url }}" class="pb-3 text-sm font-medium border-b-2 transition-colors {{ $isActive ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                {{ $tab['label'] }}
            </a>
        @endforeach
    </nav>
</div>
```

Each view includes this partial: `@include('admin.extensions.seo.partials.tabs')`

- [ ] **Step 2: Commit**

```bash
git add resources/views/admin/extensions/seo/partials/tabs.blade.php
git commit -m "feat(seo): add shared tab navigation partial for SEO extension"
```
