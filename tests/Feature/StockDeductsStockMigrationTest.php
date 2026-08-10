<?php

namespace Tests\Feature;

use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * THE HARD GUARANTEE behind the stock model simplification: deploying the
 * order_statuses.stock_action -> order_statuses.deducts_stock conversion must
 * move ZERO inventory.
 *
 * The migration is allowed to rewrite one column on one table. It is not
 * allowed to touch orders.stock_adjusted, not allowed to touch any product or
 * variant quantity, and not allowed to trigger a recompute pass - because
 * every one of those would silently re-shelve or re-take real goods on a live
 * store the moment the deploy ran.
 *
 * These tests prove it the only way worth trusting: build a realistic
 * pre-migration database (products with known quantities, variants, orders in
 * assorted statuses carrying assorted stock_adjusted values), snapshot every
 * quantity and every flag, run the REAL migrator forward, and assert the
 * snapshots come back identical.
 *
 * RefreshDatabase has already run every migration by the time a test starts,
 * so each test rolls back to exactly the migration under test first and then
 * migrates forward again, on the same sqlite :memory: engine the rest of the
 * suite uses. Pattern matches FulfillmentPlacementMigrationTest.
 */
class StockDeductsStockMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = '2026_08_09_180000_convert_stock_action_to_deducts_stock';

    /**
     * Rolls back every migration from $migration onward (inclusive), rather
     * than assuming the target is the most recent file: a later migration
     * landing on top must not silently make --step=1 roll back the wrong one.
     */
    private function rollbackTo(string $migration): void
    {
        $batchId = DB::table('migrations')->where('migration', $migration)->value('id');
        $this->assertNotNull($batchId, "Migration {$migration} has not run; this test cannot roll back to it.");

        $steps = DB::table('migrations')->where('id', '>=', $batchId)->count();
        Artisan::call('migrate:rollback', ['--step' => $steps]);
    }

    /**
     * Runs the migration under test and NOTHING else.
     *
     * rollbackTo() rewinds the whole tail, so a plain `migrate` would replay
     * every later migration too and this file would then be asserting things
     * about migrations it knows nothing about - any future schema change to
     * `products` or `orders` would read as "the conversion wrote to that
     * table". The guarantee here is about one migration, so run one migration.
     */
    private function migrateUnderTest(): void
    {
        Artisan::call('migrate', ['--path' => 'database/migrations/' . self::MIGRATION . '.php']);
    }

    /**
     * A pre-migration store: statuses still carrying the three old
     * stock_action values, products and variants with distinctive quantities,
     * and orders spread across those statuses with BOTH stock_adjusted values
     * - including the deliberately "wrong looking" combinations (an order
     * sitting in an increment status with the stock still out, an order in a
     * decrement status with the stock already back) that a careless recompute
     * pass would rush to fix.
     *
     * @return array{statuses: array<string,int>, products: array<string,int>, variants: array<string,int>, orders: array<string,int>}
     */
    private function seedPreMigrationStore(): array
    {
        Store::create(['id' => 1, 'name' => 'Test Store', 'code' => 'test']);

        $now = now();

        $statusSpecs = [
            // name                       stock_action
            ['Pending',                   'decrement'],
            ['Processing',                'decrement'],
            ['Shipped',                   'decrement'],
            ['Complete',                  'decrement'],
            ['Canceled',                  'increment'],
            ['Refunded',                  'increment'],
            ['Returned',                  'increment'],
            ['Arrived at NGD',            null],
            ['In Transit to Manila',      null],
            ['Delivery Failed',           null],
            ['Pickup Failed',             null],
            ['Returning',                 null],
            ['Lost',                      null],
            ['Damaged',                   null],
            ['Quote Ready for Payment',   null],
            // A merchant's own status, in each of the three old shapes: the
            // conversion must not know or care about these names.
            ['Bespoke Deducting',         'decrement'],
            ['Bespoke Returning',         'increment'],
            ['Bespoke Untagged',          null],
        ];

        $statuses = [];
        foreach ($statusSpecs as $i => [$name, $action]) {
            $statuses[$name] = DB::table('order_statuses')->insertGetId([
                'store_id' => 1,
                'name' => $name,
                'is_default' => $name === 'Pending',
                'stock_action' => $action,
                'sort_order' => $i + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $products = [];
        foreach ([['Tube Screamer', 7], ['Patch Cable', 250], ['Leather Strap', 0]] as $i => [$name, $qty]) {
            $products[$name] = DB::table('products')->insertGetId([
                'store_id' => 1,
                'name' => $name,
                'slug' => 'p-' . $i,
                'price' => 1000,
                'quantity' => $qty,
                'track_stock' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $variants = [
            'Strap Black' => DB::table('product_variants')->insertGetId([
                'product_id' => $products['Leather Strap'],
                'sku' => 'STRAP-BLK',
                'price' => 1500,
                'quantity' => 13,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]),
        ];

        // Orders across the whole status vocabulary, both flag values.
        $orderSpecs = [
            ['Processing',              true],
            ['Processing',              false],
            ['Canceled',                false],
            ['Canceled',                true],   // stock still out in an increment status
            ['Complete',                false],  // stock already back in a decrement status
            ['Arrived at NGD',          true],
            ['In Transit to Manila',    false],
            ['Delivery Failed',         true],
            ['Pickup Failed',           false],
            ['Returning',               true],
            ['Lost',                    true],
            ['Damaged',                 false],
            ['Quote Ready for Payment', true],
            ['Quote Ready for Payment', false],
            ['Bespoke Untagged',        true],
            ['Bespoke Returning',       true],
        ];

        $orders = [];
        foreach ($orderSpecs as $i => [$statusName, $adjusted]) {
            $orderId = DB::table('orders')->insertGetId([
                'store_id' => 1,
                'order_status_id' => $statuses[$statusName],
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'email' => 'jane' . $i . '@example.com',
                'shipping_first_name' => 'Jane',
                'shipping_last_name' => 'Doe',
                'shipping_address_1' => '123 Mango St',
                'shipping_city' => 'Cebu City',
                'shipping_state' => 'Cebu',
                'payment_method' => 'bank_transfer',
                'subtotal' => 1000,
                'shipping_cost' => 100,
                'total' => 1100,
                'stock_adjusted' => $adjusted,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Real line items, so a recompute pass would have something to
            // recompute FROM. Without these the guarantee would be vacuous.
            DB::table('order_products')->insert([
                'order_id' => $orderId,
                'product_id' => $products['Tube Screamer'],
                'name' => 'Tube Screamer',
                'price' => 1000,
                'quantity' => 2,
                'total' => 2000,
            ]);
            DB::table('order_products')->insert([
                'order_id' => $orderId,
                'product_id' => $products['Leather Strap'],
                'product_variant_id' => $variants['Strap Black'],
                'name' => 'Leather Strap',
                'price' => 500,
                'quantity' => 1,
                'total' => 500,
            ]);

            $orders[$statusName . '#' . $i] = $orderId;
        }

        return compact('statuses', 'products', 'variants', 'orders');
    }

    /** @return array{products: array, variants: array, orders: array} */
    private function inventorySnapshot(): array
    {
        return [
            'products' => DB::table('products')->orderBy('id')->pluck('quantity', 'id')->all(),
            'variants' => DB::table('product_variants')->orderBy('id')->pluck('quantity', 'id')->all(),
            'orders' => DB::table('orders')->orderBy('id')->pluck('stock_adjusted', 'id')->all(),
        ];
    }

    /**
     * THE deliverable: the deploy moves zero inventory.
     */
    public function test_migration_moves_zero_inventory(): void
    {
        $this->rollbackTo(self::MIGRATION);
        $this->seedPreMigrationStore();

        $before = $this->inventorySnapshot();

        // Sanity: the fixture is not trivially empty, or "unchanged" would
        // prove nothing at all.
        $this->assertNotEmpty($before['products']);
        $this->assertNotEmpty($before['variants']);
        $this->assertNotEmpty($before['orders']);
        $this->assertContains(1, array_map('intval', $before['orders']), 'Fixture must include stock_adjusted orders.');
        $this->assertContains(0, array_map('intval', $before['orders']), 'Fixture must include un-adjusted orders.');

        $this->migrateUnderTest();

        $after = $this->inventorySnapshot();

        $this->assertSame($before['products'], $after['products'], 'The migration changed a product quantity.');
        $this->assertSame($before['variants'], $after['variants'], 'The migration changed a variant quantity.');
        $this->assertSame($before['orders'], $after['orders'], 'The migration changed orders.stock_adjusted.');

        // Byte-identical, stated as bluntly as the guarantee is stated.
        $this->assertSame(
            md5(serialize($before)),
            md5(serialize($after)),
            'The inventory snapshot is not byte-identical across the migration.'
        );

        // And the migration really did run - otherwise the assertions above
        // would pass on a database where nothing happened at all.
        $this->assertTrue(DB::getSchemaBuilder()->hasColumn('order_statuses', 'deducts_stock'));
        $this->assertFalse(DB::getSchemaBuilder()->hasColumn('order_statuses', 'stock_action'));
    }

    /**
     * Nothing outside order_statuses is written at all - proven by row
     * timestamps, which any write through the query builder or Eloquent would
     * have disturbed, and by the row counts staying put (no inserted
     * adjustment/audit rows either).
     */
    public function test_migration_writes_to_no_table_but_order_statuses(): void
    {
        $this->rollbackTo(self::MIGRATION);
        $this->seedPreMigrationStore();

        $before = [
            'orders' => DB::table('orders')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            'order_products' => DB::table('order_products')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            'products' => DB::table('products')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            'product_variants' => DB::table('product_variants')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
        ];

        $this->migrateUnderTest();

        $after = [
            'orders' => DB::table('orders')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            'order_products' => DB::table('order_products')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            'products' => DB::table('products')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            'product_variants' => DB::table('product_variants')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
        ];

        foreach ($before as $table => $rows) {
            $this->assertSame($rows, $after[$table], "The migration wrote to `{$table}`.");
        }
    }

    /**
     * The owner-approved conversion table, exactly.
     */
    public function test_conversion_maps_every_status_the_way_the_owner_ruled(): void
    {
        $this->rollbackTo(self::MIGRATION);
        $this->seedPreMigrationStore();

        $this->migrateUnderTest();

        $deducts = DB::table('order_statuses')->pluck('deducts_stock', 'name')
            ->map(fn ($v) => (bool) $v)->all();

        // The old 'decrement' statuses stay deducting.
        foreach (['Pending', 'Processing', 'Shipped', 'Complete', 'Bespoke Deducting'] as $name) {
            $this->assertTrue($deducts[$name], "{$name} should still deduct stock.");
        }

        // The old 'increment' statuses become non-deducting: the reversal they
        // used to be tagged with is automatic now, so behaviour is unchanged.
        foreach (['Canceled', 'Refunded', 'Returned', 'Bespoke Returning'] as $name) {
            $this->assertFalse($deducts[$name], "{$name} should no longer be tagged; the reversal is automatic.");
        }

        // Seven untagged statuses where the goods are genuinely out of the
        // building become deducting.
        foreach ([
            'Arrived at NGD', 'In Transit to Manila', 'Delivery Failed',
            'Pickup Failed', 'Returning', 'Lost', 'Damaged',
        ] as $name) {
            $this->assertTrue($deducts[$name], "{$name} should deduct: the goods are out of inventory.");
        }

        // The pre-order quote stays non-deducting.
        $this->assertFalse($deducts['Quote Ready for Payment']);

        // A merchant's unrecognised untagged status converts to the closest
        // safe equivalent: non-deducting, exactly as it behaved before.
        $this->assertFalse($deducts['Bespoke Untagged']);
    }

    /** Name matching is case-insensitive, so live spelling variance still converts. */
    public function test_conversion_matches_names_case_insensitively(): void
    {
        $this->rollbackTo(self::MIGRATION);
        Store::create(['id' => 1, 'name' => 'Test Store', 'code' => 'test']);

        $now = now();
        DB::table('order_statuses')->insert([
            ['store_id' => 1, 'name' => 'DELIVERY FAILED', 'sort_order' => 1, 'stock_action' => null, 'created_at' => $now, 'updated_at' => $now],
            ['store_id' => 1, 'name' => 'quote ready for payment', 'sort_order' => 2, 'stock_action' => 'decrement', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $this->migrateUnderTest();

        $this->assertTrue((bool) DB::table('order_statuses')->where('name', 'DELIVERY FAILED')->value('deducts_stock'));
        // The pinned non-deducting name wins over the old column's baseline.
        $this->assertFalse((bool) DB::table('order_statuses')->where('name', 'quote ready for payment')->value('deducts_stock'));
    }

    /** The rollback path restores a usable stock_action column. */
    public function test_rollback_restores_stock_action(): void
    {
        $this->rollbackTo(self::MIGRATION);
        $this->seedPreMigrationStore();
        $this->migrateUnderTest();

        $before = $this->inventorySnapshot();

        $this->rollbackTo(self::MIGRATION);

        $this->assertTrue(DB::getSchemaBuilder()->hasColumn('order_statuses', 'stock_action'));
        $this->assertFalse(DB::getSchemaBuilder()->hasColumn('order_statuses', 'deducts_stock'));
        $this->assertSame('decrement', DB::table('order_statuses')->where('name', 'Processing')->value('stock_action'));
        $this->assertNull(DB::table('order_statuses')->where('name', 'Canceled')->value('stock_action'));

        // Rolling back moves no inventory either.
        $this->assertSame($before, $this->inventorySnapshot());
    }
}
