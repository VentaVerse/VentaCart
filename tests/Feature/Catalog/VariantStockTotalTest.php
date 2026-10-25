<?php

namespace Tests\Feature\Catalog;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Owner ruling 2026-10-16: a product with variants carries the stock of its
 * switched-on variants only. A switched-off variant's units are not stock
 * the shop can sell, so they never count in the parent's total, whoever
 * changes the stock: the admin, the ERP, or an order moving.
 */
class VariantStockTotalTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private ProductVariant $on;

    private ProductVariant $off;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);

        $this->product = Product::create([
            'store_id' => 1, 'name' => 'Compressor', 'slug' => 'compressor', 'sku' => 'CMP',
            'price' => 1000, 'quantity' => 0, 'is_active' => true, 'has_variants' => true, 'track_stock' => true,
        ]);
        $this->on = $this->product->variants()->create(['sku' => 'CMP-S', 'name' => 'Silver', 'price' => 1000, 'quantity' => 3, 'is_active' => true]);
        $this->off = $this->product->variants()->create(['sku' => 'CMP-B', 'name' => 'Blue', 'price' => 1000, 'quantity' => 5, 'is_active' => false]);
        $this->product->syncStockFromVariants();
    }

    public function test_the_total_counts_only_switched_on_variants(): void
    {
        $this->assertSame(3, (int) $this->product->fresh()->quantity);
    }

    public function test_switching_a_variant_on_or_off_moves_the_total(): void
    {
        $this->off->update(['is_active' => true]);
        $this->product->syncStockFromVariants();
        $this->assertSame(8, (int) $this->product->fresh()->quantity);

        $this->on->update(['is_active' => false]);
        $this->product->syncStockFromVariants();
        $this->assertSame(5, (int) $this->product->fresh()->quantity);
    }

    public function test_an_order_of_a_switched_on_variant_takes_from_the_total(): void
    {
        $order = $this->orderOf($this->on, 2);

        StockService::adjustForStatusChange($order, $this->orderStatus(deducts: true));

        $this->assertSame(1, (int) $this->on->fresh()->quantity);
        $this->assertSame(1, (int) $this->product->fresh()->quantity);
    }

    /** Stock coming back to a switched-off variant is that variant's, not the shop's. */
    public function test_returning_stock_to_a_switched_off_variant_leaves_the_total_alone(): void
    {
        $order = $this->orderOf($this->off, 2);
        $order->forceFill(['stock_adjusted' => true])->save();

        StockService::adjustForStatusChange($order, $this->orderStatus(deducts: false));

        $this->assertSame(7, (int) $this->off->fresh()->quantity);
        $this->assertSame(3, (int) $this->product->fresh()->quantity);
    }

    public function test_the_erp_switching_a_variant_off_drops_the_total(): void
    {
        $this->apiKey('erp-test-token');
        app(\App\Services\ExtensionManager::class)->install('erp-sync');
        app(\App\Services\ExtensionManager::class)->bootEnabled();

        $this->withToken('erp-test-token')
            ->putJson('/api/v1/variants/CMP-S', ['status' => false])
            ->assertOk();

        $this->assertSame(0, (int) $this->product->fresh()->quantity);
    }

    public function test_the_migration_recomputes_existing_totals(): void
    {
        DB::table('products')->whereKey($this->product->id)->update(['quantity' => 8]);

        $migration = require database_path('migrations/2026_10_16_100001_resync_variant_stock_to_switched_on_variants.php');
        $migration->up();

        $this->assertSame(3, (int) $this->product->fresh()->quantity);
    }

    private function orderStatus(bool $deducts): OrderStatus
    {
        return OrderStatus::create(['store_id' => 1, 'name' => $deducts ? 'Processing' : 'Canceled', 'sort_order' => 1, 'deducts_stock' => $deducts]);
    }

    private function orderOf(ProductVariant $variant, int $quantity): Order
    {
        $order = Order::create([
            'store_id' => 1, 'first_name' => 'A', 'last_name' => 'Buyer', 'email' => 'a@example.test',
        ]);
        $order->products()->create([
            'product_id' => $this->product->id, 'product_variant_id' => $variant->id,
            'name' => $this->product->name, 'price' => 1000, 'quantity' => $quantity, 'total' => 1000 * $quantity,
        ]);

        return $order->fresh();
    }
}
