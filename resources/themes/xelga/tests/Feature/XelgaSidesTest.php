<?php

namespace Themes\Xelga\Tests\Feature;

use App\Models\Category;
use App\Support\ImageRenditions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\UsesSampleExtension;
use Tests\TestCase;
use Themes\Xelga\Catalog\Catalog;
use Themes\Xelga\Documents\CatalogLayoutRules;
use Themes\Xelga\Documents\Defaults;
use Themes\Xelga\Documents\Layout;
use Themes\Xelga\Extensions\Placements;
use Themes\Xelga\Rendering\Picture;
use Themes\Xelga\Rendering\SectionRenderer;
use Themes\Xelga\Rendering\Storefront;
use Themes\Xelga\Tests\XelgaExtensionHelpers;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';
require_once __DIR__ . '/../XelgaExtensionHelpers.php';

/**
 * Left and right sides on the storefront: each side is three twelfths of
 * the page and stands beside Content, whose rows (the page's own section
 * included) are drawn in the width left between the sides. Top, Bottom,
 * header and footer stay the whole page wide.
 */
class XelgaSidesTest extends TestCase
{
    use RefreshDatabase;
    use UsesSampleExtension;
    use XelgaExtensionHelpers;
    use XelgaTestHelpers;

    private const PHOTO = 'media/demo/photo.jpg';

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

    /** A Text section, narrow enough for a side. */
    private function text(string $heading, bool $disabled = false): array
    {
        return ['type' => 'rich-text', 'label' => '', 'disabled' => $disabled, 'settings' => ['variant' => 'start', 'heading' => $heading, 'text' => '<p>Free returns within 30 days.</p>'], 'block_order' => [], 'blocks' => []];
    }

    /** Promo banners with one photo banner in the given layout. */
    private function promos(string $variant): array
    {
        $block = ['type' => 'banner', 'label' => 'Sale', 'disabled' => false, 'settings' => ['heading' => 'End of season sale', 'image' => self::PHOTO, 'text' => '', 'button_label' => 'Shop now', 'link' => ['type' => 'search'], 'scheme' => 'scheme-2']];

        return ['type' => 'promo-banners', 'label' => '', 'disabled' => false, 'settings' => ['variant' => $variant, 'height' => 'small'], 'block_order' => ['promo-b1'], 'blocks' => ['promo-b1' => $block]];
    }

