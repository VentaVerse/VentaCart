<?php

namespace Tests\Feature\AdminV2;

use App\Models\Setting;
use App\Models\User;
use App\Services\ThemeService;
use App\Support\StoreIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The store's own marks: uploading them, replacing them, removing them,
 * and what the admin chrome shows when the setting and the disk disagree.
 */
class StoreIdentityAssetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
    }

    /** Write a setting the way a previous request would have, then re-resolve. */
    private function putSetting(string $key, ?string $value): void
    {
        Setting::updateOrCreate(['group' => 'identity', 'key' => $key], ['value' => $value]);
        $this->refreshIdentity();
    }

    private function refreshIdentity(): void
    {
        app(ThemeService::class)->clearCache();
        StoreIdentity::flush();
    }

    private function setting(string $key): ?string
    {
        return Setting::where('group', 'identity')->where('key', $key)->value('value');
    }

    /** A minimal but structurally valid 16x16 icon file. */
    private function icoFile(string $name = 'mark.ico'): UploadedFile
    {
        $payload = str_repeat("\x00", 64);
        $bytes = pack('vvv', 0, 1, 1)
            .pack('CCCCvvVV', 16, 16, 0, 0, 1, 32, strlen($payload), 22)
            .$payload;

        return UploadedFile::fake()->createWithContent($name, $bytes);
    }

    // ---------------------------------------------------------------- upload

    public function test_logo_upload_stores_the_file_and_writes_the_setting(): void
    {
        $this->post(route('admin.settings.identity.update'), [
            'site_name' => 'New Gear Day',
            'logo' => UploadedFile::fake()->image('anything.png', 480, 120),
        ])->assertRedirect(route('admin.settings.section', 'general'));

        $path = $this->setting('logo');

        $this->assertNotNull($path);
        $this->assertStringStartsWith('identity/', $path);
        $this->assertStringEndsWith('.png', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertSame('New Gear Day', $this->setting('site_name'));
    }

    public function test_favicon_upload_accepts_png_and_ico_and_safe_svg(): void
    {
        $this->post(route('admin.settings.identity.update'), [
            'site_name' => 'New Gear Day',
            'favicon' => UploadedFile::fake()->image('f.png', 64, 64),
        ])->assertSessionHasNoErrors();
        $this->assertStringEndsWith('.png', (string) $this->setting('favicon'));

        $this->post(route('admin.settings.identity.update'), [
            'site_name' => 'New Gear Day',
            'favicon' => $this->icoFile(),
        ])->assertSessionHasNoErrors();
        $this->assertStringEndsWith('.ico', (string) $this->setting('favicon'));

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">'
            .'<rect width="16" height="16" fill="#1E4368"/></svg>';
        $this->post(route('admin.settings.identity.update'), [
            'site_name' => 'New Gear Day',
            'favicon' => UploadedFile::fake()->createWithContent('m.svg', $svg),
        ])->assertSessionHasNoErrors();
        $this->assertStringEndsWith('.svg', (string) $this->setting('favicon'));
        Storage::disk('public')->assertExists($this->setting('favicon'));
    }

    public function test_extension_comes_from_the_bytes_not_the_client_filename(): void
    {
        // Real PNG bytes wearing a filename that wants to be executed.
        $this->post(route('admin.settings.identity.update'), [
            'site_name' => 'New Gear Day',
            'logo' => UploadedFile::fake()->image('shell.php.png', 64, 64),
        ])->assertSessionHasNoErrors();

        $path = (string) $this->setting('logo');
        $this->assertStringEndsWith('.png', $path);
        $this->assertStringNotContainsString('shell', $path);
        $this->assertStringNotContainsString('.php', $path);
    }

    // --------------------------------------------------------------- replace

    public function test_replacing_the_logo_deletes_the_previous_file(): void
    {
        $this->post(route('admin.settings.identity.update'), [
            'site_name' => 'New Gear Day',
            'logo' => UploadedFile::fake()->image('one.png', 300, 100),
        ]);
        $first = (string) $this->setting('logo');
        Storage::disk('public')->assertExists($first);

        $this->post(route('admin.settings.identity.update'), [
            'site_name' => 'New Gear Day',
            'logo' => UploadedFile::fake()->image('two.png', 300, 100),
        ]);
        $second = (string) $this->setting('logo');

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    // ---------------------------------------------------------------- remove

    public function test_removing_an_asset_clears_the_setting_and_deletes_the_file(): void
    {
        $this->post(route('admin.settings.identity.update'), [
            'site_name' => 'New Gear Day',
            'logo' => UploadedFile::fake()->image('one.png', 300, 100),
            'favicon' => UploadedFile::fake()->image('f.png', 32, 32),
        ]);
        $logo = (string) $this->setting('logo');
        $favicon = (string) $this->setting('favicon');

        $this->delete(route('admin.settings.identity.destroy', 'logo'))
            ->assertRedirect(route('admin.settings.section', 'general'));

        $this->assertNull($this->setting('logo'));
        Storage::disk('public')->assertMissing($logo);
        Storage::disk('public')->assertExists($favicon);

        $this->delete(route('admin.settings.identity.destroy', 'favicon'));
        $this->assertNull($this->setting('favicon'));
        Storage::disk('public')->assertMissing($favicon);
    }

    public function test_only_the_two_marks_are_removable(): void
    {
        $this->putSetting('site_name', 'New Gear Day');

        // The route constraint refuses anything else, so the request never
        // reaches the controller and no other identity row can be wiped.
        // (Laravel answers 405 rather than 404 here because the catch-all
        // storefront fallback claims the URI for GET.)
        $response = $this->delete('/'.config('admin.prefix', 'admin').'/settings/identity/site_name');

        $this->assertTrue($response->isClientError());
        $this->assertSame('New Gear Day', $this->setting('site_name'));
    }

    // -------------------------------------------------------------- rejection

    public function test_a_non_image_is_rejected(): void
    {
        $this->post(route('admin.settings.identity.update'), [
            'site_name' => 'New Gear Day',
            'logo' => UploadedFile::fake()->createWithContent('logo.png', '<?php echo "hi";'),
        ])->assertSessionHasErrors('logo');

        $this->assertNull($this->setting('logo'));
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_an_oversized_file_is_rejected(): void
    {
        $this->post(route('admin.settings.identity.update'), [
            'site_name' => 'New Gear Day',
            'logo' => UploadedFile::fake()->image('huge.png', 400, 120)->size(4096),
        ])->assertSessionHasErrors('logo');

        $this->assertNull($this->setting('logo'));
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_an_svg_carrying_script_is_rejected(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">'
            .'<script>alert(1)</script><rect width="16" height="16"/></svg>';

        $this->post(route('admin.settings.identity.update'), [
            'site_name' => 'New Gear Day',
            'favicon' => UploadedFile::fake()->createWithContent('bad.svg', $svg),
        ])->assertSessionHasErrors('favicon');

        $this->assertNull($this->setting('favicon'));
    }

    public function test_an_svg_carrying_an_event_handler_is_rejected(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">'
            .'<rect width="16" height="16" onload="alert(1)"/></svg>';

        $this->post(route('admin.settings.identity.update'), [
            'site_name' => 'New Gear Day',
            'favicon' => UploadedFile::fake()->createWithContent('bad.svg', $svg),
        ])->assertSessionHasErrors('favicon');
    }

    public function test_an_svg_logo_is_rejected_because_the_logo_is_raster_only(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><rect width="16" height="16"/></svg>';

        $this->post(route('admin.settings.identity.update'), [
            'site_name' => 'New Gear Day',
            'logo' => UploadedFile::fake()->createWithContent('logo.svg', $svg),
        ])->assertSessionHasErrors('logo');
    }

    public function test_a_favicon_far_larger_than_an_icon_is_rejected(): void
    {
        $this->post(route('admin.settings.identity.update'), [
            'site_name' => 'New Gear Day',
            'favicon' => UploadedFile::fake()->image('f.png', 1200, 1200),
        ])->assertSessionHasErrors('favicon');
    }

    public function test_site_name_is_still_required(): void
    {
        $this->post(route('admin.settings.identity.update'), ['site_name' => ''])
            ->assertSessionHasErrors('site_name');
    }

    // ----------------------------------------------------------------- chrome

    public function test_the_slab_renders_the_uploaded_logo(): void
    {
        Storage::disk('public')->put('identity/logo-x.png', 'binary');
        $this->putSetting('logo', 'identity/logo-x.png');
        $this->putSetting('site_name', 'New Gear Day');

        $html = (string) $this->blade('<x-admin.shell title="T">body</x-admin.shell>');

        $this->assertStringContainsString('storage/identity/logo-x.png', $html);
        $this->assertStringNotContainsString('v-slab__mark', $html);

        // A storefront logo is usually dark ink; the slab is azure. The
        // plate is what lets one upload serve both surfaces.
        $this->assertStringContainsString('v-logo-plate', $html);
    }

    public function test_the_slab_falls_back_to_the_mark_when_no_logo_is_set(): void
    {
        $this->putSetting('logo', null);
        $this->putSetting('site_name', 'New Gear Day');

        $html = (string) $this->blade('<x-admin.shell title="T">body</x-admin.shell>');

        $this->assertStringContainsString('v-slab__mark', $html);
        $this->assertStringContainsString('New Gear Day', $html);

        // The generated mark carries its own brass and must not be plated.
        $this->assertStringNotContainsString('v-logo-plate', $html);
    }

    public function test_the_slab_falls_back_when_the_file_is_missing_from_disk(): void
    {
        $this->putSetting('logo', 'identity/gone.png');
        $this->putSetting('site_name', 'New Gear Day');

        $html = (string) $this->blade('<x-admin.shell title="T">body</x-admin.shell>');

        $this->assertStringContainsString('v-slab__mark', $html);
        $this->assertStringNotContainsString('identity/gone.png', $html);
    }

    public function test_the_admin_head_links_the_favicon(): void
    {
        Storage::disk('public')->put('identity/fav.png', 'binary');
        $this->putSetting('favicon', 'identity/fav.png');

        $html = (string) $this->blade('<x-admin.shell title="T">body</x-admin.shell>');

        $this->assertStringContainsString('rel="icon"', $html);
        $this->assertStringContainsString('storage/identity/fav.png', $html);
    }

    // ------------------------------------------------------------- the screen

    public function test_the_general_screen_offers_both_controls_and_drops_the_theme_pointer(): void
    {
        $response = $this->get(route('admin.settings.section', 'general'));

        $response->assertOk()
            ->assertSee('name="logo"', false)
            ->assertSee('name="favicon"', false)
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertDontSee('managed in');
    }

    public function test_a_missing_file_shows_as_none_set_rather_than_a_broken_image(): void
    {
        $this->putSetting('logo', 'identity/gone.png');

        $response = $this->get(route('admin.settings.section', 'general'));

        $response->assertOk()
            ->assertSee('None set')
            ->assertDontSee('identity/gone.png');
    }

    public function test_a_present_asset_is_previewed_with_a_remove_control(): void
    {
        Storage::disk('public')->put('identity/logo-x.png', 'binary');
        $this->putSetting('logo', 'identity/logo-x.png');

        $response = $this->get(route('admin.settings.section', 'general'));

        $response->assertOk()
            ->assertSee('storage/identity/logo-x.png', false)
            ->assertSee('Remove logo');
    }
}
