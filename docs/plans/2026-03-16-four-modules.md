# Four Modules Implementation Plan

> **For agentic workers:** Execute these modules sequentially. Each module is self-contained.

**Modules:**
1. ERP Sync API
2. Email Template System
3. Product Reviews
4. AI SEO Automation (Phase 1)

**Tech Stack:** Laravel 12 / PHP 8.2 / MySQL / Alpine.js / Tailwind CSS / TinyMCE / Claude API

---

## MODULE 1: ERP SYNC API

### Task 1.1: API token authentication

**Files:**
- Create: `app/Http/Middleware/ErpApiAuth.php`
- Modify: `bootstrap/app.php` (register middleware alias)
- Modify: `resources/views/admin/settings/index.blade.php` (add API tab)
- Modify: `app/Http/Controllers/Admin/SettingController.php` (add token generation)

**Middleware:**
```php
class ErpApiAuth
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        $storedToken = Setting::where('group', 'api')->where('key', 'erp_token')->value('value');

        if (!$token || !$storedToken || !hash_equals($storedToken, $token)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
```

**Admin Settings > API tab:**
- Display current token (masked)
- Generate/Regenerate button (creates random 64-char token)
- Copy to clipboard button

### Task 1.2: Sync log migration + model

**Files:**
- Create: `database/migrations/2026_03_16_200001_create_api_sync_logs_table.php`
- Create: `app/Models/ApiSyncLog.php`

```php
Schema::create('api_sync_logs', function (Blueprint $table) {
    $table->id();
    $table->string('method', 10);
    $table->string('endpoint');
    $table->string('sku')->nullable();
    $table->integer('status_code');
    $table->integer('response_time_ms');
    $table->text('request_body')->nullable();
    $table->text('response_body')->nullable();
    $table->string('ip_address', 45)->nullable();
    $table->timestamps();
});
```

### Task 1.3: Product endpoints

**Files:**
- Create: `app/Http/Controllers/Api/ErpProductController.php`
- Create: `routes/api.php`

**Endpoints:**
- `GET /api/erp/products` — paginated list, ?sku=, ?updated_since=, ?per_page=
- `GET /api/erp/products/{sku}` — single product with variants, images, categories
- `POST /api/erp/products` — create product (name, sku, price, qty, description, brand, categories, status)
- `PUT /api/erp/products/{sku}` — update product fields
- `POST /api/erp/products/{sku}/images` — upload multipart image(s), stored in `storage/app/public/products/{brand-slug}/`

### Task 1.4: Variant endpoints

**Files:**
- Create: `app/Http/Controllers/Api/ErpVariantController.php`

**Endpoints:**
- `GET /api/erp/variants/{sku}` — get variant by SKU with parent product
- `PUT /api/erp/variants/{sku}` — update variant (qty, price, status)

### Task 1.5: Order endpoints

**Files:**
- Create: `app/Http/Controllers/Api/ErpOrderController.php`

**Endpoints:**
- `GET /api/erp/orders` — paginated, ?status=, ?since=, ?per_page=
- `GET /api/erp/orders/{id}` — single order with items, customer, addresses
- `PUT /api/erp/orders/{id}/status` — update order status + optional comment

### Task 1.6: Admin sync log viewer

**Files:**
- Create: `app/Http/Controllers/Admin/ApiSyncLogController.php`
- Create: `resources/views/admin/api-sync-logs/index.blade.php`
- Modify: `resources/views/layouts/admin.blade.php` (add to Extensions group)
- Modify: `routes/web.php`

Admin page showing recent API calls: method, endpoint, SKU, status, response time, timestamp. Filterable.

---

## MODULE 2: EMAIL TEMPLATE SYSTEM

### Task 2.1: Migration + model

**Files:**
- Create: `database/migrations/2026_03_16_300001_create_email_templates_table.php`
- Create: `app/Models/EmailTemplate.php`

