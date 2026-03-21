# Deliveries PH Shipping Integration — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a modular shipping provider system with Flat Rate and Deliveries PH (DPH) as the first two modules. DPH provides real-time courier rate quotation at cart/checkout, admin shipment booking, and webhook-driven delivery status tracking. Each module can be independently enabled/disabled.

**Architecture:** A `ShippingProviderInterface` defines the contract. `FlatRateShippingProvider` and `DeliveryPhShippingProvider` implement it. `ShippingQuoteService` aggregates rates from all enabled providers. `DeliveryPhService` is the low-level DPH API client (JWT auth, token caching). Admin books shipments manually. DPH HTTP Triggers push status updates to our webhook. Future providers (weight-based, etc.) plug in with zero changes to checkout.

**Tech Stack:** Laravel 12 / PHP 8.2 / MySQL / Alpine.js / Tailwind CSS / DPH API v3 (JWT auth)

**Reference docs:**
- Design: `docs/plans/2026-03-06-deliveries-ph-integration-design.md`
- DPH API: `docs/delivery-ph-api-v3.md`

---

## Task 1: Database Migrations — New Tables

**Files:**
- Create: `database/migrations/2026_03_06_300001_create_shipments_table.php`
- Create: `database/migrations/2026_03_06_300002_create_shipment_events_table.php`

### Step 1: Create shipments migration

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('dph_post_id')->nullable()->index();
            $table->string('dph_partner_id');
            $table->string('dph_partner_name');
            $table->string('dph_service_type')->default('scheduled'); // scheduled or onDemand
            $table->string('dph_status')->nullable();
            $table->string('dph_sub_status')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('tracking_url')->nullable();
            $table->string('shareable_link')->nullable();
            $table->decimal('approximate_fee', 10, 2)->default(0);
            $table->decimal('actual_fee', 10, 2)->default(0);
            $table->decimal('final_fee', 10, 2)->default(0);
            $table->timestamp('booked_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
```

### Step 2: Create shipment_events migration

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->string('sub_status')->nullable();
            $table->string('remarks')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_events');
    }
};
```

### Step 3: Run migrations

Run: `php artisan migrate`
Expected: Both tables created successfully.

### Step 4: Commit

```
feat: add shipments and shipment_events tables for DPH integration
```

---

## Task 2: Database Migrations — Orders & Order Statuses

**Files:**
- Create: `database/migrations/2026_03_06_300003_add_dph_courier_name_to_orders.php`
- Create: `database/migrations/2026_03_06_300004_add_dph_order_statuses.php`

### Step 1: Create orders column migration

Add `dph_courier_name` to orders table for storing which DPH courier the customer selected at checkout.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('dph_courier_name')->nullable()->after('shipping_method');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('dph_courier_name');
        });
    }
};
```

### Step 2: Create order statuses migration

Insert new DPH-mapped statuses for store_id 1 (dev store). Existing statuses kept — new ones added with higher sort_order.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $storeId = 1;
        $existing = DB::table('order_statuses')
            ->where('store_id', $storeId)
            ->pluck('slug')
            ->toArray();

        $newStatuses = [
            ['slug' => 'booked', 'name' => 'Booked', 'stock_action' => null, 'sort_order' => 10],
            ['slug' => 'confirmed', 'name' => 'Confirmed', 'stock_action' => null, 'sort_order' => 11],
            ['slug' => 'pickup-started', 'name' => 'Pickup Started', 'stock_action' => null, 'sort_order' => 12],
            ['slug' => 'picked-up', 'name' => 'Picked Up', 'stock_action' => null, 'sort_order' => 13],
            ['slug' => 'in-transit', 'name' => 'In Transit', 'stock_action' => null, 'sort_order' => 14],
            ['slug' => 'out-for-delivery', 'name' => 'Out for Delivery', 'stock_action' => null, 'sort_order' => 15],
            ['slug' => 'failed-pickup', 'name' => 'Failed Pickup', 'stock_action' => null, 'sort_order' => 20],
            ['slug' => 'failed-delivery', 'name' => 'Failed Delivery', 'stock_action' => null, 'sort_order' => 21],
            ['slug' => 'returning', 'name' => 'Returning', 'stock_action' => null, 'sort_order' => 22],
            ['slug' => 'returned', 'name' => 'Returned', 'stock_action' => 'increment', 'sort_order' => 23],
        ];

        $now = now();
        foreach ($newStatuses as $status) {
            if (!in_array($status['slug'], $existing)) {
                DB::table('order_statuses')->insert([
                    'store_id' => $storeId,
                    'name' => $status['name'],
                    'slug' => $status['slug'],
                    'is_default' => false,
                    'stock_action' => $status['stock_action'],
                    'sort_order' => $status['sort_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $newSlugs = [
            'booked', 'confirmed', 'pickup-started', 'picked-up',
            'in-transit', 'out-for-delivery', 'failed-pickup',
            'failed-delivery', 'returning', 'returned',
        ];

        DB::table('order_statuses')
            ->where('store_id', 1)
            ->whereIn('slug', $newSlugs)
            ->delete();
    }
};
```

### Step 3: Run migrations

Run: `php artisan migrate`
Expected: Column added, new statuses inserted.

### Step 4: Commit

```
feat: add DPH courier name column and shipping order statuses
```

---

## Task 3: Eloquent Models — Shipment & ShipmentEvent

**Files:**
- Create: `app/Models/Shipment.php`
- Create: `app/Models/ShipmentEvent.php`
- Modify: `app/Models/Order.php` — add shipment relationship + dph_courier_name to fillable

### Step 1: Create Shipment model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    protected $fillable = [
        'order_id', 'dph_post_id', 'dph_partner_id', 'dph_partner_name',
        'dph_service_type', 'dph_status', 'dph_sub_status',
        'tracking_number', 'tracking_url', 'shareable_link',
        'approximate_fee', 'actual_fee', 'final_fee',
        'booked_at', 'delivered_at', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'approximate_fee' => 'decimal:2',
            'actual_fee' => 'decimal:2',
            'final_fee' => 'decimal:2',
            'booked_at' => 'datetime',
            'delivered_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class)->orderBy('created_at', 'desc');
    }
}
```

### Step 2: Create ShipmentEvent model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'shipment_id', 'status', 'sub_status', 'remarks', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
```

### Step 3: Update Order model

In `app/Models/Order.php`:
- Add `'dph_courier_name'` to `$fillable` array (after `'shipping_method'`)
- Add `shipment()` relationship method

```php
// Add to $fillable (line 17, after 'shipping_method'):
'dph_courier_name',

// Add relationship method:
public function shipment(): \Illuminate\Database\Eloquent\Relations\HasOne
{
    return $this->hasOne(Shipment::class);
}
```

### Step 4: Commit

```
feat: add Shipment and ShipmentEvent models with Order relationship
```

---

## Task 4: DeliveryPhService — Auth & Token Management

**Files:**
- Create: `app/Services/DeliveryPhService.php`

