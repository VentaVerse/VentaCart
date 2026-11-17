<?php

namespace Tests\Feature\AdminV2;

use App\Models\Store;
use App\Models\User;
use App\Services\ExtensionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The theme EDITOR as an extension (owner ruling 2026-08-18). The theme
 * system itself stays core: its tables ship with core migrations and the
 * storefront renders regardless of this extension's state — disabling
 * only closes the editing surface.
 */
class ThemeExtensionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Store::create(['id' => 1, 'name' => 'Test Store', 'code' => 'test']);
    }

    protected function installAndBoot(): void
    {
        app(ExtensionManager::class)->install('theme');
        app(ExtensionManager::class)->bootEnabled();
    }

    public function test_installed_serves_the_editor_under_extensions(): void
    {
        $this->installAndBoot();
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.theme.index'))
            ->assertOk()
            ->assertSee('Theme')
            ->assertSee('Extensions');
    }

    public function test_theme_tables_are_core_and_exist_without_the_extension(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('nav_menus'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('nav_menu_items'));
    }

    public function test_not_installed_means_no_editor_routes(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.theme.index'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.theme.settings.update'));

        // The core currencies CRUD keeps its frozen admin.theme.currencies.*
        // names and never depends on the extension.
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('admin.theme.currencies.store'));
    }

    public function test_theme_rendering_plumbing_is_core_and_lives_without_the_extension(): void
    {
        // The storefront's look comes from ThemeService and the core
        // models; none of it depends on the editor extension being
        // installed.
        $service = app(\App\Services\ThemeService::class);

        $this->assertNotSame('', $service->getActiveTheme());
    }

    public function test_themes_is_one_entry_outside_every_group_while_installed(): void
    {
        $user = User::factory()->create();

        $labels = fn () => collect(\App\Support\AdminNav::groups($user))
            ->where('label', null)->flatMap(fn ($group) => $group['items'])->all();

        $this->assertNotContains('Themes', collect($labels())->pluck('label')->all());

        $this->installAndBoot();

        $this->assertContains('Themes', collect($labels())->pluck('label')->all());
    }

    /** The store always needs a theme, so the directory cannot be switched off or removed. */
    public function test_the_theme_directory_is_required(): void
    {
        $this->installAndBoot();
        $manager = app(\App\Services\ExtensionManager::class);

        $this->assertTrue($manager->isRequired('theme'));
        foreach (['disable', 'uninstall'] as $action) {
            try {
                $manager->{$action}('theme');
                $this->fail("The theme directory was {$action}d.");
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('part of the store', $e->getMessage());
            }
        }
        $this->assertSame('enabled', $manager->state('theme'));
        $this->assertNull(collect(\App\Support\AdminNav::groups(User::factory()->create()))->firstWhere('label', 'Storefront'));
        $this->assertSame('Themes', \App\Support\AdminNav::homeOf('theme'));
        $this->assertSame(['group' => null, 'item' => 'themes'], \App\Support\AdminNav::activeItemKey('/admin/extensions/theme/xelga/editor'));
        $this->assertSame(['Themes'], array_column(\App\Support\Breadcrumbs::forPath('/admin/extensions/theme'), 'label'));
    }
}
