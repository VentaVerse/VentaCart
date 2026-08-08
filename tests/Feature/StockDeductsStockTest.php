<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The stock model after the simplification (owner ruling, 2026-08-08): a
 * status either DEDUCTS stock or it does not, and returning stock is the
 * automatic reversal of a deduction rather than something a status is tagged
 * with.
 *
 * The whole rule is a two-cell decision between orders.stock_adjusted (where
 * the stock actually is) and order_statuses.deducts_stock (where the new
 * status says it should be), so these tests walk that matrix in both
 * directions plus both no-op corners, and then pin the two edges the old
 * model got right and this one must not regress: an order created directly at
 * a deducting status, and repeated saves at the same status.
 */
class StockDeductsStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Store::create(['id' => 1, 'name' => 'Test Store', 'code' => 'test']);
    }

    private function makeStatus(string $name, bool $deducts): OrderStatus
    {
        return OrderStatus::create([
            'store_id' => 1,
            'name' => $name,
            'deducts_stock' => $deducts,
            'sort_order' => 1,
        ]);
    }

    private function product(int $quantity = 10, bool $trackStock = true): Product
    {
        return Product::create([
            'store_id' => 1,
            'name' => 'Tube Screamer ' . uniqid(),
            'slug' => 'ts-' . uniqid(),
            'price' => 1000,
            'quantity' => $quantity,
            'track_stock' => $trackStock,
            'is_active' => true,
        ]);
    }

    private function orderFor(OrderStatus $status, Product $product, int $quantity = 2, bool $stockAdjusted = false): Order
    {
        $order = Order::create([
            'store_id' => 1,
            'order_status_id' => $status->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane' . uniqid() . '@example.com',
            'shipping_first_name' => 'Jane',
            'shipping_last_name' => 'Doe',
            'shipping_address_1' => '123 Mango St',
            'shipping_city' => 'Cebu City',
            'shipping_state' => 'Cebu',
            'payment_method' => 'bank_transfer',
            'subtotal' => 1000,
            'shipping_cost' => 100,
            'total' => 1100,
            'stock_adjusted' => $stockAdjusted,
        ]);

        OrderProduct::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'price' => 500,
            'quantity' => $quantity,
            'total' => 500 * $quantity,
        ]);

        return $order;
    }

    // ---------------------------------------------------------------------
    // The from/to matrix
    // ---------------------------------------------------------------------

    public function test_not_deducted_moving_into_a_deducting_status_takes_the_stock_out(): void
    {
        $product = $this->product(10);
        $order = $this->orderFor($this->makeStatus('Pending', false), $product, 2, false);

        StockService::adjustForStatusChange($order, $this->makeStatus('Processing', true));

        $this->assertSame(8, (int) $product->fresh()->quantity);
        $this->assertTrue((bool) $order->fresh()->stock_adjusted);
    }

    public function test_deducted_moving_into_a_non_deducting_status_puts_the_stock_back(): void
    {
        $product = $this->product(8);
        $order = $this->orderFor($this->makeStatus('Processing', true), $product, 2, true);

        StockService::adjustForStatusChange($order, $this->makeStatus('Canceled', false));

        $this->assertSame(10, (int) $product->fresh()->quantity);
        $this->assertFalse((bool) $order->fresh()->stock_adjusted);
    }

    public function test_deducted_moving_into_another_deducting_status_is_a_no_op(): void
    {
        $product = $this->product(8);
        $order = $this->orderFor($this->makeStatus('Processing', true), $product, 2, true);

        StockService::adjustForStatusChange($order, $this->makeStatus('Shipped', true));

        $this->assertSame(8, (int) $product->fresh()->quantity);
        $this->assertTrue((bool) $order->fresh()->stock_adjusted);
    }

    public function test_not_deducted_moving_into_another_non_deducting_status_is_a_no_op(): void
    {
        $product = $this->product(10);
        $order = $this->orderFor($this->makeStatus('Canceled', false), $product, 2, false);

        StockService::adjustForStatusChange($order, $this->makeStatus('Refunded', false));

        $this->assertSame(10, (int) $product->fresh()->quantity);
        $this->assertFalse((bool) $order->fresh()->stock_adjusted);
    }

    /** Out and back and out again: the reversal is symmetric, not one-shot. */
    public function test_the_reversal_round_trips(): void
    {
        $product = $this->product(10);
        $deducting = $this->makeStatus('Processing', true);
        $nonDeducting = $this->makeStatus('Canceled', false);
        $order = $this->orderFor($deducting, $product, 3, false);

        StockService::adjustForStatusChange($order, $deducting);
        $this->assertSame(7, (int) $product->fresh()->quantity);

        StockService::adjustForStatusChange($order, $nonDeducting);
        $this->assertSame(10, (int) $product->fresh()->quantity);

        StockService::adjustForStatusChange($order, $deducting);
        $this->assertSame(7, (int) $product->fresh()->quantity);
        $this->assertTrue((bool) $order->fresh()->stock_adjusted);
    }

    // ---------------------------------------------------------------------
    // The edges the old model got right and this one must not regress
    // ---------------------------------------------------------------------

    public function test_an_order_created_directly_at_a_deducting_status_deducts_exactly_once(): void
    {
        $product = $this->product(10);
        $processing = $this->makeStatus('Processing', true);

        // No prior status at all - the order is born here.
        $order = $this->orderFor($processing, $product, 2, false);

        StockService::adjustForStatusChange($order, $processing);

        $this->assertSame(8, (int) $product->fresh()->quantity);
        $this->assertTrue((bool) $order->fresh()->stock_adjusted);
    }

    public function test_repeated_saves_at_the_same_status_never_double_adjust(): void
    {
        $product = $this->product(10);
        $processing = $this->makeStatus('Processing', true);
        $order = $this->orderFor($processing, $product, 2, false);

        for ($i = 0; $i < 5; $i++) {
            StockService::adjustForStatusChange($order, $processing);
        }

        $this->assertSame(8, (int) $product->fresh()->quantity);

        $canceled = $this->makeStatus('Canceled', false);
        for ($i = 0; $i < 5; $i++) {
            StockService::adjustForStatusChange($order, $canceled);
        }

        $this->assertSame(10, (int) $product->fresh()->quantity, 'Repeated non-deducting saves restocked more than once.');
    }

    /**
     * The nine statuses that used to be tagged 'increment' (Canceled, Denied,
     * Expired, Failed, Refunded, Reversed, Voided, Chargeback, Returned) are
     * now simply non-deducting. This pins that the observable behaviour - a
     * Processing order moving to any of them gives the stock back and clears
     * the flag - is EXACTLY what it was before the change.
     */
    public function test_the_former_increment_statuses_still_restock(): void
    {
        foreach ([
            'Canceled', 'Denied', 'Expired', 'Failed', 'Refunded',
            'Reversed', 'Voided', 'Chargeback', 'Returned',
        ] as $name) {
            // Stock is already out (the state a Processing order is in): the
            // shelf shows 8 of a 10-unit stock holding.
            $product = $this->product(8);
            $order = $this->orderFor($this->makeStatus('Processing ' . $name, true), $product, 2, true);

            StockService::adjustForStatusChange($order, $this->makeStatus($name, false));

            $this->assertSame(10, (int) $product->fresh()->quantity, "{$name} did not return the stock.");
            $this->assertFalse((bool) $order->fresh()->stock_adjusted, "{$name} did not clear stock_adjusted.");
        }
    }

    /**
     * The seven statuses that used to be untagged and are now deducting: a
     * parcel that is in transit, returning, lost or damaged has genuinely left
     * the shelf, so arriving at one of them must NOT put stock back.
     */
    public function test_the_in_transit_statuses_hold_the_stock_out(): void
    {
        foreach ([
            'Arrived at NGD', 'In Transit to Manila', 'Delivery Failed',
            'Pickup Failed', 'Returning', 'Lost', 'Damaged',
        ] as $name) {
            $product = $this->product(8);
            $order = $this->orderFor($this->makeStatus('Shipped ' . $name, true), $product, 2, true);

            StockService::adjustForStatusChange($order, $this->makeStatus($name, true));

            $this->assertSame(8, (int) $product->fresh()->quantity, "{$name} re-shelved stock while a parcel was out.");
            $this->assertTrue((bool) $order->fresh()->stock_adjusted);
        }
    }

    // ---------------------------------------------------------------------
    // Line-item rules carried over unchanged
    // ---------------------------------------------------------------------

    public function test_untracked_products_are_never_adjusted(): void
    {
        $product = $this->product(10, trackStock: false);
        $order = $this->orderFor($this->makeStatus('Pending', false), $product, 2, false);

        StockService::adjustForStatusChange($order, $this->makeStatus('Processing', true));

        $this->assertSame(10, (int) $product->fresh()->quantity);
        // The order is still marked adjusted: the flag describes the order, not
        // any one line, and untracked lines simply have nothing to move.
        $this->assertTrue((bool) $order->fresh()->stock_adjusted);
    }

    public function test_variant_quantities_move_with_the_order(): void
    {
        $product = $this->product(10);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'V-' . uniqid(),
            'price' => 1500,
            'quantity' => 6,
            'is_active' => true,
        ]);

        $order = $this->orderFor($this->makeStatus('Pending', false), $product, 2, false);
        OrderProduct::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'name' => $product->name,
            'price' => 1500,
            'quantity' => 1,
            'total' => 1500,
        ]);

        $deducting = $this->makeStatus('Processing', true);
        StockService::adjustForStatusChange($order, $deducting);

        $this->assertSame(5, (int) $variant->fresh()->quantity);

        StockService::adjustForStatusChange($order, $this->makeStatus('Canceled', false));

        $this->assertSame(6, (int) $variant->fresh()->quantity);
    }
}
