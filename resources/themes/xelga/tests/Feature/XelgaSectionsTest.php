<?php

namespace Themes\Xelga\Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\ProductDiscount;
use App\Models\ProductSpecial;
use App\Models\Store;
use App\Models\VariantType;
use App\Models\VariantTypeValue;
use App\Support\Pricing;
use App\Support\StoreIdentity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Themes\Xelga\Tests\XelgaTestHelpers;
use Themes\Xelga\Catalog\Catalog;
use Themes\Xelga\Rendering\Storefront;

require_once __DIR__ . '/../XelgaTestHelpers.php';

/**
 * The home page sections on real data: each draws from core's catalog through
 * core's own classes, follows core's visibility and pricing, shows shoppers
 * nothing when it has nothing to show, and tells the editor why.
 */
class XelgaSectionsTest extends TestCase
{
    use RefreshDatabase;
    use XelgaTestHelpers;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installXelga();
        $this->store = Store::firstOrCreate(['code' => 'demo'], ['name' => 'Demo Store']);
    }

    private function product(string $name, array $extra = []): Product
    {
        return Product::create($extra + [
            'store_id' => $this->store->id, 'name' => $name, 'slug' => Str::slug($name) . '-' . uniqid(),
            'sku' => 'DEMO-' . Str::upper(Str::random(6)), 'price' => 1250, 'quantity' => 5, 'is_active' => true,
        ]);
    }

    /** A home page of the given sections ({type, settings, blocks}), published. */
    private function publishHome(array $sections): void
    {
        $content = ['schema' => 1, 'order' => [], 'sections' => []];
        foreach ($sections as $i => $spec) {
            $id = $spec['type'] . '-t' . $i;
            $content['order'][] = $id;
            $section = ['type' => $spec['type'], 'label' => '', 'disabled' => false, 'settings' => $spec['settings'] ?? [], 'block_order' => [], 'blocks' => []];
            foreach ($spec['blocks'] ?? [] as $j => $block) {
                $bid = $id . '-b' . $j;
                $section['block_order'][] = $bid;
                $section['blocks'][$bid] = ['type' => $block['type'], 'label' => '', 'disabled' => false, 'settings' => $block['settings'] ?? []];
            }
            $content['sections'][$id] = $section;
        }

        $admin = $this->admin();
        $this->saveDraft($admin, 'home', $content, 0)->assertOk();
        $this->publish($admin, ['home' => 1])->assertOk();
        $this->asGuest();
        Storefront::forgetRequestState();
    }

    private function publishSettings(array $values): void
    {
        $settings = $this->defaults('settings');
        $settings['values'] = $values + $settings['values'];
        $admin = $this->admin();
        $this->saveDraft($admin, 'settings', $settings, 0)->assertOk();
        $this->publish($admin, ['settings' => 1])->assertOk();
        $this->asGuest();
        Storefront::forgetRequestState();
    }

    /** Core's visibility scope stands down in console runs; a shopper's request has it on. */
    private function asStorefrontRequest(): void
    {
        (new \ReflectionProperty(Application::class, 'isRunningInConsole'))->setValue($this->app, false);
    }

    private function home(): TestResponse
    {
        Storefront::forgetRequestState();

        return $this->get('/')->assertOk();
    }

    public function test_every_section_type_and_preset_renders_without_failing(): void
    {
        $this->product('Linen Shirt Dress');
        $sections = [];
        foreach (Catalog::sections() as $type => $spec) {
            // The home page takes every section allowed on it; page main sections have their own tests.
            if (($spec['group'] ?? 'template') !== 'template' || ! Catalog::section($type)->allowedIn('home', 'template', 'home')) {
                continue;
            }
            foreach ($spec['presets'] ?: [['settings' => [], 'blocks' => []]] as $preset) {
                $sections[] = ['type' => $type, 'settings' => $preset['settings'] ?? [], 'blocks' => $preset['blocks'] ?? []];
            }
        }
        // A page holds at most 40 sections, and a type with a limit at most
        // that many, so they are drawn on as many home pages as that takes.
        $chunks = [];
        foreach ($sections as $section) {
            $limit = Catalog::section($section['type'])->limit();
            foreach ($chunks as $i => $chunk) {
                $same = count(array_filter($chunk, fn (array $s) => $s['type'] === $section['type']));
                if (count($chunk) < 40 && ($limit === null || $same < $limit)) {
                    $chunks[$i][] = $section;
                    continue 2;
                }
            }
            $chunks[] = [$section];
        }
        $html = '';
        foreach ($chunks as $chunk) {
            \Themes\Xelga\Documents\Document::where('handle', 'home')->delete();
            $this->publishHome($chunk);
            $page = $this->home()->getContent();
            $this->assertStringNotContainsString('could not be drawn', $page);
            $html .= $page;
        }
        foreach (['hero', 'usp-bar', 'featured-products', 'image-with-text', 'promo-banners', 'testimonials', 'rich-text'] as $type) {
            $this->assertStringContainsString('data-section-type="' . $type . '"', $html, $type);
        }
        $this->assertStringNotContainsString('newsletter', $html);
    }

    public function test_the_catalog_has_the_section_types_and_no_newsletter(): void
    {
        $this->assertSame([
            'announcement-bar', 'blog-main', 'blog-posts', 'brand-list-main', 'brand-logos', 'brand-main', 'breadcrumbs',
            'cart-main', 'category-list', 'category-main', 'category-tiles', 'contact-main', 'divider', 'featured-products', 'filters', 'footer', 'header', 'hero',
            'image-with-text', 'not-found-main', 'page-main', 'post-main', 'product-details', 'product-main', 'product-reviews',
            'product-spotlight', 'promo-banners', 'recently-viewed', 'related-products', 'rich-text', 'search-main',
            'shop-the-look', 'testimonials', 'usp-bar',
        ], array_keys(Catalog::sections()));
        $this->assertSame([], \Themes\Xelga\Catalog\CatalogLinter::check());
    }

    public function test_featured_products_show_core_prices_and_sales(): void
    {
        $plain = $this->product('Merino Cardigan', ['price' => 3250]);
        $sale = $this->product('Wool Jacket', ['price' => 6000]);
        ProductSpecial::create(['product_id' => $sale->id, 'price_type' => 'fixed', 'value' => 4500, 'date_end' => now()->addWeek()]);
        $this->publishHome([['type' => 'featured-products', 'settings' => ['heading' => 'New this season', 'source' => 'newest', 'count' => 8]]]);

        $response = $this->home();
        $response->assertSee('Merino Cardigan')->assertSee('Wool Jacket')
            ->assertSee(money(3250))
            ->assertSee(money(Pricing::display($sale->fresh())->price))
            ->assertSee('<s class="price__was">' . e(money(6000)) . '</s>', false)
            ->assertSee('data-add-to-cart data-product-id="' . $plain->id . '"', false)
            ->assertSee('-25%');
    }

    public function test_on_sale_lists_only_products_with_a_running_sale(): void
    {
        $this->product('Regular Price Tee');
        $sale = $this->product('Discounted Tote');
        ProductSpecial::create(['product_id' => $sale->id, 'price_type' => 'fixed', 'value' => 900, 'date_end' => now()->addWeek()]);
        $this->publishHome([['type' => 'featured-products', 'settings' => ['heading' => 'On sale now', 'source' => 'on-sale']]]);

        $this->home()->assertSee('Discounted Tote')->assertDontSee('Regular Price Tee');
    }

    public function test_hand_picked_products_keep_the_merchants_order(): void
    {
        $a = $this->product('First Pick');
        $b = $this->product('Second Pick');
        $c = $this->product('Third Pick');
        $this->publishHome([['type' => 'featured-products', 'settings' => ['source' => 'hand-picked', 'products' => [$c->id, $a->id, $b->id]]]]);

        $this->home()->assertSeeInOrder(['Third Pick', 'First Pick', 'Second Pick']);
    }

    public function test_a_product_kept_for_one_group_shows_only_to_that_group_with_its_price(): void
    {
        $wholesale = CustomerGroup::create(['store_id' => $this->store->id, 'name' => 'Resellers']);
        CustomerGroup::create(['store_id' => $this->store->id, 'name' => 'Retail', 'is_default' => true]);
        $this->product('Public Linen Shirt', ['price' => 2000]);
        $kept = $this->product('Reseller Bundle', ['price' => 9000]);
        $kept->customerGroups()->attach($wholesale->id);
        $shared = $this->product('Shared Trouser', ['price' => 3000]);
        ProductDiscount::create(['product_id' => $shared->id, 'customer_group_id' => $wholesale->id, 'quantity' => 1, 'price_type' => 'fixed', 'value' => 2400]);
        $this->publishHome([
            ['type' => 'featured-products', 'settings' => ['heading' => 'Everything', 'source' => 'newest', 'count' => 12]],
            ['type' => 'product-spotlight', 'settings' => ['heading' => 'For resellers', 'product' => $kept->id]],
        ]);
        $this->asStorefrontRequest();

        $this->home()->assertSee('Public Linen Shirt')->assertDontSee('Reseller Bundle')
            ->assertDontSee('For resellers')->assertSee(money(3000))->assertDontSee(money(2400));

        $customer = Customer::create(['store_id' => $this->store->id, 'customer_group_id' => $wholesale->id, 'first_name' => 'Maria', 'last_name' => 'Santos',
            'email' => 'maria@example.com', 'password' => bcrypt('secret-password'), 'is_active' => true]);
        $this->actingAs($customer, 'customer');

        $this->home()->assertSee('Reseller Bundle')->assertSee('For resellers')->assertSee(money(2400));
    }

    public function test_an_empty_product_section_shows_shoppers_nothing_and_tells_the_editor_why(): void
    {
        $this->publishHome([['type' => 'featured-products', 'settings' => ['heading' => 'From a category', 'source' => 'category']]]);

        $this->home()->assertDontSee('data-section-type="featured-products"', false);

        $nonce = $this->openEditor($this->admin());
        Storefront::forgetRequestState();
        $this->get('/?xelga_preview=' . $nonce)->assertOk()
            ->assertSee('data-section-type="featured-products"', false)
            ->assertSee('Pick a category for this section.');
    }

    public function test_spotlight_counts_down_to_the_end_of_the_special_core_set(): void
    {
        $product = $this->product('Pedal of the Week', ['price' => 7600]);
        $end = now()->addDays(3)->startOfMinute();
        ProductSpecial::create(['product_id' => $product->id, 'price_type' => 'fixed', 'value' => 6450, 'date_end' => $end]);
        $this->publishHome([['type' => 'product-spotlight', 'settings' => ['heading' => 'Deal of the week', 'product' => $product->id, 'show_countdown' => true]]]);

        $this->home()->assertSee('Pedal of the Week')->assertSee(money(6450))
            ->assertSee('data-countdown-end="' . Pricing::display($product->fresh())->endsAtIso() . '"', false);
    }

    public function test_spotlight_has_no_countdown_for_a_discount_or_a_special_far_away(): void
    {
        // A discount never ends, so there is nothing to count down to.
        $open = $this->product('Open Ended Deal');
        ProductDiscount::create(['product_id' => $open->id, 'customer_group_id' => null, 'quantity' => 1, 'price_type' => 'fixed', 'value' => 1000]);
        $far = $this->product('Far Away Deal');
        ProductSpecial::create(['product_id' => $far->id, 'price_type' => 'fixed', 'value' => 1000, 'date_end' => now()->addYears(2)]);
        $this->publishHome([
            ['type' => 'product-spotlight', 'settings' => ['product' => $open->id]],
            ['type' => 'product-spotlight', 'settings' => ['product' => $far->id]],
        ]);

        $this->home()->assertSee('Open Ended Deal')->assertSee('Far Away Deal')->assertDontSee('data-countdown-end', false);
    }

    public function test_a_product_with_variants_opens_its_page_instead_of_quick_add(): void
    {
        $product = $this->product('Linen Shirt', ['has_variants' => true]);
        $this->publishHome([['type' => 'featured-products', 'settings' => ['source' => 'hand-picked', 'products' => [$product->id]]]]);

        $this->home()->assertSee('Choose a variant')->assertDontSee('data-add-to-cart data-product-id="' . $product->id . '"', false);
    }

    public function test_a_sold_out_product_says_so_and_cannot_be_added(): void
    {
        $product = $this->product('Last Season Coat', ['quantity' => 0, 'track_stock' => true]);
        $this->publishHome([['type' => 'featured-products', 'settings' => ['source' => 'hand-picked', 'products' => [$product->id]]]]);

        $this->home()->assertSee('badge--soldout', false)->assertSee('Sold out')->assertDontSee('data-add-to-cart data-product-id="' . $product->id . '"', false);
    }

    public function test_color_dots_come_from_the_products_color_variants_when_switched_on(): void
    {
        $product = $this->product('Knit Tank', ['has_variants' => true]);
        $type = VariantType::create(['product_id' => $product->id, 'name' => 'Color', 'sort_order' => 1]);
        VariantTypeValue::create(['variant_type_id' => $type->id, 'value' => 'Navy', 'sort_order' => 1]);
        VariantTypeValue::create(['variant_type_id' => $type->id, 'value' => 'Pattern A', 'sort_order' => 2]);
        $this->publishSettings(['show_swatches' => true]);
        $this->publishHome([['type' => 'featured-products', 'settings' => ['source' => 'hand-picked', 'products' => [$product->id]]]]);

        $this->home()->assertSee('data-card-swatches="true"', false)
            ->assertSee('class="swatch" data-swatch="navy" title="Navy"', false)
            ->assertSee('data-swatch="" title="Pattern A"', false);
    }

    public function test_category_tiles_link_to_core_categories_and_drop_switched_off_ones(): void
    {
        $women = Category::create(['store_id' => $this->store->id, 'name' => 'Women', 'slug' => 'women', 'is_active' => true]);
        $hidden = Category::create(['store_id' => $this->store->id, 'name' => 'Archive', 'slug' => 'archive', 'is_active' => false]);
        $this->publishHome([['type' => 'category-tiles', 'settings' => ['heading' => 'Shop by category'], 'blocks' => [
            ['type' => 'category', 'settings' => ['category' => $women->id]],
            ['type' => 'category', 'settings' => ['category' => $hidden->id, 'label' => 'Old things']],
        ]]]);

        $this->home()->assertSee('Shop by category')->assertSee('Women')
            ->assertSee('href="' . route('category.show', 'women') . '"', false)
            ->assertDontSee('Old things');
    }

    public function test_brand_logos_list_the_active_brands_from_core(): void
    {
        Brand::create(['store_id' => $this->store->id, 'name' => 'Northwind', 'slug' => 'northwind', 'is_active' => true, 'sort_order' => 2]);
        Brand::create(['store_id' => $this->store->id, 'name' => 'Aurora', 'slug' => 'aurora', 'is_active' => true, 'sort_order' => 1]);
        Brand::create(['store_id' => $this->store->id, 'name' => 'Retired Label', 'slug' => 'retired', 'is_active' => false]);
        $this->publishHome([['type' => 'brand-logos', 'settings' => ['source' => 'all', 'heading' => 'Brands we carry']]]);

        $this->home()->assertSeeInOrder(['Aurora', 'Northwind'])->assertDontSee('Retired Label')
            ->assertSee('data-odd="false"', false)
            ->assertSee('href="' . route('brand.show', 'aurora') . '"', false);
    }

    public function test_shop_the_look_leaves_out_a_dot_whose_product_is_switched_off(): void
    {
        $on = $this->product('Satin Slip Dress');
        $off = $this->product('Retired Blazer');
        $this->publishHome([['type' => 'shop-the-look', 'settings' => ['heading' => 'Shop the look'], 'blocks' => [
            ['type' => 'hotspot', 'settings' => ['product' => $on->id, 'x' => 40, 'y' => 30]],
            ['type' => 'hotspot', 'settings' => ['product' => $off->id, 'x' => 60, 'y' => 70]],
        ]]]);
        $off->update(['is_active' => false]);

        $this->home()->assertSee('Satin Slip Dress')->assertDontSee('Retired Blazer')
            ->assertSee('data-x="40" data-y="30"', false);
    }

    public function test_blog_posts_show_nothing_while_the_blog_is_off_and_say_so_in_the_editor(): void
    {
        $this->publishHome([['type' => 'blog-posts', 'settings' => ['heading' => 'From the blog']]]);

        $this->home()->assertDontSee('From the blog');

        $nonce = $this->openEditor($this->admin());
        Storefront::forgetRequestState();
        $this->get('/?xelga_preview=' . $nonce)->assertOk()->assertSee('The blog is switched off');
    }

    public function test_blog_posts_list_published_posts_when_the_blog_is_on(): void
    {
        $manager = app(\App\Services\ExtensionManager::class);
        $manager->install('blog');
        $manager->bootEnabled();
        \Extensions\Blog\Models\BlogPost::create(['title' => 'How to wash linen', 'slug' => 'wash-linen', 'content' => '<p>Cold water.</p>', 'excerpt' => 'Cold water, no dryer.', 'is_published' => true, 'published_at' => now()->subDay()]);
        \Extensions\Blog\Models\BlogPost::create(['title' => 'A post for next week', 'slug' => 'next-week', 'content' => '<p>Soon.</p>', 'is_published' => true, 'published_at' => now()->addWeek()]);
        $this->publishHome([['type' => 'blog-posts', 'settings' => ['heading' => 'From the blog']]]);

        $this->home()->assertSee('From the blog')->assertSee('How to wash linen')->assertSee('Cold water, no dryer.')
            ->assertDontSee('A post for next week');

        $manager->disable('blog');
        $this->home()->assertDontSee('From the blog');
    }

    public function test_the_footer_reads_contact_details_from_the_store_and_socials_from_the_theme(): void
    {
        Store::query()->update(['phone' => '0917 000 0000', 'email' => 'hello@example.com', 'address' => 'Quezon City']);
        StoreIdentity::flush();
        $this->publishSettings([
            'instagram_url' => ['type' => 'url', 'href' => 'https://instagram.com/demostore'],
            'facebook_url' => ['type' => 'url', 'href' => 'javascript:alert(1)'],
            'hours' => 'Mon to Sat, 9 am to 6 pm',
            'show_payment_icons' => false,
        ]);

        $this->home()->assertSee('hello@example.com')->assertSee('0917 000 0000')->assertSee('Mon to Sat, 9 am to 6 pm')
            ->assertSee('href="https://instagram.com/demostore"', false)
            ->assertDontSee('javascript:', false)
            ->assertSee('data-payment-icons="false"', false);
    }

    public function test_theme_settings_reach_the_page_as_html_attributes_and_tokens(): void
    {
        $this->publishSettings(['card_style' => 'panel', 'quick_add' => 'icon', 'cart_icon' => 'cart', 'card_ratio' => 'tall', 'font_pair' => 'barlow-semi']);

        $html = $this->home()->getContent();
        $this->assertMatchesRegularExpression('/<html[^>]*data-card-style="panel"[^>]*data-quick-add="icon"/', $html);
        $this->assertStringContainsString('data-cart-icon="cart"', $html);
        $this->assertStringContainsString('data-cart-add="/cart/add"', $html);

        $css = $this->get(route('xelga.tokens', ['version' => 1]))->assertOk()->getContent();
        $this->assertStringContainsString('--card-image-ratio:3 / 4', $css);
        $this->assertStringContainsString('"Barlow Semi Condensed"', $css);
    }

    public function test_every_font_pair_ships_its_files_and_license(): void
    {
        $pairs = Catalog::fontPairs();
        $this->assertSame(['anybody-albert', 'archivo-public', 'big-shoulders-franklin', 'familjen-instrument', 'bricolage-hanken', 'sofia-condensed', 'barlow-semi'], array_column($pairs, 'value'));
        $css = (string) file_get_contents(Catalog::root('css/base/fonts.css'));
        foreach ($pairs as $pair) {
            foreach ($pair['files'] as $file) {
                $this->assertFileExists(Catalog::root('assets/fonts/' . $file['file']));
                $this->assertFileExists(Catalog::root('assets/fonts/' . $file['license']));
                $this->assertStringContainsString($file['file'], $css);
            }
        }
    }

    public function test_section_settings_are_sanitized_on_save(): void
    {
        $product = $this->product('Real Product');
        $admin = $this->admin();
        $home = ['schema' => 1, 'order' => ['p', 'l', 'b'], 'sections' => [
            'p' => ['type' => 'featured-products', 'label' => '', 'disabled' => false, 'block_order' => [], 'blocks' => [],
                'settings' => ['source' => 'everything', 'products' => [$product->id, 999999, $product->id, 'x'], 'count' => 500, 'heading' => 'Sale']],
            'l' => ['type' => 'shop-the-look', 'label' => '', 'disabled' => false, 'settings' => ['image' => 'evil.svg'], 'block_order' => ['l-1'],
                'blocks' => ['l-1' => ['type' => 'hotspot', 'label' => '', 'disabled' => false, 'settings' => ['x' => 140, 'y' => -5, 'product' => 999999]]]],
            'b' => ['type' => 'blog-posts', 'label' => '', 'disabled' => false, 'block_order' => [], 'blocks' => [],
                'settings' => ['blog_category' => '12', 'link' => ['type' => 'url', 'href' => 'javascript:alert(1)']]],
        ]];

        $this->saveDraft($admin, 'home', $home, 0)->assertOk();
        $draft = \Themes\Xelga\Documents\Document::where('handle', 'home')->first()->draft;

        $p = $draft['sections']['p']['settings'];
        $this->assertSame('best-sellers', $p['source']);
        $this->assertSame([$product->id], $p['products']);
        $this->assertSame(16, $p['count']);

        $spot = $draft['sections']['l']['blocks']['l-1']['settings'];
        $this->assertSame([100, 0, null], [$spot['x'], $spot['y'], $spot['product']]);
        $this->assertSame('', $draft['sections']['l']['settings']['image']);

        $b = $draft['sections']['b']['settings'];
        $this->assertSame('', $b['blog_category']);
        $this->assertNull($b['link']);
    }
}
