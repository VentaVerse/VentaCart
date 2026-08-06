<?php

namespace Tests\Feature\AdminV2;

use App\Models\AbandonedCartNotification;
use App\Models\AbandonedCartRule;
use App\Models\Brand;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\EmailTemplate;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\Review;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            route('admin.reviews.index', ['tab' => 'settings']),
            route('admin.reviews.show', $review),
        ];

        foreach ($routes as $url) {
            $this->get($url)->assertOk()->assertSee('v-admin', false);
        }
    }

    public function test_abandoned_cart_settings_round_trip_updates_settings_table(): void
    {
        $this->actingAs(User::factory()->create());

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
}
