# SEO Automation Extension — Design Spec

## Overview

A dedicated SEO extension under **Extensions > SEO** that uses Claude AI + Google Search Console data to automatically generate and manage SEO meta tags across all content types (products, categories, brands, blog posts). Includes a per-entity "Optimize SEO" button for on-demand generation with preview/diff before applying.

## Architecture

### Location in Admin

- **Sidebar**: Extensions > SEO (new top-level menu group "Extensions" with SEO as first sub-item)
- **Route prefix**: `admin/extensions/seo`
- **Controller**: `App\Http\Controllers\Admin\Extensions\SeoExtensionController`
- **Service**: Refactored `SeoGeneratorService` expanded to handle all entity types

### Tabs

1. **Dashboard** — Health score, stats, quick actions
2. **Products** — Product SEO management with mass generate/accept
3. **Categories** — Category SEO management
4. **Brands** — Brand SEO management
5. **Blog** — Blog post SEO management
6. **Search Console** — GSC data visualization
7. **Settings** — API keys, credentials, configuration

## Tab Details

### 1. Dashboard Tab

Displays at a glance:
- **SEO Health Score** — percentage of all entities (products + categories + brands + blog) with meta_title filled
- **Coverage breakdown** — bar chart or stats: "Products: 412/580 (71%)", "Categories: 8/24 (33%)", etc.
- **Pending suggestions** — count of unreviewed suggestions across all types
- **Recent activity** — last 10 accepted/rejected suggestions with timestamps
- **Quick actions**: "Generate All Suggestions" button, "Accept All Pending" button
- **GSC snapshot** — top 5 queries by impressions if GSC is configured, otherwise a prompt to configure it

### 2. Products Tab

Table listing all active products with columns:
- Thumbnail (small product image)
- Product name
- SEO Status badge: "Complete" (green), "Partial" (yellow), "Missing" (red)
  - Complete = has meta_title AND meta_description
  - Partial = has one but not both
  - Missing = neither
- Current meta_title (truncated)
- Pending suggestion indicator (icon if suggestion exists)
- Actions: "Optimize" button per row

**Filters**: Search by name, filter by SEO status (All/Complete/Partial/Missing), filter by category

**Mass actions** (checkbox-based):
- "Generate SEO" — sends selected products to Claude for suggestions
- "Accept All Pending" — bulk-accepts pending suggestions for selected products

**Suggestion review inline**: When a product has a pending suggestion, clicking it expands an inline diff showing:
- Current meta_title vs Suggested meta_title
- Current meta_description vs Suggested meta_description
- Current OG tags vs Suggested (if different)
- Accept / Reject buttons

### 3. Categories Tab

Same layout as Products tab but for categories:
- Category name, SEO status, current meta, pending suggestions
- Mass generate, mass accept
- Inline diff review

### 4. Brands Tab

Same layout for brands:
- Brand name, SEO status, current meta, pending suggestions
- Mass generate, mass accept
- Inline diff review

### 5. Blog Tab

Same layout for blog posts:
- Post title, SEO status, current meta, pending suggestions
- Mass generate, mass accept
- Inline diff review
- Additionally: Claude can suggest new blog topic ideas based on GSC queries with high impressions but no matching content (displayed as a separate "Topic Suggestions" section)

### 6. Search Console Tab

Displays GSC data (if configured):
- **Top Queries table**: query, clicks, impressions, CTR, avg position (last 28 days)
- **Top Pages table**: page URL, clicks, impressions, CTR, avg position
- **Color coding**: CTR < 2% = red, 2-5% = yellow, > 5% = green
- **Refresh button** to re-fetch data
- If GSC not configured: message with link to Settings tab

### 7. Settings Tab

Configuration for the extension:
- **Claude API Key** — password field, encrypted storage, masked preview
- **Claude Model** — dropdown (claude-sonnet-4-5-20250514 default, option for opus)
- **GSC Credentials** — JSON file upload with status badge
- **GSC Site URL** — text input
- **Auto-accept** — toggle (default off). When on, suggestions are applied immediately on generation
- **Schedule** — display current schedule (monthly on 1st at 3:00 AM), info text explaining the cron requirement
- **Generate Now** — button to trigger immediate full generation

## "Optimize SEO" Button on Entity Edit Pages