This is the core service class. We build it in stages — first auth, then API methods.

### Step 1: Create the service with auth management

DPH uses JWT auth via `/loginV2`. Tokens are cached to avoid QUOTA_EXCEEDED errors. The access token has a short TTL; the refresh token lasts longer.

- Base URL comes from `dph_mode` setting (sandbox vs production)
- Credentials come from encrypted `dph_email` / `dph_password` settings
- Cache key: `dph_auth` (single-tenant, no store_id needed in cache key)

```php
<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeliveryPhService
{
    private const SANDBOX_URL = 'https://us-central1-fleet-staging-2333a.cloudfunctions.net';

    private string $baseUrl;
    private string $email;
    private string $password;

    public function __construct()
    {
        $settings = Setting::where('group', 'shipping')
            ->whereIn('key', ['dph_mode', 'dph_email', 'dph_password', 'dph_production_url'])
            ->pluck('value', 'key');

        $mode = $settings->get('dph_mode', 'sandbox');
        $this->baseUrl = $mode === 'production'
            ? ($settings->get('dph_production_url') ?: self::SANDBOX_URL)
            : self::SANDBOX_URL;

        try {
            $this->email = Crypt::decryptString($settings->get('dph_email', ''));
            $this->password = Crypt::decryptString($settings->get('dph_password', ''));
        } catch (\Throwable) {
            $this->email = '';
            $this->password = '';
        }
    }

    public static function isEnabled(): bool
    {
        return Setting::where('group', 'shipping')
            ->where('key', 'dph_enabled')
            ->value('value') === '1';
    }

    /**
     * Get a valid access token, refreshing or re-logging in as needed.
     */
    private function getAccessToken(): string
    {
        $auth = Cache::get('dph_auth');

        if ($auth && $auth['access_expires_at'] > now()->timestamp * 1000) {
            return $auth['access_token'];
        }

        // Try refresh
        if ($auth && $auth['refresh_expires_at'] > now()->timestamp * 1000) {
            try {
                $this->refreshToken($auth['refresh_token']);
                return Cache::get('dph_auth')['access_token'];
            } catch (\Throwable $e) {
                Log::warning('DPH token refresh failed, re-logging in', ['error' => $e->getMessage()]);
            }
        }

        // Full login
        $this->login();
        return Cache::get('dph_auth')['access_token'];
    }

    public function login(): void
    {
        $response = Http::post("{$this->baseUrl}/loginV2", [
            'email' => $this->email,
            'password' => $this->password,
        ]);

        $data = $response->json();

        if (!($data['success'] ?? false)) {
            throw new \RuntimeException('DPH login failed: ' . ($data['results'] ?? 'unknown error'));
        }

        $results = $data['results'];

        Cache::put('dph_auth', [
            'access_token' => $results['accessToken'],
            'refresh_token' => $results['refreshToken'],
            'access_expires_at' => $results['accessTokenExpiresAt'],
            'refresh_expires_at' => $results['refreshTokenExpiresAt'],
        ], now()->addDays(30));
    }

    private function refreshToken(string $refreshToken): void
    {
        $response = Http::withHeaders([
            'x-access-token' => "Bearer {$refreshToken}",
        ])->post("{$this->baseUrl}/refreshToken");

        $data = $response->json();

        if (!($data['success'] ?? false)) {
            throw new \RuntimeException('DPH token refresh failed: ' . ($data['results'] ?? 'unknown error'));
        }

        $results = $data['results'];

        Cache::put('dph_auth', [
            'access_token' => $results['accessToken'],
            'refresh_token' => $results['refreshToken'],
            'access_expires_at' => $results['accessTokenExpiresAt'],
            'refresh_expires_at' => $results['refreshTokenExpiresAt'],
        ], now()->addDays(30));
    }

    /**
     * Make an authenticated API request with auto-retry on token expiry.
     */
    private function apiRequest(string $method, string $endpoint, array $data = [], array $query = []): array
    {
        $attempt = function () use ($method, $endpoint, $data, $query) {
            $token = $this->getAccessToken();

            $request = Http::withHeaders([
                'x-access-token' => "Bearer {$token}",
                'Content-Type' => 'application/json',
            ]);

            if (!empty($query)) {
                $request = $request->withQueryParameters($query);
            }

            $response = $method === 'GET'
                ? $request->get("{$this->baseUrl}{$endpoint}")
                : $request->post("{$this->baseUrl}{$endpoint}", $data);

            return $response->json();
        };

        $result = $attempt();

        // If token expired mid-request, clear cache and retry once
        if (isset($result['success']) && !$result['success'] &&
            is_string($result['results'] ?? null) &&
            str_contains($result['results'], 'TOKEN_EXPIRED')) {
            Cache::forget('dph_auth');
            $result = $attempt();
        }

        return $result;
    }

    // ── Unit Conversion ──

    public static function cmToInches(float $cm): float
    {
        return round($cm / 2.54, 2);
    }

    // ── API Methods (added in next tasks) ──
}
```

### Step 2: Commit

```
feat: add DeliveryPhService with JWT auth and token caching
```

---

## Task 5: DeliveryPhService — API Methods

**Files:**
- Modify: `app/Services/DeliveryPhService.php` — add rate quotation + booking methods

### Step 1: Add rate quotation methods

Add these methods after the `cmToInches` method:

