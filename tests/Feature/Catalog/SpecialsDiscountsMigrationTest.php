<?php

namespace Tests\Feature\Catalog;

use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The split of product_specials into timed special prices and always-on
 * discounts. Each test restores the old table, writes rows in the old shape
 * and runs the split migration over them. The old shape comes from the
 * migration's own down().
 */
class SpecialsDiscountsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const SPLIT_MIGRATION = '2026_11_23_100000_split_product_specials_into_specials_and_discounts';

    private Product $product;

    private CustomerGroup $group;

    protected function setUp(): void
    {
        parent::setUp();

        $store = Store::firstOrCreate(['code' => 'test'], ['name' => 'Demo Store']);
        $this->product = Product::create(['store_id' => $store->id, 'name' => 'Demo Kettle', 'slug' => 'demo-kettle', 'sku' => 'DEMO-001', 'price' => 1000, 'is_active' => true]);
        $this->group = CustomerGroup::create(['store_id' => $store->id, 'name' => 'Wholesale']);

        $this->migration()->down();
    }

    private function migration(): object
    {
        return require database_path('migrations/' . self::SPLIT_MIGRATION . '.php');
    }

    private function oldRow(array $attributes = []): int
    {
        return DB::table('product_specials')->insertGetId($attributes + [
            'product_id' => $this->product->id, 'customer_group_id' => $this->group->id, 'price' => 800,
            'quantity' => 1, 'display_mode' => 'sale', 'priority' => 0,
            'date_start' => null, 'date_end' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function migrate(): void
    {
        $this->migration()->up();
    }

    private function discounts(): array
    {
        return DB::table('product_discounts')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
    }

    public function test_running_override_becomes_a_discount_for_its_group(): void
    {
        $this->oldRow(['display_mode' => 'override', 'price' => 750, 'quantity' => 1, 'date_start' => now()->subDay(), 'date_end' => now()->addDay()]);

        $this->migrate();

        $rows = $this->discounts();
        $this->assertCount(1, $rows);
        $this->assertSame($this->group->id, (int) $rows[0]['customer_group_id']);
        $this->assertSame($this->product->id, (int) $rows[0]['product_id']);
        $this->assertSame(1, (int) $rows[0]['quantity']);
        $this->assertSame('fixed', $rows[0]['price_type']);
        $this->assertEquals(750, $rows[0]['value']);
        $this->assertSame(0, DB::table('product_specials')->count());
    }

    public function test_override_with_no_group_becomes_an_all_groups_discount(): void
    {
        $this->oldRow(['display_mode' => 'override', 'customer_group_id' => null]);

        $this->migrate();

        $this->assertNull($this->discounts()[0]['customer_group_id']);
    }

    public function test_dated_sale_for_all_groups_stays_a_special(): void
    {
        $start = now()->subDay()->startOfSecond();
        $end = now()->addDays(3)->startOfSecond();
        $this->oldRow(['customer_group_id' => null, 'price' => 600, 'date_start' => $start, 'date_end' => $end]);

        $this->migrate();

        $this->assertSame(0, DB::table('product_discounts')->count());
        $this->assertFalse(Schema::hasColumn('product_specials', 'customer_group_id'));
        $row = DB::table('product_specials')->first();
        $this->assertSame('fixed', $row->price_type);
        $this->assertEquals(600, $row->value);
        $this->assertSame($start->format('Y-m-d H:i:s'), substr((string) $row->date_start, 0, 19));
        $this->assertSame($end->format('Y-m-d H:i:s'), substr((string) $row->date_end, 0, 19));
    }

    public function test_sale_for_one_group_becomes_a_discount_for_that_group(): void
    {
        $this->oldRow(['price' => 600, 'date_start' => now()->subDay(), 'date_end' => now()->addDays(3)]);

        $this->migrate();

        $this->assertSame(0, DB::table('product_specials')->count());
        $rows = $this->discounts();
        $this->assertCount(1, $rows);
        $this->assertSame($this->group->id, (int) $rows[0]['customer_group_id']);
        $this->assertEquals(600, $rows[0]['value']);
    }

    public function test_sale_for_all_groups_that_has_not_started_keeps_its_dates_as_a_special(): void
    {
        $this->oldRow(['customer_group_id' => null, 'date_start' => now()->addDay(), 'date_end' => now()->addDays(3)]);

        $this->migrate();

        $this->assertSame(1, DB::table('product_specials')->count());
        $this->assertSame(0, DB::table('product_discounts')->count());
    }

    public function test_not_started_override_is_not_moved(): void
    {
        $this->oldRow(['display_mode' => 'override', 'date_start' => now()->addWeek()]);

        $this->migrate();

        $this->assertSame(0, DB::table('product_discounts')->count());
        $this->assertSame(0, DB::table('product_specials')->count());
    }

    public function test_down_restores_the_old_rows(): void
    {
        $this->product->update(['price' => 1000]);
        $this->oldRow(['display_mode' => 'override', 'price' => 750, 'quantity' => 5]);
        $this->oldRow(['customer_group_id' => null, 'price' => 600, 'date_start' => now()->subDay(), 'date_end' => now()->addDay()]);
        $this->migrate();
        DB::table('product_specials')->insert(['product_id' => $this->product->id, 'price_type' => 'percent', 'value' => 10, 'date_start' => null, 'date_end' => now()->addDay(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('product_discounts')->insert(['product_id' => $this->product->id, 'customer_group_id' => $this->group->id, 'quantity' => 1, 'price_type' => 'percent', 'value' => 20, 'created_at' => now(), 'updated_at' => now()]);

        $this->migration()->down();

        $this->assertFalse(Schema::hasTable('product_discounts'));
        $this->assertTrue(Schema::hasColumns('product_specials', ['price', 'quantity', 'display_mode', 'priority', 'customer_group_id']));
        $this->assertFalse(Schema::hasColumn('product_specials', 'price_type'));
        $rows = DB::table('product_specials')->orderBy('id')->get();
        $this->assertCount(4, $rows);

        $sales = $rows->where('display_mode', 'sale');
        $this->assertCount(2, $sales);
        $this->assertTrue($sales->every(fn ($r) => $r->customer_group_id === null && (int) $r->quantity === 1 && (int) $r->priority === 0 && $r->date_end !== null));
        $this->assertEqualsCanonicalizing([600.0, 900.0], $sales->map(fn ($r) => (float) $r->price)->all());

        $overrides = $rows->where('display_mode', 'override');
        $this->assertCount(2, $overrides);
        $this->assertTrue($overrides->every(fn ($r) => $r->date_start === null && $r->date_end === null && (int) $r->customer_group_id === $this->group->id));
        $this->assertEqualsCanonicalizing([[750.0, 5], [800.0, 1]], $overrides->map(fn ($r) => [(float) $r->price, (int) $r->quantity])->values()->all());

        // And forward again.
        $this->migrate();
        $this->assertTrue(Schema::hasColumn('product_specials', 'price_type'));
    }

    public function test_undated_sale_becomes_a_discount(): void
    {
        $this->oldRow(['display_mode' => 'sale', 'price' => 700]);

        $this->migrate();

        $this->assertCount(1, $this->discounts());
        $this->assertSame(0, DB::table('product_specials')->count());
    }

    public function test_sale_with_quantity_above_one_becomes_a_discount(): void
    {
        $this->oldRow(['quantity' => 5, 'price' => 650, 'date_start' => now()->subDay(), 'date_end' => now()->addDay()]);

        $this->migrate();

        $rows = $this->discounts();
        $this->assertCount(1, $rows);
        $this->assertSame(5, (int) $rows[0]['quantity']);
        $this->assertSame(0, DB::table('product_specials')->count());
    }

    public function test_expired_rows_are_dropped(): void
    {
        $this->oldRow(['display_mode' => 'override', 'date_end' => now()->subHour()]);
        $this->oldRow(['date_end' => now()->subDay()]);
        $this->oldRow(['quantity' => 3, 'date_end' => now()->subDay()]);

        $this->migrate();

        $this->assertSame(0, DB::table('product_discounts')->count());
        $this->assertSame(0, DB::table('product_specials')->count());
    }

    public function test_rows_for_one_group_and_quantity_keep_lowest_priority_then_price(): void
    {
        $this->oldRow(['display_mode' => 'override', 'priority' => 2, 'price' => 500]);
        $this->oldRow(['display_mode' => 'override', 'priority' => 1, 'price' => 900]);
        $this->oldRow(['display_mode' => 'override', 'priority' => 1, 'price' => 850]);
        $this->oldRow(['display_mode' => 'override', 'priority' => 1, 'price' => 850]);
        $this->oldRow(['display_mode' => 'override', 'priority' => 1, 'price' => 700, 'quantity' => 10]);

        $this->migrate();

        $rows = $this->discounts();
        $this->assertCount(2, $rows);
        $byQuantity = collect($rows)->keyBy('quantity');
        $this->assertEquals(850, $byQuantity[1]['value']);
        $this->assertEquals(700, $byQuantity[10]['value']);
    }

    public function test_running_twice_changes_nothing(): void
    {
        $this->oldRow(['display_mode' => 'override', 'price' => 750]);
        $this->oldRow(['price' => 600, 'date_end' => now()->addDays(3)]);

        $this->migrate();
        $discounts = $this->discounts();
        $specials = DB::table('product_specials')->get()->all();

        $this->migrate();

        $this->assertSame($discounts, $this->discounts());
        $this->assertEquals($specials, DB::table('product_specials')->get()->all());
    }

    public function test_fresh_install_has_new_columns(): void
    {
        $this->migrate();

        $this->assertTrue(Schema::hasColumns('product_specials', ['product_id', 'price_type', 'value', 'date_start', 'date_end']));
        foreach (['display_mode', 'priority', 'quantity', 'customer_group_id', 'price'] as $gone) {
            $this->assertFalse(Schema::hasColumn('product_specials', $gone), $gone);
        }
        $this->assertTrue(Schema::hasColumns('product_discounts', ['product_id', 'customer_group_id', 'quantity', 'price_type', 'value']));
    }

    public function test_the_move_lists_late_rows_undated_sales_and_price_changes(): void
    {
        $store = $this->product->store_id;
        $other = Product::create(['store_id' => $store, 'name' => 'Demo Mug', 'slug' => 'demo-mug', 'sku' => 'DEMO-002', 'price' => 500, 'is_active' => true]);
        $third = Product::create(['store_id' => $store, 'name' => 'Demo Cup', 'slug' => 'demo-cup', 'sku' => 'DEMO-003', 'price' => 300, 'is_active' => true]);

        // The old rule charged a group's override even above the regular
        // price; the new one never raises it. The override's dates are not
        // reported.
        $this->oldRow(['display_mode' => 'override', 'price' => 1100, 'date_start' => now()->subDay(), 'date_end' => now()->addDay()]);

        // The old rule took the override by priority; the new one takes the
        // lower special.
        $this->oldRow(['product_id' => $other->id, 'customer_group_id' => null, 'price' => 400, 'priority' => 1, 'date_start' => now()->subDay(), 'date_end' => now()->addDay()]);
        $this->oldRow(['product_id' => $other->id, 'customer_group_id' => null, 'display_mode' => 'override', 'price' => 450]);

        // A group's dated sale loses its dates at the same price; an
        // override that has not started is left out.
        $this->oldRow(['product_id' => $third->id, 'price' => 250, 'date_start' => now()->subDay(), 'date_end' => now()->addDay()]);
        $this->oldRow(['product_id' => $third->id, 'customer_group_id' => null, 'display_mode' => 'override', 'price' => 200, 'date_start' => now()->addWeek()]);

        $migration = $this->migration();
        $migration->up();

        $this->assertCount(4, $migration->notes);
        $this->assertStringStartsWith('dates dropped: DEMO-003, Wholesale, price 250', $migration->notes[0]);
        $this->assertStringStartsWith('not moved, starts later: DEMO-003, all groups, price 200', $migration->notes[1]);
        $this->assertSame('price changes: DEMO-001, Wholesale, 1100.00 -> 1000.00', $migration->notes[2]);
        $this->assertSame('price changes: DEMO-002, all groups, 450.00 -> 400.00', $migration->notes[3]);
    }

    public function test_rows_are_left_alone_when_the_group_column_is_already_gone(): void
    {
        Schema::table('product_specials', function ($table) {
            $table->dropForeign(['customer_group_id']);
        });
        Schema::table('product_specials', function ($table) {
            $table->dropColumn('customer_group_id');
        });
        DB::table('product_specials')->insert([
            'product_id' => $this->product->id, 'price' => 800, 'quantity' => 1, 'display_mode' => 'sale', 'priority' => 0,
            'date_start' => null, 'date_end' => now()->addDay(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->migrate();

        $this->assertSame(0, DB::table('product_discounts')->count());
        $this->assertSame(1, DB::table('product_specials')->count());
        $this->assertFalse(Schema::hasColumn('product_specials', 'display_mode'));
    }
}
