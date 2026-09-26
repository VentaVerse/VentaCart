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
 * The order page's "Update order" boxes start from the store's own answer
 * for the status being chosen, email and every channel alike, and only when
 * the status is actually changing: a note added to an order that is staying
 * put must not email or text the customer by default.
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
        $this->shipped = OrderStatus::create([
            'store_id' => 1, 'name' => 'Shipped', 'sort_order' => 2, 'emails_customer' => true,
        ]);
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
     * I3: the order sits at an emailing status. Its current status is the
     * select's starting value, so a guard on "the status is changing" keeps
     * both boxes clear until the admin actually picks another one.
     */
    public function test_nothing_is_pre_ticked_while_the_status_stays_put(): void
    {
        $order = $this->order();

        $page = $this->get(route('admin.orders.show', $order))->assertOk();

        $page->assertSee('current: ' . $this->shipped->id, false);
        $guard = 'statusId !== current && ';
        $this->assertSame(
            1 + count(OrderNotificationService::channels()),
            substr_count($page->getContent(), $guard),
            'The email box and every channel box carry the status-is-changing guard.'
        );
    }
}
