<?php

namespace Tests\Feature\Storefront;

use App\Models\Product;
use App\Models\Store;
use App\Services\BadgeService;
use App\Services\ExtensionManager;
use App\Services\ThemeService;
use App\Support\RequestCache;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * A storefront page reads each cached value once. With the database cache
 * store every cache read is a query, and the extension registry, the badge
 * lists and the currencies were read again for every card and every price.
 */
class RequestCacheTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, int> cache reads by key */
    private array $reads = [];

    protected function setUp(): void
    {
        parent::setUp();
        RequestCache::flush();
        Event::listen([CacheHit::class, CacheMissed::class], function ($e) {
            $this->reads[$e->key] = ($this->reads[$e->key] ?? 0) + 1;
        });
    }

    /** @param \Closure(): mixed $work */
    private function duringRequest(\Closure $work): void
    {
        Route::get('/__request-cache-probe', function () use ($work) {
            $work();

            return 'ok';
        })->middleware('web');

        $this->get('/__request-cache-probe')->assertOk();
    }

    public function test_a_request_reads_the_registry_badges_and_currencies_once_however_often_it_asks(): void
    {
        $store = Store::firstOrCreate(['code' => 'demo'], ['name' => 'Demo Store']);
        $products = collect(range(1, 3))->map(fn ($i) => Product::create([
            'store_id' => $store->id, 'name' => "Demo {$i}", 'slug' => "demo-{$i}", 'sku' => "DEMO-00{$i}",
            'price' => 100, 'quantity' => 1, 'is_active' => true,
        ]));
        $this->reads = [];

        $this->duringRequest(function () use ($products) {
            foreach (range(1, 5) as $ignored) {
                app(ExtensionManager::class)->registry();
                app(ThemeService::class)->getActiveCurrencies();
            }
            foreach ($products as $product) {
                app(BadgeService::class)->getBadgesForProduct($product);
            }
        });

        foreach (['theme_currencies', 'badge_new_ids', 'badge_hot_ids', 'free_shipping_badge_rules'] as $key) {
            $this->assertSame(1, $this->reads[$key] ?? 0, "{$key} read more than once");
        }
        // Middleware that runs before routing (redirects) may read it once more.
        $this->assertLessThanOrEqual(2, $this->reads[ExtensionManager::REGISTRY_CACHE_KEY] ?? 0);
    }

    public function test_a_change_made_during_the_request_is_seen_by_the_rest_of_it(): void
    {
        $store = Store::firstOrCreate(['code' => 'demo'], ['name' => 'Demo Store']);
        $make = fn (string $sku) => Product::create([
            'store_id' => $store->id, 'name' => $sku, 'slug' => strtolower($sku), 'sku' => $sku,
            'price' => 100, 'quantity' => 1, 'is_active' => true,
        ]);
        $isNew = fn (Product $p) => in_array('new', array_column(app(BadgeService::class)->getBadgesForProduct($p), 'type'), true);
        $seen = [];

        $this->duringRequest(function () use (&$seen, $isNew, $make) {
            $seen[] = $isNew($make('DEMO-001'));
            // Saving a product clears the badge lists; the kept copy goes with them.
            $seen[] = $isNew($make('DEMO-002'));
        });

        $this->assertSame([true, true], $seen);
    }

    public function test_outside_a_routed_request_every_call_reads_through(): void
    {
        app(ExtensionManager::class)->registry();
        app(ExtensionManager::class)->registry();

        $this->assertSame(2, $this->reads[ExtensionManager::REGISTRY_CACHE_KEY] ?? 0);
    }
}
