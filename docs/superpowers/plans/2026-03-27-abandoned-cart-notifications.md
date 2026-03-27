# Abandoned Cart Notifications Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Send email notifications to registered customers who abandoned their carts, with configurable rules per customer group, template selection, and max send limits. Track all notifications and identify recovered carts.

**Architecture:** Two new tables (`abandoned_cart_rules`, `abandoned_cart_notifications`) with corresponding models. A console command `cart:notify` processes rules and sends emails via the existing `TemplateMailService`. Three new admin views (rules, notifications log, recovered carts) added as tabs on the existing abandoned carts page.

**Tech Stack:** Laravel 12, Eloquent, Blade, TemplateMailService, Artisan Console Commands

---

## File Structure

### New Files
| File | Responsibility |
|------|---------------|
| `database/migrations/2026_03_27_100001_create_abandoned_cart_rules_table.php` | Rules schema |
| `database/migrations/2026_03_27_100002_create_abandoned_cart_notifications_table.php` | Notification log schema |
| `app/Models/AbandonedCartRule.php` | Rule model with relationships |
| `app/Models/AbandonedCartNotification.php` | Notification log model |
| `app/Console/Commands/SendAbandonedCartNotifications.php` | Cron command |
| `resources/views/admin/abandoned-carts/rules.blade.php` | Rules CRUD view |
| `resources/views/admin/abandoned-carts/rule-form.blade.php` | Rule create/edit form |
| `resources/views/admin/abandoned-carts/notifications.blade.php` | Notification log view |
| `resources/views/admin/abandoned-carts/recovered.blade.php` | Recovered carts view |

### Modified Files
| File | Change |
|------|--------|
| `database/seeders/EmailTemplateSeeder.php` | Add 2 abandoned cart templates |
| `app/Http/Controllers/Admin/AbandonedCartController.php` | Add rules CRUD, notifications, recovered methods |
| `app/Http/Controllers/Admin/EmailTemplateController.php` | Add placeholder map + test data for new templates |
| `routes/web.php` | Add new admin routes |
| `resources/views/admin/abandoned-carts/index.blade.php` | Add tab navigation to link all sub-views |
| `resources/views/layouts/admin.blade.php` | Add Abandoned Carts sidebar link |

---

### Task 1: Migrations

**Files:**
- Create: `database/migrations/2026_03_27_100001_create_abandoned_cart_rules_table.php`
- Create: `database/migrations/2026_03_27_100002_create_abandoned_cart_notifications_table.php`

- [ ] **Step 1: Create abandoned_cart_rules migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abandoned_cart_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_template_id')->constrained('email_templates')->cascadeOnDelete();
            $table->foreignId('customer_group_id')->nullable()->constrained('customer_groups')->nullOnDelete();
            $table->unsignedInteger('max_sends')->default(1);
            $table->unsignedInteger('min_cart_age_hours')->default(1);
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abandoned_cart_rules');
    }
};
```

- [ ] **Step 2: Create abandoned_cart_notifications migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abandoned_cart_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('abandoned_cart_rule_id')->constrained('abandoned_cart_rules')->cascadeOnDelete();
            $table->foreignId('email_template_id')->constrained('email_templates')->cascadeOnDelete();
            $table->unsignedInteger('send_count')->default(0);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['cart_id', 'abandoned_cart_rule_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abandoned_cart_notifications');
    }
};
```

- [ ] **Step 3: Run migrations**

Run: `php artisan migrate`
Expected: Both tables created successfully.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_03_27_100001_create_abandoned_cart_rules_table.php database/migrations/2026_03_27_100002_create_abandoned_cart_notifications_table.php
git commit -m "feat(abandoned-cart): add rules and notifications migrations"
```

---

### Task 2: Models

**Files:**
- Create: `app/Models/AbandonedCartRule.php`
- Create: `app/Models/AbandonedCartNotification.php`

- [ ] **Step 1: Create AbandonedCartRule model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbandonedCartRule extends Model
{
    protected $fillable = [
        'store_id',
        'email_template_id',
        'customer_group_id',
        'max_sends',
        'min_cart_age_hours',
        'priority',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'max_sends' => 'integer',
            'min_cart_age_hours' => 'integer',
            'priority' => 'integer',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function emailTemplate(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class);
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(AbandonedCartNotification::class);
    }
}
```

