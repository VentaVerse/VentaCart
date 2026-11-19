<?php

namespace Tests\Feature\Media;

use App\Models\Store;
use App\Models\User;
use App\Support\MediaLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Folders are made only by writes, and only with names the library can open
 * and remove again. A GET can be sent by any page an admin has open, and an
 * admin who may only view must not be able to make folders by browsing.
 */
class MediaFolderWritesTest extends TestCase
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

    public function test_browsing_a_missing_folder_makes_nothing(): void
    {
        Storage::disk('public')->makeDirectory('media');

        $this->getJson(route('admin.media.browse', ['root' => 'demo-a/demo-b']))
            ->assertOk()
            ->assertJsonPath('root', MediaLibrary::ROOT)
            ->assertJsonPath('path', MediaLibrary::ROOT);

        $this->getJson(route('admin.media.tree'))->assertOk();

        Storage::disk('public')->assertMissing('media/demo-a');
        $this->assertSame([], Storage::disk('public')->allDirectories('media'));
    }

    public function test_the_tree_of_a_library_not_made_yet_makes_nothing(): void
    {
        $this->getJson(route('admin.media.tree'))->assertOk()->assertJsonPath('total', 0);

        Storage::disk('public')->assertMissing('media');
    }

    public function test_an_upload_into_a_missing_folder_checks_each_new_name(): void
    {
        $this->postJson(route('admin.media.upload'), [
            'files' => [UploadedFile::fake()->image('demo-001.jpg', 10, 10)],
            'path' => 'media/demo|folder',
        ])->assertStatus(422)->assertJsonPath('message', 'A folder name cannot contain / \\ : * ? " < > or |.');

        $this->postJson(route('admin.media.upload'), [
            'files' => [UploadedFile::fake()->image('demo-001.jpg', 10, 10)],
            'path' => 'media/products/demo.',
        ])->assertStatus(422)->assertJsonPath('message', 'A folder name cannot end with a dot.');

        $this->postJson(route('admin.media.upload'), [
            'files' => [UploadedFile::fake()->image('demo-001.jpg', 10, 10)],
            'path' => 'media/' . str_repeat('a', 81),
        ])->assertStatus(422);

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame([], Storage::disk('public')->allDirectories());
    }

    public function test_an_upload_into_a_missing_folder_with_good_names_makes_it_and_refreshes_the_tree(): void
    {
        Storage::disk('public')->makeDirectory('media');
        $this->getJson(route('admin.media.tree'))->assertOk();

        $this->postJson(route('admin.media.upload'), [
            'files' => [UploadedFile::fake()->image('demo-001.jpg', 10, 10)],
            'root' => 'products',
        ])->assertOk()->assertJsonPath('files.0.path', 'media/products/demo-001.jpg');

        $paths = array_column($this->getJson(route('admin.media.tree'))->json('folders'), 'path');
        $this->assertContains('media/products', $paths);
    }

    public function test_an_import_into_a_missing_folder_checks_each_new_name(): void
    {
        // Refused before anything is fetched.
        $this->postJson(route('admin.media.import-url'), [
            'url' => 'https://example.com/demo-001.png',
            'path' => 'media/demo<folder>',
        ])->assertStatus(422)->assertJsonPath('message', 'A folder name cannot contain / \\ : * ? " < > or |.');

        Storage::disk('public')->assertMissing('media/demo<folder>');
    }
}