```php
/**
 * Get available scheduled courier rates.
 * Used for provincial / outside Metro Manila deliveries.
 */
public function getScheduledRates(array $pickup, array $delivery, ?array $dimensions = null, ?float $weight = null): array
{
    $body = [
        'serviceType' => 'Scheduled',
        'pickup' => $pickup,    // {province, city, barangay?}
        'delivery' => $delivery, // {province, city, barangay?}
    ];

    if ($dimensions) {
        $body['dimensions'] = [
            'length' => self::cmToInches($dimensions['length'] ?? 0),
            'width' => self::cmToInches($dimensions['width'] ?? 0),
            'height' => self::cmToInches($dimensions['height'] ?? 0),
        ];
    }

    if ($weight) {
        $body['weight'] = $weight;
    }

    $result = $this->apiRequest('POST', '/v3/getAvailablePartners', $body);

    if (!($result['success'] ?? false)) {
        Log::warning('DPH getScheduledRates failed', ['result' => $result]);
        return [];
    }

    return $result['result'] ?? [];
}

/**
 * Get on-demand courier rates (Metro Manila only).
 * Requires lat/long for both pickup and delivery.
 */
public function getOnDemandRates(array $pickupData, array $deliveryData, array $dimensions, float $weight): array
{
    $body = [
        'couriers' => [], // empty = all on-demand providers
        'pickup_province' => $pickupData['province'] ?? 'Metro Manila',
        'data' => [
            'localeKey' => 'en_PH',
            'pickup' => [
                'name' => $pickupData['name'],
                'phone' => $pickupData['phone'],
                'address' => $pickupData['address'],
                'lat' => (float) $pickupData['lat'],
                'long' => (float) $pickupData['lng'],
            ],
            'delivery' => [
                'name' => $deliveryData['name'],
                'phone' => $deliveryData['phone'],
                'address' => $deliveryData['address'],
                'lat' => (float) $deliveryData['lat'],
                'long' => (float) $deliveryData['lng'],
            ],
            'scheduleAt' => now()->addMinutes(30)->timestamp * 1000,
            'vehicleType' => 'MOTORCYCLE',
            'dimensions' => [
                'height' => self::cmToInches($dimensions['height'] ?? 0),
                'width' => self::cmToInches($dimensions['width'] ?? 0),
                'depth' => self::cmToInches($dimensions['length'] ?? 0),
                'weight' => $weight,
            ],
        ],
    ];

    $result = $this->apiRequest('POST', '/v3/getOnDemandQuotation', $body);

    if (!($result['success'] ?? false)) {
        Log::warning('DPH getOnDemandRates failed', ['result' => $result]);
        return [];
    }

    return $result['result'] ?? [];
}

/**
 * Create a shipment post (book a courier).
 */
public function createPost(array $pickupDetails, array $deliveryDetails, string $partnerId, string $refNo, bool $isPsgc = true): array
{
    $body = [
        'refNo' => $refNo,
        'isPSGC' => $isPsgc,
        'pickupDetails' => $pickupDetails,
        'deliveryDetails' => $deliveryDetails,
    ];

    $result = $this->apiRequest('POST', '/v3/createPost', $body, ['partnerId' => $partnerId]);

    if (!($result['success'] ?? false)) {
        Log::error('DPH createPost failed', ['result' => $result, 'partnerId' => $partnerId]);
        throw new \RuntimeException('DPH booking failed: ' . json_encode($result['results'] ?? $result['message'] ?? 'unknown'));
    }

    return $result['result'] ?? $result['results'] ?? [];
}

/**
 * Get post details by postId.
 */
public function getPost(string $postId): array
{
    $result = $this->apiRequest('GET', '/getPost', [], ['postId' => $postId]);

    if (!($result['success'] ?? false)) {
        Log::warning('DPH getPost failed', ['postId' => $postId, 'result' => $result]);
        return [];
    }

    return $result['result'] ?? $result['results'] ?? [];
}

/**
 * Cancel a post.
 */
public function cancelPost(string $postId, string $trackingNumber, string $courierId): array
{
    $result = $this->apiRequest('POST', '/v3/cancelPost', [
        'postId' => $postId,
        'trackingNumber' => $trackingNumber,
        'courierId' => $courierId,
    ]);

    if (!($result['success'] ?? false)) {
        Log::error('DPH cancelPost failed', ['postId' => $postId, 'result' => $result]);
        throw new \RuntimeException('DPH cancellation failed: ' . json_encode($result['results'] ?? 'unknown'));
    }

    return $result;
}

/**
 * Get list of configured partners.
 */
public function getPartners(): array
{
    $result = $this->apiRequest('GET', '/getPartners');

    if (!($result['success'] ?? false)) {
        return [];
    }

    return $result['result'] ?? $result['results'] ?? [];
}
```

### Step 2: Commit

```
feat: add DPH rate quotation, booking, and tracking API methods
```

---

## Task 6: Modular Shipping Provider System

**Files:**
- Create: `app/Services/Shipping/ShippingProviderInterface.php`
- Create: `app/Services/Shipping/FlatRateShippingProvider.php`
- Create: `app/Services/Shipping/DeliveryPhShippingProvider.php`
- Create: `app/Services/Shipping/ShippingQuoteService.php`

This task introduces the modular shipping architecture. Each shipping method is an independent provider that can be enabled/disabled. The `ShippingQuoteService` aggregates rates from all enabled providers.

### Step 1: Create ShippingProviderInterface

```php
<?php

namespace App\Services\Shipping;

use Illuminate\Support\Collection;

interface ShippingProviderInterface
{
    public function getKey(): string;
    public function getName(): string;
    public function isEnabled(): bool;

    /**
     * Get shipping rates for the given address and package details.
     *
     * @param array $address {province, city, barangay?, lat?, lng?}
     * @param array $dimensions {length, width, height} in cm
     * @param float $weight in kg
     * @param float $subtotal cart subtotal for threshold calculations
     * @return array [{key, name, cost, estimated_days?, service_type, partner_id?, product_size?}]
     */
    public function getRates(array $address, array $dimensions, float $weight, float $subtotal): array;
}
```

### Step 2: Create FlatRateShippingProvider

Wraps existing flat-rate logic as a proper module. Uses the same `checkout.shipping_flat_rate` and `checkout.shipping_free_threshold` settings.

```php
<?php

namespace App\Services\Shipping;

use App\Models\Setting;

class FlatRateShippingProvider implements ShippingProviderInterface
{
    public function getKey(): string
    {
        return 'flat_rate';
    }

    public function getName(): string
    {
        return 'Flat Rate Shipping';
    }

    public function isEnabled(): bool
    {
        $enabled = Setting::where('group', 'shipping')
            ->where('key', 'flat_rate_enabled')
            ->value('value');

        // Default to enabled if setting doesn't exist (backwards compat)
        return $enabled === null || $enabled === '1';
    }

    public function getRates(array $address, array $dimensions, float $weight, float $subtotal): array
    {
        $flatRate = (float) theme('checkout.shipping_flat_rate', 150);
        $freeThreshold = (float) theme('checkout.shipping_free_threshold', 3000);

        $methods = [];

        if ($freeThreshold > 0 && $subtotal >= $freeThreshold) {
            $methods[] = [
                'key' => 'free',
                'name' => 'Free Shipping',
                'cost' => 0,
                'service_type' => 'flat_rate',
            ];
        }

        $methods[] = [
            'key' => 'flat_rate',
            'name' => 'Standard Shipping',
            'cost' => $flatRate,
            'service_type' => 'flat_rate',
        ];

        return $methods;
    }
}
```

### Step 3: Create DeliveryPhShippingProvider

Wraps `DeliveryPhService` as a shipping provider module.

