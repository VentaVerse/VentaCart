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
use App\Services\ExtensionManager;
use App\Support\ProductFeed;
use App\Support\Widgets;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The storefront's public product listing, and the product blocks built on it.
 */
class ProductFeedTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $name, array $attrs = []): Product
    {
        return Product::create($attrs + [
            'store_id' => 1, 'name' => $name, 'slug' => Str::slug($name),
            'sku' => 'DEMO-' . Str::upper(Str::random(6)), 'price' => 100, 'quantity' => 5, 'is_active' => true,
        ]);
    }

    private function category(string $name, ?int $parent = null, bool $active = true): Category
    {
        return Category::create(['store_id' => 1, 'parent_id' => $parent, 'name' => $name,
            'slug' => Str::slug($name), 'is_active' => $active]);
    }

    /** @return list<string> */
    private function names(array $criteria): array
    {
        return ProductFeed::get($criteria)->pluck('name')->all();
    }

    /**
     * The visibility scope stands down for console runs, and the test suite
     * is one; switch it off so the request reads as a storefront visit.
     */
    private function asStorefrontRequest(): void
    {
        (new \ReflectionProperty(Application::class, 'isRunningInConsole'))->setValue($this->app, false);
    }

    public function test_new_lists_the_newest_first_and_only_active_products(): void
    {
        $this->product('Older', ['date_available' => now()->subDays(3)]);
        $this->product('Newer', ['date_available' => now()->subDay()]);
        $this->product('Switched Off', ['is_active' => false, 'date_available' => now()]);

        $this->assertSame(['Newer', 'Older'], $this->names(['filter' => 'new']));
        $this->assertSame(['Newer', 'Older'], $this->names([]), 'New is the default.');
        $this->assertSame(['Newer', 'Older'], $this->names(['filter' => 'no-such-list']), 'An unknown list reads as new.');
    }

    public function test_best_sellers_rank_by_revenue_then_views(): void
    {
        $small = $this->product('Small Seller', ['views' => 50]);
        $big = $this->product('Big Seller');
        $this->product('Unsold Popular', ['views' => 10]);
        $this->product('Unsold Quiet', ['views' => 1]);

        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Pending', 'sort_order' => 1, 'is_default' => true]);
        $order = Order::create(['store_id' => 1, 'order_status_id' => $status->id, 'first_name' => 'Juan',
            'last_name' => 'Dela Cruz', 'email' => 'juan@example.com', 'subtotal' => 1200, 'total' => 1200]);
        OrderProduct::create(['order_id' => $order->id, 'product_id' => $big->id, 'name' => 'Big Seller', 'price' => 500, 'quantity' => 2, 'total' => 1000]);
        OrderProduct::create(['order_id' => $order->id, 'product_id' => $small->id, 'name' => 'Small Seller', 'price' => 200, 'quantity' => 1, 'total' => 200]);

        $this->assertSame(['Big Seller', 'Small Seller', 'Unsold Popular', 'Unsold Quiet'], $this->names(['filter' => 'best_sellers']));
    }

    public function test_on_sale_lists_only_products_with_an_active_sale_special(): void
    {
        $sale = $this->product('On Sale');
        $expired = $this->product('Sale Ended');
        $future = $this->product('Sale Not Started');
        $this->product('Full Price');

        ProductSpecial::create(['product_id' => $sale->id, 'price' => 80, 'display_mode' => 'sale', 'date_start' => now()->subDay(), 'date_end' => now()->addDay()]);
        ProductSpecial::create(['product_id' => $expired->id, 'price' => 80, 'display_mode' => 'sale', 'date_start' => now()->subDays(5), 'date_end' => now()->subDays(2)]);
        ProductSpecial::create(['product_id' => $future->id, 'price' => 80, 'display_mode' => 'sale', 'date_start' => now()->addDays(2), 'date_end' => now()->addDays(5)]);

        $this->assertSame(['On Sale'], $this->names(['filter' => 'on_sale']));
    }

    public function test_most_viewed_ranks_by_views(): void
    {
        $this->product('Few Views', ['views' => 3]);
        $this->product('Many Views', ['views' => 300]);
        $this->product('Some Views', ['views' => 30]);

        $this->assertSame(['Many Views', 'Some Views', 'Few Views'], $this->names(['filter' => 'most_viewed']));
    }

    public function test_random_returns_active_products_within_the_limit(): void
    {
        foreach (range(1, 6) as $i) {
            $this->product("Random {$i}");
        }
        $this->product('Hidden Random', ['is_active' => false]);

        $names = $this->names(['filter' => 'random', 'limit' => 4]);

        $this->assertCount(4, $names);
        $this->assertNotContains('Hidden Random', $names);
    }

    public function test_category_includes_subcategories_unless_told_not_to(): void
    {
        $phones = $this->category('Phones');
        $cases = $this->category('Phone Cases', $phones->id);
        $this->product('Demo Phone', ['date_available' => now()->subDay()])->categories()->attach($phones->id);
        $this->product('Demo Case', ['date_available' => now()])->categories()->attach($cases->id);
        $this->product('Demo Laptop');

        $this->assertSame(['Demo Case', 'Demo Phone'], $this->names(['category' => $phones->id]));
        $this->assertSame(['Demo Phone'], $this->names(['category' => $phones->id, 'subcategories' => false]));
    }

    public function test_a_missing_or_switched_off_category_or_brand_lists_nothing(): void
    {
        $this->product('Anything');
        $hidden = $this->category('Hidden', null, false);
        $off = Brand::create(['store_id' => 1, 'name' => 'Off Brand', 'slug' => 'off-brand', 'is_active' => false]);

        $this->assertSame([], $this->names(['category' => $hidden->id]));
        $this->assertSame([], $this->names(['category' => 999999]));
        $this->assertSame([], $this->names(['brand' => $off->id]));
        $this->assertSame([], $this->names(['brand' => 999999]));
    }

    public function test_brand_narrows_to_one_brand(): void
    {
        $brand = Brand::create(['store_id' => 1, 'name' => 'Demo Brand', 'slug' => 'demo-brand', 'is_active' => true]);
        $this->product('Branded', ['brand_id' => $brand->id]);
        $this->product('Unbranded');

        $this->assertSame(['Branded'], $this->names(['brand' => $brand->id]));
    }

    public function test_ids_keep_the_given_order_and_drop_unknown_or_inactive_ids(): void
    {
        $a = $this->product('First Pick');
        $b = $this->product('Second Pick');
        $c = $this->product('Third Pick');
        $off = $this->product('Inactive Pick', ['is_active' => false]);

        $this->assertSame(
            ['Third Pick', 'First Pick', 'Second Pick'],
            $this->names(['ids' => [$c->id, 999999, $a->id, $off->id, $b->id, $a->id]])
        );
        $this->assertSame(['Third Pick'], $this->names(['ids' => [$c->id, $a->id], 'limit' => 1]), 'The limit keeps the first ids.');
        $this->assertSame([], $this->names(['ids' => []]), 'An empty pick lists nothing, not everything.');
        $this->assertSame([$c->id, $a->id, $b->id], ProductFeed::parseIds("{$c->id}, {$a->id} {$b->id},x,-4,{$a->id}"));
    }

    public function test_the_limit_is_clamped_between_one_and_forty_eight(): void
    {
        foreach (range(1, 50) as $i) {
            $this->product("Limit {$i}");
        }

        $this->assertCount(48, ProductFeed::get(['limit' => 500]));
        $this->assertCount(1, ProductFeed::get(['limit' => 0]));
        $this->assertCount(1, ProductFeed::get(['limit' => -3]));
        $this->assertCount(12, ProductFeed::get(['limit' => 'many']));
        $this->assertCount(12, ProductFeed::get());
    }

    public function test_a_product_kept_for_one_group_never_reaches_a_guest_or_another_group(): void
    {
        $wholesale = CustomerGroup::create(['store_id' => 1, 'name' => 'Wholesale']);
        $retail = CustomerGroup::create(['store_id' => 1, 'name' => 'Retail', 'is_default' => true]);
        $this->product('Public Item', ['date_available' => now()->subDay()]);
        $this->product('Wholesale Item', ['date_available' => now()])->customerGroups()->attach($wholesale->id);

        $this->asStorefrontRequest();

        // A guest, through every list and through a hand-pick naming it.
        $hiddenId = Product::withoutGlobalScopes()->where('name', 'Wholesale Item')->value('id');
        foreach (ProductFeed::FILTERS as $filter) {
            $this->assertNotContains('Wholesale Item', $this->names(['filter' => $filter]), $filter);
        }
        $this->assertSame([], $this->names(['ids' => [$hiddenId]]));

        $customer = fn (CustomerGroup $group, string $email) => Customer::create(['store_id' => 1, 'customer_group_id' => $group->id,
            'first_name' => 'Maria', 'last_name' => 'Santos', 'email' => $email, 'password' => bcrypt('secret'), 'is_active' => true]);

        $this->actingAs($customer($retail, 'retail@example.com'), 'customer');
        $this->assertSame(['Public Item'], $this->names([]));

        $this->actingAs($customer($wholesale, 'wholesale@example.com'), 'customer');
        $this->assertSame(['Wholesale Item', 'Public Item'], $this->names([]));
    }

    public function test_products_come_with_what_a_card_needs(): void
    {
        $this->product('Loaded');

        $product = ProductFeed::get()->first();

        foreach (['images', 'specials', 'brand', 'cardAttributes'] as $relation) {
            $this->assertTrue($product->relationLoaded($relation), $relation);
        }
        $this->assertFalse($product->relationLoaded('variants'));
        $this->assertTrue(ProductFeed::get(['variants' => true])->first()->relationLoaded('variants'));
    }

    public function test_review_aggregates_ride_along_only_while_reviews_are_enabled(): void
    {
        $this->product('Rated');
        $this->assertArrayNotHasKey('reviews_count', ProductFeed::get()->first()->getAttributes());

        app(ExtensionManager::class)->install('reviews');
        app(ExtensionManager::class)->bootEnabled();

        $attributes = ProductFeed::get()->first()->getAttributes();
        $this->assertArrayHasKey('reviews_count', $attributes);
        $this->assertArrayHasKey('reviews_avg_rating', $attributes);
    }

    public function test_the_product_grid_lists_through_the_feed_and_offers_hand_picked_not_featured(): void
    {
        $a = $this->product('Grid A', ['date_available' => now()->subDay()]);
        $b = $this->product('Grid B', ['date_available' => now()]);

        $grid = fn (array $config) => Widgets::resolve('catalog.products', $config)['products']->pluck('name')->all();

        $this->assertSame(['Grid B', 'Grid A'], $grid(['filter' => 'new']));
        $this->assertSame(['Grid A', 'Grid B'], $grid(['filter' => 'ids', 'products' => "{$a->id}, {$b->id}"]));
        $this->assertSame([], $grid(['filter' => 'ids', 'products' => '']));
        $this->assertSame(['Grid B', 'Grid A'], $grid(['filter' => 'featured']), 'A block saved before the change still lists products.');

        $options = collect(Widgets::schema('catalog.products'))->firstWhere('name', 'filter')['options'];
        $this->assertArrayHasKey('ids', $options);
        $this->assertArrayNotHasKey('featured', $options);

        $tabs = Widgets::resolve('catalog.product_tabs', ['tabs' => [
            ['title' => 'Picks', 'filter' => 'ids', 'products' => "{$b->id},{$a->id}"],
        ]])['tabs'];
        $this->assertSame(['Grid B', 'Grid A'], $tabs[0]['products']->pluck('name')->all());
    }

    public function test_the_product_grid_still_renders_on_the_storefront(): void
    {
        $this->product('Rendered Grid Product');
        \App\Models\LayoutPlacement::create([
            'page_type' => 'home', 'region' => 'content_bottom', 'widget' => 'catalog.products', 'title' => 'Latest Arrivals',
            'config' => ['filter' => 'new', 'limit' => 4], 'sort_order' => 0, 'is_enabled' => true,
        ]);

        $this->get('/')->assertOk()->assertSee('Latest Arrivals', false)->assertSee('Rendered Grid Product', false);
    }

    public function test_saved_featured_blocks_are_moved_to_newest(): void
    {
        $placement = \DB::table('layout_placements')->insertGetId([
            'page_type' => 'home', 'region' => 'content', 'widget' => 'catalog.products', 'theme' => 'default',
            'config' => json_encode(['filter' => 'featured', 'limit' => 4]), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $tabs = \DB::table('layout_placements')->insertGetId([
            'page_type' => 'home', 'region' => 'content', 'widget' => 'catalog.product_tabs', 'theme' => 'default',
            'config' => json_encode(['tabs' => [['title' => 'A', 'filter' => 'featured'], ['title' => 'B', 'filter' => 'specials']]]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $other = \DB::table('layout_placements')->insertGetId([
            'page_type' => 'home', 'region' => 'content', 'widget' => 'content.html', 'theme' => 'default',
            'config' => json_encode(['filter' => 'featured']), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $template = \DB::table('layout_templates')->insertGetId([
            'theme' => 'default', 'name' => 'Demo Store row', 'region' => 'content',
            'rows' => json_encode([['columns' => [8], 'placements' => [['widget' => 'catalog.products', 'config' => ['filter' => 'featured']]]]]),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_11_17_110000_point_featured_product_blocks_at_newest.php');
        $migration->up();
        $migration->up();

        $config = fn (int $id) => json_decode(\DB::table('layout_placements')->where('id', $id)->value('config'), true);
        $this->assertSame('new', $config($placement)['filter']);
        $this->assertSame(4, $config($placement)['limit']);
        $this->assertSame(['new', 'specials'], array_column($config($tabs)['tabs'], 'filter'));
        $this->assertSame('featured', $config($other)['filter'], 'Only product blocks are touched.');
        $rows = json_decode(\DB::table('layout_templates')->where('id', $template)->value('rows'), true);
        $this->assertSame('new', $rows[0]['placements'][0]['config']['filter']);
    }
}
