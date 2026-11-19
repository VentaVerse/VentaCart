<?php

namespace Tests\Feature\Storefront;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\ProductSpecial;
use App\Services\CartService;
use App\Services\ThemeService;
use App\Support\ProductFeed;
use App\Support\Pricing;
use App\Support\ProductListing;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The category, brand and search pages sort by best sellers and most viewed
 * and filter to products on sale, with the same rules ProductFeed uses for
 * product sections, page after page.
 */
class ListingSortsAndOnSaleTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    private Brand $brand;

    private CustomerGroup $retail;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create(['store_id' => 1, 'name' => 'Demo Category', 'slug' => 'demo-category', 'is_active' => true]);
        $this->brand = Brand::create(['store_id' => 1, 'name' => 'Demo Brand', 'slug' => 'demo-brand', 'is_active' => true]);
        $this->retail = CustomerGroup::create(['store_id' => 1, 'name' => 'Retail', 'is_default' => true]);
    }

    private function product(string $name, array $attrs = []): Product
    {
        $product = Product::create($attrs + [
            'store_id' => 1, 'name' => $name, 'slug' => Str::slug($name),
            'sku' => 'DEMO-' . Str::upper(Str::random(6)), 'price' => 100, 'quantity' => 5,
            'is_active' => true, 'brand_id' => $this->brand->id,
        ]);
        $product->categories()->attach($this->category->id);

        return $product;
    }

    private function sell(Product $product, float $price, int $quantity): void
    {
        $status = OrderStatus::firstOrCreate(['store_id' => 1, 'name' => 'Pending'], ['sort_order' => 1, 'is_default' => true]);
        $order = Order::create(['store_id' => 1, 'order_status_id' => $status->id, 'first_name' => 'Juan',
            'last_name' => 'Dela Cruz', 'email' => 'juan@example.com', 'subtotal' => $price * $quantity, 'total' => $price * $quantity]);
        OrderProduct::create(['order_id' => $order->id, 'product_id' => $product->id, 'name' => $product->name,
            'price' => $price, 'quantity' => $quantity, 'total' => $price * $quantity]);
    }

    private function sale(Product $product, array $attrs = []): ProductSpecial
    {
        return ProductSpecial::create($attrs + [
            'product_id' => $product->id, 'price' => 80, 'display_mode' => 'sale',
            'date_start' => now()->subDay(), 'date_end' => now()->addDay(),
        ]);
    }

    /** @return array<string, string> page => url */
    private function listingPages(array $query = []): array
    {
        return [
            'category' => route('category.show', ['category' => $this->category->slug] + $query),
            'brand' => route('brand.show', ['brand' => $this->brand->slug] + $query),
            'search' => route('search.results', ['q' => 'Demo'] + $query),
            'all products' => route('search.results', $query),
        ];
    }

    /** @return list<string> */
    private function names(string $url): array
    {
        return $this->get($url)->assertOk()->viewData('products')->pluck('name')->all();
    }

    /** The visibility scope stands down for console runs; read the request as a storefront visit. */
    private function asStorefrontRequest(): void
    {
        (new \ReflectionProperty(Application::class, 'isRunningInConsole'))->setValue($this->app, false);
    }

    public function test_the_new_sorts_are_offered_with_plain_labels(): void
    {
        $this->assertSame('Best sellers', ProductListing::sorts()['best_sellers']);
        $this->assertSame('Most viewed', ProductListing::sorts()['most_viewed']);
        $this->assertTrue(ProductListing::isSort('best_sellers'));
        $this->assertSame('most_viewed', ProductListing::searchSort('most_viewed'));
    }

    public function test_best_sellers_follow_the_store_ranking_then_views_on_every_listing(): void
    {
        $small = $this->product('Small Seller Demo', ['views' => 50]);
        $big = $this->product('Big Seller Demo');
        $this->product('Unsold Popular Demo', ['views' => 10]);
        $this->product('Unsold Quiet Demo', ['views' => 1]);
        $this->sell($big, 500, 2);
        $this->sell($small, 200, 1);

        $expected = ['Big Seller Demo', 'Small Seller Demo', 'Unsold Popular Demo', 'Unsold Quiet Demo'];
        foreach ($this->listingPages(['sort' => 'best_sellers']) as $page => $url) {
            $this->assertSame($expected, $this->names($url), $page);
            $this->get($url)->assertViewHas('sort', 'best_sellers');
        }

        // The same ranking as the product sections, not a second one.
        $this->assertSame($expected, ProductFeed::get(['filter' => 'best_sellers'])->pluck('name')->all());
    }

    public function test_most_viewed_ranks_by_views_and_breaks_ties_by_the_newest_product(): void
    {
        $this->product('Few Views Demo', ['views' => 3]);
        $this->product('Tied Older Demo', ['views' => 30]);
        $this->product('Tied Newer Demo', ['views' => 30]);
        $this->product('Many Views Demo', ['views' => 300]);

        foreach ($this->listingPages(['sort' => 'most_viewed']) as $page => $url) {
            $this->assertSame(['Many Views Demo', 'Tied Newer Demo', 'Tied Older Demo', 'Few Views Demo'], $this->names($url), $page);
        }
    }

    public function test_the_new_sorts_page_without_repeating_or_skipping_a_product(): void
    {
        app(ThemeService::class)->set('category_page', 'products_per_page', '4');
        foreach (range(1, 10) as $i) {
            // Many share a view count, so only the tie-break keeps the pages apart.
            $product = $this->product("Paged {$i} Demo", ['views' => $i % 3]);
            if ($i % 4 === 0) {
                $this->sell($product, 100, $i);
            }
        }

        foreach (['best_sellers', 'most_viewed'] as $sort) {
            foreach ($this->listingPages(['sort' => $sort]) as $page => $url) {
                $seen = [];
                foreach ([1, 2, 3] as $number) {
                    $seen = [...$seen, ...$this->names($url . (str_contains($url, '?') ? '&' : '?') . 'page=' . $number)];
                }
                $this->assertCount(10, $seen, "{$sort} on {$page}");
                $this->assertCount(10, array_unique($seen), "{$sort} on {$page}");
            }
        }
    }

    public function test_on_sale_narrows_every_listing_and_keeps_the_sort(): void
    {
        $this->product('Full Price Demo', ['views' => 99]);
        $this->sale($this->product('Cheap Sale Demo', ['price' => 90, 'views' => 1]));
        $this->sale($this->product('Dear Sale Demo', ['price' => 500, 'views' => 5]));
        $this->sale($this->product('Ended Sale Demo'), ['date_start' => now()->subDays(5), 'date_end' => now()->subDays(2)]);
        // A sale above the product's own price saves nothing, so it is not on sale.
        $this->sale($this->product('No Saving Demo', ['price' => 60]));

        foreach ($this->listingPages(['on_sale' => '1', 'sort' => 'most_viewed']) as $page => $url) {
            $this->assertSame(['Dear Sale Demo', 'Cheap Sale Demo'], $this->names($url), $page);
            $this->get($url)->assertViewHas('sort', 'most_viewed');
        }
        foreach ($this->listingPages(['on_sale' => '1', 'sort' => 'price_asc']) as $page => $url) {
            $this->assertSame(['Cheap Sale Demo', 'Dear Sale Demo'], $this->names($url), $page);
        }

        // Off, or switched off with 0, the full list comes back.
        foreach ($this->listingPages(['on_sale' => '0']) as $page => $url) {
            $this->assertCount(5, $this->names($url), $page);
        }
    }

    public function test_on_sale_follows_the_shoppers_customer_group(): void
    {
        $reseller = CustomerGroup::create(['store_id' => 1, 'name' => 'Reseller']);
        $this->sale($this->product('Everyone Sale Demo', ['price' => 300]));
        $this->sale($this->product('Retail Sale Demo', ['price' => 200]), ['customer_group_id' => $this->retail->id]);
        $this->sale($this->product('Reseller Sale Demo', ['price' => 100]), ['customer_group_id' => $reseller->id]);

        foreach ($this->listingPages(['on_sale' => '1', 'sort' => 'price_asc']) as $page => $url) {
            $this->assertSame(['Retail Sale Demo', 'Everyone Sale Demo'], $this->names($url), "guest on {$page}");
        }

        $this->actingAs(Customer::create(['store_id' => 1, 'customer_group_id' => $reseller->id, 'first_name' => 'Maria',
            'last_name' => 'Santos', 'email' => 'maria@example.com', 'password' => bcrypt('secret'), 'is_active' => true]), 'customer');

        foreach ($this->listingPages(['on_sale' => '1', 'sort' => 'price_asc']) as $page => $url) {
            $this->assertSame(['Reseller Sale Demo', 'Everyone Sale Demo'], $this->names($url), "reseller on {$page}");
        }
    }

    public function test_on_sale_skips_volume_tiers_and_sales_outranked_by_a_quiet_price(): void
    {
        $this->sale($this->product('Ten Or More Demo'), ['quantity' => 10, 'price' => 50]);
        $quiet = $this->product('Quiet Price Wins Demo');
        $this->sale($quiet, ['display_mode' => 'override', 'priority' => 1, 'price' => 90]);
        $this->sale($quiet, ['priority' => 5, 'price' => 70]);
        $this->sale($this->product('Plain Sale Demo'));

        foreach ($this->listingPages(['on_sale' => '1']) as $page => $url) {
            $this->assertSame(['Plain Sale Demo'], $this->names($url), $page);
        }
    }

    public function test_the_on_sale_rule_agrees_with_the_special_the_cart_charges(): void
    {
        $other = CustomerGroup::create(['store_id' => 1, 'name' => 'Other']);
        $window = ['date_start' => now()->subDay(), 'date_end' => now()->addDay()];
        $cases = [
            'sale only' => [['display_mode' => 'sale']],
            'quiet price only' => [['display_mode' => 'override']],
            'sale outranks quiet' => [['display_mode' => 'sale', 'priority' => 1], ['display_mode' => 'override', 'priority' => 2]],
            'cheaper sale at the same rank' => [['display_mode' => 'sale', 'price' => 60], ['display_mode' => 'override', 'price' => 70]],
            'cheaper quiet at the same rank' => [['display_mode' => 'sale', 'price' => 70], ['display_mode' => 'override', 'price' => 60]],
            'one-unit tier beats no tier' => [['display_mode' => 'override', 'quantity' => 0], ['display_mode' => 'sale', 'quantity' => 1, 'priority' => 9]],
            'other group sale ignored' => [['display_mode' => 'sale', 'customer_group_id' => $other->id]],
            'other group quiet ignored' => [['display_mode' => 'sale', 'priority' => 5], ['display_mode' => 'override', 'priority' => 1, 'customer_group_id' => $other->id]],
            'ended quiet ignored' => [['display_mode' => 'sale', 'priority' => 5], ['display_mode' => 'override', 'priority' => 1, 'date_end' => now()->subHour()]],
            'sale not started' => [['display_mode' => 'sale', 'date_start' => now()->addDay(), 'date_end' => now()->addDays(3)]],
            'sale above the regular price' => [['display_mode' => 'sale', 'price' => 120]],
            'sale at the regular price' => [['display_mode' => 'sale', 'price' => 100]],
            'earlier sale wins a full tie' => [['display_mode' => 'sale', 'price' => 70], ['display_mode' => 'override', 'price' => 70]],
            'earlier quiet wins a full tie' => [['display_mode' => 'override', 'price' => 70], ['display_mode' => 'sale', 'price' => 70]],
        ];

        foreach ($cases as $name => $specials) {
            $product = $this->product(Str::title($name) . ' Demo');
            foreach ($specials as $special) {
                ProductSpecial::create($special + $window + ['product_id' => $product->id, 'price' => 80, 'quantity' => 1, 'priority' => 1]);
            }
        }

        // Listed exactly when the card shows the product as a sale.
        $listed = Product::query()->onSaleFor(CartService::customerGroupId())->pluck('id')->all();
        $shownAsSale = 0;
        foreach (Product::with('specials')->get() as $product) {
            $sale = Pricing::display($product)->isSale();
            $shownAsSale += (int) $sale;
            $this->assertSame($sale, in_array($product->id, $listed, true), $product->name);
        }
        $this->assertSame(7, $shownAsSale, 'the cases cover both outcomes');
    }

    public function test_hidden_products_stay_hidden_under_the_new_sorts_and_on_sale(): void
    {
        $wholesale = CustomerGroup::create(['store_id' => 1, 'name' => 'Wholesale']);
        $this->sale($this->product('Public Sale Demo'));
        $hidden = $this->product('Wholesale Sale Demo', ['views' => 999]);
        $hidden->customerGroups()->attach($wholesale->id);
        $this->sale($hidden);
        $this->sell($hidden, 1000, 5);

        $this->asStorefrontRequest();

        foreach ([['sort' => 'best_sellers'], ['sort' => 'most_viewed'], ['on_sale' => '1']] as $query) {
            foreach ($this->listingPages($query) as $page => $url) {
                $this->assertSame(['Public Sale Demo'], $this->names($url), json_encode($query) . " on {$page}");
            }
        }
    }
}
