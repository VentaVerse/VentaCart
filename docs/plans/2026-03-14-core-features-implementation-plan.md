# Core Features Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Implement 4 core features: theme system refactor (multi-theme support), customer impersonation, weight-based shipping with zones and customer group pricing, and checkout confirmation modal.

**Architecture:** Theme refactor creates a folder-based theme system with view resolution fallback and per-theme settings. Customer impersonation uses dual-guard sessions. Weight-based shipping plugs into the existing ShippingProviderInterface with zone/rate DB tables. Checkout modal is an Alpine.js gate before form submission.

**Tech Stack:** Laravel 12 / PHP 8.2 / MySQL / Alpine.js / Tailwind CSS / Vite

**Reference docs:**
- Design: `docs/plans/2026-03-14-core-features-design.md`

---

## PHASE 1: THEME SYSTEM REFACTOR

### Task 1: Theme folder structure and move Stage Ready views

**Files:**
- Create: `resources/themes/stage-ready/theme.json`
- Create: `resources/themes/stage-ready/css/theme.css` (move from `resources/css/storefront.css`)
- Create: `resources/themes/stage-ready/js/theme.js` (move from `resources/js/storefront.js`)
- Move: all `resources/views/storefront/` views → `resources/themes/stage-ready/views/`

**Step 1:** Create theme directory structure:
```
resources/themes/stage-ready/
├── theme.json
├── css/
│   └── theme.css
├── js/
│   └── theme.js
└── views/
    ├── layouts/
    ├── home/
    ├── product/
    ├── category/
    ├── cart/
    ├── checkout/
    ├── account/
    ├── blog/
    └── partials/
```

**Step 2:** Create `resources/themes/stage-ready/theme.json`:
```json
{
    "name": "Stage Ready",
    "version": "1.0",
    "author": "NewGearDay",
    "description": "Dark pedalboard theme with amber glow effects, noise texture, and LED indicators",
    "thumbnail": "preview.png",
    "settings": {
        "glow_intensity": {
            "type": "select",
            "label": "Glow Intensity",
            "options": ["low", "medium", "high"],
            "default": "medium"
        },
        "noise_texture": {
            "type": "toggle",
            "label": "Noise Texture Overlay",
            "default": true
        },
        "led_indicators": {
            "type": "toggle",
            "label": "LED Indicator Dots",
            "default": true
        },
        "scroll_reveal": {
            "type": "toggle",
            "label": "Scroll Reveal Animations",
            "default": true
        }
    }
}
```

**Step 3:** Move storefront views:
- Move `resources/views/storefront/*` → `resources/themes/stage-ready/views/`
- Keep `resources/views/storefront/` as an empty fallback directory (or remove if using theme-only resolution)

**Step 4:** Move storefront CSS:
- Copy `resources/css/storefront.css` → `resources/themes/stage-ready/css/theme.css`
- Remove the old file after verification

**Step 5:** Move storefront JS:
- Copy `resources/js/storefront.js` → `resources/themes/stage-ready/js/theme.js`
- Remove the old file after verification

---

### Task 2: Update ThemeService for multi-theme support

**Files:**
- Modify: `app/Services/ThemeService.php`

**Step 1:** Add theme discovery and active theme resolution to ThemeService:

