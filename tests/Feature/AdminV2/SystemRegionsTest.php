<?php

namespace Tests\Feature\AdminV2;

use App\Support\Regions;
use App\Support\Widgets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesSampleTheme;
use Tests\TestCase;

/**
 * Placeable system widgets: a theme may declare a region whose contents
 * default to its own pieces (a header, a product page body), which the
 * storefront shows until the merchant arranges the region. The default
 * theme declares none and keeps its fixed pages.
 */
class SystemRegionsTest extends TestCase
{
    use RefreshDatabase, UsesSampleTheme;

    public function test_a_theme_declares_a_header_region_with_its_shipped_piece_and_the_default_does_not(): void
    {
        $this->makeSampleTheme();

        $this->assertSame([], Regions::defaults('header', 'venta'));
        $this->assertSame([['widget' => 'system.header', 'title' => null, 'config' => []]], Regions::defaults('header', 'sample'));
        $this->assertSame('*', Regions::page('header', 'sample'));
    }

    public function test_header_pieces_are_offered_only_in_a_header_region(): void
    {
        $this->assertSame(['header'], Widgets::get('system.logo')['regions']);
        $this->assertTrue(Widgets::get('system.header')['system']);
        $this->assertFalse(Widgets::allowedIn('system.logo', 'content_top'));
    }

    public function test_the_default_theme_keeps_its_fixed_product_page(): void
    {
        app(\App\Services\ExtensionManager::class)->install('reviews');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        $product = \App\Models\Product::create(['store_id' => 1, 'name' => 'Region Fuzz', 'slug' => 'region-fuzz', 'sku' => 'RF1', 'price' => 1200, 'quantity' => 3, 'is_active' => true]);

        $this->get(route('product.show', $product->slug))->assertOk()->assertSee('addToCart(', false);
        $this->assertSame([], Regions::defaults('product_main', 'venta'));
    }

    public function test_the_product_page_is_offered_as_separate_pieces_on_the_product_page_only(): void
    {
        foreach (['images', 'name', 'rating', 'info', 'price', 'countdown', 'variants', 'volume_pricing', 'add_to_cart', 'tags', 'share', 'description', 'reviews', 'buy', 'tabs', 'related', 'brand'] as $piece) {
            $spec = Widgets::get("system.product_{$piece}");
            $this->assertNotNull($spec, "system.product_{$piece} is registered.");
            $this->assertTrue($spec['system']);
            $this->assertSame(['product_main'], $spec['regions']);
            $this->assertSame(['product'], $spec['pages']);
            $this->assertSame("widgets.system.product_{$piece}", $spec['view']);
        }
        $this->assertFalse(Widgets::allowedIn('system.product_price', 'content_top'));
        $this->assertSame(['brand', 'shipping', 'stock'], array_column(Widgets::schema('system.product_info'), 'name'));
    }

    public function test_the_category_page_is_offered_as_separate_pieces_on_the_category_page_only(): void
    {
        foreach (['image', 'description', 'subcategories', 'products'] as $piece) {
            $spec = Widgets::get("system.category_{$piece}");
            $this->assertNotNull($spec, "system.category_{$piece} is registered.");
            $this->assertTrue($spec['system']);
            $this->assertSame(['category_main'], $spec['regions']);
            $this->assertSame(['category'], $spec['pages']);
        }
        $this->assertTrue(Widgets::allowedIn('system.breadcrumbs', 'category_main'));
        $this->assertTrue(Widgets::allowedIn('system.breadcrumbs', 'product_main'));
        $this->assertTrue(Widgets::allowedIn('system.page_title', 'category_main'));
        $this->assertFalse(Widgets::allowedIn('system.page_title', 'product_main'), 'The product name is its own piece there.');
    }

