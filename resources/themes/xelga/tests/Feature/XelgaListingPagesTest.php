<?php

namespace Themes\Xelga\Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeGroup;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductSpecial;
use App\Models\Setting;
use App\Models\Store;
use App\Models\Tag;
use App\Services\ThemeService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Themes\Xelga\Tests\XelgaTestHelpers;
use Themes\Xelga\Documents\Defaults;
use Themes\Xelga\Documents\Document;
use Themes\Xelga\Rendering\Storefront;

require_once __DIR__ . '/../XelgaTestHelpers.php';

/**
 * The listing pages (category, brand, all brands, search) on real data:
 * products, filters, sort and pages all come from core's controllers, the
 * customer-group visibility holds, and every empty state says something
 * useful.
 */
class XelgaListingPagesTest extends TestCase
{
    use RefreshDatabase;
    use XelgaTestHelpers;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installXelga();
        $this->store = Store::firstOrCreate(['code' => 'demo'], ['name' => 'Demo Store']);
        // Core's visibility scope steps aside for console runs; these tests are storefront requests.
        (new \ReflectionProperty(Application::class, 'isRunningInConsole'))->setValue($this->app, false);
    }

    private function product(string $name, array $extra = []): Product
    {
        return Product::create($extra + [
            'store_id' => $this->store->id, 'name' => $name, 'slug' => Str::slug($name) . '-' . uniqid(),
            'sku' => 'DEMO-' . Str::upper(Str::random(6)), 'price' => 1250, 'quantity' => 5, 'is_active' => true,
        ]);
    }

    private function category(string $name, array $extra = []): Category
    {
        return Category::create($extra + ['store_id' => $this->store->id, 'name' => $name, 'slug' => Str::slug($name), 'is_active' => true]);
    }

    private function page(string $url): TestResponse
    {
        Storefront::forgetRequestState();

        return $this->get($url)->assertOk();
    }

    /** Publish a template with one section's settings changed from the shipped default. */
    private function publishWith(string $handle, string $sectionId, array $settings): void
    {
        $content = Defaults::content($handle);
        $content['sections'][$sectionId]['settings'] = array_merge($content['sections'][$sectionId]['settings'], $settings);
        $doc = new Document(['handle' => $handle, 'kind' => 'template', 'page_type' => $handle]);
        $doc->forceFill(['published' => $content, 'published_version' => 1])->save();
    }

    private function between(string $html, string $from, string $to): string
    {
        $start = strpos($html, $from);
        $this->assertNotFalse($start, "Missing {$from}");

        return substr($html, $start, strpos($html, $to, $start) - $start);
    }

    public function test_a_category_page_draws_its_products_subcategories_and_filters_from_core(): void
    {
        $clothing = $this->category('Clothing', ['description' => '<p>Linen for the hot months.</p>']);
        $this->category('Dresses', ['parent_id' => $clothing->id]);
        $this->category('Archive', ['parent_id' => $clothing->id, 'is_active' => false]);
        $brand = Brand::create(['store_id' => $this->store->id, 'name' => 'Northwind', 'slug' => 'northwind', 'is_active' => true]);
        $tag = Tag::create(['name' => 'Workwear', 'slug' => 'workwear']);
        $shirt = $this->product('Linen Shirt', ['brand_id' => $brand->id, 'price' => 2000]);
        $shirt->categories()->attach($clothing->id);
        $shirt->tags()->attach($tag->id);
        $group = AttributeGroup::create(['name' => 'Fabric and care']);
        $fabric = Attribute::create(['attribute_group_id' => $group->id, 'name' => 'Fabric', 'slug' => 'fabric', 'is_filterable' => true]);
        ProductAttribute::create(['product_id' => $shirt->id, 'attribute_id' => $fabric->id, 'value' => 'Linen']);

        $html = $this->page('/category/clothing')
            ->assertSee('data-section-type="category-main"', false)
            ->assertSee('Clothing')->assertSee('Linen for the hot months.')
            ->assertSee('Linen Shirt')->assertSee(money(2000))
            ->assertSee('Dresses')->assertDontSee('Archive')
            ->assertSee('name="brand[]" value="' . $brand->id . '"', false)
            ->assertSee('name="tag[]" value="' . $tag->id . '"', false)
            ->assertSee('name="attr[fabric][]" value="Linen"', false)
            ->assertSee('name="in_stock"', false)->assertSee('name="on_sale"', false)
            ->assertSee('1 product')
            ->getContent();

        $this->assertStringContainsString('data-section-type="breadcrumbs"', $html);
        // The products-per-page note is only for the editor.
        $this->assertStringNotContainsString('products per page', $html);
    }

    public function test_a_product_kept_for_one_customer_group_is_listed_only_for_that_group(): void
    {
        $resellers = CustomerGroup::create(['store_id' => $this->store->id, 'name' => 'Resellers']);
        CustomerGroup::create(['store_id' => $this->store->id, 'name' => 'Retail', 'is_default' => true]);
        $clothing = $this->category('Clothing');
        $public = $this->product('Public Linen Shirt');
        $kept = $this->product('Reseller Bundle');
        $kept->customerGroups()->attach($resellers->id);
        $public->categories()->attach($clothing->id);
        $kept->categories()->attach($clothing->id);

        $this->page('/category/clothing')->assertSee('Public Linen Shirt')->assertDontSee('Reseller Bundle');
        $this->page('/search?q=Bundle')->assertDontSee('Reseller Bundle')->assertSee('Nothing found for');

        $customer = Customer::create(['store_id' => $this->store->id, 'customer_group_id' => $resellers->id, 'first_name' => 'Maria', 'last_name' => 'Santos',
            'email' => 'maria@example.com', 'password' => bcrypt('secret-password'), 'is_active' => true]);
        $this->actingAs($customer, 'customer');

        $this->page('/category/clothing')->assertSee('Reseller Bundle');
        $this->page('/search?q=Bundle')->assertSee('Reseller Bundle');
    }

    public function test_filters_and_sort_reach_core_and_can_be_taken_off_one_by_one(): void
    {
        $clothing = $this->category('Clothing');
        $sale = $this->product('Sale Trouser', ['price' => 3000]);
        $full = $this->product('Full Price Coat', ['price' => 5000]);
        ProductSpecial::create(['product_id' => $sale->id, 'price_type' => 'fixed', 'value' => 2400, 'date_end' => now()->addWeek()]);
        $sale->categories()->attach($clothing->id);
        $full->categories()->attach($clothing->id);

        $this->page('/category/clothing?on_sale=1')
            ->assertSee('Sale Trouser')->assertDontSee('Full Price Coat')
            ->assertSee(money(2400))
            ->assertSee('Remove filter On sale', false)
            ->assertSee('name="on_sale" value="1" checked', false);

        $this->page('/category/clothing?sort=price_desc')
            ->assertSeeInOrder(['Full Price Coat', 'Sale Trouser'])
            ->assertSee('<option value="price_desc" selected>', false)
            ->assertSee('<input type="hidden" name="sort" value="price_desc">', false);

        $this->page('/category/clothing?min_price=4000')->assertSee('Full Price Coat')->assertDontSee('Sale Trouser');
    }

    public function test_nothing_matching_the_filters_says_so_and_offers_to_clear_them(): void
    {
        $clothing = $this->category('Clothing');
        $this->product('Linen Shirt')->categories()->attach($clothing->id);

        $this->page('/category/clothing?on_sale=1')
            ->assertSee('Nothing matches these filters')
            ->assertSee('Clear all filters');

        $this->category('Empty Shelf');
        $this->page('/category/empty-shelf')->assertSee('No products here yet');
    }

    public function test_filters_the_store_switched_off_in_core_do_not_show(): void
    {
        $clothing = $this->category('Clothing');
        $this->product('Linen Shirt')->categories()->attach($clothing->id);
        Setting::updateOrCreate(['group' => 'filters', 'key' => 'on_sale_enabled'], ['value' => '0']);
        Setting::updateOrCreate(['group' => 'filters', 'key' => 'price_enabled'], ['value' => '0']);
        app(ThemeService::class)->clearCache();
        $this->app->forgetInstance(ThemeService::class);

        $this->page('/category/clothing')
            ->assertDontSee('name="on_sale"', false)
            ->assertDontSee('name="min_price"', false)
            ->assertSee('name="in_stock"', false);
    }

    public function test_pages_come_from_cores_paginator_as_numbers_or_load_more(): void
    {
        $clothing = $this->category('Clothing');
        Setting::updateOrCreate(['group' => 'category_page', 'key' => 'products_per_page'], ['value' => '4']);
        app(ThemeService::class)->clearCache();
        $this->app->forgetInstance(ThemeService::class);
        foreach (range(1, 6) as $i) {
            $this->product('Shirt ' . $i)->categories()->attach($clothing->id);
        }

        $this->page('/category/clothing')
            ->assertSee('aria-label="Page 2"', false)
            ->assertSee('Showing 1 to 4 of 6 products')
            ->assertSee('rel="next"', false);

        $this->page('/category/clothing?page=2')->assertSee('Showing 5 to 6 of 6 products');

        $this->publishWith('category', 'category-main-1', ['pagination' => 'load-more']);
        $this->page('/category/clothing')
            ->assertSee('data-load-more', false)
            ->assertSee('page=2', false)
            ->assertDontSee('aria-label="Page 2"', false);
    }

    public function test_a_category_description_is_cleaned_when_it_is_printed(): void
    {
        $category = $this->category('Clothing');
        // Written straight to the table, as an import or an older version could have left it.
        \Illuminate\Support\Facades\DB::table('categories')->where('id', $category->id)->update([
            'description' => '<p>Linen for the hot months.</p><img src="x" onerror="alert(1)"><script>alert(2)</script><a href="javascript:alert(3)">more</a>',
        ]);

        $html = $this->page('/category/clothing')->assertSee('Linen for the hot months.')->getContent();
        $main = $this->between($html, '<!--xelga:category-main-1-->', '<!--/xelga:category-main-1-->');

        $this->assertStringNotContainsString('onerror', $main);
        $this->assertStringNotContainsString('<script>alert(2)', $main);
        $this->assertStringNotContainsString('javascript:', $main);
    }

    public function test_a_category_description_prints_as_html_however_it_was_stored(): void
    {
        $bass = $this->category('Bass Guitars', ['description' => '<p>Four and five strings.</p>']);
        $this->product('Demo Bass')->categories()->attach($bass->id);

        $html = $this->page('/category/bass-guitars')->getContent();
        $main = $this->between($html, '<!--xelga:category-main-1-->', '<!--/xelga:category-main-1-->');
        $this->assertStringContainsString('<p>Four and five strings.</p>', $main);
        $this->assertStringNotContainsString('&lt;p&gt;', $main);

        // Escaped tags, as an import may have written them, still print as a paragraph.
        \Illuminate\Support\Facades\DB::table('categories')->where('id', $bass->id)->update(['description' => '&lt;p&gt;Bass Guitars&lt;/p&gt;&lt;script&gt;alert(1)&lt;/script&gt;']);
        $html = $this->page('/category/bass-guitars')->getContent();
        $main = $this->between($html, '<!--xelga:category-main-1-->', '<!--/xelga:category-main-1-->');
        $this->assertStringContainsString('<p>Bass Guitars</p>', $main);
        $this->assertStringNotContainsString('&lt;p&gt;', $main);
        $this->assertStringNotContainsString('alert(1)', $main);
    }

    public function test_a_brand_description_stored_with_escaped_tags_prints_as_html(): void
    {
        $brand = Brand::create(['store_id' => $this->store->id, 'name' => 'Northwind', 'slug' => 'northwind', 'is_active' => true]);
        \Illuminate\Support\Facades\DB::table('brands')->where('id', $brand->id)->update(['description' => '&lt;p&gt;Laptops for the road.&lt;/p&gt;']);
        $this->product('Northwind Air 13', ['brand_id' => $brand->id]);

        $this->page('/brand/northwind')->assertSee('<p>Laptops for the road.</p>', false)->assertDontSee('&lt;p&gt;', false);
    }

    public function test_a_brand_page_draws_the_brand_and_its_products(): void
    {
        $brand = Brand::create(['store_id' => $this->store->id, 'name' => 'Northwind', 'slug' => 'northwind', 'is_active' => true,
            'description' => '<p>Laptops for the road.</p><script>alert(1)</script>']);
        $this->product('Northwind Air 13', ['brand_id' => $brand->id]);
        $this->product('Other Laptop');

        $html = $this->page('/brand/northwind')
            ->assertSee('data-section-type="brand-main"', false)
            ->assertSee('Laptops for the road.')->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('1 product')
            // Core's brand page takes no brand filter.
            ->assertDontSee('name="brand[]"', false)
            ->getContent();

        // The brand's own listing; the row of other products below it may show anything.
        $main = $this->between($html, '<!--xelga:brand-main-1-->', '<!--/xelga:brand-main-1-->');
        $this->assertStringContainsString('Northwind Air 13', $main);
        $this->assertStringNotContainsString('Other Laptop', $main);
    }

    public function test_the_brand_list_shows_brands_with_products_and_their_counts(): void
    {
        $north = Brand::create(['store_id' => $this->store->id, 'name' => 'Northwind', 'slug' => 'northwind', 'is_active' => true]);
        Brand::create(['store_id' => $this->store->id, 'name' => 'Empty Label', 'slug' => 'empty-label', 'is_active' => true]);
        Brand::create(['store_id' => $this->store->id, 'name' => 'Retired Label', 'slug' => 'retired', 'is_active' => false]);
        $this->product('Northwind Air 13', ['brand_id' => $north->id]);
        $this->product('Northwind Studio 14', ['brand_id' => $north->id]);

        $this->page('/brand')
            ->assertSee('data-section-type="brand-list-main"', false)
            ->assertSee('Northwind')->assertSee('2 products')
            ->assertSee(route('brand.show', 'northwind'), false)
            // Core lists only brands with products to show.
            ->assertDontSee('Empty Label')->assertDontSee('Retired Label');
    }

    public function test_an_empty_brand_list_says_so(): void
    {
        $this->page('/brand')->assertSee('No brands yet');
    }

    public function test_search_shows_results_with_core_sorts_and_no_results_with_suggestions(): void
    {
        $this->product('Linen Shirt', ['price' => 2000]);
        $this->product('Linen Trouser', ['price' => 3000]);

        $this->page('/search?q=linen&sort=price_desc')
            ->assertSee('data-section-type="search-main"', false)
            ->assertSee('2 products for "linen"')
            ->assertSeeInOrder(['Linen Trouser', 'Linen Shirt'])
            ->assertSee('Best match')
            ->assertSee('<input type="hidden" name="q" value="linen">', false)
            ->assertDontSee('name="min_price"', false);

        $this->page('/search?q=velvet')
            ->assertSee('Nothing found for')
            ->assertSee(route('search.results', ['q' => 'Tablets']), false)
            ->assertSee('Browse brands');
    }

    public function test_a_search_word_is_escaped_wherever_it_is_shown(): void
    {
        $this->page('/search?q=' . urlencode('<script>alert(1)</script>'))
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }
}
