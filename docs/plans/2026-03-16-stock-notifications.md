# Stock Notifications ("Notify Me When In Stock") Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan.

**Goal:** Allow customers to subscribe to out-of-stock product/variant notifications, auto-notify via cron when stock is replenished, with admin management, analytics, and customizable email templates.

**Architecture:** Two DB tables (notifications + email templates). Storefront modal for subscription. Cron job checks stock every 15 minutes and sends emails. Admin CRUD with waitlist/notified tabs, analytics, and template editor with placeholder support.

**Tech Stack:** Laravel 12 / PHP 8.2 / MySQL / Alpine.js / Tailwind CSS / TinyMCE (already installed)

---

## Task 1: Database migrations

**Files:**
- Create: `database/migrations/2026_03_16_100001_create_stock_notifications_table.php`
- Create: `database/migrations/2026_03_16_100002_create_stock_notification_templates_table.php`

**Step 1:** Create `stock_notifications` migration:

```php
Schema::create('stock_notifications', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('store_id')->default(1);
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->unsignedBigInteger('product_variant_id')->nullable();
    $table->string('name');
    $table->string('email');
    $table->enum('status', ['waiting', 'notified', 'failed'])->default('waiting');
    $table->timestamp('notified_at')->nullable();
    $table->timestamps();

    $table->index(['product_id', 'product_variant_id', 'status'], 'sn_product_status_index');
    $table->unique(['email', 'product_id', 'product_variant_id'], 'sn_unique_subscription');

    $table->foreign('product_variant_id')->references('id')->on('product_variants')->nullOnDelete();
});
```

**Step 2:** Create `stock_notification_templates` migration:

```php
Schema::create('stock_notification_templates', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('store_id')->default(1);
    $table->string('subject');
    $table->text('body');
    $table->timestamps();
});
```

**Step 3:** Run `php artisan migrate`.

---

## Task 2: Eloquent models

**Files:**
- Create: `app/Models/StockNotification.php`
- Create: `app/Models/StockNotificationTemplate.php`

