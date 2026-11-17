<?php

namespace Tests\Feature\Catalog;

use App\Jobs\MakeImageRenditions;
use App\Support\ImageRenditions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Smaller copies of stored photos, offered through srcset so phones are not
 * sent the original. The page must never name a copy that does not exist,
 * must notice a replaced original, and must make missing copies without
 * making the shopper wait for them.
 */
class ImageRenditionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Queue::fake();
        // Nothing queued by an earlier test carries into this one.
        ImageRenditions::dispatchQueued();
        ImageRenditions::flush();
        Queue::fake();
    }

    private function seedImage(string $path, int $w, int $h, string $type = 'jpg'): void
    {
        $im = imagecreatetruecolor($w, $h);
        imagefilledrectangle($im, 0, 0, $w, $h, imagecolorallocate($im, 12, 90, 160));
        ob_start();
        $type === 'png' ? imagepng($im) : imagejpeg($im, null, 85);
        Storage::disk('public')->put($path, (string) ob_get_clean());
        imagedestroy($im);
    }

    public function test_it_makes_the_widths_smaller_than_the_original_and_names_them_in_srcset(): void
    {
        $this->seedImage('media/products/board.jpg', 1400, 1000);

        $this->assertSame(4, ImageRenditions::make('media/products/board.jpg'));

        $disk = Storage::disk('public');
        foreach ([320, 640, 1280, 1400] as $width) {
            $disk->assertExists("renditions/media/products/board.jpg.{$width}.webp");
        }
        $disk->assertMissing('renditions/media/products/board.jpg.1920.webp');
        $this->assertSame(640, getimagesize($disk->path('renditions/media/products/board.jpg.640.webp'))[0]);

        // The same-width WebP copy is smaller than the JPEG, so it stands in for it.
        $this->assertSame(
            '/storage/renditions/media/products/board.jpg.320.webp 320w, /storage/renditions/media/products/board.jpg.640.webp 640w, '
            . '/storage/renditions/media/products/board.jpg.1280.webp 1280w, /storage/renditions/media/products/board.jpg.1400.webp 1400w',
            image_srcset('media/products/board.jpg')
        );
    }

    public function test_a_webp_original_gets_no_same_width_copy_and_stays_in_srcset(): void
    {
        $im = imagecreatetruecolor(900, 600);
        ob_start();
        imagewebp($im);
        Storage::disk('public')->put('media/products/photo.webp', (string) ob_get_clean());

        $this->assertSame(2, ImageRenditions::make('media/products/photo.webp'));
        Storage::disk('public')->assertMissing('renditions/media/products/photo.webp.900.webp');
        $this->assertStringEndsWith('/storage/media/products/photo.webp 900w', image_srcset('media/products/photo.webp'));
    }

    public function test_a_huge_original_is_left_out_of_srcset(): void
    {
        $this->seedImage('media/blog/wide.png', 3000, 600, 'png');

        ImageRenditions::make('media/blog/wide.png');

        $srcset = image_srcset('media/blog/wide.png');
        $this->assertStringContainsString('wide.png.1920.webp 1920w', $srcset);
        $this->assertStringNotContainsString('/storage/media/blog/wide.png 3000w', $srcset);
    }

    public function test_a_photo_without_copies_draws_without_srcset_and_queues_them_after_the_response(): void
    {
        $this->seedImage('media/products/new.jpg', 1000, 800);

        $this->assertSame('', image_srcset('media/products/new.jpg'));
        Queue::assertNothingPushed();

        ImageRenditions::dispatchQueued();
        Queue::assertPushed(MakeImageRenditions::class, fn ($job) => $job->path === 'media/products/new.jpg');

        // Queued at most once an hour, however many pages meet it.
        ImageRenditions::flush();
        image_srcset('media/products/new.jpg');
        ImageRenditions::dispatchQueued();
        Queue::assertPushed(MakeImageRenditions::class, 1);
    }

    public function test_a_replaced_original_counts_as_having_no_copies(): void
    {
        $this->seedImage('media/products/swap.jpg', 1000, 800);
        ImageRenditions::make('media/products/swap.jpg');
        $this->assertNotSame('', image_srcset('media/products/swap.jpg'));

        touch(Storage::disk('public')->path('media/products/swap.jpg'), time() + 10);
        ImageRenditions::flush();

        $this->assertSame('', image_srcset('media/products/swap.jpg'));
    }

    public function test_small_photos_gifs_and_unsafe_paths_get_no_smaller_copies(): void
    {
        $this->seedImage('media/products/tiny.jpg', 200, 200);
        Storage::disk('public')->put('media/products/anim.gif', 'GIF89a');

        // No smaller widths; at most the same-width WebP copy.
        $this->assertLessThanOrEqual(1, ImageRenditions::make('media/products/tiny.jpg'));
        Storage::disk('public')->assertMissing('renditions/media/products/tiny.jpg.320.webp');
        $this->assertStringNotContainsString('320w', image_srcset('media/products/tiny.jpg'));
        $this->assertSame(0, ImageRenditions::make('media/products/anim.gif'));
        $this->assertSame('', image_srcset('../.env'));
        $this->assertSame('', image_srcset('renditions/media/products/tiny.jpg.320.webp'));
    }

    public function test_forget_removes_the_copies_and_the_command_backfills(): void
    {
        $this->seedImage('media/products/a.jpg', 1000, 800);
        $this->seedImage('media/products/b.jpg', 1000, 800);
        Storage::disk('public')->put('media/products/a_thumb.jpg', 'x');

        $this->artisan('images:renditions')->assertSuccessful();

        $disk = Storage::disk('public');
        $disk->assertExists('renditions/media/products/b.jpg.640.webp');
        $disk->assertMissing('renditions/media/products/a_thumb.jpg.json');

        ImageRenditions::forget('media/products/a.jpg');
        $disk->assertMissing('renditions/media/products/a.jpg.640.webp');
        $disk->assertMissing('renditions/media/products/a.jpg.1000.webp');
        $disk->assertMissing('renditions/media/products/a.jpg.json');
    }
}