```php
Schema::create('email_templates', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('store_id')->default(1);
    $table->string('slug')->unique();
    $table->string('name');
    $table->string('subject');
    $table->text('body');
    $table->enum('type', ['customer', 'admin'])->default('customer');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### Task 2.2: Seed default templates

**Files:**
- Create: `database/seeders/EmailTemplateSeeder.php`

Pre-seed 8 templates with proper placeholder HTML bodies:
- `order_confirmation` (customer) — replaces current OrderConfirmationMail
- `order_status_update` (customer)
- `shipping_notification` (customer)
- `welcome` (customer) — replaces current WelcomeMail
- `password_reset` (customer)
- `review_request` (customer) — for Module 3
- `new_order_admin` (admin)
- `low_stock_admin` (admin)

Reference existing email template HTML from `resources/views/emails/order-confirmation.blade.php` for styling.

### Task 2.3: TemplateMailService

**Files:**
- Create: `app/Services/TemplateMailService.php`
- Create: `app/Mail/TemplateMail.php`
- Create: `resources/views/emails/template.blade.php`

```php
class TemplateMailService
{
    public static function send(string $slug, string $to, array $data, ?string $toName = null): bool
    {
        $template = EmailTemplate::where('slug', $slug)->where('is_active', true)->first();
        if (!$template) return false;

        Mail::to($to)->send(new TemplateMail($template, $data));
        return true;
    }
}
```

The `TemplateMail` class renders subject + body with placeholder replacement, uses `emails.template` view (dark-themed HTML wrapper).

### Task 2.4: Admin controller + view

**Files:**
- Create: `app/Http/Controllers/Admin/EmailTemplateController.php`
- Create: `resources/views/admin/email-templates/index.blade.php`
- Create: `resources/views/admin/email-templates/edit.blade.php`
- Modify: `resources/views/layouts/admin.blade.php` (add to Extensions group)
- Modify: `routes/web.php`

Index: table of all templates with name, slug, type badge, status toggle, edit button.
Edit: subject field, TinyMCE body editor, placeholder reference panel, save + test email buttons.

### Task 2.5: Replace existing Mailables

Update `OrderConfirmationMail`, `WelcomeMail`, `StockNotificationMail` to use `TemplateMailService` as primary, falling back to existing Mailable if template not found.

---

## MODULE 3: PRODUCT REVIEWS

### Task 3.1: Migrations

**Files:**
- Create: `database/migrations/2026_03_16_400001_create_reviews_table.php`
- Create: `database/migrations/2026_03_16_400002_create_review_images_table.php`
- Create: `database/migrations/2026_03_16_400003_create_review_coupons_table.php`

```php
// reviews
Schema::create('reviews', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->unsignedBigInteger('customer_id')->nullable();
    $table->unsignedBigInteger('order_id')->nullable();
    $table->string('token', 64)->unique();
    $table->string('name');
    $table->string('email');
    $table->tinyInteger('rating'); // 1-5
    $table->string('title')->nullable();
    $table->text('body')->nullable();
    $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
    $table->boolean('is_verified_purchase')->default(false);
    $table->timestamp('published_at')->nullable();
    $table->timestamps();
});

// review_images
Schema::create('review_images', function (Blueprint $table) {
    $table->id();
    $table->foreignId('review_id')->constrained()->cascadeOnDelete();
    $table->string('path');
    $table->timestamps();
});

