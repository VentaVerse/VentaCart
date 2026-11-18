<?php

namespace Tests\Feature\Media;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Store;
use App\Support\Media\ColumnReference;
use App\Support\Media\MediaReferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MediaReferencesTest extends TestCase
{
    use RefreshDatabase;

    private const BANNER = 'media/banners/demo.jpg';

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Demo Store']);
    }

    private function product(string $slug, ?string $image = null): int
    {
        return DB::table('products')->insertGetId([
            'store_id' => 1, 'name' => 'Demo ' . $slug, 'slug' => $slug,
            'price' => 1, 'quantity' => 1, 'image' => $image,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function gallery(int $productId, string $path): void
    {
        DB::table('product_images')->insert(['product_id' => $productId, 'path' => $path, 'sort_order' => 0]);
    }

    private function category(string $slug, string $image): void
    {
        DB::table('categories')->insert([
            'store_id' => 1, 'name' => 'Demo ' . $slug, 'slug' => $slug, 'image' => $image,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Written raw, so the stored text is exactly what a test needs it to be. */
    private function menuItem(string $config): int
    {
        return DB::table('nav_menu_items')->insertGetId(['label' => 'Demo', 'type' => 'banner', 'config' => $config]);
    }

    private function orderLine(string $image): void
    {
        $status = OrderStatus::firstOrCreate(['store_id' => 1, 'name' => 'Pending'], ['is_default' => true, 'sort_order' => 1]);
        $order = Order::create([
            'store_id' => 1, 'order_status_id' => $status->id,
            'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'email' => 'juan@example.com', 'phone' => '1',
            'shipping_first_name' => 'Juan', 'shipping_last_name' => 'Dela Cruz', 'shipping_address_1' => 'Demo Street',
            'shipping_city' => 'Demo City', 'shipping_postcode' => '1000', 'subtotal' => 1, 'total' => 1,
        ]);
        DB::table('order_products')->insert([
            'order_id' => $order->id, 'name' => 'Demo', 'sku' => 'DEMO-001', 'image' => $image,
            'price' => 1, 'quantity' => 1, 'total' => 1,
        ]);
    }

    public function test_a_path_used_by_a_product_and_its_gallery_counts_one_product(): void
    {
        $path = 'media/products/demo-001.jpg';
        $id = $this->product('demo-001', $path);
        $this->gallery($id, $path);

        $this->assertSame([$path => 1], MediaReferences::inUse([$path]));
        $this->assertSame([$path => ['products' => 1]], MediaReferences::usage([$path]));
        $this->assertSame([$path => ['total' => 1, 'label' => 'products']], MediaReferences::summary([$path]));
    }

    public function test_two_kinds_of_user_give_no_single_label(): void
    {
        $path = 'media/products/demo-001.jpg';
        $this->product('demo-001', $path);
        $this->category('demo', $path);

        $this->assertSame([$path => ['total' => 2, 'label' => null]], MediaReferences::summary([$path]));
        $this->assertSame([$path => ['products' => 1, 'categories' => 1]], MediaReferences::usage([$path]));
    }

    public function test_an_unused_path_has_no_label(): void
    {
        $this->assertSame(['media/products/spare.jpg' => ['total' => 0, 'label' => null]], MediaReferences::summary(['media/products/spare.jpg']));
        $this->assertSame(['media/products/spare.jpg' => []], MediaReferences::usage(['media/products/spare.jpg']));
    }

    public function test_json_columns_match_decoded_values(): void
    {
        $accented = 'media/banners/café/demo.jpg';

        $this->menuItem('{"banner":"media/banners/demo.jpg"}');
        $this->menuItem('{"banner":"media\/banners\/demo.jpg"}');
        $this->menuItem('{"banner":"media\/banners\/café\/demo.jpg"}');
        // Contains the path as text, but no value equals it.
        $this->menuItem('{"html":"<img src=\"/storage/media/banners/demo.jpg\">"}');

        $this->assertSame(
            [self::BANNER => ['menus' => 2], $accented => ['menus' => 1]],
            MediaReferences::usage([self::BANNER, $accented]),
        );
    }

    public function test_a_theme_setting_holding_a_path_or_a_list_counts(): void
    {
        DB::table('settings')->insert([
            ['group' => 'theme', 'key' => 'hero', 'value' => self::BANNER],
            ['group' => 'theme', 'key' => 'gallery', 'value' => '["media/banners/demo.jpg"]'],
            ['group' => 'theme', 'key' => 'note', 'value' => 'see media/banners/demo.jpg'],
        ]);

        $this->assertSame([self::BANNER => ['settings' => 2]], MediaReferences::usage([self::BANNER]));
    }

    public function test_order_lines_do_not_count_as_a_use(): void
    {
        $path = 'media/products/demo-001.jpg';
        $this->orderLine($path);

        $this->assertSame([$path => 0], MediaReferences::inUse([$path]));
        $this->assertSame(['past orders' => 1], MediaReferences::repoint($path, 'media/products/demo-002.jpg'));
        $this->assertSame('media/products/demo-002.jpg', DB::table('order_products')->value('image'));
    }

    public function test_paths_that_differ_only_by_case_are_separate(): void
    {
        $upper = 'media/products/Demo/a.jpg';
        $lower = 'media/products/demo/a.jpg';
        $this->product('upper', $upper);
        $this->product('lower', $lower);

        $this->assertSame([$upper => 1, $lower => 1], MediaReferences::inUse([$upper, $lower]));

        $this->assertSame(['products' => 1], MediaReferences::repoint('media/products/Demo', 'media/products/Moved'));
        $this->assertSame('media/products/Moved/a.jpg', DB::table('products')->where('slug', 'upper')->value('image'));
        $this->assertSame($lower, DB::table('products')->where('slug', 'lower')->value('image'));
    }

    public function test_an_underscore_in_a_folder_name_is_not_a_wildcard(): void
    {
        $this->product('set', 'media/products/demo_set/a.jpg');
        $this->product('other', 'media/products/demoXset/a.jpg');

        $this->assertSame(['products' => 1], MediaReferences::repoint('media/products/demo_set', 'media/products/sets'));
        $this->assertSame('media/products/sets/a.jpg', DB::table('products')->where('slug', 'set')->value('image'));
        $this->assertSame('media/products/demoXset/a.jpg', DB::table('products')->where('slug', 'other')->value('image'));
    }

    public function test_a_percent_sign_in_a_folder_name_is_not_a_wildcard(): void
    {
        $this->product('sale', 'media/products/50%off/a.jpg');
        $this->product('other', 'media/products/50-more-off/a.jpg');

        $this->assertSame(['products' => 1], MediaReferences::repoint('media/products/50%off', 'media/products/sale'));
        $this->assertSame('media/products/50-more-off/a.jpg', DB::table('products')->where('slug', 'other')->value('image'));
    }

    public function test_repoint_reports_rows_changed_per_kind(): void
    {
        $id = $this->product('demo-001', 'media/products/demo/a.jpg');
        $this->gallery($id, 'media/products/demo/b.jpg');
        $this->product('demo-002', 'media/products/demo-other/a.jpg');
        $this->menuItem('{"banner":"media\/products\/demo\/a.jpg","label":"Demo","columns":{}}');
        $this->orderLine('media/products/demo/a.jpg');

        $this->assertSame(
            ['products' => 2, 'menus' => 1, 'past orders' => 1],
            MediaReferences::repoint('media/products/demo', 'media/products/sorted'),
        );

        $this->assertSame('media/products/demo-other/a.jpg', DB::table('products')->where('slug', 'demo-002')->value('image'));
        $this->assertSame('media/products/sorted/b.jpg', DB::table('product_images')->value('path'));
        // Re-encoded readably, and an empty object stays an object.
        $this->assertSame('{"banner":"media/products/sorted/a.jpg","label":"Demo","columns":{}}', DB::table('nav_menu_items')->value('config'));
    }

    public function test_a_disabled_blogs_rows_still_count(): void
    {
        DB::table('extensions')->updateOrInsert(['name' => 'blog'], ['version' => '1.0.0', 'enabled' => false]);
        DB::table('blog_posts')->insert([
            'title' => 'Demo', 'slug' => 'demo', 'content' => 'Demo', 'featured_image' => self::BANNER,
        ]);

        $this->assertSame([self::BANNER => ['blog posts' => 1]], MediaReferences::usage([self::BANNER]));
    }

    public function test_a_source_whose_table_is_missing_is_skipped(): void
    {
        MediaReferences::register(ColumnReference::path('no_such_table', 'image', 'missing'));
        MediaReferences::register(ColumnReference::json('no_such_json_table', 'config', 'missing json'));
        $this->product('demo-001', self::BANNER);

        $this->assertSame([self::BANNER => ['products' => 1]], MediaReferences::usage([self::BANNER]));
        $this->assertSame(['products' => 1], MediaReferences::repoint(self::BANNER, 'media/banners/other.jpg'));
    }

    public function test_flush_forgets_every_source(): void
    {
        $this->product('demo-001', self::BANNER);
        MediaReferences::flush();

        $this->assertSame([self::BANNER => 0], MediaReferences::inUse([self::BANNER]));
    }
}
