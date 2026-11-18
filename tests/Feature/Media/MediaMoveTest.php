<?php

namespace Tests\Feature\Media;

use App\Models\MediaFile;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Store;
use App\Models\User;
use App\Support\Media\MediaReferenceSource;
use App\Support\Media\MediaReferences;
use App\Support\MediaLibrary;
use App\Support\NavTree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Renaming or moving an image or a folder changes every stored copy of its
 * path in the same transaction, past order lines included, so nothing is
 * left pointing at a file that is no longer there. When the database step
 * fails the files go back where they were, so disk and database never
 * disagree.
 */
class MediaMoveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Demo Store']);
        Storage::fake('public');
        Storage::disk('public')->makeDirectory('media/banners');
        Cache::flush();
        $this->actingAs(User::factory()->create());
    }

    private function putImage(string $path, bool $thumb = true): void
    {
        Storage::disk('public')->put($path, 'bytes');

        if ($thumb) {
            $info = pathinfo($path);
            Storage::disk('public')->put($info['dirname'] . '/' . $info['filename'] . '_thumb.' . $info['extension'], 'thumb');
        }
    }

    private function product(string $slug, ?string $image): int
    {
        return DB::table('products')->insertGetId([
            'store_id' => 1, 'name' => 'Demo ' . $slug, 'slug' => $slug,
            'price' => 1, 'quantity' => 1, 'image' => $image,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function orderLine(string $image): int
    {
        $status = OrderStatus::firstOrCreate(['store_id' => 1, 'name' => 'Pending'], ['is_default' => true, 'sort_order' => 1]);
        $order = Order::create([
            'store_id' => 1, 'order_status_id' => $status->id,
            'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'email' => 'juan@example.com', 'phone' => '1',
            'shipping_first_name' => 'Juan', 'shipping_last_name' => 'Dela Cruz', 'shipping_address_1' => 'Demo Street',
            'shipping_city' => 'Demo City', 'shipping_postcode' => '1000', 'subtotal' => 1, 'total' => 1,
        ]);

        return DB::table('order_products')->insertGetId([
            'order_id' => $order->id, 'name' => 'Demo', 'sku' => 'DEMO-001', 'image' => $image,
            'price' => 1, 'quantity' => 1, 'total' => 1,
        ]);
    }

    private function productImage(int $id): ?string
    {
        return DB::table('products')->where('id', $id)->value('image');
    }

    public function test_renaming_an_image_keeps_its_extension_and_repoints_products(): void
    {
        $this->putImage('media/products/demo-001.jpg');
        $id = $this->product('demo-001', 'media/products/demo-001.jpg');

        $this->postJson(route('admin.media.rename'), ['path' => 'media/products/demo-001.jpg', 'name' => 'Blue Shirt'])
            ->assertOk()
            ->assertExactJson(['ok' => true, 'path' => 'media/products/blue-shirt.jpg', 'updated' => ['products' => 1]]);

        $disk = Storage::disk('public');
        $disk->assertExists(['media/products/blue-shirt.jpg', 'media/products/blue-shirt_thumb.jpg']);
        $disk->assertMissing(['media/products/demo-001.jpg', 'media/products/demo-001_thumb.jpg']);
        $this->assertSame('media/products/blue-shirt.jpg', $this->productImage($id));

        // Typing the extension does not double it.
        $this->postJson(route('admin.media.rename'), ['path' => 'media/products/blue-shirt.jpg', 'name' => 'Red Shirt.JPG'])
            ->assertOk()
            ->assertJsonPath('path', 'media/products/red-shirt.jpg');
    }

    public function test_moving_a_folder_repoints_plain_and_json_references_and_order_lines(): void
    {
        $this->putImage('media/products/Demo/demo-001.jpg');
        $this->putImage('media/products/Demo/inner/demo-002.jpg');
        $path = 'media/products/Demo/demo-001.jpg';

        $product = $this->product('demo-001', $path);
        $menu = DB::table('nav_menu_items')->insertGetId([
            'label' => 'Demo', 'type' => 'banner', 'config' => '{"banner":"media\/products\/Demo\/demo-001.jpg","title":"Demo"}',
        ]);
        DB::table('settings')->insert(['group' => 'theme', 'key' => 'gallery', 'value' => '["media/products/Demo/inner/demo-002.jpg"]']);
        $line = $this->orderLine($path);
        // Stored dimensions follow the file, and a row left at the target by
        // a file removed outside the library does not block the move.
        MediaFile::query()->insert([
            ['path' => $path, 'width' => 10, 'height' => 20, 'created_at' => now(), 'updated_at' => now()],
            ['path' => 'media/banners/Demo/demo-001.jpg', 'width' => 1, 'height' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Cache::put(MediaLibrary::TREE_CACHE_KEY, ['stale'], 600);
        Cache::put(NavTree::CACHE_PREFIX . 'main', ['stale']);

        $this->postJson(route('admin.media.move'), ['paths' => ['media/products/Demo'], 'to' => 'media/banners'])
            ->assertOk()
            ->assertExactJson([
                'ok' => true,
                'moved' => ['media/products/Demo' => 'media/banners/Demo'],
                'updated' => ['products' => 1, 'menus' => 1, 'settings' => 1, 'past orders' => 1, 'library' => 1],
            ]);

        $disk = Storage::disk('public');
        $disk->assertExists(['media/banners/Demo/demo-001.jpg', 'media/banners/Demo/demo-001_thumb.jpg', 'media/banners/Demo/inner/demo-002.jpg']);
        $disk->assertMissing('media/products/Demo');

        $this->assertSame('media/banners/Demo/demo-001.jpg', $this->productImage($product));
        $this->assertSame(
            ['banner' => 'media/banners/Demo/demo-001.jpg', 'title' => 'Demo'],
            json_decode(DB::table('nav_menu_items')->where('id', $menu)->value('config'), true),
        );
        $this->assertSame('["media/banners/Demo/inner/demo-002.jpg"]', DB::table('settings')->where('key', 'gallery')->value('value'));
        $this->assertSame('media/banners/Demo/demo-001.jpg', DB::table('order_products')->where('id', $line)->value('image'));
        $this->assertSame(
            [['path' => 'media/banners/Demo/demo-001.jpg', 'width' => 10]],
            MediaFile::query()->get(['path', 'width'])->toArray(),
        );

        // The folder tree and the storefront's cached menus are read again.
        $this->assertFalse(Cache::has(MediaLibrary::TREE_CACHE_KEY));
        $this->assertFalse(Cache::has(NavTree::CACHE_PREFIX . 'main'));
    }

    public function test_a_failed_repoint_puts_the_files_back(): void
    {
        $this->putImage('media/products/demo-001.jpg');
        $this->putImage('media/products/demo-002.jpg');
        $first = $this->product('demo-001', 'media/products/demo-001.jpg');
        $second = $this->product('demo-002', 'media/products/demo-002.jpg');
        $line = $this->orderLine('media/products/demo-001.jpg');

        // A source that fails on everything but the first image, so a move
        // of two fails after the first has already moved on disk and been
        // repointed in the database.
        MediaReferences::register(new class implements MediaReferenceSource {
            public function label(): string
            {
                return 'failing';
            }

            public function countsAsUse(): bool
            {
                return false;
            }

            public function owners(array $paths): array
            {
                return [];
            }

            public function repoint(string $from, string $to): int
            {
                if ($from !== 'media/products/demo-001.jpg') {
                    throw new \RuntimeException('The database went away.');
                }

                return 0;
            }
        });

        $this->postJson(route('admin.media.move'), [
            'paths' => ['media/products/demo-001.jpg', 'media/products/demo-002.jpg'],
            'to' => 'media/banners',
        ])->assertStatus(500)->assertExactJson(['ok' => false, 'message' => 'Nothing was moved. Try again.']);

        $disk = Storage::disk('public');
        $disk->assertExists([
            'media/products/demo-001.jpg', 'media/products/demo-001_thumb.jpg',
            'media/products/demo-002.jpg', 'media/products/demo-002_thumb.jpg',
        ]);
        $disk->assertMissing(['media/banners/demo-001.jpg', 'media/banners/demo-001_thumb.jpg', 'media/banners/demo-002.jpg']);

        $this->assertSame('media/products/demo-001.jpg', $this->productImage($first));
        $this->assertSame('media/products/demo-002.jpg', $this->productImage($second));
        $this->assertSame('media/products/demo-001.jpg', DB::table('order_products')->where('id', $line)->value('image'));

        // A rename that fails the same way leaves the folder as it was.
        $this->postJson(route('admin.media.rename'), ['path' => 'media/products', 'name' => 'Catalog'])
            ->assertStatus(500);
        $disk->assertExists('media/products/demo-002.jpg');
        $disk->assertMissing('media/Catalog');
        $this->assertSame('media/products/demo-002.jpg', $this->productImage($second));
    }

    public function test_moving_a_folder_leaves_its_case_twin_alone(): void
    {
        $this->putImage('media/products/Demo/demo-001.jpg');
        $this->putImage('media/products/demo/demo-001.jpg');
        $upper = $this->product('upper', 'media/products/Demo/demo-001.jpg');
        $lower = $this->product('lower', 'media/products/demo/demo-001.jpg');
        $line = $this->orderLine('media/products/demo/demo-001.jpg');

        $this->postJson(route('admin.media.move'), ['paths' => ['media/products/Demo'], 'to' => 'media/banners'])
            ->assertOk()
            ->assertJsonPath('updated', ['products' => 1]);

        Storage::disk('public')->assertExists(['media/products/demo/demo-001.jpg', 'media/products/demo/demo-001_thumb.jpg']);
        Storage::disk('public')->assertExists('media/banners/Demo/demo-001.jpg');
        $this->assertSame('media/banners/Demo/demo-001.jpg', $this->productImage($upper));
        $this->assertSame('media/products/demo/demo-001.jpg', $this->productImage($lower));
        $this->assertSame('media/products/demo/demo-001.jpg', DB::table('order_products')->where('id', $line)->value('image'));
    }

    public function test_moving_a_folder_leaves_a_folder_whose_name_only_starts_the_same_alone(): void
    {
        $this->putImage('media/products/demo/demo-001.jpg');
        $this->putImage('media/products/demo-sale/demo-001.jpg');
        $this->putImage('media/products/demo_x/demo-001.jpg');
        $moved = $this->product('moved', 'media/products/demo/demo-001.jpg');
        $kept = $this->product('kept', 'media/products/demo-sale/demo-001.jpg');
        $wild = $this->product('wild', 'media/products/demo_x/demo-001.jpg');

        $this->postJson(route('admin.media.rename'), ['path' => 'media/products/demo', 'name' => 'Archive'])
            ->assertOk()
            ->assertExactJson(['ok' => true, 'path' => 'media/products/Archive', 'updated' => ['products' => 1]]);

        $this->assertSame('media/products/Archive/demo-001.jpg', $this->productImage($moved));
        $this->assertSame('media/products/demo-sale/demo-001.jpg', $this->productImage($kept));
        $this->assertSame('media/products/demo_x/demo-001.jpg', $this->productImage($wild));
        Storage::disk('public')->assertExists(['media/products/demo-sale/demo-001.jpg', 'media/products/demo_x/demo-001.jpg']);
    }

    public function test_a_folder_cannot_move_into_itself(): void
    {
        $this->putImage('media/products/Demo/demo-001.jpg');

        foreach (['media/products', 'media/products/Demo'] as $into) {
            $this->postJson(route('admin.media.move'), ['paths' => ['media/products'], 'to' => $into])
                ->assertStatus(422)
                ->assertExactJson(['ok' => false, 'message' => 'A folder cannot move into itself or a folder inside it.']);
        }

        Storage::disk('public')->assertExists('media/products/Demo/demo-001.jpg');
    }

    public function test_a_name_that_is_taken_is_refused(): void
    {
        $this->putImage('media/products/demo-001.jpg');
        $this->putImage('media/products/demo-002.jpg');
        $this->putImage('media/banners/demo-001.jpg');
        Storage::disk('public')->makeDirectory('media/products/demo');
        Storage::disk('public')->makeDirectory('media/products/archive');

        $this->postJson(route('admin.media.rename'), ['path' => 'media/products/demo-001.jpg', 'name' => 'demo-002'])
            ->assertStatus(422)
            ->assertExactJson(['ok' => false, 'message' => 'A file called demo-002.jpg is already in products.']);

        $this->postJson(route('admin.media.move'), ['paths' => ['media/products/demo-002.jpg', 'media/products/demo-001.jpg'], 'to' => 'media/banners'])
            ->assertStatus(422)
            ->assertExactJson(['ok' => false, 'message' => 'A file called demo-001.jpg is already in banners.']);

        $this->postJson(route('admin.media.rename'), ['path' => 'media/products/archive', 'name' => 'demo'])
            ->assertStatus(422)
            ->assertExactJson(['ok' => false, 'message' => 'A folder called demo is already here.']);

        // A refused request moves nothing, not even the items before the refused one.
        Storage::disk('public')->assertExists(['media/products/demo-001.jpg', 'media/products/demo-002.jpg', 'media/products/archive']);
        Storage::disk('public')->assertMissing('media/banners/demo-002.jpg');

        // The comparison is exact: Demo beside demo is a different name.
        $this->postJson(route('admin.media.rename'), ['path' => 'media/products/archive', 'name' => 'Demo'])
            ->assertOk()
            ->assertJsonPath('path', 'media/products/Demo');
        Storage::disk('public')->assertExists(['media/products/demo', 'media/products/Demo']);
    }

    public function test_the_library_root_cannot_be_renamed_or_moved(): void
    {
        $this->putImage('media/products/demo-001.jpg');
        Storage::disk('public')->put('bank-qr/payment.png', 'bytes');

        foreach (['media', '', '/'] as $root) {
            $this->postJson(route('admin.media.rename'), ['path' => $root === '' ? 'media/' : $root, 'name' => 'Pictures'])
                ->assertStatus(422)
                ->assertJsonPath('ok', false);
        }

        $this->postJson(route('admin.media.move'), ['paths' => ['media'], 'to' => 'media/products'])->assertStatus(422);

        // Nothing outside the library can be renamed, moved, or moved into.
        $this->postJson(route('admin.media.rename'), ['path' => 'bank-qr/payment.png', 'name' => 'demo'])->assertStatus(422);
        $this->postJson(route('admin.media.move'), ['paths' => ['bank-qr/payment.png'], 'to' => 'media'])->assertStatus(422);
        $this->postJson(route('admin.media.move'), ['paths' => ['media/products/demo-001.jpg'], 'to' => 'bank-qr'])->assertStatus(422);
        $this->postJson(route('admin.media.move'), ['paths' => ['media/products/demo-001.jpg'], 'to' => 'media/../bank-qr'])->assertStatus(422);

        Storage::disk('public')->assertExists(['media/products/demo-001.jpg', 'bank-qr/payment.png']);
    }

    public function test_a_folder_rename_keeps_the_name_as_typed(): void
    {
        $this->putImage('media/products/demo-001.jpg');
        $id = $this->product('demo-001', 'media/products/demo-001.jpg');

        $this->postJson(route('admin.media.rename'), ['path' => 'media/products', 'name' => '  Summer Sale_2026 '])
            ->assertOk()
            ->assertExactJson(['ok' => true, 'path' => 'media/Summer Sale_2026', 'updated' => ['products' => 1]]);

        Storage::disk('public')->assertExists('media/Summer Sale_2026/demo-001.jpg');
        $this->assertSame('media/Summer Sale_2026/demo-001.jpg', $this->productImage($id));

        // The folder-name rule applies, as it does when a folder is made.
        foreach (['..', 'a/b', 'demo.', ''] as $bad) {
            $this->postJson(route('admin.media.rename'), ['path' => 'media/Summer Sale_2026', 'name' => $bad])->assertStatus(422);
        }

        Storage::disk('public')->assertExists('media/Summer Sale_2026/demo-001.jpg');
    }

    /**
     * Other spellings of the library itself, or of a path outside it, are
     * refused by every write, so no request can rename, move or delete the
     * whole library while looking like a request for one folder in it.
     */
    public function test_other_spellings_of_the_library_are_refused_everywhere(): void
    {
        $this->putImage('media/products/demo-001.jpg');
        $disk = Storage::disk('public');
        $before = $disk->allFiles('media');

        $roots = ['media', 'media/', 'media//', '/media/'];
        $others = ['media/.', 'media/./', 'media/./x', 'media//x', 'media/..', 'media/a/..', 'media/products/..',
            './media', 'media//products', 'media/products/.'];

        foreach ([...$roots, ...$others] as $spelling) {
            $this->postJson(route('admin.media.delete'), ['paths' => [$spelling]])
                ->assertOk()
                ->assertJsonPath('deleted', []);
            $this->postJson(route('admin.media.rename'), ['path' => $spelling, 'name' => 'Pictures'])
                ->assertStatus(422)
                ->assertJsonPath('ok', false);
            $this->postJson(route('admin.media.move'), ['paths' => [$spelling], 'to' => 'media/banners'])
                ->assertStatus(422)
                ->assertJsonPath('ok', false);

            // The library with a trailing slash is a real place to move
            // into; every other spelling is no folder at all.
            if (! in_array($spelling, $roots, true)) {
                $this->postJson(route('admin.media.move'), ['paths' => ['media/products/demo-001.jpg'], 'to' => $spelling])
                    ->assertStatus(422)
                    ->assertJsonPath('ok', false);
            }
        }

        $this->assertTrue($disk->directoryExists('media'));
        $this->assertSame($before, $disk->allFiles('media'));
        $disk->assertExists(['media/products/demo-001.jpg', 'media/banners']);
        $disk->assertMissing('media/Pictures');
    }

    /** A folder that leads back to the library on disk is the library, whatever its name. */
    public function test_a_link_back_to_the_library_cannot_be_renamed_or_moved(): void
    {
        $this->putImage('media/products/demo-001.jpg');
        $disk = Storage::disk('public');
        symlink($disk->path('media'), $disk->path('media/products/loop'));

        $this->postJson(route('admin.media.rename'), ['path' => 'media/products/loop', 'name' => 'Pictures'])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
        $this->postJson(route('admin.media.move'), ['paths' => ['media/products/loop'], 'to' => 'media/banners'])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);

        $this->assertTrue(is_link($disk->path('media/products/loop')));
        $disk->assertExists(['media/products/demo-001.jpg', 'media/banners']);
        $disk->assertMissing(['media/products/Pictures', 'media/banners/loop']);
    }
}
