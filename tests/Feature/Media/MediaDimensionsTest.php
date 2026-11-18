<?php

namespace Tests\Feature\Media;

use App\Models\MediaFile;
use App\Models\Store;
use App\Models\User;
use App\Support\MediaLibrary;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Every library image has its width and height on record, so the grid and
 * the details panel can show them without opening each file on every
 * request. Uploads record them at once; files that reached the disk some
 * other way are measured the first time they are browsed.
 */
class MediaDimensionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Demo Store']);
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
    }

    private function png(int $width, int $height): string
    {
        ob_start();
        imagepng(imagecreatetruecolor($width, $height));

        return (string) ob_get_clean();
    }

    public function test_an_upload_records_its_width_and_height(): void
    {
        $res = $this->postJson(route('admin.media.upload'), [
            'files' => [UploadedFile::fake()->image('demo-001.jpg', 640, 480)],
            'path' => 'media/products',
        ])->assertOk();

        $row = MediaFile::where('path', 'media/products/demo-001.jpg')->first();
        $this->assertNotNull($row);
        $this->assertSame([640, 480], [$row->width, $row->height]);
        $this->assertSame(640, $res->json('files.0.width'));
        $this->assertSame(480, $res->json('files.0.height'));
        $this->assertSame('media/products', $res->json('files.0.folder'));
    }

    public function test_browse_fills_dimensions_for_a_file_written_outside_the_library(): void
    {
        Storage::disk('public')->put('media/products/demo-001.png', $this->png(10, 20));

        $files = $this->getJson(route('admin.media.browse', ['path' => 'media/products']))->assertOk()->json('files');

        $this->assertSame(10, $files[0]['width']);
        $this->assertSame(20, $files[0]['height']);
        $this->assertSame(1, MediaFile::count());
        $this->assertSame([10, 20], [MediaFile::first()->width, MediaFile::first()->height]);
    }

    /**
     * The size limit applies to files that reached the disk some other way
     * too: browse leaves an oversized one unmeasured, as the command does.
     */
    public function test_browse_does_not_record_an_image_over_the_size_limit(): void
    {
        Storage::disk('public')->put('media/products/demo-wide.png', $this->png(MediaLibrary::MAX_SIDE + 1, 1));

        $files = $this->getJson(route('admin.media.browse', ['path' => 'media/products']))->assertOk()->json('files');

        $this->assertNull($files[0]['width']);
        $this->assertNull($files[0]['height']);
        $this->assertSame(0, MediaFile::count());
    }

    /**
     * Two requests can browse the same new file at once. Both miss the row,
     * both measure it, and the second insert must not fail on the unique path.
     */
    public function test_filling_dimensions_twice_at_once_does_not_fail(): void
    {
        $path = 'media/products/demo-001.png';
        Storage::disk('public')->put($path, $this->png(10, 20));

        // Another request writes the row just after this one has read and
        // found nothing.
        $raced = false;
        DB::listen(function (QueryExecuted $query) use (&$raced, $path) {
            if ($raced || ! str_starts_with(strtolower($query->sql), 'select') || ! str_contains($query->sql, 'media_files')) {
                return;
            }
            $raced = true;
            DB::table('media_files')->insert(['path' => $path, 'width' => 10, 'height' => 20, 'created_at' => now(), 'updated_at' => now()]);
        });

        $first = MediaLibrary::dimensions([$path]);
        $second = MediaLibrary::dimensions([$path]);

        $this->assertTrue($raced);
        $this->assertSame(['width' => 10, 'height' => 20], $first[$path]);
        $this->assertSame(['width' => 10, 'height' => 20], $second[$path]);
        $this->assertSame(1, MediaFile::where('path', $path)->count());
    }

    public function test_two_paths_differing_by_case_each_get_a_dimensions_row(): void
    {
        Storage::disk('public')->put('media/products/Demo/a.png', $this->png(10, 20));
        Storage::disk('public')->put('media/products/demo/a.png', $this->png(30, 40));

        $sizes = MediaLibrary::dimensions(['media/products/Demo/a.png', 'media/products/demo/a.png']);

        $this->assertSame(['width' => 10, 'height' => 20], $sizes['media/products/Demo/a.png']);
        $this->assertSame(['width' => 30, 'height' => 40], $sizes['media/products/demo/a.png']);
        $this->assertSame(2, MediaFile::count());
    }

    public function test_a_file_that_is_not_an_image_has_no_dimensions(): void
    {
        Storage::disk('public')->put('media/products/demo-001.jpg', 'not an image');

        $this->assertNull(MediaLibrary::dimensions(['media/products/demo-001.jpg'])['media/products/demo-001.jpg']);
        $this->assertSame(0, MediaFile::count());
    }

    public function test_an_image_larger_than_6000_pixels_is_refused(): void
    {
        $res = $this->postJson(route('admin.media.upload'), [
            'files' => [UploadedFile::fake()->image('demo-wide.png', 6001, 10)],
            'path' => 'media/products',
        ])->assertStatus(422);

        $this->assertStringContainsString('6000 x 6000 pixels', $res->json('message'));
        $this->assertSame([], Storage::disk('public')->allFiles('media/products'));
    }

    public function test_one_refused_file_stores_none_of_the_request(): void
    {
        // Its name says JPEG and a fake reports its type from the name, so
        // only the content check, which reads the bytes, refuses it.
        $disguised = UploadedFile::fake()->createWithContent('demo-002.jpg', 'plain text, not a photo');

        $this->postJson(route('admin.media.upload'), [
            'files' => [UploadedFile::fake()->image('demo-001.jpg', 40, 40), $disguised],
            'path' => 'media/products',
        ])->assertStatus(422);

        $this->assertSame([], Storage::disk('public')->allFiles('media/products'));
        $this->assertSame(0, MediaFile::count());
    }

    public function test_a_folder_name_may_not_start_with_a_dot(): void
    {
        $this->postJson(route('admin.media.folder'), ['path' => 'media/products', 'name' => '.hidden'])
            ->assertStatus(422);

        Storage::disk('public')->assertMissing('media/products/.hidden');
    }

    /**
     * The library refuses paths with "..", "./" or a leading dot, so a
     * folder named like that could be made but never opened or removed.
     */
    public function test_a_folder_name_the_library_could_not_open_is_refused(): void
    {
        $refused = [
            'a..b' => 'two dots in a row',
            'Sale...' => 'end with a dot',
            'Vol.' => 'end with a dot',
            "Demo\nSale" => 'hidden characters',
            "De\x00mo" => 'hidden characters',
            "Demo\tSale" => 'hidden characters',
            'Demo/Sale' => 'cannot contain',
        ];

        foreach ($refused as $name => $says) {
            $res = $this->postJson(route('admin.media.folder'), ['path' => 'media', 'name' => $name])
                ->assertStatus(422);
            $this->assertStringContainsString($says, $res->json('errors.name.0'), json_encode($name));
        }

        $this->assertSame([], Storage::disk('public')->directories('media'));
    }

    public function test_a_folder_name_is_kept_as_typed(): void
    {
        $this->postJson(route('admin.media.folder'), ['path' => 'media', 'name' => 'Summer Sale'])
            ->assertOk()->assertJsonPath('path', 'media/Summer Sale');

        $this->postJson(route('admin.media.folder'), ['path' => 'media', 'name' => 'Payday sale 2026'])
            ->assertOk()->assertJsonPath('path', 'media/Payday sale 2026');

        $this->postJson(route('admin.media.folder'), ['path' => 'media', 'name' => 'Vol. 2'])
            ->assertOk()->assertJsonPath('path', 'media/Vol. 2');

        $this->postJson(route('admin.media.folder'), ['path' => 'media', 'name' => str_repeat('a', 81)])
            ->assertStatus(422);
    }

    public function test_making_a_folder_refreshes_the_tree(): void
    {
        Cache::put('media.tree', ['stale'], 600);

        $this->postJson(route('admin.media.folder'), ['path' => 'media', 'name' => 'Demo'])->assertOk();

        $this->assertFalse(Cache::has('media.tree'));
    }

    public function test_deleting_an_image_removes_its_dimensions_row(): void
    {
        $this->postJson(route('admin.media.upload'), [
            'files' => [UploadedFile::fake()->image('demo-001.jpg', 64, 48)],
            'path' => 'media/products',
        ])->assertOk();
        $this->assertSame(1, MediaFile::count());

        $this->postJson(route('admin.media.delete'), ['paths' => ['media/products/demo-001.jpg']])->assertOk();

        $this->assertSame(0, MediaFile::count());
    }
}
