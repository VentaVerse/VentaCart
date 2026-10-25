<?php

namespace Tests\Feature\AdminV2;

use App\Models\Customer;
use App\Models\LayoutPlacement;
use App\Models\User;
use App\Storefront\Widgets\CoreWidgets;
use App\Support\BlockGroup;
use App\Support\Widgets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesSampleTheme;
use Tests\TestCase;

/**
 * The banner, block group, map and HTML blocks any theme can place, and the
 * currency menu and welcome text a theme with a header region offers.
 */
class ContentBlocksTest extends TestCase
{
    use RefreshDatabase, UsesSampleTheme;

    protected function setUp(): void
    {
        parent::setUp();
        // Warm the theme's view location, as a served request does.
        $this->get('/');
    }

    private function place(string $widget, array $config = [], array $attrs = []): LayoutPlacement
    {
        return LayoutPlacement::create($attrs + [
            'theme' => 'venta', 'page_type' => 'home', 'region' => 'content_top',
            'widget' => $widget, 'title' => null, 'config' => $config, 'sort_order' => 0, 'is_enabled' => true,
        ]);
    }

    /** A one-column row the editors would save, for blocks stacked in it. */
    private function row(): int
    {
        return \App\Models\LayoutRow::create(['theme' => 'venta', 'page_type' => 'home', 'region' => 'content_top',
            'columns' => [8], 'sort_order' => 0, 'is_enabled' => true])->id;
    }

    public function test_the_blocks_are_registered_with_a_view_in_the_default_theme_and_a_settings_form(): void
    {
        $fields = [
            'content.banner' => ['image', 'heading', 'text', 'button', 'url', 'target', 'alignment', 'position', 'overlay', 'height', 'hover'],
            'content.block_group' => ['display', 'count', 'open'],
            'content.google_map' => ['location', 'zoom', 'height', 'map_type'],
            'content.html' => ['html'],
        ];

        foreach ($fields as $key => $names) {
            $spec = Widgets::get($key);
            $this->assertNotNull($spec, "$key is registered");
            $this->assertSame('content', $spec['module']);
            $this->assertFalse($spec['system']);
            $this->assertNull($spec['regions'], "$key can go in any region");
            $this->assertSame('widgets.' . $key, Widgets::view($key));
            $this->assertSame($names, array_column(Widgets::schema($key), 'name'));
        }

        $this->assertSame('image', collect(Widgets::schema('content.banner'))->firstWhere('name', 'image')['type'], 'The banner image uses the media library.');
    }

    public function test_a_banner_draws_its_image_words_and_a_safe_link(): void
    {
        $this->place('content.banner', ['image' => 'banners/sale.jpg', 'heading' => 'Big Sale', 'text' => "Line one\nLine two", 'button' => 'Shop now',
            'url' => '/category/demo', 'target' => 'new', 'alignment' => 'left', 'position' => 'bottom', 'overlay' => '50', 'height' => 'md']);
        $this->place('content.banner', ['image' => '../etc/passwd', 'heading' => 'Unsafe', 'button' => 'Go', 'url' => 'javascript:alert(1)'], ['sort_order' => 1]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('banners/sale.jpg', $html);
        $this->assertStringContainsString('wbanner--md wbanner--bottom wbanner--left wbanner--shade-50', $html);
        $this->assertStringContainsString('href="/category/demo"', $html);
        $this->assertStringContainsString('target="_blank" rel="noopener"', $html);
        $this->assertStringContainsString('Line one<br />', $html);
        $this->assertStringContainsString('Shop now', $html);
        $this->assertStringNotContainsString('etc/passwd', $html);
        $this->assertStringNotContainsString('javascript:alert', $html);
        $this->assertStringNotContainsString('>Go<', $html, 'A button with no safe link is not drawn.');
    }

    public function test_a_map_embeds_an_address_or_coordinates_without_a_key_and_loads_lazily(): void
    {
        $address = CoreWidgets::mapUrl(['location' => 'Rizal Park, Manila', 'zoom' => 99]);
        $this->assertStringStartsWith('https://maps.google.com/maps?', $address);
        $this->assertStringContainsString('q=Rizal+Park%2C+Manila', $address);
        $this->assertStringContainsString('z=21', $address);
        $this->assertStringContainsString('output=embed', $address);
        $this->assertStringNotContainsString('key=', $address);

        $this->assertStringContainsString('q=14.5995%2C120.9842', CoreWidgets::mapUrl(['location' => ' 14.5995 , 120.9842 ']));
        $this->assertStringContainsString('t=k', CoreWidgets::mapUrl(['location' => 'Manila', 'map_type' => 'satellite']));
        $this->assertNull(CoreWidgets::mapUrl(['location' => '  ']));

        $this->place('content.google_map', ['location' => 'Manila', 'height' => 320]);
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<iframe class="wmap__frame" src="https:\/\/maps\.google\.com\/maps\?q=Manila[^"]*" height="320" loading="lazy"/', $html);
    }

    public function test_the_html_block_draws_its_markup_as_written(): void
    {
        $this->place('content.html', ['html' => '<div class="demo-embed"><strong>Hand written</strong></div>']);

        $this->get('/')->assertOk()->assertSee('<div class="demo-embed"><strong>Hand written</strong></div>', false);
    }

