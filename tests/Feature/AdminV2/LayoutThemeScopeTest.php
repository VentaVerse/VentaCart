<?php

namespace Tests\Feature\AdminV2;

use App\Models\LayoutColumns;
use App\Models\LayoutPlacement;
use App\Models\Setting;
use App\Models\User;
use App\Services\ExtensionManager;
use App\Services\ThemeService;
use App\Support\Widgets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A layout belongs to the theme it was built for (owner ruling 2026-08-21).
 *
 * Placements used to be global, so switching theme left rows pointing at
 * regions the new theme had never declared and the editor had to warn about
 * orphans. The evidence for scoping is in the merchant's own legacy store,
 * where oc_layout_module still holds "journal2_static_banners.72" in a shop
 * running Journal 3: placements from a theme two versions dead, pointing at
 * content that no longer exists, with nothing to report them.
 */
class LayoutThemeScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ExtensionManager::class)->install('theme');
        app(ExtensionManager::class)->bootEnabled();
    }

    private function place(string $theme, string $title): LayoutPlacement
    {
        return LayoutPlacement::create([
            'theme' => $theme, 'page_type' => 'home', 'region' => 'content_top',
            'widget' => 'catalog.brands', 'title' => $title,
            'sort_order' => 0, 'is_enabled' => true,
        ]);
    }

    public function test_a_layout_built_for_one_theme_does_not_show_under_another(): void
    {
        $this->place('venta', 'Venta layout');
        $this->place('amplifier', 'Amplifier layout');

        $titles = LayoutPlacement::forRegion('home', 'content_top')->pluck('title')->all();

        $this->assertSame(['Venta layout'], $titles);
    }

    /** Switch away and back, and your layout is exactly as you left it. */
    public function test_another_themes_layout_waits_intact(): void
    {
        $this->place('amplifier', 'Amplifier layout');

        $this->assertCount(0, LayoutPlacement::forRegion('home', 'content_top'));
        $this->assertCount(1, LayoutPlacement::forRegion('home', 'content_top', 'amplifier'));
    }

    public function test_columns_are_scoped_too(): void
    {
        LayoutColumns::create(['theme' => 'venta', 'page_type' => 'cart', 'left_width' => 2, 'right_width' => 0]);
        LayoutColumns::create(['theme' => 'amplifier', 'page_type' => 'cart', 'left_width' => 0, 'right_width' => 3]);

        $this->assertSame(2, LayoutColumns::forPage('cart')['left']);
        $this->assertSame(3, LayoutColumns::forPage('cart', 'amplifier')['right']);
    }

    public function test_the_storefront_only_renders_the_running_themes_layout(): void
    {
        $this->place('amplifier', 'Not Mine');

        $this->get('/')->assertOk()->assertDontSee('Not Mine', false);
    }

    public function test_saving_writes_against_the_running_theme(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.theme.layout.update'), ['payload' => json_encode([
            'page_type' => 'home',
            'columns' => ['left' => 0, 'right' => 0],
            'placements' => [['id' => null, 'region' => 'content_top', 'widget' => 'catalog.brands', 'title' => 'Made here', 'enabled' => true]],
        ])])->assertRedirect();

        $this->assertDatabaseHas('layout_placements', ['title' => 'Made here', 'theme' => 'venta']);
    }

    /* ---------------- the editable layer ---------------- */

    /** The theme declares what a merchant may change on its hardcoded pages. */
    public function test_the_page_settings_come_from_the_theme(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.theme.layout', ['page' => 'product']))
            ->assertOk()
            ->assertSee('show_reviews', false)
            ->assertSee('Breadcrumbs', false);
    }

    /** A setting the theme scopes to one page type is not offered elsewhere. */
    public function test_a_product_only_setting_is_not_offered_on_the_cart(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.theme.layout', ['page' => 'cart']))
            ->assertOk()
            ->assertDontSee('show_reviews', false);
    }

    public function test_page_settings_save_and_come_back(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.theme.layout.update'), ['payload' => json_encode([
            'page_type' => 'product',
            'columns' => ['left' => 0, 'right' => 0],
            'settings' => ['show_reviews' => false],
            'placements' => [],
        ])])->assertRedirect();

        $this->assertFalse(LayoutColumns::forPage('product')['settings']['show_reviews']);
    }

    /* ---------------- widget settings ---------------- */

    /**
     * The gap that made the editor unusable: you could place a banner and never
     * say WHICH banner. A widget's choices come from the module that owns it.
     */
    public function test_a_widget_offers_its_own_live_choices(): void
    {
        app(ExtensionManager::class)->install('banners');
        app(ExtensionManager::class)->bootEnabled();

        $schema = Widgets::schema('banners.group');

        $this->assertNotEmpty($schema);
        $this->assertSame('banner_group', $schema[0]['name']);
        $this->assertIsArray($schema[0]['options']);
    }
}
