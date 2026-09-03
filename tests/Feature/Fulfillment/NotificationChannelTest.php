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

    /** A packer who says nothing gets the store's answer, same as the email. */
    public function test_fulfilment_falls_back_to_the_policy_for_a_channel(): void
    {
        OrderNotificationService::registerChannel(SpyChannel::class);
        NotifyMap::putFor('spy', [$this->handover->id]);

        app(FulfillmentService::class)->bookManual($this->order(), [
            'order_status_id' => $this->handover->id,
        ]);

        $this->assertSame(1, SpyChannel::$calls, 'The status is in the policy, so the channel ran.');
    }

    public function test_an_explicit_tick_beats_the_policy_in_both_directions(): void
    {
        OrderNotificationService::registerChannel(SpyChannel::class);
        NotifyMap::putFor('spy', [$this->handover->id]);

        // Policy says yes, the packer says no.
        app(FulfillmentService::class)->bookManual($this->order(), [
            'order_status_id' => $this->handover->id,
            'channels' => [],
        ]);
        $this->assertSame(0, SpyChannel::$calls);

        // Policy says nothing about Packing, the packer asks for it anyway.
        NotifyMap::putFor('spy', []);
        app(FulfillmentService::class)->bookManual($this->order(), [
            'order_status_id' => $this->handover->id,
            'channels' => ['spy'],
        ]);
        $this->assertSame(1, SpyChannel::$calls);
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

    public static function reset(): void
    {
        self::$calls = 0;
        self::$lastNew = null;
        self::$available = true;
        self::$throw = false;
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

    public function statusChanged(Order $order, ?string $oldStatus, ?string $newStatus, ?string $comment = null): array
    {
        if (self::$throw) {
            throw new \RuntimeException('the gateway fell over');
        }

        self::$calls++;
        self::$lastNew = $newStatus;

        return ['sent' => true, 'reason' => null];
    }
}
