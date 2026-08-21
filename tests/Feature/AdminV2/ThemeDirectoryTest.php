<?php

namespace Tests\Feature\AdminV2;

use App\Models\Setting;
use App\Models\User;
use App\Services\ExtensionManager;
use App\Services\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The theme extension's home is a DIRECTORY, not the editor.
 *
 * It used to open straight into colour controls, which made the platform look
 * like it had one possible appearance. A theme system's home should answer
 * "what is installed and what is running" first, and the editor should be
 * something you reach by choosing a theme.
 */
class ThemeDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ExtensionManager::class)->install('theme');
        app(ExtensionManager::class)->bootEnabled();
        $this->actingAs(User::factory()->create());
    }

    public function test_the_extension_home_lists_the_installed_themes(): void
    {
        $this->get(route('admin.theme.index'))
            ->assertOk()
            ->assertSee('Themes', false)
            ->assertSee('Venta', false)
            ->assertSee('resources/themes/venta', false);
    }

    /** Which theme is running is stated, not implied by button order. */
    public function test_the_running_theme_is_marked_live(): void
    {
        $this->get(route('admin.theme.index'))->assertOk()->assertSee('Live', false);
    }

    public function test_the_editor_has_its_own_address(): void
    {
        $this->get(route('admin.theme.customise'))
            ->assertOk()
            ->assertSee('Colors', false);
    }

    public function test_activating_an_installed_theme_points_the_storefront_at_it(): void
    {
        $this->post(route('admin.theme.activate'), ['theme' => 'venta'])->assertRedirect();

        app(ThemeService::class)->clearCache();
        $this->assertSame('venta', Setting::where('group', 'identity')->where('key', 'active_theme')->value('value'));
    }

    /** A slug nobody installed must not become the active theme. */
    public function test_activating_a_theme_that_does_not_exist_is_refused(): void
    {
        Setting::updateOrCreate(['group' => 'identity', 'key' => 'active_theme'], ['value' => 'venta']);

        $this->post(route('admin.theme.activate'), ['theme' => 'not-a-theme'])
            ->assertRedirect()
            ->assertSessionHas('error');

        app(ThemeService::class)->clearCache();
        $this->assertSame('venta', app(ThemeService::class)->getActiveTheme());
    }

    /**
     * Switching theme must not touch the layout. Placements are store data
     * keyed by page type; a region the new theme lacks stops rendering and is
     * reported, never deleted.
     */
    public function test_activating_a_theme_leaves_the_layout_alone(): void
    {
        \App\Models\LayoutPlacement::create([
            'page_type' => 'home', 'region' => 'content_top',
            'widget' => 'catalog.brands', 'title' => 'Kept',
            'sort_order' => 0, 'is_enabled' => true,
        ]);

        $this->post(route('admin.theme.activate'), ['theme' => 'venta'])->assertRedirect();

        $this->assertDatabaseHas('layout_placements', ['title' => 'Kept']);
    }
}
