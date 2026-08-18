<?php

namespace Tests\Feature\AdminV2;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Store;
use App\Models\User;
use App\Services\ExtensionManager;
use Extensions\CourierFulfillment\PlacementMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Status placement, the module's own page (owner ruling, 2026-08-09 - "Remove
 * the mapping inside order status. This is a coupling. Fulfilment is its own
 * module, mapping should be inside the fulfilment setting.").
 *
 * Every order status, the queue tab it belongs to, and the live order count
 * behind each - with UNPLACED statuses first, because that is the state a
 * fresh install starts in and the work this page exists to finish.
 *
 * The queue's own half of the ruling lives in
 * FulfillmentPlacementMembershipTest (an unplaced status shows up in All and
 * in no other tab) and FulfillmentPlacementMigrationTest (the table is created
 * and dropped with the module).
 */
class FulfillmentStatusPlacementPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Store::create(['id' => 1, 'name' => 'Test Store', 'code' => 'test']);
        app(ExtensionManager::class)->install('courier-fulfillment');
        app(ExtensionManager::class)->bootEnabled();
    }

    private function url(): string
    {
        return route('admin.fulfillment.settings.placement');
    }

    private function makeStatus(string $name, int $sort = 1): OrderStatus
    {
        return OrderStatus::create(['store_id' => 1, 'name' => $name, 'sort_order' => $sort]);
    }

    private function makeOrder(int $statusId): Order
    {
        return Order::create([
            'store_id' => 1,
            'order_status_id' => $statusId,
            'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com',
            'shipping_first_name' => 'Jane', 'shipping_last_name' => 'Doe',
            'shipping_address_1' => '123 Mango St', 'shipping_city' => 'Cebu City',
            'shipping_state' => 'Cebu', 'payment_method' => 'bank_transfer',
            'subtotal' => 1000, 'shipping_cost' => 100, 'total' => 1100,
        ]);
    }

    public function test_page_lists_every_status_with_a_placement_select_and_order_counts(): void
    {
        $this->actingAs(User::factory()->create());

        $processing = $this->makeStatus('Processing', 1);
        $delivered = $this->makeStatus('Delivered', 2);
        PlacementMap::put($delivered->id, 'delivered');

        $this->makeOrder($processing->id);
        $this->makeOrder($processing->id);
        $this->makeOrder($delivered->id);

        $this->get($this->url())
            ->assertOk()
            ->assertSee('Processing')
            ->assertSee('Delivered')
            ->assertSee('name="placements[' . $processing->id . ']"', false)
            ->assertSee('name="placements[' . $delivered->id . ']"', false)
            // The whole placement vocabulary is offered, including the
            // "no tab" choice, whose label carries its one consequence.
            ->assertSee('Not placed (All tab only)')
            ->assertSee('To Ship, To Pack')
            ->assertSee('Lost and Damaged')
            // Live counts, so the merchant sees the scale of a re-placement.
            ->assertSee('2')
            ->assertSee('1');
    }

    /** Unplaced statuses come FIRST: the setup gap is visible, never buried. */
    public function test_unplaced_statuses_are_listed_before_placed_ones(): void
    {
        $this->actingAs(User::factory()->create());

        // Sort order puts the PLACED one first, so only the unplaced-first
        // rule can produce the order this asserts.
        $placed = $this->makeStatus('Aaa Placed', 1);
        PlacementMap::put($placed->id, 'to_pack');
        $this->makeStatus('Zzz Unplaced', 2);

        $body = $this->get($this->url())->assertOk()->getContent();

        $this->assertLessThan(
            strpos($body, 'Aaa Placed'),
            strpos($body, 'Zzz Unplaced'),
            'Unplaced statuses must be listed before placed ones.',
        );
        $this->assertStringContainsString('Not placed', $body);
    }

    public function test_saving_places_statuses_and_unplacing_removes_the_mapping(): void
    {
        $this->actingAs(User::factory()->create());

        $processing = $this->makeStatus('Processing', 1);
        $shipped = $this->makeStatus('Shipped', 2);

        $this->post(route('admin.fulfillment.settings.placement.update'), [
            'placements' => [
                $processing->id => 'to_pack',
                $shipped->id => 'shipping',
            ],
        ])->assertRedirect($this->url());

        $this->assertSame('to_pack', PlacementMap::placementFor($processing->id));
        $this->assertSame('shipping', PlacementMap::placementFor($shipped->id));
        $this->assertTrue(PlacementMap::isMapped($processing->id));

        // Back to unplaced: the row is REMOVED, never stored as a value.
        $this->post(route('admin.fulfillment.settings.placement.update'), [
            'placements' => [$processing->id => ''],
        ])->assertRedirect();

        $this->assertNull(PlacementMap::placementFor($processing->id));
        $this->assertFalse(PlacementMap::isMapped($processing->id));
        $this->assertDatabaseMissing(PlacementMap::TABLE, ['order_status_id' => $processing->id]);
        // The other status is untouched by a partial save.
        $this->assertSame('shipping', PlacementMap::placementFor($shipped->id));
    }

    public function test_an_unknown_placement_value_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $status = $this->makeStatus('Processing', 1);

        $this->post(route('admin.fulfillment.settings.placement.update'), [
            'placements' => [$status->id => 'bogus'],
        ])->assertSessionHasErrors('placements.' . $status->id);

        $this->assertNull(PlacementMap::placementFor($status->id));
    }

    /** Route-model-free ids: another store's status cannot be placed from here. */
    public function test_another_stores_status_cannot_be_placed(): void
    {
        $this->actingAs(User::factory()->create());
        Store::create(['id' => 2, 'name' => 'Other Store', 'code' => 'other']);
        $foreign = OrderStatus::create(['store_id' => 2, 'name' => 'Foreign', 'sort_order' => 1]);

        $this->post(route('admin.fulfillment.settings.placement.update'), [
            'placements' => [$foreign->id => 'to_pack'],
        ])->assertRedirect();

        $this->assertNull(PlacementMap::placementFor($foreign->id));
    }

    /** The counts are one grouped query, never one per status. */
    public function test_order_counts_use_a_single_grouped_query(): void
    {
        $this->actingAs(User::factory()->create());

        $a = $this->makeStatus('Processing', 1);
        $b = $this->makeStatus('Delivered', 2);
        $this->makeOrder($a->id);
        $this->makeOrder($b->id);

        DB::enableQueryLog();
        $this->get($this->url())->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $grouped = array_filter(
            $queries,
            fn ($q) => stripos($q['query'], 'group by') !== false && str_contains($q['query'], 'order_status_id'),
        );

        $this->assertCount(1, $grouped, 'Expected exactly one grouped count query, not N+1.');
    }

    /** The hub links here, and says how much of the mapping is still open. */
    public function test_the_settings_hub_links_to_this_page(): void
    {
        $this->actingAs(User::factory()->create());
        $this->makeStatus('Processing', 1);

        // The link is a menu tab now (owner ruling 2026-08-19), and the
        // placed/unplaced summary lives on the mapping page itself.
        $this->get(route('admin.fulfillment.settings'))
            ->assertOk()
            ->assertSee('Status Mapping')
            ->assertSee($this->url(), false);
    }
}