### Product Edit Page
- New button in the SEO section of the product edit form: "Optimize with AI"
- On click: sends AJAX request to generate suggestion for this single product
- Shows a modal/inline preview with current vs suggested meta tags
- User clicks "Apply" to save or "Dismiss" to cancel
- Uses the same Claude prompt but for a single product with full context (name, description, category, brand, price, GSC data for that product's URL)

### Category/Brand/Blog Edit Pages
- Same "Optimize with AI" button pattern
- Same preview/diff modal
- Contextual: category includes its product count and top products, brand includes its products, blog includes existing content

## Claude Prompt Design

### Product SEO Prompt
Claude receives:
- Product name, description, brand, category path, price, SKU
- Current meta_title and meta_description (if any)
- GSC data for this product's URL (clicks, impressions, CTR, top queries that led to it)
- Store context: "Philippine online music gear store"
- Instructions: Generate meta_title (max 60 chars), meta_description (max 155 chars), og:title, og:description
- If current meta exists: explain what's being improved and why

### Category SEO Prompt
- Category name, parent hierarchy, number of products, top product names
- GSC data for category URL
- Generate meta_title, meta_description, og tags

### Brand SEO Prompt
- Brand name, product count, top product names
- GSC data for brand URL
- Generate meta_title, meta_description, og tags

### Blog SEO Prompt
- Post title, excerpt, category, tags
- GSC data for blog URL
- Generate meta_title, meta_description, og tags

### Batch Processing
For mass generation, products are sent in batches of 10 (current behavior) to Claude in a single request. Each batch includes GSC context for all items. Response expected as JSON keyed by entity ID.

## Database Changes

### Modify `seo_suggestions` table
Add column: `entity_type` (string, default 'product') — values: 'product', 'category', 'brand', 'blog_post'
Add column: `entity_id` (unsigned bigint) — replaces `product_id` for polymorphic usage
Add columns: `suggested_og_title`, `suggested_og_description`
Keep existing columns for backward compatibility during migration, then deprecate `product_id`.

### Add OG meta fields to entities that lack them
- `categories` table: add `meta_title`, `meta_description` (if not present), `og_title`, `og_description`
- `products` table: add `og_title`, `og_description`
- `brands` table: add `og_title`, `og_description`
- `blog_posts` table: add `og_title`, `og_description`

### Existing fields retained
- Products already have: `meta_title`, `meta_description`, `meta_keywords`
- Brands already have: `meta_title`, `meta_description`, `meta_keywords`
- Blog posts already have: `meta_title`, `meta_description`
- Categories already have: `meta_title`, `meta_description` (need to verify)

## Frontend (Storefront) Changes

### OG Tag Rendering
Update the store layout (`layouts/store.blade.php` in both themes) to use entity-specific OG tags when available:
- Product pages: use `og_title` and `og_description` if set, fallback to `meta_title`/`meta_description`
- Category pages: same pattern
- Brand pages: same pattern
- Blog pages: same pattern

## Routes

```
GET    admin/extensions/seo                         → dashboard
GET    admin/extensions/seo/products                → products list
POST   admin/extensions/seo/generate                → mass generate (accepts entity_type + IDs)
POST   admin/extensions/seo/accept/{suggestion}     → accept single
POST   admin/extensions/seo/reject/{suggestion}     → reject single
POST   admin/extensions/seo/bulk-accept             → bulk accept
POST   admin/extensions/seo/optimize                → single entity optimize (AJAX, returns suggestion JSON)
GET    admin/extensions/seo/search-console           → GSC data
GET    admin/extensions/seo/settings                → settings form
POST   admin/extensions/seo/settings                → save settings
POST   admin/extensions/seo/settings/upload-gsc     → upload GSC credentials
```

## Migration from Existing SEO System

- The existing `SeoController`, `SeoGeneratorService`, and admin SEO views continue to work during transition
- New extension runs alongside, using the same `seo_suggestions` table (with added columns)
- Once verified, old routes/views can be removed
- Existing accepted suggestions are preserved

## Safety

- All changes go through suggestion → review → accept flow (no auto-apply unless toggled on in settings)
- Original meta values are stored in `seo_suggestions` as `current_*` fields for rollback
- Single-product optimize shows preview before applying
- Mass generate only creates suggestions, doesn't modify products
- Extension has no effect on storefront if no suggestions are accepted
