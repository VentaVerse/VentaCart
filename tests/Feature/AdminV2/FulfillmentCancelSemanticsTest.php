<?php

namespace Tests\Feature\AdminV2;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderStatus;
use Extensions\CourierFulfillment\PlacementMap;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\ExtensionManager;
use Extensions\CourierFulfillment\Support\CourierWebhookIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * fulfillment-tabs-v3 Task 4: the spec's three cancel semantics.
 *
 *  (a) CANCEL ORDER - the real cancel. A new POST route sets the CORE order
 *      status to the module setting `cancel_order_status_id` (default: the
 *      status named "Canceled") through the same history + StockService path
 *      the admin order page uses (notify off). Offered on Unpaid rows (their
 *      ONLY action) and on To Pack rows (alongside Book). Refused while an
 *      active courier booking exists.
 *  (b) CANCEL BOOKING - when the courier confirms an admin-initiated cancel,
 *      the previous booking is snapshotted into courier_meta.booking_history
 *      (courier, tracking number, timeline, admin_cancelled, cancelled_at)
 *      and the live courier fields are RESET, returning the order to
 *      To Ship > To Pack. Rebooking afterwards is a fresh booking.
 *  (c) COURIER-SIDE EXCEPTION - a cancelled-phase status arriving via
 *      webhook/poll withOUT an admin-initiated cancel keeps the courier
 *      fields on the order and is recorded there. Where the order then
 *      APPEARS is not its business: fulfillment-placement Task 2 made tab
 *      membership read the core order status's placement and nothing else,
 *      so a courier-side cancel moves the row only when the store's status
 *      map moves the core status (and the tests below assert exactly that).
 *
 * The QuadX cancel-call contract itself (what is SENT to the courier) is
 * frozen by FulfillmentTest's own booking/cancel tests - nothing here (or in
 * the implementation) changes the outbound request.
 */
