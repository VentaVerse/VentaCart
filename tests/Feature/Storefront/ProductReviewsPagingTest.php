<?php

namespace Tests\Feature\Storefront;

use App\Models\Product;
use App\Models\Store;
use App\Services\ExtensionManager;
use App\Support\Reviews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A product page carries the review count, the average and the first page
 * of approved reviews; further pages come from product.reviews, a page at a
 * time, approved only, and never with the reviewer's email.
 */
class ProductReviewsPagingTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        app(ExtensionManager::class)->install('reviews');
        app(ExtensionManager::class)->bootEnabled();

        $store = Store::firstOrCreate(['code' => 'demo'], ['name' => 'Demo Store']);
        $this->product = Product::create([
            'store_id' => $store->id, 'name' => 'Linen Shirt', 'slug' => 'linen-shirt', 'sku' => 'DEMO-001',
            'price' => 100, 'quantity' => 5, 'is_active' => true,
        ]);
        foreach (range(1, 23) as $i) {
            $this->review($i, 'approved', $i % 2 ? 5 : 4);
        }
        $this->review(99, 'pending', 1);
    }

    private function review(int $i, string $status, int $rating): void
    {
        \Extensions\Reviews\Models\Review::create([
            'product_id' => $this->product->id, 'token' => "tok-{$i}", 'name' => "Maria Santos {$i}",
            'email' => "maria{$i}@example.com", 'rating' => $rating, 'title' => "Review {$i}", 'body' => 'Fits well.',
            'status' => $status, 'published_at' => now()->subDays(100 - $i),
        ]);
    }

    public function test_the_page_gets_the_summary_and_the_first_page_only(): void
    {
        $response = $this->get('/product/linen-shirt')->assertOk();

        $response->assertViewHas('reviewCount', 23);
        $response->assertViewHas('averageRating', fn ($avg) => abs($avg - (12 * 5 + 11 * 4) / 23) < 0.001);
        $response->assertViewHas('reviewsFirstPage', fn ($page) => $page->count() === Reviews::PAGE_SIZE && $page->first()->title === 'Review 23');
        $response->assertViewHas('reviewsMoreUrl', route('product.reviews', ['product' => 'linen-shirt', 'page' => 2]));
    }

    public function test_further_pages_come_as_json_newest_first_without_emails(): void
    {
        $page2 = $this->getJson('/product/linen-shirt/reviews?page=2')->assertOk()->json();

        $this->assertTrue($page2['success']);
        $this->assertCount(Reviews::PAGE_SIZE, $page2['data']['reviews']);
        $this->assertSame('Review 13', $page2['data']['reviews'][0]['title']);
        $this->assertStringNotContainsString('@example.com', json_encode($page2));
        $this->assertSame(route('product.reviews', ['product' => 'linen-shirt', 'page' => 3]), $page2['data']['next_page_url']);

        $page3 = $this->getJson('/product/linen-shirt/reviews?page=3')->assertOk()->json('data');
        $this->assertSame(['Review 3', 'Review 2', 'Review 1'], array_column($page3['reviews'], 'title'));
        $this->assertNull($page3['next_page_url']);

        $sized = $this->getJson('/product/linen-shirt/reviews?page=1&per_page=20')->assertOk()->json('data');
        $this->assertCount(20, $sized['reviews']);
        $this->assertSame(route('product.reviews', ['product' => 'linen-shirt', 'page' => 2, 'per_page' => 20]), $sized['next_page_url']);
    }

    public function test_bad_pages_and_hidden_products_are_refused(): void
    {
        $this->getJson('/product/linen-shirt/reviews?page=0')->assertStatus(422);
        $this->getJson('/product/linen-shirt/reviews?per_page=500')->assertStatus(422);

        $this->product->update(['is_active' => false]);
        $this->getJson('/product/linen-shirt/reviews')->assertNotFound();
    }
}
