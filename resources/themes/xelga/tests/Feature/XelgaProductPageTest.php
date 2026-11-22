<?php

namespace Themes\Xelga\Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\ProductDiscount;
use App\Models\ProductSpecial;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\VariantType;
use App\Services\CartService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Themes\Xelga\Tests\XelgaTestHelpers;
use Themes\Xelga\Rendering\Storefront;
use Themes\Xelga\Sections\RecentlyViewed;

require_once __DIR__ . '/../XelgaTestHelpers.php';

/**
 * The product page on real data: every price, tier, variant and stock note
 * is what core works out for the shopper looking, and nothing about
 * another customer group leaks onto the page.
 */
class XelgaProductPageTest extends TestCase
{
    use RefreshDatabase;
    use XelgaTestHelpers;

    private Store $store;

    private CustomerGroup $retail;

    private CustomerGroup $resellers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installXelga();
        $this->store = Store::firstOrCreate(['code' => 'demo'], ['name' => 'Demo Store']);
        $this->retail = CustomerGroup::create(['store_id' => $this->store->id, 'name' => 'Retail', 'is_default' => true]);
        $this->resellers = CustomerGroup::create(['store_id' => $this->store->id, 'name' => 'Resellers']);
        // Core's visibility scope steps aside for console runs; these tests are storefront requests.
        (new \ReflectionProperty(Application::class, 'isRunningInConsole'))->setValue($this->app, false);
    }

    private function product(string $name, array $extra = []): Product
    {
        return Product::create($extra + [
            'store_id' => $this->store->id, 'name' => $name, 'slug' => Str::slug($name),
            'sku' => 'DEMO-' . Str::upper(Str::random(6)), 'price' => 1000, 'quantity' => 20,
            'track_stock' => true, 'is_active' => true, 'description' => '<p>A light linen shirt for humid days.</p>',
        ]);
    }

    private function reseller(): Customer
    {
        return Customer::create(['store_id' => $this->store->id, 'customer_group_id' => $this->resellers->id, 'first_name' => 'Maria', 'last_name' => 'Santos',
            'email' => 'maria@example.com', 'password' => bcrypt('secret-password'), 'is_active' => true]);
    }

    private function page(string $url): TestResponse
    {
        Storefront::forgetRequestState();

        return $this->get($url)->assertOk();
    }

    private function productData(TestResponse $response): array
    {
        preg_match('/<script type="application\/json" data-product-data>(.*?)<\/script>/s', $response->getContent(), $m);
        $this->assertNotEmpty($m, 'The page data is missing.');

        return json_decode(html_entity_decode($m[1]), true);
    }

    public function test_the_product_page_draws_core_data_in_the_shipped_layout(): void
    {
        $brand = Brand::create(['store_id' => $this->store->id, 'name' => 'Northwind', 'slug' => 'northwind', 'is_active' => true]);
        $shirts = Category::create(['store_id' => $this->store->id, 'name' => 'Shirts', 'slug' => 'shirts', 'is_active' => true]);
        $shirt = $this->product('Linen Shirt', ['brand_id' => $brand->id]);
        $shirt->categories()->attach($shirts->id);
        $other = $this->product('Linen Trousers');
        $other->categories()->attach($shirts->id);

        $response = $this->page('/product/linen-shirt')
            ->assertSee('data-section-type="breadcrumbs"', false)
            ->assertSee('data-section-type="product-main"', false)
            ->assertSee('data-section-type="product-details"', false)
            ->assertSee('<h1 class="pinfo__title"', false)
            ->assertSee('Linen Shirt')->assertSee('Northwind')->assertSee(money(1000))
            ->assertSee('A light linen shirt for humid days.')
            ->assertSee('In stock, ready to ship')
            ->assertSee('Linen Trousers')
            ->assertSee('application/ld+json', false)
            ->assertSee('data-cart-add=', false);

        $this->assertSame(1, substr_count($response->getContent(), 'data-section-type="product-main"'));
        // Shoppers never see the editor's notes.
        $response->assertDontSee('editor-note');
    }

    public function test_a_product_kept_for_one_group_is_not_found_for_a_guest(): void
    {
        $kept = $this->product('Reseller Bundle');
        $kept->customerGroups()->attach($this->resellers->id);

        Storefront::forgetRequestState();
        $this->get('/product/reseller-bundle')->assertNotFound();

        $this->actingAs($this->reseller(), 'customer');
        $this->page('/product/reseller-bundle')->assertSee('Reseller Bundle');
    }

    public function test_volume_tiers_show_only_to_the_group_core_gives_them_to(): void
    {
        $shirt = $this->product('Linen Shirt');
        ProductDiscount::create(['product_id' => $shirt->id, 'customer_group_id' => $this->resellers->id, 'quantity' => 6, 'price_type' => 'fixed', 'value' => 900]);
        ProductDiscount::create(['product_id' => $shirt->id, 'customer_group_id' => $this->resellers->id, 'quantity' => 12, 'price_type' => 'fixed', 'value' => 800]);

        $guest = $this->page('/product/linen-shirt');
        $guest->assertDontSee('data-tier="6"', false)->assertDontSee(money(900))->assertDontSee(money(800));
        $this->assertSame([['qty' => 1, 'price' => money(1000)]], $this->productData($guest)['points']);

        $this->actingAs($this->reseller(), 'customer');
        $reseller = $this->page('/product/linen-shirt')
            ->assertSee('data-tier="6"', false)->assertSee('6 to 11 pieces')->assertSee('12 or more pieces')
            ->assertSee(money(900) . ' each')->assertSee(money(800) . ' each');

        $product = Product::find($shirt->id)->load('specials');
        $expected = array_map(fn ($q) => ['qty' => $q, 'price' => money(CartService::resolvePrice($product, 1000, $q, $this->resellers->id))], [1, 6, 12]);
        $this->assertSame($expected, $this->productData($reseller)['points']);
    }

    public function test_a_tier_for_the_default_group_shows_to_guests_as_core_gives_it(): void
    {
        $shirt = $this->product('Linen Shirt');
        ProductDiscount::create(['product_id' => $shirt->id, 'customer_group_id' => $this->retail->id, 'quantity' => 10, 'price_type' => 'fixed', 'value' => 950]);

        $this->page('/product/linen-shirt')->assertSee('data-tier="10"', false)->assertSee('10 or more pieces')->assertSee(money(950) . ' each');
    }

    public function test_a_sale_shows_the_regular_price_struck_and_a_countdown_from_the_special(): void
    {
        $shirt = $this->product('Linen Shirt');
        $end = now()->addDays(2)->startOfMinute();
        ProductSpecial::create(['product_id' => $shirt->id, 'price_type' => 'fixed', 'value' => 750, 'date_end' => $end]);

        $this->page('/product/linen-shirt')
            ->assertSee(money(750))->assertSee('<s class="price__was" data-price-was >' . money(1000) . '</s>', false)
            ->assertSee('-25%')
            ->assertSee('data-sale-end="', false);
    }

    public function test_a_sale_kept_for_another_group_does_not_reach_a_guest(): void
    {
        $shirt = $this->product('Linen Shirt');
        ProductDiscount::create(['product_id' => $shirt->id, 'customer_group_id' => $this->resellers->id, 'quantity' => 1, 'price_type' => 'fixed', 'value' => 700]);

        $this->page('/product/linen-shirt')->assertDontSee(money(700))->assertDontSee('data-sale-end', false)->assertSee(money(1000));
    }

    public function test_the_full_screen_viewer_lists_every_photo_in_the_gallery_order(): void
    {
        $shirt = $this->product('Linen Shirt', ['image' => 'media/products/demo/front.jpg']);
        $shirt->images()->create(['path' => 'media/products/demo/back.jpg', 'sort_order' => 1]);
        $shirt->images()->create(['path' => 'media/products/demo/side.jpg', 'sort_order' => 2]);

        $html = $this->page('/product/linen-shirt')
            ->assertSee('data-lightbox-track', false)
            ->assertSee('data-lightbox-prev', false)->assertSee('aria-label="Previous photo"', false)
            ->assertSee('data-lightbox-next', false)->assertSee('aria-label="Next photo"', false)
            ->assertSee('data-lightbox-close', false)
            ->assertSee('<span data-lightbox-now>1</span> / 3', false)
            ->getContent();

        // The viewer holds one slide per gallery photo, in the same order, so a
        // photo picked on the page (or by a variant) opens at the same place.
        preg_match_all('/data-lightbox-slide="(\d+)"/', $html, $viewer);
        preg_match_all('/class="gallery__slide"[^>]*data-index="(\d+)"/', $html, $gallery);
        $this->assertSame(['0', '1', '2'], $viewer[1]);
        $this->assertSame($gallery[1], $viewer[1]);
        $this->assertLessThan(strpos($html, 'back.jpg', strpos($html, 'data-lightbox-track')), strpos($html, 'front.jpg', strpos($html, 'data-lightbox-track')));
    }

    public function test_a_single_photo_opens_full_screen_without_arrows_or_a_counter(): void
    {
        $this->product('Linen Shirt', ['image' => 'media/products/demo/front.jpg']);

        $html = $this->page('/product/linen-shirt')->getContent();
        $this->assertSame(1, substr_count($html, 'data-lightbox-slide='));
        $this->assertStringContainsString('data-lightbox-close', $html);
        $this->assertStringNotContainsString('data-lightbox-prev', $html);
        $this->assertStringNotContainsString('data-lightbox-now', $html);
    }

    public function test_variants_carry_core_prices_stock_and_photos(): void
    {
        $shirt = $this->product('Linen Shirt', ['has_variants' => true, 'quantity' => 0]);
        $color = VariantType::create(['product_id' => $shirt->id, 'name' => 'Color', 'sort_order' => 0]);
        $size = VariantType::create(['product_id' => $shirt->id, 'name' => 'Size', 'sort_order' => 1]);
        $oat = $color->values()->create(['value' => 'Oat', 'sort_order' => 0]);
        $navy = $color->values()->create(['value' => 'Navy', 'sort_order' => 1]);
        $m = $size->values()->create(['value' => 'M', 'sort_order' => 0]);
        $off = $size->values()->create(['value' => 'XXL', 'sort_order' => 1]);

        $a = ProductVariant::create(['product_id' => $shirt->id, 'sku' => 'DEMO-001-OM', 'price' => 1000, 'quantity' => 3, 'is_active' => true, 'image' => 'media/products/demo/oat.jpg']);
        $a->values()->attach([$oat->id, $m->id]);
        $b = ProductVariant::create(['product_id' => $shirt->id, 'sku' => 'DEMO-001-NM', 'price' => 1150, 'quantity' => 0, 'is_active' => true]);
        $b->values()->attach([$navy->id, $m->id]);
        $c = ProductVariant::create(['product_id' => $shirt->id, 'sku' => 'DEMO-001-NX', 'price' => 1200, 'quantity' => 5, 'is_active' => false]);
        $c->values()->attach([$navy->id, $off->id]);

        $response = $this->page('/product/linen-shirt')
            ->assertSee('data-swatch="oat"', false)->assertSee('Navy')->assertSee('>M<', false)
            // A value only a switched-off variant uses is not offered.
            ->assertDontSee('XXL');

        $data = $this->productData($response);
        $key = collect([$oat->id, $m->id])->sort()->implode('_');
        $this->assertSame($a->id, $data['units'][$key]['id']);
        $this->assertSame(3, $data['units'][$key]['stock']);
        $this->assertNotNull($data['units'][$key]['image']);
        $navyKey = collect([$navy->id, $m->id])->sort()->implode('_');
        $this->assertSame(0, $data['units'][$navyKey]['stock']);
        $this->assertSame([['qty' => 1, 'price' => money(1150)]], $data['units'][$navyKey]['points']);
        $this->assertCount(2, $data['units']);
    }

    public function test_a_sold_out_product_disables_buying_and_offers_the_stock_alert_when_it_is_on(): void
    {
        $this->product('Linen Shirt', ['quantity' => 0]);

        $response = $this->page('/product/linen-shirt')->assertSee('Sold out');
        $this->assertMatchesRegularExpression('/<button type="button" class="btn btn--lg buy__add"[^>]*disabled/', $response->getContent());

        if (\Illuminate\Support\Facades\Route::has('notify-stock.store')) {
            $response->assertSee('data-notify-form', false)->assertSee('name="name"', false)->assertSee('name="email"', false);
        } else {
            $response->assertDontSee('data-notify-form', false);
        }
    }

    public function test_the_stock_alert_form_works_without_javascript(): void
    {
        try {
            $manager = app(\App\Services\ExtensionManager::class);
            $manager->install('stock-alerts');
            $manager->bootEnabled();
            app('router')->getRoutes()->refreshNameLookups();
        } catch (\Throwable) {
            $this->markTestSkipped('The stock alerts extension could not be installed here.');
        }
        if (! \Illuminate\Support\Facades\Route::has('notify-stock.store')) {
            $this->markTestSkipped('The stock alerts routes are not registered in this run.');
        }
        $product = $this->product('Linen Shirt', ['quantity' => 0]);

        $html = $this->page('/product/linen-shirt')->getContent();
        $this->assertMatchesRegularExpression('/<form class="notify__form"[^>]*>\s*<input type="hidden" name="_token" value="[^"]+"/', $html);
        $this->assertStringContainsString('<input type="hidden" name="product_id" value="' . $product->id . '">', $html);
    }

    public function test_a_text_panel_is_cleaned_when_it_is_printed(): void
    {
        $this->product('Linen Shirt');
        $content = \Themes\Xelga\Documents\Defaults::content('product');
        $content['sections']['product-details-1']['block_order'][] = 'product-details-1-guide';
        $content['sections']['product-details-1']['blocks']['product-details-1-guide'] = [
            'type' => 'text', 'label' => 'Size guide', 'disabled' => false,
            'settings' => ['title' => 'Size guide', 'body' => '<p>Measure twice.</p><img src="x" onerror="alert(1)"><script>alert(2)</script>'],
        ];
        // Stored as is, as an older version or a direct write could have left it.
        $doc = new \Themes\Xelga\Documents\Document(['handle' => 'product', 'kind' => 'template', 'page_type' => 'product']);
        $doc->forceFill(['published' => $content, 'published_version' => 1])->save();

        $html = $this->page('/product/linen-shirt')->assertSee('Measure twice.')->getContent();
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('<script>alert(2)', $html);
    }

    public function test_low_stock_says_how_many_are_left(): void
    {
        $this->product('Linen Shirt', ['quantity' => 2]);

        $this->page('/product/linen-shirt')->assertSee('Only 2 left')->assertSee('data-level="low"', false);
    }

    public function test_the_page_remembers_the_product_for_recently_viewed_and_shows_the_others(): void
    {
        $shirt = $this->product('Linen Shirt');
        $dress = $this->product('Linen Dress');
        $kept = $this->product('Reseller Bundle');
        $kept->customerGroups()->attach($this->resellers->id);

        Storefront::forgetRequestState();
        $this->get('/product/linen-shirt')->assertOk()->assertCookie(RecentlyViewed::COOKIE, (string) $shirt->id);

        Storefront::forgetRequestState();
        $this->withCookie(RecentlyViewed::COOKIE, implode(',', [$kept->id, $shirt->id]))
            ->get('/product/linen-dress')->assertOk()
            ->assertSee('data-section-type="recently-viewed"', false)
            ->assertSee('Linen Shirt')
            // A product the shopper may not see drops out of the list.
            ->assertDontSee('Reseller Bundle');

        // The product being viewed is never in its own list.
        Storefront::forgetRequestState();
        $this->withCookie(RecentlyViewed::COOKIE, (string) $dress->id)
            ->get('/product/linen-dress')->assertOk()
            ->assertDontSee('data-section-type="recently-viewed"', false);
    }

    public function test_the_editor_preview_shows_the_latest_products_as_recently_viewed_stand_ins(): void
    {
        $this->product('Linen Shirt');
        $this->product('Cotton Scarf');
        $this->product('Linen Dress');
        // Storefront requests here run as from a browser, so the editor's POST would need a form token.
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $nonce = $this->openEditor($this->admin());

        // The editor's preview: the list is empty, so the latest products stand in, never the one viewed.
        Storefront::forgetRequestState();
        $html = $this->get('/product/linen-dress?xelga_preview=' . $nonce)->assertOk()->getContent();
        $list = $this->sectionHtml($html, 'recently-viewed');
        $this->assertNotNull($list, 'Recently viewed is drawn in the preview.');
        $this->assertStringContainsString('Cotton Scarf', $list);
        $this->assertStringContainsString('Linen Shirt', $list);
        $this->assertStringNotContainsString('Linen Dress', $list);

        // The same admin outside the preview, and every shopper, get no stand-ins.
        Storefront::forgetRequestState();
        $this->get('/product/linen-dress')->assertOk()->assertDontSee('data-section-type="recently-viewed"', false);
        $this->asGuest();
        Storefront::forgetRequestState();
        $this->get('/product/linen-dress')->assertOk()->assertDontSee('data-section-type="recently-viewed"', false);
    }

    /** The markup of the first section of a type on a page, or null when the page does not draw one. */
    private function sectionHtml(string $html, string $type): ?string
    {
        $start = strpos($html, 'data-section-type="' . $type . '"');
        if ($start === false) {
            return null;
        }
        $end = strpos($html, 'data-section-type=', $start + 20);

        return substr($html, $start, $end === false ? null : $end - $start);
    }

    public function test_reviews_hide_while_the_extension_is_off(): void
    {
        $this->product('Linen Shirt');
        if (\App\Support\Reviews::enabled()) {
            $this->markTestSkipped('The reviews extension is on in this install.');
        }

        $this->page('/product/linen-shirt')->assertDontSee('data-section-type="product-reviews"', false);
    }

    public function test_an_inactive_product_answers_with_the_theme_s_not_found_page(): void
    {
        $this->product('Linen Shirt', ['is_active' => false]);

        Storefront::forgetRequestState();
        $this->get('/product/linen-shirt')->assertNotFound()->assertSee('data-section-type="not-found-main"', false);
    }
}
