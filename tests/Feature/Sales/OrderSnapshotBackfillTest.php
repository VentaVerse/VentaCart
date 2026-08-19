<?php

namespace Tests\Feature\Sales;

use App\Models\OrderStatus;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Proves the snapshot backfill actually writes.
 *
 * This is not covered by anything else: every test database is built fresh, so
 * there are never pre-existing orders and the backfill inside
 * `add_history_snapshot_columns_to_orders` is a no-op in the whole suite. The
 * one situation it exists for is a store with real order history running that
 * migration for the first time, and that situation has no other test.
 *
 * The rows are inserted raw. Order::booted() stamps the snapshot on save, so
 * anything created through Eloquent arrives already filled and would prove
 * nothing.
 */
class OrderSnapshotBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_command_fills_snapshots_on_orders_that_predate_the_column(): void
    {
        Store::firstOrCreate(['id' => 1], ['name' => 'Test Store', 'code' => 'test']);
        $status = OrderStatus::create([
            'store_id' => 1, 'name' => 'Processing', 'sort_order' => 1, 'is_default' => true,
        ]);

        // A store's history as it looks the instant the column is added.
        $ids = [];
        foreach (range(1, 3) as $n) {
            $ids[] = DB::table('orders')->insertGetId([
                'store_id' => 1,
                'order_status_id' => $status->id,
                'order_status_name' => null,
                'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => "jane{$n}@example.com",
                'subtotal' => 100, 'total' => 100,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // A genuine abandoned checkout: no status, so nothing to derive.
        $ghostId = DB::table('orders')->insertGetId([
            'store_id' => 1,
            'order_status_id' => null,
            'order_status_name' => null,
            'first_name' => 'Ghost', 'last_name' => 'Order', 'email' => 'ghost@example.com',
            'subtotal' => 50, 'total' => 50,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(4, DB::table('orders')->whereNull('order_status_name')->count());

        $this->artisan('orders:backfill-snapshots')->assertExitCode(0);

        foreach ($ids as $id) {
            $this->assertSame(
                'Processing',
                DB::table('orders')->where('id', $id)->value('order_status_name'),
                'A real order kept its NULL snapshot after the backfill.'
            );
        }

        $this->assertNull(
            DB::table('orders')->where('id', $ghostId)->value('order_status_name'),
            'A genuine ghost has no status to derive and must stay NULL.'
        );
    }

    /** Safe to run twice, which is what makes it safe to run at all. */
    public function test_running_it_again_changes_nothing(): void
    {
        Store::firstOrCreate(['id' => 1], ['name' => 'Test Store', 'code' => 'test']);
        $status = OrderStatus::create([
            'store_id' => 1, 'name' => 'Processing', 'sort_order' => 1, 'is_default' => true,
        ]);

        $id = DB::table('orders')->insertGetId([
            'store_id' => 1,
            'order_status_id' => $status->id,
            'order_status_name' => null,
            'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com',
            'subtotal' => 100, 'total' => 100,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('orders:backfill-snapshots')->assertExitCode(0);

        // An admin renames the status afterwards. The snapshot is the order's
        // own record and must NOT follow it.
        $status->update(['name' => 'Renamed After The Fact']);

        $this->artisan('orders:backfill-snapshots')->assertExitCode(0);

        $this->assertSame(
            'Processing',
            DB::table('orders')->where('id', $id)->value('order_status_name'),
            'A second run overwrote a snapshot it should have left alone.'
        );
    }
}
