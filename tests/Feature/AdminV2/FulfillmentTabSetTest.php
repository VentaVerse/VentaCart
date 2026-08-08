<?php

namespace Tests\Feature\AdminV2;

use App\Models\Order;
use App\Models\OrderStatus;
use Extensions\CourierFulfillment\PlacementMap;
use App\Models\Setting;
use App\Models\User;
use App\Services\ExtensionManager;
use Extensions\CourierFulfillment\FulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * The fulfillment TAB SET, as the owner ruled it on 2026-08-09:
 *
 *   All | Unpaid | To Ship (To Pack | To Handover) | Shipping | Failed |
 *   Lost & Damaged | Delivered | Cancelled          + the Returns aside
 *
 * Three things this file pins that no earlier test could:
 *
 *  1. ALL is a VIEW, never a placement. It lists every order regardless of
 *     placement, counts every order, and each of its rows carries the
 *     actions of the tab it really belongs to.
 *  2. `in_transit` is `shipping` everywhere - enum value, URL filter value,
 *     label - with the old URL 301ing onto the new one so bookmarks
 *     converge instead of breaking.
 *  3. `lost_damaged` is a real placement end to end: a status can be placed
 *     there, orders on it list and count under Lost & Damaged, and the row
 *     keeps its parcel's own buttons.
 *
 * Plus the setup notice on the settings hub, which exists because the
 * seeder deliberately ships no To-Handover-placed status (owner ruling) and
 * booking therefore refuses on a fresh install.
 */
class FulfillmentTabSetTest extends TestCase
{
    use RefreshDatabase;