- [ ] **Step 2: Create AbandonedCartNotification model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbandonedCartNotification extends Model
{
    protected $fillable = [
        'cart_id',
        'customer_id',
        'abandoned_cart_rule_id',
        'email_template_id',
        'send_count',
        'last_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'last_sent_at' => 'datetime',
            'send_count' => 'integer',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AbandonedCartRule::class, 'abandoned_cart_rule_id');
    }

    public function emailTemplate(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class);
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Models/AbandonedCartRule.php app/Models/AbandonedCartNotification.php
git commit -m "feat(abandoned-cart): add Rule and Notification models"
```

---

### Task 3: Email Templates

**Files:**
- Modify: `database/seeders/EmailTemplateSeeder.php`
- Modify: `app/Http/Controllers/Admin/EmailTemplateController.php`

- [ ] **Step 1: Add templates to EmailTemplateSeeder**

Add these two entries to the `$templates` array in `EmailTemplateSeeder::run()`, after the `low_stock_admin` entry:

```php
[
    'store_id' => 1,
    'slug' => 'abandoned_cart_reminder',
    'name' => 'Abandoned Cart Reminder',
    'subject' => 'You left something behind, {customer_name}!',
    'type' => 'customer',
    'body' => '<p>Hi {customer_name},</p>
<p>Looks like you left some items in your cart. No worries — they\'re still waiting for you!</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 20px 0; border-collapse: collapse;">
<tr>
<td style="padding: 12px 16px; background-color: #222222; border-radius: 6px;">
<p style="margin: 0 0 8px 0; font-size: 12px; font-weight: 700; color: #999999; text-transform: uppercase; letter-spacing: 1px;">Your Cart</p>
{cart_items}
<p style="margin: 12px 0 0 0; padding-top: 12px; border-top: 1px solid #333333; font-size: 16px; font-weight: 700; color: #F5A623;">Total: {cart_total}</p>
</td>
</tr>
</table>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin: 24px 0;">
<tr>
<td style="background-color: #F5A623; border-radius: 6px; padding: 12px 28px;">
<a href="{cart_url}" style="color: #111111; font-size: 15px; font-weight: 700; text-decoration: none; display: inline-block;">Complete Your Order</a>
</td>
</tr>
</table>
<p>Thank you,<br>{store_name}</p>',
],
[
    'store_id' => 1,
    'slug' => 'abandoned_cart_final',
    'name' => 'Abandoned Cart — Last Chance',
    'subject' => '{customer_name}, your cart is still waiting',
    'type' => 'customer',
    'body' => '<p>Hi {customer_name},</p>
<p>Just a heads up — these items are still in your cart and may sell out soon:</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 20px 0; border-collapse: collapse;">
<tr>
<td style="padding: 12px 16px; background-color: #222222; border-radius: 6px;">
<p style="margin: 0 0 8px 0; font-size: 12px; font-weight: 700; color: #999999; text-transform: uppercase; letter-spacing: 1px;">Your Cart</p>
{cart_items}
<p style="margin: 12px 0 0 0; padding-top: 12px; border-top: 1px solid #333333; font-size: 16px; font-weight: 700; color: #F5A623;">Total: {cart_total}</p>
</td>
</tr>
</table>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin: 24px 0;">
<tr>
<td style="background-color: #F5A623; border-radius: 6px; padding: 12px 28px;">
<a href="{cart_url}" style="color: #111111; font-size: 15px; font-weight: 700; text-decoration: none; display: inline-block;">Complete Your Order</a>
</td>
</tr>
</table>
<p>Don\'t miss out!</p>
<p>Thank you,<br>{store_name}</p>',
],
```

- [ ] **Step 2: Add placeholder map and test data to EmailTemplateController**

In the `PLACEHOLDER_MAP` constant, add:

```php
'abandoned_cart_reminder' => ['customer_name', 'cart_items', 'cart_total', 'cart_url', 'store_name'],
'abandoned_cart_final' => ['customer_name', 'cart_items', 'cart_total', 'cart_url', 'store_name'],
```

In the `TEST_DATA` constant, add:

```php
'cart_items' => '<p style="margin: 4px 0; font-size: 14px; color: #e8e8e8;">1x Boss DS-1 Distortion Pedal — 2,499.00</p><p style="margin: 4px 0; font-size: 14px; color: #e8e8e8;">1x Mogami Gold Cable 10ft — 2,100.00</p>',
'cart_total' => '4,599.00',
'cart_url' => '#',
```

- [ ] **Step 3: Run seeder**

Run: `php artisan db:seed --class=EmailTemplateSeeder`
Expected: Two new templates created (existing templates unchanged due to `updateOrCreate`).

- [ ] **Step 4: Commit**

```bash
git add database/seeders/EmailTemplateSeeder.php app/Http/Controllers/Admin/EmailTemplateController.php
git commit -m "feat(abandoned-cart): add email templates and placeholder config"
```

---

### Task 4: Console Command

**Files:**
- Create: `app/Console/Commands/SendAbandonedCartNotifications.php`

- [ ] **Step 1: Create the command**

```php
<?php

