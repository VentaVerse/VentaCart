<?php

namespace Tests\Feature\Themes;

use App\Models\User;
use App\Services\ExtensionManager;
use App\Services\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Core runs with whatever themes are on disk, the bundled one included or
 * not: a store whose themes folder holds only another theme still boots
 * its admin, lists that theme, and shows the plain no-theme page until a
 * theme is installed.
 */
class CoreWithoutBundledThemeTest extends TestCase
{
    use RefreshDatabase;

    protected bool $installsSampleTheme = false;

    private string $themes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->themes = storage_path('framework/testing/themes-only-sample-' . bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($this->themes);
        symlink(base_path('tests/fixtures/themes/sample'), $this->themes . '/sample');
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($this->themes));

        config([
            'extensions.themes_path' => $this->themes,
            // The bundled theme's folder is not there.
            'themes.bundled' => 'a-theme-that-is-not-on-disk',
        ]);
        app()->forgetInstance(ExtensionManager::class);
    }

    public function test_the_admin_boots_and_lists_the_themes_on_disk(): void
    {
        $manager = app(ExtensionManager::class);
        $manager->install('theme');
        $manager->bootEnabled();
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.theme.index'))->assertOk()->assertSee('Sample')->assertSee('Install');
        $this->assertSame(['sample'], array_keys(app(ThemeService::class)->getAvailableThemes()));
    }

    public function test_the_storefront_answers_the_plain_page_until_a_theme_is_installed(): void
    {
        $this->get('/')->assertStatus(503)->assertSee('No theme installed');

        app(ExtensionManager::class)->install(ThemeService::registryName('sample'));
        app(ThemeService::class)->activateTheme('sample');
        app(ThemeService::class)->clearCache();
        $this->withoutVite();

        $this->get('/')->assertOk();
    }
}
