<?php

namespace Tests\Feature\Media;

use App\Models\Store;
use App\Models\User;
use App\Services\SafeUrlFetcher;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * An image imported from a web address is judged by its bytes, as an upload
 * is, never by the type the remote server claims: a server can label a page
 * image/png, or send an image too large to make a thumbnail of.
 */
class MediaImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Demo Store']);
        Storage::fake('public');
        Storage::disk('public')->makeDirectory('media/products');
        Cache::flush();
        $this->actingAs(User::factory()->create());
    }

    private function serve(string $type, string $body): void
    {
        $this->mock(SafeUrlFetcher::class)->shouldReceive('get')
            ->andReturn(new Response(new Psr7Response(200, ['Content-Type' => $type], $body)));
    }

    private function png(int $width, int $height): string
    {
        ob_start();
        imagepng(imagecreatetruecolor($width, $height));

        return (string) ob_get_clean();
    }

    private function import(string $url = 'https://example.com/demo-001.png'): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(route('admin.media.import-url'), ['url' => $url, 'path' => 'media/products']);
    }

    public function test_a_page_labelled_as_an_image_is_refused(): void
    {
        $this->serve('image/png', '<html><body>Not an image</body></html>');

        $this->import()->assertStatus(422)
            ->assertExactJson(['ok' => false, 'message' => 'That address did not return a JPG, PNG or WebP image.']);

        $this->assertSame([], Storage::disk('public')->files('media/products'));
    }

    public function test_the_extension_comes_from_the_bytes_not_the_label(): void
    {
        // A PNG sent as a JPEG, from an address that ends in .jpg.
        $this->serve('image/jpeg', $this->png(20, 10));

        $this->import('https://example.com/demo-001.jpg')->assertOk()
            ->assertJsonPath('files.0.path', 'media/products/demo-001.png')
            ->assertJsonPath('files.0.width', 20);
    }

    public function test_a_real_image_with_a_generic_label_imports(): void
    {
        $this->serve('application/octet-stream', $this->png(12, 12));

        $this->import()->assertOk()->assertJsonPath('files.0.path', 'media/products/demo-001.png');
    }

    public function test_a_gif_is_refused(): void
    {
        $this->serve('image/png', (string) base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));

        $this->import()->assertStatus(422)
            ->assertJsonPath('message', 'That address did not return a JPG, PNG or WebP image.');
    }

    public function test_an_image_over_the_size_limit_is_refused(): void
    {
        $this->serve('image/png', $this->png(6001, 1));

        $this->import()->assertStatus(422)
            ->assertExactJson(['ok' => false, 'message' => 'That image is larger than 6000 x 6000 pixels. Make it smaller and import it again.']);

        $this->assertSame([], Storage::disk('public')->files('media/products'));
    }
}
