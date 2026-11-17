<?php

namespace Tests\Feature\AdminV2;

use App\Models\Cart;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\ExtensionManager;
use Extensions\ShippingFlatRate\Models\FlatShippingRate;
use Extensions\ShippingWeightBased\Models\WeightBasedMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Amounts on the abandoned carts and shipping rate pages are in the store's
 * own currency, so a store that sells in dollars must not see a peso sign.
 */
class ExtensionAmountsCurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Store::create(['id' => 1, 'name' => 'Demo Store', 'code' => 'demo']);
        Currency::query()->delete();
        Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'rate' => 1, 'is_default' => true, 'is_enabled' => true]);

        $manager = app(ExtensionManager::class);
        foreach (['abandoned-carts', 'shipping-flat-rate', 'shipping-weight-based'] as $name) {
            $manager->install($name);
        }
        $manager->bootEnabled();

        $this->actingAs(User::factory()->create());
    }

    public function test_abandoned_carts_show_their_value_in_the_store_currency(): void
    {
        $product = Product::create(['store_id' => 1, 'name' => 'Demo Lamp', 'slug' => 'demo-lamp', 'price' => 1250]);
        $cart = Cart::create(['store_id' => 1, 'session_id' => 'demo-session']);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 2, 'name' => 'Demo Lamp', 'price' => 1250, 'sku' => 'DEMO-001']);
        Cart::whereKey($cart->id)->update(['updated_at' => now()->subHours(3)]);

        $this->get(route('admin.abandoned-carts.index'))
            ->assertOk()->assertSee('$2,500.00')->assertDontSee('₱');

        $this->get(route('admin.abandoned-carts.show', $cart))
            ->assertOk()->assertSee('$2,500.00')->assertDontSee('₱');
    }

    public function test_flat_rate_pages_show_costs_in_the_store_currency(): void
    {
        FlatShippingRate::create(['name' => 'Standard', 'cost' => 150, 'is_active' => true, 'sort_order' => 0]);

        $this->get(route('admin.shipping-settings.flat-rate'))
            ->assertOk()->assertSee('$150.00')->assertDontSee('₱');

        $this->get(route('admin.flat-rates.create'))
            ->assertOk()->assertSee('Cost ($)')->assertDontSee('₱');
    }

    public function test_weight_based_form_labels_the_cost_in_the_store_currency(): void
    {
        $method = WeightBasedMethod::create(['name' => 'Domestic', 'admin_name' => 'Domestic', 'is_active' => true, 'sort_order' => 0]);
        $method->rates()->create(['weight_from' => 0, 'weight_to' => 5, 'cost' => 100]);

        $this->get(route('admin.weight-based-methods.edit', $method))
            ->assertOk()->assertSee('aria-label="Cost ($)"', false)->assertDontSee('₱');

        $this->get(route('admin.weight-based-methods.create'))
            ->assertOk()->assertSee('aria-label="Cost ($)"', false);
    }
}