namespace App\Console\Commands;

use App\Models\AbandonedCartNotification;
use App\Models\AbandonedCartRule;
use App\Models\Cart;
use App\Models\OrderProduct;
use App\Models\Setting;
use App\Services\TemplateMailService;
use Illuminate\Console\Command;

class SendAbandonedCartNotifications extends Command
{
    protected $signature = 'cart:notify';
    protected $description = 'Send abandoned cart notification emails based on configured rules';

    public function handle(): int
    {
        $rules = AbandonedCartRule::where('is_active', true)
            ->with('emailTemplate')
            ->orderByDesc('priority')
            ->get();

        if ($rules->isEmpty()) {
            $this->info('No active abandoned cart rules configured.');
            return self::SUCCESS;
        }

        $storeName = Setting::where('group', 'identity')
            ->where('key', 'site_name')
            ->value('value') ?: config('app.name');

        $cartUrl = rtrim(config('app.url'), '/') . '/cart';

        $sent = 0;
        $skippedMaxed = 0;
        $skippedRecovered = 0;

        // Get all abandoned carts for registered customers
        $carts = Cart::whereNotNull('customer_id')
            ->whereHas('items')
            ->with(['customer', 'items'])
            ->get();

        foreach ($carts as $cart) {
            $customer = $cart->customer;
            if (!$customer || !$customer->email) {
                continue;
            }

            // Find the first matching rule by priority
            $matchedRule = null;
            foreach ($rules as $rule) {
                // Check cart age
                $ageHours = now()->diffInHours($cart->updated_at);
                if ($ageHours < $rule->min_cart_age_hours) {
                    continue;
                }

                // Check customer group
                if ($rule->customer_group_id !== null && $customer->customer_group_id !== $rule->customer_group_id) {
                    continue;
                }

                $matchedRule = $rule;
                break;
            }

            if (!$matchedRule) {
                continue;
            }

            // Check if already maxed out for this rule
            $notification = AbandonedCartNotification::where('cart_id', $cart->id)
                ->where('abandoned_cart_rule_id', $matchedRule->id)
                ->first();

            if ($notification && $notification->send_count >= $matchedRule->max_sends) {
                $skippedMaxed++;
                continue;
            }

            // Check if recovered — customer ordered any of the same products after cart was last updated
            $cartProductIds = $cart->items->pluck('product_id')->unique()->toArray();
            $recovered = OrderProduct::whereHas('order', function ($q) use ($customer, $cart) {
                $q->where('customer_id', $customer->id)
                  ->where('created_at', '>', $cart->updated_at);
            })->whereIn('product_id', $cartProductIds)->exists();

            if ($recovered) {
                $skippedRecovered++;
                continue;
            }

            // Build cart items HTML
            $cartItemsHtml = $cart->items->map(function ($item) {
                $label = $item->name;
                if ($item->variant_label) {
                    $label .= ' — ' . $item->variant_label;
                }
                return '<p style="margin: 4px 0; font-size: 14px; color: #e8e8e8;">'
                    . e($item->quantity) . 'x ' . e($label) . ' — '
                    . number_format($item->price * $item->quantity, 2)
                    . '</p>';
            })->implode('');

            $cartTotal = number_format($cart->total, 2);

            // Send email
            $success = TemplateMailService::send($matchedRule->emailTemplate->slug, $customer->email, [
                'customer_name' => $customer->full_name ?: $customer->first_name,
                'cart_items' => $cartItemsHtml,
                'cart_total' => $cartTotal,
                'cart_url' => $cartUrl,
                'store_name' => $storeName,
            ]);

            if ($success) {
                AbandonedCartNotification::updateOrCreate(
                    [
                        'cart_id' => $cart->id,
                        'abandoned_cart_rule_id' => $matchedRule->id,
                    ],
                    [
                        'customer_id' => $customer->id,
                        'email_template_id' => $matchedRule->email_template_id,
                        'send_count' => ($notification ? $notification->send_count : 0) + 1,
                        'last_sent_at' => now(),
                    ]
                );
                $sent++;
            }
        }

        $this->info("Abandoned cart notifications: {$sent} sent, {$skippedMaxed} maxed out, {$skippedRecovered} recovered.");
        return self::SUCCESS;
    }
}
```

- [ ] **Step 2: Verify command registers**

Run: `php artisan cart:notify`
Expected: "No active abandoned cart rules configured." (no rules exist yet)

- [ ] **Step 3: Commit**

```bash
git add app/Console/Commands/SendAbandonedCartNotifications.php
git commit -m "feat(abandoned-cart): add cart:notify console command"
```

---

### Task 5: Routes & Controller Methods

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Admin/AbandonedCartController.php`

