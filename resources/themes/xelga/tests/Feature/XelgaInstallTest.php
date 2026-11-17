<?php

namespace Themes\Xelga\Tests\Feature;

use App\Services\ExtensionManager;
use App\Services\ThemeService;
use App\Support\ThemeContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Themes\Xelga\Tests\XelgaTestHelpers;
use Themes\Xelga\Catalog\CatalogLinter;

require_once __DIR__ . '/../XelgaTestHelpers.php';

/**
 * Installing creates the theme's three tables and nothing else; the package
 * keeps the theme contract; uninstalling takes the tables with it.
 */
class XelgaInstallTest extends TestCase
{
    use RefreshDatabase;
    use XelgaTestHelpers;

    private const TABLES = ['xelga_documents', 'xelga_revisions', 'xelga_assignments'];

    public function test_the_package_keeps_the_theme_contract(): void
    {
        $this->assertSame([], ThemeContract::check(resource_path('themes/xelga')));
    }

    public function test_install_creates_the_tables_and_the_theme_goes_live(): void
    {
        foreach (self::TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }

        $this->installXelga();

        foreach (self::TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "{$table} was not created.");
        }
        $this->assertSame('xelga', app(ThemeService::class)->getActiveTheme());
        $this->assertTrue(app(ThemeService::class)->keepsOwnStorage('xelga'));
        // Installing writes no documents: the shipped defaults are read from files.
        $this->assertSame(0, \Themes\Xelga\Documents\Document::count());
        // The starting store's photos are in the media library, and the
        // Clothes photos the section presets show.
        Storage::disk('public')->assertExists('media/xelga/gadgets/product-phone-pro-graphite.jpg');
        Storage::disk('public')->assertExists('media/xelga/clothes/slide-1-linen.jpg');
    }

    public function test_uninstall_drops_every_theme_table(): void
    {
        $this->installXelga(live: false);
        app(ExtensionManager::class)->uninstall(ThemeService::registryName('xelga'));

        foreach (self::TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), "{$table} was left behind.");
        }
    }

    public function test_the_catalog_and_shipped_defaults_pass_the_linter(): void
    {
        $this->installXelga(live: false);

        $this->assertSame([], CatalogLinter::check());
    }
}
