<?php

namespace Tests\Feature\Fulfillment;

use App\Contracts\OrderNotificationChannel;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Store;
use App\Models\User;
use App\Services\OrderNotificationService;
use App\Support\Phone;
use Extensions\CourierFulfillment\FulfillmentService;
use Extensions\CourierFulfillment\NotifyMap;
use Extensions\CourierFulfillment\PlacementMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\ResetsNotificationChannels;
use Tests\TestCase;

/**
 * Another way of telling a customer, plugged in by an extension.
 *
 * The channel here is a stand-in rather than the SMS one on purpose: these
 * tests are about the socket, not about UniSMS. If they only passed with the
 * SMS extension present they would be testing the wrong thing, and the
 * decoupling they claim to prove would be decoupling in name.
 */
class NotificationChannelTest extends TestCase
{
    use RefreshDatabase;
    use ResetsNotificationChannels;

    private OrderStatus $packing;

    private OrderStatus $handover;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        app(\App\Services\ExtensionManager::class)->install('courier-fulfillment');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        $this->actingAs(User::factory()->create());

        $this->packing = OrderStatus::create(['store_id' => 1, 'name' => 'Packing', 'sort_order' => 1]);
        $this->handover = OrderStatus::create(['store_id' => 1, 'name' => 'Handed over', 'sort_order' => 2]);
        PlacementMap::put($this->packing->id, 'to_pack');
        PlacementMap::put($this->handover->id, 'to_handover');

