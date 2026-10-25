<?php

namespace Tests\Feature\Catalog;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\DemoContent;
use App\Models\Product;
use App\Models\Store;
use App\Services\DemoContentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A template's demo products may carry more than a name and a picture:
 * extra pictures, the categories they sit in and attribute values, so the
 * cards and filters a template switches on have something to show. What
 * the demo made, it takes away again; anything the merchant made stays.
 */
class DemoContentExtrasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
    }

    private function preset(): array
    {
        return ['products' => [[
            'name' => 'Demo Beans', 'price' => 620, 'image' => 'demo/beans.jpg',
            'images' => ['demo/beans-2.jpg', '../../.env', 'demo/beans-3.jpg'],
            'categories' => ['Coffee', 'Single origin'],
            'attributes' => ['Origin' => 'Sagada', 'Weight' => '250 g', ['group' => 'Roast', 'name' => 'Level', 'value' => 'Light', 'card' => true, 'filter' => true]],
        ]]];
    }

    public function test_demo_products_carry_pictures_categories_and_attributes(): void
    {
        app(DemoContentService::class)->install('demo', $this->preset());

        $product = Product::where('name', 'Demo Beans')->firstOrFail();
        $this->assertSame(['demo/beans-2.jpg', 'demo/beans-3.jpg'], $product->images()->pluck('path')->all(), 'A path climbing out of storage is skipped.');
        $this->assertEqualsCanonicalizing(['Coffee', 'Single origin'], $product->categories()->pluck('name')->all());
        $this->assertSame(['Level' => 'Light', 'Origin' => 'Sagada', 'Weight' => '250 g'], $product->productAttributes()->with('attribute')->get()->mapWithKeys(fn ($v) => [$v->attribute->name => $v->value])->sortKeys()->all());
        $level = Attribute::where('name', 'Level')->firstOrFail();
        $this->assertTrue($level->show_on_card);
        $this->assertTrue($level->is_filterable);
        $this->assertSame('Roast', $level->group->name);
    }

    public function test_clearing_takes_away_what_the_demo_made_and_keeps_the_merchants(): void
    {
        $mine = Category::create(['store_id' => 1, 'name' => 'Coffee', 'slug' => 'coffee', 'is_active' => true]);
        app(DemoContentService::class)->install('demo', $this->preset());
        $this->assertSame(2, Category::count());

        app(DemoContentService::class)->clear();

        $this->assertSame(0, Product::withoutGlobalScopes()->count());
        $this->assertSame(['Coffee'], Category::pluck('name')->all(), 'The merchant\'s own category stays; the one the demo made goes.');
        $this->assertSame($mine->id, Category::value('id'));
        $this->assertSame(0, Attribute::count(), 'Attributes the demo made go with it.');
        $this->assertSame(0, DemoContent::count());
    }

    public function test_a_theme_can_add_to_each_demo_product(): void
    {
        $seen = [];
        DemoContentService::afterProduct(function (Product $product, array $definition, string $slug) use (&$seen) {
            $seen[] = [$product->name, $definition['extra'] ?? null, $slug];
        });
        try {
            app(DemoContentService::class)->install('demo', ['products' => [['name' => 'Demo Mug', 'price' => 10, 'extra' => 'blue']]]);
        } finally {
            DemoContentService::forgetAfterProduct();
        }

        $this->assertSame([['Demo Mug', 'blue', 'demo']], $seen);
    }
}
