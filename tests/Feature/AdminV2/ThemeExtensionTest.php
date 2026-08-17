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
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('homepage_sections'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('nav_menu_items'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('footer_columns'));
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
        $this->assertSame(0, \App\Models\HomepageSection::count());
    }

    public function test_nav_contribution_appears_in_extensions_group_only_while_enabled(): void
    {
        $user = User::factory()->create();

        $labels = fn () => collect(\App\Support\AdminNav::groups($user))
            ->firstWhere('label', 'Extensions')['items'] ?? [];

        $this->assertNotContains('Themes', collect($labels())->pluck('label')->all());

        $this->installAndBoot();

        $this->assertContains('Themes', collect($labels())->pluck('label')->all());
    }
}
