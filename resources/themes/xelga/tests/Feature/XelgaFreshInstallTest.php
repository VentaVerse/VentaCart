<?php

namespace Themes\Xelga\Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Services\ExtensionManager;
use App\Services\ThemeService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Themes\Xelga\Documents\Defaults;
use Themes\Xelga\Preview\PreviewMode;
use Themes\Xelga\Rendering\Storefront;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';

/**
 * A fresh VentaCart install runs Xelga, and its home page opens complete:
 * the Gadgets store's spotlight, category tiles and buttons point at the
 * products and categories the installer catalog seeded, with no empty
 * state for shoppers or in the editor. Nothing is written to the theme's
 * tables until the merchant saves.
 */
class XelgaFreshInstallTest extends TestCase
{
    use RefreshDatabase;
    use XelgaTestHelpers;

    private const CATEGORIES = ['phones', 'laptops', 'monitors', 'tablets', 'accessories'];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        // The theme a real install bundles, not the sample core's tests run on.
        config(['themes.bundled' => (require config_path('themes.php'))['bundled']]);
        app()->forgetInstance(ExtensionManager::class);
        $this->seed(DatabaseSeeder::class);
        app(ExtensionManager::class)->bootEnabled();
        app(ThemeService::class)->clearCache();

        Defaults::flush();
        PreviewMode::reset();
        Storefront::forgetRequestState();
        $this->withoutVite();
    }

    public function test_a_fresh_install_runs_xelga(): void
    {
        $this->assertSame('xelga', ThemeService::bundled());
        $this->assertSame('xelga', Setting::where('group', 'identity')->where('key', 'active_theme')->value('value'));
        $this->assertSame('enabled', app(ExtensionManager::class)->state('theme-xelga'));
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('xelga_documents')->count(), 'Nothing is saved until the merchant saves.');
    }

    public function test_the_home_page_shows_the_installer_catalog_with_no_empty_state(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // The spotlight draws its product.
        $this->assertStringContainsString('data-section-type="product-spotlight"', $html);
        $this->assertStringContainsString('Aurora 15 Pro', $html);

        // Every tile and the banner buttons point at a seeded category.
        foreach (self::CATEGORIES as $slug) {
            $this->assertNotNull(Category::where('slug', $slug)->first(), "The installer catalog seeds {$slug}.");
            $this->assertStringContainsString('href="' . route('category.show', $slug) . '"', $html, "Nothing links to {$slug}.");
        }

        // Both product rows have products to show.
        $this->assertSame(2, substr_count($html, 'data-section-type="featured-products"'));
        $this->assertGreaterThan(0, Product::count());
    }

    public function test_the_editor_opens_a_fresh_install_with_nothing_to_pick(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $admin = $this->admin();

        // The editor marks a section whose picker is empty; the home page it opens has none.
        $response = $this->actingAs($admin)->get(route('admin.xelga.editor'))->assertOk();
        preg_match('#<script type="application/json" id="xelga-boot">(.*?)</script>#s', $response->getContent(), $m);
        $boot = json_decode(html_entity_decode($m[1] ?? '', ENT_QUOTES), true) ?? json_decode($m[1] ?? '', true);
        $home = $boot['documents']['home']['content']['sections'];

        $this->assertSame(Product::where('slug', 'aurora-15-pro')->value('id'), $home['spotlight-1']['settings']['product']);
        foreach ($home['categories-1']['block_order'] as $i => $block) {
            $this->assertSame(
                Category::where('slug', self::CATEGORIES[$i])->value('id'),
                $home['categories-1']['blocks'][$block]['settings']['category'] ?? null,
                "Tile {$block} has no category."
            );
        }
        $this->assertSame('category', $home['hero-1']['blocks']['hero-1-b1']['settings']['button_link']['type']);

        // And its preview draws every section with something to show.
        $nonce = $this->openEditor($admin);
        Storefront::forgetRequestState();
        $html = $this->get('/?xelga_preview=' . $nonce)->assertOk()->getContent();
        preg_match_all('#<p class="editor-note"[^>]*>(.*?)</p>#s', $html, $notes);
        $notes = array_map(fn (string $note) => trim(strip_tags($note)), $notes[1]);
        // Two notes always ship, both for shoppers who have not acted yet:
        // the banner's "all slides hidden" note (hidden while a slide shows)
        // and the recently viewed row, empty until a product is opened.
        $this->assertSame([], array_values(array_filter($notes, fn (string $note) => ! str_starts_with($note, 'All slides are hidden') && ! str_starts_with($note, 'Shoppers see the products they opened last'))));
        $this->assertMatchesRegularExpression('#class="hero__empty" data-hero-empty\s+hidden#', $html);
    }
}
