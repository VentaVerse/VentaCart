<?php

namespace Tests\Feature\Themes;

use App\Models\NavMenu;
use App\Models\Setting;
use App\Services\ExtensionManager;
use App\Services\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Upgrading a running store onto the storefront that ships with VentaCart:
 * its footer links become the footer menu, and a store whose theme can no
 * longer draw is moved onto the bundled theme. Both are safe on a fresh
 * install and safe to run twice. Core's tests bundle the sample theme, so
 * this runs with any bundled theme.
 */
class StorefrontUpgradeTest extends TestCase
{
    use RefreshDatabase;

    /** The store starts with no theme installed; each test decides. */
    protected bool $installsSampleTheme = false;

    private function migration(string $name): object
    {
        return require database_path("migrations/{$name}.php");
    }

    private function footerMenu(): void
    {
        $this->migration('2026_11_18_100000_move_footer_link_columns_into_a_footer_menu')->up();
    }

    private function switchTheme(): void
    {
        $this->migration('2026_11_18_110000_switch_storefront_to_the_bundled_theme')->up();
    }

    /** A store that has been upgraded before: its migrations ran in more than one batch. */
    private function anUpgradedStore(): void
    {
        DB::table('migrations')->insert(['migration' => '2026_01_01_000000_an_earlier_release', 'batch' => 2]);
    }

    private function live(): ?string
    {
        return Setting::where('group', 'identity')->where('key', 'active_theme')->value('value');
    }

    public function test_footer_link_columns_become_the_footer_menu_in_order(): void
    {
        $this->footerColumn(['title' => 'Help', 'type' => 'links', 'sort_order' => 2, 'is_enabled' => true, 'config' => ['links' => [
            ['label' => 'Returns', 'url' => '/returns'],
            ['label' => 'Bad', 'url' => 'javascript:alert(1)'],
        ]]]);
        $this->footerColumn(['title' => 'Information', 'type' => 'links', 'sort_order' => 1, 'is_enabled' => true, 'config' => ['items' => [
            ['label' => 'About us', 'url' => '/about'],
        ]]]);
        $this->footerColumn(['title' => 'Old', 'type' => 'links', 'sort_order' => 3, 'is_enabled' => false, 'config' => ['links' => [['label' => 'Hidden', 'url' => '/hidden']]]]);
        $this->footerColumn(['title' => 'Say hello', 'type' => 'text', 'sort_order' => 4, 'is_enabled' => true, 'config' => ['content' => 'Hi']]);

        $this->footerMenu();
        $this->footerMenu();

        $menu = NavMenu::where('key', 'footer')->firstOrFail();
        $this->assertSame(1, NavMenu::where('key', 'footer')->count());
        $this->assertSame(['About us', 'Returns'], $menu->items()->orderBy('sort_order')->pluck('label')->all());
        $this->assertSame(['/about', '/returns'], $menu->items()->orderBy('sort_order')->pluck('url')->all());
        $this->assertSame(['footer' => 2], [$menu->key => $menu->items()->whereNull('parent_id')->count()]);
    }

    public function test_a_store_with_no_footer_links_or_its_own_footer_menu_gets_none(): void
    {
        $this->footerMenu();
        $this->assertFalse(NavMenu::where('key', 'footer')->exists());

        $own = NavMenu::create(['key' => 'footer', 'name' => 'My footer']);
        $this->footerColumn(['title' => 'Help', 'type' => 'links', 'sort_order' => 1, 'is_enabled' => true, 'config' => ['links' => [['label' => 'Returns', 'url' => '/returns']]]]);
        $this->footerMenu();
        $this->assertSame(0, $own->items()->count(), 'A footer menu the merchant made is left as it is.');
    }

    public function test_a_store_whose_theme_folder_is_gone_moves_onto_the_bundled_theme(): void
    {
        $this->anUpgradedStore();
        Setting::updateOrCreate(['group' => 'identity', 'key' => 'active_theme'], ['value' => 'a-removed-theme']);
        DB::table('extensions')->insert(['name' => 'theme-a-removed-theme', 'version' => '1.0.0', 'enabled' => true, 'installed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $this->switchTheme();

        $this->assertSame(ThemeService::bundled(), $this->live());
        $this->assertSame('enabled', app(ExtensionManager::class)->state(ThemeService::registryName(ThemeService::bundled())));
        $this->withoutVite();
        $this->get('/')->assertOk();

        // Run again, nothing changes.
        $this->switchTheme();
        $this->assertSame(ThemeService::bundled(), $this->live());
    }

    public function test_a_store_on_a_theme_that_is_not_installed_moves_too(): void
    {
        $this->anUpgradedStore();
        $this->makeSecondTheme('kept-on-disk');
        Setting::updateOrCreate(['group' => 'identity', 'key' => 'active_theme'], ['value' => 'kept-on-disk']);

        $this->switchTheme();

        $this->assertSame(ThemeService::bundled(), $this->live());
    }

    public function test_a_store_whose_theme_still_draws_is_left_alone(): void
    {
        $this->anUpgradedStore();
        $this->makeSecondTheme('its-own-choice');
        app(ExtensionManager::class)->install('theme-its-own-choice');
        Setting::updateOrCreate(['group' => 'identity', 'key' => 'active_theme'], ['value' => 'its-own-choice']);

        $this->switchTheme();

        $this->assertSame('its-own-choice', $this->live());
        $this->assertSame('not_installed', app(ExtensionManager::class)->state(ThemeService::registryName(ThemeService::bundled())));
    }

    public function test_a_fresh_install_is_left_to_its_seeders(): void
    {
        Setting::updateOrCreate(['group' => 'identity', 'key' => 'active_theme'], ['value' => 'a-removed-theme']);

        $this->switchTheme();

        $this->assertSame('a-removed-theme', $this->live());
        $this->assertSame('not_installed', app(ExtensionManager::class)->state(ThemeService::registryName(ThemeService::bundled())));
    }

    private function makeSecondTheme(string $slug): void
    {
        $build = storage_path('framework/testing/themes/' . $slug);
        \Illuminate\Support\Facades\File::deleteDirectory($build);
        \Illuminate\Support\Facades\File::copyDirectory(base_path('tests/fixtures/themes/sample'), $build);
        $link = resource_path('themes/' . $slug);
        if (! is_link($link)) {
            symlink($build, $link);
        }
        $this->beforeApplicationDestroyed(function () use ($build, $link) {
            @unlink($link);
            \Illuminate\Support\Facades\File::deleteDirectory($build);
        });
        app()->forgetInstance(ExtensionManager::class);
    }

    /**
     * A footer column as the old Header & footer screen saved it, in the
     * table an upgraded store still has when this release's migrations run.
     */
    private function footerColumn(array $column): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('footer_columns')) {
            \Illuminate\Support\Facades\Schema::create('footer_columns', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('type', 30);
                $table->json('config')->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_enabled')->default(true);
            });
        }

        \Illuminate\Support\Facades\DB::table('footer_columns')->insert(['title' => $column['title'], 'type' => $column['type'],
            'config' => json_encode($column['config']), 'sort_order' => $column['sort_order'], 'is_enabled' => $column['is_enabled']]);
    }
}
