<?php

namespace Tests\Feature\AdminV2;

use App\Models\LayoutPlacement;
use App\Models\LayoutRow;
use App\Models\User;
use App\Services\ExtensionManager;
use App\Services\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesSampleTheme;
use Tests\TestCase;

/**
 * What the Layout editor shows first, and how a widget gets onto a page.
 *
 * It opens where a store's content is (Home, or the first page with widgets),
 * lists every page with its count and Every page last, adds a widget from a
 * control inside each position, and says so when the live theme arranges its
 * pages somewhere else.
 */
class LayoutEditorOpeningTest extends TestCase
{
    use RefreshDatabase, UsesSampleTheme;

    private function place(string $page, string $region = 'content_top'): void
    {
        $row = LayoutRow::create(['theme' => 'default', 'page_type' => $page, 'region' => $region, 'columns' => [8], 'sort_order' => 0]);
        LayoutPlacement::create(['theme' => 'default', 'page_type' => $page, 'region' => $region, 'row_id' => $row->id, 'widget' => 'catalog.brands', 'title' => 'Our brands', 'sort_order' => 0]);
    }

    private function boot(): void
    {
        app(ExtensionManager::class)->install('theme');
        app(ExtensionManager::class)->bootEnabled();
        $this->withoutVite();
        $this->actingAs(User::factory()->create());
    }

    public function test_it_opens_on_home_when_home_has_widgets(): void
    {
        $this->boot();
        $this->place('home');
        $this->place('cart');

        $this->get(route('admin.layout'))->assertOk()->assertViewHas('pageType', 'home');
    }

    public function test_it_opens_on_home_when_nothing_is_placed_anywhere(): void
    {
        $this->boot();

        $this->get(route('admin.layout'))->assertOk()->assertViewHas('pageType', 'home');
    }

    public function test_it_opens_on_the_first_page_with_widgets_when_home_is_empty(): void
    {
        $this->boot();
        $this->place(LayoutPlacement::EVERYWHERE);
        $this->place('cart');

        $this->get(route('admin.layout'))->assertOk()->assertViewHas('pageType', 'cart');
    }

    public function test_an_unknown_page_opens_the_default_rather_than_every_page(): void
    {
        $this->boot();
        $this->place('home');

        $this->get(route('admin.layout', ['page' => 'nowhere']))->assertOk()->assertViewHas('pageType', 'home');
        $this->get(route('admin.layout', ['page' => '*']))->assertOk()->assertViewHas('pageType', '*');
    }

    public function test_every_page_is_listed_last_with_a_count_on_every_entry(): void
    {
        $this->boot();
        $this->place('home');

        $html = $this->get(route('admin.layout'))->assertOk()->getContent();

        $this->assertStringContainsString('Home (1)', $html, 'the phone page select shows counts');
        $this->assertStringContainsString('Cart (0)', $html);
        $this->assertStringContainsString('Every page (shared)', $html);
        $this->assertStringNotContainsString('All pages', $html);
        $rail = substr($html, strpos($html, '<nav class="v-lay__rail"'));
        $rail = substr($rail, 0, strpos($rail, '</nav>'));
        $this->assertGreaterThan(strpos($rail, 'Sign in and register'), strpos($rail, 'Every page (shared)'));
        $this->assertGreaterThan(strpos($rail, 'Home'), strpos($rail, 'Every page (shared)'));
    }

    public function test_every_position_offers_add_widget_and_no_copy_to_another_page(): void
    {
        $this->boot();

        $html = $this->get(route('admin.layout', ['page' => 'home']))->assertOk()->getContent();

        foreach (['Header top', 'Content top', 'Column left', 'Column right', 'Content bottom', 'Footer top'] as $label) {
            $this->assertStringContainsString('aria-label="Add widget to ' . $label . '"', $html);
        }
        $this->assertStringContainsString('A widget is one block of content', $html);
        $this->assertStringNotContainsString('Copy to', $html);
        $this->assertStringNotContainsString('copyUrl', $html);

        // Footer top is drawn above the footer, as the storefront draws it.
        $this->assertLessThan(strpos($html, '<b>Footer</b>'), strpos($html, 'id="v-slot-footer_top"'));
    }

    public function test_a_widget_added_from_the_plus_saves_as_one_row_with_one_widget(): void
    {
        $this->boot();

        // What the editor sends after "+ Add widget" and picking Banner: a
        // new one-column row holding the one widget, with no heading.
        $this->post(route('admin.layout.update'), ['payload' => json_encode([
            'page_type' => 'home', 'columns' => ['left' => 0, 'right' => 0], 'settings' => [],
            'rows' => [['id' => null, 'region' => 'content_top', 'columns' => [8], 'enabled' => true, 'style' => null, 'responsive' => null, 'placements' => [
                ['id' => null, 'column' => 0, 'widget' => 'content.banner', 'title' => null, 'enabled' => true, 'config' => ['image' => 'media/hero/demo.jpg'], 'style' => null],
            ]]],
        ])])->assertRedirect()->assertSessionHas('success', '1 widget placed on Home.');

        $this->assertSame(1, LayoutRow::where('page_type', 'home')->count());
        $placement = LayoutPlacement::where('page_type', 'home')->sole();
        $this->assertSame('content.banner', $placement->widget);
        $this->assertNull($placement->title);
        $this->assertSame(LayoutRow::where('page_type', 'home')->value('id'), $placement->row_id);
    }

    public function test_the_page_parts_are_described_where_they_sit(): void
    {
        $this->boot();

        $this->get(route('admin.layout', ['page' => 'product']))->assertOk()
            ->assertSee('Built into this page')
            ->assertSee('The product: images, name, price')
            ->assertSee('The trail of links back to Home', false)
            ->assertSee('Edit in Header &amp; footer', false);
    }

    public function test_a_live_theme_with_its_own_editor_is_named_with_the_way_to_it(): void
    {
        foreach (['theme', 'banners'] as $name) {
            app(ExtensionManager::class)->install($name);
        }
        $this->installSampleTheme();
        app(ExtensionManager::class)->bootEnabled();
        app(ThemeService::class)->activateTheme('sample');
        app(ThemeService::class)->clearCache();
        $this->withoutVite();
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.layout'))->assertOk()
            ->assertSee('Your live theme Sample arranges its pages in its own editor.')
            ->assertSee('href="' . route('admin.dashboard') . '"', false);
    }

    public function test_no_notice_when_the_live_theme_is_the_one_being_arranged(): void
    {
        $this->boot();

        $this->get(route('admin.layout'))->assertOk()->assertDontSee('arranges its pages in its own editor');
    }
}
