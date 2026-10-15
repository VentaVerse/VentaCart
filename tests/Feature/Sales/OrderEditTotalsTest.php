<?php

namespace Tests\Feature\Sales;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderStatus;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Saving an order in the admin recomputes its total, and the recompute
 * has to keep every deduction the order was placed with, not just the
 * coupon. Store credit is spent from the customer's balance at checkout
 * and never handed back automatically, so an edit that forgot it billed
 * the customer again for money they had already paid with credit.
 */
class OrderEditTotalsTest extends TestCase
{
    use RefreshDatabase;

    private OrderStatus $status;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        $this->status = OrderStatus::create(['store_id' => 1, 'name' => 'Pending', 'sort_order' => 1]);
    }

    /** A checkout order: ₱1,000 of goods, ₱100 shipping, ₱300 paid in store credit. */
    private function orderPaidPartlyInCredit(array $overrides = []): Order
    {
        $order = Order::create(array_merge([
            'store_id' => 1, 'order_status_id' => $this->status->id,
            'first_name' => 'June', 'last_name' => 'Sillacos', 'email' => 'june@example.com',
            'subtotal' => 1000, 'shipping_cost' => 100, 'total' => 800,
            'total_lines' => [['code' => 'store_credit', 'label' => 'Store Credit', 'amount' => -300, 'extras' => ['customer_id' => 9]]],
        ], $overrides));
        OrderProduct::create([
            'order_id' => $order->id, 'name' => 'Valeton GP-5', 'price' => 1000, 'quantity' => 1, 'total' => 1000,
        ]);

        return $order->fresh();
    }

    private function save(Order $order, float $price, array $extra = []): void
    {
        $this->actingAs(User::factory()->create())
            ->put(route('admin.orders.update', $order), array_merge([
                'store_id' => 1,
                'order_status_id' => $this->status->id,
                'first_name' => 'June',
                'last_name' => 'Sillacos',
                'email' => 'june@example.com',
                'items' => [['name' => 'Valeton GP-5', 'price' => $price, 'quantity' => 1]],
            ], $extra))
            ->assertSessionHasNoErrors();
    }

    public function test_saving_without_changes_keeps_the_store_credit_deducted(): void
    {
        $order = $this->orderPaidPartlyInCredit();

        $this->save($order, 1000);

        $this->assertSame('800.00', $order->fresh()->total);
    }

    public function test_repricing_a_line_still_deducts_the_store_credit(): void
    {
        $order = $this->orderPaidPartlyInCredit();

        $this->save($order, 600);

        // 600 + 100 shipping - 300 credit
        $this->assertSame('400.00', $order->fresh()->total);
    }

    public function test_coupon_and_store_credit_both_stay_deducted(): void
    {
        $order = $this->orderPaidPartlyInCredit(['coupon_code' => 'SAVE10', 'coupon_discount' => 100, 'total' => 700]);

        $this->save($order, 1000, ['coupon_code' => 'SAVE10', 'coupon_discount' => 100]);

        // 1000 + 100 - 100 coupon - 300 credit
        $this->assertSame('700.00', $order->fresh()->total);
    }
}
