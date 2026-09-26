<?php

namespace Tests\Feature\Notifications;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Store;
use App\Models\User;
use App\Services\OrderNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RecordingChannel;
use Tests\Support\ResetsNotificationChannels;
use Tests\TestCase;

/**
 * The order page's "Update order" boxes. The email box starts unticked, as
 * in OpenCart: by hand, the person decides (owner ruling 2026-09-26). Each
 * channel's box starts from that channel's own list, and only while the
 * status is actually changing: a note added to an order that is staying put
 * must not text the customer by default.
 */
class OrderPagePreTickTest extends TestCase
{
    use RefreshDatabase;
    use ResetsNotificationChannels;

    private OrderStatus $packing;

    private OrderStatus $shipped;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        $this->packing = OrderStatus::create(['store_id' => 1, 'name' => 'Packing', 'sort_order' => 1]);
        $this->shipped = OrderStatus::create(['store_id' => 1, 'name' => 'Shipped', 'sort_order' => 2]);
        // Shipped emails by itself for automated changes. It must still not
        // pre-tick the box on the order page.
        $this->emailAutomaticallyOn($this->shipped->id);
        RecordingChannel::reset();
        OrderNotificationService::registerChannel(RecordingChannel::class);
        $this->actingAs(User::factory()->create());
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
            'email' => 'buyer@example.test', 'order_status_id' => $this->shipped->id,
        ]);
    }

    /** I2: each channel's box answers from that channel's own list. */
    public function test_each_channel_box_is_pre_ticked_from_its_own_list(): void
    {
        RecordingChannel::$wants = true;

        $page = $this->get(route('admin.orders.show', $this->order()))->assertOk();

        $wants = $page->viewData('channelWantsByStatus');
        $this->assertTrue($wants['recorder'][$this->shipped->id]);
        $this->assertTrue($wants['recorder'][$this->packing->id]);
        $page->assertSee("channelWants['recorder']", false);
    }

    public function test_a_channel_that_wants_nothing_starts_unticked(): void
    {
        RecordingChannel::$wants = false;

        $page = $this->get(route('admin.orders.show', $this->order()))->assertOk();

        $this->assertFalse($page->viewData('channelWantsByStatus')['recorder'][$this->shipped->id]);
    }

    /**
     * The order sits at a status another path would text about. Its current
     * status is the select's starting value, so a guard on "the status is
     * changing" keeps every channel box clear until the admin picks another.
     */
    public function test_no_channel_is_pre_ticked_while_the_status_stays_put(): void
    {
        $page = $this->get(route('admin.orders.show', $this->order()))->assertOk();

        $page->assertSee('current: ' . $this->shipped->id, false);
        $this->assertSame(
            count(OrderNotificationService::channels()),
            substr_count($page->getContent(), 'statusId !== current && '),
            'Every channel box carries the status-is-changing guard.'
        );
    }

    public function test_the_email_box_is_never_pre_ticked(): void
    {
        $html = $this->get(route('admin.orders.show', $this->order()))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~<input type="checkbox" name="notify_customer" value="1"\s+aria-label~', $html,
            'The email box carries no checked binding, whatever the automatic list says.');
    }
}
