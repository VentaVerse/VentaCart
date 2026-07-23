<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Missing"/ghost orders (NULL order_status_id = abandoned checkout) must never
 * be visible on the customer-facing storefront.
 */
class CustomerOrderVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(Customer $customer, ?int $statusId): Order
    {
        return Order::create([
            'store_id' => $customer->store_id,
            'customer_id' => $customer->id,
            'order_status_id' => $statusId,
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'email' => $customer->email,
            'total' => 100,
        ]);
    }

    private function setUpCustomerWithOrders(): array
    {
        $store = Store::create(['name' => 'Test Store', 'code' => 'test']);

        $customer = Customer::create([
            'store_id' => $store->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'password' => bcrypt('secret'),
        ]);

        $status = OrderStatus::create([
            'store_id' => $store->id,
            'name' => 'Pending',
            'is_default' => true,
            'sort_order' => 1,
        ]);

        $realOrder = $this->makeOrder($customer, $status->id);
        $ghostOrder = $this->makeOrder($customer, null);

        return compact('customer', 'realOrder', 'ghostOrder');
    }

    public function test_order_history_hides_ghost_orders(): void
    {
        ['customer' => $customer, 'realOrder' => $realOrder, 'ghostOrder' => $ghostOrder]
            = $this->setUpCustomerWithOrders();

        $response = $this->actingAs($customer, 'customer')->get(route('account.orders'));

        $response->assertOk();
        $response->assertSee(route('account.orders.show', $realOrder), false);
        $response->assertDontSee(route('account.orders.show', $ghostOrder), false);
    }

    public function test_dashboard_hides_ghost_orders(): void
    {
        ['customer' => $customer, 'realOrder' => $realOrder, 'ghostOrder' => $ghostOrder]
            = $this->setUpCustomerWithOrders();

        $response = $this->actingAs($customer, 'customer')->get(route('account.dashboard'));

        $response->assertOk();
        $response->assertSee(route('account.orders.show', $realOrder), false);
        $response->assertDontSee(route('account.orders.show', $ghostOrder), false);
    }

    public function test_ghost_order_detail_returns_404(): void
    {
        ['customer' => $customer, 'realOrder' => $realOrder, 'ghostOrder' => $ghostOrder]
            = $this->setUpCustomerWithOrders();

        $this->actingAs($customer, 'customer')
            ->get(route('account.orders.show', $realOrder))
            ->assertOk();

        $this->actingAs($customer, 'customer')
            ->get(route('account.orders.show', $ghostOrder))
            ->assertNotFound();
    }
}
