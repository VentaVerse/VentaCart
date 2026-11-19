<?php

namespace Tests\Feature\Media;

use App\Models\Store;
use App\Models\User;
use App\Support\MediaLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * New code can be deployed before `php artisan migrate` has made the
 * media_files table. Until then the library keeps working and only goes
 * without stored dimensions, instead of failing every request, or failing
 * after a file was already written or removed.
 */
class MediaBeforeMigrateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Demo Store']);
        Storage::fake('public');
        Storage::fake('local');
        Storage::disk('public')->makeDirectory('media/banners');
        Cache::flush();
        Schema::drop('media_files');
        $this->actingAs(User::factory()->create());
    }

    public function test_the_library_works_without_the_dimensions_table(): void
    {
        $this->assertFalse(MediaLibrary::hasFileTable());

        $this->postJson(route('admin.media.upload'), [
            'files' => [UploadedFile::fake()->image('demo-001.jpg', 40, 30)],
            'path' => 'media/products',
        ])->assertOk()->assertJsonPath('files.0.path', 'media/products/demo-001.jpg');

        $this->getJson(route('admin.media.browse', ['path' => 'media/products']))
            ->assertOk()
            ->assertJsonPath('files.0.path', 'media/products/demo-001.jpg')
            ->assertJsonPath('files.0.width', null);

        $this->postJson(route('admin.media.rename'), ['path' => 'media/products/demo-001.jpg', 'name' => 'demo-002'])
            ->assertOk()
            ->assertJsonPath('path', 'media/products/demo-002.jpg');

        $this->postJson(route('admin.media.move'), ['paths' => ['media/products/demo-002.jpg'], 'to' => 'media/banners'])
            ->assertOk();

        $this->postJson(route('admin.media.delete'), ['paths' => ['media/banners/demo-002.jpg']])
            ->assertOk()
            ->assertJsonPath('deleted', ['media/banners/demo-002.jpg']);

        Storage::disk('public')->assertMissing('media/banners/demo-002.jpg');
    }

    public function test_the_table_is_asked_for_once_per_request(): void
    {
        MediaLibrary::hasFileTable();

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        MediaLibrary::hasFileTable();
        MediaLibrary::dimensions(['media/products/demo-001.jpg', 'media/products/demo-002.jpg']);

        $this->assertSame(0, $queries);
    }
}