```php
/**
 * Get the active theme slug.
 */
public function getActiveTheme(): string
{
    return $this->get('identity.active_theme', 'stage-ready');
}

/**
 * Get the path to the active theme directory.
 */
public function getThemePath(?string $theme = null): string
{
    $theme = $theme ?? $this->getActiveTheme();
    return resource_path("themes/{$theme}");
}

/**
 * Get all installed themes by scanning the themes directory.
 */
public function getInstalledThemes(): array
{
    $themesPath = resource_path('themes');
    if (!is_dir($themesPath)) {
        return [];
    }

    $themes = [];
    foreach (scandir($themesPath) as $dir) {
        if ($dir === '.' || $dir === '..') continue;
        $jsonPath = "{$themesPath}/{$dir}/theme.json";
        if (file_exists($jsonPath)) {
            $meta = json_decode(file_get_contents($jsonPath), true);
            $meta['slug'] = $dir;
            $themes[$dir] = $meta;
        }
    }

    return $themes;
}

/**
 * Get theme-specific settings schema from theme.json.
 */
public function getThemeSettingsSchema(?string $theme = null): array
{
    $theme = $theme ?? $this->getActiveTheme();
    $jsonPath = $this->getThemePath($theme) . '/theme.json';

    if (!file_exists($jsonPath)) {
        return [];
    }

    $meta = json_decode(file_get_contents($jsonPath), true);
    return $meta['settings'] ?? [];
}

/**
 * Get a theme-specific setting value.
 */
public function getThemeSetting(string $key, mixed $default = null): mixed
{
    $theme = $this->getActiveTheme();
    return $this->get("theme_settings.{$theme}_{$key}", $default);
}

/**
 * Set a theme-specific setting.
 */
public function setThemeSetting(string $key, mixed $value): void
{
    $theme = $this->getActiveTheme();
    $this->set('theme_settings', "{$theme}_{$key}", $value);
}
```

---

### Task 3: Update view resolution — StoreLayout component

**Files:**
- Modify: `app/View/Components/StoreLayout.php`
- Modify: `app/Providers/AppServiceProvider.php` (or create a ThemeServiceProvider)

**Step 1:** Register a dynamic view namespace in AppServiceProvider `boot()`:

```php
use App\Services\ThemeService;
use Illuminate\Support\Facades\View;

public function boot(): void
{
    // ... existing boot code ...

    // Register theme view namespace
    $this->app->booted(function () {
        $theme = app(ThemeService::class)->getActiveTheme();
        $themePath = resource_path("themes/{$theme}/views");

        if (is_dir($themePath)) {
            // Prepend theme views so they take priority
            View::getFinder()->prependLocation($themePath);
        }
    });
}
```

**Step 2:** Update StoreLayout component render method to use the theme layout:

```php
public function render(): \Illuminate\Contracts\View\View
{
    $theme = $this->theme->getActiveTheme();
    $themeView = "layouts.store";

    return view($themeView);
}
```

Since we prepend the theme views directory, Laravel's view finder will automatically check `resources/themes/{active}/views/layouts/store.blade.php` before any other location.

---

### Task 4: Update Vite config for theme assets

**Files:**
- Modify: `vite.config.js`
- Modify: `resources/themes/stage-ready/views/layouts/store.blade.php` (update @vite references)

**Step 1:** Update `vite.config.js` to compile theme assets:

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import fs from 'fs';
import path from 'path';

// Discover theme entry points
function getThemeEntries() {
    const themesDir = path.resolve(__dirname, 'resources/themes');
    const entries = [];

    if (fs.existsSync(themesDir)) {
        for (const theme of fs.readdirSync(themesDir)) {
            const cssPath = `resources/themes/${theme}/css/theme.css`;
            const jsPath = `resources/themes/${theme}/js/theme.js`;
            if (fs.existsSync(path.resolve(__dirname, cssPath))) entries.push(cssPath);
            if (fs.existsSync(path.resolve(__dirname, jsPath))) entries.push(jsPath);
        }
    }

    return entries;
}

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                ...getThemeEntries(),
            ],
            refresh: true,
        }),
    ],
});
```

**Step 2:** Update the store layout `@vite` directive to reference theme paths:

In `resources/themes/stage-ready/views/layouts/store.blade.php`, change:
```blade
{{-- Old --}}
@vite(['resources/css/storefront.css', 'resources/js/storefront.js'])