// review_coupons
Schema::create('review_coupons', function (Blueprint $table) {
    $table->id();
    $table->foreignId('review_id')->constrained()->cascadeOnDelete();
    $table->string('coupon_code')->unique();
    $table->enum('discount_type', ['percent', 'fixed'])->default('percent');
    $table->decimal('discount_value', 10, 2);
    $table->date('expires_at')->nullable();
    $table->boolean('used')->default(false);
    $table->timestamps();
});
```

### Task 3.2: Models

**Files:**
- Create: `app/Models/Review.php`
- Create: `app/Models/ReviewImage.php`
- Create: `app/Models/ReviewCoupon.php`

### Task 3.3: Review settings (admin)

Add "Reviews" tab to admin Settings page with:
- Enable/disable reviews globally
- Review request trigger: order status dropdown + delay days input
- Customer groups: multi-select which groups get review requests
- Coupon: enable/disable, type (% or fixed), value, expiry days

Settings stored in `settings` table under group `reviews`.

### Task 3.4: Review request cron

**Files:**
- Create: `app/Console/Commands/SendReviewRequests.php`
- Modify: `routes/console.php`

`review:send` command runs daily:
1. Find orders that reached the trigger status X days ago
2. Filter by customer group
3. Check no review already exists for this order
4. Generate token, create pending review record, send email via TemplateMailService

### Task 3.5: Public review submission page

**Files:**
- Create: `app/Http/Controllers/Storefront/ReviewController.php`
- Create: `resources/themes/stage-ready/views/review/submit.blade.php`
- Create: `resources/themes/clean-light/views/review/submit.blade.php`
- Modify: `routes/web.php`

`GET /review/{token}` — shows product info, star rating picker, title, body, photo upload (1-2 images).
`POST /review/{token}` — validates and saves review, marks as pending.
Thank you page after submission.

### Task 3.6: Admin review management

**Files:**
- Create: `app/Http/Controllers/Admin/ReviewController.php`
- Create: `resources/views/admin/reviews/index.blade.php`
- Modify: `resources/views/layouts/admin.blade.php` (add to Sales group)
- Modify: `routes/web.php`

Tabs: Pending, Approved, Rejected.
Actions: Approve (sets published_at, optionally generates coupon), Reject, Delete.
View: full review with photos, product link, customer info.

### Task 3.7: Storefront display

- Product page: star rating average + count below product name
- Review tab in product detail area showing approved reviews with photos
- Add CSS for star rating display + review cards to both theme CSS files

### Task 3.8: Coupon generation on approval

When admin approves a review and coupon is enabled:
- Auto-generate unique coupon code
- Save to `review_coupons`
- Send "thank you + coupon" email via TemplateMailService

---

## MODULE 4: AI SEO AUTOMATION (Phase 1)

### Task 4.1: Migration

**Files:**
- Create: `database/migrations/2026_03_16_500001_create_seo_suggestions_table.php`
- Create: `database/migrations/2026_03_16_500002_create_seo_reports_table.php`

```php
// seo_suggestions
Schema::create('seo_suggestions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->string('suggested_meta_title')->nullable();
    $table->text('suggested_meta_description')->nullable();
    $table->string('suggested_meta_keywords')->nullable();
    $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
    $table->timestamp('generated_at');
    $table->timestamps();
});

// seo_reports
Schema::create('seo_reports', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('store_id')->default(1);
    $table->integer('total_products_analyzed');
    $table->integer('suggestions_generated');
    $table->integer('auto_accepted')->default(0);
    $table->integer('pending_review')->default(0);
    $table->decimal('health_score', 5, 2)->default(0);
    $table->json('top_opportunities')->nullable();
    $table->json('search_insights')->nullable();
    $table->timestamp('generated_at');
    $table->timestamps();
});
```

### Task 4.2: Models + Settings

- Create `SeoSuggestion` and `SeoReport` models
- Add "SEO" tab to admin Settings: Claude API key, schedule (weekly/monthly), batch size, auto-accept toggle

### Task 4.3: SEO generation command

**Files:**
- Create: `app/Console/Commands/GenerateSeoSuggestions.php`
- Create: `app/Services/SeoGeneratorService.php`
- Modify: `routes/console.php`

`seo:generate` command:
1. Fetch products needing SEO (no meta, or stale > 30 days)
2. Batch of 50, construct prompt with product data
3. Call Claude API (claude-sonnet-4-5-20250514)
4. Parse response, save suggestions
5. Generate report with health score
6. Send admin email notification

### Task 4.4: Non-AI SEO features

**Files:**
- Create: `app/Console/Commands/GenerateSitemap.php`
- Create: `app/Http/Middleware/AddSeoHeaders.php`
- Modify theme layouts for JSON-LD + OpenGraph

- Sitemap.xml generation (products, categories, brands, blog posts)
- JSON-LD structured data on product pages
- JSON-LD breadcrumbs
- OpenGraph + Twitter Card meta tags
- Canonical URLs

### Task 4.5: Admin SEO page

**Files:**
- Create: `app/Http/Controllers/Admin/SeoController.php`
- Create: `resources/views/admin/seo/index.blade.php`
- Modify: `resources/views/layouts/admin.blade.php` (add to Extensions group)

Tabs: Suggestions (pending/accepted/rejected), Reports (history), Settings.
Bulk accept/reject. Google SERP preview. "Generate Now" manual trigger.

---

## Build order

Execute modules in order: 1 → 2 → 3 → 4. Module 2 (Email Templates) should come before 3 (Reviews) since reviews use the template system for emails.