- [ ] **Step 1: Add routes to web.php**

Add after the existing abandoned cart routes (after line 278):

```php
    // Abandoned Cart Rules & Notifications
    Route::get('/abandoned-carts/rules', [\App\Http\Controllers\Admin\AbandonedCartController::class, 'rules'])->name('abandoned-carts.rules');
    Route::get('/abandoned-carts/rules/create', [\App\Http\Controllers\Admin\AbandonedCartController::class, 'createRule'])->name('abandoned-carts.rules.create');
    Route::post('/abandoned-carts/rules', [\App\Http\Controllers\Admin\AbandonedCartController::class, 'storeRule'])->name('abandoned-carts.rules.store');
    Route::get('/abandoned-carts/rules/{rule}/edit', [\App\Http\Controllers\Admin\AbandonedCartController::class, 'editRule'])->name('abandoned-carts.rules.edit');
    Route::put('/abandoned-carts/rules/{rule}', [\App\Http\Controllers\Admin\AbandonedCartController::class, 'updateRule'])->name('abandoned-carts.rules.update');
    Route::delete('/abandoned-carts/rules/{rule}', [\App\Http\Controllers\Admin\AbandonedCartController::class, 'destroyRule'])->name('abandoned-carts.rules.destroy');
    Route::get('/abandoned-carts/notifications', [\App\Http\Controllers\Admin\AbandonedCartController::class, 'notifications'])->name('abandoned-carts.notifications');
    Route::get('/abandoned-carts/recovered', [\App\Http\Controllers\Admin\AbandonedCartController::class, 'recovered'])->name('abandoned-carts.recovered');
```

**Important:** These routes must come BEFORE the `{cart}` wildcard route to avoid conflicts. Move them above `Route::get('/abandoned-carts/{cart}', ...)`.

- [ ] **Step 2: Add controller methods**

Replace the entire `AbandonedCartController.php` with:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AbandonedCartNotification;
use App\Models\AbandonedCartRule;
use App\Models\Cart;
use App\Models\CustomerGroup;
use App\Models\EmailTemplate;
use App\Models\OrderProduct;
use Illuminate\Http\Request;

class AbandonedCartController extends Controller
{
    public function index(Request $request)
    {
        $query = Cart::with(['customer', 'items.product'])
            ->whereHas('items')
            ->where('updated_at', '<', now()->subHour());

        if ($search = $request->input('search')) {
            $query->whereHas('customer', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->input('type') === 'guest') {
            $query->whereNull('customer_id');
        } elseif ($request->input('type') === 'registered') {
            $query->whereNotNull('customer_id');
        }

        $carts = $query->orderByDesc('updated_at')->paginate(25)->withQueryString();

        $totalAbandoned = Cart::whereHas('items')->where('updated_at', '<', now()->subHour())->count();
        $totalValue = Cart::whereHas('items')
            ->where('updated_at', '<', now()->subHour())
            ->with('items')
            ->get()
            ->sum(fn ($cart) => $cart->total);

        return view('admin.abandoned-carts.index', compact('carts', 'totalAbandoned', 'totalValue'));
    }

    public function show(Cart $cart)
    {
        $cart->load(['customer', 'items.product']);

        return view('admin.abandoned-carts.show', compact('cart'));
    }

    public function destroy(Cart $cart)
    {
        $cart->items()->delete();
        $cart->delete();

        return redirect()->route('admin.abandoned-carts.index')->with('success', 'Abandoned cart deleted.');
    }

    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return back()->with('error', 'No carts selected.');
        }

        Cart::whereIn('id', $ids)->each(function ($cart) {
            $cart->items()->delete();
            $cart->delete();
        });