**StockNotification model:**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockNotification extends Model
{
    protected $fillable = [
        'store_id', 'product_id', 'product_variant_id',
        'name', 'email', 'status', 'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'notified_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function scopeWaiting($query)
    {
        return $query->where('status', 'waiting');
    }

    public function scopeNotified($query)
    {
        return $query->where('status', 'notified');
    }
}
```

**StockNotificationTemplate model:**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockNotificationTemplate extends Model
{
    protected $fillable = ['store_id', 'subject', 'body'];

    public function renderSubject(array $data): string
    {
        return $this->replacePlaceholders($this->subject, $data);
    }

    public function renderBody(array $data): string
    {
        return $this->replacePlaceholders($this->body, $data);
    }

    private function replacePlaceholders(string $text, array $data): string
    {
        foreach ($data as $key => $value) {
            $text = str_replace('{' . $key . '}', $value, $text);
        }
        return $text;
    }
}
```

---

## Task 3: Seed default email template

**Files:**
- Create: `database/seeders/StockNotificationTemplateSeeder.php`

Seed with:

```php
StockNotificationTemplate::firstOrCreate(
    ['store_id' => 1],
    [
        'subject' => 'Good news! {product_name} is back in stock',
        'body' => '<p>Hi {customer_name},</p>
<p>Great news! The <strong>{product_name}</strong>{variant_name} you were waiting for is now back in stock at {store_name}.</p>
<p><a href="{product_url}">View Product</a></p>
<p>Hurry — stock is limited and this item may sell out again!</p>
<p>Thank you,<br>{store_name}</p>',
    ]
);
```

Run: `php artisan db:seed --class=StockNotificationTemplateSeeder`

---

## Task 4: Storefront subscription controller + route

**Files:**
- Create: `app/Http/Controllers/Storefront/StockNotificationController.php`
- Modify: `routes/web.php`

**Controller:**

```php
<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\StockNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockNotificationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'variant_id' => ['nullable', 'exists:product_variants,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        // Check for duplicate
        $exists = StockNotification::where('email', $validated['email'])
            ->where('product_id', $validated['product_id'])
            ->where('product_variant_id', $validated['variant_id'] ?? null)
            ->where('status', 'waiting')
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => "You're already on the waitlist for this product.",
            ], 409);
        }

        StockNotification::create([
            'store_id' => 1,
            'product_id' => $validated['product_id'],
            'product_variant_id' => $validated['variant_id'] ?? null,
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        return response()->json([
            'success' => true,
            'message' => "We'll notify you when this item is back in stock!",
        ]);
    }
}
```

**Route** (add in storefront section of `routes/web.php`):

```php
use App\Http\Controllers\Storefront\StockNotificationController;

Route::post('/notify-stock', [StockNotificationController::class, 'store'])->name('notify-stock.store');
```

---

## Task 5: Product page — Notify Me button + modal (both themes)

**Files:**
- Modify: `resources/themes/stage-ready/views/product/show.blade.php`
- Modify: `resources/themes/clean-light/views/product/show.blade.php`
- Modify: `resources/themes/stage-ready/js/theme.js`
- Modify: `resources/themes/clean-light/js/theme.js`
- Modify: `resources/themes/stage-ready/css/theme.css`
- Modify: `resources/themes/clean-light/css/theme.css`

### JS changes (both theme.js files):

Add to the `addToCart` Alpine component data:

```js
notifyModal: false,
notifyName: '',
notifyEmail: '',
notifyLoading: false,
notifyMessage: '',
notifySuccess: false,
```

Add `init()` enhancement — pre-fill if customer logged in (pass from Blade via config):

```js
// In init()
this.notifyName = config.customerName || '';
this.notifyEmail = config.customerEmail || '';
```

Add computed property to check if current selection is out of stock:

```js
get isOutOfStock() {
    if (!config.trackStock) return false;
    if (this.hasVariants) {
        if (!this.activeVariant) return false;
        return parseInt(this.activeVariant.quantity) <= 0;
    }
    return parseInt(config.productQuantity) <= 0;
},
```

Add notify submit method:

```js
async submitNotify() {
    this.notifyLoading = true;
    this.notifyMessage = '';
    try {
        const resp = await fetch('/notify-stock', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                product_id: this.productId,
                variant_id: this.activeVariant?.variant_id || null,
                name: this.notifyName,
                email: this.notifyEmail,
            }),
        });
        const data = await resp.json();
        this.notifyMessage = data.message;
        this.notifySuccess = data.success !== false;
        if (this.notifySuccess) {
            setTimeout(() => { this.notifyModal = false; }, 2000);
        }
    } catch (e) {
        this.notifyMessage = 'Something went wrong. Please try again.';
        this.notifySuccess = false;
    }
    this.notifyLoading = false;
},
```

### Blade changes (both theme product show views):

Pass extra config to `addToCart`:

```blade
x-data="addToCart({
    productId: {{ $product->id }},
    hasVariants: {{ $product->has_variants ? 'true' : 'false' }},
    variantMatrix: {{ Js::from($variantMatrix) }},
    variantTypes: {{ Js::from($product->variantTypes->map(fn($t) => ['id' => $t->id])) }},
    basePrice: {{ $product->price }},
    trackStock: {{ $product->track_stock ? 'true' : 'false' }},
    productQuantity: {{ $product->quantity }},
    customerName: '{{ auth('customer')->user()?->first_name ?? '' }}',
    customerEmail: '{{ auth('customer')->user()?->email ?? '' }}',
})"
```

Replace the add-to-cart section (lines ~167-192) to conditionally show either add-to-cart or notify button:

```blade
{{-- Add to Cart / Notify Me --}}
<template x-if="!isOutOfStock">
    <div>
        <div x-show="quantity > 1" class="mb-3 text-sm text-[#999]">
            <span>Total: </span>
            <span class="text-lg text-[#E8E8E8]" style="font-family: var(--font-heading);">{{ $currencySymbol }}<span x-text="totalPrice.toFixed(2)"></span></span>
        </div>
        <div class="flex items-center gap-3 mb-6">
            <div class="cart-item-qty">
                <button type="button" class="qty-btn" @click="if(quantity > 1) quantity--">-</button>
                <input type="number" x-model.number="quantity" min="1" class="qty-input">
                <button type="button" class="qty-btn" @click="quantity++">+</button>
            </div>
            <button class="product-add-btn" @click="submit()" :disabled="loading">
                <!-- existing add-to-cart button content -->
            </button>
        </div>
    </div>
</template>

<template x-if="isOutOfStock">
    <div class="mb-6">
        <button class="product-notify-btn" @click="notifyModal = true">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            NOTIFY ME WHEN IN STOCK
        </button>
    </div>
</template>
```

Add the notify modal markup after the ATC message div:

```blade
{{-- Notify Me Modal --}}
<div x-show="notifyModal" x-cloak class="checkout-modal-overlay" @click.self="notifyModal = false">
    <div class="checkout-modal" @click.stop>
        <h2 class="checkout-modal-title">NOTIFY ME WHEN IN STOCK</h2>
        <p class="text-sm text-[#999] mb-4">
            We'll email you when
            <strong class="text-[#C8C8C8]" x-text="(hasVariants && activeVariant) ? activeVariant.name : '{{ $product->name }}'"></strong>
            is back in stock.
        </p>

        <div class="space-y-4">
            <div>
                <label class="account-label">Name</label>
                <input type="text" x-model="notifyName" class="account-input w-full" placeholder="Your name">
            </div>
            <div>
                <label class="account-label">Email</label>
                <input type="email" x-model="notifyEmail" class="account-input w-full" placeholder="your@email.com">
            </div>
        </div>

        <div x-show="notifyMessage" class="mt-3 text-sm" :class="notifySuccess ? 'text-emerald-400' : 'text-[var(--color-secondary)]'" x-text="notifyMessage"></div>

        <div class="checkout-modal-actions mt-6">
            <button @click="notifyModal = false" class="checkout-modal-btn-back">Cancel</button>
            <button @click="submitNotify()" class="checkout-modal-btn-confirm" :disabled="notifyLoading || !notifyName || !notifyEmail">
                <span x-show="!notifyLoading">Notify Me</span>
                <span x-show="notifyLoading">Submitting...</span>
            </button>
        </div>
    </div>
</div>
```

### CSS changes (both theme.css files):

Add to `@layer components`:

```css
/* Notify Me Button */
.product-notify-btn {
    font-family: var(--font-heading);
    letter-spacing: 0.15em;
    @apply w-full py-3.5 px-6 text-sm font-bold rounded transition-all duration-300 flex items-center justify-center gap-2;
    border: 2px solid var(--color-primary);
    color: var(--color-primary);
    background: transparent;
}

.product-notify-btn:hover {
    background: var(--color-primary);
    color: #0D0D0D;
}
```

For Clean Light, change `color: #0D0D0D` to `color: #FFFFFF` in the hover state.

---

## Task 6: Mailable class for stock notification

**Files:**
- Create: `app/Mail/StockNotificationMail.php`

```php
<?php

namespace App\Mail;

use App\Models\StockNotification;
use App\Models\StockNotificationTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StockNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $renderedSubject;
    public string $renderedBody;

    public function __construct(
        public StockNotification $notification,
        StockNotificationTemplate $template
    ) {
        $product = $notification->product;
        $variant = $notification->variant;

        $data = [
            'customer_name' => $notification->name,
            'product_name' => $product->name,
            'product_url' => route('product.show', $product->slug),
            'variant_name' => $variant ? ' — ' . $variant->name : '',
            'product_image' => $product->images->first()
                ? asset('storage/' . $product->images->first()->path)
                : '',
            'store_name' => app(\App\Services\ThemeService::class)->get('identity.site_name', 'Store'),
        ];

        $this->renderedSubject = $template->renderSubject($data);
        $this->renderedBody = $template->renderBody($data);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->renderedSubject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.stock-notification',
            with: [
                'body' => $this->renderedBody,
                'productName' => $this->notification->product->name,
            ],
        );
    }
}
```

**Email template view** — Create: `resources/views/emails/stock-notification.blade.php`

Follow the existing dark email template pattern (table-based layout, 600px max-width, #111111 background, #F5A623 accents). The body content is `{!! $body !!}` — rendered from the template with placeholders already replaced.

---

## Task 7: Cron command — `stock:notify`

**Files:**
- Create: `app/Console/Commands/NotifyStockSubscribers.php`
- Modify: `routes/console.php`

**Command:**

```php
<?php

namespace App\Console\Commands;

use App\Mail\StockNotificationMail;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockNotification;
use App\Models\StockNotificationTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotifyStockSubscribers extends Command
{
    protected $signature = 'stock:notify';
    protected $description = 'Send email notifications for products back in stock';

    public function handle(): int
    {
        $waiting = StockNotification::waiting()
            ->with(['product.images', 'variant'])
            ->get();

        if ($waiting->isEmpty()) {
            $this->info('No waiting subscribers.');
            return 0;
        }

        $template = StockNotificationTemplate::where('store_id', 1)->first();
        if (!$template) {
            $this->error('No email template found.');
            return 1;
        }

        $notified = 0;
        $failed = 0;

        // Group by product_id + product_variant_id
        $grouped = $waiting->groupBy(fn ($n) => $n->product_id . '_' . ($n->product_variant_id ?? 'base'));

        foreach ($grouped as $key => $subscribers) {
            $first = $subscribers->first();
            $product = $first->product;

            if (!$product || !$product->is_active) continue;

            // Check stock
            $inStock = false;
            if ($first->product_variant_id) {
                $variant = $first->variant;
                $inStock = $variant && $variant->quantity >= 1;
            } else {
                $inStock = !$product->track_stock || $product->quantity >= 1;
            }

            if (!$inStock) continue;

            foreach ($subscribers as $notification) {
                try {
                    Mail::to($notification->email)->send(
                        new StockNotificationMail($notification, $template)
                    );
                    $notification->update([
                        'status' => 'notified',
                        'notified_at' => now(),
                    ]);
                    $notified++;
                } catch (\Throwable $e) {
                    $notification->update(['status' => 'failed']);
                    $failed++;
                    Log::error("StockNotify: failed to send to {$notification->email}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->info("Done. Notified: {$notified}, Failed: {$failed}");
        return 0;
    }
}
```

**Schedule** (add to `routes/console.php`):

```php
Schedule::command('stock:notify')->everyFifteenMinutes();
```

---

## Task 8: Admin controller

**Files:**
- Create: `app/Http/Controllers/Admin/StockNotificationController.php`
- Modify: `routes/web.php`

**Controller methods:**

- `index(Request $request)` — List notifications. Accepts `?tab=waitlist|notified` (default: waitlist), search by product name or email. Loads analytics (most requested products). Also loads the email template.
- `destroy(StockNotification $notification)` — Delete a waitlist entry.
- `bulkDelete(Request $request)` — Delete multiple by IDs.
- `updateTemplate(Request $request)` — Save email template (subject + body).
- `sendTest(Request $request)` — Send test email to admin with sample data.

**Routes** (inside admin group):

```php
use App\Http\Controllers\Admin\StockNotificationController as AdminStockNotificationController;

Route::get('/stock-notifications', [AdminStockNotificationController::class, 'index'])->name('stock-notifications.index');
Route::delete('/stock-notifications/{stockNotification}', [AdminStockNotificationController::class, 'destroy'])->name('stock-notifications.destroy');
Route::post('/stock-notifications/bulk-delete', [AdminStockNotificationController::class, 'bulkDelete'])->name('stock-notifications.bulk-delete');
Route::post('/stock-notifications/template', [AdminStockNotificationController::class, 'updateTemplate'])->name('stock-notifications.update-template');
Route::post('/stock-notifications/test-email', [AdminStockNotificationController::class, 'sendTest'])->name('stock-notifications.send-test');
```

---

## Task 9: Admin view

**Files:**
- Create: `resources/views/admin/stock-notifications/index.blade.php`
- Modify: `resources/views/layouts/admin.blade.php` (add sidebar link)

**View structure:**

Three tabs managed by Alpine.js (`x-data="{ activeTab: '{{ session('tab', 'waitlist') }}' }"`):

**Waitlist tab:**
- Search bar (product name / email)
- Bulk delete checkbox + button
- Table: checkbox, Customer Name, Email, Product, Variant (or "—"), Date Requested
- Per-row: Delete button
- Pagination

**Notified tab:**
- Same table but with Date Notified column, no delete actions
- Pagination

**Email Template tab:**
- Subject input field
- Body textarea with TinyMCE
- Available placeholders display: `{customer_name}`, `{product_name}`, `{product_url}`, `{variant_name}`, `{product_image}`, `{store_name}`
- Save button
- Send Test button (sends to logged-in admin's email)

**Analytics section** (shown at bottom of Waitlist tab):
- "Most Requested Products" heading
- Table: Product Name, Total Requests, Currently Waiting
- Query: `stock_notifications` grouped by `product_id`, count total + count where `status=waiting`, order by total DESC, limit 10

**Sidebar link:** Add between "Shipping Zones" and "Settings":

```php
['route' => 'admin.stock-notifications.index', 'label' => 'Stock Alerts', 'icon' => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9', 'pattern' => 'admin.stock-notifications*'],
```

---

## Task 10: Build & verify

- `npm run build`
- `php artisan view:clear`
- `php artisan cache:clear`
- Test cron: `php artisan stock:notify`
- Verify storefront: visit an out-of-stock product, click "Notify Me", submit
- Verify admin: check waitlist, notified tabs, template editor
