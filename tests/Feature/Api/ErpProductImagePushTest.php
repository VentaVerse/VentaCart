<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\ProductImage;
use Extensions\Reviews\Models\Review;
use App\Models\Setting;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A product push is all or nothing.
 *
 * Venta used to answer 201 with an `images_failed` list in the body when it
 * could not fetch a product's images. Every client that checks the status
 * code, the owner's ERP included, filed that as a clean success, so products
 * landed with no images and nothing raised an alarm. The partial result was
 * never the problem; dressing it as success was. Any image the caller asked
 * for and Venta could not fetch now fails the whole request with 422, rolls
 * the product back, and deletes the files that request had already written.
 *
 * Hosts are literal IP addresses on purpose. SafeUrlFetcher validates literal
 * IPs without a DNS lookup, so these tests never touch the network at all:
 * 203.0.113.x is public as far as the guard is concerned, 192.168.x.x is the
 * private address the owner's ERP actually served its images from.
 */
class ErpProductImagePushTest extends TestCase
{
    use RefreshDatabase;

    private const GOOD = 'http://203.0.113.10/good.jpg';

    private const GOOD_TWO = 'http://203.0.113.10/good-two.png';

    private const MISSING = 'http://203.0.113.10/missing.jpg';

    private const HUGE = 'http://203.0.113.10/huge.jpg';

    private const HTML = 'http://203.0.113.10/not-an-image.jpg';

    private const PRIVATE_HOST = 'http://192.168.0.224/image/cache/product.jpg';

    protected function setUp(): void
    {
        parent::setUp();

        Store::create(['name' => 'Test Store', 'code' => 'test']);

        // The push endpoints live in extensions now (wave 3).
        app(\App\Services\ExtensionManager::class)->install('erp-sync');
        app(\App\Services\ExtensionManager::class)->install('reviews');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        Storage::fake('public');

        Setting::create([
            'group' => 'api',
            'key' => 'erp_token',
            'value' => Crypt::encryptString('erp-test-token'),
        ]);

        Http::preventStrayRequests();
        Http::fake([
            '*/good.jpg' => Http::response('jpeg-bytes', 200, ['Content-Type' => 'image/jpeg']),
            '*/good-two.png' => Http::response('png-bytes', 200, ['Content-Type' => 'image/png']),
            '*/missing.jpg' => Http::response('not found', 404),
            '*/huge.jpg' => Http::response(str_repeat('x', 5 * 1024 * 1024 + 1), 200, ['Content-Type' => 'image/jpeg']),
            '*/not-an-image.jpg' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html; charset=utf-8']),
        ]);
    }

    private function push(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken('erp-test-token')->postJson('/api/v1/products', array_merge([
            'name' => 'Boss DS-1 Distortion',
            'sku' => 'BOSS-DS1',
            'price' => 3500,
        ], $overrides));
    }

    private function existingProduct(): Product
    {
        return Product::create([
            'store_id' => 1,
            'name' => 'Original Name',
            'sku' => 'EXISTING-1',
            'slug' => 'original-name',
            'price' => 1000,
            'quantity' => 7,
        ]);
    }

