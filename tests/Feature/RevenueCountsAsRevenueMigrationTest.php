<?php

namespace Tests\Feature;

use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * THE HARD GUARANTEE behind making revenue a per-status property: deploying
 * the order_statuses.counts_as_revenue column must not move a single figure
 * outside that one table.
 *
 * This store carries ~27,700 real orders reaching back to March 2016. Every
 * historical figure inherits this flag, so the migration is allowed to add one
 * column to one table and derive its value from the column beside it
 * (deducts_stock) - and nothing else. It must never write to `orders`, never
 * to `products` or `product_variants`, never to any quantity or total, and it
 * must not restate a single order's history.
 *
 * Proven the way the stock conversion proves its own guarantee
 * (tests/Feature/StockDeductsStockMigrationTest.php): build a realistic
 * pre-migration database, snapshot every row of every table the migration
 * must not touch, run the REAL migrator forward, and assert the snapshots come
 * back byte-identical.
 */
class RevenueCountsAsRevenueMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = '2026_08_09_210000_add_counts_as_revenue_to_order_statuses';

    /**
     * Rolls back every migration from $migration onward (inclusive), rather
     * than assuming the target is the most recent file.
     */
    private function rollbackTo(string $migration): void
    {
        $batchId = DB::table('migrations')->where('migration', $migration)->value('id');
        $this->assertNotNull($batchId, "Migration {$migration} has not run; this test cannot roll back to it.");

        $steps = DB::table('migrations')->where('id', '>=', $batchId)->count();
        Artisan::call('migrate:rollback', ['--step' => $steps]);
    }

    /**
     * A pre-migration store: statuses on both sides of deducts_stock,
     * including the awkward pairs the derivation must respect rather than
     * second-guess (a status that deducted and earned nothing is still
     * derived as revenue; the merchant unticks it afterward).
     *
     * @return array{statuses: array<string,int>, products: array<string,int>, orders: array<string,int>}
     */
    private function seedPreMigrationStore(): array
    {
        Store::create(['id' => 1, 'name' => 'Test Store', 'code' => 'test']);

        $now = now();

        $statusSpecs = [
            // name                       deducts_stock
            ['Pending',                   false],
            ['Processing',                true],
            ['Shipped',                   true],
            ['Complete',                  true],
            ['Delivered',                 true],
            ['Canceled',                  false],
            ['Refunded',                  false],
            ['Chargeback',                false],
            ['Quote Ready for Payment',   false],
            // Goods out of the building, money never arrived: derived as
            // revenue because it deducts, and the owner unticks it by hand.
            ['Lost',                      true],
            ['Damaged',                   true],
            // A merchant's own statuses, both sides.
            ['Bespoke Deducting',         true],
            ['Bespoke Idle',              false],
        ];

        $statuses = [];
        foreach ($statusSpecs as $i => [$name, $deducts]) {
            $statuses[$name] = DB::table('order_statuses')->insertGetId([
                'store_id' => 1,
                'name' => $name,
                'is_default' => $name === 'Pending',
                'deducts_stock' => $deducts,
                'sort_order' => $i + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $products = [];
        foreach ([['Tube Screamer', 7], ['Patch Cable', 250]] as $i => [$name, $qty]) {
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

        $orders = [];
        $i = 0;
        foreach (array_keys($statuses) as $statusName) {
            $orders[$statusName] = $this->insertOrder($statuses[$statusName], $products, $i++, $now);
        }

        // And the ghosts: rows with no status at all, exactly the abandoned
        // checkouts OpenCart counted as revenue and Venta never will.
        $orders['ghost'] = $this->insertOrder(null, $products, $i++, $now);

        return compact('statuses', 'products', 'orders');
    }

    private function insertOrder(?int $statusId, array $products, int $i, $now): int
    {
        $orderId = DB::table('orders')->insertGetId([
            'store_id' => 1,
            'order_status_id' => $statusId,
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
            'stock_adjusted' => $i % 2 === 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('order_products')->insert([
            'order_id' => $orderId,
            'product_id' => $products['Tube Screamer'],
            'name' => 'Tube Screamer',
            'price' => 1000,
            'quantity' => 2,
            'total' => 2000,
        ]);

        return $orderId;
    }

    /** @return array<string,array> */
    private function snapshotOfEverythingElse(): array
    {
        $tables = ['orders', 'order_products', 'products', 'product_variants', 'customers'];

        $snapshot = [];
        foreach ($tables as $table) {
            $snapshot[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        }

        return $snapshot;
    }

    /**
     * THE deliverable: the deploy changes nothing outside order_statuses.
     */
    public function test_migration_writes_to_no_table_but_order_statuses(): void
    {
        $this->rollbackTo(self::MIGRATION);
        $this->seedPreMigrationStore();

        $before = $this->snapshotOfEverythingElse();

        // Sanity: the fixture is not trivially empty, or "unchanged" proves
        // nothing at all.
        $this->assertNotEmpty($before['orders']);
        $this->assertNotEmpty($before['order_products']);
        $this->assertNotEmpty($before['products']);

        Artisan::call('migrate');

        $after = $this->snapshotOfEverythingElse();

        foreach ($before as $table => $rows) {
            $this->assertSame($rows, $after[$table], "The migration wrote to `{$table}`.");
        }

        $this->assertSame(
            md5(serialize($before)),
            md5(serialize($after)),
            'The non-status snapshot is not byte-identical across the migration.'
        );

        // And the migration really did run, or everything above passed on a
        // database where nothing happened.
        $this->assertTrue(DB::getSchemaBuilder()->hasColumn('order_statuses', 'counts_as_revenue'));
    }

    /**
     * The derivation the owner ruled: a status which took goods out of
     * inventory is a status where revenue is real. Nothing else is consulted -
     * not the name, not the sort order, not whether orders exist on it.
     */
    public function test_the_initial_value_is_derived_from_deducts_stock(): void
    {
        $this->rollbackTo(self::MIGRATION);
        $this->seedPreMigrationStore();

        Artisan::call('migrate');

        $rows = DB::table('order_statuses')->get(['name', 'deducts_stock', 'counts_as_revenue']);

        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $this->assertSame(
                (bool) $row->deducts_stock,
                (bool) $row->counts_as_revenue,
                "{$row->name} was not derived from its stock behaviour."
            );
        }

        // Both sides are actually represented, or the loop above is vacuous.
        $this->assertTrue((bool) DB::table('order_statuses')->where('name', 'Complete')->value('counts_as_revenue'));
        $this->assertFalse((bool) DB::table('order_statuses')->where('name', 'Refunded')->value('counts_as_revenue'));
        // The awkward one: goods gone, money never arrived. Derived true
        // because it deducted, and independently untickable afterward.
        $this->assertTrue((bool) DB::table('order_statuses')->where('name', 'Lost')->value('counts_as_revenue'));
    }

    /** The two properties are independent once derived: changing one leaves the other alone. */
    public function test_the_flag_moves_independently_of_stock_after_the_derivation(): void
    {
        $this->rollbackTo(self::MIGRATION);
        $this->seedPreMigrationStore();
        Artisan::call('migrate');

        DB::table('order_statuses')->where('name', 'Lost')->update(['counts_as_revenue' => false]);

        $lost = DB::table('order_statuses')->where('name', 'Lost')->first();
        $this->assertTrue((bool) $lost->deducts_stock, 'A lost parcel still took the goods out.');
        $this->assertFalse((bool) $lost->counts_as_revenue, 'A lost parcel earned nothing.');
    }

    /** The rollback path drops the column and leaves stock behaviour untouched. */
    public function test_rollback_drops_the_column_and_moves_nothing_else(): void
    {
        $this->rollbackTo(self::MIGRATION);
        $this->seedPreMigrationStore();
        Artisan::call('migrate');

        $before = $this->snapshotOfEverythingElse();
        $deducts = DB::table('order_statuses')->orderBy('id')->pluck('deducts_stock', 'name')->all();

        $this->rollbackTo(self::MIGRATION);

        $this->assertFalse(DB::getSchemaBuilder()->hasColumn('order_statuses', 'counts_as_revenue'));
        $this->assertTrue(DB::getSchemaBuilder()->hasColumn('order_statuses', 'deducts_stock'));
        $this->assertSame($deducts, DB::table('order_statuses')->orderBy('id')->pluck('deducts_stock', 'name')->all());
        $this->assertSame($before, $this->snapshotOfEverythingElse());
    }

    /** Re-running the migration on a database that already has the column is a no-op. */
    public function test_migration_is_idempotent(): void
    {
        $this->rollbackTo(self::MIGRATION);
        $this->seedPreMigrationStore();
        Artisan::call('migrate');

        // A merchant has since said "Lost earned us nothing".
        DB::table('order_statuses')->where('name', 'Lost')->update(['counts_as_revenue' => false]);
        $statuses = DB::table('order_statuses')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();

        // Running up() again must not re-derive over the merchant's decision.
        $migration = require database_path('migrations/' . self::MIGRATION . '.php');
        $migration->up();

        $this->assertSame($statuses, DB::table('order_statuses')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all());
    }
}