        SpyChannel::reset();
        Mail::fake();
    }

    protected function tearDown(): void
    {
        $this->resetNotificationChannels();

        parent::tearDown();
    }

    private function order(): Order
    {
        return Order::create([
            'store_id' => 1, 'first_name' => 'A', 'last_name' => 'Buyer',
            'email' => 'buyer@example.test', 'order_status_id' => $this->packing->id,
        ]);
    }

    public function test_a_channel_is_offered_only_once_registered(): void
    {
        $this->assertSame([], OrderNotificationService::channels(),
            'Nothing is offered until an extension registers something.');

        OrderNotificationService::registerChannel(SpyChannel::class);

        $this->assertCount(1, OrderNotificationService::channels());
        $this->assertSame('spy', OrderNotificationService::channels()[0]->key());
    }

    /** A channel with nothing configured must not be offered a tick that would do nothing. */
    public function test_an_unconfigured_channel_is_not_offered(): void
    {
        OrderNotificationService::registerChannel(SpyChannel::class);
        SpyChannel::$available = false;

        $this->assertSame([], OrderNotificationService::channels());
    }

    public function test_only_the_ticked_channels_are_used(): void
    {
        OrderNotificationService::registerChannel(SpyChannel::class);

        OrderNotificationService::statusChangedVia([], $this->order(), 'Packing', 'Handed over', 'note');
        $this->assertSame(0, SpyChannel::$calls, 'Nothing ticked, nothing sent.');

        OrderNotificationService::statusChangedVia(['spy'], $this->order(), 'Packing', 'Handed over', 'note');
        $this->assertSame(1, SpyChannel::$calls);
        $this->assertSame('Handed over', SpyChannel::$lastNew);
    }

    /** The email and the channels are separate acts, in both directions. */
    public function test_a_channel_can_send_without_an_email(): void
    {
        OrderNotificationService::registerChannel(SpyChannel::class);

        OrderNotificationService::statusChangedVia(['spy'], $this->order(), 'Packing', 'Handed over', null);

        $this->assertSame(1, SpyChannel::$calls);
        Mail::assertNothingSent();
    }

    /**
     * A gateway falling over must not undo a status change. The order moved
     * whatever the network did.
     */
    public function test_a_channel_that_throws_does_not_take_the_order_with_it(): void
    {
        OrderNotificationService::registerChannel(SpyChannel::class);
        SpyChannel::$throw = true;

        $results = OrderNotificationService::statusChangedVia(['spy'], $this->order(), 'Packing', 'Handed over', null);

        $this->assertFalse($results['spy']['sent']);
        $this->assertNotNull($results['spy']['reason']);
    }

    /**
     * A packer who says nothing gets the store's answer, which for a channel
     * is the channel's own list (wantsStatus), not fulfillment's retiring
     * NotifyMap.
     */
    public function test_fulfilment_falls_back_to_the_channels_own_list(): void
    {
        OrderNotificationService::registerChannel(SpyChannel::class);

        app(FulfillmentService::class)->bookManual($this->order(), [
            'order_status_id' => $this->handover->id,
        ]);
        $this->assertSame(1, SpyChannel::$calls, 'The channel wants this status, so it ran.');

        SpyChannel::$wants = false;
        NotifyMap::putFor('spy', [$this->handover->id]);
        app(FulfillmentService::class)->bookManual($this->order(), [
            'order_status_id' => $this->handover->id,
        ]);
        $this->assertSame(1, SpyChannel::$calls, 'The retiring list no longer answers for the channel.');
    }

    public function test_an_explicit_tick_beats_the_channels_list_in_both_directions(): void
    {
        OrderNotificationService::registerChannel(SpyChannel::class);

        // The channel says yes, the packer says no.
        app(FulfillmentService::class)->bookManual($this->order(), [
            'order_status_id' => $this->handover->id,
            'channels' => [],
        ]);
        $this->assertSame(0, SpyChannel::$calls);

        // The channel says no, the packer asks for it anyway.
        SpyChannel::$wants = false;
        app(FulfillmentService::class)->bookManual($this->order(), [
            'order_status_id' => $this->handover->id,
            'channels' => ['spy'],
        ]);
        $this->assertSame(1, SpyChannel::$calls);
    }

    /**
     * Only the email half given: it stays the caller's answer, and the
     * channel half is still the channel's own list.
     */
    public function test_one_half_given_leaves_the_other_to_the_store(): void
    {
        OrderNotificationService::registerChannel(SpyChannel::class);

        app(FulfillmentService::class)->bookManual($this->order(), [
            'order_status_id' => $this->handover->id,
            'notify_customer' => false,
        ]);

        $this->assertSame(1, SpyChannel::$calls);
        Mail::assertNothingSent();
    }

    /**
     * I4: the queue form sends a hidden channels[] sentinel, so a packer who
     * unticks every channel box has said "no channels", not "no opinion".
     */
    public function test_the_queue_form_with_every_box_unticked_sends_nothing(): void
    {
        OrderNotificationService::registerChannel(SpyChannel::class);
        $order = $this->order();

        $this->post('/' . config('admin.prefix', 'admin') . '/extensions/courier-fulfillment/' . $order->id . '/book-manual', [
            'order_status_id' => $this->handover->id,
            'notify_customer' => '0',
            'channels' => [''],
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->handover->id, $order->fresh()->order_status_id);
        $this->assertSame(0, SpyChannel::$calls, 'Every box unticked means no channels.');
    }

    public function test_the_queue_form_with_a_box_ticked_sends_it(): void
    {
        OrderNotificationService::registerChannel(SpyChannel::class);
        SpyChannel::$wants = false;
        $order = $this->order();

        $this->post('/' . config('admin.prefix', 'admin') . '/extensions/courier-fulfillment/' . $order->id . '/book-manual', [
            'order_status_id' => $this->handover->id,
            'notify_customer' => '0',
            'channels' => ['', 'spy'],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, SpyChannel::$calls);
    }

    /** The queue page pre-ticks from the new homes, not the retiring list. */
    public function test_the_queue_page_pre_ticks_from_the_status_and_the_channel(): void
    {
        OrderNotificationService::registerChannel(SpyChannel::class);
        $this->handover->update(['emails_customer' => true]);
        NotifyMap::put([$this->packing->id]);
        // The manual form is drawn only when the store keeps a courier.
        \App\Models\ShippingCourier::create(['store_id' => 1, 'name' => 'LBC Express', 'is_active' => true, 'sort_order' => 1]);

        $view = $this->get('/' . config('admin.prefix', 'admin') . '/extensions/courier-fulfillment')->assertOk();

        $notifyByStatus = $view->viewData('notifyByStatus');
        $this->assertTrue($notifyByStatus[$this->handover->id] ?? false);
        $this->assertFalse($notifyByStatus[$this->packing->id] ?? false, 'NotifyMap no longer pre-ticks.');
        $this->assertTrue($view->viewData('channelByStatus')['spy'][$this->handover->id] ?? false);
        $view->assertSee('name="channels[]" value=""', false);
    }

    /** Numbers are stored as typed, so the channel gets a whole one or nothing. */
    public function test_a_national_number_becomes_dialable_and_junk_does_not(): void
    {
        $this->assertSame('+639171234567', Phone::e164('09171234567', '63'));
        $this->assertSame('+639171234567', Phone::e164('9171234567', '63'));
        $this->assertSame('+639171234567', Phone::e164('+63 917 123 4567', '63'));
        $this->assertNull(Phone::e164('999', '63'), 'Too short to be anybody.');
        $this->assertNull(Phone::e164('9171234567', null), 'No country, no number.');
        $this->assertNull(Phone::e164('', '63'));
    }
}

/** A channel that records what it was asked to do. */
class SpyChannel implements OrderNotificationChannel
{
    public static int $calls = 0;

    public static ?string $lastNew = null;

    public static bool $available = true;

    public static bool $throw = false;

    public static bool $wants = true;

    public static function reset(): void
    {
        self::$calls = 0;
        self::$lastNew = null;
        self::$available = true;
        self::$throw = false;
        self::$wants = true;
    }

    public function key(): string
    {
        return 'spy';
    }

    public function label(): string
    {
        return 'Send spy';
    }

    public function available(): bool
    {
        return self::$available;
    }

    public function wantsStatus(?int $orderStatusId): bool
    {
        return self::$wants;
    }

    public function statusChanged(Order $order, ?string $oldStatus, ?string $newStatus, ?string $comment = null, ?int $newStatusId = null): array
    {
        if (self::$throw) {
            throw new \RuntimeException('the gateway fell over');
        }

        self::$calls++;
        self::$lastNew = $newStatus;

        return ['sent' => true, 'reason' => null];
    }
}
