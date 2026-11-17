<?php

namespace Tests\Feature\AdminV2;

use App\Models\Extension;
use App\Models\User;
use App\Services\ExtensionManager;
use App\Services\ThemeService;
use App\Support\ThemeContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\UsesSampleTheme;
use Tests\TestCase;

/**
 * Themes as installable packages: a theme folder is
 * discovered like an extension, installed against the contract, made live
 * from the Themes page, and a store with no installed theme shows core's
 * plain page.
 */
class ThemePackagesTest extends TestCase
{
    use RefreshDatabase, UsesSampleTheme;

    private function admin(): void
    {
        app(ExtensionManager::class)->install('theme');
        app(ExtensionManager::class)->bootEnabled();
        $this->actingAs(User::factory()->create());
    }

    /** Every theme on disk, the bundled one when it is there, and the sample core's tests run on. */
    public function test_the_shipped_themes_keep_the_contract(): void
    {
        $folders = array_filter(glob(resource_path('themes/*'), GLOB_ONLYDIR) ?: [], fn ($dir) => is_file($dir . '/theme.json'));
        $this->assertContains(resource_path('themes/sample'), $folders);

        foreach ($folders as $dir) {
            $this->assertSame([], ThemeContract::check($dir), basename($dir));
        }
    }

    public function test_the_contract_names_what_a_broken_package_lacks(): void
    {
        $dir = storage_path('framework/testing/theme-broken');
        File::deleteDirectory($dir);
        File::ensureDirectoryExists($dir . '/views');
        File::put($dir . '/theme.json', json_encode(['name' => 'Broken', 'version' => '0.1', 'regions' => ['header_top' => ['label' => 'Top']], 'settings' => ['thing' => ['type' => 'wizard', 'label' => 'Thing']]]));

        $problems = ThemeContract::check($dir);
        File::deleteDirectory($dir);

        $this->assertContains('css/theme.css is missing.', $problems);
        $this->assertContains('View [layouts.store] is missing.', $problems);
        $this->assertContains('Setting [thing] has an unknown type: wizard.', $problems);
        // Colors and fonts are the theme's own business, in its stylesheet.
        $this->assertFalse(collect($problems)->contains(fn ($p) => str_contains($p, 'colors') || str_contains($p, 'font')));
        // A theme draws its pages itself: nothing asks it for regions.
        $this->assertFalse(collect($problems)->contains(fn ($p) => str_contains($p, 'region')));
    }

    public function test_themes_are_discovered_as_packages_and_the_bundled_one_is_installed(): void
    {
        $this->makeSampleTheme();
        $bundled = ThemeService::bundled();
        $discovered = app(ExtensionManager::class)->discovered();
        $this->assertSame('theme', $discovered->get('theme-'.$bundled)['type']);
        $this->assertSame('theme', $discovered->get('theme-'.$this->sampleTheme)['type']);
        $this->assertTrue($discovered->get('theme-'.$bundled)['default_enabled']);
        $this->assertFalse($discovered->get('theme-'.$this->sampleTheme)['default_enabled']);

        $themes = app(ThemeService::class)->getAvailableThemes();
        $this->assertTrue($themes[$bundled]['installed']);
        $this->assertFalse($themes[$this->sampleTheme]['installed']);
        $this->assertArrayNotHasKey($this->sampleTheme, app(ThemeService::class)->getInstalledThemes());
    }

    public function test_a_theme_that_is_not_installed_cannot_be_made_live(): void
    {
        $this->makeSampleTheme();
        $this->expectException(\InvalidArgumentException::class);
        app(ThemeService::class)->activateTheme($this->sampleTheme);
    }

    public function test_the_themes_page_installs_and_uninstalls_a_package(): void
    {
        $this->makeSampleTheme();
        $this->admin();

        $this->get(route('admin.theme.index'))->assertOk()->assertSee('Not installed')->assertSee('Install');

        $this->post(route('admin.theme.package.install'), ['theme' => $this->sampleTheme])->assertRedirect()->assertSessionHas('success');
        $this->assertTrue(Extension::where('name', 'theme-'.$this->sampleTheme)->exists());
        $this->get(route('admin.theme.index'))->assertOk()->assertSee('Use this theme');

        $this->post(route('admin.theme.package.uninstall'), ['theme' => $this->sampleTheme])->assertRedirect()->assertSessionHas('success');
        $this->assertFalse(Extension::where('name', 'theme-'.$this->sampleTheme)->exists());
    }

    public function test_uninstalling_a_theme_takes_its_own_extensions_and_their_data_with_it(): void
    {
        $this->makeSampleTheme();
        $ext = resource_path('themes/'.$this->sampleTheme.'/extensions/notes');
        File::ensureDirectoryExists($ext.'/database/migrations');
        File::put($ext.'/extension.json', json_encode(['name' => 'sample-notes', 'title' => 'Sample Notes', 'version' => '1.0.0', 'type' => 'feature']));
        File::put($ext.'/database/migrations/2026_01_01_000000_create_sample_notes.php', <<<'PHP'
            <?php
            use Illuminate\Database\Migrations\Migration;
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;
            return new class extends Migration {
                public function up(): void { Schema::create('sample_notes', fn (Blueprint $t) => $t->id()); }
                public function down(): void { Schema::dropIfExists('sample_notes'); }
            };
            PHP);
        $this->admin();

        $this->post(route('admin.theme.package.install'), ['theme' => $this->sampleTheme])->assertSessionHas('success');
        app(ExtensionManager::class)->forgetRegistry();
        app(ExtensionManager::class)->install('sample-notes');
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('sample_notes'));

        $this->post(route('admin.theme.package.uninstall'), ['theme' => $this->sampleTheme])->assertRedirect()->assertSessionHas('success');

        $this->assertFalse(Extension::where('name', 'theme-'.$this->sampleTheme)->exists());
        $this->assertFalse(Extension::where('name', 'sample-notes')->exists(), 'The theme\'s own extension goes with it.');
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('sample_notes'), 'And so does its data.');
    }

    public function test_a_store_without_an_installed_theme_shows_the_plain_page_and_the_admin_still_works(): void
    {
        $this->admin();
        $this->get('/')->assertOk();

        $this->post(route('admin.theme.package.uninstall'), ['theme' => ThemeService::bundled()])->assertRedirect()->assertSessionHas('warning');

        $this->get('/')->assertStatus(503)->assertSee('No theme installed');
        $this->get(route('admin.theme.index'))->assertOk()->assertSee('Install');

        // A callback has nothing to do with how pages look: posts pass the gate.
        $this->post('/cart/add', [])->assertStatus(302);

        $this->post(route('admin.theme.package.install'), ['theme' => ThemeService::bundled()])->assertRedirect()->assertSessionHas('success');
        $this->get('/')->assertOk();
    }

    public function test_the_registry_row_carries_the_package_version(): void
    {
        $name = ThemeService::registryName(ThemeService::bundled());
        $manifest = json_decode((string) file_get_contents(ThemeService::root(ThemeService::bundled() . '/theme.json')), true);
        $row = Extension::where('name', $name)->first();
        $this->assertSame($manifest['version'], $row->version);
        $this->assertSame('enabled', app(ExtensionManager::class)->state($name));
    }
}
