<?php

namespace Themes\Xelga\Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Themes\Xelga\Tests\XelgaTestHelpers;
use Themes\Xelga\Documents\Assignment;
use Themes\Xelga\Documents\Defaults;
use Themes\Xelga\Documents\Document;
use Themes\Xelga\Rendering\Storefront;

require_once __DIR__ . '/../XelgaTestHelpers.php';

/**
 * The cart page and drawer use core's cart and its routes only, and a page
 * draws its assigned special layout or falls back to its page type's own.
 */
class XelgaCartAndTemplatesTest extends TestCase
{
    use RefreshDatabase;
    use XelgaTestHelpers;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installXelga();
        $this->store = Store::firstOrCreate(['code' => 'demo'], ['name' => 'Demo Store']);
        CustomerGroup::create(['store_id' => $this->store->id, 'name' => 'Retail', 'is_default' => true]);
        (new \ReflectionProperty(Application::class, 'isRunningInConsole'))->setValue($this->app, false);
        // Out of the console the token check runs again; these tests post as the storefront script does.
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    private function product(string $name, array $extra = []): Product
    {
        return Product::create($extra + [
            'store_id' => $this->store->id, 'name' => $name, 'slug' => Str::slug($name),
            'sku' => 'DEMO-' . Str::upper(Str::random(6)), 'price' => 1000, 'quantity' => 20, 'track_stock' => true, 'is_active' => true,
        ]);
    }

    private function page(string $url): TestResponse
    {
        Storefront::forgetRequestState();

        return $this->get($url)->assertOk();
    }

    /** A custom template: the product page with one extra rich text section. */
    private function customTemplate(string $handle, ?string $published, ?string $draft = null): Document
    {
        $make = function (string $text) {
            $content = Defaults::content('product');
            $content['sections']['note-1'] = ['type' => 'rich-text', 'label' => 'Note', 'disabled' => false, 'settings' => ['heading' => $text]];
            $content['order'][] = 'note-1';

            return $content;
        };
        $doc = new Document(['handle' => $handle, 'kind' => 'template', 'page_type' => 'product', 'label' => 'Gift guide']);
        $doc->forceFill([
            'published' => $published === null ? null : $make($published),
            'published_version' => $published === null ? 0 : 1,
            'draft' => $draft === null ? null : $make($draft),
        ])->save();

        return $doc;
    }

    public function test_an_empty_cart_shows_the_empty_state(): void
    {
        $this->page('/cart')
            ->assertSee('data-section-type="cart-main"', false)
            ->assertSee('Your cart is empty.')
            ->assertSee('Continue shopping');
    }

    private function customer(): Customer
    {
        return Customer::create(['store_id' => $this->store->id, 'first_name' => 'Juan', 'last_name' => 'Dela Cruz',
            'email' => 'juan@example.com', 'password' => bcrypt('secret-password'), 'is_active' => true]);
    }

    public function test_the_cart_page_draws_core_s_lines_and_subtotal(): void
    {
        $shirt = $this->product('Linen Shirt', ['price' => 1250]);
        $this->actingAs($this->customer(), 'customer');
        $this->postJson('/cart/add', ['product_id' => $shirt->id, 'quantity' => 2])->assertOk();

        $this->page('/cart')
            ->assertSee('Linen Shirt')
            ->assertSee(money(1250))->assertSee(money(2500))
            ->assertSee('data-line-qty', false);
    }

    public function test_a_guest_is_sent_to_sign_in_before_checkout(): void
    {
        // Core has no guest checkout: the button leads to sign in, which returns to checkout.
        $this->assertSame(route('customer.login', ['redirect' => 'checkout']), \Themes\Xelga\Sections\CartSummary::checkoutUrl());
        $html = $this->page('/')->getContent();
        $this->assertStringContainsString(e(route('customer.login', ['redirect' => 'checkout'])), $html);
    }

    public function test_a_signed_in_customer_checks_out_from_the_cart(): void
    {
        $shirt = $this->product('Linen Shirt');
        $this->actingAs($this->customer(), 'customer');
        $this->postJson('/cart/add', ['product_id' => $shirt->id, 'quantity' => 1])->assertOk();

        $this->page('/cart')->assertSee(route('checkout.index'), false)->assertDontSee('You sign in or create an account');
    }

    public function test_a_line_core_flags_as_short_blocks_checkout(): void
    {
        $shirt = $this->product('Linen Shirt', ['quantity' => 5]);
        $this->actingAs($this->customer(), 'customer');
        $this->postJson('/cart/add', ['product_id' => $shirt->id, 'quantity' => 3])->assertOk();
        $shirt->forceFill(['quantity' => 1])->save();

        $html = $this->page('/cart')->assertSee('Not enough in stock')->getContent();
        $this->assertMatchesRegularExpression('/<button type="button" class="btn btn--lg btn--block" disabled>Check out<\/button>/', $html);
    }

