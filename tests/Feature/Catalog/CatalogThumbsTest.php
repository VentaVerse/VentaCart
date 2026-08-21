<?php

namespace Tests\Feature\Catalog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Backfilling the small versions the migrated catalogue never had.
 *
 * thumb_url() prefers a `_thumb` and falls back to the original, so this
 * command is what turns that preference into an actual saving. It has to be
 * safe to run twice and safe to interrupt, because it walks thousands of
 * files on a live store.
 */
class CatalogThumbsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function seedImage(string $path, int $w, int $h): void
    {
        $im = imagecreatetruecolor($w, $h);
        imagefilledrectangle($im, 0, 0, $w, $h, imagecolorallocate($im, 12, 90, 160));
        $tmp = tempnam(sys_get_temp_dir(), 'img') . '.jpg';
        imagejpeg($im, $tmp, 85);
        imagedestroy($im);
        Storage::disk('public')->put($path, file_get_contents($tmp));
        @unlink($tmp);
    }

    public function test_it_makes_the_missing_thumbnail(): void
    {
        $this->seedImage('products/big.jpg', 1200, 900);

        $this->artisan('catalog:thumbs')->assertSuccessful();

        Storage::disk('public')->assertExists('products/big_thumb.jpg');

        $this->assertLessThan(
            Storage::disk('public')->size('products/big.jpg'),
            Storage::disk('public')->size('products/big_thumb.jpg'),
            'a thumbnail that is not smaller is not a thumbnail'
        );
    }

    public function test_thumb_url_prefers_the_small_one_and_falls_back_when_there_is_none(): void
    {
        $this->seedImage('products/has.jpg', 1200, 900);
        $this->seedImage('products/none.jpg', 40, 40);

        $this->artisan('catalog:thumbs')->assertSuccessful();

        $this->assertStringContainsString('has_thumb.jpg', thumb_url('products/has.jpg'));
        // Already smaller than the target, so the original IS its own thumb.
        $this->assertStringContainsString('none.jpg', thumb_url('products/none.jpg'));
        $this->assertStringNotContainsString('_thumb', thumb_url('products/none.jpg'));
    }

    public function test_running_it_again_changes_nothing(): void
    {
        $this->seedImage('products/big.jpg', 1200, 900);

        $this->artisan('catalog:thumbs')->assertSuccessful();
        $first = Storage::disk('public')->lastModified('products/big_thumb.jpg');

        $this->artisan('catalog:thumbs')->assertSuccessful();

        $this->assertSame($first, Storage::disk('public')->lastModified('products/big_thumb.jpg'));
    }

    public function test_it_never_makes_a_thumbnail_of_a_thumbnail(): void
    {
        $this->seedImage('products/big.jpg', 1200, 900);

        $this->artisan('catalog:thumbs')->assertSuccessful();
        $this->artisan('catalog:thumbs')->assertSuccessful();

        Storage::disk('public')->assertMissing('products/big_thumb_thumb.jpg');
    }

    public function test_one_unreadable_file_does_not_stop_the_walk(): void
    {
        Storage::disk('public')->put('products/broken.jpg', 'not an image at all');
        $this->seedImage('products/fine.jpg', 1200, 900);

        $this->artisan('catalog:thumbs')->assertSuccessful();

        Storage::disk('public')->assertExists('products/fine_thumb.jpg');
        Storage::disk('public')->assertMissing('products/broken_thumb.jpg');
    }
}
