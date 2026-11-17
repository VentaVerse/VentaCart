<?php

namespace Themes\Xelga\Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Services\ExtensionManager;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Themes\Xelga\Rendering\Storefront;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';

/**
 * The reviews section draws the summary and one page of reviews; the rest
 * arrive from core when the shopper presses Show more reviews.
 */
class XelgaProductReviewsTest extends TestCase
{
    use RefreshDatabase;
    use XelgaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installXelga();
        app(ExtensionManager::class)->install('reviews');
        app(ExtensionManager::class)->bootEnabled();
        (new \ReflectionProperty(Application::class, 'isRunningInConsole'))->setValue($this->app, false);
    }

    private function productWithReviews(int $count): void
    {
        $store = Store::firstOrCreate(['code' => 'demo'], ['name' => 'Demo Store']);
        $product = Product::create([
            'store_id' => $store->id, 'name' => 'Linen Shirt', 'slug' => 'linen-shirt', 'sku' => 'DEMO-001',
            'price' => 100, 'quantity' => 5, 'is_active' => true,
        ]);
        foreach (range(1, $count) as $i) {
            \Extensions\Reviews\Models\Review::create([
                'product_id' => $product->id, 'token' => "tok-{$i}", 'name' => "Juan Dela Cruz {$i}", 'email' => "juan{$i}@example.com",
                'rating' => 5, 'title' => "Review {$i}", 'body' => 'Good.', 'status' => 'approved', 'published_at' => now()->subDays(100 - $i),
            ]);
        }
    }

    private function page(): string
    {
        Storefront::forgetRequestState();

        return $this->get('/product/linen-shirt')->assertOk()->getContent();
    }

    public function test_many_reviews_send_one_page_and_a_link_to_the_next(): void
    {
        $this->productWithReviews(40);

        $html = $this->page();

        $this->assertStringContainsString('From 40 reviews', $html);
        $drawn = substr($html, 0, (int) strpos($html, '<template data-review-template>'));
        $this->assertSame(10, substr_count($drawn, '<li class="review"'));
        $this->assertSame(5, substr_count($drawn, 'data-review-more'));
        $this->assertStringContainsString('data-reviews-url="' . e(route('product.reviews', ['product' => 'linen-shirt', 'page' => 2])) . '"', $html);
        $this->assertStringContainsString('<template data-review-template>', $html);
        $this->assertStringNotContainsString('Review 30<', $html);
        $this->assertStringNotContainsString('@example.com', $html);
    }

    public function test_a_few_reviews_need_no_link(): void
    {
        $this->productWithReviews(3);

        $html = $this->page();

        $this->assertSame(3, substr_count($html, '<li class="review"'));
        $this->assertStringNotContainsString('data-reviews-more', $html);
    }
}
