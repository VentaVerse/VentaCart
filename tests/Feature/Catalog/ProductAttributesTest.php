<?php

namespace Tests\Feature\Catalog;

use App\Models\Attribute;
use App\Models\AttributeGroup;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Support\ProductAttributes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Product attributes: groups of named facts with one value per product
 * (origin, weight, lens width). Edited on the product form, shown on
 * cards when the attribute says so, filterable on category pages, and
 * listed on the product page under their groups.
 */
class ProductAttributesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
    }

    private function attribute(string $name, string $group = 'Details', array $extra = []): Attribute
    {
        $groupModel = AttributeGroup::firstOrCreate(['name' => $group]);

        return Attribute::create(['attribute_group_id' => $groupModel->id, 'name' => $name] + $extra);
    }

    private function product(string $name, array $values = []): Product
    {
        $product = Product::create(['store_id' => 1, 'name' => $name, 'slug' => \Illuminate\Support\Str::slug($name), 'price' => 100, 'quantity' => 5, 'is_active' => true, 'date_available' => now()->subDay()]);
        foreach ($values as $attributeId => $value) {
            $product->productAttributes()->create(['attribute_id' => $attributeId, 'value' => $value]);
        }

        return $product;
    }

    public function test_attributes_are_managed_in_the_catalog(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.attributes.index'))->assertOk()->assertSee('Attributes');
        $this->post(route('admin.attributes.store'), ['name' => 'Origin', 'group' => 'Coffee', 'sort_order' => 1, 'is_filterable' => '1', 'show_on_card' => '1'])
            ->assertRedirect(route('admin.attributes.index'));

        $origin = Attribute::where('name', 'Origin')->firstOrFail();
        $this->assertSame('Coffee', $origin->group->name);
        $this->assertTrue($origin->is_filterable);
        $this->assertTrue($origin->show_on_card);
        $this->assertSame('origin', $origin->slug);

        $this->put(route('admin.attributes.update', $origin), ['name' => 'Origin', 'group' => 'Beans', 'sort_order' => 2])->assertRedirect();
        $origin->refresh();
        $this->assertSame('Beans', $origin->group->name);
        $this->assertFalse($origin->show_on_card, 'An unchecked box turns the flag off.');
        $this->assertSame(0, AttributeGroup::where('name', 'Coffee')->count(), 'A group left empty is removed.');

        $this->post(route('admin.attributes.store'), ['name' => 'Origin', 'group' => 'Beans'])->assertSessionHasErrors('name');

        $this->delete(route('admin.attributes.destroy', $origin))->assertRedirect();
        $this->assertSame(0, Attribute::count());
        $this->assertSame(0, AttributeGroup::count());
    }

    public function test_the_product_form_saves_one_value_per_attribute(): void
    {
        $this->actingAs(User::factory()->create());
        $origin = $this->attribute('Origin');
        $weight = $this->attribute('Weight');
        $product = $this->product('Demo Beans', [$weight->id => '250 g']);

        $this->get(route('admin.products.edit', $product))->assertOk()->assertSee('name="attribute_values[' . $origin->id . ']"', false);

        $this->put(route('admin.products.update', $product), [
            'store_id' => 1, 'name' => 'Demo Beans', 'price' => 100, 'is_active' => '1',
            'attribute_values' => [$origin->id => ' Benguet ', $weight->id => ''],
        ])->assertRedirect();

        $values = $product->productAttributes()->pluck('value', 'attribute_id')->all();
        $this->assertSame([$origin->id => 'Benguet'], $values, 'Values are trimmed and an emptied one is removed.');

        $this->put(route('admin.products.update', $product), [
            'store_id' => 1, 'name' => 'Demo Beans', 'price' => 100, 'is_active' => '1',
            'attribute_values' => [999999 => 'Nowhere'],
        ])->assertSessionHasErrors('attribute_values');
    }

    public function test_cards_carry_the_attributes_marked_for_cards_in_order(): void
    {
        $origin = $this->attribute('Origin', 'Details', ['show_on_card' => true, 'sort_order' => 2]);
        $process = $this->attribute('Process', 'Details', ['show_on_card' => true, 'sort_order' => 1]);
        $notes = $this->attribute('Notes', 'Details', ['show_on_card' => false]);
        $product = $this->product('Demo Roast', [$origin->id => 'Sagada', $process->id => 'Washed', $notes->id => 'Cocoa']);

        $chips = ProductAttributes::card($product->fresh());
        $this->assertSame([['name' => 'Process', 'value' => 'Washed'], ['name' => 'Origin', 'value' => 'Sagada']], $chips);

        // Listings load them with the products, so a grid costs one query.
        $loaded = Product::with(ProductAttributes::CARD_RELATION)->find($product->id);
        $this->assertTrue($loaded->relationLoaded(ProductAttributes::CARD_RELATION));
        $this->assertSame($chips, ProductAttributes::card($loaded));
    }

    public function test_category_pages_filter_by_attribute_values(): void
    {
        $origin = $this->attribute('Origin', 'Details', ['is_filterable' => true]);
        $hidden = $this->attribute('Lot number', 'Details', ['is_filterable' => false]);
        $category = Category::create(['store_id' => 1, 'name' => 'Coffee', 'slug' => 'coffee', 'is_active' => true]);
        foreach (['Demo Sagada' => 'Sagada', 'Demo Benguet' => 'Benguet', 'Demo Kalinga' => 'Kalinga'] as $name => $place) {
            $this->product($name, [$origin->id => $place, $hidden->id => 'L-1'])->categories()->attach($category->id);
        }

        $response = $this->get(route('category.show', $category->slug))->assertOk();
        $filters = $response->viewData('availableAttributes');
        $this->assertCount(1, $filters, 'Only filterable attributes are offered.');
        $this->assertSame('origin', $filters[0]['slug']);
        $this->assertSame(['Benguet', 'Kalinga', 'Sagada'], array_column($filters[0]['values'], 'value'));

        $response = $this->get(route('category.show', ['category' => $category->slug, 'attr' => ['origin' => ['Sagada', 'Kalinga']]]))->assertOk();
        $names = $response->viewData('products')->pluck('name')->sort()->values()->all();
        $this->assertSame(['Demo Kalinga', 'Demo Sagada'], $names);

        // A filter on an attribute that is not filterable is ignored.
        $all = $this->get(route('category.show', ['category' => $category->slug, 'attr' => ['lot-number' => ['nope']]]))->viewData('products');
        $this->assertCount(3, $all);
    }

    public function test_the_product_page_lists_attributes_under_their_groups(): void
    {
        $origin = $this->attribute('Origin', 'Farm', ['sort_order' => 1]);
        $altitude = $this->attribute('Altitude', 'Farm', ['sort_order' => 2]);
        $weight = $this->attribute('Weight', 'Pack');
        $product = $this->product('Demo Single Origin', [$altitude->id => '1,600 m', $origin->id => 'Sagada', $weight->id => '250 g']);

        $response = $this->get(route('product.show', $product->slug))->assertOk();
        $this->assertSame([
            ['name' => 'Farm', 'attributes' => [['name' => 'Origin', 'value' => 'Sagada'], ['name' => 'Altitude', 'value' => '1,600 m']]],
            ['name' => 'Pack', 'attributes' => [['name' => 'Weight', 'value' => '250 g']]],
        ], $response->viewData('attributeGroups'));
        $response->assertSee('1,600 m');
    }
}