    public function test_the_drawer_is_on_every_page_and_only_names_core_routes(): void
    {
        $html = $this->page('/')->getContent();

        $this->assertStringContainsString('data-cart-drawer', $html);
        $this->assertStringContainsString('data-cart-open', $html);
        preg_match('/<script type="application\/json" data-cart-config>(.*?)<\/script>/s', $html, $m);
        $config = json_decode(html_entity_decode($m[1]), true);
        $this->assertSame(route('cart.mini', [], false), $config['endpoints']['mini']);
        $this->assertSame(str_replace('/0', '/{id}', route('cart.update', ['cartItem' => 0], false)), $config['endpoints']['update']);
        $this->assertSame(str_replace('/0', '/{id}', route('cart.remove', ['cartItem' => 0], false)), $config['endpoints']['remove']);
        $this->assertFalse($config['signed_in']);
        $this->assertNull($config['coupon']);
        // The free shipping wording is the merchant's, from theme settings.
        $this->assertSame('Add {amount} more for free shipping.', $config['free_message']);
        $this->assertSame('Your order qualifies for free shipping.', $config['free_reached']);

        // The cart page itself has no drawer.
        $this->assertStringNotContainsString('data-cart-drawer', $this->page('/cart')->getContent());
    }

    public function test_a_product_draws_its_assigned_published_layout(): void
    {
        $shirt = $this->product('Linen Shirt');
        $other = $this->product('Linen Dress');
        $doc = $this->customTemplate('product.gift-guide', 'Wrapped for gifting');
        Assignment::create(['subject_type' => 'product', 'subject_id' => $shirt->id, 'document_id' => $doc->id]);

        $this->page('/product/linen-shirt')->assertSee('Wrapped for gifting')->assertSee('data-section-type="product-main"', false);
        $this->page('/product/linen-dress')->assertDontSee('Wrapped for gifting');
    }

    public function test_an_assigned_layout_never_published_falls_back_to_the_product_template(): void
    {
        $shirt = $this->product('Linen Shirt');
        $doc = $this->customTemplate('product.gift-guide', null, 'Draft only');
        Assignment::create(['subject_type' => 'product', 'subject_id' => $shirt->id, 'document_id' => $doc->id]);

        $this->page('/product/linen-shirt')->assertDontSee('Draft only')->assertSee('data-section-type="product-main"', false);
    }

    public function test_an_assignment_to_a_layout_of_another_page_type_is_ignored(): void
    {
        $shirt = $this->product('Linen Shirt');
        $doc = $this->customTemplate('category.gift-guide', 'Wrong page type');
        Assignment::create(['subject_type' => 'product', 'subject_id' => $shirt->id, 'document_id' => $doc->id]);

        $this->page('/product/linen-shirt')->assertDontSee('Wrong page type');
    }

    public function test_a_shopper_cannot_ask_for_a_layout_with_the_preview_parameter(): void
    {
        $this->product('Linen Shirt');
        $this->customTemplate('product.gift-guide', 'Wrapped for gifting', 'Draft gift text');

        $this->page('/product/linen-shirt?xelga_template=product.gift-guide')
            ->assertDontSee('Wrapped for gifting')->assertDontSee('Draft gift text');
    }

    public function test_the_editor_preview_shows_a_custom_layout_s_draft_on_any_product(): void
    {
        $this->product('Linen Shirt');
        $this->customTemplate('product.gift-guide', 'Wrapped for gifting', 'Draft gift text');
        $nonce = $this->openEditor($this->admin());

        Storefront::forgetRequestState();
        $this->get('/product/linen-shirt?xelga_template=product.gift-guide&xelga_preview=' . $nonce)
            ->assertOk()->assertSee('Draft gift text');
    }

    public function test_a_template_that_lost_its_main_section_still_draws_the_product(): void
    {
        $this->product('Linen Shirt');
        $content = Defaults::content('product');
        unset($content['sections']['product-main-1']);
        $content['order'] = array_values(array_diff($content['order'], ['product-main-1']));
        $doc = new Document(['handle' => 'product', 'kind' => 'template', 'page_type' => 'product']);
        $doc->forceFill(['published' => $content, 'published_version' => 1])->save();

        $this->page('/product/linen-shirt')->assertSee('data-section-type="product-main"', false)->assertSee('<h1 class="pinfo__title"', false);
    }

    public function test_every_page_type_ships_a_default_with_its_main_section(): void
    {
        $mains = [
            'product' => 'product-main', 'cart' => 'cart-main', 'category' => 'category-main', 'search' => 'search-main',
            'brand' => 'brand-main', 'brand-list' => 'brand-list-main', 'blog' => 'blog-main', 'blog-post' => 'post-main',
            'page' => 'page-main', 'contact' => 'contact-main', 'not-found' => 'not-found-main',
        ];
        foreach ($mains as $handle => $type) {
            $types = array_column(Defaults::content($handle)['sections'] ?? [], 'type');
            $this->assertContains($type, $types, "The {$handle} default has no {$type}.");
        }
    }
}
