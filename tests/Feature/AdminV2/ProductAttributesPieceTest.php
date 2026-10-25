<?php

namespace Tests\Feature\AdminV2;

use App\Models\Attribute;
use App\Models\AttributeGroup;
use App\Models\LayoutPlacement;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Support\ThemeContract;
use App\Support\Widgets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesSampleTheme;
use Tests\TestCase;

/**
 * The product's specifications as a placeable product page piece. Core
 * draws a plain version, so a theme that declares the product page region
 * may draw its own but does not have to.
 */
class ProductAttributesPieceTest extends TestCase
{
    use RefreshDatabase, UsesSampleTheme;

    private function productWithAttributes(): Product
    {
        $product = Product::create(['store_id' => 1, 'name' => 'Demo Kettle', 'slug' => 'demo-kettle', 'sku' => 'DEMO-001', 'price' => 1200, 'quantity' => 3, 'is_active' => true]);
        $group = AttributeGroup::create(['name' => 'Build', 'sort_order' => 0]);
        $attribute = Attribute::create(['attribute_group_id' => $group->id, 'name' => 'Material', 'slug' => 'material', 'sort_order' => 0]);
        ProductAttribute::create(['product_id' => $product->id, 'attribute_id' => $attribute->id, 'value' => 'Stainless steel']);

        return $product;
    }

    public function test_the_piece_is_offered_on_the_product_page_only(): void
    {
        $spec = Widgets::get('system.product_attributes');

        $this->assertNotNull($spec);
        $this->assertTrue($spec['system']);
        $this->assertTrue($spec['optional']);
        $this->assertSame(['product_main'], $spec['regions']);
        $this->assertSame(['product'], $spec['pages']);
        $this->assertSame('widgets.system.product_attributes', $spec['view']);
    }

    public function test_a_theme_need_not_draw_it_to_keep_the_contract(): void
    {
        $this->makeSampleTheme();

        $this->assertFileDoesNotExist(resource_path('themes/sample/views/widgets/system/product_attributes.blade.php'));
        $this->assertSame([], ThemeContract::check(resource_path('themes/sample')));
    }

    public function test_core_draws_the_attribute_groups_when_the_theme_does_not(): void
    {
        $this->installSampleTheme(activate: true);
        $product = $this->productWithAttributes();
        LayoutPlacement::create(['theme' => 'sample', 'page_type' => 'product', 'region' => 'product_main', 'widget' => 'system.product_attributes', 'sort_order' => 0, 'is_enabled' => true]);

        $this->get(route('product.show', $product->slug))
            ->assertOk()
            ->assertSeeInOrder(['product-attributes__table', 'Build', 'Material', 'Stainless steel'], false);
    }

    public function test_the_default_theme_has_its_own_view(): void
    {
        $this->assertFileExists(resource_path('themes/venta/views/widgets/system/product_attributes.blade.php'));

        $this->productWithAttributes();
        \App\Support\PageContext::set(['attributeGroups' => \App\Support\ProductAttributes::groups(Product::first())]);
        view()->getFinder()->prependLocation(resource_path('themes/venta/views'));

        $html = view(Widgets::view('system.product_attributes'))->render();

        $this->assertStringContainsString('product-attributes__title', $html);
        $this->assertStringContainsString('Stainless steel', $html);
    }
}
