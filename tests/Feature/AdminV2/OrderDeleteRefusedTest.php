<?php

namespace Tests\Feature\AdminV2;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An extension may refuse an order's delete from a `deleting` listener, by
 * returning false or by throwing. The admin is then told the order was kept,
 * never that it was deleted. A DomainException's message is written for the
 * merchant and is shown as it is; anything else is logged and a plain
 * message shown instead.
 */
class OrderDeleteRefusedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
    }

    public function test_an_order_with_no_objection_is_deleted(): void
    {
        $order = $this->order();

        $this->actingAs(User::factory()->create())
            ->delete(route('admin.orders.destroy', $order))
            ->assertRedirect(route('admin.orders.index'))
            ->assertSessionHas('success', 'Order deleted successfully.');

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    public function test_a_listener_returning_false_keeps_the_order_and_says_so(): void
    {
        $order = $this->order();
        Order::deleting(fn () => false);

        $this->actingAs(User::factory()->create())
            ->from(route('admin.orders.show', $order))
            ->delete(route('admin.orders.destroy', $order))
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHas('error', 'This order was not deleted.')
            ->assertSessionMissing('success');

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_a_domain_exception_shows_its_message_and_keeps_the_order(): void
    {
        $order = $this->order();
        Order::deleting(function () {
            throw new \DomainException('This order still has a payment held on it. Refund or settle it first.');
        });

        $this->actingAs(User::factory()->create())
            ->from(route('admin.orders.index'))
            ->delete(route('admin.orders.destroy', $order))
            ->assertRedirect(route('admin.orders.index'))
            ->assertSessionHas('error', 'This order still has a payment held on it. Refund or settle it first.')
            ->assertSessionMissing('success');

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_an_unexpected_failure_is_logged_and_a_plain_message_shown(): void
    {
        $order = $this->order();
        Order::deleting(function () {
            throw new \RuntimeException('SQLSTATE[HY000]: internal detail');
        });

        $this->actingAs(User::factory()->create())
            ->from(route('admin.orders.index'))
            ->delete(route('admin.orders.destroy', $order))
            ->assertRedirect(route('admin.orders.index'))
            ->assertSessionHas('error', 'This order was not deleted.')
            ->assertSessionMissing('success');

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    private function order(): Order
    {
        return Order::create([
            'store_id' => 1, 'first_name' => 'Maria', 'last_name' => 'Santos', 'email' => 'maria@example.test',
            'payment_method' => 'cod', 'shipping_method' => 'Standard', 'subtotal' => 500, 'shipping_cost' => 0, 'total' => 500,
        ]);
    }
}