{{-- New --}}
@vite([
    'resources/themes/' . app(\App\Services\ThemeService::class)->getActiveTheme() . '/css/theme.css',
    'resources/themes/' . app(\App\Services\ThemeService::class)->getActiveTheme() . '/js/theme.js'
])
```

---

### Task 5: Update admin Theme page — theme selector + dynamic settings

**Files:**
- Modify: `app/Http/Controllers/Admin/ThemeController.php`
- Modify: `resources/views/admin/theme/index.blade.php`

**Step 1:** Update ThemeController `index()` to pass installed themes and theme settings schema:

```php
public function index()
{
    $theme = app(ThemeService::class);
    $settings = $theme->getAllGrouped();
    $installedThemes = $theme->getInstalledThemes();
    $activeTheme = $theme->getActiveTheme();
    $themeSettingsSchema = $theme->getThemeSettingsSchema();

    // Get current values for theme-specific settings
    $themeSettingValues = [];
    foreach ($themeSettingsSchema as $key => $schema) {
        $themeSettingValues[$key] = $theme->getThemeSetting($key, $schema['default'] ?? null);
    }

    return view('admin.theme.index', compact(
        'settings', 'installedThemes', 'activeTheme',
        'themeSettingsSchema', 'themeSettingValues'
    ));
}
```

**Step 2:** Add `switchTheme()` method:

```php
public function switchTheme(Request $request)
{
    $request->validate(['theme' => 'required|string']);

    $theme = $request->input('theme');
    $themePath = resource_path("themes/{$theme}/theme.json");

    if (!file_exists($themePath)) {
        return back()->with('error', 'Theme not found.');
    }

    $themeService = app(ThemeService::class);
    $themeService->set('identity', 'active_theme', $theme);
    $themeService->clearCache();

    return back()->with('success', 'Theme switched to ' . $theme);
}
```

**Step 3:** Add `updateThemeSettings()` method:

```php
public function updateThemeSettings(Request $request)
{
    $themeService = app(ThemeService::class);
    $schema = $themeService->getThemeSettingsSchema();

    foreach ($request->input('theme_settings', []) as $key => $value) {
        if (array_key_exists($key, $schema)) {
            $themeService->setThemeSetting($key, $value);
        }
    }

    $themeService->clearCache();

    return back()->with('success', 'Theme settings updated.')->with('tab', 'theme-settings');
}
```

**Step 4:** Update the admin theme view to add a theme selector dropdown at the top and a "Theme Settings" tab that dynamically renders fields from the schema. The theme selector is a form with a dropdown of installed themes and a "Switch" button. The Theme Settings tab iterates over `$themeSettingsSchema` and renders appropriate inputs (select, toggle, text, color, number) based on the `type` field.

**Step 5:** Add routes:

```php
Route::post('/theme/switch', [ThemeController::class, 'switchTheme'])->name('theme.switch');
Route::post('/theme/theme-settings', [ThemeController::class, 'updateThemeSettings'])->name('theme.update-theme-settings');
```

---

### Task 6: Create "Clean Light" theme

**Files:**
- Create: `resources/themes/clean-light/theme.json`
- Create: `resources/themes/clean-light/css/theme.css`
- Create: `resources/themes/clean-light/views/layouts/store.blade.php`
- Create: other views as needed (can start minimal, copying from stage-ready and modifying)

**Step 1:** Create `resources/themes/clean-light/theme.json`:
```json
{
    "name": "Clean Light",
    "version": "1.0",
    "author": "NewGearDay",
    "description": "Clean, minimal light theme with modern sans-serif typography",
    "thumbnail": "preview.png",
    "settings": {
        "card_shadow": {
            "type": "select",
            "label": "Card Shadow",
            "options": ["none", "subtle", "medium"],
            "default": "subtle"
        },
        "border_radius": {
            "type": "select",
            "label": "Border Radius",
            "options": ["none", "small", "medium", "large"],
            "default": "medium"
        },
        "header_style": {
            "type": "select",
            "label": "Header Style",
            "options": ["simple", "centered", "full-width"],
            "default": "simple"
        }
    }
}
```

**Step 2:** Create the CSS theme file with light-mode variables and clean styling. No glow effects, no noise texture. White backgrounds, subtle borders, clean shadows.

**Step 3:** Copy the store layout from stage-ready and modify for light theme (remove noise-bg class, dark-specific markup, glow effects). Copy other views as needed — product cards, header, footer, etc.

**Step 4:** Run `npm run build` to compile the new theme assets.

**Step 5:** Verify: switch to Clean Light in admin, confirm storefront renders correctly.

---

## PHASE 2: CUSTOMER IMPERSONATION

### Task 7: Impersonation controller and routes

**Files:**
- Create: `app/Http/Controllers/Admin/ImpersonationController.php`
- Modify: `routes/web.php` (add 2 routes in admin group)

**Step 1:** Create the controller:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    public function start(Customer $customer)
    {
        // Store return URL and admin context
        session()->put('impersonating_customer_id', $customer->id);
        session()->put('impersonate_return_url', route('admin.customers.edit', $customer));

        // Log in as the customer on the customer guard
        Auth::guard('customer')->login($customer);

        return redirect()->route('home');
    }

    public function stop()
    {
        $returnUrl = session()->pull('impersonate_return_url', route('admin.customers.index'));
        session()->forget('impersonating_customer_id');

        // Log out of customer guard
        Auth::guard('customer')->logout();

        return redirect($returnUrl);
    }
}
```

