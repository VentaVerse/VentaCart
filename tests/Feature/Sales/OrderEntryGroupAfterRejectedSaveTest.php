<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A rejected save must come back with the customer's group.
 *
 * The group is not a form field: the page learns it when a customer is picked.
 * After a failed validation Laravel redirects back with the old input only, so
 * a page that does not look the group up again asks for shipping and payment
 * methods as a guest would, and a Reseller's restrictions silently vanish.
 */
class OrderEntryGroupAfterRejectedSaveTest extends TestCase
{
    use RefreshDatabase;

    private function reseller(string $email = 'reseller@example.com'): Customer
    {
        $group = CustomerGroup::create(['store_id' => 1, 'name' => 'Resellers']);

        return Customer::create([
            'store_id' => 1, 'first_name' => 'Rhea', 'last_name' => 'Cruz',
            'email' => $email, 'password' => bcrypt('x'),
            'customer_group_id' => $group->id,
        ]);
    }

    public function test_the_create_page_keeps_the_picked_customers_group_after_a_rejected_save(): void
    {
        $customer = $this->reseller();

        $html = $this->actingAs(User::factory()->create())
            ->withSession(['_old_input' => ['customer_id' => (string) $customer->id]])
            ->get(route('admin.orders.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('customerGroupId: '.$customer->customer_group_id.',', $html);
        $this->assertStringContainsString("customerGroupName: 'Resellers'", $html);
    }

    public function test_a_fresh_create_page_has_no_group(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get(route('admin.orders.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('customerGroupId: null,', $html);
    }

    public function test_the_edit_page_follows_the_customer_picked_before_the_rejected_save(): void
    {
        $reseller = $this->reseller();
        $other = Customer::create([
            'store_id' => 1, 'first_name' => 'Jo', 'last_name' => 'Santos',
            'email' => 'jo@example.com', 'password' => bcrypt('x'),
        ]);
        $order = Order::create([
            'store_id' => 1, 'customer_id' => $other->id,
            'first_name' => 'Jo', 'last_name' => 'Santos', 'email' => 'jo@example.com',
            'subtotal' => 0, 'total' => 0,
        ]);

        $html = $this->actingAs(User::factory()->create())
            ->withSession(['_old_input' => ['customer_id' => (string) $reseller->id]])
            ->get(route('admin.orders.edit', $order))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('customerGroupId: '.$reseller->customer_group_id.',', $html);
        $this->assertStringContainsString("customerGroupName: 'Resellers'", $html);
    }
}
