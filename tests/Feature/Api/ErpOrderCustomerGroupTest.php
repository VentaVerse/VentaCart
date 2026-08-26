<?php

namespace Tests\Feature\Api;

use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * Which customer group an order was placed under, when the ERP fetches it.
 *
 * Informational only: the ERP does not set it and nothing links a group
 * across the two systems. It answers "was this a reseller order" without the
 * ERP having to know what a Venta customer group is.
 */
class ErpOrderCustomerGroupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        app(\App\Services\ExtensionManager::class)->install('erp-sync');
        app(\App\Services\ExtensionManager::class)->bootEnabled();

        Setting::create([
            'group' => 'api',
            'key' => 'erp_token',
            'value' => Crypt::encryptString('erp-test-token'),
        ]);
    }

    private function group(string $name, bool $default = false): CustomerGroup
    {
        return CustomerGroup::create(['store_id' => 1, 'name' => $name, 'is_default' => $default]);
    }

    private function customer(CustomerGroup $group): \App\Models\Customer
    {
        return \App\Models\Customer::create([
            'store_id' => 1, 'first_name' => 'Ana', 'last_name' => 'Cruz',
            'email' => 'ana' . uniqid() . '@example.test', 'password' => bcrypt('x'),
            'customer_group_id' => $group->id,
        ]);
    }

    /**
     * order_status_name and customer_group_name are snapshot columns and are
     * deliberately not fillable: the model derives both from their foreign
     * keys on save. Setting the keys is the only honest way to build one, and
     * it means these tests exercise the real capture path.
     */
    private function order(array $attributes = []): Order
    {
        $status = \App\Models\OrderStatus::firstOrCreate(
            ['name' => 'Pending'],
            ['store_id' => 1, 'sort_order' => 0]
        );

        return Order::create(array_merge([
            'store_id' => 1,
            'first_name' => 'Ana', 'last_name' => 'Cruz',
            'email' => 'ana@example.test',
            'order_status_id' => $status->id,
            'total' => 1000, 'subtotal' => 1000,
        ], $attributes));
    }

    private function fetch(string $url)
    {
        return $this->withToken('erp-test-token')->getJson($url);
    }

    public function test_fetching_one_order_says_which_group_it_was_placed_under(): void
    {
        $order = $this->order(['customer_id' => $this->customer($this->group('Resellers'))->id]);

        $this->fetch('/api/v1/orders/' . $order->id)
            ->assertOk()
            ->assertJsonPath('customer_group', 'Resellers');
    }

    public function test_the_list_carries_it_too(): void
    {
        $this->order(['customer_id' => $this->customer($this->group('Resellers'))->id]);

        $this->fetch('/api/v1/orders')
            ->assertOk()
            ->assertJsonPath('data.0.customer_group', 'Resellers');
    }

    /**
     * 24,237 migrated orders never captured it, and not one of them has a
     * customer attached, so there is nothing to fall back to. Null is the
     * honest answer; a guess would read as fact.
     */
    public function test_a_guest_order_answers_null_rather_than_guessing(): void
    {
        $order = $this->order();

        $this->assertNull($order->fresh()->customer_group_name);

        $this->fetch('/api/v1/orders/' . $order->id)
            ->assertOk()
            ->assertJsonPath('customer_group', null);
    }

    /**
     * The point of a snapshot. Moving a customer into another group must not
     * rewrite what their past orders were bought under.
     */
    public function test_moving_a_customer_between_groups_does_not_rewrite_old_orders(): void
    {
        $default = $this->group('Default', true);
        $resellers = $this->group('Resellers');
        $customer = $this->customer($default);

        $order = $this->order(['customer_id' => $customer->id]);
        $this->assertSame('Default', $order->fresh()->customer_group_name);

        $customer->update(['customer_group_id' => $resellers->id]);

        $this->fetch('/api/v1/orders/' . $order->id)
            ->assertOk()
            ->assertJsonPath('customer_group', 'Default');
    }

    public function test_it_is_read_only_and_the_erp_cannot_set_it(): void
    {
        $order = $this->order(['customer_id' => $this->customer($this->group('Default', true))->id]);

        $this->withToken('erp-test-token')
            ->putJson('/api/v1/orders/' . $order->id . '/status', [
                'status' => 'Processing',
                'customer_group_name' => 'Resellers',
                'customer_group' => 'Resellers',
            ]);

        // Nothing the ERP sends touches it: the group is Venta's, and the
        // brief was informational only, with no linking in either direction.
        $this->assertSame('Default', $order->fresh()->customer_group_name);
    }
}