**Step 2:** Add routes inside the admin middleware group in `routes/web.php`:

```php
use App\Http\Controllers\Admin\ImpersonationController;

// Inside Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(...)
Route::get('/customers/{customer}/impersonate', [ImpersonationController::class, 'start'])->name('customers.impersonate');
Route::get('/impersonate/stop', [ImpersonationController::class, 'stop'])->name('impersonate.stop');
```

---

### Task 8: Impersonation UI — admin button + storefront bar

**Files:**
- Modify: `resources/views/admin/customers/form.blade.php` (add impersonate button, line ~264)
- Modify: storefront layout view (add impersonation bar after announcement bar)

**Step 1:** Add impersonate button to admin customer form. Next to the Cancel/Submit buttons, add (only for existing customers):

```blade
@if ($customer)
    <a href="{{ route('admin.customers.impersonate', $customer) }}"
       class="px-6 py-2.5 bg-amber-500 text-white text-sm font-medium rounded-lg hover:bg-amber-600 transition-colors"
       onclick="return confirm('You will be logged in as {{ $customer->first_name }} {{ $customer->last_name }} on the storefront.')">
        Login as Customer
    </a>
@endif
```

**Step 2:** Add impersonation bar to the storefront layout. Insert before the announcement bar. This bar appears only when `session('impersonating_customer_id')` is set AND the admin guard is authenticated:

```blade
@if (session('impersonating_customer_id') && Auth::guard('web')->check())
    <div class="impersonation-bar">
        <div class="container-store flex items-center justify-between py-2 text-sm">
            <span>
                Viewing as <strong>{{ Auth::guard('customer')->user()->first_name }} {{ Auth::guard('customer')->user()->last_name }}</strong>
                ({{ Auth::guard('customer')->user()->customerGroup->name ?? 'No Group' }})
            </span>
            <a href="{{ route('admin.impersonate.stop') }}" class="impersonation-bar-link">
                Back to Admin
            </a>
        </div>
    </div>
@endif
```

**Step 3:** Add CSS for the impersonation bar to the storefront CSS (or theme CSS):

```css
.impersonation-bar {
    background-color: #f59e0b;
    color: #000;
    font-family: ui-sans-serif, system-ui, sans-serif;
    z-index: 9999;
    position: relative;
}

.impersonation-bar-link {
    color: #000;
    text-decoration: underline;
    font-weight: 600;
}

.impersonation-bar-link:hover {
    color: #333;
}
```

**Step 4:** Verify: go to admin > Customers > edit a customer > click "Login as Customer". Storefront should show the amber bar. Click "Back to Admin" to return.

---

## PHASE 3: WEIGHT-BASED SHIPPING

### Task 9: Database migrations — shipping zones, regions, rates

**Files:**
- Create: `database/migrations/2026_03_14_400001_create_shipping_zones_table.php`
- Create: `database/migrations/2026_03_14_400002_create_shipping_zone_regions_table.php`
- Create: `database/migrations/2026_03_14_400003_create_shipping_zone_rates_table.php`

**Step 1:** Create `shipping_zones` migration:

```php
Schema::create('shipping_zones', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('store_id')->default(1);
    $table->string('name');
    $table->boolean('is_default')->default(false);
    $table->integer('sort_order')->default(0);
    $table->boolean('status')->default(true);
    $table->timestamps();
});
```

**Step 2:** Create `shipping_zone_regions` migration:

