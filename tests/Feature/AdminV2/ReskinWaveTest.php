<?php

namespace Tests\Feature\AdminV2;

use Extensions\AbandonedCarts\Models\AbandonedCartNotification;
use Extensions\AbandonedCarts\Models\AbandonedCartRule;
use App\Models\ApiSyncLog;
use App\Models\Automation;
use Extensions\PaymentBankTransfer\Models\BankAccount;
use Extensions\Blog\Models\BlogCategory;
use Extensions\Blog\Models\BlogPost;
use App\Models\Brand;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use Extensions\Coupons\Models\Coupon;
use App\Models\Country;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\CustomerGroup;
use App\Models\EmailTemplate;
use Extensions\ShippingFlatRate\Models\FlatShippingRate;
use App\Models\FooterColumn;
use Extensions\ShippingFree\Models\FreeShippingRule;
use App\Models\GeoZone;
use App\Models\HomepageSection;
use App\Models\NavMenuItem;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderStatus;
use App\Models\Page;
use App\Models\Permission;
use App\Models\Product;
use Extensions\Reviews\Models\Review;
use App\Models\Role;
use Extensions\Seo\Models\SeoSuggestion;
use App\Models\ShippingCourier;
use App\Models\Store;
use App\Models\Tag;
use App\Models\User;
use Extensions\ShippingWeightBased\Models\WeightBasedMethod;
use App\Models\Zone;
use Extensions\Banners\Models\Banner;
use Extensions\Banners\Models\BannerGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Shared home for the admin-v2 final-wave reskin tests. Each wave task adds
 * one test_<wave>_pages_render_in_new_shell() method plus, where the old
 * view had a save round-trip worth pinning, one POST/PUT test asserting the
 * database write. See docs/superpowers/plans/2026-08-07-admin-v2-reskin-protocol.md.
 */
class ReskinWaveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Store::create(['id' => 1, 'name' => 'Test Store', 'code' => 'test']);
    }

    public function test_orders_pages_render_in_new_shell(): void
    {
        $this->actingAs(User::factory()->create());

        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Pending', 'sort_order' => 1, 'is_default' => true]);

        $order = Order::create([
            'store_id' => 1,
            'order_status_id' => $status->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'subtotal' => 1000,
            'shipping_cost' => 100,
            'total' => 1100,
        ]);

        $routes = [
            route('admin.orders.index'),
            route('admin.orders.create'),
            route('admin.orders.show', $order),
            route('admin.orders.edit', $order),
        ];

        foreach ($routes as $url) {
            $this->get($url)->assertOk()->assertSee('v-admin', false);
        }
    }

    public function test_order_status_change_round_trip_updates_order_and_history(): void
    {
        $this->actingAs(User::factory()->create());

        $pending = OrderStatus::create(['store_id' => 1, 'name' => 'Pending', 'sort_order' => 1, 'is_default' => true]);
        $processing = OrderStatus::create(['store_id' => 1, 'name' => 'Processing', 'sort_order' => 2]);

        $order = Order::create([
            'store_id' => 1,
            'order_status_id' => $pending->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'subtotal' => 1000,
            'shipping_cost' => 100,
            'total' => 1100,
        ]);

        $this->post(route('admin.orders.history.store', $order), [
            'order_status_id' => $processing->id,
            'comment' => 'Moving to processing',
        ])->assertRedirect(route('admin.orders.show', $order));

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'order_status_id' => $processing->id]);
        $this->assertDatabaseHas('order_histories', [
            'order_id' => $order->id,
            'order_status_id' => $processing->id,
            'comment' => 'Moving to processing',
        ]);
    }

    public function test_products_pages_render_in_new_shell(): void
    {
        $this->actingAs(User::factory()->create());

        $brand = Brand::create(['store_id' => 1, 'name' => 'Boss', 'slug' => 'boss']);
        $category = Category::create(['store_id' => 1, 'name' => 'Pedals', 'slug' => 'pedals']);

        $product = Product::create([
            'store_id' => 1,
            'brand_id' => $brand->id,
            'name' => 'Distortion Pedal',
            'slug' => 'distortion-pedal',
            'price' => 1000,
        ]);
        $product->categories()->attach($category->id);

        $routes = [
            route('admin.products.index'),
            route('admin.products.create'),
            route('admin.products.edit', $product),
        ];

        foreach ($routes as $url) {
            $this->get($url)->assertOk()->assertSee('v-admin', false);
        }
    }

    public function test_product_update_round_trip_with_variant_payload(): void
    {
        $this->actingAs(User::factory()->create());

        $brand = Brand::create(['store_id' => 1, 'name' => 'Boss', 'slug' => 'boss']);

        $product = Product::create([
            'store_id' => 1,
            'brand_id' => $brand->id,
            'name' => 'Distortion Pedal',
            'slug' => 'distortion-pedal',
            'price' => 1000,
        ]);

        $payload = [
            'store_id' => 1,
            'name' => 'Distortion Pedal V2',
            'brand_id' => $brand->id,
            'price' => '1000.00',
            'is_active' => '1',
            'track_stock' => '1',
            'variant_types' => [
                ['name' => 'Color', 'values' => ['Red', 'Blue']],
            ],
            'variants' => [
                [
                    'sku' => 'DIST-RED',
                    'modifier_sign' => '+',
                    'modifier_amount' => '50.00',
                    'quantity' => 10,
                    'value_keys' => ['0_0'],
                    'is_active' => '1',
                ],
                [
                    'sku' => 'DIST-BLUE',
                    'modifier_sign' => '-',
                    'modifier_amount' => '25.00',
                    'quantity' => 5,
                    'value_keys' => ['0_1'],
                    'is_active' => '1',
                ],
            ],
        ];

        $this->put(route('admin.products.update', $product), $payload)
            ->assertRedirect(route('admin.products.edit', $product));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Distortion Pedal V2',
            'has_variants' => true,
            'quantity' => 15,
        ]);
        $this->assertDatabaseHas('variant_types', [
            'product_id' => $product->id,
            'name' => 'Color',
        ]);
        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'sku' => 'DIST-RED',
            'price' => 1050.00,
            'price_modifier' => 50.00,
            'quantity' => 10,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'sku' => 'DIST-BLUE',
            'price' => 975.00,
            'price_modifier' => -25.00,
            'quantity' => 5,
            'is_active' => true,
        ]);
    }

    public function test_carts_and_reviews_pages_render_in_new_shell(): void
    {
        $this->actingAs(User::factory()->create());
        app(\App\Services\ExtensionManager::class)->install('abandoned-carts');
        app(\App\Services\ExtensionManager::class)->install('reviews');
        app(\App\Services\ExtensionManager::class)->bootEnabled();

        $group = CustomerGroup::create(['store_id' => 1, 'name' => 'Retail']);

        $customer = Customer::create([
            'store_id' => 1,
            'customer_group_id' => $group->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'password' => 'secret',
            'is_active' => true,
        ]);

        $product = Product::create(['store_id' => 1, 'name' => 'Test Pedal', 'slug' => 'test-pedal', 'price' => 1000]);

        $cart = Cart::create(['store_id' => 1, 'customer_id' => $customer->id]);
        $cart->timestamps = false;
        $cart->update(['updated_at' => now()->subDays(2)]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'name' => $product->name,
            'price' => 1000,
        ]);

        $template = EmailTemplate::create([
            'store_id' => 1,
            'slug' => 'abandoned_cart_reminder',
            'name' => 'Reminder',
            'subject' => 'Come back!',
            'body' => 'Hi {customer_name}',
            'type' => 'customer',
            'is_active' => true,
        ]);

        $rule = AbandonedCartRule::create([
            'store_id' => 1,
            'email_template_id' => $template->id,
            'customer_group_id' => $group->id,
            'max_sends' => 3,
            'min_cart_age_hours' => 1,
            'priority' => 1,
            'is_active' => true,
        ]);

        $notification = AbandonedCartNotification::create([
            'cart_id' => $cart->id,
            'customer_id' => $customer->id,
            'abandoned_cart_rule_id' => $rule->id,
            'email_template_id' => $template->id,
            'send_count' => 1,
            'last_sent_at' => now()->subDay(),
        ]);

        // Order placed after the notification, for the same customer/product,
        // so recovered() finds a match and renders the non-empty branch.
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Pending', 'sort_order' => 1, 'is_default' => true]);
        $order = Order::create([
            'store_id' => 1,
            'customer_id' => $customer->id,
            'order_status_id' => $status->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'subtotal' => 1000,
            'shipping_cost' => 0,
            'total' => 1000,
        ]);
        OrderProduct::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'price' => 1000,
            'quantity' => 1,
            'total' => 1000,
        ]);

        $review = Review::create([
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'token' => Str::random(32),
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'rating' => 5,
            'title' => 'Great pedal',
            'body' => 'Loved it.',
            'status' => 'pending',
        ]);

        $routes = [
            route('admin.abandoned-carts.settings'),
            route('admin.abandoned-carts.index'),
            route('admin.abandoned-carts.show', $cart),
            route('admin.abandoned-carts.notifications'),
            route('admin.abandoned-carts.recovered'),
            route('admin.abandoned-carts.rules'),
            route('admin.abandoned-carts.rules.create'),
            route('admin.abandoned-carts.rules.edit', $rule),
            route('admin.abandoned-carts.templates'),
            route('admin.abandoned-carts.templates.create'),
            route('admin.abandoned-carts.templates.edit', $template),
            route('admin.reviews.index'),
            route('admin.reviews.settings'),
            route('admin.reviews.show', $review),
        ];

        foreach ($routes as $url) {
            $this->get($url)->assertOk()->assertSee('v-admin', false);
        }
    }

    public function test_abandoned_cart_settings_round_trip_updates_settings_table(): void
    {
        $this->actingAs(User::factory()->create());
        app(\App\Services\ExtensionManager::class)->install('abandoned-carts');
        app(\App\Services\ExtensionManager::class)->bootEnabled();

        $this->post(route('admin.abandoned-carts.settings.update'), [
            'enabled' => '1',
            'skip_out_of_stock' => '0',
        ])->assertRedirect(route('admin.abandoned-carts.settings'));

        $this->assertDatabaseHas('settings', [
            'group' => 'abandoned_cart',
            'key' => 'enabled',
            'value' => '1',
        ]);
        $this->assertDatabaseHas('settings', [
            'group' => 'abandoned_cart',
            'key' => 'skip_out_of_stock',
            'value' => '0',
        ]);

        // Unchecked x-admin.toggle omits the checkbox but keeps the hidden 0.
        $this->post(route('admin.abandoned-carts.settings.update'), [
            'enabled' => '0',
        ])->assertRedirect(route('admin.abandoned-carts.settings'));

        $this->assertDatabaseHas('settings', [
            'group' => 'abandoned_cart',
            'key' => 'enabled',
            'value' => '0',
        ]);
    }

    public function test_catalog_pages_render_in_new_shell(): void
    {
        $this->actingAs(User::factory()->create());

        $parentCategory = Category::create(['store_id' => 1, 'name' => 'Pedals', 'slug' => 'pedals']);
        $childCategory = Category::create(['store_id' => 1, 'parent_id' => $parentCategory->id, 'name' => 'Distortion', 'slug' => 'distortion']);

        $brand = Brand::create(['store_id' => 1, 'name' => 'Boss', 'slug' => 'boss']);

        $tag = Tag::create(['name' => 'Vintage', 'slug' => 'vintage']);

        $routes = [
            route('admin.categories.index'),
            route('admin.categories.create'),
            route('admin.categories.edit', $parentCategory),
            route('admin.categories.edit', $childCategory),
            route('admin.brands.index'),
            route('admin.brands.create'),
            route('admin.brands.edit', $brand),
            route('admin.media.index'),
            route('admin.tags.index'),
            route('admin.tags.create'),
            route('admin.tags.edit', $tag),
        ];

        foreach ($routes as $url) {
            $this->get($url)->assertOk()->assertSee('v-admin', false);
        }
    }

    public function test_category_update_round_trip_reparents_and_updates_fields(): void
    {
        $this->actingAs(User::factory()->create());

        $parent = Category::create(['store_id' => 1, 'name' => 'Pedals', 'slug' => 'pedals']);
        $category = Category::create(['store_id' => 1, 'name' => 'Cables', 'slug' => 'cables', 'is_active' => true]);

        $this->put(route('admin.categories.update', $category), [
            'store_id' => 1,
            'parent_id' => $parent->id,
            'name' => 'Instrument Cables',
            'description' => 'Guitar and patch cables.',
            'sort_order' => 5,
            'is_active' => '1',
        ])->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'parent_id' => $parent->id,
            'name' => 'Instrument Cables',
            'sort_order' => 5,
            'is_active' => true,
        ]);

        // Unchecked x-admin.toggle omits the checkbox but keeps the hidden 0.
        $this->put(route('admin.categories.update', $category), [
            'store_id' => 1,
            'parent_id' => $parent->id,
            'name' => 'Instrument Cables',
            'sort_order' => 5,
            'is_active' => '0',
        ])->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'is_active' => false,
        ]);
    }

    public function test_customers_and_content_pages_render_in_new_shell(): void
    {
        app(\App\Services\ExtensionManager::class)->install('blog');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        $this->actingAs(User::factory()->create());

        $group = CustomerGroup::create(['store_id' => 1, 'name' => 'Retail', 'is_default' => true]);
        $country = Country::create(['iso2' => 'PH', 'iso3' => 'PHL', 'name' => 'Philippines', 'phone_code' => '63', 'is_active' => true]);

        $customer = Customer::create([
            'store_id' => 1,
            'customer_group_id' => $group->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $customer->addresses()->create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'address_1' => '123 Main St',
            'city' => 'Manila',
            'country_id' => $country->id,
            'is_default' => true,
        ]);
        $customer->activities()->create([
            'type' => 'order_placed',
            'description' => 'Placed order #1001',
        ]);

        $blogCategory = BlogCategory::create(['name' => 'News', 'slug' => 'news', 'sort_order' => 0]);
        $tag = Tag::create(['name' => 'Vintage', 'slug' => 'vintage']);

        $post = BlogPost::create([
            'title' => 'Hello World',
            'slug' => 'hello-world',
            'content' => 'Body content.',
            'blog_category_id' => $blogCategory->id,
            'is_published' => true,
            'published_at' => now(),
        ]);
        $post->tags()->attach($tag->id);

        $page = Page::create([
            'title' => 'About Us',
            'slug' => 'about-us',
            'body' => 'Our story.',
            'is_active' => true,
        ]);

        $routes = [
            route('admin.customers.index'),
            route('admin.customers.create'),
            route('admin.customers.edit', $customer),
            route('admin.customer-groups.index'),
            route('admin.customer-groups.create'),
            route('admin.customer-groups.edit', $group),
            route('admin.pages.index'),
            route('admin.pages.create'),
            route('admin.pages.edit', $page),
            route('admin.blog.index'),
            route('admin.blog.create'),
            route('admin.blog.edit', $post),
            route('admin.blog-categories.index'),
            route('admin.blog-categories.create'),
            route('admin.blog-categories.edit', $blogCategory),
        ];

        foreach ($routes as $url) {
            $this->get($url)->assertOk()->assertSee('v-admin', false);
        }
    }

    public function test_customer_update_round_trip_syncs_addresses_and_toggles(): void
    {
        $this->actingAs(User::factory()->create());

        $group = CustomerGroup::create(['store_id' => 1, 'name' => 'Retail', 'is_default' => true]);
        $country = Country::create(['iso2' => 'PH', 'iso3' => 'PHL', 'name' => 'Philippines', 'phone_code' => '63', 'is_active' => true]);

        $customer = Customer::create([
            'store_id' => 1,
            'customer_group_id' => $group->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'password' => 'secret123',
            'is_active' => true,
            'newsletter' => false,
        ]);
        $existingAddress = $customer->addresses()->create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'address_1' => '123 Main St',
            'city' => 'Manila',
            'country_id' => $country->id,
            'is_default' => true,
        ]);

        $this->put(route('admin.customers.update', $customer), [
            'store_id' => 1,
            'customer_group_id' => $group->id,
            'first_name' => 'Janet',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'phone' => '9171234567',
            'is_active' => '1',
            'newsletter' => '1',
            'addresses' => [
                [
                    'id' => $existingAddress->id,
                    'first_name' => 'Janet',
                    'last_name' => 'Doe',
                    'address_1' => '456 New St',
                    'city' => 'Quezon City',
                    'country_id' => $country->id,
                    'is_default' => '1',
                ],
                [
                    'id' => '',
                    'first_name' => 'Janet',
                    'last_name' => 'Doe',
                    'address_1' => '789 Second Address',
                    'city' => 'Makati',
                    'country_id' => $country->id,
                    'is_default' => '0',
                ],
            ],
        ])->assertRedirect(route('admin.customers.index'));

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'first_name' => 'Janet',
            'phone' => '9171234567',
            'is_active' => true,
            'newsletter' => true,
        ]);

        $this->assertDatabaseHas('customer_addresses', [
            'id' => $existingAddress->id,
            'address_1' => '456 New St',
            'city' => 'Quezon City',
            'is_default' => true,
        ]);
        $this->assertDatabaseHas('customer_addresses', [
            'customer_id' => $customer->id,
            'address_1' => '789 Second Address',
            'city' => 'Makati',
            'is_default' => false,
        ]);
        $this->assertEquals(2, $customer->addresses()->count());

        // Unchecked x-admin.toggle omits the checkbox but keeps the hidden 0.
        $this->put(route('admin.customers.update', $customer), [
            'store_id' => 1,
            'customer_group_id' => $group->id,
            'first_name' => 'Janet',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'is_active' => '0',
            'newsletter' => '0',
        ])->assertRedirect(route('admin.customers.index'));

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'is_active' => false,
            'newsletter' => false,
        ]);

        // Addresses omitted entirely from the payload are all deleted (syncAddresses
        // drops anything not present in the submitted array).
        $this->assertEquals(0, $customer->addresses()->count());
    }

    public function test_marketing_and_child_forms_render_in_new_shell(): void
    {
        $this->actingAs(User::factory()->create());
        $manager = app(\App\Services\ExtensionManager::class);
        foreach (['shipping-flat-rate', 'shipping-weight-based', 'shipping-free', 'payment-bank-transfer', 'payment-hitpay', 'payment-cod', 'coupons'] as $name) {
            $manager->install($name);
        }
        $manager->bootEnabled();

        $group = CustomerGroup::create(['store_id' => 1, 'name' => 'Retail', 'is_default' => true]);
        $geoZone = GeoZone::create(['name' => 'Metro Manila', 'is_active' => true]);
        $brand = Brand::create(['store_id' => 1, 'name' => 'Boss', 'slug' => 'boss']);
        $category = Category::create(['store_id' => 1, 'name' => 'Pedals', 'slug' => 'pedals']);

        $coupon = Coupon::create([
            'code' => 'SUMMER10',
            'name' => 'Summer 10% Off',
            'type' => 'percent',
            'discount_value' => 10,
            'customer_eligibility' => 'all',
            'is_active' => true,
        ]);

        $flatRate = FlatShippingRate::create([
            'name' => 'Standard Flat Rate',
            'cost' => 150,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $weightMethod = WeightBasedMethod::create([
            'name' => 'Standard Shipping',
            'admin_name' => 'Standard Shipping',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $weightMethod->rates()->create(['weight_from' => 0, 'weight_to' => 5, 'cost' => 100]);

        $freeShippingRule = FreeShippingRule::create([
            'name' => 'Free Shipping over 5000',
            'min_order_total' => 5000,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $bankAccount = BankAccount::create([
            'bank_name' => 'BDO',
            'account_name' => 'Venta Store',
            'account_number' => '1234567890',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $routes = [
            route('admin.coupons.index'),
            route('admin.coupons.index', ['search' => 'SUMMER', 'type' => 'percent', 'status' => 'active']),
            route('admin.coupons.create'),
            route('admin.coupons.edit', $coupon),
        ];

        // Bank account editor migrated to the v settings shell.
        $this->get(route('admin.bank-accounts.edit', $bankAccount))
            ->assertOk()->assertSee('v-embed', false);

        // The shipping child forms migrated to the v settings shell.
        $vRoutes = [
            route('admin.flat-rates.create'),
            route('admin.flat-rates.edit', $flatRate),
            route('admin.weight-based-methods.create'),
            route('admin.weight-based-methods.edit', $weightMethod),
            route('admin.free-shipping-rules.index'),
            route('admin.free-shipping-rules.create'),
            route('admin.free-shipping-rules.edit', $freeShippingRule),
        ];

        foreach ($vRoutes as $url) {
            $this->get($url)->assertOk()->assertSee('v-embed', false);
        }

        foreach ($routes as $url) {
            $this->get($url)->assertOk()->assertSee('v-admin', false);
        }
    }

    public function test_coupon_update_round_trip_syncs_restrictions_and_toggles(): void
    {
        $this->actingAs(User::factory()->create());
        $manager = app(\App\Services\ExtensionManager::class);
        $manager->install('coupons');
        $manager->bootEnabled();

        $group = CustomerGroup::create(['store_id' => 1, 'name' => 'Retail', 'is_default' => true]);
        $product = Product::create(['store_id' => 1, 'name' => 'Distortion Pedal', 'slug' => 'distortion-pedal', 'price' => 1000]);

        $coupon = Coupon::create([
            'code' => 'SUMMER10',
            'name' => 'Summer 10% Off',
            'type' => 'percent',
            'discount_value' => 10,
            'customer_eligibility' => 'all',
            'is_active' => true,
            'free_shipping' => false,
        ]);

        $this->put(route('admin.coupons.update', $coupon), [
            'code' => 'summer20',
            'name' => 'Summer 20% Off',
            'type' => 'percent',
            'discount_value' => '20',
            'min_order_total' => '1000',
            'max_discount' => '500',
            'customer_eligibility' => 'logged_in',
            'customer_group_id' => $group->id,
            'usage_limit' => '100',
            'usage_per_customer' => '1',
            'date_start' => '2026-01-01',
            'date_end' => '2026-12-31',
            'is_active' => '1',
            'free_shipping' => '1',
            'restriction_products' => json_encode([$product->id]),
        ])->assertRedirect(route('admin.coupons.index'));

        // Code is uppercased by the controller regardless of casing submitted.
        $this->assertDatabaseHas('coupons', [
            'id' => $coupon->id,
            'code' => 'SUMMER20',
            'name' => 'Summer 20% Off',
            'discount_value' => 20,
            'customer_eligibility' => 'logged_in',
            'customer_group_id' => $group->id,
            'is_active' => true,
            'free_shipping' => true,
        ]);
        $this->assertDatabaseHas('coupon_restrictions', [
            'coupon_id' => $coupon->id,
            'restriction_type' => 'product',
            'restriction_id' => $product->id,
        ]);

        // Unchecked x-admin.toggle omits the checkbox but keeps the hidden 0.
        $this->put(route('admin.coupons.update', $coupon), [
            'code' => 'summer20',
            'name' => 'Summer 20% Off',
            'type' => 'percent',
            'discount_value' => '20',
            'customer_eligibility' => 'all',
            'is_active' => '0',
            'free_shipping' => '0',
        ])->assertRedirect(route('admin.coupons.index'));

        $this->assertDatabaseHas('coupons', [
            'id' => $coupon->id,
            'is_active' => false,
            'free_shipping' => false,
        ]);

        // Restrictions omitted from the payload are all cleared (syncRestrictions
        // deletes existing rows before re-creating from the submitted fields).
        $this->assertEquals(0, $coupon->restrictions()->count());
    }

    public function test_spokes_wave1_pages_render_in_new_shell(): void
    {
        $role = Role::create(['name' => 'Super Admin', 'slug' => 'super-admin']);
        $this->actingAs(User::factory()->create(['role_id' => $role->id]));

        $country = Country::create(['iso2' => 'PH', 'iso3' => 'PHL', 'name' => 'Philippines', 'phone_code' => '63', 'is_active' => true]);
        $zone = Zone::create(['country_id' => $country->id, 'name' => 'Metro Manila', 'code' => 'MM', 'is_active' => true]);
        $geoZone = GeoZone::create(['name' => 'Domestic', 'description' => 'PH-wide', 'is_active' => true]);
        $geoZone->entries()->create(['country_id' => $country->id, 'zone_id' => $zone->id]);

        $editableUser = User::factory()->create(['role_id' => $role->id]);

        Permission::create(['name' => 'View Orders', 'slug' => 'orders.view', 'group' => 'orders']);

        $template = EmailTemplate::create([
            'store_id' => 1,
            'slug' => 'welcome',
            'name' => 'Welcome Email',
            'subject' => 'Welcome!',
            'body' => 'Hi {customer_name}',
            'type' => 'customer',
            'is_active' => true,
        ]);

        // Geo zone edit migrated to the v shell (first v exemplar);
        // it renders under the v marker, asserted separately below.
        $this->get(route('admin.localization.edit-geo-zone', $geoZone))
            ->assertOk()->assertSee('v-embed', false);

        // Migrated to the v settings shell.
        $vRoutes = [
            route('admin.localization.index'),
            route('admin.localization.index', ['tab' => 'zones', 'country' => $country->id]),
            route('admin.localization.index', ['tab' => 'geo-zones']),
            route('admin.users.index'),
            route('admin.users.create'),
            route('admin.users.edit', $editableUser),
            route('admin.user-groups.index'),
            route('admin.user-groups.create'),
            route('admin.email-templates.index'),
            route('admin.email-templates.edit', $template),
        ];

        foreach ($vRoutes as $url) {
            $this->get($url)->assertOk()->assertSee('v-embed', false);
        }
    }

    /**
     * The sidebar's work-waiting badges are composed onto the chrome
     * component (the one that renders the nav). Regression pin: the
     * v3->v rename split shell/chrome and the composer kept pointing at
     * the shell, so every badge silently vanished (found by the owner
     * on 2026-08-19).
     */
    public function test_nav_badges_render_on_the_chrome_sidebar(): void
    {
        app(\App\Services\ExtensionManager::class)->install('reviews');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        $this->actingAs(User::factory()->create());

        $product = Product::create(['store_id' => 1, 'name' => 'Badge Pedal', 'slug' => 'badge-pedal', 'price' => 1000]);
        Review::create([
            'product_id' => $product->id,
            'token' => Str::random(32),
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'rating' => 5,
            'title' => 'Great pedal',
            'body' => 'Loved it.',
            'status' => 'pending',
        ]);
        \Illuminate\Support\Facades\Cache::forget('admin.nav-badges');

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('v-nav__badge', false);
    }

    public function test_user_group_crud_round_trip_owns_its_permission_config(): void
    {
        $this->actingAs(User::factory()->create());

        $viewOrders = Permission::create(['name' => 'View Orders', 'slug' => 'orders.view', 'group' => 'orders']);
        $editOrders = Permission::create(['name' => 'Edit Orders', 'slug' => 'orders.edit', 'group' => 'orders']);
        $viewProducts = Permission::create(['name' => 'View Products', 'slug' => 'products.view', 'group' => 'products']);

        // Create: the group arrives with its own permission set and a
        // generated slug.
        $this->post(route('admin.user-groups.store'), [
            'name' => 'Support',
            'description' => 'Order desk',
            'permissions' => [$viewOrders->id, $viewProducts->id],
        ])->assertRedirect(route('admin.user-groups.index'));

        $support = Role::where('slug', 'support')->firstOrFail();
        $this->assertEquals(2, $support->permissions()->count());

        // Update replaces the full set: the unsubmitted product grant drops.
        $this->put(route('admin.user-groups.update', $support), [
            'name' => 'Support',
            'permissions' => [$viewOrders->id, $editOrders->id],
        ])->assertRedirect(route('admin.user-groups.index'));

        $this->assertDatabaseHas('role_permission', ['role_id' => $support->id, 'permission_id' => $editOrders->id]);
        $this->assertDatabaseMissing('role_permission', ['role_id' => $support->id, 'permission_id' => $viewProducts->id]);

        // A rename keeps the slug anchor stable.
        $this->put(route('admin.user-groups.update', $support), [
            'name' => 'Order Desk',
            'permissions' => [$viewOrders->id],
        ]);
        $this->assertEquals('support', $support->fresh()->slug);

        // Super Admin can never be stripped: a save with no permissions
        // still holds the full set afterward.
        $superAdmin = Role::create(['name' => 'Super Admin', 'slug' => 'super-admin']);
        $this->put(route('admin.user-groups.update', $superAdmin), [
            'name' => 'Super Admin',
            'permissions' => [],
        ])->assertRedirect(route('admin.user-groups.index'));
        $this->assertEquals(Permission::count(), $superAdmin->permissions()->count());

        // Delete guards: never Super Admin, never a group that still has
        // users; an empty group goes.
        $this->delete(route('admin.user-groups.destroy', $superAdmin))->assertRedirect();
        $this->assertDatabaseHas('roles', ['id' => $superAdmin->id]);

        $staffed = Role::create(['name' => 'Staffed', 'slug' => 'staffed']);
        User::factory()->create(['role_id' => $staffed->id]);
        $this->delete(route('admin.user-groups.destroy', $staffed))->assertRedirect();
        $this->assertDatabaseHas('roles', ['id' => $staffed->id]);

        // Duplicate: a copy carries the permission set, gets a fresh
        // slug, and lands on its own edit page with no users.
        $this->post(route('admin.user-groups.duplicate', $staffed))->assertRedirect();
        $copy = Role::where('name', 'Staffed (copy)')->firstOrFail();
        $this->assertNotEquals($staffed->slug, $copy->slug);
        $this->assertEquals($staffed->permissions()->count(), $copy->permissions()->count());
        $this->assertEquals(0, $copy->users()->count());

        $this->delete(route('admin.user-groups.destroy', $support))->assertRedirect(route('admin.user-groups.index'));
        $this->assertDatabaseMissing('roles', ['id' => $support->id]);

        // The users directory narrows to one group via ?group=.
        $inGroup = User::where('role_id', $staffed->id)->first();
        $this->get(route('admin.users.index', ['group' => $staffed->id]))
            ->assertOk()
            ->assertSee($inGroup->name)
            ->assertSee('In the Staffed group');
    }

    public function test_spokes_wave2_pages_render_in_new_shell(): void
    {
        app(\App\Services\ExtensionManager::class)->install('stock-alerts');
        app(\App\Services\ExtensionManager::class)->install('theme');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        $this->actingAs(User::factory()->create());

        $category = Category::create(['store_id' => 1, 'name' => 'Pedals', 'slug' => 'pedals']);

        HomepageSection::create(['type' => 'banner', 'title' => 'Hero', 'config' => [], 'sort_order' => 1, 'is_enabled' => true]);
        NavMenuItem::create(['label' => 'Pedals', 'type' => 'category', 'category_id' => $category->id, 'sort_order' => 1, 'is_enabled' => true]);
        FooterColumn::create(['title' => 'Links', 'type' => 'links', 'config' => ['links' => []], 'sort_order' => 1, 'is_enabled' => true]);

        $courier = ShippingCourier::create(['store_id' => 1, 'name' => 'GoGo Express', 'tracking_url' => 'https://track.example.com/{tracking_number}', 'is_active' => true, 'sort_order' => 1]);

        ApiSyncLog::create(['method' => 'POST', 'endpoint' => '/api/products', 'sku' => 'DIST-1', 'status_code' => 200, 'response_time_ms' => 120, 'ip_address' => '127.0.0.1']);

        $this->get(route('admin.api-sync-logs.index'))
            ->assertOk()->assertSee('v-admin', false);

        // Theme, Couriers, and Stock Alerts migrated to the v settings shell.
        $vRoutes = [
            route('admin.theme.index'),
            route('admin.shipping-couriers.index'),
            route('admin.stock-notifications.index'),
            route('admin.stock-notifications.index', ['tab' => 'template']),
        ];

        foreach ($vRoutes as $url) {
            $this->get($url)->assertOk()->assertSee('v-embed', false);
        }

        // Banners is an optional extension — only assert its routes when the
        // BannerServiceProvider has actually registered them (Route::has guard,
        // matching the pattern ModuleController already uses for this module).
        if (Route::has('admin.banners.index')) {
            $group = BannerGroup::create([
                'name' => 'Homepage Hero',
                'slug' => 'homepage-hero',
                'autoplay' => true,
                'speed' => 5000,
                'height' => 500,
                'height_mobile' => 400,
                'full_width' => true,
                'is_active' => true,
            ]);

            $banner = Banner::create([
                'banner_group_id' => $group->id,
                'name' => 'Summer Sale',
                'bg_color' => '#000000',
                'bg_size' => 'cover',
                'layers' => [],
                'sort_order' => 1,
                'is_active' => true,
            ]);

            $bannerRoutes = [
                route('admin.banners.index'),
                route('admin.banners.create'),
                route('admin.banners.edit', $group),
                route('admin.banners.slides', $group),
                route('admin.banners.slides.create', $group),
                route('admin.banners.slides.edit', [$group, $banner]),
            ];

            foreach ($bannerRoutes as $url) {
                $this->get($url)->assertOk()->assertSee('v-admin', false);
            }
        }
    }

    public function test_shipping_courier_update_round_trip_edits_courier(): void
    {
        $this->actingAs(User::factory()->create());

        $courier = ShippingCourier::create([
            'store_id' => 1,
            'name' => 'GoGo Express',
            'tracking_url' => 'https://track.example.com/{tracking_number}',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->put(route('admin.shipping-couriers.update', $courier), [
            'name' => 'GoGo Express Plus',
            'tracking_url' => 'https://track.example.com/v2/{tracking_number}',
            'sort_order' => 2,
            'is_active' => '1',
        ])->assertRedirect(route('admin.shipping-couriers.index'));

        $this->assertDatabaseHas('shipping_couriers', [
            'id' => $courier->id,
            'name' => 'GoGo Express Plus',
            'tracking_url' => 'https://track.example.com/v2/{tracking_number}',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        // Unchecked x-admin.checkbox omits the field entirely; the controller's
        // $request->boolean('is_active') still resolves the missing key to false.
        $this->put(route('admin.shipping-couriers.update', $courier), [
            'name' => 'GoGo Express Plus',
            'tracking_url' => 'https://track.example.com/v2/{tracking_number}',
            'sort_order' => 2,
        ])->assertRedirect(route('admin.shipping-couriers.index'));

        $this->assertDatabaseHas('shipping_couriers', [
            'id' => $courier->id,
            'is_active' => false,
        ]);
    }

    public function test_dashboard_renders_in_new_shell(): void
    {
        app(\App\Services\ExtensionManager::class)->install('seo');
        app(\App\Services\ExtensionManager::class)->install('reviews');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        $this->actingAs(User::factory()->create());

        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Pending', 'sort_order' => 1, 'is_default' => true]);

        Order::create([
            'store_id' => 1,
            'order_status_id' => $status->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'subtotal' => 1000,
            'shipping_cost' => 100,
            'total' => 1100,
        ]);

        $product = Product::create(['store_id' => 1, 'name' => 'Distortion Pedal', 'slug' => 'distortion-pedal', 'price' => 1000]);

        // Pending review + SEO suggestion + a stale cart exercise the three
        // linked counts under the day sheet's Outstanding movement.
        Review::create([
            'product_id' => $product->id,
            'token' => Str::random(32),
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'rating' => 5,
            'title' => 'Great pedal',
            'body' => 'Loved it.',
            'status' => 'pending',
        ]);

        SeoSuggestion::create([
            'product_id' => $product->id,
            'entity_type' => Product::class,
            'entity_id' => $product->id,
            'status' => 'pending',
            'generated_at' => now(),
        ]);

        $group = CustomerGroup::create(['store_id' => 1, 'name' => 'Retail']);
        $customer = Customer::create([
            'store_id' => 1,
            'customer_group_id' => $group->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane2@example.com',
            'password' => 'secret',
            'is_active' => true,
        ]);

        $cart = Cart::create(['store_id' => 1, 'customer_id' => $customer->id]);
        $cart->timestamps = false;
        $cart->update(['updated_at' => now()->subDays(2)]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'name' => $product->name,
            'price' => 1000,
        ]);

        // Renders through CustomerActivity::color, now returning v-* tokens
        // directly (re-centralized from the local map in customers/form).
        CustomerActivity::create([
            'customer_id' => $customer->id,
            'type' => 'order_placed',
            'description' => 'Placed an order',
            'created_at' => now(),
        ]);

        // Enabled, daily-at-16:00 automation last run 10 days ago is always
        // overdue regardless of when the test runs — exercises the danger
        // branch of the automation health strip.
        Automation::create([
            'key' => 'currency_update',
            'name' => 'Currency Exchange Rates',
            'command' => 'currency:update',
            'schedule_preset' => 'daily',
            'schedule_params' => ['hour' => 16, 'minute' => 0],
            'cron_expression' => '0 16 * * *',
            'enabled' => true,
            'last_run_at' => now()->subDays(10),
            'last_run_status' => 'success',
        ]);

        // The dashboard is the day sheet now (see DaySheetTest for what it
        // promises): the figures are named for the ledger they belong to,
        // and automation health is a rail panel that names each stalled
        // automation with an Overdue or Failed chip.
        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('v-admin', false)
            ->assertSee('Revenue taken')
            ->assertSee('Overdue')
            ->assertSee('Currency Exchange Rates');
    }

    public function test_old_admin_layout_is_gone(): void
    {
        $this->assertFileDoesNotExist(base_path('resources/views/layouts/admin.blade.php'));
        $this->assertFileDoesNotExist(base_path('app/View/Components/AdminLayout.php'));
    }

    public function test_admin_login_page_renders_in_new_shell(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('v-admin', false)
            ->assertSee('Sign in');
    }

    public function test_profile_page_renders_in_new_shell(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.profile.edit'))
            ->assertOk()
            ->assertSee('v-admin', false)
            ->assertSee('My Profile')
            ->assertSee('Delete Account');
    }

    public function test_settings_hub_destinations_render_persistent_sub_sidebar(): void
    {
        $this->actingAs(User::factory()->create());

        // Users index is a representative "external" Settings-hub destination
        // (not a registry section) — it must now carry the same persistent
        // sub-sidebar + "All Settings" back link as the 7 registry sections.
        // Users migrated to the v settings shell: the persistent settings
        // nav is now the v context nav, which still lists sibling sections.
        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('v-embed', false)
            ->assertSee('Payments'); // context nav lists sibling sections

        // Bank Transfer ships NOT installed (owner ruling 2026-08-19), so a
        // test that asserts on its row installs it first, the same way the
        // coupons and fulfillment tests do.
        app(\App\Services\ExtensionManager::class)->install('payment-bank-transfer');
        app(\App\Services\ExtensionManager::class)->bootEnabled();

        // Standard rows (owner ruling 2026-08-18): the landing carries only
        // Enable/Disable + Configure; availability (restrictions) lives in
        // the method page's own Availability tab, reached through Configure.
        $this->get(route('admin.payment-settings.index'))
            ->assertOk()
            ->assertSee('v-embed', false)
            ->assertSee(route('admin.payment-settings.show', 'bank_transfer'), false);

        $this->get(route('admin.payment-settings.show', 'bank_transfer'))
            ->assertOk()
            ->assertSee('Availability');
    }

    public function test_automations_section_renders_without_raw_palette_classes(): void
    {
        $this->actingAs(User::factory()->create());

        Automation::create([
            'key' => 'currency_update',
            'name' => 'Currency Exchange Rates',
            'command' => 'currency:update',
            'schedule_preset' => 'daily',
            'schedule_params' => ['hour' => 16, 'minute' => 0],
            'cron_expression' => '0 16 * * *',
            'enabled' => true,
            'last_run_at' => now()->subDay(),
            'last_run_status' => 'success',
        ]);

        $this->get(route('admin.settings.section', 'automations'))
            ->assertOk()
            ->assertSee('v-embed', false)
            ->assertSee('Currency Exchange Rates')
            ->assertDontSee('bg-gray-50', false)
            ->assertDontSee('bg-indigo-600', false)
            ->assertDontSee('text-gray-900', false);
    }

    /**
     * Owner report: "When I go to their settings, there are menus that bring
     * me to the Main Nav." Settings-context pages (extensions/seo/...
     * and extensions/abandoned-carts/...) used to @include the
     * Marketing/SEO and Sales workspace navs wholesale, so every link out of
     * a Settings page re-entered a different workspace's main nav. Pin that
     * these pages no longer carry those workspace-nav markers, while the
     * single, explicit crossing links (in both directions) still render.
     */
    public function test_one_home_pages_share_one_side_pan(): void
    {
        // One home, side-pan menus (owner ruling 2026-08-19 evening,
        // superseding the tab bars of the same morning): ops and config
        // pages share the module's own context nav, the same v-snav the
        // Settings and SEO spaces wear.
        app(\App\Services\ExtensionManager::class)->install('abandoned-carts');
        app(\App\Services\ExtensionManager::class)->install('reviews');
        app(\App\Services\ExtensionManager::class)->install('seo');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        $this->actingAs(User::factory()->create());

        foreach ([
            route('admin.abandoned-carts.index'),
            route('admin.abandoned-carts.settings'),
            route('admin.abandoned-carts.rules'),
        ] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('aria-label="Abandoned Carts"', false)
                ->assertSee('v-snav', false)
                ->assertSee('Templates')
                ->assertSee('Settings');
        }

        foreach ([route('admin.reviews.index'), route('admin.reviews.settings')] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('aria-label="Reviews"', false)
                ->assertSee('v-snav', false)
                ->assertSee('Settings');
        }

        // The SEO space keeps its own space nav, engine settings included.
        $this->get(route('admin.extensions.seo.settings'))
            ->assertOk()
            ->assertSee('aria-label="SEO"', false);
    }

    /**
     * Owner report, round one: "Make global length of contents 1400. There
     * are some that are squeezed. Such as Automations." Automations' schedule
     * table was rendering inside settings-shell's narrow (672px) mode.
     *
     * Owner report, round two: "/general width is not uniform with other
     * pages, it is narrower. Same on other settings pages." The `wide` prop
     * that fixed automations was opt-in, and seven of the thirty-four
     * settings screens never opted in, so a person moving between them
     * watched the work column jump 229px at 1440. The prop is gone: settings
     * is one workspace with one column width, and the measure cap stays on
     * x-admin.workspace for callers that genuinely want it.
     *
     * So this pins the whole set, in both directions: every settings screen
     * renders the work column, and none of them caps it.
     */
    public function test_every_settings_section_renders_one_column_width(): void
    {
        $this->actingAs(User::factory()->create());

        // general/mail/checkout/security migrated to the v settings shell;
        // they render its marker instead of the v2 workspace column.
        foreach (['general', 'mail', 'checkout', 'security', 'currencies'] as $section) {
            $this->get(route('admin.settings.section', $section))
                ->assertOk()
                ->assertSee('v-settings__main', false);
        }

        // Automations migrated to the v settings shell.
        $this->get(route('admin.settings.section', 'automations'))
            ->assertOk()
            ->assertSee('v-settings__main', false);

        // The two settings screens outside the section controller, one a
        // table and one a column of fields: same column either way.
        $this->get(route('admin.user-groups.index'))
            ->assertOk()
            ->assertDontSee('v-workspace__main--narrow', false);

        $this->get(route('admin.users.create'))
            ->assertOk()
            ->assertDontSee('v-workspace__main--narrow', false);
    }

    /**
     * Fulfillment queue (extensions/courier-fulfillment) — seeds one order per
     * filter phase (to_pack: no tracking number; shipping: tracking number,
     * not delivered; delivered: courier_status = delivered) and pins that the
     * filter param scopes the result set and survives into the rendered
     * filter-pill/search markup, matching FulfillmentController::index().
     *
     * The extension is installed in-test (real extension.json, not a fixture)
     * since its routes and courier_* order columns only exist once installed
     * + enabled — same pattern as UrlGrammarTest's fulfillment coverage.
     */
    public function test_fulfillment_pages_render_in_new_shell(): void
    {
        $this->actingAs(User::factory()->create());
        app(\App\Services\ExtensionManager::class)->install('courier-fulfillment');
        // Registers the extension's runtime PSR-4 autoloader up front, so this
        // test can reference Extensions\CourierFulfillment\* (the
        // module-owned placement mapping) before its first HTTP request.
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        // Task 4: 'fulfillment_enabled' is opt-out (missing/NULL means active),
        // so no setting write is needed here — see FulfillmentController::index().

        // fulfillment-placement Task 2: which tab an order appears under is
        // decided by the module's placement mapping, so this test's
        // three orders now sit on three placed statuses. Its subject (the
        // pages render in the new shell, and each filter round-trips its
        // param into the search form) is unchanged.
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Processing', 'sort_order' => 1, 'is_default' => true]);
        $shippedStatus = OrderStatus::create(['store_id' => 1, 'name' => 'Shipped', 'sort_order' => 2]);
        $deliveredStatus = OrderStatus::create(['store_id' => 1, 'name' => 'Delivered', 'sort_order' => 3]);
        // Placement is module data with NO default (owner ruling,
        // 2026-08-09), so every status this test relies on is placed here.
        \Extensions\CourierFulfillment\PlacementMap::put($status->id, 'to_pack');
        \Extensions\CourierFulfillment\PlacementMap::put($shippedStatus->id, 'shipping');
        \Extensions\CourierFulfillment\PlacementMap::put($deliveredStatus->id, 'delivered');

        $makeOrder = function (string $suffix, ?OrderStatus $on = null) use ($status) {
            return Order::create([
                'store_id' => 1,
                'order_status_id' => ($on ?? $status)->id,
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'shipping_first_name' => 'Jane',
                'shipping_last_name' => 'Doe',
                'email' => "jane.{$suffix}@example.com",
                'subtotal' => 1000,
                'shipping_cost' => 100,
                'total' => 1100,
            ]);
        };

        $toPack = $makeOrder('topack');

        $inTransit = $makeOrder('intransit', $shippedStatus);
        $inTransit->forceFill([
            'courier_tracking_number' => 'TRACK-IN-TRANSIT',
            'courier_provider' => 'quadx',
            'courier_status' => 'picked_up',
        ])->save();

        $delivered = $makeOrder('delivered', $deliveredStatus);
        $delivered->forceFill([
            'courier_tracking_number' => 'TRACK-DELIVERED',
            'courier_provider' => 'quadx',
            'courier_status' => 'delivered',
        ])->save();

        // route()/route helpers can't resolve these names yet — the extension's
        // routes are only registered once BootExtensions middleware re-checks
        // on the next dispatched request (see install()'s $booted reset), so
        // raw prefix-based paths are used instead, same as UrlGrammarTest.
        $base = '/' . config('admin.prefix', 'admin') . '/extensions/courier-fulfillment';

        // Default filter (to_pack) renders the shell and shows only the unbooked order.
        $this->get($base)
            ->assertOk()
            ->assertSee('v-admin', false)
            ->assertSee('#' . $toPack->id)
            ->assertDontSee('#' . $inTransit->id)
            ->assertDontSee('#' . $delivered->id);

        // shipping filter: only the booked-not-delivered order, param preserved
        // in the hidden search-form input the view renders.
        $this->get($base . '?filter=shipping')
            ->assertOk()
            ->assertSee('#' . $inTransit->id)
            ->assertDontSee('#' . $toPack->id)
            ->assertDontSee('#' . $delivered->id)
            ->assertSee('name="filter" value="shipping"', false);

        // delivered filter: only the delivered order.
        $this->get($base . '?filter=delivered')
            ->assertOk()
            ->assertSee('#' . $delivered->id)
            ->assertDontSee('#' . $toPack->id)
            ->assertDontSee('#' . $inTransit->id)
            ->assertSee('name="filter" value="delivered"', false);

        // all filter: every order, q param preserved alongside filter.
        $this->get($base . '?filter=all&q=jane')
            ->assertOk()
            ->assertSee('#' . $toPack->id)
            ->assertSee('#' . $inTransit->id)
            ->assertSee('#' . $delivered->id)
            ->assertSee('name="filter" value="all"', false);

        // Courier settings route (mounted separately under Settings > Modules)
        // renders in the v settings space since the savebar sweep.
        $this->get('/' . config('admin.prefix', 'admin') . '/extensions/courier-fulfillment')
            ->assertOk()
            ->assertSee('v-admin', false);
    }
}
