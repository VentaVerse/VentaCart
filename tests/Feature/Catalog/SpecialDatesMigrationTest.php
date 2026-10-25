<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Turning product_specials.date_start / date_end into DATETIME columns keeps
 * every stored date meaning the whole day: a start becomes 00:00:00 and an
 * end 23:59:59, so a sale that ended "October 31" still includes October 31.
 *
 * The test rolls back to the migration, writes rows the way the DATE columns
 * held them, and runs that one migration forward.
 */
class SpecialDatesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = '2026_10_25_235900_add_times_to_product_special_dates';

    private function rollbackTo(string $migration): void
    {
        $id = DB::table('migrations')->where('migration', $migration)->value('id');
        $this->assertNotNull($id, "Migration {$migration} has not run.");

        Artisan::call('migrate:rollback', ['--step' => DB::table('migrations')->where('id', '>=', $id)->count()]);
    }

    private function migration(): object
    {
        return require database_path('migrations/' . self::MIGRATION . '.php');
    }

    private function row(int $productId, ?string $start, ?string $end): int
    {
        return DB::table('product_specials')->insertGetId([
            'product_id' => $productId, 'customer_group_id' => null, 'price' => 800, 'quantity' => 1,
            'display_mode' => 'sale', 'priority' => 1, 'date_start' => $start, 'date_end' => $end,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_stored_dates_become_whole_days(): void
    {
        $this->rollbackTo(self::MIGRATION);
        $this->assertSame('date', strtolower(Schema::getColumnType('product_specials', 'date_end')));

        Store::firstOrCreate(['code' => 'test'], ['name' => 'Demo Store']);
        $product = Product::create(['store_id' => 1, 'name' => 'Demo Kettle', 'slug' => 'demo-kettle', 'sku' => 'DEMO-001', 'price' => 1000, 'is_active' => true]);

        $both = $this->row($product->id, '2026-10-25', '2026-10-31');
        $open = $this->row($product->id, null, null);
        $endOnly = $this->row($product->id, null, '2026-12-31');

        Artisan::call('migrate', ['--path' => 'database/migrations/' . self::MIGRATION . '.php']);

        $this->assertSame('datetime', strtolower(Schema::getColumnType('product_specials', 'date_start')));
        $this->assertSame('datetime', strtolower(Schema::getColumnType('product_specials', 'date_end')));

        $read = fn (int $id) => (array) DB::table('product_specials')->where('id', $id)->first(['date_start', 'date_end']);
        $this->assertSame(['date_start' => '2026-10-25 00:00:00', 'date_end' => '2026-10-31 23:59:59'], $read($both));
        $this->assertSame(['date_start' => null, 'date_end' => null], $read($open));
        $this->assertSame(['date_start' => null, 'date_end' => '2026-12-31 23:59:59'], $read($endOnly));

        // Running it again on columns that already hold times changes nothing.
        DB::table('product_specials')->where('id', $both)->update(['date_end' => '2026-10-31 21:30:00']);
        $this->migration()->up();
        $this->assertSame('2026-10-31 21:30:00', $read($both)['date_end']);
    }

    public function test_it_rolls_back_to_dates(): void
    {
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Demo Store']);
        $product = Product::create(['store_id' => 1, 'name' => 'Demo Kettle', 'slug' => 'demo-kettle', 'sku' => 'DEMO-001', 'price' => 1000, 'is_active' => true]);
        $id = $this->row($product->id, '2026-10-25 18:00:00', '2026-10-31 21:30:00');

        $this->migration()->down();

        $this->assertSame('date', strtolower(Schema::getColumnType('product_specials', 'date_end')));
        $row = DB::table('product_specials')->where('id', $id)->first();
        $this->assertSame('2026-10-25', substr((string) $row->date_start, 0, 10));
        $this->assertSame('2026-10-31', (string) $row->date_end);

        $this->migration()->up();
        $this->assertSame('2026-10-31 23:59:59', DB::table('product_specials')->where('id', $id)->value('date_end'));
    }
}
