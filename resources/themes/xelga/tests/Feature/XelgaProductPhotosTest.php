<?php

namespace Themes\Xelga\Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Themes\Xelga\Catalog\Catalog;
use Themes\Xelga\Documents\Document;
use Themes\Xelga\Rendering\Storefront;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';

/**
 * The product photo settings: how much of the row the photos take, the
 * shape of their frame, and how a shopper zooms in. Each one reaches the page
 * as markup the stylesheet and script read, so these tests read the markup.
 */
class XelgaProductPhotosTest extends TestCase
{
    use RefreshDatabase;
    use XelgaTestHelpers;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installXelga();
        $this->store = Store::firstOrCreate(['code' => 'demo'], ['name' => 'Demo Store']);
    }

    private function product(int $photos = 3): Product
    {
        $product = Product::create([
            'store_id' => $this->store->id, 'name' => 'Linen Shirt', 'slug' => 'linen-shirt', 'sku' => 'DEMO-001',
            'price' => 1000, 'quantity' => 20, 'track_stock' => true, 'is_active' => true,
            'image' => 'media/products/demo/front.jpg',
        ]);
        foreach (array_slice(['back', 'side'], 0, max(0, $photos - 1)) as $i => $name) {
            $product->images()->create(['path' => "media/products/demo/{$name}.jpg", 'sort_order' => $i + 1]);
        }

        return $product;
    }

    /** Publishes the shipped product page with these Product information settings. */
    private function publishProductSettings(array $settings): void
    {
        $content = $this->defaults('product');
        foreach ($content['sections'] as $id => $section) {
            if ($section['type'] === 'product-main') {
                $content['sections'][$id]['settings'] = $settings + (array) $section['settings'];
            }
        }
        $admin = $this->admin();
        $version = (int) (Document::where('handle', 'product')->value('draft_version') ?? 0);
        $this->saveDraft($admin, 'product', $content, $version)->assertOk();
        $this->publish($admin, ['product' => $version + 1])->assertOk();
        $this->asGuest();
    }

    /**
     * The product page as a shopper sees it. Core's visibility scope steps
     * aside for console runs, so the request runs as a web request; only for
     * the page, since outside the console the editor's saves need a CSRF token.
     */
    private function page(): string
    {
        Storefront::forgetRequestState();
        $console = new \ReflectionProperty(Application::class, 'isRunningInConsole');
        $console->setValue($this->app, false);
        try {
            return $this->get('/product/linen-shirt')->assertOk()->getContent();
        } finally {
            $console->setValue($this->app, true);
        }
    }

    /** The opening tag of the Product information section. */
    private function sectionTag(string $html): string
    {
        $this->assertSame(1, preg_match('/<section class="s s-product[^"]*"[^>]*>/', $html, $m), 'The Product information section is missing.');

        return $m[0];
    }

    public function test_the_schema_puts_size_and_shape_after_the_layout_and_defaults_new_stores_to_hover_zoom(): void
    {
        $schema = Catalog::section('product-main');
        $defaults = $schema->defaults();

        $this->assertSame('medium', $defaults['photo_size']);
        $this->assertSame('fit', $defaults['photo_shape']);
        $this->assertSame('hover', $defaults['zoom']);

        $keys = array_keys($schema->settings());
        $at = array_search('gallery', $keys, true);
        $this->assertSame(['photo_size', 'photo_shape'], array_slice($keys, $at + 1, 2));

        $this->assertSame(['small', 'medium', 'large'], $schema->settings()['photo_size']->optionValues());
        $this->assertSame(['fit', 'square', 'portrait', 'landscape'], $schema->settings()['photo_shape']->optionValues());
        // Full screen keeps its stored value, so a store that chose it keeps it.
        $this->assertSame(['off', 'lightbox', 'hover'], $schema->settings()['zoom']->optionValues());
    }

    public function test_a_page_saved_before_the_settings_existed_draws_the_defaults(): void
    {
        $this->product();

        $tag = $this->sectionTag($this->page());

        $this->assertStringContainsString('data-photo-size="medium"', $tag);
        $this->assertStringContainsString('data-photo-shape="fit"', $tag);
        $this->assertStringContainsString('data-zoom="hover"', $tag);
    }

    public function test_each_size_and_shape_reaches_the_section_as_an_attribute(): void
    {
        $this->product();

        foreach ([['small', 'square'], ['large', 'portrait'], ['medium', 'landscape'], ['small', 'fit']] as [$size, $shape]) {
            $this->publishProductSettings(['photo_size' => $size, 'photo_shape' => $shape]);
            $tag = $this->sectionTag($this->page());

            $this->assertStringContainsString("data-photo-size=\"{$size}\"", $tag);
            $this->assertStringContainsString("data-photo-shape=\"{$shape}\"", $tag);
        }
    }

    public function test_a_value_the_schema_does_not_offer_is_saved_as_the_default(): void
    {
        $this->product();

        $this->publishProductSettings(['photo_size' => 'huge', 'photo_shape' => 'circle', 'zoom' => 'lens']);
        $tag = $this->sectionTag($this->page());

        $this->assertStringContainsString('data-photo-size="medium"', $tag);
        $this->assertStringContainsString('data-photo-shape="fit"', $tag);
        $this->assertStringContainsString('data-zoom="hover"', $tag);
    }

    public function test_a_store_that_chose_full_screen_keeps_full_screen_without_hover_zoom(): void
    {
        $this->product();

        $this->publishProductSettings(['zoom' => 'lightbox']);
        $html = $this->page();

        $this->assertStringContainsString('data-zoom="lightbox"', $this->sectionTag($html));
        $this->assertStringContainsString('data-gallery-zoom', $html);
        $this->assertStringContainsString('<dialog class="lightbox" data-lightbox', $html);
        $this->assertSame(3, substr_count($html, 'data-lightbox-slide='));
        $this->assertStringNotContainsString('data-zoom-src', $html);
    }

    public function test_hover_zoom_reads_each_photo_s_original_file_and_keeps_full_screen(): void
    {
        $this->product();

        $this->publishProductSettings(['zoom' => 'hover']);
        $html = $this->page();

        $this->assertStringContainsString('data-gallery-zoom', $html);
        $this->assertStringContainsString('<dialog class="lightbox" data-lightbox', $html);
        preg_match_all('/<figure class="gallery__slide"[^>]*>\s*<img src="([^"]+)"[^>]*data-zoom-src="([^"]+)"/', $html, $m);
        $this->assertCount(3, $m[0]);
        $this->assertSame($m[1], $m[2]);
        $this->assertSame(storage_url('media/products/demo/front.jpg'), html_entity_decode($m[2][0]));
    }

    public function test_hover_zoom_on_a_single_photo_still_opens_full_screen(): void
    {
        $this->product(1);

        $html = $this->page();

        $this->assertSame(1, substr_count($html, 'data-zoom-src='));
        $this->assertStringContainsString('data-gallery-zoom', $html);
        $this->assertSame(1, substr_count($html, 'data-lightbox-slide='));
    }

    public function test_zoom_off_draws_no_button_no_viewer_and_no_zoom_source(): void
    {
        $this->product();

        $this->publishProductSettings(['zoom' => 'off']);
        $html = $this->page();

        $this->assertStringContainsString('data-zoom="off"', $this->sectionTag($html));
        $this->assertStringNotContainsString('data-gallery-zoom', $html);
        $this->assertStringNotContainsString('data-lightbox', $html);
        $this->assertStringNotContainsString('data-zoom-src', $html);
    }

    public function test_photos_offer_their_smaller_copies_while_zoom_and_full_screen_keep_the_original(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        foreach (['front', 'back', 'side'] as $name) {
            $im = imagecreatetruecolor(1400, 1400);
            ob_start();
            imagejpeg($im);
            \Illuminate\Support\Facades\Storage::disk('public')->put("media/products/demo/{$name}.jpg", (string) ob_get_clean());
            \App\Support\ImageRenditions::make("media/products/demo/{$name}.jpg");
        }
        $this->product();

        $this->publishProductSettings(['zoom' => 'hover']);
        $html = $this->page();

        preg_match('/<figure class="gallery__slide"[^>]*>\s*(<img [^>]*>)/', $html, $main);
        $this->assertStringContainsString('srcset="/storage/renditions/media/products/demo/front.jpg.320.webp 320w', $main[1]);
        $this->assertMatchesRegularExpression('#/storage/(renditions/)?media/products/demo/front\.jpg(\.1400\.webp)? 1400w#', $main[1]);
        $this->assertStringContainsString('sizes="', $main[1]);
        $this->assertStringContainsString('data-zoom-src="' . storage_url('media/products/demo/front.jpg') . '"', $main[1]);

        preg_match('/<button type="button" class="gallery__thumb"[^>]*>(<img [^>]*>)/', $html, $thumb);
        $this->assertStringContainsString('sizes="96px"', $thumb[1]);

        preg_match_all('/<img class="lightbox__img"[^>]*>/', $html, $full);
        $this->assertCount(3, $full[0]);
        foreach ($full[0] as $tag) {
            $this->assertStringNotContainsString('srcset', $tag);
        }
    }
}
