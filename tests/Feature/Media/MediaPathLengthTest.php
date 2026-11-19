<?php

namespace Tests\Feature\Media;

use App\Models\Store;
use App\Models\User;
use App\Support\MediaLibrary;
use App\Services\SafeUrlFetcher;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Every column that stores an image path holds 255 characters. A longer
 * path is refused by a strict database and cut short by one that is not, so
 * the library never makes one: each way a path can come about refuses it
 * with a message the merchant can act on.
 */
class MediaPathLengthTest extends TestCase
{
    use RefreshDatabase;

    /** media/ + three folders of 80 characters: 248 characters. */
    private string $deep;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Demo Store']);
        Storage::fake('public');
        Storage::fake('local');
        Cache::flush();
        $this->actingAs(User::factory()->create());

        $this->deep = 'media/' . str_repeat('a', 80) . '/' . str_repeat('b', 80) . '/' . str_repeat('c', 80);
        Storage::disk('public')->makeDirectory($this->deep);
        Storage::disk('public')->makeDirectory('media/banners');
    }

    public function test_the_longest_path_matches_the_reference_columns(): void
    {
        $this->assertSame(255, MediaLibrary::PATH_MAX);
    }

    public function test_an_upload_whose_path_would_be_too_long_is_refused(): void
    {
        $this->postJson(route('admin.media.upload'), [
            'files' => [UploadedFile::fake()->image('demo-001.jpg', 10, 10)],
            'path' => $this->deep,
        ])->assertStatus(422)->assertExactJson([
            'ok' => false,
            'message' => 'demo-001.jpg has a name too long for this folder. Give it a shorter name or choose a folder nearer the top.',
        ]);

        $this->assertSame([], Storage::disk('public')->files($this->deep));

        // A short enough name still fits: 248 + "/a.jpg" is 254.
        $this->postJson(route('admin.media.upload'), [
            'files' => [UploadedFile::fake()->image('a.jpg', 10, 10)],
            'path' => $this->deep,
        ])->assertOk();
    }

    public function test_an_import_whose_path_would_be_too_long_is_refused(): void
    {
        ob_start();
        imagepng(imagecreatetruecolor(10, 10));
        $png = (string) ob_get_clean();

        $this->mock(SafeUrlFetcher::class)->shouldReceive('get')
            ->andReturn(new Response(new Psr7Response(200, ['Content-Type' => 'image/png'], $png)));

        $this->postJson(route('admin.media.import-url'), [
            'url' => 'https://example.com/demo-001.png',
            'path' => $this->deep,
        ])->assertStatus(422)->assertJsonPath('message', 'That image has a name too long for this folder. Give it a shorter name or choose a folder nearer the top.');

        $this->assertSame([], Storage::disk('public')->files($this->deep));
    }

    public function test_a_rename_whose_path_would_be_too_long_is_refused(): void
    {
        Storage::disk('public')->put($this->deep . '/a.jpg', 'bytes');

        $this->postJson(route('admin.media.rename'), ['path' => $this->deep . '/a.jpg', 'name' => 'demo-001'])
            ->assertStatus(422)
            ->assertExactJson(['ok' => false, 'message' => 'That name is too long.']);

        Storage::disk('public')->assertExists($this->deep . '/a.jpg');
    }

    public function test_a_folder_rename_counts_the_images_inside_it(): void
    {
        // 244 characters now; a folder name 19 characters longer makes 263,
        // though the folder's own path stays short.
        $inner = '/' . str_repeat('b', 80) . '/' . str_repeat('c', 80) . '/' . str_repeat('f', 70) . '.jpg';
        Storage::disk('public')->put('media/x' . $inner, 'bytes');

        $this->postJson(route('admin.media.rename'), ['path' => 'media/x', 'name' => str_repeat('y', 20)])
            ->assertStatus(422)
            ->assertExactJson(['ok' => false, 'message' => 'That name is too long.']);

        Storage::disk('public')->assertExists('media/x' . $inner);

        $this->postJson(route('admin.media.rename'), ['path' => 'media/x', 'name' => 'y'])->assertOk();
    }

    public function test_a_move_whose_path_would_be_too_long_is_refused(): void
    {
        Storage::disk('public')->put('media/banners/demo-001.jpg', 'bytes');

        $this->postJson(route('admin.media.move'), ['paths' => ['media/banners/demo-001.jpg'], 'to' => $this->deep])
            ->assertStatus(422)
            ->assertExactJson([
                'ok' => false,
                'message' => 'demo-001.jpg would sit too deep in ' . str_repeat('c', 80) . ' to be stored. Choose a folder nearer the top.',
            ]);

        Storage::disk('public')->assertExists('media/banners/demo-001.jpg');
    }

    public function test_a_new_folder_whose_path_would_be_too_long_is_refused(): void
    {
        $this->postJson(route('admin.media.folder'), ['path' => $this->deep, 'name' => 'demo-folder'])
            ->assertStatus(422)
            ->assertExactJson(['ok' => false, 'message' => 'That name is too long.']);
    }
}
