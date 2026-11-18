<?php

namespace Themes\Xelga\Tests\Feature;

use App\Models\NavMenu;
use App\Models\NavMenuItem;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Themes\Xelga\Tests\XelgaTestHelpers;
use Themes\Xelga\Documents\Document;

require_once __DIR__ . '/../XelgaTestHelpers.php';

/**
 * The storefront draws the home page from the published documents (or the
 * shipped defaults), with the store's own name, menu and contact details;
 * other pages keep the real frame around a placeholder.
 */
class XelgaStorefrontTest extends TestCase
{
    use RefreshDatabase;
    use XelgaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installXelga();
    }

    public function test_home_draws_header_main_banner_and_footer_from_the_shipped_defaults(): void
    {
        $menu = NavMenu::where('key', 'main')->firstOrFail();
        NavMenuItem::create(['menu_id' => $menu->id, 'label' => 'New arrivals', 'type' => 'link', 'url' => '/search?q=new', 'sort_order' => 1, 'is_enabled' => true]);
        $parent = NavMenuItem::create(['menu_id' => $menu->id, 'label' => 'Women', 'type' => 'link', 'url' => '/search?q=women', 'sort_order' => 2, 'is_enabled' => true]);
        NavMenuItem::create(['menu_id' => $menu->id, 'parent_id' => $parent->id, 'label' => 'Dresses', 'type' => 'link', 'url' => '/search?q=dresses', 'sort_order' => 1, 'is_enabled' => true]);
        Store::query()->update(['phone' => '0900 000 0000', 'email' => 'hello@example.com']);

        $response = $this->get('/')->assertOk();

        $response->assertSee('data-section-type="header"', false)
            ->assertSee('data-section-type="hero"', false)
            ->assertSee('data-section-type="footer"', false)
            ->assertSee('Demo Store')
            ->assertSee('New arrivals')
            ->assertSee('Dresses')
            ->assertSee('Cash on delivery anywhere in the Philippines')
            ->assertSee('Phones, laptops and tablets, paid for at your door')
            ->assertSee('hello@example.com')
            ->assertSee('/storage/media/xelga/gadgets/product-phone-pro-graphite.jpg', false)
            ->assertSee('/storage/media/xelga/gadgets/cat-phones.jpg', false)
            ->assertSee('/xelga/tokens/0.css', false)
            ->assertSee('<!--xelga:hero-1-->', false)
            ->assertSee('© ' . now()->year . ' Demo Store');

        // Gadgets is the shipped store; its name is never printed as the store's.
        $response->assertDontSee('>Gadgets<', false);
    }

    public function test_hidden_sections_are_not_drawn_for_shoppers(): void
    {
        $admin = $this->admin();
        $home = $this->defaults('home');
        $home['sections']['hero-1']['disabled'] = true;
        $this->saveDraft($admin, 'home', $home, 0)->assertOk();
        $this->publish($admin, ['home' => 1])->assertOk();
        $this->asGuest();

        $this->get('/')->assertOk()->assertDontSee('data-section-type="hero"', false)->assertSee('data-section-type="footer"', false);
    }

    public function test_other_pages_render_inside_the_real_frame(): void
    {
        $this->get(route('cart.index'))->assertOk()
            ->assertSee('data-section-type="cart-main"', false)
            ->assertSee('data-section-type="header"', false)
            ->assertSee('data-section-type="footer"', false);
        \Themes\Xelga\Rendering\Storefront::forgetRequestState();
        $this->get(route('search.results', ['q' => 'linen']))->assertOk()->assertSee('data-section-type="search-main"', false);
    }

    public function test_the_published_tokens_stylesheet_is_versioned_and_cached(): void
    {
        $this->get(route('xelga.tokens', ['version' => 0]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/css; charset=utf-8')
            ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public')
            ->assertSee('--font-heading:"Bricolage Grotesque"', false)
            ->assertSee('.scheme-3{--c-bg:#101214', false);

        $admin = $this->admin();
        $settings = $this->defaults('settings');
        $settings['values']['page_width'] = 1280;
        $this->saveDraft($admin, 'settings', $settings, 0)->assertOk();
        $this->publish($admin, ['settings' => 1])->assertOk();
        $this->asGuest();

        $this->get(route('xelga.tokens', ['version' => 0]))->assertRedirect(route('xelga.tokens', ['version' => 1]));
        $this->get(route('xelga.tokens', ['version' => 1]))->assertOk()->assertSee('--page-width:1280px', false);
        $this->get('/')->assertSee('/xelga/tokens/1.css', false);
    }

    public function test_a_section_that_fails_is_left_out_and_the_page_still_renders(): void
    {
        // A section type the catalog does not know (from an older theme
        // version) is kept in the document but drawn for nobody.
        Document::create(['handle' => 'home', 'kind' => 'template', 'page_type' => 'home']);
        Document::where('handle', 'home')->update([
            'published' => json_encode(['schema' => 1, 'order' => ['gone-1', 'hero-1'], 'sections' => ['gone-1' => ['type' => 'retired-type', 'settings' => []]] + $this->defaults('home')['sections']]),
            'published_version' => 1,
        ]);

        $this->get('/')->assertOk()->assertSee('Phones, laptops and tablets, paid for at your door')->assertDontSee('retired-type');
    }
}
