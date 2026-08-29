<?php

namespace Tests\Feature\AdminV2;

use App\Models\Customer;
use App\Models\LayoutPlacement;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\User;
use App\Services\ExtensionManager;
use App\Support\Widgets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The widget catalogue (Plaza plan, phase 3): every registered widget has a
 * view in the theme, the product listing knows more ways to pick, and the
 * content, social, blog and review widgets render from their settings.
 */
class WidgetCatalogueTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Product listings read the reviews relation the reviews extension adds
     * and the blog widget reads the blog's table, so both are booted here
     * the way a store running them is.
     */
    protected function setUp(): void
    {
        parent::setUp();
        app(ExtensionManager::class)->install('reviews');
        app(ExtensionManager::class)->install('blog');
        app(ExtensionManager::class)->bootEnabled();
        // The theme's view location is prepended once the app has served a
        // request; warm it so view lookups see the theme.
        $this->get('/');
    }

    private function place(string $widget, array $config = [], array $attrs = []): LayoutPlacement
    {
        return LayoutPlacement::create($attrs + [
            'theme' => 'venta', 'page_type' => 'home', 'region' => 'content_top',
            'widget' => $widget, 'title' => 'Widget Title', 'config' => $config, 'sort_order' => 0, 'is_enabled' => true,
        ]);
    }

    private function product(string $name, array $attrs = []): Product
    {
        return Product::create($attrs + ['store_id' => 1, 'name' => $name, 'slug' => \Illuminate\Support\Str::slug($name), 'sku' => strtoupper(\Illuminate\Support\Str::random(6)), 'price' => 100, 'quantity' => 5, 'is_active' => true]);
    }

    public function test_every_core_widget_has_a_view_the_theme_renders(): void
    {
        foreach (['catalog.products', 'catalog.product_tabs', 'catalog.also_bought', 'catalog.brands', 'catalog.category_tabs', 'catalog.recently_viewed',
            'content.text', 'content.cta', 'content.icon_list', 'content.separator', 'content.gallery', 'content.accordion',
            'social.facebook', 'social.twitter', 'blog.posts'] as $key) {
            $this->assertTrue(Widgets::has($key), "$key is not registered");
            $this->assertNotNull(Widgets::view($key), "$key has no view");
        }
    }

    public function test_the_product_grid_can_pick_most_viewed_and_random_and_lay_out_as_a_slider(): void
    {
        $this->product('Quiet One', ['views' => 1]);
        $this->product('Loud One', ['views' => 900]);

        $this->place('catalog.products', ['filter' => 'most_viewed', 'limit' => 1, 'layout' => 'slider', 'columns' => '3', 'card' => 'compact']);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('Loud One', $html);
        $this->assertStringNotContainsString('Quiet One', $html);
        $this->assertStringContainsString('wslider__track--3', $html);
        $this->assertStringContainsString('product-card--compact', $html);

        $this->assertCount(2, Widgets::resolve('catalog.products', ['filter' => 'random', 'limit' => 10])['products']);
    }

    public function test_also_bought_lists_what_shared_an_order_and_is_offered_on_product_pages_only(): void
    {
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Complete', 'is_default' => true, 'sort_order' => 1]);
        $customer = Customer::create(['store_id' => 1, 'first_name' => 'J', 'last_name' => 'D', 'email' => 'j@example.com', 'password' => bcrypt('x')]);
        $cable = $this->product('Cable');
        $strap = $this->product('Strap');
        $pick = $this->product('Pick');
        foreach ([[$cable, $strap], [$cable, $strap], [$cable, $pick]] as $pair) {
            $order = Order::create(['store_id' => 1, 'customer_id' => $customer->id, 'order_status_id' => $status->id, 'first_name' => 'J', 'last_name' => 'D', 'email' => 'j@example.com', 'phone' => '1',
                'shipping_first_name' => 'J', 'shipping_last_name' => 'D', 'shipping_address_1' => 'x', 'shipping_city' => 'C', 'shipping_postcode' => '1', 'subtotal' => 200, 'total' => 200]);
            foreach ($pair as $p) {
                $order->products()->create(['product_id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'price' => 100, 'quantity' => 1, 'total' => 100]);
            }
        }
        $this->place('catalog.also_bought', ['limit' => 2], ['page_type' => 'product', 'region' => 'content_bottom']);

        $html = $this->get(route('product.show', $cable->slug))->assertOk()->getContent();
        $this->assertLessThan(strpos($html, 'Pick'), strpos($html, 'Customers also bought'));
        $this->assertLessThan(strpos($html, '>Pick<'), strpos($html, '>Strap<'), 'The product bought together most often comes first.');

        $this->assertSame(['product'], Widgets::get('catalog.also_bought')['pages']);
        $this->actingAs(User::factory()->create());
        $this->get(route('admin.layout', ['page' => 'home']))->assertOk()->assertSee('also_bought', false);
    }

    public function test_content_widgets_render_from_their_settings(): void
    {
        $this->place('content.cta', ['text' => "Line one\nLine two", 'button' => 'Shop now', 'url' => '/category/pedals', 'alignment' => 'left'], ['sort_order' => 0]);
        $this->place('content.cta', ['text' => 'Bad link', 'button' => 'Go', 'url' => 'javascript:alert(1)'], ['sort_order' => 1, 'title' => 'Unsafe']);
        $this->place('content.icon_list', ['items' => [['icon' => 'truck', 'title' => 'Fast delivery', 'text' => 'Nationwide'], ['icon' => 'nope', 'title' => '', 'text' => '']], 'columns' => '3'], ['sort_order' => 2]);
        $this->place('content.separator', ['style' => 'line', 'size' => 'lg'], ['sort_order' => 3]);
        $this->place('content.accordion', ['items' => [['title' => 'Shipping', 'text' => 'Two days'], ['title' => '', 'text' => 'skipped']]], ['sort_order' => 4]);
        $this->place('content.gallery', ['images' => ['banners/a.jpg', '../etc/passwd', 'banners/b.jpg'], 'columns' => '2'], ['sort_order' => 5]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('wcta--left', $html);
        $this->assertStringContainsString('Line one<br />', $html);
        $this->assertStringContainsString('href="/category/pedals"', $html);
        $this->assertStringNotContainsString('javascript:alert', $html, 'An unsafe link renders no button.');
        $this->assertStringContainsString('Fast delivery', $html);
        $this->assertSame(1, substr_count($html, 'wicons__item'), 'An empty item is not drawn.');
        $this->assertStringContainsString('wsep--line wsep--lg', $html);
        $this->assertStringContainsString('Shipping', $html);
        $this->assertSame(1, substr_count($html, 'waccordion__item'));
        $this->assertStringContainsString('banners/a.jpg', $html);
        $this->assertStringNotContainsString('etc/passwd', $html);
        $this->assertStringContainsString('wlightbox', $html);
    }

    public function test_product_tabs_draw_one_tab_per_listing_that_has_products(): void
    {
        $this->product('Fresh', ['is_featured' => false]);
        $this->place('catalog.product_tabs', ['tabs' => [['title' => 'Latest', 'filter' => 'new', 'limit' => 4], ['title' => 'Empty specials', 'filter' => 'specials', 'limit' => 4]]]);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('Latest', $html);
        $this->assertStringNotContainsString('Empty specials', $html);
        $this->assertStringContainsString('role="tablist"', $html);
    }

    public function test_social_widgets_render_only_for_addresses_they_can_trust(): void
    {
        $this->place('social.facebook', ['url' => 'https://www.facebook.com/newgearday', 'height' => 300], ['sort_order' => 0]);
        $this->place('social.facebook', ['url' => 'https://evil.test/newgearday'], ['sort_order' => 1, 'title' => 'Evil Book']);
        $this->place('social.twitter', ['username' => '@newgearday'], ['sort_order' => 2]);
        $this->place('social.twitter', ['username' => 'bad name!'], ['sort_order' => 3, 'title' => 'Bad Bird']);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('facebook.com/plugins/page.php?href=https%3A%2F%2Fwww.facebook.com%2Fnewgearday', $html);
        $this->assertStringNotContainsString('Evil Book', $html);
        $this->assertStringContainsString('twitter.com/newgearday', $html);
        $this->assertStringNotContainsString('Bad Bird', $html);
    }

    public function test_blog_posts_and_latest_reviews_come_from_their_extensions(): void
    {
        \Extensions\Blog\Models\BlogPost::create(['title' => 'Pedal care', 'slug' => 'pedal-care', 'content' => 'x', 'excerpt' => 'Keep them clean', 'is_published' => true, 'published_at' => now()->subDay()]);
        \Extensions\Blog\Models\BlogPost::create(['title' => 'Draft post', 'slug' => 'draft', 'content' => 'x', 'is_published' => false]);
        $product = $this->product('Reviewed Cable');
        \Extensions\Reviews\Models\Review::create(['product_id' => $product->id, 'token' => 'tok-ana', 'name' => 'Ana', 'email' => 'a@example.com', 'rating' => 5, 'title' => 'Great cable', 'body' => 'Solid.', 'status' => 'approved', 'published_at' => now()]);
        \Extensions\Reviews\Models\Review::create(['product_id' => $product->id, 'token' => 'tok-ben', 'name' => 'Ben', 'email' => 'b@example.com', 'rating' => 2, 'title' => 'Meh', 'body' => 'No.', 'status' => 'approved', 'published_at' => now()]);

        $this->assertTrue(Widgets::has('reviews.latest'));
        $this->place('blog.posts', ['limit' => 3], ['sort_order' => 0]);
        $this->place('reviews.latest', ['limit' => 3, 'minimum' => '4'], ['sort_order' => 1]);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('Pedal care', $html);
        $this->assertStringNotContainsString('Draft post', $html);
        $this->assertStringContainsString('Great cable', $html);
        $this->assertStringNotContainsString('Meh', $html, 'A review under the minimum stars is not shown.');
    }
}