```php
<?php

namespace App\Services\Shipping;

use App\Models\Setting;
use App\Services\DeliveryPhService;
use Illuminate\Support\Facades\Log;

class DeliveryPhShippingProvider implements ShippingProviderInterface
{
    public function getKey(): string
    {
        return 'dph';
    }

    public function getName(): string
    {
        return 'Deliveries PH';
    }

    public function isEnabled(): bool
    {
        return DeliveryPhService::isEnabled();
    }

    public function getRates(array $address, array $dimensions, float $weight, float $subtotal): array
    {
        try {
            $dph = app(DeliveryPhService::class);
            $pickupAddress = $this->getPickupAddress();

            $quotes = [];

            // Scheduled rates (works everywhere)
            $scheduledRates = $dph->getScheduledRates(
                ['province' => $pickupAddress['province'], 'city' => $pickupAddress['city'], 'barangay' => $pickupAddress['barangay'] ?? ''],
                ['province' => $address['province'] ?? '', 'city' => $address['city'] ?? '', 'barangay' => $address['barangay'] ?? ''],
                $dimensions,
                $weight
            );

            foreach ($scheduledRates as $rate) {
                $partnerId = $rate['id'] ?? '';
                $products = $rate['products'] ?? [];
                $estimatedDays = $rate['estimatedDeliveryDays'] ?? null;

                // Pick the cheapest product size that fits
                $cheapest = collect($products)->sortBy('price')->first();
                if ($cheapest) {
                    $quotes[] = [
                        'key' => "dph_scheduled_{$partnerId}_{$cheapest['size']}",
                        'name' => ($rate['name'] ?? $partnerId) . ' (' . ($cheapest['size'] ?? '') . ')',
                        'cost' => (float) $cheapest['price'],
                        'estimated_days' => $estimatedDays,
                        'service_type' => 'scheduled',
                        'partner_id' => $partnerId,
                        'product_size' => $cheapest['size'] ?? '',
                    ];
                }
            }

            return $quotes;
        } catch (\Throwable $e) {
            Log::error('DeliveryPhShippingProvider: failed to fetch rates', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    private function getPickupAddress(): array
    {
        $settings = Setting::where('group', 'shipping')
            ->whereIn('key', [
                'dph_pickup_address', 'dph_pickup_city', 'dph_pickup_province',
                'dph_pickup_barangay', 'dph_pickup_postal_code',
                'dph_pickup_lat', 'dph_pickup_lng',
                'dph_pickup_contact_name', 'dph_pickup_contact_number', 'dph_pickup_contact_email',
            ])
            ->pluck('value', 'key');

        return [
            'address' => $settings->get('dph_pickup_address', ''),
            'city' => $settings->get('dph_pickup_city', ''),
            'province' => $settings->get('dph_pickup_province', ''),
            'barangay' => $settings->get('dph_pickup_barangay', ''),
            'postal_code' => $settings->get('dph_pickup_postal_code', ''),
            'lat' => $settings->get('dph_pickup_lat', ''),
            'lng' => $settings->get('dph_pickup_lng', ''),
            'name' => $settings->get('dph_pickup_contact_name', ''),
            'phone' => $settings->get('dph_pickup_contact_number', ''),
            'email' => $settings->get('dph_pickup_contact_email', ''),
        ];
    }
}
```

### Step 4: Create ShippingQuoteService (aggregator)

```php
<?php

namespace App\Services\Shipping;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ShippingQuoteService
{
    /**
     * All registered shipping providers.
     * Future providers are added here.
     */
    private function getProviders(): array
    {
        return [
            new DeliveryPhShippingProvider(),
            new FlatRateShippingProvider(),  // flat rate last = appears at bottom of list
        ];
    }

    /**
     * Get shipping quotes from ALL enabled providers.
     *
     * @param array $deliveryAddress {province, city, barangay?, lat?, lng?}
     * @param Collection $cartItems Cart items with product relationship loaded
     * @param float $subtotal Cart subtotal
     * @return array Merged normalized shipping options
     */
    public function getQuotes(array $deliveryAddress, Collection $cartItems, float $subtotal): array
    {
        $dimensions = $this->aggregateDimensions($cartItems);
        $quotes = [];

        foreach ($this->getProviders() as $provider) {
            if (!$provider->isEnabled()) {
                continue;
            }

            try {
                $providerRates = $provider->getRates(
                    $deliveryAddress,
                    $dimensions,
                    $dimensions['weight'],
                    $subtotal
                );
                $quotes = array_merge($quotes, $providerRates);
            } catch (\Throwable $e) {
                Log::error("ShippingQuoteService: {$provider->getKey()} provider failed", [
                    'error' => $e->getMessage(),
                ]);
                // Skip failed provider, continue with others
            }
        }

        return $quotes;
    }

    /**
     * Aggregate dimensions from cart items.
     * Weight: sum all items. Dimensions: largest single item's dimensions.
     */
    private function aggregateDimensions(Collection $cartItems): array
    {
        $totalWeight = 0;
        $maxLength = 0;
        $maxWidth = 0;
        $maxHeight = 0;

        foreach ($cartItems as $item) {
            $product = $item->product;
            if (!$product) continue;

            $itemWeight = $item->productVariant?->weight ?: $product->weight;
            $totalWeight += ($itemWeight ?: 0) * $item->quantity;

            $maxLength = max($maxLength, $product->length ?: 0);
            $maxWidth = max($maxWidth, $product->width ?: 0);
            $maxHeight = max($maxHeight, $product->height ?: 0);
        }

        return [
            'weight' => $totalWeight ?: 0.5, // minimum 0.5kg if no weight data
            'length' => $maxLength ?: 10,     // default 10cm if no dimensions
            'width' => $maxWidth ?: 10,
            'height' => $maxHeight ?: 10,
        ];
    }
}
```

### Step 5: Commit

```
feat: add modular shipping provider system with flat-rate and DPH providers
```

---

## Task 7: Admin Settings — Shipping Tab

**Files:**
- Modify: `resources/views/admin/settings/index.blade.php` — add 6th "Shipping" tab
- Modify: `app/Http/Controllers/Admin/SettingController.php` — add DPH encrypted keys + test connection + tab mapping

### Step 1: Update SettingController

Add DPH encrypted keys to `ENCRYPTED_KEYS` constant:

```php
private const ENCRYPTED_KEYS = [
    'hitpay_sandbox_api_key',
    'hitpay_sandbox_salt',
    'hitpay_production_api_key',
    'hitpay_production_salt',
    'dph_email',
    'dph_password',
];
```

In the `index()` method, add masked previews for DPH keys. After the existing HitPay masked loop (line 37-47), extend `$hitpayMasked` to also include DPH keys — or better, rename it to `$maskedKeys` for generality. Actually, to minimize changes, just extend the existing loop since it already iterates `ENCRYPTED_KEYS`. But the variable is named `$hitpayMasked`. Rename it to `$maskedKeys` in both the controller and the view.

In `index()`:
- Rename `$hitpayMasked` → `$maskedKeys` (lines 36-47, and compact on line 49)
- The existing loop already iterates all `ENCRYPTED_KEYS`, so it will automatically handle DPH keys

In `update()`:
- Add `'shipping'` to the tab mapping (line 77-79):

```php
$tab = match ($group) {
    'identity', 'general' => 'general',
    'shipping' => 'shipping',
    default => $group,
};
```

Add `testDphConnection()` method:

```php
public function testDphConnection()
{
    try {
        $dph = app(\App\Services\DeliveryPhService::class);
        $dph->login();

        return back()->with('success', 'DPH connection successful!')->with('tab', 'shipping');
    } catch (\Throwable $e) {
        return back()->with('error', 'DPH connection failed: ' . $e->getMessage())->with('tab', 'shipping');
    }
}
```

Also add a method to generate webhook secret:

