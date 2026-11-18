<?php

namespace Themes\Xelga\Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Support\ImageRenditions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\UsesSampleExtension;
use Tests\TestCase;
use Themes\Xelga\Documents\Defaults;
use Themes\Xelga\Rendering\Storefront;
use Themes\Xelga\Tests\XelgaExtensionHelpers;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';
require_once __DIR__ . '/../XelgaExtensionHelpers.php';

/**
 * Top, Content and Bottom on the storefront. Top and Bottom run the whole
 * page wide; the sides stand beside Content only, in a wrapper inside
 * main. A page without sides draws its rows one after another, as before.
 */
class XelgaAreasRenderTest extends TestCase
{
    use RefreshDatabase;
    use UsesSampleExtension;
    use XelgaExtensionHelpers;
    use XelgaTestHelpers;

    private const PHOTO = 'media/demo/photo.jpg';

    private const SETTINGS = ['width' => 'page', 'scheme' => '', 'space' => 'md'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpXelgaWithExtension();
    }

    protected function tearDown(): void
    {
        Storefront::forgetRequestState();
        parent::tearDown();
    }

    private function text(string $heading): array
    {
        return ['type' => 'rich-text', 'label' => '', 'disabled' => false, 'settings' => ['variant' => 'start', 'heading' => $heading, 'text' => '<p>Free returns within 30 days.</p>'], 'block_order' => [], 'blocks' => []];
    }

    private function banner(): array
    {
        $home = Defaults::content('home');
        $hero = $home['sections']['hero-1'];
        $hero['settings']['variant'] = 'full-image';
        foreach (array_keys($hero['blocks']) as $blockId) {
            $hero['blocks'][$blockId]['settings']['image'] = self::PHOTO;
        }

        return $hero;
    }

    private function best(): array
    {
        return ['type' => 'featured-products', 'label' => '', 'disabled' => false, 'settings' => ['variant' => 'grid', 'heading' => 'Best sellers', 'source' => 'newest', 'count' => 4, 'cols' => 4], 'block_order' => [], 'blocks' => []];
    }

    private function fakePhoto(): void
    {
        Storage::fake('public');
        ImageRenditions::flush();
        $image = imagecreatetruecolor(1600, 900);
        ob_start();
        imagejpeg($image);
        Storage::disk('public')->put(self::PHOTO, (string) ob_get_clean());
        imagedestroy($image);
        ImageRenditions::make(self::PHOTO);
    }

    private function catalog(): void
    {
        $category = Category::create(['store_id' => $this->store->id, 'name' => 'Laptops', 'slug' => 'laptops', 'is_active' => true]);
        foreach (['DEMO-001' => 'Demo Laptop 13', 'DEMO-002' => 'Demo Laptop 14'] as $sku => $name) {
            $product = Product::create([
                'store_id' => $this->store->id, 'name' => $name, 'slug' => strtolower($sku), 'sku' => $sku,
                'price' => 45000, 'quantity' => 10, 'track_stock' => true, 'is_active' => true, 'image' => self::PHOTO,
            ]);
            $product->categories()->attach($category->id);
        }
    }

    private function row(string $id, string $area, array $columns, string $split = '12'): array
    {
        return ['id' => 'row-' . $id, 'split' => $split, 'columns' => $columns, 'settings' => self::SETTINGS, 'area' => $area];
    }

