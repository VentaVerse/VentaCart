<?php

namespace Tests\Feature\Storefront;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Store;
use App\Support\OrderPanels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Tests\Fixtures\Extensions\SampleExtension\Orders\SampleOrderPanels;
use Tests\Support\UsesSampleExtension;
use Tests\TestCase;

/**
 * Fragments extensions add to the customer's order pages, drawn by the
 * theme in fixed places. The sample theme draws all three slots.
 */
class OrderPanelsTest extends TestCase
{
    use RefreshDatabase;
    use UsesSampleExtension;

    private Customer $customer;

    /** @var list<Order> */
    private array $orders = [];

    protected function setUp(): void
    {
        parent::setUp();

        $store = Store::create(['name' => 'Demo Store', 'code' => 'demo']);
        $this->customer = Customer::create([
            'store_id' => $store->id, 'first_name' => 'Maria', 'last_name' => 'Santos',
            'email' => 'maria@example.test', 'password' => bcrypt('secret'),
        ]);
        $status = OrderStatus::create(['store_id' => $store->id, 'name' => 'Pending', 'is_default' => true, 'sort_order' => 1]);
        foreach ([1, 2, 3] as $n) {
            $this->orders[] = Order::create([
                'store_id' => $store->id, 'customer_id' => $this->customer->id, 'order_status_id' => $status->id,
                'first_name' => 'Maria', 'last_name' => 'Santos', 'email' => 'maria@example.test', 'total' => 100 * $n,
            ]);
        }
    }

    public function test_without_registrations_nothing_is_drawn(): void
    {
        $this->assertSame([], OrderPanels::rows(Order::all()));
        $this->assertSame('', OrderPanels::render(OrderPanels::DETAIL_SUMMARY, $this->orders[0])->toHtml());

        $this->actingAs($this->customer, 'customer')->get(route('account.orders'))
            ->assertOk()
            ->assertDontSee('account-order-panel', false);
        $this->actingAs($this->customer, 'customer')->get(route('account.orders.show', $this->orders[0]))
            ->assertOk()
            ->assertDontSee('data-sample-summary', false);
    }

    public function test_the_list_slot_runs_once_for_the_page_and_draws_under_each_row(): void
    {
        $this->installSampleExtension();
        SampleOrderPanels::$listCalls = 0;

        $response = $this->actingAs($this->customer, 'customer')->get(route('account.orders'))->assertOk();

        $this->assertSame(1, SampleOrderPanels::$listCalls, 'One call for the whole page, so an extension can batch its reads.');
        foreach ($this->orders as $order) {
            $response->assertSee('<tr class="account-order-panel"><td colspan="6"><p data-sample-row="' . $order->id . '">Sold by Demo Store</p></td></tr>', false);
        }
    }

    public function test_the_detail_slots_draw_on_the_order_page(): void
    {
        $this->installSampleExtension();
        $order = $this->orders[1];

        $this->actingAs($this->customer, 'customer')->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSee('<p data-sample-summary>Order ' . $order->id . ' is sold by Demo Store</p>', false)
            ->assertSee('<a data-sample-action href="' . url('/sample-extension/returns/' . $order->id) . '">Return an item</a>', false);
    }

    public function test_fragments_for_orders_not_on_the_page_and_odd_answers_are_dropped(): void
    {
        OrderPanels::add(OrderPanels::ROW_AFTER, fn ($orders) => [
            $this->orders[0]->id => '<b>one</b>',
            999 => '<b>not on this page</b>',
            $this->orders[1]->id => 42,
        ]);
        OrderPanels::add(OrderPanels::ROW_AFTER, fn ($orders) => [$this->orders[0]->id => new HtmlString('<i>two</i>')]);
        OrderPanels::add(OrderPanels::ROW_AFTER, fn ($orders) => 'not a list');

        $rows = OrderPanels::rows(Order::query()->paginate(15));

        $this->assertSame([$this->orders[0]->id], array_keys($rows));
        $this->assertSame('<b>one</b><i>two</i>', $rows[$this->orders[0]->id]->toHtml(), 'Joined in the order added.');
    }

    public function test_a_failing_panel_is_reported_and_the_rest_still_draw(): void
    {
        $reported = [];
        $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)->reportable(function (\Throwable $e) use (&$reported) {
            $reported[] = $e->getMessage();
        });
        OrderPanels::add(OrderPanels::DETAIL_ACTIONS, fn (Order $order) => throw new \RuntimeException('panel broke'));
        OrderPanels::add(OrderPanels::DETAIL_ACTIONS, fn (Order $order) => '<a href="#">Contact</a>');

        $this->assertSame('<a href="#">Contact</a>', OrderPanels::render(OrderPanels::DETAIL_ACTIONS, $this->orders[0])->toHtml());
        $this->assertSame(['panel broke'], $reported);
    }

    public function test_an_unknown_slot_is_refused_and_the_list_slot_is_not_a_detail_slot(): void
    {
        Log::spy();
        OrderPanels::add('order.somewhere', fn () => 'x');
        OrderPanels::add(OrderPanels::ROW_AFTER, fn () => [1 => 'x']);

        $this->assertFalse(OrderPanels::has('order.somewhere'));
        $this->assertSame('', OrderPanels::render(OrderPanels::ROW_AFTER, $this->orders[0])->toHtml());
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_switched_off_nothing_is_drawn(): void
    {
        $this->installSampleExtension();
        $this->switchOffSampleExtension();

        $this->actingAs($this->customer, 'customer')->get(route('account.orders'))
            ->assertOk()
            ->assertDontSee('data-sample-row', false);
    }
}