class FulfillmentCancelSemanticsTest extends TestCase
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
        // Registers the extension's runtime PSR-4 autoloader up front, so
        // this file can reference Extensions\CourierFulfillment\* (the
        // module-owned placement mapping) before its first HTTP request.
        app(ExtensionManager::class)->bootEnabled();
    }

    private function enableQuadxStaging(): void
    {
        Setting::create(['group' => 'courier', 'key' => 'quadx_enabled', 'value' => '1']);
        Setting::create(['group' => 'courier', 'key' => 'quadx_mode', 'value' => 'staging']);
        Setting::create(['group' => 'courier', 'key' => 'quadx_staging_api_key', 'value' => Crypt::encryptString('test-api-key')]);
        Setting::create(['group' => 'courier', 'key' => 'quadx_staging_secret_key', 'value' => Crypt::encryptString('test-secret-key')]);
    }

    private function defaultStatus(): OrderStatus
    {
        $status = OrderStatus::firstOrCreate(
            ['store_id' => 1, 'name' => 'Processing'],
            ['sort_order' => 1, 'is_default' => true],
        );

        // Placement is module data with NO default (owner ruling,
        // 2026-08-09): an unplaced status belongs to no tab, so this fixture
        // places its own default status under To Pack.
        PlacementMap::put($status->id, 'to_pack');

        return $status;
    }

    private function statusWithStage(string $stage, string $name, bool $deductsStock = false): OrderStatus
    {
        $status = OrderStatus::firstOrCreate(
            ['store_id' => 1, 'name' => $name],
            ['sort_order' => 1, 'deducts_stock' => $deductsStock],
        );

        PlacementMap::put($status->id, $stage);

        return $status;
    }

    private function makeOrder(?OrderStatus $status = null): Order
    {
        return Order::create([
            'store_id' => 1,
            'order_status_id' => ($status ?? $this->defaultStatus())->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'shipping_first_name' => 'Jane',
            'shipping_last_name' => 'Doe',
            'shipping_address_1' => '123 Mango St',
            'shipping_city' => 'Cebu City',
            'shipping_state' => 'Cebu',
            'shipping_postcode' => '6000',
            'shipping_phone' => '+639171234567',
            'payment_method' => 'bank_transfer',
            'subtotal' => 1000,
            'shipping_cost' => 100,
            'total' => 1100,
        ]);
    }

    // ------------------------------------------------------------------
    // (a) Cancel order: the Unpaid row's ONLY action.
    // ------------------------------------------------------------------

    public function test_unpaid_row_renders_exactly_one_action_the_cancel_order_button(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        $unpaidStatus = $this->statusWithStage('unpaid', 'Unpaid');
        $order = $this->makeOrder($unpaidStatus);

        $response = $this->get($this->queueUrl() . '?filter=unpaid');
        $response->assertOk()
            ->assertSee('#' . $order->id, false)
            ->assertSee('Cancel order')
            // No Book, no Tracking/drawer opener, no Print waybill, no
            // courier-booking Cancel - the spec's "exactly ONE action".
            ->assertDontSee('openBooking(JSON.parse(', false)
            ->assertDontSee('openDrawer(JSON.parse(', false)
            ->assertDontSee('Print waybill')
            ->assertDontSee('cancel-fulfillment-' . $order->id, false);

        // Exactly one cancel-order opener on the page (one row, one button).
        $this->assertSame(
            1,
            substr_count($response->getContent(), "\$dispatch('v3-confirm', 'cancel-order-{$order->id}')")
        );
    }

    public function test_to_pack_row_offers_cancel_order_alongside_book(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        $order = $this->makeOrder();

        $response = $this->get($this->queueUrl() . '?filter=to_ship&sub=to_pack');
        $response->assertOk()
            ->assertSee('#' . $order->id, false)
            ->assertSee('openBooking(JSON.parse(', false)
            ->assertSee('Cancel order')
            ->assertSee("cancel-order-{$order->id}", false);
    }

    public function test_cancel_order_moves_status_writes_history_returns_stock_and_lands_view_only_in_cancelled(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        // Default target: the status NAMED "Canceled" (no setting stored).
        $canceled = $this->statusWithStage('cancelled', 'Canceled', deductsStock: false);

        $product = Product::create([
            'store_id' => 1,
            'name' => 'Guitar Strap',
            'slug' => 'guitar-strap',
            'price' => 500,
            'quantity' => 10,
            'track_stock' => true,
        ]);

        $order = $this->makeOrder();
        OrderProduct::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'price' => 500,
            'quantity' => 2,
            'total' => 1000,
        ]);
        // The order's stock was already taken (the state a paid/processing
        // order is in) - cancelling must give it back via StockService.
        $order->update(['stock_adjusted' => true]);

        $response = $this->post($this->queueUrl() . '/' . $order->id . '/cancel-order');
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertSame($canceled->id, $order->order_status_id);

        // History entry through the same path the order page uses - notify OFF.
        $this->assertDatabaseHas('order_histories', [
            'order_id' => $order->id,
            'order_status_id' => $canceled->id,
            'notify_customer' => false,
        ]);

        // StockService::adjustForStatusChange() ran: reaching a non-deducting
        // status reverses the deduction, so the two units came back and the
        // adjusted flag cleared.
        $this->assertSame(12, (int) $product->fresh()->quantity);
        $this->assertFalse((bool) $order->stock_adjusted);

        // The order now lives in the Cancelled tab as a VIEW-ONLY row: order
        // link only, no fulfillment actions of any kind.
        $cancelledTab = $this->get($this->queueUrl() . '?filter=cancelled');
        $cancelledTab->assertOk()
            ->assertSee('#' . $order->id, false)
            ->assertDontSee('openBooking(JSON.parse(', false)
            ->assertDontSee('openDrawer(JSON.parse(', false)
            ->assertDontSee('Print waybill')
            ->assertDontSee("cancel-order-{$order->id}", false)
            ->assertDontSee('cancel-fulfillment-' . $order->id, false);

        // And it left To Pack.
        $this->get($this->queueUrl() . '?filter=to_ship&sub=to_pack')
            ->assertOk()
            ->assertDontSee('#' . $order->id, false);
    }

    public function test_cancel_order_uses_the_configured_cancel_status_setting(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->statusWithStage('cancelled', 'Canceled'); // the default would be this...
        $void = $this->statusWithStage('cancelled', 'Voided');
        Setting::create(['group' => 'courier', 'key' => 'cancel_order_status_id', 'value' => (string) $void->id]);

        $order = $this->makeOrder();

        $this->post($this->queueUrl() . '/' . $order->id . '/cancel-order')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame($void->id, $order->fresh()->order_status_id);
    }

    public function test_cancel_order_refuses_while_a_booking_is_active_and_changes_nothing(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();
        $this->statusWithStage('cancelled', 'Canceled');

        Http::fake(); // any outbound call here would itself be a bug

        $order = $this->makeOrder();
        $order->forceFill([
            'courier_provider' => 'quadx',
            'courier_tracking_number' => 'QX-ACTIVE-001',
            'courier_status' => 'for_pickup',
        ])->save();
        $statusBefore = $order->order_status_id;

        $response = $this->post($this->queueUrl() . '/' . $order->id . '/cancel-order');
        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('booking', strtolower(session('error')));

        $order->refresh();
        $this->assertSame($statusBefore, $order->order_status_id);
        $this->assertSame('QX-ACTIVE-001', $order->courier_tracking_number);
        $this->assertDatabaseCount('order_histories', 0);
        Http::assertNothingSent();
    }

    // ------------------------------------------------------------------
    // Hub settings field: save + validation.
    // ------------------------------------------------------------------

    public function test_hub_renders_the_cancel_status_select_with_a_use_default_placeholder_selected_when_unset(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $canceled = $this->statusWithStage('cancelled', 'Canceled');

        // Fix-round minor: with NO stored setting the placeholder is what's
        // selected — never a concrete status the admin did not choose (the
        // old pre-selected default silently persisted on first hub save).
        $this->get($this->settingsUrl())
            ->assertOk()
            ->assertSee('name="settings[cancel_order_status_id]"', false)
            ->assertSee('<option value="" selected', false)
            ->assertSee('Use default (Canceled)')
            ->assertDontSee('<option value="' . $canceled->id . '" selected', false);
    }

    public function test_hub_save_with_the_placeholder_never_persists_a_status_and_clears_an_existing_override(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $void = $this->statusWithStage('cancelled', 'Voided');
        $this->get($this->settingsUrl())->assertOk(); // prime routes

        // Saving with the placeholder ("" -> null) stores nothing.
        $this->post($this->settingsUrl(), [
            'settings' => ['cancel_order_status_id' => ''],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('settings', ['group' => 'courier', 'key' => 'cancel_order_status_id']);

        // An explicit choice stores; re-saving the placeholder clears it.
        $this->post($this->settingsUrl(), ['settings' => ['cancel_order_status_id' => (string) $void->id]]);
        $this->assertDatabaseHas('settings', ['group' => 'courier', 'key' => 'cancel_order_status_id', 'value' => (string) $void->id]);

        $this->post($this->settingsUrl(), ['settings' => ['cancel_order_status_id' => '']]);
        $this->assertDatabaseMissing('settings', ['group' => 'courier', 'key' => 'cancel_order_status_id']);
    }

    public function test_hub_saves_a_valid_cancel_status_id_and_rejects_an_unknown_one(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $void = $this->statusWithStage('cancelled', 'Voided');

        // Prime the routes (registered on the first request after install).
        $this->get($this->settingsUrl())->assertOk();

        $this->post($this->settingsUrl(), [
            'settings' => ['cancel_order_status_id' => (string) $void->id],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('settings', [
            'group' => 'courier',
            'key' => 'cancel_order_status_id',
            'value' => (string) $void->id,
        ]);

        // An id that is not an order status must be rejected, not stored.
        $this->post($this->settingsUrl(), [
            'settings' => ['cancel_order_status_id' => '999999'],
        ])->assertSessionHasErrors('settings.cancel_order_status_id');

        $this->assertDatabaseMissing('settings', [
            'group' => 'courier',
            'key' => 'cancel_order_status_id',
            'value' => '999999',
        ]);
    }

    // ------------------------------------------------------------------
    // (b) Cancel booking: courier-confirmed cancel resets the courier
    // fields, snapshots into booking_history, returns the order to To Pack,
    // and a rebook afterwards is a fresh booking.
    // ------------------------------------------------------------------

    public function test_cancel_booking_resets_courier_fields_snapshots_history_and_returns_the_order_to_to_pack(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        Http::fake([
            'api.staging.quadx.xyz/v2/orders/*' => Http::response(['ok' => true], 200), // cancel
        ]);

        $timeline = [['status' => 'for_pickup', 'at' => 1754600000]];
        // fulfillment-placement Task 3: a booked order sits on a
        // To-Handover-placed status (booking put it there), and cancelling
        // the booking is what brings it back. defaultStatus() ('Processing',
        // To Pack placed) is created here so there is somewhere to return to.
        $this->defaultStatus();
        $order = $this->makeOrder($this->statusWithStage('to_handover', 'Order Being Shipped'));
        $order->forceFill([
            'courier_provider' => 'quadx',
            'courier_tracking_number' => 'QX-RESET-001',
            'courier_status' => 'for_pickup',
            'courier_meta' => json_encode(['timeline' => $timeline, 'booked_response' => ['id' => 555]]),
            'tracking_number' => 'QX-RESET-001',
        ])->save();

        $this->post($this->queueUrl() . '/' . $order->id . '/cancel')
            ->assertRedirect()
            ->assertSessionHas('success');

        $order->refresh();

        // Live courier fields reset - the order reads as never booked.
        $this->assertNull($order->courier_provider);
        $this->assertNull($order->courier_status);
        $this->assertNull($order->courier_tracking_number);
        // Core snapshot column no longer points at a voided AWB.
        $this->assertNull($order->tracking_number);

        // The previous booking survives as a complete snapshot.
        $meta = json_decode($order->courier_meta, true);
        $this->assertSame(['booking_history'], array_keys($meta));
        $this->assertCount(1, $meta['booking_history']);
        $snapshot = $meta['booking_history'][0];
        $this->assertSame('quadx', $snapshot['courier']);
        $this->assertSame('QX-RESET-001', $snapshot['tracking_number']);
        $this->assertSame('for_pickup', $snapshot['status']);
        $this->assertSame($timeline, $snapshot['timeline']);
        $this->assertTrue($snapshot['admin_cancelled']);
        $this->assertNotEmpty($snapshot['cancelled_at']);
        $this->assertSame(555, $snapshot['meta']['booked_response']['id']);

        // Audit trail on the order itself. fulfillment-placement Task 3: the
        // return to To Pack is now a real CORE STATUS change (the order came
        // from To Handover), so the history line names the status it landed
        // on rather than claiming a move that never happened.
        $this->assertDatabaseHas('order_histories', [
            'order_id' => $order->id,
            'comment' => 'Courier booking cancelled: QX-RESET-001 (QuadX). Order status set to Processing, back under To Ship, To Pack.',
            'notify_customer' => false,
        ]);
        $this->assertSame('to_pack', \Extensions\CourierFulfillment\FulfillmentService::tabBucket($order));

        // Back in To Ship > To Pack, and NOT a Cancelled-tab exception.
        $this->get($this->queueUrl() . '?filter=to_ship&sub=to_pack')
            ->assertOk()
            ->assertSee('#' . $order->id, false)
            ->assertSee('data-count-for="to_pack">1<', false);
        $this->get($this->queueUrl() . '?filter=cancelled')
            ->assertOk()
            ->assertSee('data-count-for="cancelled">0<', false)
            ->assertDontSee('#' . $order->id, false);
    }

    public function test_rebooking_after_a_reset_is_a_fresh_booking_that_keeps_the_booking_history(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        Http::fake([
            'api.staging.quadx.xyz/v2/orders' => Http::response(['tracking_number' => 'QX-FRESH-002', 'status' => 'for_pickup'], 200),
        ]);

        // fulfillment-placement Task 3: the rebook below moves the order onto
        // a To-Handover-placed status, so the store needs one (booking is
        // refused otherwise - see FulfillmentService::bookedStatus()).
        $this->statusWithStage('to_handover', 'Order Being Shipped');

        $order = $this->makeOrder();
        // The exact post-reset state the test above proves cancel() writes.
        $order->forceFill([
            'courier_meta' => json_encode(['booking_history' => [[
                'courier' => 'quadx',
                'tracking_number' => 'QX-RESET-001',
                'status' => 'for_pickup',
                'timeline' => [],
                'meta' => [],
                'admin_cancelled' => true,
                'cancelled_at' => '2026-08-08T10:00:00+08:00',
            ]]]),
        ])->save();

        $this->post($this->queueUrl() . '/' . $order->id . '/book', [
            'courier' => 'quadx',
            'service' => 'next_day',
            'parcel' => 'box',
            'shipping_payment' => 'net_off',
        ])->assertRedirect()->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('QX-FRESH-002', $order->courier_tracking_number);
        $this->assertSame('for_pickup', $order->courier_status);

        $meta = json_decode($order->courier_meta, true);
        // Fresh booking: no previous tracking carried, no rebook history line.
        $this->assertNull($meta['previous_tracking_number']);
        $this->assertDatabaseMissing('order_histories', [
            'order_id' => $order->id,
            'comment' => 'Rebooked with courier: QX-RESET-001 superseded by QX-FRESH-002',
        ]);
        // The audit snapshot survives the rebook.
        $this->assertCount(1, $meta['booking_history']);
        $this->assertSame('QX-RESET-001', $meta['booking_history'][0]['tracking_number']);

        Http::assertSentCount(1);
    }

    // ------------------------------------------------------------------
    // (c) Courier-side exception: a cancelled-phase status arriving via
    // webhook withOUT an admin cancel keeps the courier fields and surfaces
    // in Cancelled with Rebook + Tracking.
    // ------------------------------------------------------------------

    /**
     * fulfillment-placement Task 2 REWRITE (was: "...lands in cancelled with
     * rebook and tracking"). The old premise was the tabs-v3 rule that a
     * courier-side cancel status put the order in the Cancelled TAB by
     * itself. It no longer does: the ingest keeps its real job (record the
     * courier state, apply the store's status map), and the tab follows the
     * CORE status - here unmapped, so the order stays exactly where it was,
     * which is the honest answer for a store that has not told Venta what a
     * '7001' means to its own workflow.
     */
    public function test_courier_side_7001_without_admin_cancel_records_the_state_and_leaves_the_tab_to_the_core_status(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        app(ExtensionManager::class)->bootEnabled();
        $this->enableQuadxStaging();

        $order = $this->makeOrder();
        $order->forceFill([
            'courier_provider' => 'spx',
            'courier_tracking_number' => 'SPXPH000000000001',
            'courier_status' => '0',
        ])->save();

        // The exact ingest path the SPX webhook/poll uses.
        CourierWebhookIngestor::ingest('spx', [[
            'reference_id' => (string) $order->id,
            'tracking_number' => 'SPXPH000000000001',
            'status' => '7001',
            'occurred_at' => now()->toIso8601String(),
            'remarks' => null,
        ]]);

        $order->refresh();
        // Courier fields STAY - this is an exception, not an admin reset.
        $this->assertSame('spx', $order->courier_provider);
        $this->assertSame('7001', $order->courier_status);
        $this->assertSame('SPXPH000000000001', $order->courier_tracking_number);
        // No status map is configured for '7001', so the CORE status did not
        // move, and neither did the order's tab.
        $this->assertSame('to_pack', \Extensions\CourierFulfillment\FulfillmentService::tabBucket($order));

        $this->get($this->queueUrl() . '?filter=cancelled')
            ->assertOk()
            ->assertSee('data-count-for="cancelled">0<', false)
            ->assertDontSee('SPXPH000000000001');

        // It sits in To Pack, where its cancelled booking is not live, so
        // booking again is offered and would in fact succeed.
        $this->get($this->queueUrl() . '?filter=to_ship&sub=to_pack')
            ->assertOk()
            ->assertSee('SPXPH000000000001')
            ->assertSee('openBooking(JSON.parse(', false);
    }

    // ------------------------------------------------------------------
    // fulfillment-placement Task 2 REWRITE (was: "actively booked unpaid
    // order buckets by courier phase not unpaid", the tabs-v3 owner ruling
    // on stage-vs-phase precedence). There is no precedence any more,
    // because there is no second input: an Unpaid-placed order is under
    // Unpaid, booked or not, and the tally and the per-row rule agree
    // because they read the same column. The invariant the old carve-out
    // existed to protect (an unpaid order is never quietly booked) is now
    // held by the booking gate instead, asserted below.
    // ------------------------------------------------------------------

    public function test_unpaid_placed_orders_stay_under_unpaid_whatever_their_courier_state(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        app(ExtensionManager::class)->bootEnabled();
        $this->enableQuadxStaging();

        $unpaidStatus = $this->statusWithStage('unpaid', 'Unpaid');

        $plainUnpaid = $this->makeOrder($unpaidStatus);
        $unpaidHandover = $this->makeOrder($unpaidStatus);
        $unpaidHandover->forceFill([
            'courier_provider' => 'quadx',
            'courier_tracking_number' => 'QX-UNPAID-H1',
            'courier_status' => 'for_pickup',
        ])->save();
        $unpaidTransit = $this->makeOrder($unpaidStatus);
        $unpaidTransit->forceFill([
            'courier_provider' => 'quadx',
            'courier_tracking_number' => 'QX-UNPAID-T1',
            'courier_status' => 'in_transit',
        ])->save();

        // Per-row rule: the placement, three times.
        foreach ([$plainUnpaid, $unpaidHandover, $unpaidTransit] as $order) {
            $this->assertSame('unpaid', \Extensions\CourierFulfillment\FulfillmentService::tabBucket($order));
        }

        // Tally agrees with the per-row rule (same column, one query).
        $response = $this->get($this->queueUrl());
        $response->assertOk()
            ->assertSee('data-count-for="unpaid">3<', false)
            ->assertSee('data-count-for="to_handover">0<', false)
            ->assertSee('data-count-for="shipping">0<', false);

        // All three list under Unpaid, and none of them anywhere else.
        $unpaidTab = $this->get($this->queueUrl() . '?filter=unpaid');
        $unpaidTab->assertOk()
            ->assertSee('#' . $plainUnpaid->id, false)
            ->assertSee('QX-UNPAID-H1')
            ->assertSee('QX-UNPAID-T1')
            // Booking stays a To Pack action, so no row here offers it -
            // the invariant this tab exists to protect.
            ->assertDontSee('openBooking(JSON.parse(', false)
            // The two live parcels carry their own buttons, wherever their
            // core status sits (owner ruling, fix round: courier actions
            // key on courier data, not on the tab). This is the 29850 dead
            // end: an Unpaid-placed order holding a real AWB used to render
            // an empty actions cell.
            ->assertSee('Print waybill')
            ->assertSee('openDrawer(JSON.parse(', false)
            ->assertSee('/sales/fulfillment/' . $unpaidHandover->id . '/label', false)
            ->assertSee('/sales/fulfillment/' . $unpaidTransit->id . '/label', false)
            // The parcel-less row gains nothing from its neighbours, and no
            // row here offers Cancel booking (To Handover only).
            ->assertDontSee('/sales/fulfillment/' . $plainUnpaid->id . '/label', false)
            ->assertDontSee("cancel-fulfillment-{$unpaidHandover->id}", false)
            ->assertDontSee("cancel-fulfillment-{$unpaidTransit->id}", false);

        $this->get($this->queueUrl() . '?filter=to_ship&sub=to_handover')
            ->assertOk()
            ->assertDontSee('QX-UNPAID-H1');
        $this->get($this->queueUrl() . '?filter=shipping')
            ->assertOk()
            ->assertDontSee('QX-UNPAID-T1');
    }

    public function test_unpaid_order_with_a_cancelled_booking_stays_under_unpaid(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        app(ExtensionManager::class)->bootEnabled();

        $unpaidStatus = $this->statusWithStage('unpaid', 'Unpaid');
        $order = $this->makeOrder($unpaidStatus);
        // A booking that is no longer live (courier-cancelled) is not an
        // ACTIVE booking — unpaid still claims the order.
        $order->forceFill([
            'courier_provider' => 'quadx',
            'courier_tracking_number' => 'QX-UNPAID-C1',
            'courier_status' => 'canceled',
        ])->save();

        $this->assertSame('unpaid', \Extensions\CourierFulfillment\FulfillmentService::tabBucket($order));

        $this->get($this->queueUrl() . '?filter=unpaid')
            ->assertOk()
            ->assertSee('data-count-for="unpaid">1<', false)
            ->assertSee('#' . $order->id, false);
        $this->get($this->queueUrl() . '?filter=cancelled')
            ->assertOk()
            ->assertSee('data-count-for="cancelled">0<', false)
            ->assertDontSee('#' . $order->id, false);
    }

    // ------------------------------------------------------------------
    // The server-side book gate. fulfillment-placement Task 2 widened it
    // from "not unpaid, not cancelled" to "To Pack only" (the full matrix
    // lives in FulfillmentPlacementMembershipTest); these two keep the
    // unpaid and cancelled cases asserted here, next to the cancel
    // semantics they belong to, with the message the gate now gives.
    // ------------------------------------------------------------------

    public function test_book_route_refuses_an_unpaid_placed_order_with_no_outbound_call_and_no_mutation(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        Http::fake(); // any outbound call would itself be a bug

        $unpaidStatus = $this->statusWithStage('unpaid', 'Unpaid');
        $order = $this->makeOrder($unpaidStatus);

        $response = $this->post($this->queueUrl() . '/' . $order->id . '/book', [
            'courier' => 'quadx',
            'service' => 'next_day',
            'parcel' => 'box',
            'shipping_payment' => 'net_off',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Unpaid', session('error'));
        $this->assertStringContainsString('Set its status to one placed under To Pack before booking.', session('error'));

        Http::assertNothingSent();
        $order->refresh();
        $this->assertNull($order->courier_provider);
        $this->assertNull($order->courier_tracking_number);
        $this->assertNull($order->courier_status);
    }

    public function test_book_route_refuses_a_cancelled_placed_order(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        Http::fake();

        $cancelledStatus = $this->statusWithStage('cancelled', 'Canceled');
        $order = $this->makeOrder($cancelledStatus);

        $response = $this->post($this->queueUrl() . '/' . $order->id . '/book', [
            'courier' => 'quadx',
            'service' => 'next_day',
            'parcel' => 'box',
            'shipping_payment' => 'net_off',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Cancelled', session('error'));
        $this->assertStringContainsString('Set its status to one placed under To Pack before booking.', session('error'));

        Http::assertNothingSent();
        $this->assertNull($order->fresh()->courier_tracking_number);
    }

    public function test_estimate_route_refuses_unpaid_and_cancelled_placed_orders(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        Http::fake();

        $unpaid = $this->makeOrder($this->statusWithStage('unpaid', 'Unpaid'));
        $cancelled = $this->makeOrder($this->statusWithStage('cancelled', 'Canceled'));

        $r1 = $this->postJson($this->queueUrl() . '/' . $unpaid->id . '/estimate', ['courier' => 'quadx']);
        $r1->assertOk()->assertJson(['ok' => false]);
        $this->assertStringContainsString('Unpaid', $r1->json('message'));
        $this->assertStringContainsString('Set its status to one placed under To Pack before booking.', $r1->json('message'));

        $r2 = $this->postJson($this->queueUrl() . '/' . $cancelled->id . '/estimate', ['courier' => 'quadx']);
        $r2->assertOk()->assertJson(['ok' => false]);
        $this->assertStringContainsString('Cancelled', $r2->json('message'));

        Http::assertNothingSent();
    }
    // ------------------------------------------------------------------
    // fulfillment-placement Task 2 REWRITE (was: the tabs-v3 F1 "coherent
    // rule" block, four tests asserting that a stage-claimed row KEEPS its
    // courier-appropriate actions - a per-row action matrix driven by
    // courier state). Actions are hardcoded per TAB now: Cancelled offers
    // none at all, whatever the shipment underneath is doing, and Unpaid
    // offers exactly Cancel order. The four courier states the old tests
    // enumerated are kept here, asserting the new rule on each of them so
    // the coverage is not lost, only re-pointed.
    // ------------------------------------------------------------------

    public function test_a_cancelled_placed_row_offers_no_workflow_actions_in_any_courier_state(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        app(ExtensionManager::class)->bootEnabled();
        $this->enableQuadxStaging();

        $cancelledStatus = $this->statusWithStage('cancelled', 'Canceled');

        // The four states the old F1 matrix enumerated, on one page.
        $liveBooking = $this->makeOrder($cancelledStatus);
        $liveBooking->forceFill([
            'courier_provider' => 'quadx',
            'courier_tracking_number' => 'QX-CXLSTAGE-A1',
            'courier_status' => 'for_pickup', // cancelable per QuadXFulfillment
        ])->save();

        $creating = $this->makeOrder($cancelledStatus);
        $creating->forceFill([
            'courier_provider' => 'spx',
            'courier_status' => 'creating',
            'courier_tracking_number' => null,
        ])->save();

        $courierCancelled = $this->makeOrder($cancelledStatus);
        $courierCancelled->forceFill([
            'courier_provider' => 'quadx',
            'courier_tracking_number' => 'QX-CXLSTAGE-C1',
            'courier_status' => 'canceled',
        ])->save();

        $noCourierState = $this->makeOrder($cancelledStatus);

        $response = $this->get($this->queueUrl() . '?filter=cancelled');
        $response->assertOk()
            ->assertSee('data-count-for="cancelled">4<', false)
            ->assertSee('QX-CXLSTAGE-A1')
            ->assertSee('QX-CXLSTAGE-C1')
            // Not one WORKFLOW action on any of the four rows. Tracking is
            // a different thing: the owner's 2026-08-09 ruling renders it on
            // every tab except Unpaid whenever the row carries courier
            // state, so the three rows that do get a drawer opener. Print
            // waybill needs a LIVE booking on top (a cancelled shipment has
            // no printable AWB), so only the first row gets one.
            ->assertDontSee('openBooking(JSON.parse(', false)
            ->assertDontSee('Cancel order');
        // "Cancel booking" is deliberately NOT asserted by label: the
        // drawer's own static markup carries that string on every page
        // regardless of any row. The per-order dispatch/dialog names below
        // are the assertion that no ROW offers it.

        foreach ([$liveBooking, $creating, $courierCancelled, $noCourierState] as $order) {
            $response->assertDontSee("cancel-order-{$order->id}", false)
                ->assertDontSee("cancel-fulfillment-{$order->id}", false);
        }

        // A real parcel always carries its own buttons: only the live
        // booking gets a waybill link. The cancelled one, the mid-create one
        // and the courier-less one do not.
        $response->assertSee('/sales/fulfillment/' . $liveBooking->id . '/label', false);
        foreach ([$creating, $courierCancelled, $noCourierState] as $order) {
            $response->assertDontSee('/sales/fulfillment/' . $order->id . '/label', false);
        }

        // The row-level "book again" hint (co-actions__hint) was removed on
        // owner directive (2026-08-09) - none of the four rows ever renders it.
        $response->assertDontSee('co-actions__hint', false);
    }

    /**
     * The one consequence worth stating plainly: a cancelled-placed order
     * whose parcel is still live can no longer be stopped from the queue,
     * because Cancelled has no actions. The order's status is the lever -
     * move it back under To Ship and the booking actions return with it.
     */
    public function test_moving_a_cancelled_placed_row_back_under_to_ship_restores_its_booking_actions(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        app(ExtensionManager::class)->bootEnabled();
        $this->enableQuadxStaging();

        $order = $this->makeOrder($this->statusWithStage('cancelled', 'Canceled'));
        $order->forceFill([
            'courier_provider' => 'quadx',
            'courier_tracking_number' => 'QX-CXLSTAGE-A2',
            'courier_status' => 'for_pickup',
        ])->save();

        $order->forceFill(['order_status_id' => $this->statusWithStage('to_handover', 'Order Being Shipped')->id])->save();

        $this->get($this->queueUrl() . '?filter=to_ship&sub=to_handover')
            ->assertOk()
            ->assertSee('QX-CXLSTAGE-A2')
            ->assertSee('Print waybill')
            ->assertSee('openDrawer(JSON.parse(', false)
            ->assertSee("cancel-fulfillment-{$order->id}", false);
    }

    public function test_an_unpaid_placed_creation_failed_row_keeps_its_single_cancel_order_action(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        app(ExtensionManager::class)->bootEnabled();
        $this->enableQuadxStaging();

        $order = $this->makeOrder($this->statusWithStage('unpaid', 'Unpaid'));
        // A permanently failed async create on an unpaid-placed order. Under
        // tabs v3 the 'failed' courier phase pulled it into the Failed tab;
        // now its status keeps it under Unpaid, whose single action works
        // (nothing is booked, so cancelOrder() accepts it).
        $order->forceFill([
            'courier_provider' => 'spx',
            'courier_status' => 'creation_failed',
            'courier_tracking_number' => null,
            'courier_meta' => json_encode(['creation_error' => 'SPX rejected the delivery address']),
        ])->save();

        $this->get($this->queueUrl() . '?filter=failed')
            ->assertOk()
            ->assertDontSee('#' . $order->id, false);

        $this->get($this->queueUrl() . '?filter=unpaid')
            ->assertOk()
            ->assertSee('#' . $order->id, false)
            // The failure reason still reads on the row itself.
            ->assertSee('SPX rejected the delivery address')
            ->assertSee("cancel-order-{$order->id}", false)
            ->assertDontSee('openBooking(JSON.parse(', false)
            // Its one WORKFLOW action is still Cancel order. The row DOES
            // open a Details drawer, because a failed create is courier
            // data and courier actions key on the data rather than the tab
            // (owner ruling, fix round) - nothing about it is bookable or
            // printable, so no Book and no waybill.
            ->assertSee('openDrawer(JSON.parse(', false)
            ->assertDontSee('/sales/fulfillment/' . $order->id . '/label', false);
    }

    // ------------------------------------------------------------------
    // The whole chain the spec describes, end to end and in one test: a
    // courier status arrives, the store's status map turns it into a CORE
    // order status (with that status's stock rule), and THAT status's
    // placement decides the tab. The courier never touches the tab itself.
    // The status-map DATA is unchanged by this task.
    // ------------------------------------------------------------------

    public function test_quadx_courier_side_cancel_webhook_maps_to_a_cancelled_placed_status_restocks_and_moves_the_tab(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();
        Setting::create(['group' => 'courier', 'key' => 'quadx_webhook_token', 'value' => 'qx-cancel-hook-token']);

        // The map the settings page's smart default proposes: courier
        // 'canceled' -> the status named "Canceled" (cancelled stage,
        // non-deducting, so entering it returns the stock).
        $canceled = $this->statusWithStage('cancelled', 'Canceled', deductsStock: false);
        Setting::create(['group' => 'courier', 'key' => 'quadx_status_map', 'value' => json_encode([
            'canceled' => ['order_status_id' => $canceled->id, 'notify' => false],
        ])]);

        $product = Product::create([
            'store_id' => 1,
            'name' => 'Guitar Strap',
            'slug' => 'guitar-strap',
            'price' => 500,
            'quantity' => 10,
            'track_stock' => true,
        ]);

        $order = $this->makeOrder();
        OrderProduct::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'price' => 500,
            'quantity' => 2,
            'total' => 1000,
        ]);
        $order->update(['stock_adjusted' => true]);
        $order->forceFill([
            'courier_provider' => 'quadx',
            'courier_tracking_number' => 'QX-COURIER-CXL1',
            'courier_status' => 'for_pickup',
        ])->save();

        // The courier-side cancel arrives via the real webhook path.
        $this->postJson('/webhooks/courier/quadx?token=qx-cancel-hook-token', [
            'data' => [[
                'reference_id' => (string) $order->id,
                'tracking_number' => 'QX-COURIER-CXL1',
                'status' => 'canceled',
                'status_updated_at' => now()->toIso8601String(),
            ]],
        ])->assertOk()->assertJson(['ok' => true]);

        $order->refresh();
        // The map applied: core status moved to the cancelled stage and the
        // mapped status restocked the two units. Courier state STAYS.
        $this->assertSame($canceled->id, $order->order_status_id);
        $this->assertSame('cancelled', PlacementMap::placementFor($order->order_status_id));
        $this->assertSame('cancelled', \Extensions\CourierFulfillment\FulfillmentService::tabBucket($order));
        $this->assertSame('canceled', $order->courier_status);
        $this->assertSame('QX-COURIER-CXL1', $order->courier_tracking_number);
        $this->assertSame(12, (int) $product->fresh()->quantity);
        $this->assertFalse((bool) $order->stock_adjusted);

        // The order moved tabs because its CORE STATUS moved, and the
        // Cancelled tab it landed in offers no workflow action (plus
        // whatever its own parcel state still supports).
        $response = $this->get($this->queueUrl() . '?filter=cancelled');
        $response->assertOk()
            ->assertSee('data-count-for="cancelled">1<', false)
            ->assertSee('QX-COURIER-CXL1')
            ->assertDontSee('co-actions__hint', false)
            // Courier state is still on the order, so Tracking comes with
            // it; the booking is cancelled, so the waybill does not, and no
            // workflow action does either.
            ->assertSee('openDrawer(JSON.parse(', false)
            ->assertDontSee('/sales/fulfillment/' . $order->id . '/label', false)
            ->assertDontSee('openBooking(JSON.parse(', false)
            ->assertDontSee("cancel-fulfillment-{$order->id}", false);

        // And it left To Pack, where it sat before the webhook arrived.
        $this->get($this->queueUrl() . '?filter=to_ship&sub=to_pack')
            ->assertOk()
            ->assertSee('data-count-for="to_pack">0<', false)
            ->assertDontSee('QX-COURIER-CXL1');
    }

    // ------------------------------------------------------------------
    // FINAL-review F3: the stage gate blocks only genuinely NEW bookings -
    // the idempotent branches (already booked, async create in flight)
    // answer first, so an ERP retry for an already-booked order whose stage
    // regressed still reads already_booked, never placement_blocked.
    // ------------------------------------------------------------------

    public function test_book_answers_already_booked_for_an_actively_booked_order_even_on_a_regressed_stage(): void
    {
        $this->installExtension();
        $this->enableQuadxStaging();
        Http::fake(); // an outbound call would itself be a bug

        $unpaidStatus = $this->statusWithStage('unpaid', 'Unpaid');
        $order = $this->makeOrder($unpaidStatus);
        $order->forceFill([
            'courier_provider' => 'quadx',
            'courier_tracking_number' => 'QX-RETRY-001',
            'courier_status' => 'for_pickup',
        ])->save();

        $res = app(\Extensions\CourierFulfillment\FulfillmentService::class)->book($order, [
            'courier' => 'quadx',
            'service' => 'next_day',
            'parcel' => 'box',
            'shipping_payment' => 'net_off',
        ]);

        $this->assertTrue($res['success']);
        $this->assertTrue($res['already_booked']);
        $this->assertSame('QX-RETRY-001', $res['tracking_number']);
        $this->assertArrayNotHasKey('code', $res);
        Http::assertNothingSent();
    }

    public function test_book_answers_creation_in_progress_not_placement_blocked_for_a_mid_create_order_on_a_regressed_placement(): void
    {
        $this->installExtension();
        $this->enableQuadxStaging();
        Http::fake();

        $unpaidStatus = $this->statusWithStage('unpaid', 'Unpaid');
        $order = $this->makeOrder($unpaidStatus);
        $order->forceFill([
            'courier_provider' => 'spx',
            'courier_status' => 'creating',
            'courier_tracking_number' => null,
        ])->save();

        $res = app(\Extensions\CourierFulfillment\FulfillmentService::class)->book($order, [
            'courier' => 'quadx',
            'service' => 'next_day',
            'parcel' => 'box',
            'shipping_payment' => 'net_off',
        ]);

        $this->assertFalse($res['success']);
        $this->assertSame('creation_in_progress', $res['code']);
        Http::assertNothingSent();
    }

    // ------------------------------------------------------------------
    // FINAL-review F5: the hub's cancel-order-status select offers ONLY
    // cancelled-stage statuses (the only coherent targets), and the save
    // validation enforces the same scope.
    // ------------------------------------------------------------------

    public function test_hub_cancel_status_select_lists_only_cancelled_stage_statuses(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $canceled = $this->statusWithStage('cancelled', 'Canceled');
        $void = $this->statusWithStage('cancelled', 'Voided');
        // The negative case is a DELIVERED-placed status on purpose:
        // fulfillment-placement Task 3 added two more selects to this same
        // hub (booked = To Handover placed, unbooked = To Pack placed), so a
        // To-Pack-placed status now legitimately appears elsewhere on the
        // page. 'delivered' is offered by no select at all, which is what
        // keeps this an honest assertion about scoping.
        $this->statusWithStage('delivered', 'DeliveredStageStatus');

        $this->get($this->settingsUrl())
            ->assertOk()
            ->assertSee('Use default (Canceled)')
            ->assertSee('<option value="' . $canceled->id . '"', false)
            ->assertSee('<option value="' . $void->id . '"', false)
            ->assertDontSee('DeliveredStageStatus');
    }

    public function test_hub_save_rejects_a_non_cancelled_stage_status_id(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->statusWithStage('cancelled', 'Canceled');
        $ready = $this->statusWithStage('to_pack', 'ReadyStageStatus');

        $this->get($this->settingsUrl())->assertOk(); // prime routes

        $this->post($this->settingsUrl(), [
            'settings' => ['cancel_order_status_id' => (string) $ready->id],
        ])->assertSessionHasErrors('settings.cancel_order_status_id');

        $this->assertDatabaseMissing('settings', [
            'group' => 'courier',
            'key' => 'cancel_order_status_id',
            'value' => (string) $ready->id,
        ]);
    }

    // ------------------------------------------------------------------
    // After a reset the tracking-number-dependent routes must degrade
    // gracefully (friendly flash / 404), never 500. The cached label PDF of
    // the old tracking number stays on disk harmlessly - nothing links to
    // it anymore.
    // ------------------------------------------------------------------

    public function test_label_and_tracking_routes_degrade_gracefully_after_a_booking_reset(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        $order = $this->makeOrder();
        $order->forceFill([
            'courier_meta' => json_encode(['booking_history' => [[
                'courier' => 'quadx',
                'tracking_number' => 'QX-RESET-001',
                'status' => 'for_pickup',
                'timeline' => [],
                'meta' => [],
                'admin_cancelled' => true,
                'cancelled_at' => '2026-08-08T10:00:00+08:00',
            ]]]),
        ])->save();

        Http::fake();

        $this->get($this->queueUrl() . '/' . $order->id . '/label')
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->get($this->queueUrl() . '/' . $order->id . '/label-file')
            ->assertNotFound();

        $this->post($this->queueUrl() . '/' . $order->id . '/track')
            ->assertRedirect()
            ->assertSessionHas('error');

        Http::assertNothingSent();
    }
}