    /**
     * The category page: breadcrumbs and a banner in Top, the listing in
     * Content, best sellers in Bottom, with these sections in its sides.
     *
     * @param  array<string, array>  $left
     * @param  array<string, array>  $right
     */
    private function category(array $left = [], array $right = [], array $contentRows = []): array
    {
        $content = Defaults::content('category');
        $content['sections'] = [...$content['sections'], 'banner' => $this->banner(), 'best' => $this->best(), ...$left, ...$right];
        unset($content['sections']['recent-1']);
        $rows = [
            $this->row('crumbs', 'top', [['breadcrumbs-1']]),
            $this->row('banner', 'top', [['banner']]),
            $this->row('main', 'content', [['category-main-1']]),
            ...$contentRows,
            $this->row('best', 'bottom', [['best']]),
        ];
        $content['rows'] = $rows;
        $content['sides'] = array_filter(['left' => array_keys($left), 'right' => array_keys($right)]);
        $content['order'] = ['breadcrumbs-1', 'banner', ...array_keys($left), 'category-main-1', ...array_merge(...array_map(fn ($r) => array_merge(...$r['columns']), $contentRows)), ...array_keys($right), 'best'];

        return $content;
    }

    /** The HTML between a section's or row's markers. */
    private function cut(string $html, string $id): string
    {
        $open = "<!--xelga:{$id}-->";
        $start = strpos($html, $open);
        $this->assertNotFalse($start, "{$id} is drawn.");
        $end = strpos($html, "<!--/xelga:{$id}-->", $start);

        return substr($html, $start + strlen($open), $end - $start - strlen($open));
    }

    /** @return list<string> */
    private function sizesIn(string $html): array
    {
        preg_match_all('#\bsizes="([^"]*)"#', $html, $m);

        return array_values(array_unique(array_map('html_entity_decode', $m[1])));
    }

    private function main(string $html): string
    {
        $start = strpos($html, '<main id="main-content">');
        $this->assertNotFalse($start);

        return (string) preg_replace('/name="_token" value="[^"]*"/', 'name="_token" value=""', $this->outerHtml($html, $start));
    }

    public function test_top_and_bottom_rows_run_full_width_beside_a_side(): void
    {
        $this->fakePhoto();
        $this->catalog();
        $this->publishedDocument('category', $this->category());
        $plain = $this->page('/category/laptops')->getContent();

        $this->publishedDocument('category', $this->category(['note-1' => $this->text('Need help choosing?')]));
        $html = $this->page('/category/laptops')->getContent();

        $wrapper = strpos($html, '<div class="xp" data-sides="left">');
        $this->assertNotFalse($wrapper);
        $end = $wrapper + strlen($this->outerHtml($html, $wrapper));
        $this->assertLessThan($wrapper, strpos($html, '<!--xelga:breadcrumbs-1-->'));
        $this->assertLessThan($wrapper, strpos($html, '<!--xelga:banner-->'));
        $this->assertGreaterThanOrEqual($end, strpos($html, '<!--xelga:best-->'));

        $content = $this->contentArea($html);
        $this->assertStringContainsString('data-content="9"', $content);
        $this->assertStringContainsString('data-section-type="category-main"', $content);
        foreach (['breadcrumbs', 'hero', 'featured-products', 'rich-text'] as $type) {
            $this->assertStringNotContainsString("data-section-type=\"{$type}\"", $content, "{$type} is not in Content.");
        }

        // Sized for the whole page, as on the same page without sides.
        foreach (['banner', 'best'] as $id) {
            $sizes = $this->sizesIn($this->cut($html, $id));
            $this->assertNotSame([], $sizes, "{$id} draws a photo.");
            $this->assertSame($this->sizesIn($this->cut($plain, $id)), $sizes, "{$id} is sized for the whole page.");
        }
        $this->assertSame(['100vw'], $this->sizesIn($this->cut($html, 'banner')));
    }

    public function test_content_rows_are_sized_for_the_content_width(): void
    {
        $this->catalog();
        $tiles = [$this->row('tiles', 'content', [['tile-a'], ['tile-b']], '6-6')];
        $content = $this->category(['note-1' => $this->text('Left')], ['note-2' => $this->text('Right')], $tiles);
        $content['sections'] += ['tile-a' => $this->tile('Tile A'), 'tile-b' => $this->tile('Tile B')];
        $this->publishedDocument('category', $content);

        $html = $this->page('/category/laptops')->getContent();

        $area = $this->contentArea($html);
        $this->assertStringContainsString('<div class="xp__content" data-content="6">', $area);
        $row = $this->cut($area, 'row-tiles');
        $this->assertStringContainsString('<div class="xr__col" data-span="6">', $row);
        $this->assertStringContainsString('data-span="3"', $this->cut($row, 'tile-a'));
        $this->assertStringContainsString('data-span="3"', $this->cut($row, 'tile-b'));
    }

