<?php

namespace Tests\Feature\Settings;

use App\Models\Setting;
use App\Models\User;
use App\Services\ThemeService;
use App\Support\StoreIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The logo and favicon are chosen from the media library and copied into the store identity folder. */
class IdentityFromLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
        app(ThemeService::class)->clearCache();
        StoreIdentity::flush();
    }

    private function png(string $path, int $width, int $height): void
    {
        ob_start();
        imagepng(imagecreatetruecolor($width, $height));
        Storage::disk('public')->put($path, (string) ob_get_clean());
    }

    private function setting(string $key): ?string
    {
        return Setting::where('group', 'identity')->where('key', $key)->value('value');
    }

    public function test_a_logo_chosen_from_the_library_is_copied_into_the_store_identity(): void
    {
        $this->png('media/branding/demo-logo.png', 480, 120);

        $this->post(route('admin.settings.identity.update'), [
            'site_name' => 'Demo Store',
            'logo_media' => 'media/branding/demo-logo.png',
        ])->assertSessionHasNoErrors();

        $path = $this->setting('logo');

        $this->assertNotNull($path);
        $this->assertMatchesRegularExpression('#^identity/logo-[A-Za-z0-9]{24}\.png$#', $path);
        Storage::disk('public')->assertExists($path);
        Storage::disk('public')->assertExists('media/branding/demo-logo.png');
        $this->assertSame(
            Storage::disk('public')->get('media/branding/demo-logo.png'),
            Storage::disk('public')->get($path)
        );
    }

    public function test_deleting_the_library_image_later_leaves_the_logo_in_place(): void
    {
        $this->png('media/branding/demo-logo.png', 480, 120);

        $this->post(route('admin.settings.identity.update'), [
            'site_name' => 'Demo Store',
            'logo_media' => 'media/branding/demo-logo.png',
        ])->assertSessionHasNoErrors();

        $path = $this->setting('logo');
        Storage::disk('public')->delete('media/branding/demo-logo.png');

        Storage::disk('public')->assertExists($path);
        $this->assertStringEndsWith($path, (string) StoreIdentity::logoUrl());
    }

    public function test_a_library_image_that_fails_the_logo_checks_is_refused(): void
    {
        $this->png('media/branding/tiny.png', 10, 10);

        $this->post(route('admin.settings.identity.update'), [
            'site_name' => 'Demo Store',
            'logo_media' => 'media/branding/tiny.png',
        ])->assertSessionHasErrors('logo_media');

        $this->assertNull($this->setting('logo'));
        $this->assertSame([], Storage::disk('public')->files('identity'));
    }

    public function test_a_path_outside_the_library_is_refused(): void
    {
        $this->png('bank-qr/demo.png', 480, 120);

        $this->post(route('admin.settings.identity.update'), [
            'site_name' => 'Demo Store',
            'logo_media' => 'bank-qr/demo.png',
        ])->assertSessionHasErrors('logo_media');

        $this->assertNull($this->setting('logo'));
    }

    public function test_a_favicon_can_still_be_uploaded_as_svg(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><rect width="16" height="16"/></svg>';

        $this->post(route('admin.settings.identity.update'), [
            'site_name' => 'Demo Store',
            'favicon' => UploadedFile::fake()->createWithContent('mark.svg', $svg),
        ])->assertSessionHasNoErrors();

        $path = $this->setting('favicon');
        $this->assertStringEndsWith('.svg', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_a_favicon_chosen_from_the_library_replaces_the_previous_one(): void
    {
        $this->png('media/branding/demo-icon.png', 64, 64);
        Storage::disk('public')->put('identity/favicon-old.png', 'x');
        Setting::create(['group' => 'identity', 'key' => 'favicon', 'value' => 'identity/favicon-old.png']);

        $this->post(route('admin.settings.identity.update'), [
            'site_name' => 'Demo Store',
            'favicon_media' => 'media/branding/demo-icon.png',
        ])->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing('identity/favicon-old.png');
        Storage::disk('public')->assertExists($this->setting('favicon'));
        Storage::disk('public')->assertExists('media/branding/demo-icon.png');
    }

    public function test_saving_general_settings_without_a_logo_still_works(): void
    {
        $this->post(route('admin.settings.identity.update'), [
            'site_name' => 'Demo Store',
            'logo_media' => '',
            'favicon_media' => '',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.settings.section', 'general'));

        $this->assertSame('Demo Store', $this->setting('site_name'));
        $this->assertNull($this->setting('logo'));
    }

    public function test_the_general_page_offers_the_library_and_an_icon_upload(): void
    {
        $html = $this->get(route('admin.settings.section', 'general'))->assertOk()->getContent();

        $this->assertStringContainsString('data-media-picker="logo_media"', $html);
        $this->assertStringContainsString('data-media-picker="favicon_media"', $html);
        $this->assertStringContainsString('Or upload an ICO or SVG file', $html);
        $this->assertStringNotContainsString('name="logo"', $html);
    }
}