        return back()->with('success', count($ids) . ' abandoned cart(s) deleted.');
    }

    // --- Rules ---

    public function rules()
    {
        $rules = AbandonedCartRule::with(['emailTemplate', 'customerGroup'])
            ->orderByDesc('priority')
            ->get();

        return view('admin.abandoned-carts.rules', compact('rules'));
    }

    public function createRule()
    {
        $templates = EmailTemplate::where('is_active', true)->orderBy('name')->get();
        $customerGroups = CustomerGroup::orderBy('name')->get();

        return view('admin.abandoned-carts.rule-form', [
            'rule' => null,
            'templates' => $templates,
            'customerGroups' => $customerGroups,
        ]);
    }

    public function storeRule(Request $request)
    {
        $validated = $request->validate([
            'email_template_id' => 'required|exists:email_templates,id',
            'customer_group_id' => 'nullable|exists:customer_groups,id',
            'max_sends' => 'required|integer|min:1|max:100',
            'min_cart_age_hours' => 'required|integer|min:1|max:8760',
            'priority' => 'required|integer|min:0|max:999',
            'is_active' => 'boolean',
        ]);

        $validated['store_id'] = 1;
        $validated['is_active'] = $request->boolean('is_active');

        AbandonedCartRule::create($validated);

        return redirect()->route('admin.abandoned-carts.rules')->with('success', 'Rule created.');
    }

    public function editRule(AbandonedCartRule $rule)
    {
        $templates = EmailTemplate::where('is_active', true)->orderBy('name')->get();
        $customerGroups = CustomerGroup::orderBy('name')->get();

        return view('admin.abandoned-carts.rule-form', compact('rule', 'templates', 'customerGroups'));
    }

    public function updateRule(Request $request, AbandonedCartRule $rule)
    {
        $validated = $request->validate([
            'email_template_id' => 'required|exists:email_templates,id',
            'customer_group_id' => 'nullable|exists:customer_groups,id',
            'max_sends' => 'required|integer|min:1|max:100',
            'min_cart_age_hours' => 'required|integer|min:1|max:8760',
            'priority' => 'required|integer|min:0|max:999',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $rule->update($validated);

        return redirect()->route('admin.abandoned-carts.rules')->with('success', 'Rule updated.');
    }

    public function destroyRule(AbandonedCartRule $rule)
    {
        $rule->delete();

        return redirect()->route('admin.abandoned-carts.rules')->with('success', 'Rule deleted.');
    }

    // --- Notification Log ---

    public function notifications(Request $request)
    {
        $query = AbandonedCartNotification::with(['customer', 'cart.items', 'rule', 'emailTemplate'])
            ->orderByDesc('last_sent_at');

        if ($templateId = $request->input('template')) {
            $query->where('email_template_id', $templateId);
        }

        $notifications = $query->paginate(25)->withQueryString();

        $templates = EmailTemplate::whereIn('slug', ['abandoned_cart_reminder', 'abandoned_cart_final'])
            ->orderBy('name')
            ->get();

        return view('admin.abandoned-carts.notifications', compact('notifications', 'templates'));
    }

    // --- Recovered Carts ---

    public function recovered()
    {
        // Get all notifications that were sent
        $notifications = AbandonedCartNotification::where('send_count', '>', 0)
            ->with(['customer', 'cart.items'])
            ->get();

        $recoveredCarts = [];

        foreach ($notifications as $notification) {
            $cart = $notification->cart;
            $customer = $notification->customer;

            if (!$cart || !$customer) {
                continue;
            }

            $cartProductIds = $cart->items->pluck('product_id')->unique()->toArray();

            if (empty($cartProductIds)) {
                continue;
            }

            // Find order with matching products placed after last notification
            $matchingOrderProduct = OrderProduct::whereHas('order', function ($q) use ($customer, $notification) {
                $q->where('customer_id', $customer->id)
                  ->where('created_at', '>', $notification->last_sent_at);
            })->whereIn('product_id', $cartProductIds)->with('order')->first();

            if ($matchingOrderProduct) {
                $recoveredCarts[] = [
                    'customer' => $customer,
                    'cart_items' => $cart->items->pluck('name')->implode(', '),
                    'send_count' => $notification->send_count,
                    'last_sent_at' => $notification->last_sent_at,
                    'order' => $matchingOrderProduct->order,
                    'recovery_time' => $notification->last_sent_at->diffForHumans($matchingOrderProduct->order->created_at, true),
                ];
            }
        }

        return view('admin.abandoned-carts.recovered', compact('recoveredCarts'));
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add routes/web.php app/Http/Controllers/Admin/AbandonedCartController.php
git commit -m "feat(abandoned-cart): add routes and controller methods for rules, notifications, recovered"
```

---

### Task 6: Admin Views — Tab Navigation on Index

**Files:**
- Modify: `resources/views/admin/abandoned-carts/index.blade.php`

- [ ] **Step 1: Add tab navigation to index view**

Add a tab bar at the top of the index view (after `<x-slot name="header">Abandoned Carts</x-slot>` and before the summary cards). This same tab bar will be included on all abandoned cart sub-views for consistent navigation.

```blade
{{-- Tab Navigation --}}
<div class="mb-6 border-b border-gray-200">
    <nav class="-mb-px flex space-x-8">
        <a href="{{ route('admin.abandoned-carts.index') }}"
           class="py-3 px-1 border-b-2 text-sm font-medium {{ request()->routeIs('admin.abandoned-carts.index') ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
            Carts
        </a>
        <a href="{{ route('admin.abandoned-carts.rules') }}"
           class="py-3 px-1 border-b-2 text-sm font-medium {{ request()->routeIs('admin.abandoned-carts.rules') ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
            Rules
        </a>
        <a href="{{ route('admin.abandoned-carts.notifications') }}"
           class="py-3 px-1 border-b-2 text-sm font-medium {{ request()->routeIs('admin.abandoned-carts.notifications') ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
            Notifications
        </a>
        <a href="{{ route('admin.abandoned-carts.recovered') }}"
           class="py-3 px-1 border-b-2 text-sm font-medium {{ request()->routeIs('admin.abandoned-carts.recovered') ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
            Recovered
        </a>
    </nav>
</div>
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/admin/abandoned-carts/index.blade.php
git commit -m "feat(abandoned-cart): add tab navigation to index view"
```

---

### Task 7: Admin Views — Rules

**Files:**
- Create: `resources/views/admin/abandoned-carts/rules.blade.php`
- Create: `resources/views/admin/abandoned-carts/rule-form.blade.php`

- [ ] **Step 1: Create rules list view**

```blade
<x-admin-layout>
    <x-slot name="header">Abandoned Carts</x-slot>

    {{-- Tab Navigation (same as index) --}}
    <div class="mb-6 border-b border-gray-200">
        <nav class="-mb-px flex space-x-8">
            <a href="{{ route('admin.abandoned-carts.index') }}" class="py-3 px-1 border-b-2 text-sm font-medium border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">Carts</a>
            <a href="{{ route('admin.abandoned-carts.rules') }}" class="py-3 px-1 border-b-2 text-sm font-medium border-indigo-500 text-indigo-600">Rules</a>
            <a href="{{ route('admin.abandoned-carts.notifications') }}" class="py-3 px-1 border-b-2 text-sm font-medium border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">Notifications</a>
            <a href="{{ route('admin.abandoned-carts.recovered') }}" class="py-3 px-1 border-b-2 text-sm font-medium border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">Recovered</a>
        </nav>
    </div>

    <div class="flex justify-between items-center mb-4">
        <p class="text-sm text-gray-500">Rules determine which template to send, to which customer group, and how often.</p>
        <a href="{{ route('admin.abandoned-carts.rules.create') }}" class="px-4 py-2 bg-gray-800 text-white text-sm rounded-lg hover:bg-gray-700 transition-colors">Create Rule</a>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Template</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer Group</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Max Sends</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Min Age</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse ($rules as $rule)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $rule->priority }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900">{{ $rule->emailTemplate->name ?? '—' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $rule->customerGroup->name ?? 'All Groups' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $rule->max_sends }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $rule->min_cart_age_hours }}h</td>
                        <td class="px-6 py-4">
                            @if ($rule->is_active)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Inactive</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right text-sm space-x-3">
                            <a href="{{ route('admin.abandoned-carts.rules.edit', $rule) }}" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                            <form method="POST" action="{{ route('admin.abandoned-carts.rules.destroy', $rule) }}" class="inline" onsubmit="return confirm('Delete this rule?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">No rules configured yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
```

- [ ] **Step 2: Create rule form view**

```blade
<x-admin-layout>
    <x-slot name="header">{{ $rule ? 'Edit Rule' : 'Create Rule' }}</x-slot>

    <div class="max-w-2xl">
        <form method="POST" action="{{ $rule ? route('admin.abandoned-carts.rules.update', $rule) : route('admin.abandoned-carts.rules.store') }}" class="space-y-6">
            @csrf
            @if ($rule) @method('PUT') @endif

            <div>
                <label for="email_template_id" class="block text-sm font-medium text-gray-700 mb-1">Email Template</label>
                <select name="email_template_id" id="email_template_id" required class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Select template...</option>
                    @foreach ($templates as $template)
                        <option value="{{ $template->id }}" {{ old('email_template_id', $rule?->email_template_id) == $template->id ? 'selected' : '' }}>
                            {{ $template->name }}
                        </option>
                    @endforeach
                </select>
                @error('email_template_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="customer_group_id" class="block text-sm font-medium text-gray-700 mb-1">Customer Group</label>
                <select name="customer_group_id" id="customer_group_id" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Groups</option>
                    @foreach ($customerGroups as $group)
                        <option value="{{ $group->id }}" {{ old('customer_group_id', $rule?->customer_group_id) == $group->id ? 'selected' : '' }}>
                            {{ $group->name }}
                        </option>
                    @endforeach
                </select>
                @error('customer_group_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label for="max_sends" class="block text-sm font-medium text-gray-700 mb-1">Max Sends</label>
                    <input type="number" name="max_sends" id="max_sends" min="1" max="100" value="{{ old('max_sends', $rule?->max_sends ?? 1) }}" required class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="mt-1 text-xs text-gray-400">Per cart, per rule</p>
                    @error('max_sends') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="min_cart_age_hours" class="block text-sm font-medium text-gray-700 mb-1">Min Cart Age (hours)</label>
                    <input type="number" name="min_cart_age_hours" id="min_cart_age_hours" min="1" max="8760" value="{{ old('min_cart_age_hours', $rule?->min_cart_age_hours ?? 1) }}" required class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="mt-1 text-xs text-gray-400">How old before first send</p>
                    @error('min_cart_age_hours') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="priority" class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                    <input type="number" name="priority" id="priority" min="0" max="999" value="{{ old('priority', $rule?->priority ?? 0) }}" required class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="mt-1 text-xs text-gray-400">Higher = checked first</p>
                    @error('priority') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex items-center">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $rule?->is_active ?? true) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <label for="is_active" class="ml-2 text-sm text-gray-700">Active</label>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm rounded-lg hover:bg-gray-700 transition-colors">
                    {{ $rule ? 'Update Rule' : 'Create Rule' }}
                </button>
                <a href="{{ route('admin.abandoned-carts.rules') }}" class="px-4 py-2 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300 transition-colors">Cancel</a>
            </div>
        </form>
    </div>
</x-admin-layout>
```

- [ ] **Step 3: Commit**

```bash
git add resources/views/admin/abandoned-carts/rules.blade.php resources/views/admin/abandoned-carts/rule-form.blade.php
git commit -m "feat(abandoned-cart): add rules list and form views"
```

---

### Task 8: Admin Views — Notifications & Recovered

**Files:**
- Create: `resources/views/admin/abandoned-carts/notifications.blade.php`
- Create: `resources/views/admin/abandoned-carts/recovered.blade.php`

- [ ] **Step 1: Create notifications view**

```blade
<x-admin-layout>
    <x-slot name="header">Abandoned Carts</x-slot>

    {{-- Tab Navigation --}}
    <div class="mb-6 border-b border-gray-200">
        <nav class="-mb-px flex space-x-8">
            <a href="{{ route('admin.abandoned-carts.index') }}" class="py-3 px-1 border-b-2 text-sm font-medium border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">Carts</a>
            <a href="{{ route('admin.abandoned-carts.rules') }}" class="py-3 px-1 border-b-2 text-sm font-medium border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">Rules</a>
            <a href="{{ route('admin.abandoned-carts.notifications') }}" class="py-3 px-1 border-b-2 text-sm font-medium border-indigo-500 text-indigo-600">Notifications</a>
            <a href="{{ route('admin.abandoned-carts.recovered') }}" class="py-3 px-1 border-b-2 text-sm font-medium border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">Recovered</a>
        </nav>
    </div>

    {{-- Filter --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
        <form method="GET" action="{{ route('admin.abandoned-carts.notifications') }}" class="flex items-end gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Template</label>
                <select name="template" class="rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Templates</option>
                    @foreach ($templates as $template)
                        <option value="{{ $template->id }}" {{ request('template') == $template->id ? 'selected' : '' }}>{{ $template->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm rounded-lg hover:bg-gray-700 transition-colors">Filter</button>
            <a href="{{ route('admin.abandoned-carts.notifications') }}" class="px-4 py-2 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300 transition-colors">Clear</a>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cart</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Template</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sends</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Sent</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse ($notifications as $notification)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            @if ($notification->customer)
                                <div class="text-sm font-medium text-gray-900">{{ $notification->customer->full_name }}</div>
                                <div class="text-xs text-gray-400">{{ $notification->customer->email }}</div>
                            @else
                                <span class="text-sm text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if ($notification->cart)
                                <a href="{{ route('admin.abandoned-carts.show', $notification->cart_id) }}" class="text-sm text-indigo-600 hover:text-indigo-900">
                                    #{{ $notification->cart_id }} ({{ $notification->cart->items->count() }} items)
                                </a>
                            @else
                                <span class="text-sm text-gray-400">Deleted</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $notification->emailTemplate->name ?? '—' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900">{{ $notification->send_count }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $notification->last_sent_at?->diffForHumans() ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">No notifications sent yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($notifications->hasPages())
            <div class="px-6 py-3 border-t border-gray-200">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</x-admin-layout>
```

- [ ] **Step 2: Create recovered carts view**

```blade
<x-admin-layout>
    <x-slot name="header">Abandoned Carts</x-slot>

    {{-- Tab Navigation --}}
    <div class="mb-6 border-b border-gray-200">
        <nav class="-mb-px flex space-x-8">
            <a href="{{ route('admin.abandoned-carts.index') }}" class="py-3 px-1 border-b-2 text-sm font-medium border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">Carts</a>
            <a href="{{ route('admin.abandoned-carts.rules') }}" class="py-3 px-1 border-b-2 text-sm font-medium border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">Rules</a>
            <a href="{{ route('admin.abandoned-carts.notifications') }}" class="py-3 px-1 border-b-2 text-sm font-medium border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">Notifications</a>
            <a href="{{ route('admin.abandoned-carts.recovered') }}" class="py-3 px-1 border-b-2 text-sm font-medium border-indigo-500 text-indigo-600">Recovered</a>
        </nav>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cart Items</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notified</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recovery Time</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse ($recoveredCarts as $recovered)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900">{{ $recovered['customer']->full_name }}</div>
                            <div class="text-xs text-gray-400">{{ $recovered['customer']->email }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ Str::limit($recovered['cart_items'], 80) }}</td>
                        <td class="px-6 py-4">
                            <div class="text-sm text-gray-900">{{ $recovered['send_count'] }}x</div>
                            <div class="text-xs text-gray-400">Last: {{ $recovered['last_sent_at']->diffForHumans() }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <a href="{{ route('admin.orders.show', $recovered['order']) }}" class="text-sm text-indigo-600 hover:text-indigo-900">
                                #{{ $recovered['order']->id }}
                            </a>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $recovered['recovery_time'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">No recovered carts yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
```

- [ ] **Step 3: Commit**

```bash
git add resources/views/admin/abandoned-carts/notifications.blade.php resources/views/admin/abandoned-carts/recovered.blade.php
git commit -m "feat(abandoned-cart): add notifications log and recovered carts views"
```

---

### Task 9: Sidebar Link & Build

**Files:**
- Modify: `resources/views/layouts/admin.blade.php`

- [ ] **Step 1: Add Abandoned Carts to sidebar**

In `resources/views/layouts/admin.blade.php`, in the Extensions group children array (around line 82, after the Shipping link), add:

```php
$link('admin.abandoned-carts.index', 'Abandoned Carts', 'M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z', 'admin.abandoned-carts*'),
```

- [ ] **Step 2: Run build and clear views**

Run: `npm run build && php artisan view:clear`

- [ ] **Step 3: Commit**

```bash
git add resources/views/layouts/admin.blade.php
git commit -m "feat(abandoned-cart): add sidebar link and build"
```

---

### Task 10: Integration Test

- [ ] **Step 1: Verify migrations ran**

Run: `php artisan migrate:status | grep abandoned`
Expected: Both `abandoned_cart_rules` and `abandoned_cart_notifications` migrations show as ran.

- [ ] **Step 2: Seed email templates**

Run: `php artisan db:seed --class=EmailTemplateSeeder`
Expected: Templates created/updated without errors.

- [ ] **Step 3: Verify admin pages load**

Open in browser:
- `/admin/abandoned-carts` — should show tab navigation with Carts, Rules, Notifications, Recovered
- `/admin/abandoned-carts/rules` — should show empty rules list with Create Rule button
- `/admin/abandoned-carts/rules/create` — should show form with template dropdown, customer group, etc.
- `/admin/abandoned-carts/notifications` — should show empty notifications list
- `/admin/abandoned-carts/recovered` — should show empty recovered list

- [ ] **Step 4: Create a test rule and run the command**

1. Create a rule via `/admin/abandoned-carts/rules/create` (pick the Abandoned Cart Reminder template, All Groups, max 1, min age 1h, priority 0, active)
2. Run: `php artisan cart:notify`
3. Check output for send count
4. Verify notification appears in `/admin/abandoned-carts/notifications`

- [ ] **Step 5: Final commit**

```bash
git add -A
git commit -m "feat(abandoned-cart): complete abandoned cart notification system"
```