    public function test_main_holds_every_area(): void
    {
        $this->fakePhoto();
        $this->catalog();
        $this->publishedDocument('category', $this->category(['note-1' => $this->text('Left')], ['note-2' => $this->text('Right')]));

        $html = $this->page('/category/laptops')->getContent();
        $main = $this->main($html);

        foreach (['<!--xelga:breadcrumbs-1-->', '<!--xelga:banner-->', '<div class="xp" data-sides="both">', '<!--xelga:category-main-1-->', '<!--xelga:best-->'] as $mark) {
            $this->assertStringContainsString($mark, $main);
        }
        $this->assertStringNotContainsString('data-section-type="header"', $main);
        $this->assertStringNotContainsString('data-section-type="footer"', $main);
        $start = strpos($html, '<main id="main-content">');
        $this->assertLessThan($start, strpos($html, 'data-section-type="header"'));
        $this->assertGreaterThan($start + strlen($main), strpos($html, 'data-section-type="footer"'));
        // The skip link reaches Top, the first thing under the header.
        $this->assertStringContainsString('href="#main-content"', $html);
        $this->assertSame(1, substr_count($html, '<main'));
    }

    public function test_sides_are_named_regions(): void
    {
        $this->catalog();
        $this->publishedDocument('category', $this->category(['note-1' => $this->text('Left')], ['note-2' => $this->text('Right')]));

        $html = $this->page('/category/laptops')->getContent();
        $main = $this->main($html);

        $left = strpos($main, '<section class="xp__side xp__side--left" aria-label="Left side">');
        $right = strpos($main, '<section class="xp__side xp__side--right" aria-label="Right side">');
        $this->assertNotFalse($left);
        $this->assertNotFalse($right);
        $this->assertStringContainsString('<!--xelga:note-1-->', $this->outerHtml($main, $left));
        $this->assertStringContainsString('<!--xelga:note-2-->', $this->outerHtml($main, $right));
        // Left, Content, right: the order a phone stacks them in.
        $this->assertLessThan(strpos($main, '<div class="xp__content"'), $left);
        $this->assertGreaterThan(strpos($main, '<div class="xp__content"'), $right);
        // A side is never an aside: one inside main is not a top-level complementary landmark.
        $this->assertStringNotContainsString('<aside class="xp', $main);
    }

    public function test_a_page_with_areas_and_no_side_draws_like_plain_rows(): void
    {
        $this->product('Linen Shirt');
        $product = Defaults::content('product');
        $rows = [];
        foreach ($product['order'] as $id) {
            $type = $product['sections'][$id]['type'];
            $rows[] = ['id' => 'row-' . $id, 'split' => '12', 'columns' => [[$id]], 'settings' => self::SETTINGS, 'area' => match ($type) {
                'breadcrumbs' => 'top',
                'product-main', 'product-details' => 'content',
                default => 'bottom',
            }];
        }

        $this->publishedDocument('product', ['rows' => $rows] + $product);
        $withAreas = $this->main($this->page('/product/linen-shirt')->getContent());

        $this->publishedDocument('product', ['rows' => array_map(fn (array $row) => array_diff_key($row, ['area' => true]), $rows)] + $product);
        $plain = $this->main($this->page('/product/linen-shirt')->getContent());

        $this->assertSame($plain, $withAreas);
        $this->assertStringNotContainsString('class="xp', $withAreas);
        $this->assertStringNotContainsString('data-content', $withAreas);
        $this->assertStringNotContainsString('data-area', $withAreas);
    }
}
