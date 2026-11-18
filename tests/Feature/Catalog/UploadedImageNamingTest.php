<?php

namespace Tests\Feature\Catalog;

use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Services\ImageUploadService;
use App\Support\MediaLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * An uploaded image is named by what its bytes are, never by the name the
 * browser sent. The public disk is served by the web server, so a file that
 * keeps a client-chosen ending such as .php, .pht or .html could be run as
 * code or rendered as a page on the store's own domain.
 *
 * Real uploaded files are used rather than fakes, because a fake reports its
 * content type from its name, which is exactly what must not be trusted.
 */
class UploadedImageNamingTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = Store::firstOrCreate(['code' => 'demo'], ['name' => 'Demo Store']);
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
    }

    private function upload(string $name, string $bytes): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function jpeg(): string
    {
        ob_start();
        imagejpeg(imagecreatetruecolor(40, 40));

        return (string) ob_get_clean();
    }

    private function png(): string
    {
        ob_start();
        imagepng(imagecreatetruecolor(40, 40));

        return (string) ob_get_clean();
    }

    private function assertNamedAsImage(string $path, string $extension): void
    {
        $this->assertSame($extension, pathinfo($path, PATHINFO_EXTENSION), $path);
        Storage::disk('public')->assertExists($path);
    }

    private function assertNothingStoredEndingIn(string ...$endings): void
    {
        foreach (Storage::disk('public')->allFiles() as $file) {
            foreach ($endings as $ending) {
                $this->assertStringEndsNotWith('.' . $ending, $file);
            }
        }
    }

    // ------------------------------------------------------------ media library

    public function test_media_library_names_a_jpeg_called_shell_php_as_jpg(): void
    {
        $path = MediaLibrary::store($this->upload('shell.php', $this->jpeg()), MediaLibrary::ROOT);

        $this->assertNamedAsImage($path, 'jpg');
        $this->assertNothingStoredEndingIn('php');
    }

    public function test_media_library_endpoint_names_a_jpeg_called_shell_html_as_jpg(): void
    {
        $response = $this->postJson(route('admin.media.upload'), [
            'files' => [$this->upload('shell.html', $this->jpeg()), $this->upload('shell.pht', $this->jpeg())],
        ])->assertOk();

        foreach ($response->json('files') as $file) {
            $this->assertNamedAsImage($file['path'], 'jpg');
        }
        $this->assertNothingStoredEndingIn('html', 'pht');
    }

    public function test_media_library_refuses_a_file_that_is_not_an_image(): void
    {
        try {
            MediaLibrary::store($this->upload('photo.jpg', '<?php echo 1;'), MediaLibrary::ROOT);
            $this->fail('A non-image must be refused.');
        } catch (ValidationException) {
        }

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_media_library_still_keeps_a_normal_png(): void
    {
        $path = MediaLibrary::store($this->upload('Red Pedal.png', $this->png()), MediaLibrary::ROOT);

        $this->assertNamedAsImage($path, 'png');
        $this->assertSame('red-pedal.png', basename($path));
    }

    // ------------------------------------------------------- image upload service

    public function test_upload_service_names_a_jpeg_called_shell_php_as_jpg(): void
    {
        $media = app(ImageUploadService::class)->upload($this->upload('shell.php', $this->jpeg()), $this->store->id);

        $this->assertNamedAsImage($media->path, 'jpg');
        $this->assertNothingStoredEndingIn('php');
    }

    public function test_upload_service_refuses_a_file_that_is_not_an_image(): void
    {
        try {
            app(ImageUploadService::class)->upload($this->upload('photo.jpg', 'plain text'), $this->store->id);
            $this->fail('A non-image must be refused.');
        } catch (ValidationException) {
        }

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_upload_service_still_keeps_a_normal_png(): void
    {
        $media = app(ImageUploadService::class)->upload($this->upload('photo.png', $this->png()), $this->store->id);

        $this->assertNamedAsImage($media->path, 'png');
    }

    // ------------------------------------------------- media library endpoint

    public function test_product_upload_names_a_jpeg_by_its_content(): void
    {
        foreach (['shell.html', 'shell.pht'] as $name) {
            $path = $this->postJson(route('admin.media.upload'), [
                'files' => [$this->upload($name, $this->jpeg())],
                'path' => 'media/products',
            ])->assertOk()->json('files.0.path');

            $this->assertNamedAsImage($path, 'jpg');
        }

        $this->postJson(route('admin.media.upload'), [
            'files' => [$this->upload('shell.php', $this->jpeg())],
            'path' => 'media/products',
        ])->assertStatus(422);

        $this->assertNothingStoredEndingIn('html', 'pht', 'php');
    }

    public function test_product_upload_refuses_a_file_that_is_not_an_image(): void
    {
        $this->postJson(route('admin.media.upload'), [
            'files' => [$this->upload('photo.jpg', 'plain text')],
            'path' => 'media/products',
        ])->assertStatus(422);

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_product_upload_still_keeps_a_normal_png(): void
    {
        $path = $this->postJson(route('admin.media.upload'), [
            'files' => [$this->upload('photo.png', $this->png())],
            'path' => 'media/products',
        ])->assertOk()->json('files.0.path');

        $this->assertNamedAsImage($path, 'png');
    }

    public function test_catalog_upload_names_a_jpeg_by_its_content(): void
    {
        $path = $this->postJson(route('admin.media.upload'), [
            'files' => [$this->upload('shell.html', $this->jpeg())],
            'path' => 'media/catalog',
        ])->assertOk()->json('files.0.path');

        $this->assertNamedAsImage($path, 'jpg');
        $this->assertNothingStoredEndingIn('html');
    }

    public function test_catalog_upload_refuses_a_non_image_and_keeps_a_normal_png(): void
    {
        $this->postJson(route('admin.media.upload'), [
            'files' => [$this->upload('photo.jpg', 'plain text')],
            'path' => 'media/catalog',
        ])->assertStatus(422);

        $path = $this->postJson(route('admin.media.upload'), [
            'files' => [$this->upload('photo.png', $this->png())],
            'path' => 'media/catalog',
        ])->assertOk()->json('files.0.path');

        $this->assertNamedAsImage($path, 'png');
    }

    // ------------------------------------------------------------ store identity

    public function test_logo_named_shell_php_is_stored_by_its_content(): void
    {
        $this->post(route('admin.settings.identity.update'), [
            'site_name' => 'Demo Store',
            'logo' => $this->upload('shell.php', $this->pngOfSize(480, 120)),
        ])->assertSessionHasNoErrors();

        $path = (string) Setting::where('group', 'identity')->where('key', 'logo')->value('value');
        $this->assertNamedAsImage($path, 'png');
        $this->assertNothingStoredEndingIn('php');
    }

    private function pngOfSize(int $width, int $height): string
    {
        ob_start();
        imagepng(imagecreatetruecolor($width, $height));

        return (string) ob_get_clean();
    }
}