    public function test_create_with_private_address_image_is_refused_and_leaves_no_product(): void
    {
        $response = $this->push(['images' => [self::PRIVATE_HOST]]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'images_unavailable')
            ->assertJsonPath('images_failed.0.url', self::PRIVATE_HOST);

        $reason = $response->json('images_failed.0.reason');

        // The reason has to explain itself to whoever reads the ERP's log, so
        // it names the host, says what is wrong with it, and says what to do.
        $this->assertStringContainsString("host '192.168.0.224'", $reason);
        $this->assertStringContainsString('private or internal network address', $reason);
        $this->assertStringContainsString('public internet', $reason);
        $this->assertStringNotContainsString('Exception', $reason);

        $this->assertSame(0, Product::count());
        $this->assertSame(0, ProductImage::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_create_with_a_missing_image_is_refused(): void
    {
        $response = $this->push(['images' => [self::MISSING]]);

        $response->assertStatus(422)
            ->assertJsonPath('images_failed.0.url', self::MISSING);

        $this->assertStringContainsString('HTTP 404', $response->json('images_failed.0.reason'));
        $this->assertSame(0, Product::count());
    }

    public function test_create_with_an_oversized_image_is_refused(): void
    {
        $response = $this->push(['images' => [self::HUGE]]);

        $response->assertStatus(422);

        $this->assertStringContainsString('over the 5 MB limit', $response->json('images_failed.0.reason'));
        $this->assertSame(0, Product::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_create_with_a_non_image_content_type_is_refused(): void
    {
        $response = $this->push(['images' => [self::HTML]]);

        $response->assertStatus(422);

        $this->assertStringContainsString('text/html', $response->json('images_failed.0.reason'));
        $this->assertStringContainsString('not an accepted image format', $response->json('images_failed.0.reason'));
        $this->assertSame(0, Product::count());
    }

    public function test_create_with_valid_images_still_succeeds(): void
    {
        $response = $this->push(['images' => [self::GOOD, self::GOOD_TWO]]);

        $response->assertStatus(201)
            ->assertJsonCount(2, 'images')
            ->assertJsonMissingPath('images_failed');

        $product = Product::firstWhere('sku', 'BOSS-DS1');

        $this->assertNotNull($product);
        $this->assertSame(2, $product->images()->count());
        $this->assertCount(2, Storage::disk('public')->allFiles());

        foreach ($product->images as $image) {
            Storage::disk('public')->assertExists($image->path);
        }
    }

    public function test_a_partial_batch_rolls_back_and_leaves_no_orphaned_files(): void
    {
        $response = $this->push([
            'images' => [self::GOOD, self::GOOD_TWO, self::MISSING],
        ]);

        $response->assertStatus(422)
            ->assertJsonCount(1, 'images_failed')
            ->assertJsonPath('images_failed.0.url', self::MISSING);

        $this->assertStringContainsString('1 of 3 image(s)', $response->json('message'));

        // The two images that downloaded fine had already been written to disk
        // when the third failed. Their rows are gone with the rollback, so the
        // files have to go with them.
        $this->assertSame(0, Product::count());
        $this->assertSame(0, ProductImage::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_failed_image_never_touches_images_another_product_shares(): void
    {
        $existing = $this->existingProduct();
        Storage::disk('public')->put('products/_unbranded/shared.jpg', 'shared-bytes');
        ProductImage::create([
            'product_id' => $existing->id,
            'path' => 'products/_unbranded/shared.jpg',
            'sort_order' => 0,
        ]);

        $this->push(['images' => [self::GOOD, self::MISSING]])->assertStatus(422);

        Storage::disk('public')->assertExists('products/_unbranded/shared.jpg');
        $this->assertSame(['products/_unbranded/shared.jpg'], Storage::disk('public')->allFiles());
        $this->assertSame(1, ProductImage::count());
    }

    public function test_an_update_whose_image_fails_leaves_the_product_exactly_as_it_was(): void
    {
        $product = $this->existingProduct();

        $response = $this->withToken('erp-test-token')->putJson('/api/v1/products/EXISTING-1', [
            'name' => 'Renamed By ERP',
            'price' => 9999,
            'quantity' => 42,
            'images' => [self::PRIVATE_HOST],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'images_unavailable');

        $this->assertStringContainsString('Product not updated', $response->json('message'));

        $product->refresh();

        $this->assertSame('Original Name', $product->name);
        $this->assertSame('1000.00', (string) $product->price);
        $this->assertSame(7, $product->quantity);
        $this->assertSame('original-name', $product->slug);
        $this->assertSame(0, ProductImage::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_an_update_that_sends_no_images_still_succeeds(): void
    {
        $product = $this->existingProduct();

        $this->withToken('erp-test-token')->putJson('/api/v1/products/EXISTING-1', [
            'name' => 'Renamed By ERP',
            'quantity' => 42,
        ])->assertStatus(200);

        $product->refresh();

        $this->assertSame('Renamed By ERP', $product->name);
        $this->assertSame(42, $product->quantity);
    }

    public function test_an_update_with_reachable_images_appends_them(): void
    {
        $this->existingProduct();

        $this->withToken('erp-test-token')->putJson('/api/v1/products/EXISTING-1', [
            'images' => [self::GOOD],
        ])->assertStatus(200)->assertJsonCount(1, 'images');

        $this->assertSame(1, ProductImage::count());
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_a_push_without_dimensions_succeeds_and_stores_zero(): void
    {
        $this->push()->assertStatus(201);

        $product = Product::firstWhere('sku', 'BOSS-DS1');

        $this->assertSame('0.00', (string) $product->length);
        $this->assertSame('0.00', (string) $product->width);
        $this->assertSame('0.00', (string) $product->height);
    }

    public function test_an_update_that_nulls_the_dimensions_falls_back_to_zero(): void
    {
        $product = $this->existingProduct();

        $this->withToken('erp-test-token')->putJson('/api/v1/products/EXISTING-1', [
            'length' => null,
            'width' => null,
            'height' => null,
            'weight' => null,
            'cost' => null,
        ])->assertStatus(200);

        $product->refresh();

        $this->assertSame(0.0, (float) $product->length);
        $this->assertSame(0.0, (float) $product->width);
        $this->assertSame(0.0, (float) $product->height);
        $this->assertSame(0.0, (float) $product->weight);
        $this->assertSame(0.0, (float) $product->cost);
    }

    public function test_a_push_whose_variants_omit_cost_succeeds(): void
    {
        $this->push([
            'variants' => [
                ['sku' => 'BOSS-DS1-RED', 'name' => 'Red', 'option_name' => 'Colour', 'price' => 3600, 'quantity' => 2],
            ],
        ])->assertStatus(201);

        $product = Product::firstWhere('sku', 'BOSS-DS1');

        $this->assertSame('0.00', (string) $product->variants()->first()->cost);
    }

    public function test_the_dedicated_image_endpoint_refuses_a_partial_batch_too(): void
    {
        $product = $this->existingProduct();

        $this->withToken('erp-test-token')->postJson('/api/v1/products/EXISTING-1/images', [
            'image_urls' => [self::GOOD, self::PRIVATE_HOST],
        ])->assertStatus(422)->assertJsonPath('error', 'images_unavailable');

        $this->assertSame(0, $product->images()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_review_push_whose_image_fails_is_refused_whole(): void
    {
        $this->existingProduct();

        $response = $this->withToken('erp-test-token')->postJson('/api/v1/reviews', [
            'product_sku' => 'EXISTING-1',
            'author' => 'A Buyer',
            'rating' => 5,
            'body' => 'Great pedal.',
            'platform' => 'shopee',
            'platform_review_id' => 'sh-1',
            'images' => [self::GOOD, self::PRIVATE_HOST],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'images_unavailable')
            ->assertJsonPath('images_failed.0.url', self::PRIVATE_HOST);

        $this->assertStringContainsString('Review not created', $response->json('message'));
        $this->assertSame(0, Review::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_review_push_with_reachable_images_still_succeeds(): void
    {
        $this->existingProduct();

        $this->withToken('erp-test-token')->postJson('/api/v1/reviews', [
            'product_sku' => 'EXISTING-1',
            'author' => 'A Buyer',
            'rating' => 5,
            'platform' => 'shopee',
            'platform_review_id' => 'sh-2',
            'images' => [self::GOOD],
        ])->assertStatus(201)->assertJsonPath('images_saved', 1);

        $this->assertSame(1, Review::count());
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }
}
