<?php

namespace Tests\Feature\Sales;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Live incident, 2026-08-20: every order vanished from the admin after deploy.
 *
 * Nothing was deleted. `add_history_snapshot_columns_to_orders` adds
 * `order_status_name` and backfills nothing, so every pre-existing order got
 * NULL. Both the orders list and the dashboard treat a NULL snapshot as "this
 * order never reached a status", so 27,706 real orders disappeared and the
 * dashboard switched to its new-store shape. The data was intact the whole
 * time; `orders:backfill-snapshots` brought it back.
 *
 * Two fixes, because either alone leaves a hole:
 *
 *  1. The migration backfills, so the state never exists.
 *  2. "Missing" stops meaning "snapshot is NULL" and starts meaning what it
 *     always meant: the order never reached a status at all. A real order
 *     always carries order_status_id even before its snapshot is written, and
 *     a genuine ghost carries neither. Distinguishing them is what turns a
 *     skipped deploy step from a catastrophe into a cosmetic blank.
 */
class OrderSnapshotResilienceTest extends TestCase
{
    use RefreshDatabase;

    private OrderStatus $status;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['id' => 1], ['name' => 'Test Store', 'code' => 'test']);
        $this->status = OrderStatus::create([
            'store_id' => 1, 'name' => 'Processing', 'sort_order' => 1, 'is_default' => true,
        ]);
        $this->actingAs(User::factory()->create());
    }

    /**
     * Inserted raw, NOT through Eloquent. Order::booted() registers a saving
     * hook that stamps order_status_name from order_status_id, so the model
     * physically cannot produce the un-backfilled state. Only a migration
     * adding the column to rows that already exist can, which is exactly what
     * happened on live. A fixture that went through Order::create() would
     * silently be handed a snapshot and prove nothing.
     */
    private function order(?int $statusId, ?string $snapshot): Order
    {
        $id = DB::table('orders')->insertGetId([
            'store_id' => 1,
            'order_status_id' => $statusId,
            'order_status_name' => $snapshot,
            'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com',
            'subtotal' => 100, 'total' => 100,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return Order::withoutGlobalScopes()->findOrFail($id);
    }

    private function ordersUrl(array $params = []): string
    {
        return '/' . config('admin.prefix', 'admin') . '/sales/orders' . ($params ? '?' . http_build_query($params) : '');
    }

    /**
     * The incident, reproduced. A real order whose snapshot was never written
     * must still appear. This is the assertion that would have caught it.
     */
    public function test_a_real_order_with_no_snapshot_yet_still_appears_in_the_list(): void
    {
        $order = $this->order($this->status->id, null);

        $this->get($this->ordersUrl())
            ->assertOk()
            ->assertSee('#' . $order->id, false);
    }

    public function test_a_normal_order_appears(): void
    {
        $order = $this->order($this->status->id, 'Processing');

        $this->get($this->ordersUrl())
            ->assertOk()
            ->assertSee('#' . $order->id, false);
    }

    /** A genuine ghost: never reached a status, so it carries neither field. */
    public function test_a_genuine_ghost_order_stays_out_of_the_default_list(): void
    {
        $ghost = $this->order(null, null);

        $this->get($this->ordersUrl())
            ->assertOk()
            ->assertDontSee('#' . $ghost->id, false);
    }

    public function test_the_missing_filter_shows_only_genuine_ghosts(): void
    {
        $ghost = $this->order(null, null);
        $unbackfilled = $this->order($this->status->id, null);

        $this->get($this->ordersUrl(['order_status_id' => 'missing']))
            ->assertOk()
            ->assertSee('#' . $ghost->id, false)
            ->assertDontSee('#' . $unbackfilled->id, false);
    }

    /**
     * The dashboard made the same mistake, which is why the whole interface
     * swapped to onboarding. It must judge trade the same way.
     */
    public function test_the_dashboard_counts_an_unbackfilled_order_as_trade(): void
    {
        $this->order($this->status->id, null);

        $this->get('/' . config('admin.prefix', 'admin') . '/dashboard')
            ->assertOk()
            ->assertDontSee('No orders yet.', false);
    }
}
