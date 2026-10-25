<?php

namespace Tests\Feature\AdminV2;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\RowStyle;
use App\Support\Widgets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A product grid or product tab narrowed to one category branch or one
 * brand, and the subtitle and "view all" link a block's title may carry.
 */
class ProductBlockFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function category(string $name, ?int $parent = null, bool $active = true): Category
    {
        return Category::create(['store_id' => 1, 'parent_id' => $parent, 'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name), 'is_active' => $active]);
    }

    private function product(string $name, array $attrs = []): Product
    {
        return Product::create($attrs + ['store_id' => 1, 'name' => $name, 'slug' => \Illuminate\Support\Str::slug($name),
            'sku' => 'DEMO-' . \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(5)), 'price' => 100, 'quantity' => 5, 'is_active' => true]);
    }

    private function names(array $config): array
    {
        return Widgets::resolve('catalog.products', $config)['products']->pluck('name')->sort()->values()->all();
    }

    public function test_a_grid_shows_the_whole_category_branch_and_nothing_else(): void
    {
        $phones = $this->category('Phones');
        $cases = $this->category('Phone Cases', $phones->id);
        $laptops = $this->category('Laptops');
        $this->product('Demo Phone')->categories()->attach($phones->id);
        $this->product('Demo Case')->categories()->attach($cases->id);
        $this->product('Demo Laptop')->categories()->attach($laptops->id);

        $this->assertSame(['Demo Case', 'Demo Phone'], $this->names(['filter' => 'most_viewed', 'category' => 'phones']));
        $this->assertSame(['Demo Case'], $this->names(['category' => 'phone-cases']));
        $this->assertCount(3, $this->names(['category' => '']));
    }

    public function test_a_grid_narrows_to_one_brand(): void
    {
        $brand = Brand::create(['store_id' => 1, 'name' => 'Demo Brand', 'slug' => 'demo-brand', 'is_active' => true]);
        $this->product('Branded', ['brand_id' => $brand->id]);
        $this->product('Unbranded');

        $this->assertSame(['Branded'], $this->names(['brand' => 'demo-brand']));
    }

    public function test_a_category_or_brand_that_is_gone_shows_nothing_rather_than_everything(): void
    {
        $this->product('Anything');
        $this->category('Hidden', null, false);

        $this->assertSame([], $this->names(['category' => 'no-such-category']));
        $this->assertSame([], $this->names(['category' => 'hidden']));
        $this->assertSame([], $this->names(['brand' => 'no-such-brand']));
    }

    public function test_each_product_tab_may_take_its_own_category(): void
    {
        $audio = $this->category('Audio');
        $this->product('Demo Earbuds')->categories()->attach($audio->id);
        $this->product('Demo Charger');

        $tabs = Widgets::resolve('catalog.product_tabs', ['tabs' => [
            ['title' => 'Audio', 'filter' => 'new', 'category' => 'audio'],
            ['title' => 'Everything', 'filter' => 'new'],
        ]])['tabs'];

        $this->assertSame(['Demo Earbuds'], $tabs[0]['products']->pluck('name')->all());
        $this->assertCount(2, $tabs[1]['products']);
    }

    public function test_the_category_choices_name_categories_by_slug_with_depth(): void
    {
        $phones = $this->category('Phones');
        $this->category('Phone Cases', $phones->id);

        $field = collect(Widgets::schema('catalog.products'))->firstWhere('name', 'category');

        $this->assertSame(['' => 'Any category', 'phones' => 'Phones', 'phone-cases' => '- Phone Cases'], $field['options']);

        // A tab's category is a field inside a repeating row, and it reaches
        // the editor as a plain list as well.
        $tabs = collect(Widgets::schema('catalog.product_tabs'))->firstWhere('name', 'tabs');
        $this->assertSame($field['options'], $tabs['fields']['category']['options']);
        $this->assertNotFalse(json_encode(Widgets::schema('catalog.product_tabs')));
    }

    public function test_a_title_carries_a_subtitle_and_a_safe_link(): void
    {
        $extras = RowStyle::titleExtras(['subtitle' => "  New prices\nevery morning ", 'title_link_text' => 'See all', 'title_link_url' => '/specials']);
        $this->assertSame('New prices every morning', $extras['subtitle']);
        $this->assertSame(['text' => 'See all', 'url' => '/specials'], $extras['link']);

        foreach (['javascript:alert(1)', '//example.com/x', 'data:text/html,x', 'specials', '/a b', '/"><b>'] as $bad) {
            $this->assertNull(RowStyle::titleExtras(['title_link_text' => 'x', 'title_link_url' => $bad])['link'], $bad);
        }
        $this->assertSame('https://example.com/sale', RowStyle::titleExtras(['title_link_text' => 'x', 'title_link_url' => 'https://example.com/sale'])['link']['url']);
        $this->assertNull(RowStyle::titleExtras(['title_link_url' => '/specials'])['link'], 'A link needs its words.');
        $this->assertFalse(RowStyle::isStyled(['subtitle' => 'Words only']), 'Words around a title draw no wrapper.');
    }

    public function test_a_template_keeps_a_blocks_title_words_even_with_no_other_style(): void
    {
        $rows = \App\Models\LayoutTemplate::cleanRows([[
            'columns' => [8],
            'placements' => [['widget' => 'catalog.products', 'column' => 0, 'config' => [],
                'style' => ['subtitle' => 'New prices every morning', 'title_link_text' => 'See all', 'title_link_url' => '/specials']]],
        ]]);

        $style = $rows[0]['placements'][0]['style'];
        $this->assertSame('New prices every morning', $style['subtitle']);
        $this->assertSame('/specials', $style['title_link_url']);
    }
}
