<?php

namespace Tests\Feature\Sales;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Editing an order's lines has to move stock.
 *
 * orders.stock_adjusted is one boolean meaning "this order's CURRENT lines are
 * out of inventory", while the increment/decrement walk the order's lines as
 * they stand at that moment. Change the lines while the flag is up and the flag
 * describes a list that no longer exists: the eventual return walks the new
 * list, so it hands back units never taken and never hands back the ones still
 * out. Measured before the fix: swapping 2 of X for 3 of Y and cancelling left
 * X down 2 forever and Y up 3 from nothing.
 */
class OrderEditStockTest extends TestCase
{
    use RefreshDatabase;

    private OrderStatus $deducting;

    private OrderStatus $plain;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        $this->actingAs(User::factory()->create());

        $this->plain = OrderStatus::create(['store_id' => 1, 'name' => 'Pending', 'sort_order' => 1, 'deducts_stock' => false]);
        $this->deducting = OrderStatus::create(['store_id' => 1, 'name' => 'Processing', 'sort_order' => 2, 'deducts_stock' => true]);
    }

    private function product(string $name, int $qty, bool $track = true): Product
    {
        return Product::create([
            'store_id' => 1, 'name' => $name, 'slug' => \Illuminate\Support\Str::slug($name),
            'price' => 100, 'quantity' => $qty, 'track_stock' => $track,
        ]);
    }

    private function orderFor(Product $p, int $qty, ?ProductVariant $variant = null): Order
    {
        $order = Order::create([
            'store_id' => 1, 'first_name' => 'A', 'last_name' => 'Buyer',
            'email' => 'buyer@example.test', 'order_status_id' => $this->plain->id, 'total' => 0,
        ]);
        OrderProduct::create([
            'order_id' => $order->id, 'product_id' => $p->id,
            'product_variant_id' => $variant?->id,
            'name' => $p->name, 'quantity' => $qty, 'price' => 100, 'total' => 100 * $qty,
        ]);

        return $order->fresh();
    }

    /** Swap one product for another while the units are out. */
    public function test_swapping_a_product_returns_the_old_and_takes_the_new(): void
    {
        $x = $this->product('X', 5);
        $y = $this->product('Y', 5);
        $order = $this->orderFor($x, 2);

        StockService::adjustForStatusChange($order, $this->deducting);
        $this->assertSame(3, $x->fresh()->quantity, 'precondition: X is out by 2');

        $old = $order->fresh()->products;
        OrderProduct::where('order_id', $order->id)->delete();
        OrderProduct::create([
            'order_id' => $order->id, 'product_id' => $y->id,
            'name' => 'Y', 'quantity' => 3, 'price' => 100, 'total' => 300,
        ]);

        StockService::reconcileLines($order->fresh(), $old);

        $this->assertSame(5, $x->fresh()->quantity, 'X must come back: it is no longer on the order.');
        $this->assertSame(2, $y->fresh()->quantity, 'Y must go out: it is on the order now.');
    }

    /** The case the owner named: X sits at 0 and must come back to 1. */
    public function test_a_product_at_zero_comes_back_when_it_leaves_the_order(): void
    {
        $x = $this->product('X', 1);
        $y = $this->product('Y', 1);
        $order = $this->orderFor($x, 1);

        StockService::adjustForStatusChange($order, $this->deducting);
        $this->assertSame(0, $x->fresh()->quantity);

        $old = $order->fresh()->products;
        OrderProduct::where('order_id', $order->id)->delete();
        OrderProduct::create([
            'order_id' => $order->id, 'product_id' => $y->id,
            'name' => 'Y', 'quantity' => 1, 'price' => 100, 'total' => 100,
        ]);

        StockService::reconcileLines($order->fresh(), $old);

        $this->assertSame(1, $x->fresh()->quantity, 'X back to 1.');
        $this->assertSame(0, $y->fresh()->quantity, 'Y down to 0.');
    }

    public function test_changing_a_quantity_moves_only_the_difference(): void
    {
        $x = $this->product('X', 10);
        $order = $this->orderFor($x, 2);

        StockService::adjustForStatusChange($order, $this->deducting);
        $this->assertSame(8, $x->fresh()->quantity);

        $old = $order->fresh()->products;
        $order->products()->first()->update(['quantity' => 5]);

        StockService::reconcileLines($order->fresh(), $old);

        $this->assertSame(5, $x->fresh()->quantity, '2 back, 5 out.');
    }

    /** Nothing is out yet, so an edit must move nothing at all. */
    public function test_an_edit_moves_nothing_while_the_order_holds_no_stock(): void
    {
        $x = $this->product('X', 5);
        $y = $this->product('Y', 5);
        $order = $this->orderFor($x, 2);

        $old = $order->fresh()->products;
        OrderProduct::where('order_id', $order->id)->delete();
        OrderProduct::create([
            'order_id' => $order->id, 'product_id' => $y->id,
            'name' => 'Y', 'quantity' => 3, 'price' => 100, 'total' => 300,
        ]);

        StockService::reconcileLines($order->fresh(), $old);

        $this->assertSame(5, $x->fresh()->quantity);
        $this->assertSame(5, $y->fresh()->quantity);
    }

    /**
     * A variant line moves the variant AND its parent, because a parent's
     * quantity is maintained as the sum of its variants
     * (ProductController::syncQuantityFromVariants).
     */
    public function test_a_variant_swap_moves_the_variant_and_its_parent(): void
    {
        $p = $this->product('Shirt', 0);
        $v1 = ProductVariant::create(['product_id' => $p->id, 'sku' => 'S-1', 'price' => 100, 'quantity' => 5, 'cost' => 0]);
        $v2 = ProductVariant::create(['product_id' => $p->id, 'sku' => 'S-2', 'price' => 100, 'quantity' => 5, 'cost' => 0]);
        $p->update(['has_variants' => true, 'quantity' => 10]);

        $order = $this->orderFor($p, 2, $v1);
        StockService::adjustForStatusChange($order, $this->deducting);
        $this->assertSame(3, $v1->fresh()->quantity);
        $this->assertSame(8, $p->fresh()->quantity, 'parent tracks the sum');

        $old = $order->fresh()->products;
        OrderProduct::where('order_id', $order->id)->delete();
        OrderProduct::create([
            'order_id' => $order->id, 'product_id' => $p->id, 'product_variant_id' => $v2->id,
            'name' => 'Shirt', 'quantity' => 3, 'price' => 100, 'total' => 300,
        ]);

        StockService::reconcileLines($order->fresh(), $old);

        $this->assertSame(5, $v1->fresh()->quantity, 'V1 back.');
        $this->assertSame(2, $v2->fresh()->quantity, 'V2 out.');
        $this->assertSame(7, $p->fresh()->quantity, 'parent still equals the sum of its variants.');
        $this->assertSame(
            (int) $p->fresh()->variants()->sum('quantity'),
            (int) $p->fresh()->quantity,
            'parent quantity must never drift from the sum of its variants.'
        );
    }

    /**
     * The whole path, through the controller the admin actually uses.
     *
     * The tests above drive StockService directly, so they would all still
     * pass if update() never called it. This one fails if the wiring is wrong.
     */
    public function test_saving_the_order_form_reconciles_stock_and_records_it(): void
    {
        $x = $this->product('X', 5);
        $y = $this->product('Y', 5);
        $order = $this->orderFor($x, 2);

        StockService::adjustForStatusChange($order, $this->deducting);
        $order->update(['order_status_id' => $this->deducting->id]);
        $this->assertSame(3, $x->fresh()->quantity);

        $this->put(route('admin.orders.update', $order), [
            'store_id' => 1,
            'order_status_id' => $this->deducting->id,
            'first_name' => 'A', 'last_name' => 'Buyer', 'email' => 'buyer@example.test',
            'items' => [
                ['product_id' => $y->id, 'name' => 'Y', 'quantity' => 3, 'price' => 100],
            ],
        ])->assertRedirect();

        $this->assertSame(5, $x->fresh()->quantity, 'X returned by the real save path.');
        $this->assertSame(2, $y->fresh()->quantity, 'Y taken out by the real save path.');

        $this->assertDatabaseHas('order_histories', ['order_id' => $order->id]);
        $this->assertStringContainsString(
            'Inventory reconciled',
            \App\Models\OrderHistory::where('order_id', $order->id)->latest('id')->value('comment')
        );
    }

    /**
     * track_stock off means Venta does not manage this product's inventory.
     * The variant branch used to ignore that and move variants anyway, which
     * broke the parent-equals-sum invariant in the other direction.
     */
    public function test_an_untracked_product_moves_neither_parent_nor_variant(): void
    {
        $p = $this->product('Made to order', 0, track: false);
        $v = ProductVariant::create(['product_id' => $p->id, 'sku' => 'M-1', 'price' => 100, 'quantity' => 5, 'cost' => 0]);
        $p->update(['has_variants' => true, 'quantity' => 5]);

        $order = $this->orderFor($p, 2, $v);
        StockService::adjustForStatusChange($order, $this->deducting);

        $this->assertSame(5, $v->fresh()->quantity, 'an untracked product does not move its variants either.');
        $this->assertSame(5, $p->fresh()->quantity);
    }
}
