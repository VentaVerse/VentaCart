<?php

namespace Themes\Xelga\Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ProductSpecial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\ThemeService;
use Tests\Support\UsesSampleExtension;
use Tests\TestCase;
use Themes\Xelga\Catalog\Catalog;
use Themes\Xelga\Catalog\CatalogLinter;
use Themes\Xelga\Documents\Defaults;
use Themes\Xelga\Rendering\Storefront;
use Themes\Xelga\Tests\XelgaExtensionHelpers;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';
require_once __DIR__ . '/../XelgaExtensionHelpers.php';

/**
 * The Filters and Category list sections, made to sit in a page side.
 * Filters takes the category listing's filter form out of the listing:
 * a category page that draws a Filters section has one filter form, in
 * that section, and the listing's Filter button leads to it. A page
 * without one (or with a hidden one) keeps the listing's own column.
 * Category list shows core's active categories and marks the one shown.
 */
class XelgaFiltersSectionTest extends TestCase
{
    use RefreshDatabase;
    use UsesSampleExtension;
    use XelgaExtensionHelpers;
    use XelgaTestHelpers;

    private Category $laptops;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpXelgaWithExtension();

        $this->laptops = Category::create(['store_id' => $this->store->id, 'name' => 'Laptops', 'slug' => 'laptops', 'is_active' => true]);
        $brand = Brand::create(['store_id' => $this->store->id, 'name' => 'Northwind', 'slug' => 'northwind', 'is_active' => true]);
        foreach (['Demo Laptop 13' => 30000, 'Demo Laptop 15' => 50000] as $name => $price) {
            $product = $this->product($name);
            $product->update(['price' => $price, 'brand_id' => $brand->id]);
            $product->categories()->attach($this->laptops->id);
        }
    }

    protected function tearDown(): void
    {
        Storefront::forgetRequestState();
        parent::tearDown();
    }

    private function filters(bool $disabled = false): array
    {
        return ['type' => 'filters', 'label' => '', 'disabled' => $disabled, 'settings' => [], 'block_order' => [], 'blocks' => []];
    }

    private function categoryList(array $settings = []): array
    {
        return ['type' => 'category-list', 'label' => '', 'disabled' => false, 'settings' => $settings, 'block_order' => [], 'blocks' => []];
    }

    /** The category template with these sections in its left side. */
    private function withLeft(array $sections, ?array $content = null): array
    {
        $content ??= Defaults::content('category');
        $content['sections'] = [...$content['sections'], ...$sections];
        $content['order'] = [...array_keys($sections), ...$content['order']];
        $content['sides'] = ['left' => array_keys($sections)];

        return $content;
    }

    /** The left side's HTML. */
    private function leftSide(string $html): string
    {
        return $this->sideArea($html, 'left');
    }

    /** Content, beside the sides. */
    private function content(string $html): string
    {
        return $this->contentArea($html);
    }

    public function test_a_filters_section_takes_over_the_listing_filter_column(): void
    {
        $this->publishedDocument('category', $this->withLeft(['filters-1' => $this->filters()]));

        $html = $this->page('/category/laptops')->getContent();

        $this->assertSame(1, substr_count($html, 'data-filters>'), 'The page has one filter form.');
        $side = $this->leftSide($html);
        $this->assertStringContainsString('<section class="s s-filters scheme-1" id="filters-1"', $side);
        $this->assertStringContainsString('<form class="filters" id="filters-1-filters"', $side);
        $this->assertStringContainsString('name="brand[]"', $side);
        $this->assertStringContainsString('name="min_price"', $side);

        $main = $this->content($html);
        $this->assertStringContainsString('data-filters="elsewhere"', $main);
        $this->assertStringNotContainsString('listing__aside', $main);
        $this->assertStringNotContainsString('data-filter-drawer', $main);
        $this->assertStringNotContainsString('<form class="filters"', $main);
        $this->assertMatchesRegularExpression('#<button type="button" class="btn btn--secondary btn--sm toolbar__filter" data-filter-open aria-controls="filters-1" aria-expanded="false">#', $main);
        $this->assertStringNotContainsString('data-filter-jump', $html);
    }

    public function test_the_filter_button_opens_the_filters_section(): void
    {
        $this->publishedDocument('category', $this->withLeft(['filters-1' => $this->filters()]));

        $html = $this->page('/category/laptops')->getContent();

        $this->assertSame(1, preg_match('#<button[^>]*data-filter-open[^>]*aria-controls="([^"]+)"[^>]*aria-expanded="false"#', $html, $button));
        $this->assertStringContainsString('<section class="s s-filters scheme-1" id="' . $button[1] . '"', $html);
        // The section carries the drawer's heading, which takes focus when it opens, and its close button.
        $side = $this->leftSide($html);
        $this->assertStringContainsString('id="filters-1-title" tabindex="-1" data-filters-title', $side);
        $this->assertStringContainsString('data-filters-close', $side);
        $this->assertStringNotContainsString('<details class="filters-box"', $side);
    }

    public function test_the_filter_button_shows_only_where_the_filters_section_draws_a_form(): void
    {
        $this->publishedDocument('category', $this->withLeft(['filters-1' => $this->filters()]));

        foreach (['/category/laptops', '/category/empty-shelf'] as $url) {
            Category::firstOrCreate(['store_id' => $this->store->id, 'slug' => 'empty-shelf'], ['name' => 'Empty shelf', 'is_active' => true]);
            $html = $this->page($url)->getContent();

            $this->assertSame(
                substr_count($html, '<form class="filters"'),
                substr_count($html, 'data-filter-open'),
                "{$url}: a Filter button for each filter form, none without one.",
            );
        }

        // Every filter switched off in core: no form, no button, only the section's note.
        foreach (['price', 'availability', 'on_sale', 'brand', 'tags'] as $key) {
            Setting::updateOrCreate(['group' => 'filters', 'key' => "{$key}_enabled"], ['value' => '0']);
        }
        app(ThemeService::class)->clearCache();
        $this->app->forgetInstance(ThemeService::class);

        $html = $this->page('/category/laptops')->getContent();

        $this->assertStringNotContainsString('data-filter-open', $html);
        $this->assertStringNotContainsString('<form class="filters"', $html);
    }

    public function test_one_filter_form_on_the_page(): void
    {
        $this->publishedDocument('category', $this->withLeft(['filters-1' => $this->filters()]));
        $html = $this->page('/category/laptops')->getContent();
        $this->assertSame(1, substr_count($html, 'data-filters>'), 'In a side.');
        $this->assertSame(1, substr_count($html, 'id="filters-1-filters"'));
        $this->assertSame(0, substr_count($html, 'data-filter-drawer'));

        // In a Content column.
        $content = Defaults::content('category');
        $content['sections']['filters-1'] = $this->filters();
        $content['sections']['note-1'] = ['type' => 'rich-text', 'label' => '', 'disabled' => false, 'settings' => [], 'block_order' => [], 'blocks' => []];
        $content['rows'] = [];
        foreach ($content['order'] as $id) {
            $area = match (true) {
                $id === 'category-main-1' => 'content',
                in_array($content['sections'][$id]['type'], ['breadcrumbs'], true) => 'top',
                default => 'bottom',
            };
            $content['rows'][] = ['id' => 'row-' . $id, 'split' => '12', 'columns' => [[$id]], 'settings' => ['scheme' => 'scheme-1', 'width' => 'contained', 'space' => 'normal'], 'area' => $area];
        }
        array_splice($content['rows'], array_search('row-category-main-1', array_column($content['rows'], 'id')), 0, [
            ['id' => 'row-filters', 'split' => '4-8', 'columns' => [['filters-1'], ['note-1']], 'settings' => ['scheme' => 'scheme-1', 'width' => 'contained', 'space' => 'normal'], 'area' => 'content'],
        ]);
        $content['order'] = ['filters-1', 'note-1', ...array_values(array_diff($content['order'], ['filters-1', 'note-1']))];
        $this->publishedDocument('category', $content);

        $html = $this->page('/category/laptops')->getContent();

        $this->assertSame(1, substr_count($html, 'data-filters>'), 'In a Content column.');
        $this->assertSame(1, substr_count($html, 'id="filters-1-filters"'));
        $this->assertStringContainsString('aria-controls="filters-1"', $html);
        $this->assertSame(1, substr_count($html, '<section class="s s-filters')); 
    }

    public function test_filters_keep_the_sort_and_active_values(): void
    {
        $sale = Product::where('name', 'Demo Laptop 15')->first();
        ProductSpecial::create(['product_id' => $sale->id, 'price' => 45000, 'quantity' => 1, 'display_mode' => 'sale', 'priority' => 1]);
        $this->publishedDocument('category', $this->withLeft(['filters-1' => $this->filters()]));

        $html = $this->page('/category/laptops?sort=price_desc&on_sale=1&min_price=100')->getContent();

        $side = $this->leftSide($html);
        $this->assertStringContainsString('<input type="hidden" name="sort" value="price_desc">', $side);
        $this->assertStringContainsString('name="on_sale" value="1" checked', $side);
        $this->assertStringContainsString('name="min_price" min="0" step="any" value="100"', $side);
        // Clearing keeps the sort the shopper chose.
        $this->assertStringContainsString('href="' . url('/category/laptops') . '?sort=price_desc" data-filters-clear', $side);
        // The badge and the chips in the listing count what is on.
        $main = $this->content($html);
        $this->assertMatchesRegularExpression('#data-filter-badge\s*>2</span>#', $main);
        $this->assertStringContainsString('Remove filter On sale', $main);
        $this->assertStringContainsString('Demo Laptop 15', $main);
        $this->assertStringNotContainsString('Demo Laptop 13', $main);
    }

    public function test_only_one_filters_section_per_page(): void
    {
        $content = $this->withLeft(['filters-1' => $this->filters(), 'filters-2' => $this->filters()]);

        $this->saveDraft($this->admin(), 'category', $content, 0)
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['content.sections.filters-2.type']]);

        $this->saveDraft($this->admin(), 'category', $this->withLeft(['filters-1' => $this->filters()]), 0)->assertOk();
    }

    public function test_filters_cannot_be_added_to_the_home_page(): void
    {
        $home = Defaults::content('home');
        $home['sections']['filters-1'] = $this->filters();
        $home['order'] = ['filters-1', ...$home['order']];
        $home['sides'] = ['left' => ['filters-1']];

        $this->saveDraft($this->admin(), 'home', $home, 0)
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['content.sections.filters-1.type']]);
    }

    public function test_category_list_shows_active_categories_and_marks_the_current_one(): void
    {
        $this->laptops->update(['sort_order' => 1]);
        $gaming = Category::create(['store_id' => $this->store->id, 'parent_id' => $this->laptops->id, 'name' => 'Gaming laptops', 'slug' => 'gaming-laptops', 'is_active' => true]);
        Category::create(['store_id' => $this->store->id, 'parent_id' => $gaming->id, 'name' => 'Deep branch', 'slug' => 'deep-branch', 'is_active' => true]);
        Category::create(['store_id' => $this->store->id, 'name' => 'Archive', 'slug' => 'archive', 'is_active' => false]);
        Category::create(['store_id' => $this->store->id, 'name' => 'Audio', 'slug' => 'audio', 'is_active' => true, 'sort_order' => 2]);
        $this->publishedDocument('category', $this->withLeft(['category-list-1' => $this->categoryList(['heading' => 'Shop by category'])]));

        $side = $this->leftSide($this->page('/category/laptops')->getContent());

        $this->assertStringContainsString('Shop by category', $side);
        $this->assertStringContainsString('<a class="category-list__link" href="' . route('category.show', 'laptops') . '" aria-current="page">Laptops</a>', $side);
        $this->assertStringContainsString('<a class="category-list__link" href="' . route('category.show', 'gaming-laptops') . '">Gaming laptops</a>', $side);
        $this->assertStringContainsString('>Audio</a>', $side);
        $this->assertLessThan(strpos($side, '>Audio<'), strpos($side, '>Gaming laptops<'), 'Tree order: a child follows its parent.');
        $this->assertStringNotContainsString('Archive', $side);
        $this->assertStringNotContainsString('Deep branch', $side, 'Two levels by default.');

        // Top level only.
        $this->publishedDocument('category', $this->withLeft(['category-list-1' => $this->categoryList(['depth' => 'top'])]));
        $side = $this->leftSide($this->page('/category/gaming-laptops')->getContent());
        $this->assertStringContainsString('Categories', $side);
        $this->assertStringContainsString('>Laptops</a>', $side);
        $this->assertStringNotContainsString('Gaming laptops', $side);
        $this->assertStringNotContainsString('aria-current', $side);
    }

    public function test_category_list_can_sit_beside_the_product_page_but_not_the_cart(): void
    {
        $product = Defaults::content('product');
        $product['sections']['category-list-1'] = $this->categoryList();
        $product['order'] = ['category-list-1', ...$product['order']];
        $product['sides'] = ['left' => ['category-list-1']];
        $this->saveDraft($this->admin(), 'product', $product, 0)->assertOk();

        $this->assertTrue(Catalog::section('category-list')->allowedIn('category.gift-guide', 'template', 'category'));
        $this->assertFalse(Catalog::section('category-list')->allowedIn('cart', 'template', 'cart'));
        $this->assertFalse(Catalog::section('filters')->allowedIn('product', 'template', 'product'));
        $this->assertTrue(Catalog::section('filters')->allowedIn('category.gift-guide', 'template', 'category'));
    }

    public function test_both_sections_pass_the_linter(): void
    {
        foreach (['filters', 'category-list'] as $type) {
            $schema = Catalog::section($type);
            $this->assertNotNull($schema, $type);
            $this->assertSame(1, $schema->limit(), $type);
            $this->assertSame(3, $schema->raw['span']['min'] ?? null, $type);
            $this->assertSame('categories', $schema->pickerGroup(), $type);
            $this->assertIsInt($schema->pickerOrder(), $type);
        }
        $this->assertSame('Filters', Catalog::section('filters')->label);
        $this->assertSame('Category list', Catalog::section('category-list')->label);
        $this->assertSame([], CatalogLinter::check());
    }
}
