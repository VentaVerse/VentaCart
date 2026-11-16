<?php

namespace Tests\Feature\Storefront;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\ThemeService;
use App\Support\ProductListing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Settings > Catalog "Products per page" and "Default sort" drive the
 * category, brand and search pages, a sort in the URL still wins, and a
 * stored value the storefront cannot use falls back instead of failing.
 */
class ProductListingSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    private Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create(['store_id' => 1, 'name' => 'Demo Category', 'slug' => 'demo-category', 'is_active' => true]);
        $this->brand = Brand::create(['store_id' => 1, 'name' => 'Demo Brand', 'slug' => 'demo-brand', 'is_active' => true]);

        // Price, name and date added each give a different order, so every
        // sort is told apart by the names it returns.
        $this->product('Bravo Demo', 300, now()->subDays(1));
        $this->product('Alpha Demo', 200, now()->subDays(3));
        $this->product('Charlie Demo', 100, now()->subDays(2));
    }

    private function product(string $name, int $price, \DateTimeInterface $available): Product
    {
        $product = Product::create([
            'store_id' => 1, 'name' => $name, 'slug' => Str::slug($name),
            'sku' => 'DEMO-' . Str::upper(Str::random(6)), 'price' => $price, 'quantity' => 5,
            'is_active' => true, 'date_available' => $available, 'brand_id' => $this->brand->id,
        ]);
        $product->categories()->attach($this->category->id);

        return $product;
    }

    private function store(string $key, ?string $value): void
    {
        app(ThemeService::class)->set('category_page', $key, $value);
    }

    /** @return array<string, string> page => url */
    private function listingPages(array $query = []): array
    {
        return [
            'category' => route('category.show', ['category' => $this->category->slug] + $query),
            'brand' => route('brand.show', ['brand' => $this->brand->slug] + $query),
            'search' => route('search.results', ['q' => 'Demo'] + $query),
        ];
    }

    /** @return list<string> */
    private function names(string $url): array
    {
        return $this->get($url)->assertOk()->viewData('products')->pluck('name')->all();
    }

    public function test_the_default_sort_applies_when_none_is_requested(): void
    {
        $this->store('default_sort', 'price_asc');

        foreach (['category', 'brand'] as $page) {
            $url = $this->listingPages()[$page];
            $this->assertSame(['Charlie Demo', 'Alpha Demo', 'Bravo Demo'], $this->names($url), $page);
            $this->get($url)->assertViewHas('sort', 'price_asc');
        }
    }

    public function test_a_requested_sort_wins_over_the_default(): void
    {
        $this->store('default_sort', 'price_asc');

        foreach ($this->listingPages(['sort' => 'name_desc']) as $page => $url) {
            $this->assertSame(['Charlie Demo', 'Bravo Demo', 'Alpha Demo'], $this->names($url), $page);
        }
    }

    public function test_an_unknown_requested_sort_falls_back_to_the_default(): void
    {
        $this->store('default_sort', 'price_desc');

        foreach (['category', 'brand'] as $page) {
            $url = $this->listingPages(['sort' => 'id; drop table products'])[$page];
            $this->assertSame(['Bravo Demo', 'Alpha Demo', 'Charlie Demo'], $this->names($url), $page);
            $this->get($url)->assertViewHas('sort', 'price_desc');
        }

        $url = route('category.show', ['category' => $this->category->slug, 'sort' => ['price_asc']]);
        $this->get($url)->assertOk()->assertViewHas('sort', 'price_desc');
    }

    public function test_an_invalid_stored_default_sort_falls_back_to_date_added(): void
    {
        foreach (['default', 'random', '', null] as $stored) {
            $this->store('default_sort', $stored);

            $this->assertSame(ProductListing::DEFAULT_SORT, ProductListing::defaultSort());
            $this->assertSame(['Bravo Demo', 'Charlie Demo', 'Alpha Demo'], $this->names($this->listingPages()['category']));
            $this->get($this->listingPages()['brand'])->assertOk()->assertViewHas('sort', 'date_added');
        }
    }

    public function test_search_keeps_relevance_as_its_default_and_accepts_listing_sorts(): void
    {
        $this->store('default_sort', 'price_asc');

        $this->get(route('search.results', ['q' => 'Demo']))->assertOk()->assertViewHas('sort', 'relevance');
        $this->get(route('search.results', ['q' => 'Demo', 'sort' => 'relevance']))->assertOk()->assertViewHas('sort', 'relevance');
        $this->get(route('search.results', ['q' => 'Demo', 'sort' => 'bogus']))->assertOk()->assertViewHas('sort', 'relevance');
        $this->assertSame(['Charlie Demo', 'Alpha Demo', 'Bravo Demo'], $this->names(route('search.results', ['q' => 'Demo', 'sort' => 'price_asc'])));
    }

    public function test_products_per_page_applies_on_category_brand_and_search(): void
    {
        $this->store('products_per_page', '5');

        foreach ($this->listingPages() as $page => $url) {
            $this->assertSame(5, $this->get($url)->assertOk()->viewData('products')->perPage(), $page);
        }
    }

    public function test_products_per_page_is_clamped_and_falls_back(): void
    {
        foreach (['500' => 60, '1' => 4, '0' => 4, '-3' => 4, '24' => 24, 'abc' => 12, '' => 12] as $stored => $expected) {
            $this->store('products_per_page', (string) $stored);

            $this->assertSame($expected, ProductListing::perPage(), "stored '{$stored}'");
            foreach ($this->listingPages() as $page => $url) {
                $this->assertSame($expected, $this->get($url)->assertOk()->viewData('products')->perPage(), "{$page}, stored '{$stored}'");
            }
        }
    }

    public function test_a_per_page_value_in_the_url_is_ignored(): void
    {
        $this->store('products_per_page', '8');

        foreach ($this->listingPages(['per_page' => '1000', 'limit' => '1000']) as $page => $url) {
            $this->assertSame(8, $this->get($url)->assertOk()->viewData('products')->perPage(), $page);
        }
    }

    public function test_the_storefront_dropdown_and_the_admin_select_offer_the_same_sorts(): void
    {
        foreach (array_keys(ProductListing::SORTS) as $key) {
            $this->get($this->listingPages()['category'])->assertSee('value="' . $key . '"', false);
        }

        $this->actingAs(User::factory()->create());
        $admin = $this->get(route('admin.settings.section', 'catalog'))->assertOk();
        foreach (ProductListing::SORTS as $key => $label) {
            $admin->assertSee('value="' . $key . '"', false)->assertSee($label);
        }
        $admin->assertDontSee('value="default"', false);
    }

    public function test_the_admin_saves_valid_listing_settings(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.settings.update'), [
            '_redirect' => route('admin.settings.section', 'catalog'),
            'groups' => ['category_page' => ['products_per_page' => '24', 'default_sort' => 'name_asc']],
        ])->assertRedirect(route('admin.settings.section', 'catalog'))->assertSessionHasNoErrors();

        $this->assertSame('24', Setting::where('group', 'category_page')->where('key', 'products_per_page')->value('value'));
        $this->assertSame('name_asc', Setting::where('group', 'category_page')->where('key', 'default_sort')->value('value'));
        $this->assertSame(['Alpha Demo', 'Bravo Demo', 'Charlie Demo'], $this->names($this->listingPages()['category']));
    }

    public function test_the_admin_refuses_a_sort_or_page_size_the_storefront_cannot_use(): void
    {
        $this->actingAs(User::factory()->create());
        $this->store('products_per_page', '12');
        $this->store('default_sort', 'price_asc');

        foreach ([
            ['default_sort' => 'default'],
            ['default_sort' => 'relevance'],
            ['products_per_page' => '61'],
            ['products_per_page' => '3'],
            ['products_per_page' => '12.5'],
            ['products_per_page' => 'twelve'],
        ] as $values) {
            $field = 'groups.category_page.' . array_key_first($values);
            $this->post(route('admin.settings.update'), [
                'groups' => ['category_page' => $values + ['default_sort' => 'name_asc'], 'badges' => ['new_count' => '9']],
            ])->assertRedirect()->assertSessionHasErrors($field);
        }

        // Nothing in the rejected saves was written, not even the valid parts.
        $this->assertSame('12', Setting::where('group', 'category_page')->where('key', 'products_per_page')->value('value'));
        $this->assertSame('price_asc', Setting::where('group', 'category_page')->where('key', 'default_sort')->value('value'));
        $this->assertNull(Setting::where('group', 'badges')->where('key', 'new_count')->value('value'));
    }
}