```php
Schema::create('shipping_zone_regions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('shipping_zone_id')->constrained()->cascadeOnDelete();
    $table->unsignedBigInteger('country_id')->nullable();
    $table->unsignedBigInteger('ph_region_id')->nullable();
    $table->unsignedBigInteger('ph_province_id')->nullable();
    $table->unsignedBigInteger('ph_city_id')->nullable();
    $table->timestamps();

    $table->index(['country_id', 'ph_region_id', 'ph_province_id', 'ph_city_id'], 'szr_location_index');
});
```

**Step 3:** Create `shipping_zone_rates` migration:

```php
Schema::create('shipping_zone_rates', function (Blueprint $table) {
    $table->id();
    $table->foreignId('shipping_zone_id')->constrained()->cascadeOnDelete();
    $table->unsignedBigInteger('customer_group_id')->nullable(); // null = default rate for all groups
    $table->decimal('weight_from', 8, 3)->default(0);
    $table->decimal('weight_to', 8, 3);
    $table->decimal('cost', 10, 2);
    $table->decimal('free_shipping_threshold', 10, 2)->nullable(); // order total for free shipping
    $table->timestamps();

    $table->index(['shipping_zone_id', 'customer_group_id'], 'szr_zone_group_index');
});
```

**Step 4:** Run `php artisan migrate`.

---

### Task 10: Eloquent models — ShippingZone, ShippingZoneRegion, ShippingZoneRate

**Files:**
- Create: `app/Models/ShippingZone.php`
- Create: `app/Models/ShippingZoneRegion.php`
- Create: `app/Models/ShippingZoneRate.php`

**Step 1:** Create ShippingZone model:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingZone extends Model
{
    protected $fillable = ['store_id', 'name', 'is_default', 'sort_order', 'status'];

    protected $casts = [
        'is_default' => 'boolean',
        'status' => 'boolean',
    ];

    public function regions(): HasMany
    {
        return $this->hasMany(ShippingZoneRegion::class);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(ShippingZoneRate::class);
    }
}
```

**Step 2:** Create ShippingZoneRegion model:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingZoneRegion extends Model
{
    protected $fillable = ['shipping_zone_id', 'country_id', 'ph_region_id', 'ph_province_id', 'ph_city_id'];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }
}
```

**Step 3:** Create ShippingZoneRate model:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingZoneRate extends Model
{
    protected $fillable = [
        'shipping_zone_id', 'customer_group_id',
        'weight_from', 'weight_to', 'cost', 'free_shipping_threshold',
    ];

    protected $casts = [
        'weight_from' => 'decimal:3',
        'weight_to' => 'decimal:3',
        'cost' => 'decimal:2',
        'free_shipping_threshold' => 'decimal:2',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }
}
```

---

### Task 11: WeightBasedShippingProvider

**Files:**
- Create: `app/Services/Shipping/WeightBasedShippingProvider.php`
- Modify: `app/Services/Shipping/ShippingQuoteService.php` (register provider)

**Step 1:** Create the provider:

```php
<?php

namespace App\Services\Shipping;