```php
public function generateDphWebhookSecret()
{
    $secret = bin2hex(random_bytes(32));

    Setting::updateOrCreate(
        ['group' => 'shipping', 'key' => 'dph_webhook_secret'],
        ['value' => $secret]
    );

    return back()->with('success', 'Webhook secret generated.')->with('tab', 'shipping');
}
```

### Step 2: Add Shipping tab to settings view

In `resources/views/admin/settings/index.blade.php`:

Add to the `$tabs` array (line 9-15):
```php
['id' => 'shipping', 'label' => 'Shipping'],
```

Add the Shipping tab content panel after the Mail tab panel. This includes:
- DPH Enable/Disable toggle
- Mode dropdown (sandbox/production)
- Email/Password (encrypted, masked — same pattern as HitPay)
- Test Connection button
- Webhook Secret with generate + copy
- Store Pickup Address fields (text inputs + PSGC dropdowns for province/city/barangay)
- Flat rate / free threshold (moved from Checkout tab)

The full Blade markup for the shipping tab will follow the same pattern as the existing Payment tab for encrypted credential handling.

### Step 3: Add routes

In `routes/web.php`, inside the admin group (after line 190):

```php
Route::post('/settings/test-dph', [SettingController::class, 'testDphConnection'])->name('settings.test-dph');
Route::post('/settings/generate-dph-secret', [SettingController::class, 'generateDphWebhookSecret'])->name('settings.generate-dph-secret');
```

### Step 4: Commit

```
feat: add Shipping settings tab with DPH credentials and pickup address
```

---

## Task 8: DPH Webhook Controller

**Files:**
- Create: `app/Http/Controllers/Storefront/DphWebhookController.php`
- Modify: `routes/web.php` — add webhook route

### Step 1: Create webhook controller

DPH uses HTTP Triggers — status updates come as GET query parameters to a URL you configure in DPH dashboard. We read all data from query params. Validation is via a secret token.

```php
<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\OrderHistory;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\Shipment;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DphWebhookController extends Controller
{
    private const STATUS_MAP = [
        'ASSIGNED' => 'booked',
        'ACCEPTED' => 'confirmed',
        'ASSIGNING_RIDER' => 'confirmed',
        'START_PICKUP' => 'pickup-started',
        'DONE_PICKUP' => 'picked-up',
        'ARRIVED_AT_HUB' => 'in-transit',
        'START_DELIVERY' => 'out-for-delivery',
        'DONE' => 'delivered',
        'FAILED_PICKUP' => 'failed-pickup',
        'FAILED_DELIVERY' => 'failed-delivery',
        'CANCEL' => 'canceled',
        'IN_TRANSIT_FOR_RETURN' => 'returning',
        'RETURNED' => 'returned',
    ];

    public function __invoke(Request $request)
    {
        // 1. Validate secret
        $secret = $request->query('secret');
        $storedSecret = Setting::where('group', 'shipping')
            ->where('key', 'dph_webhook_secret')
            ->value('value');

        if (!$secret || !$storedSecret || !hash_equals($storedSecret, $secret)) {
            Log::warning('DPH webhook: invalid secret');
            return response('Unauthorized', 401);
        }

        // 2. Extract data from query params (DPH HTTP Trigger tokens)
        $postId = $request->query('postId');
        $status = $request->query('status');
        $subStatus = $request->query('subStatus');
        $trackingNumber = $request->query('trackingNumber');
        $trackingUrl = $request->query('trackingUrl');
        $shareableLink = $request->query('shareableLink');
        $feeApproximate = $request->query('fee_approximate');
        $feeActual = $request->query('fee_actual');
        $feeFinal = $request->query('fee_final');
        $remarks = $request->query('remarks');
        $courierName = $request->query('courierName');

        Log::info('DPH webhook received', ['postId' => $postId, 'status' => $status, 'subStatus' => $subStatus]);

        // 3. Find shipment
        if (!$postId) {
            Log::warning('DPH webhook: missing postId');
            return response('OK', 200);
        }

        $shipment = Shipment::where('dph_post_id', $postId)->first();

        if (!$shipment) {
            Log::warning('DPH webhook: shipment not found', ['postId' => $postId]);
            return response('OK', 200);
        }

        // 4. Idempotency: skip if already at this status + sub_status
        if ($shipment->dph_status === $status && $shipment->dph_sub_status === $subStatus) {
            return response('OK', 200);
        }

        // 5. Create shipment event
        $shipment->events()->create([
            'status' => $status ?? 'UNKNOWN',
            'sub_status' => $subStatus,
            'remarks' => $remarks,
            'meta' => $request->query(),
        ]);

        // 6. Update shipment record
        $shipmentUpdate = [
            'dph_status' => $status,
            'dph_sub_status' => $subStatus,
        ];

        if ($trackingNumber) $shipmentUpdate['tracking_number'] = $trackingNumber;
        if ($trackingUrl) $shipmentUpdate['tracking_url'] = $trackingUrl;
        if ($shareableLink) $shipmentUpdate['shareable_link'] = $shareableLink;
        if ($feeApproximate !== null) $shipmentUpdate['approximate_fee'] = (float) $feeApproximate;
        if ($feeActual !== null) $shipmentUpdate['actual_fee'] = (float) $feeActual;
        if ($feeFinal !== null) $shipmentUpdate['final_fee'] = (float) $feeFinal;

        if ($status === 'DONE') {
            $shipmentUpdate['delivered_at'] = now();
        }

        $shipment->update($shipmentUpdate);

        // 7. Map DPH status to order status and update order
        $order = $shipment->order;

        if ($order && isset(self::STATUS_MAP[$status])) {
            $orderStatusSlug = self::STATUS_MAP[$status];
            $storeId = $order->store_id;

            $newOrderStatus = OrderStatus::where('store_id', $storeId)
                ->where('slug', $orderStatusSlug)
                ->first();

            if ($newOrderStatus && (int) $order->order_status_id !== (int) $newOrderStatus->id) {
                $order->update(['order_status_id' => $newOrderStatus->id]);

                OrderHistory::create([
                    'order_id' => $order->id,
                    'order_status_id' => $newOrderStatus->id,
                    'comment' => "DPH: {$status}" . ($subStatus ? " ({$subStatus})" : '') . ($remarks ? " - {$remarks}" : ''),
                ]);

                StockService::adjustForStatusChange($order, $newOrderStatus);
            }

            // Update tracking number on order if provided
            if ($trackingNumber && !$order->tracking_number) {
                $order->update(['tracking_number' => $trackingNumber]);
            }
        }

        return response('OK', 200);
    }
}
```

### Step 2: Add webhook route

In `routes/web.php`, after the HitPay webhook route (line 57):

```php
// DPH webhook (no CSRF, no auth — secret-validated via query param)
Route::match(['get', 'post'], '/dph/webhook', \App\Http\Controllers\Storefront\DphWebhookController::class)
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
    ->name('dph.webhook');
```

