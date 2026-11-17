<?php

namespace Tests\Feature\Storefront;

use App\Models\Product;
use App\Models\Store;
use App\Support\ProductFeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * A product page view is counted without being treated as an edit: the
 * count goes up, but updated_at stays where the last real change left it,
 * and the badge caches every listing reads are not thrown away.
 */
class ProductViewCountTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $slug, int $views): Product
    {
        $store = Store::firstOrCreate(['code' => 'demo'], ['name' => 'Demo Store']);

        return Product::create([
            'store_id' => $store->id, 'name' => ucfirst($slug), 'slug' => $slug, 'sku' => 'DEMO-' . strtoupper($slug),
            'price' => 100, 'quantity' => 5, 'is_active' => true, 'views' => $views,
        ]);
    }

    public function test_a_view_counts_without_moving_updated_at_or_clearing_badge_caches(): void
    {
        $product = $this->product('lamp', 4);
        $product->forceFill(['updated_at' => now()->subWeek()])->saveQuietly();
        $before = $product->fresh()->updated_at;
        Cache::put('badge_hot_ids', [$product->id], 3600);

        $this->get('/product/lamp')->assertOk();
        $this->get('/product/lamp')->assertOk();

        $fresh = $product->fresh();
        $this->assertSame(6, (int) $fresh->views);
        $this->assertTrue($before->equalTo($fresh->updated_at), 'a view is not an edit');
        $this->assertSame([$product->id], Cache::get('badge_hot_ids'));
    }

    public function test_most_viewed_still_follows_the_count(): void
    {
        $this->product('desk', 3);
        $this->product('chair', 2);

        $this->get('/product/chair')->assertOk();
        $this->get('/product/chair')->assertOk();

        $this->assertSame(['chair', 'desk'], ProductFeed::get(['filter' => 'most_viewed'])->pluck('slug')->all());
    }
}
