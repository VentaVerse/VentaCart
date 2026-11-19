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
 * Every picker calls the same endpoints, so what counts as a safe path is
 * decided once. root is a starting folder, never a boundary, and the library
 * tree is the only boundary: nothing outside it, such as the store's payment
 * QR codes, can be browsed or deleted. An image that records use is not
 * deleted, since one image can be on many products.
 */
class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Demo Store']);
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
        foreach (['../secrets', 'products/../../etc', './hidden', '.env', 'media/.', 'media/./', 'media/a/..', './media', 'media//products', 'media/products/.', 'media/.hidden/x.jpg'] as $bad) {
            $this->assertFalse(MediaLibrary::safePath($bad), $bad . ' must be refused');
        }
    }

    /**
     * An allow list, not a deny list. A deny list only refuses the folders
     * somebody remembered, and the public disk grows whenever a package is
     * installed.
     */
    public function test_anything_that_is_not_a_media_root_is_refused(): void
    {
        foreach (['vendor/package/logo.png', 'livewire/x.js', 'storage', 'whatever-lands-here'] as $notMedia) {
            $this->assertFalse(MediaLibrary::safePath($notMedia), $notMedia . ' is not media');
        }

        // The library itself, and the folders inside it.
        $this->assertSame(MediaLibrary::ROOT, MediaLibrary::safePath(MediaLibrary::ROOT));

        foreach (MediaLibrary::DEFAULTS as $folder) {
            $path = MediaLibrary::ROOT . '/' . $folder;
            $this->assertSame($path, MediaLibrary::safePath($path));
        }

        // A folder the merchant made is media too, because the rule is the
        // tree rather than a list of names somebody has to maintain.
        $this->assertSame('media/summer-sale/x.jpg', MediaLibrary::safePath('media/summer-sale/x.jpg'));

        // A folder carried over from an earlier store is media like any other.
        $this->assertSame('media/catalog/old.jpg', MediaLibrary::safePath('media/catalog/old.jpg'));
    }

    public function test_deleting_refuses_an_image_in_use_and_never_detaches_the_rows(): void
    {
        $this->putFile('media/products/shared.jpg');

        $a = Product::create(['store_id' => 1, 'name' => 'A', 'slug' => 'a', 'price' => 1, 'quantity' => 1, 'image' => 'media/products/shared.jpg']);
        $b = Product::create(['store_id' => 1, 'name' => 'B', 'slug' => 'b', 'price' => 1, 'quantity' => 1]);
        ProductImage::create(['product_id' => $b->id, 'path' => 'media/products/shared.jpg', 'sort_order' => 0]);

        $response = $this->postJson(route('admin.media.delete'), ['paths' => ['media/products/shared.jpg']])->assertStatus(409);

        // Two products use it, so it stays, and so do the rows that point at
        // it: a product never loses its image because of a delete elsewhere.
        // Read the array, not a dotted path: the key IS a path with dots in it.
        $this->assertSame([['label' => 'products', 'count' => 2]], $response->json('inUse')['media/products/shared.jpg']);
        $this->assertSame('media/products/shared.jpg', $a->fresh()->image);
        $this->assertSame(1, ProductImage::where('product_id', $b->id)->count());
        Storage::disk('public')->assertExists('media/products/shared.jpg');
    }

    public function test_browse_reports_how_many_products_use_each_file(): void
    {
        $this->putFile('media/products/used.jpg');
        $this->putFile('media/products/spare.jpg');

        $p = Product::create(['store_id' => 1, 'name' => 'A', 'slug' => 'a', 'price' => 1, 'quantity' => 1]);
        ProductImage::create(['product_id' => $p->id, 'path' => 'media/products/used.jpg', 'sort_order' => 0]);

        $files = collect($this->getJson(route('admin.media.browse', ['root' => 'products']))->assertOk()->json('files'))
            ->keyBy('name');

        $this->assertSame(1, $files['used.jpg']['usedBy']['total']);
        $this->assertSame(0, $files['spare.jpg']['usedBy']['total']);
    }

    /**
     * The old browse rewrote an empty path back to root, so the category
     * picker, whose folder did not exist, had both its exits lead back into
     * the same empty folder. Three clicks, three dead ends, no way out.
     */
    public function test_a_folder_that_does_not_exist_yet_opens_the_library_without_creating_it(): void
    {
        Storage::disk('public')->assertMissing('media/categories');

        $body = $this->getJson(route('admin.media.browse', ['root' => 'categories']))->assertOk()->json();

        // Looking never writes: a missing folder is not made by a read, and
        // the picker opens the library itself instead, so it is never a dead
        // end and a category picker can still reach a photo under products.
        $this->assertSame(MediaLibrary::ROOT, $body['path']);
        Storage::disk('public')->assertMissing('media/categories');
    }

    /** Only the library itself has nowhere above it. */
    public function test_the_library_is_the_top(): void
    {
        $body = $this->getJson(route('admin.media.browse', ['path' => MediaLibrary::ROOT]))->assertOk()->json();

        $this->assertSame(MediaLibrary::ROOT, $body['path']);
        $this->assertNull($body['parent'], 'the library has no up-tile to offer');
    }

    /**
     * Nothing asserted on the folder list until a stale constant reference in
     * foldersIn() put a 500 on every browse call and the suite stayed green.
     */
    public function test_browse_returns_the_folders_inside_a_root(): void
    {
        $this->putFile('media/products/demo/a.jpg');
        $this->putFile('media/products/acme/b.jpg');
        $this->putFile('media/products/loose.jpg');

        $body = $this->getJson(route('admin.media.browse', ['root' => 'products']))->assertOk()->json();

        $this->assertSame(['acme', 'demo'], collect($body['folders'])->pluck('name')->all());
        $this->assertSame(['loose.jpg'], collect($body['files'])->pluck('name')->all());
    }

    public function test_search_looks_through_the_whole_root_not_one_folder(): void
    {
        $this->putFile('media/products/acme/demo-001.jpg');
        $this->putFile('media/products/demo/demo-002.jpg');
        $this->putFile('media/brands/acme-logo.jpg');

        $body = $this->getJson(route('admin.media.browse', ['root' => 'products', 'q' => 'acme']))->assertOk()->json();

        $this->assertTrue($body['searching']);
        $this->assertSame(['demo-001.jpg'], collect($body['files'])->pluck('name')->all(), 'a folder deep in the root is reachable');
    }

    public function test_an_upload_keeps_the_merchants_filename(): void
    {
        $this->postJson(route('admin.media.upload'), [
            'files' => [UploadedFile::fake()->image('Demo Bridge Black.jpg')],
            'path' => 'media/products',
        ])->assertOk();

        // It used to become a UUID, and the filename is the only identifier
        // the grid shows.
        Storage::disk('public')->assertExists('media/products/demo-bridge-black.jpg');
    }

    public function test_a_refused_upload_names_the_file_and_what_is_allowed(): void
    {
        $res = $this->postJson(route('admin.media.upload'), [
            'files' => [UploadedFile::fake()->create('notes.txt', 1, 'text/plain')],
            'path' => 'media/products',
        ])->assertStatus(422);

        $this->assertSame('notes.txt is not a photo. Use a JPG, PNG or WebP file.', $res->json('message'), 'One plain message, naming the file.');

        $this->postJson(route('admin.media.upload'), [
            'files' => [UploadedFile::fake()->image('demo-big.jpg')->size(6000)],
            'path' => 'media/products',
        ])->assertStatus(422)->assertJsonPath('message', 'demo-big.jpg is larger than 5 MB. Make it smaller and upload it again.');
    }

    public function test_two_uploads_of_one_name_do_not_overwrite_each_other(): void
    {
        foreach (range(1, 2) as $i) {
            $this->postJson(route('admin.media.upload'), [
                'files' => [UploadedFile::fake()->image('logo.jpg')],
                'path' => 'media/brands',
            ])->assertOk();
        }

        Storage::disk('public')->assertExists('media/brands/logo.jpg');
        Storage::disk('public')->assertExists('media/brands/logo-2.jpg');
    }

    /**
     * Making a folder never moves one. Renaming has its own request, which
     * changes every stored copy of the paths inside the folder too.
     */
    public function test_making_a_folder_ignores_a_folder_to_rename(): void
    {
        Storage::disk('public')->makeDirectory('media/products/demo');

        $this->postJson(route('admin.media.folder'), [
            'path' => 'media/products', 'name' => 'Demo', 'from' => 'media/products/demo',
        ])->assertOk();

        // 'from' is ignored, not honoured: the old folder is untouched and a
        // new empty one is created beside it.
        Storage::disk('public')->assertExists('media/products/demo');
        Storage::disk('public')->assertExists('media/products/Demo');
    }

    public function test_a_folder_cannot_land_on_an_existing_one(): void
    {
        Storage::disk('public')->makeDirectory('media/products/demo');

        $this->postJson(route('admin.media.folder'), ['path' => 'media/products', 'name' => 'demo'])
            ->assertStatus(422);
    }

    public function test_thumbnails_are_offered_and_originals_are_not_listed_twice(): void
    {
        $this->putFile('media/products/photo.jpg');
        $this->putFile('media/products/photo_thumb.jpg');

        $files = $this->getJson(route('admin.media.browse', ['root' => 'products']))->assertOk()->json('files');

        $this->assertCount(1, $files, 'a thumbnail is not a file the merchant chooses');
        $this->assertStringContainsString('photo_thumb.jpg', $files[0]['thumb']);
    }

    public function test_the_library_paginates_rather_than_painting_a_thousand_tiles(): void
    {
        for ($i = 0; $i < 130; $i++) {
            $this->putFile('media/products/p' . $i . '.jpg');
        }

        $body = $this->getJson(route('admin.media.browse', ['root' => 'products']))->assertOk()->json();

        $this->assertSame(130, $body['total']);
        $this->assertCount(60, $body['files']);
        $this->assertSame(3, $body['lastPage']);
    }
}
