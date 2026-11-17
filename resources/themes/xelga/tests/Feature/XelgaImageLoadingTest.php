<?php

namespace Themes\Xelga\Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Support\ImageRenditions;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use Themes\Xelga\Documents\Document;
use Themes\Xelga\Rendering\Storefront;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';

/**
 * How photos load: the first few of the page's content load at once (the
 * first two at high priority, the hero's first slide preloaded), and every
 * photo after them waits until the shopper scrolls near it.
 */
class XelgaImageLoadingTest extends TestCase
{
    use RefreshDatabase;
    use XelgaTestHelpers;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        ImageRenditions::flush();
        $this->installXelga();
        $this->store = Store::firstOrCreate(['code' => 'demo'], ['name' => 'Demo Store']);
        (new \ReflectionProperty(Application::class, 'isRunningInConsole'))->setValue($this->app, false);
    }

    private function photo(string $path): void
    {
        $im = imagecreatetruecolor(1600, 900);
        ob_start();
        imagejpeg($im);
        Storage::disk('public')->put($path, (string) ob_get_clean());
        imagedestroy($im);
        ImageRenditions::make($path);
    }

    private function html(string $url): string
    {
        Storefront::forgetRequestState();

        return $this->get($url)->assertOk()->getContent();
    }

    /** @return list<string> the <img> tags of the shared product cards' main photos, in page order */
    private function cardPhotos(string $html): array
    {
        preg_match_all('/<img class="card__img"[^>]*>/', $html, $m);

        return $m[0];
    }

    public function test_the_first_cards_of_a_listing_load_at_once_and_the_rest_wait(): void
    {
        $category = Category::create(['store_id' => $this->store->id, 'name' => 'Shirts', 'slug' => 'shirts', 'is_active' => true]);
        foreach (range(1, 6) as $i) {
            $product = Product::create([
                'store_id' => $this->store->id, 'name' => "Linen Shirt {$i}", 'slug' => 'linen-shirt-' . $i . '-' . Str::lower(Str::random(4)),
                'sku' => "DEMO-00{$i}", 'price' => 1000, 'quantity' => 5, 'is_active' => true,
                'image' => "media/products/shirt-{$i}.jpg",
            ]);
            $product->categories()->attach($category->id);
        }

        $cards = $this->cardPhotos($this->html('/category/shirts'));

        $this->assertCount(6, $cards);
        foreach (array_slice($cards, 0, 2) as $tag) {
            $this->assertStringContainsString('fetchpriority="high"', $tag);
            $this->assertStringNotContainsString('loading="lazy"', $tag);
        }
        foreach (array_slice($cards, 2, 2) as $tag) {
            $this->assertStringContainsString('loading="eager"', $tag);
            $this->assertStringNotContainsString('fetchpriority', $tag);
        }
        foreach (array_slice($cards, 4) as $tag) {
            $this->assertStringContainsString('loading="lazy"', $tag);
            $this->assertStringContainsString('decoding="async"', $tag);
        }
    }

    public function test_the_first_hero_slide_is_preloaded_at_high_priority_and_later_slides_are_lazy(): void
    {
        $this->photo('media/hero/one.jpg');
        $this->photo('media/hero/two.jpg');
        $blocks = [];
        foreach (['one', 'two'] as $i => $name) {
            $blocks["hero-t-b{$i}"] = ['type' => 'slide', 'label' => '', 'disabled' => false,
                'settings' => ['heading' => "Slide {$name}", 'button_label' => '', 'image' => "media/hero/{$name}.jpg"]];
        }
        $content = ['schema' => 1, 'order' => ['hero-t'], 'sections' => ['hero-t' => [
            'type' => 'hero', 'label' => '', 'disabled' => false, 'settings' => ['variant' => 'slideshow'],
            'block_order' => array_keys($blocks), 'blocks' => $blocks,
        ]]];
        $doc = new Document(['handle' => 'home', 'kind' => 'template', 'page_type' => 'home']);
        $doc->forceFill(['published' => $content, 'published_version' => 1])->save();

        $html = $this->html('/');

        $head = substr($html, 0, strpos($html, '</head>'));
        $this->assertSame(1, substr_count($head, '<link rel="preload" as="image"'));
        $this->assertMatchesRegularExpression('#<link rel="preload" as="image" href="/storage/media/hero/one\.jpg"\s+imagesrcset="[^"]*one\.jpg\.640\.webp 640w[^"]*" imagesizes="100vw"\s+fetchpriority="high">#', $head);

        preg_match_all('/<div class="hero__media">\s*(<img [^>]*>)/', $html, $m);
        $this->assertCount(2, $m[1]);
        $this->assertStringContainsString('fetchpriority="high"', $m[1][0]);
        $this->assertStringContainsString('srcset="', $m[1][0]);
        $this->assertStringContainsString('loading="lazy"', $m[1][1]);
    }

    public function test_photos_below_a_product_s_gallery_do_not_compete_with_it(): void
    {
        $product = Product::create([
            'store_id' => $this->store->id, 'name' => 'Linen Shirt', 'slug' => 'linen-shirt', 'sku' => 'DEMO-001',
            'price' => 1000, 'quantity' => 5, 'is_active' => true, 'image' => 'media/products/front.jpg',
        ]);
        $product->images()->create(['path' => 'media/products/back.jpg', 'sort_order' => 1]);

        $html = $this->html('/product/linen-shirt');

        preg_match_all('/<figure class="gallery__slide"[^>]*>\s*(<img [^>]*>)/', $html, $m);
        $this->assertStringContainsString('fetchpriority="high"', $m[1][0]);
        $this->assertStringContainsString('loading="lazy"', $m[1][1]);
        $this->assertSame(1, substr_count($html, 'fetchpriority="high"'));
    }
}
