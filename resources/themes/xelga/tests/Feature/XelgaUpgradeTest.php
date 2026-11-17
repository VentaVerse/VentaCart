<?php

namespace Themes\Xelga\Tests\Feature;

use App\Models\FooterColumn;
use App\Models\NavMenu;
use App\Models\NavMenuItem;
use App\Models\Setting;
use App\Services\ExtensionManager;
use App\Services\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Themes\Xelga\Documents\Defaults;
use Themes\Xelga\Preview\PreviewMode;
use Themes\Xelga\Rendering\Storefront;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';

/**
 * A running store upgraded onto Xelga keeps what its shoppers know: the
 * main menu in the header, its footer links in the footer, and its
 * announcement line in the announcement bar.
 */
class XelgaUpgradeTest extends TestCase
{
    use RefreshDatabase;
    use XelgaTestHelpers;

    protected bool $installsSampleTheme = false;

    public function test_a_store_whose_theme_is_gone_opens_on_xelga_with_its_menus_and_announcement(): void
    {
        Storage::fake('public');
        config(['themes.bundled' => (require config_path('themes.php'))['bundled']]);
        app()->forgetInstance(ExtensionManager::class);

        // A store upgraded before, on a theme this release no longer has.
        DB::table('migrations')->insert(['migration' => '2026_01_01_000000_an_earlier_release', 'batch' => 2]);
        Setting::updateOrCreate(['group' => 'identity', 'key' => 'active_theme'], ['value' => 'a-removed-theme']);
        Setting::updateOrCreate(['group' => 'header', 'key' => 'announcement_enabled'], ['value' => '1']);
        Setting::updateOrCreate(['group' => 'header', 'key' => 'announcement_text'], ['value' => 'Free delivery over 2,000 pesos']);
        Setting::updateOrCreate(['group' => 'header', 'key' => 'announcement_link'], ['value' => '/delivery']);
        NavMenuItem::create(['menu_id' => NavMenu::main()->id, 'label' => 'Guitars', 'type' => 'custom', 'url' => '/guitars', 'sort_order' => 1, 'is_enabled' => true]);
        FooterColumn::create(['title' => 'Help', 'type' => 'links', 'sort_order' => 1, 'is_enabled' => true, 'config' => ['links' => [['label' => 'Returns policy', 'url' => '/returns']]]]);

        (require database_path('migrations/2026_11_18_100000_move_footer_link_columns_into_a_footer_menu.php'))->up();
        (require database_path('migrations/2026_11_18_110000_switch_storefront_to_the_bundled_theme.php'))->up();

        $this->assertSame('xelga', Setting::where('group', 'identity')->where('key', 'active_theme')->value('value'));
        $this->assertSame('enabled', app(ExtensionManager::class)->state('theme-xelga'));

        app(ExtensionManager::class)->bootEnabled();
        app(ThemeService::class)->clearCache();
        Defaults::flush();
        PreviewMode::reset();
        Storefront::forgetRequestState();
        $this->withoutVite();

        $this->get('/')->assertOk()
            ->assertSee('data-section-type="header"', false)
            ->assertSee('Guitars')
            ->assertSee('Returns policy')
            ->assertSee('Free delivery over 2,000 pesos')
            ->assertSee('href="/delivery"', false)
            ->assertDontSee('Vireo Buds Pro, now on sale');
    }

    public function test_a_store_without_an_announcement_line_gets_the_shipped_header(): void
    {
        Storage::fake('public');
        $this->installXelga();

        $this->assertSame(0, DB::table('xelga_documents')->count());
    }
}