    private string $prefix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prefix = config('admin.prefix', 'admin');
    }

    private function queueUrl(): string
    {
        return '/' . $this->prefix . '/sales/fulfillment';
    }

    private function settingsUrl(): string
    {
        return '/' . $this->prefix . '/settings/modules/courier-fulfillment';
    }

    private function installExtension(): void
    {
        app(ExtensionManager::class)->install('courier-fulfillment');
        app(ExtensionManager::class)->bootEnabled();
    }

    private function enableQuadxStaging(): void
    {
        Setting::create(['group' => 'courier', 'key' => 'quadx_enabled', 'value' => '1']);
        Setting::create(['group' => 'courier', 'key' => 'quadx_mode', 'value' => 'staging']);
        Setting::create(['group' => 'courier', 'key' => 'quadx_staging_api_key', 'value' => Crypt::encryptString('test-api-key')]);
        Setting::create(['group' => 'courier', 'key' => 'quadx_staging_secret_key', 'value' => Crypt::encryptString('test-secret-key')]);
    }

    private function statusPlaced(string $placement, ?string $name = null): OrderStatus
    {
        $status = OrderStatus::firstOrCreate(
            ['store_id' => 1, 'name' => $name ?? ('Status ' . $placement)],
            ['sort_order' => 1],
        );

        PlacementMap::put($status->id, $placement);

        return $status;
    }

    private function makeOrder(OrderStatus $status, ?string $tracking = null, ?string $courierStatus = null): Order
    {
        $order = Order::create([
            'store_id' => 1,
            'order_status_id' => $status->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'shipping_first_name' => 'Jane',
            'shipping_last_name' => 'Doe',
            'shipping_address_1' => '123 Mango St',
            'shipping_city' => 'Cebu City',
            'shipping_state' => 'Cebu',
            'payment_method' => 'bank_transfer',
            'subtotal' => 1000,
            'shipping_cost' => 100,
            'total' => 1100,
        ]);

        if ($tracking !== null || $courierStatus !== null) {
            $order->forceFill([
                'courier_provider' => 'quadx',
                'courier_tracking_number' => $tracking,
                'courier_status' => $courierStatus,
            ])->save();
        }

        return $order;
    }

    /** The order-number anchor exactly as the queue's band renders it. */
    private function rowMarker(int $id): string
    {
        return '>#' . $id . '</a>';
    }

    // ------------------------------------------------------------------
    // The bar itself.
    // ------------------------------------------------------------------

    public function test_the_eight_tabs_render_in_the_ruled_order_with_the_returns_aside(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        // A REAL row on the spine's `in_transit` phase, so the retired-word
        // assertion below has something to catch. Without it the page
        // carries no status chip at all and assertDontSee('In Transit')
        // passes on an empty table, proving nothing. 'out_for_delivery' is
        // deliberate: QuadX buckets it to the `in_transit` spine phase (the
        // internal value that deliberately did NOT change), while its own
        // courier-vocabulary label reads "Out For Delivery", so the only
        // thing that can put "In Transit" on this page is the spine chip.
        $this->makeOrder($this->statusPlaced('shipping', 'Shipped'), 'QX-TAB-1', 'out_for_delivery');

        $html = $this->get($this->queueUrl())->assertOk()->getContent();

        $positions = [];
        foreach (['all', 'unpaid', 'to_ship', 'shipping', 'failed', 'lost_damaged', 'delivered', 'cancelled'] as $key) {
            $at = strpos($html, 'data-count-for="' . $key . '"');
            $this->assertNotFalse($at, "Tab {$key} is missing from the segment bar.");
            $positions[$key] = $at;
        }

        $this->assertSame(
            ['all', 'unpaid', 'to_ship', 'shipping', 'failed', 'lost_damaged', 'delivered', 'cancelled'],
            array_keys($positions),
        );
        $ordered = $positions;
        asort($ordered);
        $this->assertSame(array_keys($positions), array_keys($ordered), 'The tabs do not render in the ruled order.');

        // Labels, and the retired one - checked on the Shipping tab, where
        // the seeded row above renders its status chip.
        $this->get($this->queueUrl() . '?filter=shipping')
            ->assertOk()
            ->assertSee('QX-TAB-1')
            ->assertSee('All')
            ->assertSee('Shipping')
            ->assertSee('Lost & Damaged')
            // The chip reads the tab's word, never the spine's own value.
            ->assertDontSee('In Transit')
            ->assertSee('Out For Delivery')
            // Returns stays an aside link, never a tab.
            ->assertSee('Returns')
            ->assertDontSee('data-count-for="returns"', false);
    }

    // ------------------------------------------------------------------
    // All: a view, not a placement.
    // ------------------------------------------------------------------

    public function test_all_lists_every_order_regardless_of_placement_and_counts_them_all(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $orders = [];
        foreach (FulfillmentService::PLACEMENTS as $placement) {
            $orders[$placement] = $this->makeOrder($this->statusPlaced($placement));
        }

        $response = $this->get($this->queueUrl() . '?filter=all&per_page=50');
        $response->assertOk()
            ->assertSee('data-count-for="all">' . count(FulfillmentService::PLACEMENTS) . '<', false);

        foreach ($orders as $placement => $order) {
            $response->assertSee($this->rowMarker($order->id), false);
        }
    }

    /**
     * The point of All: every row keeps the actions of the tab it really
     * belongs to, resolved per row from its own core status placement.
     */
    public function test_all_rows_carry_the_actions_of_their_real_tab(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        $unpaid    = $this->makeOrder($this->statusPlaced('unpaid', 'Unpaid'));
        $toPack    = $this->makeOrder($this->statusPlaced('to_pack', 'Processing'));
        $handover  = $this->makeOrder($this->statusPlaced('to_handover', 'Order Being Shipped'), 'QX-ALL-H', 'for_pickup');
        $shipping  = $this->makeOrder($this->statusPlaced('shipping', 'Shipped'), 'QX-ALL-S', 'in_transit');
        $lost      = $this->makeOrder($this->statusPlaced('lost_damaged', 'Lost in Transit'), 'QX-ALL-L', 'in_transit');

        $response = $this->get($this->queueUrl() . '?filter=all&per_page=50');
        $response->assertOk();

        // Unpaid: its one action, and no courier affordance of any kind.
        $response->assertSee("cancel-order-{$unpaid->id}", false);

        // To Pack: Book plus Cancel order (Book is a single shared modal
        // opener, so its presence is asserted once for the page).
        $response->assertSee('openBooking(JSON.parse(', false)
            ->assertSee("cancel-order-{$toPack->id}", false);

        // To Handover: the only tab that offers Cancel booking.
        $response->assertSee("cancel-fulfillment-{$handover->id}", false)
            ->assertDontSee("cancel-fulfillment-{$shipping->id}", false)
            ->assertDontSee("cancel-fulfillment-{$lost->id}", false);

        // Neither Shipping nor Lost & Damaged offers Book or Cancel order.
        $response->assertDontSee("cancel-order-{$shipping->id}", false)
            ->assertDontSee("cancel-order-{$lost->id}", false)
            ->assertDontSee("cancel-order-{$handover->id}", false);

        // Every parcel-carrying row keeps its own two buttons: the label
        // route is per order, so each one is asserted by its own URL.
        foreach ([$handover, $shipping, $lost] as $order) {
            $response->assertSee('/sales/fulfillment/' . $order->id . '/label', false);
        }
        // ...and the Unpaid row never gets one, even though it is on the
        // same page as rows that do.
        $response->assertDontSee('/sales/fulfillment/' . $unpaid->id . '/label', false);
    }

    public function test_all_respects_search(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $wanted = $this->makeOrder($this->statusPlaced('delivered', 'Delivered'), 'QX-FIND-ME', 'delivered');
        $other  = $this->makeOrder($this->statusPlaced('to_pack', 'Processing'));

        $this->get($this->queueUrl() . '?filter=all&q=QX-FIND-ME')
            ->assertOk()
            ->assertSee($this->rowMarker($wanted->id), false)
            ->assertDontSee($this->rowMarker($other->id), false);
    }

    // ------------------------------------------------------------------
    // Shipping: the rename, end to end.
    // ------------------------------------------------------------------

    public function test_a_shipping_placed_order_lands_in_the_shipping_tab(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $order = $this->makeOrder($this->statusPlaced('shipping', 'Shipped'), 'QX-SHIP-1', 'in_transit');

        $this->get($this->queueUrl() . '?filter=shipping')
            ->assertOk()
            ->assertSee('QX-SHIP-1')
            ->assertSee('data-count-for="shipping">1<', false);
    }

    public function test_the_legacy_in_transit_filter_redirects_permanently_to_shipping_keeping_its_params(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $response = $this->get($this->queueUrl() . '?filter=in_transit&q=QX&per_page=10');

        $response->assertStatus(301);
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('filter=shipping', $location);
        $this->assertStringContainsString('q=QX', $location);
        $this->assertStringContainsString('per_page=10', $location);
        $this->assertStringNotContainsString('filter=in_transit', $location);
        // 'sub' has no meaning on a top-level tab and must not be invented.
        $this->assertStringNotContainsString('sub=', $location);
    }

    // ------------------------------------------------------------------
    // Lost & Damaged: the new placement, end to end.
    // ------------------------------------------------------------------

    public function test_lost_damaged_is_a_real_placement_from_the_status_page_to_the_queue(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->installExtension();

        // Placed by the owner on the module's Status placement page, the
        // only way a status ever gets a placement (owner ruling, 2026-08-09:
        // core's Order Statuses page no longer offers one).
        $status = $this->statusPlaced('to_pack', 'Lost in Transit');
        $this->post(route('admin.fulfillment.settings.placement.update'), [
            'placements' => [$status->id => 'lost_damaged'],
        ])->assertRedirect();
        $this->assertSame('lost_damaged', PlacementMap::placementFor($status->id));

        $order = $this->makeOrder($status->fresh(), 'QX-LOST-1', 'in_transit');
        $this->assertSame('lost_damaged', FulfillmentService::tabBucket($order->fresh()));

        $this->get($this->queueUrl() . '?filter=lost_damaged')
            ->assertOk()
            ->assertSee('QX-LOST-1')
            ->assertSee('data-count-for="lost_damaged">1<', false);

        // And nowhere else.
        $this->get($this->queueUrl() . '?filter=failed')
            ->assertOk()
            ->assertDontSee('QX-LOST-1');
    }

    // ------------------------------------------------------------------
    // The setup notice.
    // ------------------------------------------------------------------

    public function test_settings_hub_shows_the_setup_notice_when_nothing_is_placed_under_to_handover(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        // A fresh install: the seeder ships no fulfillment statuses, so a
        // To-Pack-placed status alone is what a store starts with.
        $this->statusPlaced('to_pack', 'Processing');

        $this->get($this->settingsUrl())
            ->assertOk()
            ->assertSee('No order status is placed under To Handover, so booking is refused')
            // The notice points at the module's OWN placement page, not at
            // core's Order Statuses page (owner ruling, 2026-08-09).
            ->assertSee(route('admin.fulfillment.settings.placement'), false);
    }

    public function test_the_setup_notice_disappears_once_a_status_is_placed_under_to_handover(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->statusPlaced('to_handover', 'Order Being Shipped');

        $this->get($this->settingsUrl())
            ->assertOk()
            ->assertDontSee('No order status is placed under To Handover, so booking is refused');
    }
}
