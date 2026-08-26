<?php

namespace Tests\Feature\AdminV2;

use App\Models\Order;
use App\Models\User;
use App\Services\ExtensionManager;
use App\Services\PaymentManager;
use App\Services\PaymentMethods\PaymentMethodInterface;
use App\Services\PaymentMethods\PaymentResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstalledTestPayment implements PaymentMethodInterface
{
    public function getKey(): string { return 'installed_method'; }
    public function getBaseKey(): string { return 'installed_method'; }
    public function getName(): string { return 'Installed Method'; }
    public function getDescription(): string { return ''; }
    public function getImage(): ?string { return null; }
    public function isEnabled(): bool { return true; }
    public function processPayment(Order $order): PaymentResult { return new PaymentResult(true); }
}

/**
 * The COD classification list on the fulfillment hub is built from the payment
 * methods Venta has INSTALLED, never from the distinct values in the orders
 * table.
 *
 * It used to be the union of the two. That resurrected every slug format the
 * store had ever retired — NGD carried four `bank_transfer_<account_id>` slugs
 * from a scheme the bank transfer extension no longer writes — and offered them
 * as checkboxes indistinguishable from live methods. The "legacy" badge meant
 * to mark them only fired after twelve months of disuse, so the recently
 * retired ones, the only ones anybody would mistake for real, were exactly the
 * ones it stayed silent about.
 */
class FulfillmentCodPaymentMethodsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The registry is process-global static state.
        PaymentManager::reset();
        PaymentManager::register(InstalledTestPayment::class);
    }

    protected function tearDown(): void
    {
        PaymentManager::reset();
        parent::tearDown();
    }

    private function installExtension(): void
    {
        $manager = app(ExtensionManager::class);
        $manager->install('courier-fulfillment');
        $manager->bootEnabled();
    }

    private function settingsUrl(): string
    {
        return '/' . config('admin.prefix', 'admin') . '/extensions/courier-fulfillment/settings';
    }

    private function orderPaidWith(string $slug): Order
    {
        return Order::create([
            'store_id'             => 1,
            'first_name'           => 'Jane',
            'last_name'            => 'Doe',
            'email'                => 'jane@example.com',
            'phone'                => '09171234567',
            'shipping_first_name'  => 'Jane',
            'shipping_last_name'   => 'Doe',
            'shipping_address_1'   => '123 Mango St',
            'shipping_barangay'    => 'Lahug',
            'shipping_city'        => 'Cebu City',
            'shipping_state'       => 'Cebu',
            'shipping_postcode'    => '6000',
            'shipping_phone'       => '09171234567',
            'payment_method'       => $slug,
            'subtotal'             => 1000,
            'shipping_cost'        => 100,
            'total'                => 1100,
        ]);
    }

    public function test_retired_slugs_in_order_history_are_not_offered(): void
    {
        // Exactly the shape NGD's orders table carries.
        $this->orderPaidWith('bank_transfer_7');

        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->get($this->settingsUrl())
            ->assertOk()
            ->assertDontSee('value="bank_transfer_7"', false);
    }

    public function test_marketplace_orders_do_not_leak_into_the_list(): void
    {
        $this->orderPaidWith('shopee');
        $this->orderPaidWith('lazada');

        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->get($this->settingsUrl())
            ->assertOk()
            ->assertDontSee('value="shopee"', false)
            ->assertDontSee('value="lazada"', false);
    }

    public function test_installed_methods_are_offered_even_with_no_orders(): void
    {
        // No orders at all: the list must still be complete, which a
        // history-derived list could never manage on a fresh store.
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->get($this->settingsUrl())
            ->assertOk()
            ->assertSee('value="installed_method"', false);
    }
}
