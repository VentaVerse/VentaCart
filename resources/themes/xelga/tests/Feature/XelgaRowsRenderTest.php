<?php

namespace Themes\Xelga\Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\Store;
use App\Support\ImageRenditions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\UsesSampleExtension;
use Tests\TestCase;
use Themes\Xelga\Catalog\Catalog;
use Themes\Xelga\Documents\Defaults;
use Themes\Xelga\Documents\Layout;
use Themes\Xelga\Documents\LayoutRules;
use Themes\Xelga\Rendering\Picture;
use Themes\Xelga\Rendering\RenderContext;
use Themes\Xelga\Rendering\SectionRenderer;
use Themes\Xelga\Rendering\Storefront;
use Themes\Xelga\Tests\XelgaExtensionHelpers;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';
require_once __DIR__ . '/../XelgaExtensionHelpers.php';

/**
 * Rows of columns as the shopper gets them: a one-column row draws its
 * section as before, a row of columns draws a grid whose columns carry
 * their width in twelfths, and what a section draws inside a column
 * (its photos' sizes) follows the column's width.
 */
class XelgaRowsRenderTest extends TestCase
{
    use RefreshDatabase;
    use UsesSampleExtension;
    use XelgaExtensionHelpers;
    use XelgaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpXelgaWithExtension();
    }

    /** The home page with tiles in one row of the given split, right after the banner. */
    private function homeWithRow(string $split, array $columns, array $settings = []): array
    {
        $home = $this->defaults('home');
        $ids = array_merge(...$columns);
        $order = array_values($home['order']);
        array_splice($order, 1, 0, $ids);
        $home['order'] = $order;
        foreach ($ids as $id) {
            $home['sections'][$id] = $this->tile('Tile ' . $id);
        }
        $home['rows'] = [['id' => 'row-tiles', 'split' => $split, 'columns' => $columns, 'settings' => $settings]];

        return $home;
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

    /** @return list<string> the data-span of each column, in order */
    private function columnSpans(string $row): array
    {
        preg_match_all('#<div class="xr__col" data-span="(\d+)">#', $row, $m);

        return $m[1];
    }

    private function mainContent(string $url): string
    {
        $html = $this->page($url)->getContent();
        $start = strpos($html, '<main id="main-content">');
        $end = strrpos($html, '</main>');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        return (string) preg_replace('/name="_token" value="[^"]*"/', 'name="_token" value=""', substr($html, $start, $end - $start));
    }

    /**
     * A section of the given type and variant, with the sample content its
     * own preset ships, a photo wherever the section takes one, and a brand
     * to show. Only variants that fit a column of four are drawn in one: the
     * wide ones (a store's default is not always among the narrow ones) have
     * their own test.
     */
    private function narrowSection(string $type, string $variant): array
    {
        $schema = Catalog::section($type);
        $preset = collect($schema->raw['presets'])->first(fn ($p) => ($p['settings']['variant'] ?? null) === $variant) ?? $schema->raw['presets'][0];
        $photo = 'media/demo/photo.jpg';
        $settings = ['variant' => $variant] + $preset['settings'];
        if ($type === 'product-spotlight') {
            $store = Store::firstOrCreate(['code' => 'demo'], ['name' => 'Demo Store']);
            $settings['product'] = Product::create([
                'store_id' => $store->id, 'name' => 'Linen Shirt', 'slug' => 'linen-shirt-' . Str::lower(Str::random(4)),
                'sku' => 'DEMO-001', 'price' => 1250, 'quantity' => 5, 'is_active' => true,
            ])->id;
        }
        if (in_array($type, ['image-with-text', 'product-spotlight'], true)) {
            $settings['image'] = $photo;
        }
        if ($type === 'brand-logos') {
            $store = Store::firstOrCreate(['code' => 'demo'], ['name' => 'Demo Store']);
            Brand::create(['store_id' => $store->id, 'name' => 'Northwind', 'slug' => 'northwind', 'is_active' => true]);
            $settings['source'] = 'all';
        }
        $blocks = [];
        foreach ($preset['blocks'] ?? [] as $i => $block) {
            $blockSettings = $block['settings'];
            if ($type === 'promo-banners') {
                $blockSettings['image'] = $photo;
            }
            $blocks["{$type}-b{$i}"] = ['type' => $block['type'], 'label' => $block['label'] ?? '', 'disabled' => false, 'settings' => $blockSettings];
        }

        return ['type' => $type, 'label' => '', 'disabled' => false, 'settings' => $settings, 'block_order' => array_keys($blocks), 'blocks' => $blocks];
    }

    private function fakePhoto(): void
    {
        Storage::fake('public');
        ImageRenditions::flush();
        $image = imagecreatetruecolor(1600, 900);
        ob_start();
        imagejpeg($image);
        Storage::disk('public')->put('media/demo/photo.jpg', (string) ob_get_clean());
        imagedestroy($image);
        ImageRenditions::make('media/demo/photo.jpg');
    }

    /** The home page with one section of the narrow group in a 4-8 row beside a tile. */
    private function homeWithNarrow(string $type, string $variant): array
    {
        $home = $this->homeWithRow('4-8', [['narrow-1'], ['tile-b']]);
        $home['sections']['narrow-1'] = $this->narrowSection($type, $variant);

        return $home;
    }

    /** @return array<string, array{0: string, 1: string, 2: string, 3: bool}> type, variant, section class, whether it prints photos */
    public static function narrowGroup(): array
    {
        return [
            'text' => ['rich-text', 'center', 's-richtext', false],
            'image with text' => ['image-with-text', 'split', 's-iwt', true],
            'promo banners' => ['promo-banners', 'overlay', 's-promos', true],
            'product spotlight' => ['product-spotlight', 'split', 's-spotlight', true],
            'brand logos' => ['brand-logos', 'row', 's-brands', false],
            'testimonials' => ['testimonials', 'carousel', 's-quotes', false],
            'store promises' => ['usp-bar', 'columns', 's-usp', false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('narrowGroup')]
    public function test_the_narrow_group_sits_in_columns(string $type, string $variant, string $class, bool $photos): void
    {
        $this->fakePhoto();
        $this->publishedDocument('home', $this->homeWithNarrow($type, $variant));

        $row = $this->cut($this->page('/')->getContent(), 'row-tiles');

        $this->assertSame(['4', '8'], $this->columnSpans($row));
        $this->assertMatchesRegularExpression('#<div class="xr__col" data-span="4"><!--xelga:narrow-1-->.*?<!--/xelga:narrow-1--></div>#s', $row);
        $section = $this->cut($row, 'narrow-1');
        $this->assertMatchesRegularExpression('#<section class="s ' . $class . ' #', $section);
        if ($photos) {
            $this->assertStringContainsString('sizes="' . e(Picture::spanSizes(4)) . '"', $section);
            $this->assertStringContainsString('427px', $section);
        }
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> type, variant, the split whose wide column holds it */
    public static function wideVariants(): array
    {
        return [
            'text beside text' => ['rich-text', 'split', '6-6'],
            'overlapping image with text' => ['image-with-text', 'overlap', '8-4'],
            'mosaic banners' => ['promo-banners', 'mosaic', '8-4'],
            'banner spotlight' => ['product-spotlight', 'banner', '8-4'],
            'review grid' => ['testimonials', 'grid', '6-6'],
            'promise strip' => ['usp-bar', 'strip', '6-6'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('wideVariants')]
    public function test_a_variant_too_wide_for_its_column_moves_out(string $type, string $variant, string $split): void
    {
        $this->fakePhoto();
        $home = $this->homeWithNarrow($type, $variant);
        $adjusted = $this->saveWithRows('home', $home, 0)->assertOk()->json('data.adjusted');

        $moved = array_values(array_filter($adjusted, fn ($a) => $a['reason'] === 'too-wide'));
        $this->assertCount(1, $moved);
        $this->assertSame('content.sections.narrow-1', $moved[0]['path']);

        // Published as it stands, the section is drawn in a row of its own, outside the column.
        $this->publishedDocument('home', $home);
        $html = $this->page('/')->getContent();
        $this->assertStringNotContainsString('narrow-1', $this->cut($html, 'row-tiles'));
        $this->assertStringContainsString('<!--xelga:narrow-1-->', $html);

        // In the wide column of a split that has one, the same variant stays.
        $wide = $this->homeWithRow($split, [['narrow-1'], ['tile-b']]);
        $wide['sections']['narrow-1'] = $this->narrowSection($type, $variant);
        $adjusted = $this->saveWithRows('home', $wide, 1)->assertOk()->json('data.adjusted');
        $this->assertSame([], array_values(array_filter($adjusted, fn ($a) => $a['reason'] === 'too-wide')));
    }

    /** A list of products in the given column of a row, as its own preset ships. */
    private function productListSection(string $type, int $cols, string $variant = 'grid'): array
    {
        $preset = Catalog::section($type)->raw['presets'][0];

        return ['type' => $type, 'label' => '', 'disabled' => false, 'settings' => ['cols' => $cols, 'variant' => $variant] + $preset['settings'], 'block_order' => [], 'blocks' => []];
    }

    public function test_product_lists_cap_cards_per_row_to_their_width(): void
    {
        $this->fakePhoto();
        $store = Store::firstOrCreate(['code' => 'demo'], ['name' => 'Demo Store']);
        foreach (['Linen Shirt', 'Canvas Bag', 'Wool Cap', 'Denim Jacket'] as $i => $name) {
            Product::create([
                'store_id' => $store->id, 'name' => $name, 'slug' => 'demo-' . $i,
                'sku' => 'DEMO-00' . $i, 'price' => 1250, 'quantity' => 5, 'is_active' => true, 'image' => 'media/demo/photo.jpg',
            ]);
        }

        // Four to a line asked for: a half-width column holds two, and so does a two-thirds one.
        foreach (['6-6' => [6, '2'], '8-4' => [8, '2']] as $split => [$span, $cols]) {
            $home = $this->homeWithRow($split, [['list-1'], ['tile-b']]);
            $home['sections']['list-1'] = $this->productListSection('featured-products', 4);
            $home['sections']['list-1']['settings']['source'] = 'newest';
            $this->publishedDocument('home', $home);
            $section = $this->cut($this->cut($this->page('/')->getContent(), 'row-tiles'), 'list-1');

            $this->assertStringContainsString('data-cols="' . $cols . '"', $section);
            $this->assertStringContainsString('class="s s-products ', $section);
            // The cap reaches the card photos' sizes, as a share of the column.
            $this->assertStringContainsString('sizes="' . e(Picture::cardSizes(2, (int) $cols, $span)) . '"', $section);
        }

        // The same list on a full-width row keeps the setting.
        $home = $this->defaults('home');
        $home['sections']['list-1'] = $this->productListSection('featured-products', 4);
        $home['sections']['list-1']['settings']['source'] = 'newest';
        $home['order'][] = 'list-1';
        $this->publishedDocument('home', $home);
        $this->assertStringContainsString('data-cols="4"', $this->cut($this->page('/')->getContent(), 'list-1'));
    }

    public function test_card_sizes_count_the_columns_a_line_holds(): void
    {
        $this->assertSame('(min-width: 1320px) 640px, (min-width: 990px) 50vw, (min-width: 750px) 50vw, 50vw', Picture::cardSizes(2, 1, 6));
        // A column too narrow to open the grid draws the phone's number of cards to a line.
        $this->assertSame('(min-width: 1320px) 640px, (min-width: 990px) 50vw, (min-width: 750px) 50vw, 100vw', Picture::cardSizes(1, 4, 6));
        $this->assertSame('(min-width: 1320px) 320px, (min-width: 990px) 25vw, (min-width: 750px) 33vw, 50vw', Picture::cardSizes(2, 4, 12));
    }

    public function test_category_tile_photos_are_sized_for_their_column(): void
    {
        $this->fakePhoto();
        $home = $this->homeWithRow('6-6', [['tiles-1'], ['tile-b']]);
        $home['sections']['tiles-1'] = $this->tilesWithPhoto();
        $this->publishedDocument('home', $home);
        $section = $this->cut($this->cut($this->page('/')->getContent(), 'row-tiles'), 'tiles-1');

        $this->assertStringContainsString('data-cols="2"', $section);
        $this->assertStringContainsString('sizes="' . e(Picture::gridSizes(6, '', 2, 2)) . '"', $section);
    }

    /** Category tiles whose photo is set on each tile. */
    private function tilesWithPhoto(): array
    {
        $section = $this->narrowSection('category-tiles', 'image-cards');
        $store = Store::firstOrCreate(['code' => 'demo'], ['name' => 'Demo Store']);
        $category = \App\Models\Category::create(['store_id' => $store->id, 'name' => 'Shirts', 'slug' => 'shirts', 'is_active' => true]);
        foreach ($section['blocks'] as $id => $block) {
            $section['blocks'][$id]['settings']['image'] = 'media/demo/photo.jpg';
            $section['blocks'][$id]['settings']['category'] = $category->id;
        }

        return $section;
    }

    public function test_tiles_and_posts_fit_their_column(): void
    {
        $context = new RenderContext('home', 'home', 'template', false);

        $this->assertSame(5, $context->fitColumns(5));
        $this->assertSame(4, $context->fitColumns(null));
        $this->assertSame(3, $context->fitColumns(0, 3));
        $this->assertSame(2, $context->withSpan(6)->fitColumns(4));
        $this->assertSame(2, $context->withSpan(8)->fitColumns(5));
        $this->assertSame(1, $context->withSpan(4)->fitColumns(4));
        $this->assertSame(1, $context->withSpan(3)->fitColumns(2));
        $this->assertSame(2, $context->withSpan(12)->fitColumns(2));
    }

    public function test_wide_sections_sit_in_an_8_column(): void
    {
        // Shop the look, side by side with a tile: it stays in the wide column.
        $home = $this->homeWithRow('8-4', [['look-1'], ['tile-b']]);
        $home['sections']['look-1'] = $this->narrowSection('shop-the-look', 'side');
        $response = $this->saveWithRows('home', $home, 0)->assertOk();
        $this->assertSame([], array_values(array_filter($response->json('data.adjusted'), fn ($a) => $a['reason'] === 'too-wide')));

        $this->publishedDocument('home', $home);
        $row = $this->cut($this->page('/')->getContent(), 'row-tiles');
        $this->assertMatchesRegularExpression('#<div class="xr__col" data-span="8"><!--xelga:look-1-->#', $row);

        // The wide photo with its list below needs the whole page, so it leaves the column.
        $home['sections']['look-1'] = $this->narrowSection('shop-the-look', 'full');
        $adjusted = $this->saveWithRows('home', $home, 1)->assertOk()->json('data.adjusted');
        $this->assertCount(1, array_filter($adjusted, fn ($a) => $a['reason'] === 'too-wide' && $a['path'] === 'content.sections.look-1'));

        // Product details beside another section on the product page.
        $product = $this->productWith(['tile-c' => $this->tile('Tile c')]);
        $product['order'] = array_values(array_diff($product['order'], ['tile-c']));
        array_splice($product['order'], array_search('product-details-1', $product['order'], true) + 1, 0, ['tile-c']);
        $rows = [];
        foreach (Layout::rows($product) as $row) {
            if ($row['columns'][0] === ['tile-c']) {
                continue;
            }
            $rows[] = $row['columns'][0] === ['product-details-1']
                ? ['id' => 'row-details', 'split' => '8-4', 'columns' => [['product-details-1'], ['tile-c']], 'settings' => []]
                : $row;
        }
        $product['rows'] = $rows;
        $response = $this->saveWithRows('product', $product, 0)->assertOk();
        $this->assertSame([], array_values(array_filter($response->json('data.adjusted'), fn ($a) => $a['reason'] === 'too-wide')));
        $kept = collect($response->json('data.document.content.rows'))->firstWhere('id', 'row-details');
        $this->assertSame([['product-details-1'], ['tile-c']], $kept['columns']);
    }

    public function test_a_plain_row_draws_no_wrapper(): void
    {
        // The first visit makes the photos' smaller copies, which later visits list.
        $this->mainContent('/');
        $before = $this->mainContent('/');

        // Every section in a one-column row of its own, one of them with
        // settings, so the stored rows are kept and drawn.
        $home = $this->defaults('home');
        $home['rows'] = Layout::rows($home);
        $home['rows'][1]['settings'] = ['width' => 'full', 'scheme' => 'scheme-2', 'space' => 'lg'];
        $this->publishedDocument('home', $home);

        $after = $this->mainContent('/');
        $this->assertSame($before, $after);
        $this->assertStringNotContainsString('class="xr', $after);
    }

    public function test_a_row_draws_its_columns_with_spans(): void
    {
        $this->publishedDocument('home', $this->homeWithRow('6-6', [['tile-a'], ['tile-b']]));

        $html = $this->page('/')->getContent();
        $row = $this->cut($html, 'row-tiles');

        $this->assertStringStartsWith('<div class="xr" data-row-id="row-tiles" data-split="6-6" data-width="page" data-space="md"><div class="xr__inner">', $row);
        $this->assertSame(['6', '6'], $this->columnSpans($row));
        foreach (['tile-a', 'tile-b'] as $tile) {
            $this->assertMatchesRegularExpression('#data-section-id="' . $tile . '"[^>]*data-span="6"#', $this->cut($row, $tile));
        }
        // Each column holds its own tile, in order.
        $this->assertMatchesRegularExpression('#<div class="xr__col" data-span="6"><!--xelga:tile-a-->.*?<!--/xelga:tile-a--></div><div class="xr__col" data-span="6"><!--xelga:tile-b-->#s', $row);
        $this->assertStringEndsWith('</div></div></div>', $row);
        // The banner before the row and the promises after it draw outside it.
        $this->assertLessThan(strpos($html, '<!--xelga:row-tiles-->'), strpos($html, '<!--/xelga:hero-1-->'));
        $this->assertGreaterThan(strpos($html, '<!--/xelga:row-tiles-->'), strpos($html, '<!--xelga:usp-1-->'));
    }

    public function test_quarters_hold_span_three_sections(): void
    {
        $this->publishedDocument('home', $this->homeWithRow('3-3-3-3', [['t-1'], ['t-2'], ['t-3'], ['t-4']]));

        $row = $this->cut($this->page('/')->getContent(), 'row-tiles');

        $this->assertSame(['3', '3', '3', '3'], $this->columnSpans($row));
        foreach (['t-1', 't-2', 't-3', 't-4'] as $tile) {
            $this->assertStringContainsString('data-span="3"', $this->cut($row, $tile));
        }
    }

    public function test_an_empty_column_keeps_its_place(): void
    {
        $this->publishedDocument('home', $this->homeWithRow('3-6-3', [[], ['tile-b'], ['tile-c']]));

        $row = $this->cut($this->page('/')->getContent(), 'row-tiles');

        $this->assertSame(['3', '6', '3'], $this->columnSpans($row));
        $this->assertStringContainsString('<div class="xr__inner"><div class="xr__col" data-span="3"></div><div class="xr__col" data-span="6"><!--xelga:tile-b-->', $row);
    }

    public function test_hidden_sections_in_a_column_are_not_drawn_for_shoppers(): void
    {
        $home = $this->homeWithRow('6-6', [['tile-a', 'tile-hidden'], ['tile-b']]);
        $home['sections']['tile-hidden']['disabled'] = true;
        $this->publishedDocument('home', $home);

        $row = $this->cut($this->page('/')->getContent(), 'row-tiles');
        $this->assertStringNotContainsString('tile-hidden', $row);
        $this->assertSame(['6', '6'], $this->columnSpans($row));

        // A row whose sections are all hidden draws nothing at all.
        $home['sections']['tile-a']['disabled'] = true;
        $home['sections']['tile-b']['disabled'] = true;
        $this->publishedDocument('home', $home);
        $html = $this->page('/')->getContent();
        $this->assertStringNotContainsString('row-tiles', $html);
        $this->assertStringNotContainsString('class="xr', $html);
    }

    public function test_section_only_cuts_a_whole_row(): void
    {
        $home = $this->homeWithRow('6-6', [['tile-a'], ['tile-b']]);
        $this->saveWithRows('home', $home, 0)->assertOk();
        $nonce = $this->openEditor($this->admin());

        $row = $this->get('/?xelga_preview=' . $nonce . '&xelga_section=row-tiles')
            ->assertOk()->assertHeader('X-Xelga-Section', 'row-tiles')->getContent();
        $this->assertStringStartsWith('<div class="xr" data-row-id="row-tiles"', $row);
        $this->assertSame(['6', '6'], $this->columnSpans($row));
        $this->assertStringContainsString('data-section-id="tile-a"', $row);
        $this->assertStringContainsString('data-section-id="tile-b"', $row);
        $this->assertStringNotContainsString('data-section-id="hero-1"', $row);

        // A section in a column is still answered on its own.
        $tile = $this->get('/?xelga_preview=' . $nonce . '&xelga_section=tile-b')->assertOk()->getContent();
        $this->assertStringStartsWith('<section', ltrim($tile));
        $this->assertStringContainsString('data-span="6"', $tile);
    }

    public function test_images_in_a_column_are_sized_for_the_column(): void
    {
        $this->publishedDocument('home', $this->homeWithRow('4-8', [['tile-a'], ['tile-b']]));

        $row = $this->cut($this->page('/')->getContent(), 'row-tiles');

        $this->assertStringContainsString('sizes="' . e(Picture::spanSizes(4)) . '"', $this->cut($row, 'tile-a'));
        $this->assertStringContainsString('427px', $this->cut($row, 'tile-a'));
        $this->assertStringContainsString('854px', $this->cut($row, 'tile-b'));
        // A tile in a full-width row is sized for the page.
        $this->assertSame('(min-width: 1320px) 1280px, 100vw', Picture::spanSizes(12));
    }

    public function test_image_sizes_follow_the_grid_at_each_width(): void
    {
        $this->assertSame('(min-width: 1320px) 427px, (min-width: 990px) 34vw, (min-width: 750px) 50vw, 100vw', Picture::spanSizes(4));
        $this->assertSame('(min-width: 1320px) 854px, (min-width: 750px) 67vw, 100vw', Picture::spanSizes(8));
        $this->assertSame('(min-width: 1320px) 640px, (min-width: 750px) 50vw, 100vw', Picture::spanSizes(6));
        $this->assertSame('(min-width: 1320px) 320px, (min-width: 990px) 25vw, 50vw', Picture::spanSizes(3, '50vw'));

        // A product card's photo: unchanged on a full-width row, a share of the column in one.
        $this->assertSame('(min-width: 1320px) 320px, (min-width: 990px) 25vw, (min-width: 750px) 33vw, 50vw', Picture::cardSizes());
        $this->assertSame('(min-width: 1320px) 320px, (min-width: 990px) 25vw, (min-width: 750px) 33vw, 100vw', Picture::cardSizes(1, 4, 12));
        $this->assertSame('(min-width: 1320px) 320px, (min-width: 990px) 25vw, (min-width: 750px) 25vw, 50vw', Picture::cardSizes(2, 3, 6));
    }

    public function test_row_settings_reach_the_markup(): void
    {
        $this->publishedDocument('home', $this->homeWithRow('8-4', [['tile-a'], ['tile-b']], ['width' => 'full', 'scheme' => 'scheme-2', 'space' => 'lg']));

        $row = $this->cut($this->page('/')->getContent(), 'row-tiles');

        $this->assertStringStartsWith('<div class="xr scheme-2" data-row-id="row-tiles" data-split="8-4" data-width="full" data-space="lg">', $row);
        $this->assertSame(['8', '4'], $this->columnSpans($row));
    }

    public function test_drawn_types_leave_out_hidden_sections(): void
    {
        // As the storefront hands it over: rows read back with every section placed.
        $normalized = fn (array $content) => Layout::normalize($content, app(LayoutRules::class, ['handle' => 'home']))['content'];
        $home = $this->homeWithRow('6-6', [['tile-a'], ['tile-b']]);
        $context = new RenderContext('home', 'home', 'template', false);
        $renderer = app(SectionRenderer::class);

        $types = $renderer->drawnTypes($normalized($home), $context);
        $this->assertContains('hero', $types);
        $this->assertContains('sample-extension.tile', $types);
        $this->assertSame($types, array_values(array_unique($types)));
        $this->assertTrue($context->withTypes($types)->has('sample-extension.tile'));

        $home['sections']['tile-a']['disabled'] = true;
        $home['sections']['tile-b']['disabled'] = true;
        $home['sections']['gone-1'] = ['type' => 'no-such-section', 'disabled' => false, 'settings' => [], 'block_order' => [], 'blocks' => []];
        $home['order'][] = 'gone-1';
        $types = $renderer->drawnTypes($normalized($home), $context);
        $this->assertFalse($context->withTypes($types)->has('sample-extension.tile'));
        $this->assertNotContains('no-such-section', $types);
        $this->assertTrue($context->withTypes($types)->has('hero'));

        // Every section is told what else the page draws.
        $this->publishedDocument('home', $this->homeWithRow('6-6', [['tile-a'], ['tile-b']]));
        $this->assertStringContainsString('data-beside-banner', $this->cut($this->page('/')->getContent(), 'tile-a'));
    }

    public function test_a_column_span_survives_a_change_of_group(): void
    {
        $context = (new RenderContext('home', 'home', 'template', false))->withSpan(4)->withTypes(['hero']);

        $this->assertSame(4, $context->span);
        $this->assertSame(4, $context->withGroup('footer')->span);
        $this->assertTrue($context->withGroup('footer')->has('hero'));
        $this->assertSame(12, (new RenderContext('home', 'home', 'template', false))->span);
        $this->assertSame(12, $context->withSpan(40)->span);
        $this->assertSame(1, $context->withSpan(0)->span);
    }

    /**
     * A page's template drawn for a content area $span twelfths wide, with
     * the data core's controller handed the page: the page is visited once
     * to learn that data, then drawn again by the renderer.
     */
    private function drawnAt(string $url, int $span, ?SectionRenderer $renderer = null): string
    {
        $seen = null;
        \Illuminate\Support\Facades\View::composer('xelga::snippets.template', function ($view) use (&$seen) {
            $seen = $view->getData();
        });
        $this->page($url);
        $this->assertNotNull($seen, "{$url} draws a template.");

        $handle = app(\Themes\Xelga\Rendering\TemplateResolver::class)->handleFor($seen['pageType'], $seen['subject'] ?? null);
        $content = Storefront::withMainSection(app(\Themes\Xelga\Rendering\DocumentRepository::class)->content($handle), \Themes\Xelga\Documents\Handles::baseFor($seen['pageType']));
        $content = Layout::normalize($content, app(LayoutRules::class, ['handle' => $handle]))['content'];
        $context = (new RenderContext($seen['pageType'], $handle, 'template', false, $seen['subject'] ?? null, $seen['page'] ?? []))->withSpan($span);

        return ($renderer ?? app(SectionRenderer::class))->render($content, $context);
    }

    /** The sizes attribute of every photo in the HTML. */
    private function sizesIn(string $html): array
    {
        preg_match_all('#\bsizes="([^"]*)"#', $html, $m);

        return array_map('html_entity_decode', $m[1]);
    }

    public function test_the_main_banner_is_sized_for_a_narrower_content_area(): void
    {
        $this->fakePhoto();
        $home = $this->defaults('home');
        foreach ($home['sections'] as $id => $section) {
            if ($section['type'] === 'hero') {
                $home['sections'][$id]['settings']['variant'] = 'full-image';
                foreach ($section['blocks'] as $blockId => $block) {
                    $home['sections'][$id]['blocks'][$blockId]['settings']['image'] = 'media/demo/photo.jpg';
                }
                $hero = $id;
            }
        }
        $this->publishedDocument('home', $home);

        // The whole page wide, the banner is sized for the screen as before.
        $this->assertSame(['100vw'], array_values(array_unique($this->sizesIn($this->cut($this->page('/')->getContent(), $hero)))));

        // Beside the sides, for the content area left between them.
        $renderer = app(SectionRenderer::class);
        $sizes = array_values(array_unique($this->sizesIn($this->cut($this->drawnAt('/', 6, $renderer), $hero))));
        $this->assertSame([Picture::contentSizes(6, '100vw')], $sizes);
        $this->assertStringContainsString('640px', $sizes[0]);
        $this->assertSame(6, $renderer->contentSpan());
        // Below 990 pixels the sides stack and the banner is the screen's width again.
        $this->assertStringEndsWith(', 100vw', $sizes[0]);
    }

    public function test_main_sections_are_sized_for_a_narrower_content_area(): void
    {
        $this->fakePhoto();
        $store = Store::firstOrCreate(['code' => 'demo'], ['name' => 'Demo Store']);
        $category = \App\Models\Category::create(['store_id' => $store->id, 'name' => 'Laptops', 'slug' => 'laptops', 'is_active' => true]);
        foreach (['DEMO-001' => 'Demo Laptop 13', 'DEMO-002' => 'Demo Laptop 14', 'DEMO-003' => 'Demo Laptop 15'] as $sku => $name) {
            $product = Product::create([
                'store_id' => $store->id, 'name' => $name, 'slug' => strtolower($sku), 'sku' => $sku,
                'price' => 45000, 'quantity' => 10, 'track_stock' => true, 'is_active' => true, 'image' => 'media/demo/photo.jpg',
            ]);
            $product->categories()->attach($category->id);
        }

        // The product's photos: sized for the photo column of the whole page as
        // before, and for the content area (one column) at six twelfths.
        $full = $this->sizesIn($this->drawnAt('/product/demo-001', 12));
        $this->assertContains('(min-width: 1320px) 720px, (min-width: 750px) 55vw, 100vw', $full);
        $narrow = $this->sizesIn($this->drawnAt('/product/demo-001', 6));
        $this->assertContains(Picture::contentSizes(6, '(min-width: 1320px) 720px, (min-width: 750px) 55vw, 100vw'), $narrow);
        $this->assertStringStartsWith('(min-width: 1320px) 640px, (min-width: 990px) 50vw, ', Picture::contentSizes(6, '(min-width: 1320px) 720px, (min-width: 750px) 55vw, 100vw'));

        // The category's cards: as many to a line as the content area holds, and sized for it.
        $grid = fn (string $html) => (string) preg_replace('#^.*?(<section[^>]*data-section-type="category-main".*?</section>).*$#s', '$1', $html);
        $wide = $grid($this->drawnAt('/category/laptops', 12));
        $this->assertMatchesRegularExpression('#data-section-type="category-main"[^>]*data-cols="3"#s', $wide);
        $this->assertContains(Picture::cardSizes(), $this->sizesIn($wide));
        $six = $grid($this->drawnAt('/category/laptops', 6));
        $this->assertMatchesRegularExpression('#data-section-type="category-main"[^>]*data-cols="2"#s', $six);
        $this->assertContains(Picture::contentSizes(6, Picture::cardSizes(2, 2), .5), $this->sizesIn($six));
        $this->assertNotContains(Picture::cardSizes(), $this->sizesIn($six));
    }

    public function test_the_content_area_is_the_whole_page_until_sides_are_drawn(): void
    {
        $renderer = app(SectionRenderer::class);
        $this->assertSame(12, $renderer->contentSpan());

        // A row keeps its split; its columns' sections take their share of the content area.
        $this->publishedDocument('home', $this->homeWithRow('6-6', [['tile-a'], ['tile-b']]));
        $html = $this->drawnAt('/', 6, $renderer);
        $this->assertSame(['6', '6'], $this->columnSpans($this->cut($html, 'row-tiles')));
        $this->assertStringContainsString('data-span="3"', $this->cut($html, 'tile-a'));
        $this->assertStringContainsString('sizes="' . e(Picture::spanSizes(3)) . '"', $this->cut($html, 'tile-a'));

        // The store's own pages are drawn the whole page wide, so main carries no width.
        $page = $this->page('/')->getContent();
        $this->assertStringContainsString('<main id="main-content">', $page);
        $this->assertSame(12, app(Storefront::class)->contentSpan());
    }

    protected function tearDown(): void
    {
        Storefront::forgetRequestState();
        parent::tearDown();
    }
}
