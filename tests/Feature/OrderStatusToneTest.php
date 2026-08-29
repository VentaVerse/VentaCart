<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User;
use App\Support\StatusTone;
use Database\Seeders\OrderStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A status wears a tone in the customer's account (neutral, info, warning,
 * success, danger). It is a setting on the status, seeded from the name
 * once and edited by the merchant, never derived from the name at render
 * time: "Start Pickup" used to become two broken CSS classes and "Unpaid"
 * rendered as plain text.
 */
class OrderStatusToneTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seed_gives_each_status_a_sensible_tone(): void
    {
        $this->seed(OrderStatusSeeder::class);

        $tone = fn (string $name) => OrderStatus::where('name', $name)->value('tone');

        $this->assertSame(StatusTone::WARNING, $tone('Unpaid'));
        $this->assertSame(StatusTone::WARNING, $tone('Pending'));
        $this->assertSame(StatusTone::INFO, $tone('Processing'));
        $this->assertSame(StatusTone::INFO, $tone('Shipped'));
        $this->assertSame(StatusTone::SUCCESS, $tone('Complete'));
        $this->assertSame(StatusTone::SUCCESS, $tone('Delivered'));
        $this->assertSame(StatusTone::DANGER, $tone('Canceled'));
        $this->assertSame(StatusTone::DANGER, $tone('Refunded'));
        $this->assertSame(StatusTone::DANGER, $tone('Chargeback'));
    }

    public function test_the_suggestion_reads_names_the_way_a_merchant_would(): void
    {
        $this->assertSame(StatusTone::DANGER, StatusTone::suggest('Delivery Failed', true));
        $this->assertSame(StatusTone::DANGER, StatusTone::suggest('Returned'));
        $this->assertSame(StatusTone::SUCCESS, StatusTone::suggest('Payment Received', true));
        $this->assertSame(StatusTone::WARNING, StatusTone::suggest('Quote Ready for Payment'));
        $this->assertSame(StatusTone::INFO, StatusTone::suggest('Start Pickup', true));
        $this->assertSame(StatusTone::NEUTRAL, StatusTone::suggest('Item Reserved', false));
    }

    public function test_the_admin_sets_the_tone_per_status_and_a_made_up_tone_is_refused(): void
    {
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Start Pickup', 'is_default' => true, 'sort_order' => 1]);
        $this->assertSame(StatusTone::NEUTRAL, $status->tone);

        $rows = fn (string $tone) => ['default_id' => $status->id, 'rows' => [
            $status->id => ['name' => 'Start Pickup', 'deducts_stock' => '1', 'customer_can_cancel' => '0', 'sort_order' => 1, 'tone' => $tone],
        ]];

        $this->actingAs(User::factory()->create())->put(route('admin.order-statuses.update-all'), $rows('info'))->assertRedirect();
        $this->assertSame(StatusTone::INFO, $status->fresh()->tone);

        $this->actingAs(User::factory()->create())->put(route('admin.order-statuses.update-all'), $rows('purple'))->assertSessionHasErrors('rows.'.$status->id.'.tone');
        $this->assertSame(StatusTone::INFO, $status->fresh()->tone);
    }

    public function test_a_new_status_without_a_tone_gets_one_suggested_from_its_name(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.order-statuses.store'), ['name' => 'Lost in transit', 'deducts_stock' => '1'])
            ->assertRedirect();

        $this->assertSame(StatusTone::DANGER, OrderStatus::where('name', 'Lost in transit')->value('tone'));
    }

    public function test_the_account_badge_wears_the_tone_and_history_shows_only_what_was_written_for_the_customer(): void
    {
        $unpaid = OrderStatus::create(['store_id' => 1, 'name' => 'Unpaid', 'is_default' => true, 'sort_order' => 1, 'tone' => StatusTone::WARNING]);
        $moving = OrderStatus::create(['store_id' => 1, 'name' => 'Start Pickup', 'sort_order' => 2, 'tone' => StatusTone::INFO]);
        $customer = Customer::create(['store_id' => 1, 'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com', 'password' => bcrypt('secret')]);
        $order = Order::create([
            'store_id' => 1, 'customer_id' => $customer->id, 'order_status_id' => $moving->id,
            'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com', 'phone' => '1',
            'shipping_first_name' => 'Jane', 'shipping_last_name' => 'Doe', 'shipping_address_1' => 'x',
            'shipping_city' => 'Cebu City', 'shipping_postcode' => '6000', 'subtotal' => 100, 'total' => 100,
        ]);
        $order->histories()->create(['order_status_id' => $unpaid->id, 'comment' => 'Order confirmed.', 'notify_customer' => true]);
        $order->histories()->create(['order_status_id' => $moving->id, 'comment' => 'Packed by Ana, for_pickup booked', 'notify_customer' => false]);

        $list = $this->actingAs($customer, 'customer')->get(route('account.orders'))->assertOk()->getContent();
        $this->assertStringContainsString('account-status-badge--info', $list);
        $this->assertStringNotContainsString('account-status-start pickup', $list);

        $page = $this->actingAs($customer, 'customer')->get(route('account.orders.show', $order))->assertOk()->getContent();
        $this->assertStringContainsString('account-status-badge--info', $page);
        $this->assertStringContainsString('account-status-badge--warning', $page, 'The history chip wears the tone of the status it records.');
        $this->assertStringContainsString('Order confirmed.', $page);
        $this->assertStringNotContainsString('for_pickup', $page, 'The courier feed is for the store, not the customer.');
    }
}
