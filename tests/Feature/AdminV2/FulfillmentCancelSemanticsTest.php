<?php

namespace Tests\Feature\AdminV2;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderStatus;
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
 *      fields and surfaces in Cancelled with Rebook + Tracking.
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
        return OrderStatus::firstOrCreate(
            ['store_id' => 1, 'name' => 'Processing'],
            ['sort_order' => 1, 'is_default' => true],
        );
    }

    private function statusWithStage(string $stage, string $name, ?string $stockAction = null): OrderStatus
    {
        return OrderStatus::firstOrCreate(
            ['store_id' => 1, 'name' => $name],
            ['sort_order' => 1, 'fulfillment_stage' => $stage, 'stock_action' => $stockAction],
        );
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
            substr_count($response->getContent(), "\$dispatch('open-modal', 'cancel-order-{$order->id}')")
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
        $canceled = $this->statusWithStage('cancelled', 'Canceled', 'increment');

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

        // StockService::adjustForStatusChange() ran: 'increment' put the two
        // units back and cleared the adjusted flag.
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
        $order = $this->makeOrder();
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

        // Audit trail on the order itself.
        $this->assertDatabaseHas('order_histories', [
            'order_id' => $order->id,
            'comment' => 'Courier booking cancelled: QX-RESET-001 (QuadX). Order returned to To Pack.',
            'notify_customer' => false,
        ]);

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

    public function test_courier_side_7001_without_admin_cancel_lands_in_cancelled_with_rebook_and_tracking(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        app(ExtensionManager::class)->bootEnabled();
        $this->enableQuadxStaging(); // an enabled courier so Rebook can render

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

        $response = $this->get($this->queueUrl() . '?filter=cancelled');
        $response->assertOk()
            ->assertSee('data-count-for="cancelled">1<', false)
            ->assertSee('SPXPH000000000001')
            ->assertSee('Rebook')
            ->assertSee('openDrawer(JSON.parse(', false);
    }

    // ------------------------------------------------------------------
    // Owner ruling (spec amendment 59bd751, "Stage precedence"): unpaid
    // stage claims an order ONLY while it has no active booking; an
    // actively-booked order buckets by its courier phase regardless of
    // payment status. Cancelled stage precedence stays absolute. The
    // grouped tally and the per-row bucket must agree.
    // ------------------------------------------------------------------

    public function test_actively_booked_unpaid_order_buckets_by_courier_phase_not_unpaid(): void
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

        // Per-row bucket: the shared rule.
        $this->assertSame('unpaid', \Extensions\CourierFulfillment\FulfillmentService::tabBucket($plainUnpaid));
        $this->assertSame('to_handover', \Extensions\CourierFulfillment\FulfillmentService::tabBucket($unpaidHandover));
        $this->assertSame('in_transit', \Extensions\CourierFulfillment\FulfillmentService::tabBucket($unpaidTransit));

        // Tally agrees with the per-row buckets.
        $response = $this->get($this->queueUrl());
        $response->assertOk()
            ->assertSee('data-count-for="unpaid">1<', false)
            ->assertSee('data-count-for="to_handover">1<', false)
            ->assertSee('data-count-for="in_transit">1<', false);

        // Membership: the booked ones list under their courier-phase tabs
        // (with the normal courier action set, not the unpaid one-action
        // row), never under Unpaid.
        $this->get($this->queueUrl() . '?filter=to_ship&sub=to_handover')
            ->assertOk()
            ->assertSee('QX-UNPAID-H1')
            ->assertSee('Print waybill')
            ->assertSee('openDrawer(JSON.parse(', false)
            ->assertDontSee('Cancel order');

        $this->get($this->queueUrl() . '?filter=in_transit')
            ->assertOk()
            ->assertSee('QX-UNPAID-T1');

        $this->get($this->queueUrl() . '?filter=unpaid')
            ->assertOk()
            ->assertSee('#' . $plainUnpaid->id, false)
            ->assertDontSee('QX-UNPAID-H1')
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
    // Owner ruling: the server-side book gate that makes the invariant
    // real — unpaid/cancelled-stage orders cannot be booked (or quoted).
    // ------------------------------------------------------------------

    public function test_book_route_refuses_an_unpaid_stage_order_with_no_outbound_call_and_no_mutation(): void
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
        $this->assertStringContainsString('unpaid', session('error'));
        $this->assertStringContainsString('ready stage', session('error'));

        Http::assertNothingSent();
        $order->refresh();
        $this->assertNull($order->courier_provider);
        $this->assertNull($order->courier_tracking_number);
        $this->assertNull($order->courier_status);
    }

    public function test_book_route_refuses_a_cancelled_stage_order(): void
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
        $this->assertStringContainsString('cancelled', session('error'));

        Http::assertNothingSent();
        $this->assertNull($order->fresh()->courier_tracking_number);
    }

    public function test_estimate_route_refuses_unpaid_and_cancelled_stage_orders(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        Http::fake();

        $unpaid = $this->makeOrder($this->statusWithStage('unpaid', 'Unpaid'));
        $cancelled = $this->makeOrder($this->statusWithStage('cancelled', 'Canceled'));

        $r1 = $this->postJson($this->queueUrl() . '/' . $unpaid->id . '/estimate', ['courier' => 'quadx']);
        $r1->assertOk()->assertJson(['ok' => false]);
        $this->assertStringContainsString('unpaid', $r1->json('message'));

        $r2 = $this->postJson($this->queueUrl() . '/' . $cancelled->id . '/estimate', ['courier' => 'quadx']);
        $r2->assertOk()->assertJson(['ok' => false]);
        $this->assertStringContainsString('cancelled', $r2->json('message'));

        Http::assertNothingSent();
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