use App\Models\Setting;
use App\Models\ShippingZone;
use App\Models\ShippingZoneRegion;
use App\Models\ShippingZoneRate;
use App\Models\PhProvince;
use App\Models\PhCity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class WeightBasedShippingProvider implements ShippingProviderInterface
{
    public function getKey(): string
    {
        return 'weight_based';
    }

    public function getName(): string
    {
        return 'Weight-Based Shipping';
    }

    public function isEnabled(): bool
    {
        return (bool) Setting::where('group', 'shipping')
            ->where('key', 'weight_based_enabled')
            ->value('value');
    }

    public function getRates(array $address, array $dimensions, float $weight, float $subtotal): array
    {
        try {
            $zone = $this->resolveZone($address);

            if (!$zone) {
                return [];
            }

            $customerGroupId = null;
            if (Auth::guard('customer')->check()) {
                $customerGroupId = Auth::guard('customer')->user()->customer_group_id;
            }

            // Try customer-group-specific rate first, fall back to default (null group)
            $rate = $this->findRate($zone->id, $weight, $customerGroupId);

            if (!$rate && $customerGroupId) {
                $rate = $this->findRate($zone->id, $weight, null);
            }

            if (!$rate) {
                return [];
            }

            // Check free shipping threshold
            if ($rate->free_shipping_threshold && $subtotal >= $rate->free_shipping_threshold) {
                return [[
                    'key' => "weight_based_{$zone->id}",
                    'name' => "Shipping ({$zone->name})",
                    'cost' => 0,
                    'estimated_days' => null,
                    'service_type' => 'weight_based',
                ]];
            }

            return [[
                'key' => "weight_based_{$zone->id}",
                'name' => "Shipping ({$zone->name})",
                'cost' => (float) $rate->cost,
                'estimated_days' => null,
                'service_type' => 'weight_based',
            ]];
        } catch (\Throwable $e) {
            Log::error('WeightBasedShippingProvider: failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Resolve which shipping zone matches the delivery address.
     * Priority: city > province > region > country > default zone.
     */
    private function resolveZone(array $address): ?ShippingZone
    {
        $province = $address['province'] ?? '';
        $city = $address['city'] ?? '';

        // Look up province and city IDs
        $phProvince = PhProvince::where('name', $province)->first();
        $phCity = $phProvince
            ? PhCity::where('ph_province_id', $phProvince->id)->where('name', $city)->first()
            : null;

        $query = ShippingZoneRegion::query()
            ->whereHas('zone', fn($q) => $q->where('status', true));

        // Try most specific match first: city
        if ($phCity) {
            $zone = (clone $query)->where('ph_city_id', $phCity->id)->first();
            if ($zone) return $zone->zone;
        }

        // Province
        if ($phProvince) {
            $zone = (clone $query)->where('ph_province_id', $phProvince->id)
                ->whereNull('ph_city_id')->first();
            if ($zone) return $zone->zone;
        }

        // Region
        if ($phProvince) {
            $zone = (clone $query)->where('ph_region_id', $phProvince->ph_region_id)
                ->whereNull('ph_province_id')->whereNull('ph_city_id')->first();
            if ($zone) return $zone->zone;
        }

        // Default zone
        return ShippingZone::where('is_default', true)->where('status', true)->first();
    }

    private function findRate(int $zoneId, float $weight, ?int $customerGroupId): ?ShippingZoneRate
    {
        return ShippingZoneRate::where('shipping_zone_id', $zoneId)
            ->where('customer_group_id', $customerGroupId)
            ->where('weight_from', '<=', $weight)
            ->where('weight_to', '>=', $weight)
            ->first();
    }
}
```

**Step 2:** Register in `ShippingQuoteService::getProviders()`:

```php
private function getProviders(): array
{
    return [
        new DeliveryPhShippingProvider(),
        new WeightBasedShippingProvider(),
        new FlatRateShippingProvider(),
    ];
}
```

---

### Task 12: Shipping Zones admin CRUD

**Files:**
- Create: `app/Http/Controllers/Admin/ShippingZoneController.php`
- Create: `resources/views/admin/shipping-zones/index.blade.php`
- Create: `resources/views/admin/shipping-zones/form.blade.php`
- Modify: `routes/web.php` (add resource routes)
- Modify: `resources/views/layouts/admin.blade.php` (add sidebar link)

**Step 1:** Create ShippingZoneController with standard CRUD methods:
- `index()` — list all zones with region counts and rate counts
- `create()` — show form with empty zone
- `store()` — validate and save zone + regions + rates
- `edit($zone)` — show form with loaded zone, regions, rates
- `update($zone)` — validate and update zone + sync regions + rates
- `destroy($zone)` — delete zone (cascade deletes regions + rates)

**Step 2:** Create index view — table listing zones with columns: Name, Regions, Rates, Default, Status, Actions (edit/delete).

**Step 3:** Create form view with sections:
- **General:** Zone name, is_default toggle, status toggle
- **Regions:** Multi-select assignment. Show available regions/provinces with checkboxes. Group by island group for PH (NCR, Luzon regions, Visayas regions, Mindanao regions).
- **Rates:** Dynamic rate table with Alpine.js. Tabs for each customer group + "Default" tab. Each tab has rows: weight_from, weight_to, cost, free_shipping_threshold. Add/remove rows.

**Step 4:** Add routes:
```php
Route::resource('shipping-zones', ShippingZoneController::class)->names('shipping-zones');
```

**Step 5:** Add sidebar link in admin layout between DPH Explorer and Settings:
```php
['route' => 'admin.shipping-zones.index', 'label' => 'Shipping Zones', 'icon' => '...', 'pattern' => 'admin.shipping-zones*'],
```

---

### Task 13: Seed default PH shipping zones

**Files:**
- Create: `database/seeders/ShippingZoneSeeder.php`

**Step 1:** Create seeder that creates 4 default zones:

1. **Metro Manila** — ph_region_id = 13 (NCR)
2. **Luzon** — ph_region_ids = 1, 2, 3, 4, 5, 14, 17 (Regions I-V, CAR, MIMAROPA)
3. **Visayas** — ph_region_ids = 6, 7, 8 (Regions VI-VIII)
4. **Mindanao** — ph_region_ids = 9, 10, 11, 12, 15, 16 (Regions IX-XIII, ARMM, Caraga)

Each zone gets sample default rates:
- Metro Manila: 0-1kg=₱100, 1-3kg=₱150, 3-5kg=₱200, 5-10kg=₱300
- Luzon: 0-1kg=₱150, 1-3kg=₱200, 3-5kg=₱300, 5-10kg=₱450
- Visayas: 0-1kg=₱200, 1-3kg=₱300, 3-5kg=₱400, 5-10kg=₱550
- Mindanao: 0-1kg=₱200, 1-3kg=₱300, 3-5kg=₱400, 5-10kg=₱550

**Step 2:** Run `php artisan db:seed --class=ShippingZoneSeeder`.

---

### Task 14: Add weight-based enable/disable to admin Settings > Shipping tab

**Files:**
- Modify: `resources/views/admin/settings/index.blade.php` (add weight-based section)

**Step 1:** Add a "Weight-Based Shipping" section in the Shipping tab (before or after Flat Rate section):

```blade
<h4 class="font-medium text-gray-900 mb-3">Weight-Based Shipping</h4>
<div class="space-y-4">
    <div class="flex items-center gap-3">
        <input type="hidden" name="settings[weight_based_enabled]" value="0">
        <input type="checkbox" name="settings[weight_based_enabled]" value="1"
               {{ ... checked ... }} class="...">
        <label>Enable Weight-Based Shipping</label>
    </div>
    <p class="text-xs text-gray-500">Configure zones and rates in <a href="{{ route('admin.shipping-zones.index') }}" class="text-indigo-600 hover:underline">Shipping Zones</a>.</p>
</div>
```

---

## PHASE 4: CHECKOUT CONFIRMATION MODAL

### Task 15: Add confirmation modal to checkout

**Files:**
- Modify: checkout view (in the active theme's views/checkout/index.blade.php)
- Modify: theme CSS (add modal styles)

**Step 1:** Add modal markup after the order form in the checkout view. The modal is controlled by Alpine.js (`showConfirmModal` boolean). When user clicks "Place Order", instead of submitting immediately, it sets `showConfirmModal = true`.

**Step 2:** The modal displays:
- Product list (loop `cartItems` from Alpine state — name, qty, price, line total)
- Selected shipping address (read from the address cards data)
- Shipping method + cost
- Payment method
- Order notes
- Subtotal + shipping + total
- "Go Back" button (closes modal) + "Confirm & Pay" button (submits the form)

**Step 3:** Modify the `submitOrder()` Alpine method:

```js
// Old: directly submit
submitOrder() {
    this.placing = true;
    this.$refs.orderForm.submit();
}

// New: show modal first
submitOrder() {
    this.showConfirmModal = true;
}

confirmOrder() {
    this.placing = true;
    this.$refs.orderForm.submit();
}
```

**Step 4:** Add modal CSS to theme CSS files. Dark overlay, centered card, theme-appropriate styling. For Stage Ready: dark card with amber accents. For Clean Light: white card with subtle shadow.

**Step 5:** Verify: go through checkout, click Place Order → modal appears with summary → click Confirm & Pay → order placed.

---

## Build & Verify

After each phase:
- Run `npm run build`
- Run `php artisan view:clear`
- Run `php artisan config:clear` (if config changed)
- Run `php artisan cache:clear` (if ThemeService cache changed)
- Manual test the feature in browser
