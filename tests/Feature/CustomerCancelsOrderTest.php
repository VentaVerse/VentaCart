<?php

namespace Tests\Feature;

use App\Mail\TemplateMail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Services\CustomerOrderCancellation;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A customer may cancel their own order from their account while it sits on
 * a status the merchant marked "customer may cancel" (owner request,
 * 2026-08-30: the unpaid ones, including a COD-to-confirm status). The move
 * goes through OrderStatusChanger, so stock returns, history is written,
 * and the store is told.
 */
class CustomerCancelsOrderTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;
    private OrderStatus $pending;
    private OrderStatus $processing;
    private OrderStatus $canceled;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EmailTemplateSeeder::class);
        Mail::fake();
        Setting::updateOrCreate(['group' => 'mail', 'key' => 'from_address'], ['value' => 'store@example.com']);
        $this->store = Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        $this->pending = OrderStatus::create(['store_id' => 1, 'name' => 'Pending', 'is_default' => true, 'sort_order' => 1, 'deducts_stock' => true, 'customer_can_cancel' => true]);
        $this->processing = OrderStatus::create(['store_id' => 1, 'name' => 'Processing', 'sort_order' => 2, 'deducts_stock' => true]);
        $this->canceled = OrderStatus::create(['store_id' => 1, 'name' => 'Canceled', 'sort_order' => 7, 'deducts_stock' => false]);
        Setting::create(['group' => 'checkout', 'key' => 'customer_cancel_status_id', 'value' => (string) $this->canceled->id]);
    }

    private function customer(string $email = 'jane@example.com'): Customer
    {
        return Customer::create(['store_id' => 1, 'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => $email, 'password' => bcrypt('secret')]);
    }

    private function order(Customer $customer, OrderStatus $status): Order
    {
        return Order::create([
            'store_id' => 1, 'customer_id' => $customer->id, 'order_status_id' => $status->id,
            'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => $customer->email, 'phone' => '1',
            'shipping_first_name' => 'Jane', 'shipping_last_name' => 'Doe', 'shipping_address_1' => 'x',
            'shipping_city' => 'Cebu City', 'shipping_postcode' => '6000', 'subtotal' => 100, 'total' => 100,
        ]);
    }

    public function test_the_button_shows_only_while_the_status_allows_it(): void
    {
        $customer = $this->customer();
        $open = $this->order($customer, $this->pending);
        $paid = $this->order($customer, $this->processing);

        $this->actingAs($customer, 'customer')->get(route('account.orders.show', $open))
            ->assertOk()->assertSee('CANCEL THIS ORDER');
        $this->actingAs($customer, 'customer')->get(route('account.orders.show', $paid))
            ->assertOk()->assertDontSee('CANCEL THIS ORDER');
    }

    public function test_cancelling_moves_the_order_writes_history_returns_stock_and_tells_both_sides(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer, $this->pending);
        $product = Product::create(['store_id' => 1, 'name' => 'Cable', 'slug' => 'cable', 'sku' => 'C1', 'price' => 100, 'quantity' => 3, 'is_active' => true]);
        $order->products()->create(['product_id' => $product->id, 'name' => 'Cable', 'sku' => 'C1', 'price' => 100, 'quantity' => 2, 'total' => 200]);
        // The order deducted stock when it reached Pending: stock_adjusted is
        // StockService's own marker for "this order holds units".
        $product->update(['quantity' => 1]);
        $order->forceFill(['stock_adjusted' => true])->save();

        $this->actingAs($customer, 'customer')
            ->post(route('account.orders.cancel', $order), ['reason' => 'changed my mind'])
            ->assertRedirect(route('account.orders.show', $order))
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame($this->canceled->id, $order->order_status_id);
        $this->assertSame('Canceled', $order->order_status_name);
        $this->assertSame(3, $product->fresh()->quantity, 'Cancelling onto a non-deducting status must hand the units back.');
        $this->assertDatabaseHas('order_histories', ['order_id' => $order->id, 'order_status_id' => $this->canceled->id, 'comment' => 'Cancelled by customer: changed my mind.']);

        // The customer hears it moved; the store hears who moved it.
        Mail::assertSent(TemplateMail::class, fn (TemplateMail $m) => str_contains($m->renderedBody, 'Canceled') && str_contains($m->renderedBody, 'changed my mind'));
        Mail::assertSent(TemplateMail::class, fn (TemplateMail $m) => str_contains($m->renderedSubject, 'cancelled by Jane Doe'));
    }

    public function test_it_lands_on_the_configured_status(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer, $this->pending);
        $byCustomer = OrderStatus::create(['store_id' => 1, 'name' => 'Cancelled by customer', 'sort_order' => 8]);
        Setting::where('group', 'checkout')->where('key', 'customer_cancel_status_id')->update(['value' => (string) $byCustomer->id]);

        $this->actingAs($customer, 'customer')->post(route('account.orders.cancel', $order))->assertRedirect();

        $this->assertSame($byCustomer->id, $order->fresh()->order_status_id);
    }

    public function test_an_order_past_the_allowed_status_is_refused_and_unchanged(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer, $this->processing);

        $this->actingAs($customer, 'customer')
            ->post(route('account.orders.cancel', $order))
            ->assertRedirect(route('account.orders.show', $order))
            ->assertSessionHas('error');

        $this->assertSame($this->processing->id, $order->fresh()->order_status_id);
        Mail::assertNothingSent();
    }

    public function test_someone_elses_order_is_forbidden(): void
    {
        $owner = $this->customer();
        $other = $this->customer('other@example.com');
        $order = $this->order($owner, $this->pending);

        $this->actingAs($other, 'customer')->post(route('account.orders.cancel', $order))->assertForbidden();
        $this->assertSame($this->pending->id, $order->fresh()->order_status_id);
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $order = $this->order($this->customer(), $this->pending);

        $this->post(route('account.orders.cancel', $order))->assertRedirect();
        $this->assertSame($this->pending->id, $order->fresh()->order_status_id);
    }

    public function test_without_a_landing_status_nothing_is_offered_and_nothing_happens(): void
    {
        // No guess by a status name: the setting is the only source. Unset it,
        // and even a ticked status offers no button and refuses a direct POST.
        Setting::where('group', 'checkout')->where('key', 'customer_cancel_status_id')->delete();
        $customer = $this->customer();
        $order = $this->order($customer, $this->pending);

        $this->actingAs($customer, 'customer')->get(route('account.orders.show', $order))->assertOk()->assertDontSee('CANCEL THIS ORDER');

        $this->assertFalse(CustomerOrderCancellation::allowed($order->fresh('status'), $customer));
        $this->actingAs($customer, 'customer')->post(route('account.orders.cancel', $order))->assertSessionHas('error');
        $this->assertSame($this->pending->id, $order->fresh()->order_status_id);
    }

    public function test_the_admin_can_flip_the_switch_per_status(): void
    {
        $this->actingAs(User::factory()->create())
            ->put(route('admin.order-statuses.update-all'), [
                'default_id' => $this->pending->id,
                'rows' => [
                    $this->pending->id    => ['name' => 'Pending', 'deducts_stock' => '1', 'customer_can_cancel' => '0', 'sort_order' => 1],
                    $this->processing->id => ['name' => 'Processing', 'deducts_stock' => '1', 'customer_can_cancel' => '1', 'sort_order' => 2],
                    $this->canceled->id   => ['name' => 'Canceled', 'deducts_stock' => '0', 'customer_can_cancel' => '0', 'sort_order' => 7],
                ],
            ])->assertRedirect();

        $this->assertFalse($this->pending->fresh()->customer_can_cancel);
        $this->assertTrue($this->processing->fresh()->customer_can_cancel);
    }

    public function test_a_deleted_landing_status_offers_nothing_rather_than_guessing(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer, $this->pending);
        $this->canceled->delete();

        $this->assertFalse(CustomerOrderCancellation::allowed($order->fresh('status'), $customer));
        $this->actingAs($customer, 'customer')->post(route('account.orders.cancel', $order))->assertSessionHas('error');
        $this->assertSame($this->pending->id, $order->fresh()->order_status_id);
    }
}
