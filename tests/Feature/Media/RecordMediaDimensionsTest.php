<?php

namespace Tests\Feature\Media;

use App\Models\MediaFile;
use App\Support\MediaLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecordMediaDimensionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function png(int $width, int $height): string
    {
        ob_start();
        imagepng(imagecreatetruecolor($width, $height));

        return (string) ob_get_clean();
    }

    public function test_it_records_every_image_without_a_row_and_skips_thumbnails(): void
    {
        $disk = Storage::disk('public');
        $disk->put('media/products/demo-001.png', $this->png(10, 20));
        $disk->put('media/products/demo-001_thumb.png', $this->png(5, 10));
        $disk->put('media/blog/deep/demo-002.png', $this->png(30, 40));
        $disk->put('media/products/notes.txt', 'text');
        $disk->put('identity/logo.png', $this->png(8, 8));
        MediaFile::create(['path' => 'media/blog/deep/demo-002.png', 'width' => 1, 'height' => 1]);

        $this->artisan('media:dimensions')
            ->expectsOutput('Recorded 1 images, skipped 0 unreadable.')
            ->assertSuccessful();

        $this->assertSame(['media/blog/deep/demo-002.png', 'media/products/demo-001.png'], MediaFile::orderBy('path')->pluck('path')->all());
        $row = MediaFile::where('path', 'media/products/demo-001.png')->first();
        $this->assertSame([10, 20], [$row->width, $row->height]);
        $this->assertSame(1, MediaFile::where('path', 'media/blog/deep/demo-002.png')->value('width'));
    }

    public function test_it_works_in_more_than_one_chunk(): void
    {
        $disk = Storage::disk('public');
        $bytes = $this->png(2, 3);
        for ($i = 0; $i < 450; $i++) {
            $disk->put("media/bulk/demo-{$i}.png", $bytes);
        }

        $this->artisan('media:dimensions')->expectsOutput('Recorded 450 images, skipped 0 unreadable.')->assertSuccessful();

        $this->assertSame(450, MediaFile::count());
    }

    public function test_running_it_twice_changes_nothing(): void
    {
        Storage::disk('public')->put('media/products/demo-001.png', $this->png(10, 20));

        $this->artisan('media:dimensions')->expectsOutput('Recorded 1 images, skipped 0 unreadable.');
        $before = MediaFile::first()->only(['id', 'path', 'width', 'height', 'updated_at']);

        $this->artisan('media:dimensions')->expectsOutput('Recorded 0 images, skipped 0 unreadable.');

        $this->assertSame(1, MediaFile::count());
        $this->assertEquals($before, MediaFile::first()->only(['id', 'path', 'width', 'height', 'updated_at']));
    }

    public function test_a_case_twin_with_a_row_does_not_hide_its_sibling(): void
    {
        $disk = Storage::disk('public');
        $disk->put('media/products/Demo/a.png', $this->png(4, 4));
        $disk->put('media/products/demo/a.png', $this->png(6, 6));
        MediaFile::create(['path' => 'media/products/Demo/a.png', 'width' => 4, 'height' => 4]);

        $this->artisan('media:dimensions')->assertSuccessful();

        $this->assertSame(2, MediaFile::count());
        $this->assertSame(6, MediaFile::where('path', 'media/products/demo/a.png')->value('width'));
    }

    public function test_prune_removes_rows_for_missing_files(): void
    {
        Storage::disk('public')->put('media/products/demo-001.png', $this->png(10, 20));
        MediaFile::create(['path' => 'media/products/gone.png', 'width' => 5, 'height' => 5]);

        $this->artisan('media:dimensions')->assertSuccessful();
        $this->assertSame(2, MediaFile::count());

        $this->artisan('media:dimensions', ['--prune' => true])
            ->expectsOutput('Removed 1 rows whose file is gone.')
            ->assertSuccessful();

        $this->assertSame(['media/products/demo-001.png'], MediaFile::pluck('path')->all());
    }

    public function test_an_unreadable_file_is_counted_not_fatal(): void
    {
        $disk = Storage::disk('public');
        $disk->put('media/products/broken.png', 'not an image');
        $disk->put('media/products/demo-001.png', $this->png(10, 20));

        $this->artisan('media:dimensions')
            ->expectsOutput('Recorded 1 images, skipped 1 unreadable.')
            ->assertSuccessful();

        $this->assertSame(['media/products/demo-001.png'], MediaFile::pluck('path')->all());
    }

    public function test_an_image_over_the_size_limit_is_skipped(): void
    {
        Storage::disk('public')->put('media/products/huge.png', $this->png(MediaLibrary::MAX_SIDE + 1, 1));

        $this->artisan('media:dimensions')
            ->expectsOutput('Recorded 0 images, skipped 1 unreadable.')
            ->assertSuccessful();

        $this->assertSame(0, MediaFile::count());
    }
}