    public function test_a_shipped_row_keeps_its_columns_and_plain_keys_share_a_full_width_row(): void
    {
        $this->makeSampleTheme();

        $rows = Regions::defaultRows('product_main', 'sample');
        $this->assertCount(2, $rows);
        $this->assertSame([4, 4], $rows[0]['columns']);
        $this->assertSame(['system.product_images'], array_column($rows[0]['cols'][0], 'widget'));
        $this->assertSame(['system.product_name', 'system.product_price', 'system.product_add_to_cart'], array_column($rows[0]['cols'][1], 'widget'));
        $this->assertSame([8], $rows[1]['columns']);
        $this->assertSame(['system.product_tabs'], array_column($rows[1]['cols'][0], 'widget'));

        // The flat list is every piece in order, as it always was.
        $this->assertSame(
            ['system.product_images', 'system.product_name', 'system.product_price', 'system.product_add_to_cart', 'system.product_tabs'],
            array_column(Regions::defaults('product_main', 'sample'), 'widget')
        );
        $this->assertSame([], \App\Support\ThemeContract::check(resource_path('themes/sample')), 'Every piece its regions take has a view.');
    }

    public function test_a_shipped_row_whose_columns_do_not_add_up_is_reported_and_drawn_as_one_column(): void
    {
        $this->makeSampleTheme();
        $path = resource_path('themes/sample/theme.json');
        $manifest = json_decode((string) file_get_contents($path), true);
        $manifest['regions']['product_main']['defaults'] = [['columns' => [4, 3], 'cols' => [['system.product_images'], ['system.product_price']]]];
        file_put_contents($path, json_encode($manifest));

        $this->assertStringContainsString('do not add up', implode(' ', \App\Support\ThemeContract::check(resource_path('themes/sample'))));
        $rows = Regions::defaultRows('product_main', 'sample');
        $this->assertSame([8], $rows[0]['columns']);
        $this->assertSame(['system.product_images', 'system.product_price'], array_column($rows[0]['cols'][0], 'widget'));
    }

    public function test_an_unarranged_region_draws_its_shipped_rows_through_the_row_grid(): void
    {
        $this->installSampleTheme(activate: true);
        // The theme's views join the finder per request; this renders outside one.
        view()->getFinder()->prependLocation(resource_path('themes/sample/views'));

        $html = \Illuminate\Support\Facades\Blade::render('<x-storefront.region name="product_main" />');

        $this->assertStringContainsString('layout-row layout-row--2', $html);
        $this->assertMatchesRegularExpression('/layout-col--4">\s*<div class="sample-product_images">/', $html);
        $this->assertLessThan(strpos($html, 'sample-product_tabs'), strpos($html, 'sample-product_add_to_cart'));
        $this->assertSame(0, \App\Models\LayoutRow::count(), 'Nothing was written to show the default.');
    }

    public function test_the_editor_offers_the_shipped_rows_until_the_page_or_its_parent_is_arranged(): void
    {
        $this->installSampleTheme(activate: true);
        $category = \App\Models\Category::create(['store_id' => 1, 'name' => 'Demo Pedals', 'slug' => 'demo-pedals', 'is_active' => true]);

        $product = (array) \App\Support\LayoutInventory::units('product');
        $this->assertCount(2, $product['product_main']);
        $this->assertTrue($product['product_main'][0]['shipped']);
        $this->assertSame([4, 4], $product['product_main'][0]['columns']);
        $this->assertSame('system.product_images', $product['product_main'][0]['cols'][0][0]['widget']);
        $this->assertArrayNotHasKey('category_main', $product, 'A region is offered on its own page type.');

        // One category inherits all categories' arrangement, so it is
        // offered the shipped rows only while that is the theme's own.
        $one = (array) \App\Support\LayoutInventory::units("category:{$category->id}");
        $this->assertSame(['system.category_subcategories', 'system.category_products'], array_column($one['category_main'][0]['cols'][0], 'widget'));

        \App\Models\LayoutPlacement::create(['theme' => 'sample', 'page_type' => 'category', 'region' => 'category_main', 'widget' => 'system.category_products', 'sort_order' => 0, 'is_enabled' => true]);
        $this->assertArrayNotHasKey('category_main', (array) \App\Support\LayoutInventory::units("category:{$category->id}"));
    }

    public function test_the_category_page_publishes_its_data_for_its_pieces(): void
    {
        $category = \App\Models\Category::create(['store_id' => 1, 'name' => 'Demo Pedals', 'slug' => 'demo-pedals', 'is_active' => true]);

        $this->get(route('category.show', $category->slug))->assertOk();

        $this->assertTrue(\App\Support\PageContext::get('category')->is($category));
        $this->assertTrue(\App\Support\PageContext::has('products'));
        $this->assertTrue(\App\Support\PageContext::has('subcategories'));
    }
}
