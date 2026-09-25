<?php

namespace Tests\Feature\Fulfillment;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Store;
use App\Models\User;
use Extensions\CourierFulfillment\FulfillmentService;
use Extensions\CourierFulfillment\NotifyMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Which statuses tell the customer, decided once by the store.
 *
 * The core order page keeps its unticked box on purpose: moving a status
 * there is a deliberate act by somebody with the order open who may have a
 * reason not to write. Fulfilment is the opposite. A packer records parcels
 * all afternoon and should not be deciding each time whether this is the kind
 * of status a customer hears about.
 */
class NotifyPolicyTest extends TestCase
{
    use RefreshDatabase;

    private OrderStatus $shipped;

    private OrderStatus $packing;

    private OrderStatus $handover;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);

        // The module registers its own PSR-4 autoloader when it boots, so
        // nothing under Extensions\CourierFulfillment resolves until it has.
        app(\App\Services\ExtensionManager::class)->install('courier-fulfillment');
        app(\App\Services\ExtensionManager::class)->bootEnabled();

        $this->actingAs(User::factory()->create());

        $this->shipped = OrderStatus::create(['store_id' => 1, 'name' => 'Shipped', 'sort_order' => 1]);
        $this->packing = OrderStatus::create(['store_id' => 1, 'name' => 'Packing', 'sort_order' => 2]);

        // A parcel can only be recorded from a status placed under To Pack,
        // which is where the orders these tests book actually sit.
        \Extensions\CourierFulfillment\PlacementMap::put($this->packing->id, 'to_pack');
        \Extensions\CourierFulfillment\PlacementMap::put($this->shipped->id, 'shipping');

        $this->handover = OrderStatus::create(['store_id' => 1, 'name' => 'Handed over', 'sort_order' => 3]);
        \Extensions\CourierFulfillment\PlacementMap::put($this->handover->id, 'to_handover');

        // TemplateMailService looks the template up and quietly returns false
        // when it is missing, so without this a test asserting "an email was
        // sent" would fail for the wrong reason.
        \DB::table('email_templates')->insert([
            'store_id' => 1,
            'slug' => 'order_status_update',
            'name' => 'Order status update',
            'subject' => 'Your order #{order_id} is now {new_status}',
            'body' => '<p>Hi {customer_name}, order #{order_id} moved from {old_status} to {new_status}.</p><p>{comment}</p>',
            'type' => 'customer',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Mail::fake();
    }

    private function order(): Order
    {
        return Order::create([
            'store_id' => 1, 'first_name' => 'A', 'last_name' => 'Buyer',
            'email' => 'buyer@example.test', 'order_status_id' => $this->packing->id,
        ]);
    }

    public function test_the_policy_is_stored_and_read_back(): void
    {
        $this->assertSame([], NotifyMap::all());
        $this->assertFalse(NotifyMap::notifies($this->shipped->id));

        NotifyMap::put([$this->shipped->id]);

        $this->assertSame([$this->shipped->id], NotifyMap::all());
        $this->assertTrue(NotifyMap::notifies($this->shipped->id));
        $this->assertFalse(NotifyMap::notifies($this->packing->id));
        $this->assertFalse(NotifyMap::notifies(null));
    }

    /** A caller that says nothing gets the store's answer. */
    public function test_a_manual_record_with_no_opinion_follows_the_policy(): void
    {
        NotifyMap::put([$this->shipped->id]);
        $service = app(FulfillmentService::class);

        $notified = $service->bookManual($this->order(), ['order_status_id' => $this->shipped->id]);
        $this->assertTrue($notified['success']);

        $quiet = $service->bookManual($this->order(), ['order_status_id' => $this->packing->id]);
        $this->assertTrue($quiet['success']);

        // The history row records what was decided, whoever decided it.
        $this->assertTrue((bool) \DB::table('order_histories')
            ->where('order_status_id', $this->shipped->id)->value('notify_customer'));
        $this->assertFalse((bool) \DB::table('order_histories')
            ->where('order_status_id', $this->packing->id)->value('notify_customer'));
    }

    /**
     * The packer can still say no. An explicit false has to beat the policy,
     * or the tick on the form would be decoration.
     */
    public function test_an_explicit_choice_overrides_the_policy(): void
    {
        NotifyMap::put([$this->shipped->id]);

        app(FulfillmentService::class)->bookManual($this->order(), [
            'order_status_id' => $this->shipped->id,
            'notify_customer' => false,
        ]);

        $this->assertFalse((bool) \DB::table('order_histories')
            ->where('order_status_id', $this->shipped->id)->value('notify_customer'));
    }

    public function test_an_explicit_yes_beats_a_status_the_policy_stays_quiet_about(): void
    {
        NotifyMap::put([]);

        app(FulfillmentService::class)->bookManual($this->order(), [
            'order_status_id' => $this->handover->id,
            'notify_customer' => true,
        ]);

        $this->assertTrue((bool) \DB::table('order_histories')
            ->where('order_status_id', $this->handover->id)->value('notify_customer'));
    }

    /**
     * The half that means the ERP needs no work: it moves a status and the
     * store's policy decides, exactly as it does for a packer.
     */
    public function test_the_erp_follows_the_policy_when_it_says_nothing(): void
    {
        NotifyMap::put([$this->shipped->id]);
        $order = $this->order();

        $this->postJson(route('erp.orders.book-manual', ['id' => $order->id]), [
            'order_status_id' => $this->shipped->id,
        ], $this->erpHeaders())->assertOk();

        $this->assertTrue((bool) \DB::table('order_histories')
            ->where('order_id', $order->id)->where('order_status_id', $this->shipped->id)
            ->value('notify_customer'));
    }

    /** @return array<string, string> */
    private function erpHeaders(): array
    {
        $token = 'erp-test-token';
        $this->apiKey($token);

        return ['Authorization' => 'Bearer ' . $token];
    }

    /**
     * One email, the same one the order page sends, with the parcel's number
     * in it. A tracking number used to divert this to the shipping template,
     * which never said the status had changed.
     */
    public function test_a_recorded_parcel_sends_one_status_email_carrying_the_tracking(): void
    {
        NotifyMap::put([$this->handover->id]);

        $courier = \App\Models\ShippingCourier::create([
            'store_id' => 1, 'name' => 'Test Courier', 'is_active' => true,
            'tracking_url' => 'https://track.example.test/{tracking_number}',
        ]);

        app(FulfillmentService::class)->bookManual($this->order(), [
            'order_status_id'     => $this->handover->id,
            'shipping_courier_id' => $courier->id,
            'tracking_number'     => 'TRK123456',
            'comment'             => 'Left with the guard',
        ]);

        Mail::assertSent(\App\Mail\TemplateMail::class, 1);

        Mail::assertSent(\App\Mail\TemplateMail::class, function ($mail) {
            $body = $mail->renderedBody;

            return str_contains($body, 'Handed over')
                && str_contains($body, 'Left with the guard')
                && str_contains($body, 'TRK123456')
                && str_contains($body, 'https://track.example.test/TRK123456');
        });
    }

    /**
     * applyBookedStatus() appends an audit line to the HISTORY row ("Order
     * status set to X by fulfillment"), which is for the record, not for the
     * customer. The two must not be the same string any more.
     */
    public function test_the_customer_email_does_not_carry_the_fulfillment_audit_line(): void
    {
        NotifyMap::put([$this->handover->id]);
        $order = $this->order();

        app(FulfillmentService::class)->bookManual($order, [
            'order_status_id' => $this->handover->id,
            'comment'         => 'Left with the guard',
        ]);

        Mail::assertSent(\App\Mail\TemplateMail::class, fn ($m) => ! str_contains($m->renderedBody, 'by fulfillment'));

        $this->assertStringContainsString(
            'by fulfillment',
            \App\Models\OrderHistory::where('order_id', $order->id)->latest('id')->value('comment'),
            'The audit line still belongs on the record.'
        );
    }

    /** And with no number, the same email without a tracking tail. */
    public function test_a_status_move_with_no_parcel_still_sends_one_plain_email(): void
    {
        NotifyMap::put([$this->handover->id]);

        app(FulfillmentService::class)->bookManual($this->order(), [
            'order_status_id' => $this->handover->id,
            'comment'         => 'Ready for the rider',
        ]);

        Mail::assertSent(\App\Mail\TemplateMail::class, 1);
        Mail::assertSent(\App\Mail\TemplateMail::class, fn ($m) =>
            str_contains($m->renderedBody, 'Ready for the rider')
            && ! str_contains($m->renderedBody, '<a href'));
    }
}