Note: Using `Route::match(['get', 'post'], ...)` because DPH HTTP Triggers may use either method.

### Step 3: Commit

```
feat: add DPH webhook controller for shipment status tracking
```

---

## Task 9: Admin Shipment Controller — Booking & Management

**Files:**
- Create: `app/Http/Controllers/Admin/ShipmentController.php`
- Modify: `routes/web.php` — add admin shipment routes

### Step 1: Create ShipmentController

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\Shipment;
use App\Services\DeliveryPhService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShipmentController extends Controller
{
    /**
     * Fetch available couriers for an order (AJAX).
     */
    public function getAvailableRates(Order $order)
    {
        if (!DeliveryPhService::isEnabled()) {
            return response()->json(['error' => 'DPH is not enabled.'], 400);
        }

        try {
            $dph = app(DeliveryPhService::class);
            $pickupAddress = $this->getPickupAddress();

            $rates = $dph->getScheduledRates(
                ['province' => $pickupAddress['province'], 'city' => $pickupAddress['city'], 'barangay' => $pickupAddress['barangay'] ?? ''],
                ['province' => $order->shipping_state ?? '', 'city' => $order->shipping_city ?? '', 'barangay' => $order->shipping_barangay ?? ''],
                $this->getOrderDimensions($order),
                $this->getOrderWeight($order)
            );

            $options = [];
            foreach ($rates as $rate) {
                $partnerId = $rate['id'] ?? '';
                $products = $rate['products'] ?? [];
                $estimatedDays = $rate['estimatedDeliveryDays'] ?? null;

                foreach ($products as $product) {
                    $options[] = [
                        'partner_id' => $partnerId,
                        'partner_name' => $rate['name'] ?? $partnerId,
                        'service_type' => $rate['serviceType'] ?? 'scheduled',
                        'size' => $product['size'] ?? '',
                        'price' => (float) ($product['price'] ?? 0),
                        'max_weight' => $product['weight'] ?? null,
                        'estimated_days' => $estimatedDays,
                    ];
                }
            }

            return response()->json(['success' => true, 'rates' => $options]);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch DPH rates for order', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch rates: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Book a shipment with DPH.
     */
    public function book(Request $request, Order $order)
    {
        $request->validate([
            'partner_id' => 'required|string',
            'partner_name' => 'required|string',
            'service_type' => 'required|string|in:scheduled,onDemand',
            'product_size' => 'nullable|string',
            'approximate_fee' => 'required|numeric|min:0',
        ]);

        if ($order->shipment) {
            return response()->json(['error' => 'Order already has a shipment booked.'], 400);
        }

        try {
            $dph = app(DeliveryPhService::class);
            $pickupAddress = $this->getPickupAddress();
            $dimensions = $this->getOrderDimensions($order);
            $weight = $this->getOrderWeight($order);

            $pickupDetails = [
                'senderName' => $pickupAddress['name'],
                'contactNumber' => $pickupAddress['phone'],
                'emailAddress' => $pickupAddress['email'],
                'pickupDateTime' => now()->addHours(2)->format('Y-m-d\TH:i'),
                'pickupAddress' => $pickupAddress['address'],
                'pickupCity' => $pickupAddress['city'],
                'province' => $pickupAddress['province'],
                'barangay' => $pickupAddress['barangay'] ?? '',
                'postalCode' => $pickupAddress['postal_code'] ?? '',
            ];

            if ($pickupAddress['lat'] && $pickupAddress['lng']) {
                $pickupDetails['lat'] = (float) $pickupAddress['lat'];
                $pickupDetails['long'] = (float) $pickupAddress['lng'];
            }

            $deliveryDetails = [
                'recipientName' => trim($order->shipping_first_name . ' ' . $order->shipping_last_name),
                'contactNumber' => $order->phone ?? '',
                'emailAddress' => $order->email ?? '',
                'deliveryDateTime' => now()->addDays(3)->format('Y-m-d\TH:i'),
                'deliveryAddress' => $order->shipping_address_1 ?? '',
                'deliveryCity' => $order->shipping_city ?? '',
                'province' => $order->shipping_state ?? '',
                'barangay' => $order->shipping_barangay ?? '',
                'postalCode' => $order->shipping_postcode ?? '',
                'itemDescription' => 'Order #' . $order->id,
                'productSize' => $request->input('product_size', ''),
                'length' => DeliveryPhService::cmToInches($dimensions['length']),
                'width' => DeliveryPhService::cmToInches($dimensions['width']),
                'height' => DeliveryPhService::cmToInches($dimensions['height']),
                'weight' => $weight,
                'declaredValue' => (float) $order->total,
            ];

            $refNo = 'ORD-' . $order->id;
            $result = $dph->createPost($pickupDetails, $deliveryDetails, $request->input('partner_id'), $refNo);

            // Create shipment record
            $postId = $result['postId'] ?? $result['id'] ?? null;

            $shipment = Shipment::create([
                'order_id' => $order->id,
                'dph_post_id' => $postId,
                'dph_partner_id' => $request->input('partner_id'),
                'dph_partner_name' => $request->input('partner_name'),
                'dph_service_type' => $request->input('service_type'),
                'dph_status' => 'ASSIGNED',
                'approximate_fee' => $request->input('approximate_fee'),
                'booked_at' => now(),
                'meta' => $result,
            ]);

            // Update order status to "booked"
            $bookedStatus = OrderStatus::where('store_id', $order->store_id)
                ->where('slug', 'booked')
                ->first();

            if ($bookedStatus) {
                $order->update([
                    'order_status_id' => $bookedStatus->id,
                    'dph_courier_name' => $request->input('partner_name'),
                ]);

                OrderHistory::create([
                    'order_id' => $order->id,
                    'order_status_id' => $bookedStatus->id,
                    'comment' => 'Shipment booked via DPH — ' . $request->input('partner_name'),
                ]);

                StockService::adjustForStatusChange($order, $bookedStatus);
            }

            return response()->json([
                'success' => true,
                'shipment' => $shipment->fresh(),
                'message' => 'Shipment booked successfully!',
            ]);
        } catch (\Throwable $e) {
            Log::error('DPH booking failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Booking failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Cancel a shipment.
     */
    public function cancel(Order $order)
    {
        $shipment = $order->shipment;

        if (!$shipment || !$shipment->dph_post_id) {
            return response()->json(['error' => 'No shipment to cancel.'], 400);
        }

        // Only allow cancellation before pickup
        $nonCancellable = ['DONE_PICKUP', 'ARRIVED_AT_HUB', 'START_DELIVERY', 'DONE', 'CANCEL', 'RETURNED'];
        if (in_array($shipment->dph_status, $nonCancellable)) {
            return response()->json(['error' => 'Shipment cannot be cancelled at this stage.'], 400);
        }

        try {
            $dph = app(DeliveryPhService::class);
            $dph->cancelPost(
                $shipment->dph_post_id,
                $shipment->tracking_number ?? '',
                $shipment->dph_partner_id
            );

            $shipment->update(['dph_status' => 'CANCEL']);

            $cancelledStatus = OrderStatus::where('store_id', $order->store_id)
                ->where('slug', 'canceled')
                ->first();

            if ($cancelledStatus) {
                $order->update(['order_status_id' => $cancelledStatus->id]);

                OrderHistory::create([
                    'order_id' => $order->id,
                    'order_status_id' => $cancelledStatus->id,
                    'comment' => 'Shipment cancelled via DPH.',
                ]);

                StockService::adjustForStatusChange($order, $cancelledStatus);
            }

            return response()->json(['success' => true, 'message' => 'Shipment cancelled.']);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Cancellation failed: ' . $e->getMessage()], 500);
        }
    }

    private function getPickupAddress(): array
    {
        $settings = Setting::where('group', 'shipping')
            ->whereIn('key', [
                'dph_pickup_address', 'dph_pickup_city', 'dph_pickup_province',
                'dph_pickup_barangay', 'dph_pickup_postal_code',
                'dph_pickup_lat', 'dph_pickup_lng',
                'dph_pickup_contact_name', 'dph_pickup_contact_number', 'dph_pickup_contact_email',
            ])
            ->pluck('value', 'key');

        return [
            'address' => $settings->get('dph_pickup_address', ''),
            'city' => $settings->get('dph_pickup_city', ''),
            'province' => $settings->get('dph_pickup_province', ''),
            'barangay' => $settings->get('dph_pickup_barangay', ''),
            'postal_code' => $settings->get('dph_pickup_postal_code', ''),
            'lat' => $settings->get('dph_pickup_lat', ''),
            'lng' => $settings->get('dph_pickup_lng', ''),
            'name' => $settings->get('dph_pickup_contact_name', ''),
            'phone' => $settings->get('dph_pickup_contact_number', ''),
            'email' => $settings->get('dph_pickup_contact_email', ''),
        ];
    }

    private function getOrderDimensions(Order $order): array
    {
        $order->loadMissing('products.product');

        $maxLength = 0;
        $maxWidth = 0;
        $maxHeight = 0;

        foreach ($order->products as $item) {
            if ($item->product) {
                $maxLength = max($maxLength, $item->product->length ?: 0);
                $maxWidth = max($maxWidth, $item->product->width ?: 0);
                $maxHeight = max($maxHeight, $item->product->height ?: 0);
            }
        }

        return [
            'length' => $maxLength ?: 10,
            'width' => $maxWidth ?: 10,
            'height' => $maxHeight ?: 10,
        ];
    }

    private function getOrderWeight(Order $order): float
    {
        $order->loadMissing('products.product', 'products.productVariant');

        $total = 0;
        foreach ($order->products as $item) {
            $weight = $item->productVariant?->weight ?: ($item->product?->weight ?: 0);
            $total += $weight * $item->quantity;
        }

        return $total ?: 0.5;
    }
}
```

### Step 2: Add admin routes

In `routes/web.php`, inside the admin middleware group, after the orders routes (after line 129):

```php
// Shipments (DPH)
Route::get('/orders/{order}/shipment/rates', [\App\Http\Controllers\Admin\ShipmentController::class, 'getAvailableRates'])->name('orders.shipment.rates');
Route::post('/orders/{order}/shipment/book', [\App\Http\Controllers\Admin\ShipmentController::class, 'book'])->name('orders.shipment.book');
Route::post('/orders/{order}/shipment/cancel', [\App\Http\Controllers\Admin\ShipmentController::class, 'cancel'])->name('orders.shipment.cancel');
```

### Step 3: Commit

```
feat: add admin ShipmentController for DPH booking and cancellation
```

---

## Task 10: Admin Order Page — Shipment Booking Panel

**Files:**
- Modify: `resources/views/admin/orders/form.blade.php` — add shipment booking UI to Shipping tab
- Modify: `app/Http/Controllers/Admin/OrderController.php` — pass shipment data to view

### Step 1: Update OrderController

In the `edit()` method, load the shipment relationship and pass it to the view:

```php
$order->load('shipment.events');
```

### Step 2: Update the Shipping & Tracking tab in form.blade.php

Replace the existing Shipping & Tracking tab content (lines 337-367) with:

1. **If order has a shipment**: Show shipment info card (status, tracking, fees) + event timeline + cancel button
2. **If no shipment**: Show existing courier/tracking fields + "Book via DPH" button that opens an inline panel

The DPH booking panel uses Alpine.js:
- Clicking "Book Shipment" fetches rates from `/admin/orders/{id}/shipment/rates`
- Shows courier options as cards with price + estimated days
- Admin selects one and clicks "Confirm Booking"
- Posts to `/admin/orders/{id}/shipment/book`

The existing courier dropdown and tracking number fields remain for manual (non-DPH) tracking.

### Step 3: Commit

```
feat: add shipment booking panel to admin order page
```

---

## Task 11: Checkout — DPH Courier Selection

**Files:**
- Modify: `app/Http/Controllers/Storefront/CheckoutController.php` — fetch DPH rates
- Modify: `app/Services/CheckoutService.php` — accept DPH shipping method in placeOrder
- Modify: `resources/views/storefront/checkout/index.blade.php` — show courier options with estimated days
- Add route: `POST /checkout/shipping-rates` for AJAX rate fetching

### Step 1: Add shipping rates endpoint

In `CheckoutController.php`, add method:

```php
public function getShippingRates(Request $request)
{
    $request->validate([
        'address_id' => 'required|exists:customer_addresses,id',
    ]);

    $customer = Auth::guard('customer')->user();
    $address = $customer->addresses()->findOrFail($request->input('address_id'));
    $items = $this->cartService->getItems();
    $subtotal = $this->cartService->getSubtotal();

    $quoteService = app(\App\Services\ShippingQuoteService::class);
    $quotes = $quoteService->getQuotes([
        'province' => $address->state,
        'city' => $address->city,
        'barangay' => $address->barangay,
    ], $items, $subtotal);

    return response()->json(['methods' => $quotes]);
}
```

### Step 2: Update CheckoutController::index()

Replace the static `getShippingMethods()` call with `ShippingQuoteService`:

```php
// If customer has a default address, fetch DPH rates immediately
$shippingMethods = [];
if ($defaultAddress) {
    $quoteService = app(\App\Services\ShippingQuoteService::class);
    $shippingMethods = $quoteService->getQuotes([
        'province' => $defaultAddress->state,
        'city' => $defaultAddress->city,
        'barangay' => $defaultAddress->barangay,
    ], $items, $subtotal);
} else {
    $shippingMethods = $this->checkoutService->getShippingMethods($subtotal);
}
```

### Step 3: Update CheckoutService::placeOrder()

Modify `placeOrder()` to handle DPH shipping methods. When `shipping_method` starts with `dph_`, parse the partner_id and store courier name:

In the shipping method resolution block (lines 94-98), add DPH awareness:

```php
$shippingMethod = collect($shippingMethods)->firstWhere('key', $shippingMethodKey);
if (!$shippingMethod && str_starts_with($shippingMethodKey, 'dph_')) {
    // DPH method selected but not in current flat-rate list — use submitted cost
    $shippingMethod = [
        'key' => $shippingMethodKey,
        'name' => $shippingMethodName ?? 'DPH Courier',
        'cost' => $shippingCostOverride ?? 0,
    ];
}
```

Also accept additional parameters in `placeOrder()`:
- `?string $shippingMethodName = null`
- `?float $shippingCostOverride = null`

And store `dph_courier_name` on the order when it's a DPH method.

### Step 4: Update checkout view

In the checkout Alpine.js `checkoutWizard`:
- Add a method to re-fetch shipping rates when address changes
- Show estimated delivery days next to each DPH option
- Existing radio button template works — just needs `estimated_days` display

### Step 5: Add route

In `routes/web.php`, inside the checkout middleware group (after line 63):

```php
Route::post('/checkout/shipping-rates', [StorefrontCheckoutController::class, 'getShippingRates'])->name('checkout.shipping-rates');
```

### Step 6: Update placeOrder validation and call

In `CheckoutController::placeOrder()`, add optional fields:

```php
'shipping_method_name' => ['nullable', 'string', 'max:255'],
'shipping_cost' => ['nullable', 'numeric', 'min:0'],
```

Pass these to `CheckoutService::placeOrder()`.

### Step 7: Commit

```
feat: integrate DPH courier selection into checkout flow
```

---

## Task 12: Cart Page — Shipping Estimate

**Files:**
- Modify: `resources/views/storefront/cart/index.blade.php` — add shipping estimate section
- Modify: `app/Http/Controllers/Storefront/CartController.php` — add estimate endpoint
- Add route: `POST /cart/shipping-estimate`

### Step 1: Add estimate endpoint to CartController

```php
public function estimateShipping(Request $request)
{
    $request->validate([
        'province' => 'required|string',
        'city' => 'required|string',
    ]);

    $items = $this->cartService->getItems();
    $subtotal = $this->cartService->getSubtotal();

    $quoteService = app(\App\Services\ShippingQuoteService::class);
    $quotes = $quoteService->getQuotes([
        'province' => $request->input('province'),
        'city' => $request->input('city'),
        'barangay' => $request->input('barangay', ''),
    ], $items, $subtotal);

    return response()->json(['methods' => $quotes]);
}
```

### Step 2: Add route

In `routes/web.php`, in the Cart routes section (after line 52):

```php
Route::post('/cart/shipping-estimate', [StorefrontCartController::class, 'estimateShipping'])->name('cart.estimate-shipping');
```

### Step 3: Update cart view

Replace the "Calculated at checkout" text (line 67-70) with an expandable "Estimate Shipping" section:

- Province + City cascading dropdowns (reuse existing PSGC components)
- "Get Estimate" button → AJAX call to `/cart/shipping-estimate`
- Shows list of courier options with prices
- Non-blocking — cart works fine without estimating

### Step 4: Commit

```
feat: add shipping estimate section to cart page
```

---

## Task 13: Routes Cleanup & Import Statements

**Files:**
- Modify: `routes/web.php` — add all new route imports and verify no conflicts

### Step 1: Add use statements at top of routes/web.php

```php
use App\Http\Controllers\Admin\ShipmentController;
use App\Http\Controllers\Storefront\DphWebhookController;
```

### Step 2: Verify all routes are registered

Run: `php artisan route:list --name=dph`
Run: `php artisan route:list --name=shipment`

Expected: Webhook route + admin shipment routes + cart estimate + checkout rates all listed.

### Step 3: Commit

```
chore: consolidate DPH route imports and verify registration
```

---

## Task 14: End-to-End Manual Testing

### Step 1: Configure DPH sandbox credentials in admin

1. Go to `/admin/settings` → Shipping tab
2. Enable DPH
3. Set mode to Sandbox
4. Enter sandbox email + password
5. Click "Test Connection" → should show success
6. Generate webhook secret
7. Fill in pickup address fields

### Step 2: Test cart shipping estimate

1. Add products to cart
2. Go to cart page
3. Expand "Estimate Shipping"
4. Select province + city
5. Click "Get Estimate"
6. Verify courier options appear with prices

### Step 3: Test checkout courier selection

1. Go to checkout with items in cart
2. Select/add a shipping address
3. Verify DPH courier options appear in sidebar
4. Select a DPH courier
5. Place order
6. Verify order created with correct shipping cost and courier name

### Step 4: Test admin shipment booking

1. Go to admin → Orders → open the order
2. Go to Shipping & Tracking tab
3. Click "Book Shipment"
4. Verify courier options load from DPH
5. Select a courier and confirm booking
6. Verify shipment record created, order status changed to "Booked"

### Step 5: Test webhook (manual)

Simulate a webhook call:
```bash
curl "https://dev.newgearday.com/dph/webhook?secret=YOUR_SECRET&postId=YOUR_POST_ID&status=DONE_PICKUP&subStatus=&trackingNumber=TEST123&trackingUrl=&shareableLink=&fee_approximate=150&fee_actual=150&fee_final=150&remarks=Picked+up&courierName=NinjaVan"
```

Verify:
- Shipment event created
- Shipment status updated
- Order status changed to "picked-up"
- Order tracking number set

### Step 6: Commit

```
feat: complete DPH shipping integration — rates, booking, webhook tracking
```

---

## Implementation Order Summary

| Task | Component | Dependencies |
|------|-----------|-------------|
| 1 | Migrations — shipments + events tables | None |
| 2 | Migrations — orders column + order statuses | None |
| 3 | Shipment + ShipmentEvent models | Tasks 1-2 |
| 4 | DeliveryPhService — auth | None |
| 5 | DeliveryPhService — API methods | Task 4 |
| 6 | Modular Shipping Provider System (interface + flat rate + DPH provider + aggregator) | Tasks 4-5 |
| 7 | Admin Settings — Shipping tab (per-module enable/disable) | Tasks 4-5 |
| 8 | DPH Webhook Controller | Tasks 3, 5 |
| 9 | Admin ShipmentController | Tasks 3, 5 |
| 10 | Admin Order Page — booking UI | Task 9 |
| 11 | Checkout — courier selection | Task 6 |
| 12 | Cart — shipping estimate | Task 6 |
| 13 | Routes cleanup | Tasks 8-12 |
| 14 | End-to-end testing | All |

**Parallelizable groups:**
- Tasks 1+2 (independent migrations)
- Tasks 4+5 → 6 (service chain)
- Tasks 7, 8, 9 (independent controllers, all depend on service)
- Tasks 10, 11, 12 (independent UIs, all depend on controllers)

**Adding future shipping providers:**
Create a new class implementing `ShippingProviderInterface`, add it to `ShippingQuoteService::getProviders()`, add enable/disable toggle in Shipping settings tab. Zero changes needed to cart/checkout.
