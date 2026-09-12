<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The ERP reads the total as a breakdown, not one figure (owner request,
 * 2026-09-12): every line between the subtotal and the total, in the
 * order the admin order page shows them, so subtotal + shipping + lines
 * adds up to the total the ERP already reads.
 */
class ErpOrderTotalsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        app(\App\Services\ExtensionManager::class)->install('erp-sync');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        $this->apiKey('erp-test-token');
    }

    private function order(array $overrides = []): Order
    {
        $status = OrderStatus::firstOrCreate(['store_id' => 1, 'name' => 'Processing'], ['sort_order' => 2]);

        return Order::create(array_merge([
            'store_id' => 1, 'order_status_id' => $status->id,
            'first_name' => 'June', 'last_name' => 'Sillacos', 'email' => 'june@example.com', 'phone' => '1',
            'shipping_first_name' => 'June', 'shipping_last_name' => 'Sillacos', 'shipping_address_1' => 'x',
            'shipping_city' => 'Cebu City', 'shipping_postcode' => '6000',
            'subtotal' => 1000, 'shipping_cost' => 100, 'total' => 1100,
        ], $overrides));
    }

    private function totals(Order $order): array
    {
        return $this->withToken('erp-test-token')->getJson('/api/v1/orders/' . $order->id)
            ->assertOk()
            ->json('totals');
    }

    public function test_each_line_of_the_total_reaches_the_erp(): void
    {
        $order = $this->order([
            'coupon_code' => 'SAVE10', 'coupon_discount' => 100, 'total' => 700,
            'total_lines' => [
                ['code' => 'coupon', 'label' => 'Coupon (SAVE10)', 'amount' => -100, 'extras' => ['code' => 'SAVE10']],
                ['code' => 'store_credit', 'label' => 'Store Credit', 'amount' => -300, 'extras' => ['customer_id' => 9]],
            ],
        ]);

        $totals = $this->totals($order);

        $this->assertSame([
            ['code' => 'coupon', 'label' => 'Coupon (SAVE10)', 'amount' => '-100.00'],
            ['code' => 'store_credit', 'label' => 'Store Credit', 'amount' => '-300.00'],
        ], $totals['lines']);

        // The point of the breakdown: it adds up to the total.
        $sum = (float) $totals['subtotal'] + (float) $totals['shipping'] + array_sum(array_map('floatval', array_column($totals['lines'], 'amount')));
        $this->assertEqualsWithDelta((float) $totals['total'], $sum, 0.001);
    }

    public function test_a_coupon_keyed_in_by_the_admin_is_a_line_too(): void
    {
        // Admin-entered orders carry the coupon on its columns only.
        $order = $this->order(['coupon_code' => 'STAFF', 'coupon_discount' => 50, 'total' => 1050]);

        $this->assertSame(
            [['code' => 'coupon', 'label' => 'Coupon (STAFF)', 'amount' => '-50.00']],
            $this->totals($order)['lines'],
        );
    }

    public function test_the_coupon_columns_win_over_a_stale_coupon_line(): void
    {
        // The admin changed the discount after checkout: the column is the
        // figure the total was recomputed from, the saved line is not.
        $order = $this->order([
            'coupon_code' => 'SAVE10', 'coupon_discount' => 40, 'total' => 1060,
            'total_lines' => [['code' => 'coupon', 'label' => 'Coupon (SAVE10)', 'amount' => -100, 'extras' => []]],
        ]);

        $this->assertSame(
            [['code' => 'coupon', 'label' => 'Coupon (SAVE10)', 'amount' => '-40.00']],
            $this->totals($order)['lines'],
        );
    }

    public function test_an_order_with_nothing_between_subtotal_and_total_has_no_lines(): void
    {
        $order = $this->order([
            'total_lines' => [['code' => 'store_credit', 'label' => 'Store Credit', 'amount' => 0, 'extras' => []]],
        ]);

        $this->assertSame([], $this->totals($order)['lines']);
    }
}
