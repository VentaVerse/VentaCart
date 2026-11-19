<?php

namespace Themes\Xelga\Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSpecial;
use App\Models\Store;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Themes\Xelga\Catalog\Catalog;
use Themes\Xelga\Catalog\CatalogLinter;
use Themes\Xelga\Documents\Document;
use Themes\Xelga\Rendering\Storefront;
use Themes\Xelga\Settings\LinkValue;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';

/**
 * The link beside a Featured products heading follows "Products to show":
 * "These products" opens the listing of the same products, and the listing
 * pages sort by best sellers and most viewed and filter to products on
 * sale, each choice keeping the other.
 */
class XelgaFeaturedLinkTest extends TestCase
{
    use RefreshDatabase;
    use XelgaTestHelpers;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installXelga();
        $this->store = Store::firstOrCreate(['code' => 'demo'], ['name' => 'Demo Store']);
        LinkValue::flush();
    }

    private function product(string $name, array $extra = []): Product
    {
        return Product::create($extra + [
            'store_id' => $this->store->id, 'name' => $name, 'slug' => Str::slug($name) . '-' . uniqid(),
            'sku' => 'DEMO-' . Str::upper(Str::random(6)), 'price' => 1250, 'quantity' => 5, 'is_active' => true,
        ]);
    }

    private function publishHome(array $settings): void
    {
        $content = ['schema' => 1, 'order' => ['p'], 'sections' => [
            'p' => ['type' => 'featured-products', 'label' => '', 'disabled' => false, 'settings' => $settings, 'block_order' => [], 'blocks' => []],
        ]];
        $doc = Document::firstOrNew(['handle' => 'home'], ['kind' => 'template', 'page_type' => 'home']);
        $doc->forceFill(['published' => $content, 'published_version' => (int) $doc->published_version + 1])->save();
    }

    private function page(string $url): TestResponse
    {
        Storefront::forgetRequestState();
        LinkValue::flush();
        // Core's visibility scope stands down in console runs; a shopper's request has it on.
        (new \ReflectionProperty(Application::class, 'isRunningInConsole'))->setValue($this->app, false);

        return $this->get($url)->assertOk();
    }

    private function headLink(string $html): ?string
    {
        $head = substr($html, (int) strpos($html, 'class="s-head"'), 3000);

        return preg_match('/<a class="link-arrow" href="([^"]+)"[^>]*><span data-setting="link_label">View all/', $head, $m) === 1 ? html_entity_decode($m[1]) : null;
    }

    public function test_the_link_setting_follows_the_products_by_default(): void
    {
        $setting = collect(Catalog::section('featured-products')->settings())->firstWhere('key', 'link');

        $this->assertSame(['type' => 'these-products'], $setting->default);
        $this->assertTrue($setting->followsProducts());
    }

    public function test_lint_accepts_the_flag_only_on_a_url_beside_a_source(): void
    {
        $link = ['key' => 'link', 'type' => 'url', 'label' => 'Link goes to', 'default' => ['type' => 'these-products'], 'follows_products' => true];
        $source = ['key' => 'source', 'type' => 'select', 'label' => 'Products to show', 'default' => 'newest', 'options' => [['value' => 'newest', 'label' => 'Newest']]];
        $problems = function (array $settings): array {
            $schema = Catalog::root('schemas/sections/zz-probe.json');
            file_put_contents($schema, json_encode([
                'type' => 'zz-probe', 'label' => 'Probe', 'group' => 'template', 'templates' => ['home'],
                'class' => 's-zz-probe', 'picker_group' => 'products', 'settings' => $settings,
            ]));
            try {
                Catalog::flush();

                return array_values(array_filter(CatalogLinter::check(), fn (string $p) => str_contains($p, 'zz-probe') && (str_contains($p, 'follow') || str_contains($p, 'default of'))));
            } finally {
                @unlink($schema);
                Catalog::flush();
            }
        };

        $this->assertSame([], $problems([$source, $link]));
        $this->assertNotSame([], $problems([$link]), 'a link with no source to follow');
        $this->assertNotSame([], $problems([$source, ['follows_products' => true] + ['key' => 'title', 'type' => 'text', 'label' => 'Title', 'default' => '']]));
        $this->assertNotSame([], $problems([$source, ['follows_products' => 'yes'] + $link]));
        $this->assertNotSame([], $problems([$source, ['follows_products' => false] + $link]), 'the default needs the flag');
        $this->assertSame([], CatalogLinter::check());
    }

    public function test_these_products_leads_to_the_listing_of_the_same_products(): void
    {
        $category = Category::create(['store_id' => $this->store->id, 'name' => 'Laptops', 'slug' => 'laptops', 'is_active' => true]);
        $off = Category::create(['store_id' => $this->store->id, 'name' => 'Archive', 'slug' => 'archive', 'is_active' => false]);
        $brand = Brand::create(['store_id' => $this->store->id, 'name' => 'Northwind', 'slug' => 'northwind', 'is_active' => true]);
        $link = ['type' => 'these-products'];

        $cases = [
            [['source' => 'newest'], '/search?sort=date_added'],
            [['source' => 'best-sellers'], '/search?sort=best_sellers'],
            [['source' => 'most-viewed'], '/search?sort=most_viewed'],
            [['source' => 'on-sale'], '/search?on_sale=1'],
            [['source' => 'category', 'category' => $category->id], '/category/laptops'],
            [['source' => 'brand', 'brand' => $brand->id], '/brand/northwind'],
            [['source' => 'hand-picked', 'products' => [1]], null],
            [['source' => 'category', 'category' => $off->id], null],
            [['source' => 'category', 'category' => 999999], null],
            [['source' => 'category', 'category' => null], null],
            [['source' => 'brand', 'brand' => 999999], null],
        ];
        foreach ($cases as [$settings, $expected]) {
            LinkValue::flush();
            $this->assertSame($expected, LinkValue::forProducts($link, $settings), json_encode($settings));
        }

        // Any other link goes where it says, whatever the section shows.
        $this->assertSame('/cart', LinkValue::forProducts(['type' => 'cart'], ['source' => 'hand-picked']));
        $this->assertSame('/search', LinkValue::forProducts(['type' => 'search'], ['source' => 'best-sellers']));
        // On its own, without the section, the link means nothing.
        $this->assertNull(LinkValue::resolve($link));
    }

    public function test_only_a_setting_that_follows_the_products_keeps_these_products(): void
    {
        $this->assertSame(['type' => 'these-products'], LinkValue::clean(['type' => 'these-products'], true));
        $this->assertNull(LinkValue::clean(['type' => 'these-products']));

        $admin = $this->admin();
        $home = ['schema' => 1, 'order' => ['p', 'r'], 'sections' => [
            'p' => ['type' => 'featured-products', 'label' => '', 'disabled' => false, 'block_order' => [], 'blocks' => [],
                'settings' => ['link' => ['type' => 'these-products']]],
            'r' => ['type' => 'rich-text', 'label' => '', 'disabled' => false, 'block_order' => [], 'blocks' => [],
                'settings' => ['button_link' => ['type' => 'these-products']]],
        ]];
        $this->saveDraft($admin, 'home', $home, 0)->assertOk();
        $draft = Document::where('handle', 'home')->first()->draft;

        $this->assertSame(['type' => 'these-products'], $draft['sections']['p']['settings']['link']);
        $this->assertNull($draft['sections']['r']['settings']['button_link']);
    }

    public function test_the_heading_link_follows_the_section_and_hides_for_a_hand_picked_list(): void
    {
        $product = $this->product('Demo Laptop');
        ProductSpecial::create(['product_id' => $product->id, 'price' => 1000, 'quantity' => 1, 'display_mode' => 'sale', 'priority' => 1]);

        // A section that never set its link takes the new default.
        $this->publishHome(['source' => 'on-sale', 'link_label' => 'View all']);
        $this->assertSame('/search?on_sale=1', $this->headLink($this->page('/')->getContent()));

        $this->publishHome(['source' => 'hand-picked', 'products' => [$product->id], 'link_label' => 'View all']);
        $html = $this->page('/')->assertSee('Demo Laptop')->getContent();
        $this->assertNull($this->headLink($html));

        // A link the merchant chose stays theirs.
        $this->publishHome(['source' => 'hand-picked', 'products' => [$product->id], 'link_label' => 'View all', 'link' => ['type' => 'search']]);
        $this->assertSame('/search', $this->headLink($this->page('/')->getContent()));
    }

    public function test_listings_offer_the_new_sorts_and_search_offers_on_sale(): void
    {
        $sale = $this->product('Sale Laptop Demo', ['views' => 1]);
        $second = $this->product('Sale Tablet Demo', ['views' => 5]);
        $this->product('Full Price Laptop Demo', ['views' => 9]);
        foreach ([$sale, $second] as $onSale) {
            ProductSpecial::create(['product_id' => $onSale->id, 'price' => 1000, 'quantity' => 1, 'display_mode' => 'sale', 'priority' => 1]);
        }
        $category = Category::create(['store_id' => $this->store->id, 'name' => 'Laptops', 'slug' => 'laptops', 'is_active' => true]);
        Product::query()->get()->each(fn (Product $p) => $p->categories()->attach($category->id));

        $this->page('/category/laptops?sort=most_viewed&on_sale=1')
            ->assertSee('<option value="best_sellers" >Best sellers</option>', false)
            ->assertSee('<option value="most_viewed" selected>Most viewed</option>', false)
            // Sorting keeps the filter on; the filters keep the sort.
            ->assertSee('<input type="hidden" name="on_sale" value="1">', false)
            ->assertSee('<input type="hidden" name="sort" value="most_viewed">', false)
            ->assertSeeInOrder(['Sale Tablet Demo', 'Sale Laptop Demo'])->assertDontSee('Full Price Laptop Demo');

        // The All products page: the switch sits in the sort form, so each keeps the other.
        $this->page('/search?sort=most_viewed')
            ->assertSeeInOrder(['Full Price Laptop Demo', 'Sale Tablet Demo', 'Sale Laptop Demo'])
            ->assertSee('name="on_sale" value="1" data-sort', false)
            ->assertDontSee('name="on_sale" value="1" data-sort checked', false);

        $onSale = $this->page('/search?sort=most_viewed&on_sale=1');
        $this->assertSame(['Sale Tablet Demo', 'Sale Laptop Demo'], $onSale->viewData('products')->pluck('name')->all());
        $onSale
            ->assertSee('name="on_sale" value="1" data-sort checked', false)
            ->assertSee('<option value="most_viewed" selected>Most viewed</option>', false)
            ->assertDontSee('<input type="hidden" name="on_sale"', false);

        // Nothing on sale for the words: a way back to every result.
        $this->page('/search?q=full&sort=price_asc&on_sale=1')
            ->assertSee('Nothing on sale for')
            ->assertSee('Show products at their regular price too')
            ->assertSee('href="' . e(url('/search') . '?q=full&sort=price_asc') . '"', false);
    }
}
