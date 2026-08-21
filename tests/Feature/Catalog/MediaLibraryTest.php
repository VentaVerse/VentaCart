<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Store;
use App\Models\User;
use App\Support\MediaLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * One library, one contract.
 *
 * Five hand-rolled browsers used to call the product image endpoints, each
 * with its own idea of what a safe path was and what root meant. root was
 * only ever a starting folder, never a boundary, so a product picker could
 * browse and delete the store's bank QR code. And the delete asked nothing
 * about references: one image in the real catalogue is on fourteen products.
 */
class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
    }

    private function putFile(string $path): void
    {
        Storage::disk('public')->put($path, 'bytes');
    }

    public function test_a_picker_cannot_reach_the_stores_own_assets(): void
    {
        $this->putFile('bank-qr/payment.png');
        $this->putFile('identity/logo.png');

        foreach (['bank-qr', 'identity', 'theme', '_tmp'] as $hidden) {
            $this->assertFalse(MediaLibrary::safePath($hidden), $hidden . ' must not be browsable');
            $this->assertFalse(MediaLibrary::safePath($hidden . '/anything.png'));
        }

        $this->getJson(route('admin.media.browse', ['root' => 'products', 'path' => 'bank-qr']))
            ->assertStatus(422);
    }

    public function test_traversal_is_still_refused(): void
    {
        foreach (['../secrets', 'products/../../etc', './hidden', '.env'] as $bad) {
            $this->assertFalse(MediaLibrary::safePath($bad), $bad . ' must be refused');
        }
    }

    public function test_deleting_says_what_it_cost_and_detaches_the_rows(): void
    {
        $this->putFile('products/shared.jpg');

        $a = Product::create(['store_id' => 1, 'name' => 'A', 'slug' => 'a', 'price' => 1, 'quantity' => 1, 'image' => 'products/shared.jpg']);
        $b = Product::create(['store_id' => 1, 'name' => 'B', 'slug' => 'b', 'price' => 1, 'quantity' => 1]);
        ProductImage::create(['product_id' => $b->id, 'path' => 'products/shared.jpg', 'sort_order' => 0]);

        $response = $this->postJson(route('admin.media.delete'), ['paths' => ['products/shared.jpg']])->assertOk();

        // Two products used it, and neither is left pointing at a file that
        // is gone. That was the whole hole: the live delete asked nothing.
        // Read the array, not a dotted path: the key IS a path with dots in it.
        $this->assertSame(2, $response->json('usedBy')['products/shared.jpg']);
        $this->assertNull($a->fresh()->image);
        $this->assertSame(0, ProductImage::where('path', 'products/shared.jpg')->count());
        Storage::disk('public')->assertMissing('products/shared.jpg');
    }

    public function test_browse_reports_how_many_products_use_each_file(): void
    {
        $this->putFile('products/used.jpg');
        $this->putFile('products/spare.jpg');

        $p = Product::create(['store_id' => 1, 'name' => 'A', 'slug' => 'a', 'price' => 1, 'quantity' => 1]);
        ProductImage::create(['product_id' => $p->id, 'path' => 'products/used.jpg', 'sort_order' => 0]);

        $files = collect($this->getJson(route('admin.media.browse', ['root' => 'products']))->assertOk()->json('files'))
            ->keyBy('name');

        $this->assertSame(1, $files['used.jpg']['usedBy']);
        $this->assertSame(0, $files['spare.jpg']['usedBy']);
    }

    /**
     * The old browse rewrote an empty path back to root, so the category
     * picker, whose folder did not exist, had both its exits lead back into
     * the same empty folder. Three clicks, three dead ends, no way out.
     */
    public function test_a_root_that_does_not_exist_yet_is_created_rather_than_trapping(): void
    {
        Storage::disk('public')->assertMissing('categories');

        $body = $this->getJson(route('admin.media.browse', ['root' => 'categories']))->assertOk()->json();

        $this->assertSame('categories', $body['path']);
        $this->assertNull($body['parent'], 'the root has no up-tile to offer');
        $this->assertSame([], $body['files']);
        Storage::disk('public')->assertExists('categories');
    }

    public function test_search_looks_through_the_whole_root_not_one_folder(): void
    {
        $this->putFile('products/wampler/tumnus.jpg');
        $this->putFile('products/boss/ds1.jpg');
        $this->putFile('brands/wampler-logo.jpg');

        $body = $this->getJson(route('admin.media.browse', ['root' => 'products', 'q' => 'wampler']))->assertOk()->json();

        $this->assertTrue($body['searching']);
        $this->assertSame(['tumnus.jpg'], collect($body['files'])->pluck('name')->all(), 'a folder deep in the root is reachable');
    }

    public function test_an_upload_keeps_the_merchants_filename(): void
    {
        $this->postJson(route('admin.media.upload'), [
            'files' => [UploadedFile::fake()->image('Hipshot Bridge Black.jpg')],
            'path' => 'products',
        ])->assertOk();

        // It used to become a UUID, and the filename is the only identifier
        // the grid shows.
        Storage::disk('public')->assertExists('products/hipshot-bridge-black.jpg');
    }

    public function test_two_uploads_of_one_name_do_not_overwrite_each_other(): void
    {
        foreach (range(1, 2) as $i) {
            $this->postJson(route('admin.media.upload'), [
                'files' => [UploadedFile::fake()->image('logo.jpg')],
                'path' => 'brands',
            ])->assertOk();
        }

        Storage::disk('public')->assertExists('brands/logo.jpg');
        Storage::disk('public')->assertExists('brands/logo-2.jpg');
    }

    public function test_renaming_a_folder_carries_every_stored_path_with_it(): void
    {
        $this->putFile('products/wampler/tumnus.jpg');

        $p = Product::create(['store_id' => 1, 'name' => 'A', 'slug' => 'a', 'price' => 1, 'quantity' => 1,
            'image' => 'products/wampler/tumnus.jpg']);
        ProductImage::create(['product_id' => $p->id, 'path' => 'products/wampler/tumnus.jpg', 'sort_order' => 0]);

        $this->postJson(route('admin.media.folder'), [
            'path' => 'products', 'name' => 'Wampler', 'from' => 'products/wampler',
        ])->assertOk();

        // A rename that leaves the database behind is a way to break every
        // product in the folder at once.
        $this->assertSame('products/Wampler/tumnus.jpg', $p->fresh()->image);
        $this->assertSame('products/Wampler/tumnus.jpg', ProductImage::first()->path);
        Storage::disk('public')->assertExists('products/Wampler/tumnus.jpg');
    }

    public function test_a_folder_cannot_land_on_an_existing_one(): void
    {
        Storage::disk('public')->makeDirectory('products/wampler');

        $this->postJson(route('admin.media.folder'), ['path' => 'products', 'name' => 'wampler'])
            ->assertStatus(422);
    }

    public function test_thumbnails_are_offered_and_originals_are_not_listed_twice(): void
    {
        $this->putFile('products/photo.jpg');
        $this->putFile('products/photo_thumb.jpg');

        $files = $this->getJson(route('admin.media.browse', ['root' => 'products']))->assertOk()->json('files');

        $this->assertCount(1, $files, 'a thumbnail is not a file the merchant chooses');
        $this->assertStringContainsString('photo_thumb.jpg', $files[0]['thumb']);
    }

    public function test_the_library_paginates_rather_than_painting_a_thousand_tiles(): void
    {
        for ($i = 0; $i < 130; $i++) {
            $this->putFile('products/p' . $i . '.jpg');
        }

        $body = $this->getJson(route('admin.media.browse', ['root' => 'products']))->assertOk()->json();

        $this->assertSame(130, $body['total']);
        $this->assertCount(120, $body['files']);
        $this->assertSame(2, $body['lastPage']);
    }
}
