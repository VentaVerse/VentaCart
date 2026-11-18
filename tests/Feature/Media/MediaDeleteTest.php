<?php

namespace Tests\Feature\Media;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * An image something uses is never deleted from under it: the answer names
 * every kind of user so the merchant can change them first. Past order
 * lines are a record, not a use, so they never stop a delete; an order
 * whose photo is gone shows the blank tile. A folder goes only once it
 * holds no images at any depth.
 */
class MediaDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Demo Store']);
        Storage::fake('public');
        Cache::flush();
        $this->actingAs(User::factory()->create());
    }

    private function putImage(string $path): void
    {
        Storage::disk('public')->put($path, 'bytes');
    }

    private function product(string $slug, ?string $image): int
    {
        return DB::table('products')->insertGetId([
            'store_id' => 1, 'name' => 'Demo ' . $slug, 'slug' => $slug,
            'price' => 1, 'quantity' => 1, 'image' => $image,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function category(string $slug, string $image): void
    {
        DB::table('categories')->insert([
            'store_id' => 1, 'name' => 'Demo ' . $slug, 'slug' => $slug, 'image' => $image,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function order(string $image): Order
    {
        $status = OrderStatus::firstOrCreate(['store_id' => 1, 'name' => 'Pending'], ['is_default' => true, 'sort_order' => 1]);
        $order = Order::create([
            'store_id' => 1, 'order_status_id' => $status->id,
            'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'email' => 'juan@example.com', 'phone' => '1',
            'shipping_first_name' => 'Juan', 'shipping_last_name' => 'Dela Cruz', 'shipping_address_1' => 'Demo Street',
            'shipping_city' => 'Demo City', 'shipping_postcode' => '1000', 'subtotal' => 1, 'total' => 1,
        ]);
        $order->products()->create(['name' => 'Demo', 'sku' => 'DEMO-001', 'image' => $image, 'price' => 1, 'quantity' => 1, 'total' => 1]);

        return $order;
    }

    public function test_an_image_in_use_is_not_deleted_and_its_users_are_listed(): void
    {
        $path = 'media/products/demo-001.jpg';
        $this->putImage($path);
        $id = $this->product('demo-001', $path);
        $this->category('demo', $path);
        DB::table('product_images')->insert(['product_id' => $id, 'path' => $path, 'sort_order' => 0]);

        $response = $this->postJson(route('admin.media.delete'), ['paths' => [$path]])
            ->assertStatus(409)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', '1 image is in use and was not deleted.')
            ->assertJsonPath('deleted', []);

        // Read the array, not a dotted path: the key is a path with dots in it.
        $this->assertSame(
            [['label' => 'products', 'count' => 1], ['label' => 'categories', 'count' => 1]],
            $response->json('inUse')[$path],
        );

        Storage::disk('public')->assertExists($path);
        $this->assertSame($path, DB::table('products')->where('id', $id)->value('image'));
        $this->assertSame(1, DB::table('product_images')->where('product_id', $id)->count());
    }

    public function test_unused_images_in_the_same_request_are_still_deleted(): void
    {
        foreach (['demo-001', 'demo-002', 'demo-003', 'Demo-003'] as $name) {
            $this->putImage('media/products/' . $name . '.jpg');
        }
        Storage::disk('public')->put('media/products/demo-003_thumb.jpg', 'thumb');
        $this->product('one', 'media/products/demo-001.jpg');
        $this->product('two', 'media/products/demo-002.jpg');
        // Its case twin is in use; this one is not.
        $this->product('three', 'media/products/Demo-003.jpg');

        $response = $this->postJson(route('admin.media.delete'), [
            'paths' => ['media/products/demo-001.jpg', 'media/products/demo-002.jpg', 'media/products/demo-003.jpg'],
        ])->assertStatus(409)
            ->assertJsonPath('message', '2 images are in use and were not deleted.')
            ->assertJsonPath('deleted', ['media/products/demo-003.jpg']);

        $this->assertSame(['media/products/demo-001.jpg', 'media/products/demo-002.jpg'], array_keys($response->json('inUse')));

        $disk = Storage::disk('public');
        $disk->assertMissing(['media/products/demo-003.jpg', 'media/products/demo-003_thumb.jpg']);
        $disk->assertExists(['media/products/demo-001.jpg', 'media/products/demo-002.jpg', 'media/products/Demo-003.jpg']);

        // When nothing is in use the answer is a plain success.
        $this->postJson(route('admin.media.delete'), ['paths' => ['media/products/Demo-003.jpg']])->assertStatus(409);
        DB::table('products')->where('slug', 'three')->update(['image' => null]);
        $this->postJson(route('admin.media.delete'), ['paths' => ['media/products/Demo-003.jpg']])
            ->assertOk()
            ->assertExactJson(['ok' => true, 'deleted' => ['media/products/Demo-003.jpg']]);
    }

    public function test_only_an_empty_folder_can_be_deleted(): void
    {
        $this->putImage('media/products/Demo/inner/demo-001.jpg');
        Storage::disk('public')->makeDirectory('media/products/demo/inner');

        $this->postJson(route('admin.media.delete'), ['paths' => ['media/products/demo', 'media/products/Demo']])
            ->assertStatus(422)
            ->assertExactJson(['ok' => false, 'message' => 'Empty the folder first.']);

        // Nothing in a refused request is deleted.
        Storage::disk('public')->assertExists(['media/products/demo/inner', 'media/products/Demo/inner/demo-001.jpg']);

        $this->postJson(route('admin.media.delete'), ['paths' => ['media/products/demo']])
            ->assertOk()
            ->assertExactJson(['ok' => true, 'deleted' => ['media/products/demo']]);

        Storage::disk('public')->assertMissing('media/products/demo');
        Storage::disk('public')->assertExists('media/products/Demo/inner/demo-001.jpg');

        // The library itself is never deleted, and nothing outside it is.
        Storage::disk('public')->put('bank-qr/payment.png', 'bytes');
        $this->postJson(route('admin.media.delete'), ['paths' => ['media', 'bank-qr/payment.png']])->assertOk()->assertJsonPath('deleted', []);
        Storage::disk('public')->assertExists(['media/products', 'bank-qr/payment.png']);
    }

    public function test_usage_lists_each_kind_of_user(): void
    {
        $path = 'media/banners/demo-001.jpg';
        $this->putImage($path);
        $this->product('one', $path);
        $this->product('two', $path);
        $this->category('demo', $path);
        DB::table('nav_menu_items')->insert(['label' => 'Demo', 'type' => 'banner', 'config' => '{"banner":"media/banners/demo-001.jpg"}']);
        DB::table('settings')->insert(['group' => 'theme', 'key' => 'hero', 'value' => $path]);
        // A past order keeps its copy but is not a use.
        $this->order($path);

        $this->getJson(route('admin.media.usage', ['path' => $path]))
            ->assertOk()
            ->assertExactJson([
                'ok' => true,
                'path' => $path,
                'uses' => [
                    ['label' => 'products', 'count' => 2],
                    ['label' => 'categories', 'count' => 1],
                    ['label' => 'menus', 'count' => 1],
                    ['label' => 'settings', 'count' => 1],
                ],
                'total' => 5,
            ]);

        $this->getJson(route('admin.media.usage', ['path' => 'media/banners/Demo-001.jpg']))
            ->assertOk()
            ->assertJsonPath('uses', [])
            ->assertJsonPath('total', 0);

        $this->getJson(route('admin.media.usage', ['path' => 'bank-qr/payment.png']))->assertStatus(422);
    }

    public function test_an_order_line_whose_image_was_deleted_shows_the_placeholder(): void
    {
        $path = 'media/products/demo-001.jpg';
        $this->putImage($path);
        $order = $this->order($path);

        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee(storage_url($path), false);

        $this->postJson(route('admin.media.delete'), ['paths' => [$path]])
            ->assertOk()
            ->assertJsonPath('deleted', [$path]);

        // The line keeps its record of what was sold; the page shows the blank tile.
        $this->assertSame($path, $order->products()->value('image'));
        $this->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('v-thumb--blank', false)
            ->assertDontSee(storage_url($path), false);
    }
}