    public function test_only_signed_in_staff_can_write_a_blocks_settings(): void
    {
        $payload = ['payload' => json_encode([
            'page_type' => 'home', 'columns' => ['left' => 0, 'right' => 0],
            'rows' => [['id' => null, 'region' => 'content_top', 'columns' => [8], 'enabled' => true,
                'placements' => [['id' => null, 'column' => 0, 'widget' => 'content.html', 'config' => ['html' => '<script>alert(1)</script>'], 'enabled' => true]]]],
        ])];

        // A visitor, then a signed-in customer: neither reaches the layout.
        $this->post(route('admin.layout.update'), $payload)->assertRedirect(route('login'));
        $customer = Customer::create(['store_id' => 1, 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'email' => 'juan@example.com', 'password' => bcrypt('secret')]);
        // Signed in on the customer guard only, as a storefront sign-in is.
        // (actingAs would also make it the default guard, which no real
        // request does.)
        $this->app['auth']->guard('customer')->setUser($customer);
        $this->post(route('admin.layout.update'), $payload)->assertRedirect(route('login'));
        $this->assertDatabaseMissing('layout_placements', ['widget' => 'content.html']);

        // Staff can.
        $this->actingAs(User::factory()->create(), 'web')->post(route('admin.layout.update'), $payload)->assertRedirect();
        $this->assertDatabaseHas('layout_placements', ['widget' => 'content.html']);
    }

    public function test_a_block_group_shows_the_blocks_below_it_as_tabs(): void
    {
        $row = $this->row();
        $this->place('content.block_group', ['display' => 'tabs', 'count' => 2], ['row_id' => $row, 'title' => 'More to see', 'sort_order' => 0]);
        $this->place('content.html', ['html' => '<p>First panel</p>'], ['row_id' => $row, 'title' => 'Delivery', 'sort_order' => 1]);
        $this->place('content.html', ['html' => '<p>Second panel</p>'], ['row_id' => $row, 'sort_order' => 2]);
        $this->place('content.html', ['html' => '<p>After the group</p>'], ['row_id' => $row, 'sort_order' => 3]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('wgroup--tabs', $html);
        $this->assertSame(2, substr_count($html, 'role="tabpanel"'));
        $this->assertMatchesRegularExpression('/role="tab"[^>]*>Delivery</', $html, 'A tab is named by its block\'s heading.');
        $this->assertMatchesRegularExpression('/role="tab"[^>]*>HTML</', $html, 'A block without a heading is named by its kind.');
        $this->assertSame(1, substr_count($html, 'Delivery'), 'The block does not repeat its heading inside its tab.');
        $group = substr($html, strpos($html, 'wgroup--tabs'));
        $this->assertLessThan(strpos($group, 'After the group'), strpos($group, 'Second panel'));
        $tail = substr($group, strrpos($group, 'role="tabpanel"'));
        $this->assertGreaterThanOrEqual(2, substr_count(substr($tail, 0, strpos($tail, 'After the group')), '</section>'), 'The third block renders after the group, not in it.');
    }

    public function test_a_block_group_as_an_accordion_and_its_limits(): void
    {
        $row = $this->row();
        $this->place('content.block_group', ['display' => 'accordion', 'count' => 5, 'open' => 'none'], ['row_id' => $row, 'sort_order' => 0]);
        $this->place('content.html', ['html' => '<p>Only panel</p>'], ['row_id' => $row, 'title' => 'Returns', 'sort_order' => 1]);
        $this->place('content.html', ['html' => ''], ['row_id' => $row, 'title' => 'Empty one', 'sort_order' => 2]);
        $this->place('content.block_group', ['display' => 'tabs', 'count' => 1], ['row_id' => $row, 'sort_order' => 3]);
        $this->place('content.html', ['html' => '<p>Second group</p>'], ['row_id' => $row, 'sort_order' => 4]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('wgroup--accordion', $html);
        $this->assertStringContainsString('Returns', $html);
        $this->assertStringNotContainsString('Empty one', $html, 'A block with nothing to show makes no section.');
        $this->assertStringContainsString('x-data="{ open: null }"', $html, 'All sections start closed.');
        $this->assertStringContainsString('wgroup--tabs', $html, 'A group never swallows the next group.');
        $this->assertStringContainsString('Second group', $html);

        $this->assertSame(1, BlockGroup::count(['count' => -3]));
        $this->assertSame(BlockGroup::MAX, BlockGroup::count(['count' => 500]));
        $this->assertSame(2, BlockGroup::count([]));
    }

    public function test_a_block_group_with_nothing_below_it_draws_nothing(): void
    {
        $this->place('content.block_group', ['count' => 3], ['title' => 'Lonely group']);

        $this->get('/')->assertOk()->assertDontSee('Lonely group');
    }

    public function test_the_currency_menu_and_welcome_text_are_header_pieces(): void
    {
        foreach (['system.currency_menu' => ['label', 'display', 'show'], 'system.welcome_text' => ['guest', 'customer', 'links']] as $key => $names) {
            $spec = Widgets::get($key);
            $this->assertTrue($spec['system']);
            $this->assertSame(['header'], $spec['regions']);
            $this->assertFalse(Widgets::allowedIn($key, 'content_top'));
            $this->assertSame($names, array_column(Widgets::schema($key), 'name'));
        }

        // The default theme declares no header region, so it is not asked to
        // draw them; a theme that does is.
        $this->assertSame([], \App\Support\ThemeContract::check(resource_path('themes/venta')));
        $this->makeSampleTheme();
        @unlink(resource_path('themes/sample/views/widgets/system/welcome_text.blade.php'));
        $this->assertContains('View [widgets.system.welcome_text] is missing.', \App\Support\ThemeContract::check(resource_path('themes/sample')));
    }
}
