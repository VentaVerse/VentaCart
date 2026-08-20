<?php

namespace Tests\Feature\AdminV2;

use App\Models\LayoutPlacement;
use App\Support\Regions;
use App\Support\Widgets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regions and placements: where a module renders, on which pages, in what order.
 *
 * Venta had exactly one of these and it worked on one page only. homepage_sections
 * was read by HomeController, which switched on section type to load the data, and
 * rendered by an if/elseif chain in home.blade.php. That is why banners could not
 * leave the home page: not a business rule, just the only view that rendered
 * sections.
 */
class LayoutPlacementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Widgets::register('test.marker', [
            'title' => 'Marker',
            'view' => 'widgets.test.marker',
            'resolve' => fn (array $c) => ['note' => $c['note'] ?? 'plain'],
        ]);
    }

    protected function tearDown(): void
    {
        // The registry is process-global, like the courier one.
        Widgets::forget('test.marker');
        parent::tearDown();
    }

    /* ---------------- regions belong to the theme ---------------- */

    public function test_regions_are_declared_by_the_active_theme(): void
    {
        $regions = Regions::all();

        $this->assertArrayHasKey('content_top', $regions);
        $this->assertSame('Above the content', $regions['content_top']['label']);
    }

    /** Core holds no list of region names, so a theme can invent one. */
    public function test_a_region_the_theme_does_not_declare_does_not_exist(): void
    {
        $this->assertFalse(Regions::exists('faceplate'));
    }

    /* ---------------- placement resolution ---------------- */

    private function place(array $attrs = []): LayoutPlacement
    {
        return LayoutPlacement::create($attrs + [
            'page_type' => 'home',
            'region' => 'content_top',
            'widget' => 'test.marker',
            'sort_order' => 0,
            'is_enabled' => true,
        ]);
    }

    public function test_a_placement_belongs_to_its_page_type_alone(): void
    {
        $this->place(['page_type' => 'cart']);

        $this->assertCount(1, LayoutPlacement::forRegion('cart', 'content_top'));
        $this->assertCount(0, LayoutPlacement::forRegion('home', 'content_top'));
    }

    /**
     * '*' is additive, not overriding: it is what a site-wide promo strip needs.
     * The cost is that a page carries something not placed from that page's own
     * screen, so the editor has to show inherited rows as inherited.
     */
    public function test_an_everywhere_placement_shows_on_every_page_type(): void
    {
        $this->place(['page_type' => LayoutPlacement::EVERYWHERE]);
        $this->place(['page_type' => 'cart', 'sort_order' => 1]);

        $this->assertCount(2, LayoutPlacement::forRegion('cart', 'content_top'));
        $this->assertCount(1, LayoutPlacement::forRegion('product', 'content_top'));
    }

    public function test_order_is_the_merchants_order(): void
    {
        $this->place(['title' => 'second', 'sort_order' => 5]);
        $this->place(['title' => 'first', 'sort_order' => 1]);

        $this->assertSame(['first', 'second'],
            LayoutPlacement::forRegion('home', 'content_top')->pluck('title')->all());
    }

    public function test_a_disabled_placement_is_not_returned(): void
    {
        $this->place(['is_enabled' => false]);

        $this->assertCount(0, LayoutPlacement::forRegion('home', 'content_top'));
    }

    /* ---------------- the widget registry ---------------- */

    public function test_a_widget_carries_its_own_data(): void
    {
        $this->assertSame(['note' => 'hello'], Widgets::resolve('test.marker', ['note' => 'hello']));
    }

    /** An unregistered widget is silence, never a crash on a shopper's page. */
    public function test_an_unregistered_widget_resolves_to_nothing(): void
    {
        $this->assertFalse(Widgets::has('nobody.home'));
        $this->assertSame([], Widgets::resolve('nobody.home', []));
        $this->assertNull(Widgets::view('nobody.home'));
    }

    /**
     * Theme first, module second. This one rule is the whole "the theme owns
     * the look" argument: a theme paints products its own way without the
     * catalog module knowing.
     */
    public function test_a_theme_view_wins_over_the_modules_own(): void
    {
        // The theme's view folder is prepended per request (RegisterThemeViews),
        // so a lookup outside one sees only what ships in resources/views.
        \Illuminate\Support\Facades\View::getFinder()
            ->prependLocation(resource_path('themes/venta/views'));

        $this->assertSame('widgets.catalog.products', Widgets::view('catalog.products'));
    }

    /* ---------------- the storefront ---------------- */

    public function test_the_home_page_renders_what_was_placed_on_it(): void
    {
        $this->place(['widget' => 'catalog.brands', 'title' => 'Our Brands']);

        $this->get('/')->assertOk()->assertSee('Our Brands', false);
    }

    public function test_a_placement_on_another_page_type_does_not_leak_onto_home(): void
    {
        $this->place(['page_type' => 'checkout', 'widget' => 'catalog.brands', 'title' => 'Checkout Brands']);

        $this->get('/')->assertOk()->assertDontSee('Checkout Brands', false);
    }

    /**
     * Switching theme must not destroy a merchant's layout. An orphan is
     * reported so they can move it, never deleted so they discover it missing.
     */
    public function test_a_placement_in_a_region_this_theme_lacks_is_reported_not_deleted(): void
    {
        $orphan = $this->place(['region' => 'faceplate']);

        $this->assertTrue(Regions::orphans()->contains('id', $orphan->id));
        $this->assertDatabaseHas('layout_placements', ['id' => $orphan->id]);
    }
}
