<?php

namespace Tests\Feature\Media;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Store;
use App\Models\User;
use App\Support\MediaLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The folder tree arrives in one request, every folder with the number of
 * images inside it and its subfolders, so the sidebar never asks folder by
 * folder. It is cached, and every write to the library clears that cache.
 */
class MediaTreeTest extends TestCase
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

    private function putFile(string $path): void
    {
        Storage::disk('public')->put($path, 'bytes');
    }

    /** @return array<string, array<string, mixed>> */
    private function tree(): array
    {
        return collect($this->getJson(route('admin.media.tree'))->assertOk()->json('folders'))
            ->keyBy('path')->all();
    }

    public function test_the_tree_lists_every_folder_with_counts_that_include_subfolders(): void
    {
        $this->putFile('media/products/a.jpg');
        $this->putFile('media/products/demo/b.jpg');
        $this->putFile('media/products/demo/b_thumb.jpg');
        Storage::disk('public')->makeDirectory('media/blog');

        $res = $this->getJson(route('admin.media.tree'))->assertOk();
        $folders = collect($res->json('folders'))->keyBy('path');

        $this->assertTrue($res->json('ok'));
        $this->assertSame(['path' => 'media', 'name' => 'Media', 'parent' => null, 'count' => 2], $folders['media']);
        $this->assertSame(['path' => 'media/products', 'name' => 'products', 'parent' => 'media', 'count' => 2], $folders['media/products']);
        $this->assertSame(1, $folders['media/products/demo']['count']);
        $this->assertSame('media/products', $folders['media/products/demo']['parent']);
        $this->assertSame(0, $folders['media/blog']['count'], 'an empty folder is still listed');
        $this->assertSame(2, $res->json('total'));
    }

    public function test_the_tree_lists_folders_in_natural_order_with_case_twins_both_listed(): void
    {
        foreach (['media/products/demo', 'media/products/Demo', 'media/products/item10', 'media/products/item9', 'media/products-archive'] as $dir) {
            Storage::disk('public')->makeDirectory($dir);
        }
        $this->putFile('media/products/Demo/a.jpg');

        $paths = collect($this->getJson(route('admin.media.tree'))->json('folders'))->pluck('path')->all();

        $this->assertSame('media', $paths[0]);
        $this->assertContains('media/products/Demo', $paths);
        $this->assertContains('media/products/demo', $paths);
        $this->assertLessThan(array_search('media/products/item10', $paths), array_search('media/products/item9', $paths));
        // A folder's subfolders follow it directly, before its next sibling.
        $this->assertLessThan(array_search('media/products-archive', $paths), array_search('media/products/item10', $paths));
        $this->assertSame(1, $this->tree()['media/products/Demo']['count']);
        $this->assertSame(0, $this->tree()['media/products/demo']['count']);
    }

    public function test_the_tree_is_one_cached_request(): void
    {
        $this->putFile('media/products/a.jpg');
        $this->assertSame(1, $this->tree()['media/products']['count']);

        // Written straight to the disk, so nothing told the library.
        $this->putFile('media/products/b.jpg');

        $this->assertSame(1, $this->tree()['media/products']['count']);
        $this->assertNotNull(Cache::get(MediaLibrary::TREE_CACHE_KEY));

        MediaLibrary::touched();
        $this->assertSame(2, $this->tree()['media/products']['count']);
    }

    public function test_every_library_write_refreshes_the_tree(): void
    {
        $this->assertSame(0, $this->tree()['media']['count']);

        $this->postJson(route('admin.media.upload'), [
            'files' => [UploadedFile::fake()->image('demo-001.jpg', 20, 20)],
            'path' => 'media/products',
        ])->assertOk();
        $this->assertSame(1, $this->tree()['media/products']['count']);

        $this->postJson(route('admin.media.folder'), ['path' => 'media/products', 'name' => 'demo'])->assertOk();
        $this->assertArrayHasKey('media/products/demo', $this->tree());

        $this->postJson(route('admin.media.delete'), ['paths' => ['media/products/demo-001.jpg']])->assertOk();
        $this->assertSame(0, $this->tree()['media/products']['count']);
    }

    public function test_search_returns_matching_folders_and_names_each_files_folder(): void
    {
        $this->putFile('media/products/demo/one.jpg');
        $this->putFile('media/products/demo/two.jpg');
        $this->putFile('media/banners/demo-001.jpg');
        $this->putFile('media/banners/other.jpg');

        $body = $this->getJson(route('admin.media.browse', ['path' => 'media', 'q' => 'demo']))->assertOk()->json();

        $this->assertTrue($body['searching']);
        $this->assertSame([['name' => 'demo', 'path' => 'media/products/demo', 'count' => 2]], $body['folders']);

        $files = collect($body['files'])->keyBy('name');
        $this->assertEqualsCanonicalizing(['one.jpg', 'two.jpg', 'demo-001.jpg'], $files->keys()->all());
        $this->assertSame('media/products/demo', $files['one.jpg']['folder']);
        $this->assertSame('media/banners', $files['demo-001.jpg']['folder']);

        // The search matches names, not the whole path: every file sits under
        // media/, so "media" names no file and no folder.
        $none = $this->getJson(route('admin.media.browse', ['path' => 'media', 'q' => 'media']))->assertOk()->json();
        $this->assertSame([], $none['files']);
        $this->assertSame([], $none['folders']);

        // Kept for pickers that still read them.
        $this->assertArrayHasKey('root', $body);
        $this->assertArrayHasKey('crumbs', $body);
        $this->assertArrayHasKey('parent', $body);
    }

    public function test_browse_says_how_many_use_each_file_and_of_what_kind(): void
    {
        $this->putFile('media/products/demo-001.jpg');
        $this->putFile('media/products/demo-002.jpg');
        $this->putFile('media/products/spare.jpg');

        foreach (['a', 'b'] as $slug) {
            $p = Product::create(['store_id' => 1, 'name' => strtoupper($slug), 'slug' => $slug, 'price' => 1, 'quantity' => 1]);
            ProductImage::create(['product_id' => $p->id, 'path' => 'media/products/demo-001.jpg', 'sort_order' => 0]);
        }
        $p->update(['image' => 'media/products/demo-002.jpg']);
        Category::create(['store_id' => 1, 'name' => 'Demo', 'slug' => 'demo', 'image' => 'media/products/demo-002.jpg']);

        $files = collect($this->getJson(route('admin.media.browse', ['path' => 'media/products']))->assertOk()->json('files'))
            ->keyBy('name');

        $this->assertSame(['total' => 2, 'label' => 'products'], $files['demo-001.jpg']['usedBy']);
        $this->assertSame(['total' => 2, 'label' => null], $files['demo-002.jpg']['usedBy'], 'two kinds of user have no one label');
        $this->assertSame(['total' => 0, 'label' => null], $files['spare.jpg']['usedBy']);
    }

    public function test_browse_shows_sixty_images_a_page(): void
    {
        for ($i = 0; $i < 61; $i++) {
            $this->putFile('media/products/demo-' . $i . '.jpg');
        }

        $body = $this->getJson(route('admin.media.browse', ['path' => 'media/products', 'page' => 2]))->assertOk()->json();

        $this->assertSame(MediaLibrary::PER_PAGE, 60);
        $this->assertCount(1, $body['files']);
        $this->assertSame(2, $body['lastPage']);
    }

    public function test_browse_runs_the_same_number_of_queries_however_many_images_it_shows(): void
    {
        $count = function (int $images): int {
            Storage::fake('public');
            for ($i = 0; $i < $images; $i++) {
                $this->putFile('media/products/demo-' . $i . '.jpg');
            }
            $this->getJson(route('admin.media.browse', ['path' => 'media/products']))->assertOk();

            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson(route('admin.media.browse', ['path' => 'media/products']))->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $this->assertSame($count(2), $count(60));
    }
}