    /**
     * The document with these sections in its sides.
     *
     * @param  array<string, array>  $left  section id => section
     * @param  array<string, array>  $right
     */
    private function withSides(array $content, array $left = [], array $right = []): array
    {
        $content['sections'] = [...$content['sections'], ...$left, ...$right];
        $content['order'] = [...array_keys($left), ...array_values($content['order']), ...array_keys($right)];
        $content['sides'] = array_filter(['left' => array_keys($left), 'right' => array_keys($right)]);

        return $content;
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

    private function category(): void
    {
        Category::create(['store_id' => $this->store->id, 'name' => 'Laptops', 'slug' => 'laptops', 'is_active' => true]);
    }

    /** The HTML between a row's or section's markers. */
    private function cut(string $html, string $id): string
    {
        $open = "<!--xelga:{$id}-->";
        $start = strpos($html, $open);
        $this->assertNotFalse($start, "{$id} is drawn.");
        $end = strpos($html, "<!--/xelga:{$id}-->", $start);
        $this->assertNotFalse($end);

        return substr($html, $start + strlen($open), $end - $start - strlen($open));
    }

    /** The sides wrapper: the left side, Content and the right side. */
    private function wrapper(string $html): string
    {
        $start = strpos($html, '<div class="xp"');
        $this->assertNotFalse($start, 'The page draws its sides.');

        return $this->outerHtml($html, $start);
    }

    /** Content, between the sides. */
    private function content(string $html): string
    {
        $this->assertMatchesRegularExpression('#<div class="xp__content" data-content="\d+">#', $html);

        return $this->contentArea($html);
    }

    /** @return list<string> the sizes attribute of every photo in the HTML */
    private function sizesIn(string $html): array
    {
        preg_match_all('#\bsizes="([^"]*)"#', $html, $m);

        return array_values(array_unique(array_map('html_entity_decode', $m[1])));
    }

    public function test_sides_run_beside_content(): void
    {
        $this->category();
        $this->publishedDocument('category', $this->withSides(Defaults::content('category'), ['note-1' => $this->text('Need help choosing?')]));

        $html = $this->page('/category/laptops')->getContent();

        $this->assertStringContainsString('<div class="xp" data-sides="left">', $html);
        $wrapper = $this->wrapper($html);
        $left = strpos($wrapper, '<section class="xp__side xp__side--left" aria-label="Left side">');
        $content = strpos($wrapper, '<div class="xp__content" data-content="9">');
        $this->assertNotFalse($left);
        $this->assertNotFalse($content);
        $this->assertLessThan($content, $left);
        $this->assertStringNotContainsString('xp__side--right', $wrapper);

        $side = substr($wrapper, $left, $content - $left);
        $this->assertStringContainsString('<!--xelga:note-1-->', $side);
        $main = $this->content($html);
        $this->assertStringContainsString('data-section-type="category-main"', $main);
        $this->assertStringNotContainsString('note-1', $main);
        // Breadcrumbs are too wide for the content beside a side, so they run above it, in Top.
        $this->assertStringNotContainsString('data-section-type="breadcrumbs"', $main);
        $this->assertLessThan(strpos($html, '<div class="xp"'), strpos($html, 'data-section-type="breadcrumbs"'));
    }

    public function test_a_photo_section_draws_beside_a_side(): void
    {
        $this->fakePhoto();
        $this->category();
        $category = Defaults::content('category');
        $category['sections']['promos-1'] = $this->promos('stacked');
        array_splice($category['order'], 2, 0, ['promos-1']);
        $this->publishedDocument('category', $this->withSides($category, [], ['note-1' => $this->text('Our promise')]));

        $html = $this->page('/category/laptops')->getContent();

        $this->assertStringContainsString('<div class="xp" data-sides="right">', $html);
        $main = $this->content($html);
        $this->assertStringContainsString('data-content="9"', $main);
        $this->assertStringContainsString('<!--xelga:promos-1-->', $main);
        // Sized for nine twelfths of the page from 990 pixels.
        $sizes = $this->sizesIn($this->cut($html, 'promos-1'));
        $this->assertCount(1, $sizes);
        $this->assertStringStartsWith('(min-width: 1320px) 960px, (min-width: 990px) 75vw, ', $sizes[0]);
        // The right side follows the content, so phones read it after the page.
        $this->assertGreaterThan(strpos($html, '<div class="xp__content"'), strpos($html, '<section class="xp__side xp__side--right" aria-label="Right side">'));
    }

    public function test_both_sides_leave_six_twelfths(): void
    {
        $this->category();
        $category = $this->withSides(Defaults::content('category'), ['note-1' => $this->text('Left')], ['note-2' => $this->text('Right')]);
        $category['sections'] += ['tile-a' => $this->tile('Tile A'), 'tile-b' => $this->tile('Tile B')];
        array_splice($category['order'], 2, 0, ['tile-a', 'tile-b']);
        $category['rows'] = [['id' => 'row-tiles', 'split' => '6-6', 'columns' => [['tile-a'], ['tile-b']], 'settings' => []]];
        $this->publishedDocument('category', $category);

        $html = $this->page('/category/laptops')->getContent();

        $this->assertStringContainsString('<div class="xp" data-sides="both">', $html);
        $main = $this->content($html);
        $this->assertStringContainsString('data-content="6"', $main);
        $row = $this->cut($main, 'row-tiles');
        $this->assertStringContainsString('<div class="xr__col" data-span="6">', $row);
        $this->assertStringContainsString('data-span="3"', $this->cut($row, 'tile-a'));
        $this->assertStringContainsString('data-span="3"', $this->cut($row, 'tile-b'));
        $this->assertSame(6, app(SectionRenderer::class)->contentSpan());
    }

    public function test_a_full_width_row_fills_the_content_area_only(): void
    {
        $this->category();
        $category = $this->withSides(Defaults::content('category'), ['note-1' => $this->text('Left')]);
        $category['sections'] += ['tile-a' => $this->tile('Tile A'), 'tile-b' => $this->tile('Tile B')];
        array_splice($category['order'], 2, 0, ['tile-a', 'tile-b']);
        $category['rows'] = [['id' => 'row-tiles', 'split' => '6-6', 'columns' => [['tile-a'], ['tile-b']], 'settings' => ['width' => 'full']]];
        $this->publishedDocument('category', $category);

        $html = $this->page('/category/laptops')->getContent();

        $this->assertStringContainsString('data-row-id="row-tiles"', $this->content($html));
        $this->assertMatchesRegularExpression('#<div class="xr" data-row-id="row-tiles" data-split="6-6" data-width="full"#', $this->content($html));
    }

    public function test_header_and_footer_stay_outside_the_sides(): void
    {
        $this->category();
        $this->publishedDocument('category', $this->withSides(Defaults::content('category'), ['note-1' => $this->text('Left')], ['note-2' => $this->text('Right')]));

        $html = $this->page('/category/laptops')->getContent();
        $wrapper = $this->wrapper($html);

        $this->assertStringNotContainsString('data-section-type="header"', $wrapper);
        $this->assertStringNotContainsString('data-section-type="footer"', $wrapper);
        $start = strpos($html, '<div class="xp"');
        $this->assertLessThan($start, strpos($html, 'data-section-type="header"'));
        $this->assertGreaterThan($start + strlen($wrapper), strpos($html, 'data-section-type="footer"'));
    }

    public function test_a_side_with_nothing_to_show_is_not_drawn_and_takes_no_width(): void
    {
        $this->fakePhoto();
        $this->category();
        $category = Defaults::content('category');
        $category['sections']['promos-1'] = $this->promos('stacked');
        array_splice($category['order'], 2, 0, ['promos-1']);
        $this->publishedDocument('category', $this->withSides($category, ['note-1' => $this->text('Hidden', true), 'note-2' => $this->text('Also hidden', true)]));

        $html = $this->page('/category/laptops')->getContent();

        $this->assertStringNotContainsString('class="xp"', $html);
        $this->assertStringNotContainsString('class="xp__side', $html);
        $this->assertStringContainsString('<main id="main-content">', $html);
        $this->assertSame(['(min-width: 750px) 50vw, 100vw'], $this->sizesIn($this->cut($html, 'promos-1')));
        $this->assertSame(12, app(SectionRenderer::class)->contentSpan());
    }

    public function test_a_side_whose_sections_show_nothing_is_not_drawn(): void
    {
        $this->category();
        // Brand logos on a store with no brands prints nothing for a shopper.
        $brands = ['type' => 'brand-logos', 'label' => '', 'disabled' => false, 'settings' => ['variant' => 'grid', 'source' => 'all'], 'block_order' => [], 'blocks' => []];
        $category = $this->withSides(Defaults::content('category'), ['brands-side' => $brands]);
        $this->publishedDocument('category', $category, $category);

        $html = $this->page('/category/laptops')->getContent();

        $this->assertStringNotContainsString('class="xp"', $html);
        $this->assertStringContainsString('<main id="main-content">', $html);
        $this->assertSame(12, app(SectionRenderer::class)->contentSpan());

        // The editor's preview keeps the side, so the section can be reached there.
        $nonce = $this->openEditor($this->admin());
        $preview = $this->get('/category/laptops?xelga_preview=' . $nonce)->assertOk()->getContent();
        $this->assertStringContainsString('<div class="xp" data-sides="left">', $preview);
        $this->assertStringContainsString('data-section-id="brands-side"', $preview);
    }

    public function test_side_sections_are_sized_for_three_twelfths(): void
    {
        $this->fakePhoto();
        $this->category();
        $this->publishedDocument('category', $this->withSides(Defaults::content('category'), ['promos-side' => $this->promos('stacked')]));

        $html = $this->page('/category/laptops')->getContent();

        $side = $this->cut($html, 'promos-side');
        // Three twelfths from 990 pixels; below that the sides stack the
        // whole page wide, so the section's own full-width sizes apply.
        $sizes = Picture::contentSizes(3, '(min-width: 750px) 50vw, 100vw');
        $this->assertSame([$sizes], $this->sizesIn($side));
        $this->assertStringStartsWith('(min-width: 1320px) 320px, (min-width: 990px) 25vw, ', $sizes);
        $this->assertStringEndsWith(', (min-width: 750px) 50vw, 100vw', $sizes);
        // The content beside it is nine twelfths.
        $this->assertStringContainsString('data-content="9"', $html);
    }

    public function test_a_placed_section_never_lands_in_a_side(): void
    {
        $this->product('Linen Shirt');
        $card = Placements::insertId('sample-extension:card');
        $product = Defaults::content('product');
        // The placement goes after the product's main section, in the order
        // the page reads; it gets its own row in Bottom, below the sides.
        $this->publishedDocument('product', $this->withSides($product, ['note-1' => $this->text('Left')], ['note-2' => $this->text('Right')]));

        $html = $this->page('/product/linen-shirt')->getContent();

        $wrapper = strpos($html, '<div class="xp"');
        $this->assertGreaterThanOrEqual($wrapper + strlen($this->wrapper($html)), strpos($html, "<!--xelga:{$card}-->"));
        $this->assertStringContainsString('data-sample-card', $this->cut($html, $card));
        foreach (['left', 'right'] as $which) {
            $this->assertStringNotContainsString($card, $this->sideArea($html, $which));
        }
    }

    public function test_promo_banners_and_brand_logos_fit_a_side(): void
    {
        $rules = new CatalogLayoutRules('category');
        $promos = Catalog::section('promo-banners');
        $brands = Catalog::section('brand-logos');
        $this->assertSame(['min' => 3, 'variants' => ['overlay' => 4, 'mosaic' => 8]], $promos->spans());
        $this->assertSame(['min' => 3, 'variants' => ['row' => 4]], $brands->spans());
        $this->assertSame(['min' => 3, 'variants' => ['split' => 6]], Catalog::section('rich-text')->spans());

        $this->assertSame(3, $rules->minSpan(['type' => 'promo-banners', 'settings' => ['variant' => 'stacked']]));
        $this->assertSame(3, $rules->minSpan(['type' => 'brand-logos', 'settings' => ['variant' => 'grid']]));
        $this->assertSame(4, $rules->minSpan(['type' => 'promo-banners', 'settings' => ['variant' => 'overlay']]));
        $this->assertSame(4, $rules->minSpan(['type' => 'brand-logos', 'settings' => ['variant' => 'row']]));

        // In a side they stay; the layouts that need more room leave it.
        $category = $this->withSides(Defaults::content('category'), [
            'promos-side' => $this->promos('stacked'),
            'brands-side' => ['type' => 'brand-logos', 'label' => '', 'disabled' => false, 'settings' => ['variant' => 'grid', 'source' => 'all'], 'block_order' => [], 'blocks' => []],
            'promos-wide' => $this->promos('overlay'),
        ]);
        $result = Layout::normalize($category, $rules);
        $this->assertSame(['promos-side', 'brands-side'], $result['content']['sides']['left']);
        $wide = array_values(array_filter($result['moved'], fn ($m) => $m['path'] === 'content.sections.promos-wide'));
        $this->assertSame([['path' => 'content.sections.promos-wide', 'reason' => 'too-wide']], array_map(fn ($m) => array_diff_key($m, ['value' => 1]), $wide));
    }

    public function test_a_column_too_narrow_beside_the_sides_moves_out(): void
    {
        $products = ['type' => 'featured-products', 'label' => '', 'disabled' => false, 'settings' => [], 'block_order' => [], 'blocks' => []];
        $category = $this->withSides(Defaults::content('category'), ['note-1' => $this->text('Left')], ['note-2' => $this->text('Right')]);
        $category['sections'] += ['list-1' => $products, 'tile-b' => $this->tile('Tile B')];
        array_splice($category['order'], 2, 0, ['list-1', 'tile-b']);
        $category['rows'] = [['id' => 'row-list', 'split' => '8-4', 'columns' => [['list-1'], ['tile-b']], 'settings' => []]];

        // Beside two sides an 8 column is four twelfths of the page: too narrow
        // for a list of products, which needs six. It gets its own row, the
        // whole content area wide, and the save says so.
        $adjusted = $this->saveWithRows('category', $category, 0)->assertOk()->json('data.adjusted');
        $moved = array_values(array_filter($adjusted, fn ($a) => $a['path'] === 'content.sections.list-1'));
        $this->assertCount(1, $moved);
        $this->assertSame('too-wide', $moved[0]['reason']);

        // With one side the 8 column is six twelfths, wide enough.
        unset($category['sides']['right'], $category['sections']['note-2']);
        $category['order'] = array_values(array_diff($category['order'], ['note-2']));
        $adjusted = $this->saveWithRows('category', $category, 1)->assertOk()->json('data.adjusted');
        $this->assertSame([], array_values(array_filter($adjusted, fn ($a) => $a['path'] === 'content.sections.list-1')));
    }
}
