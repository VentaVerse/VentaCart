<?php

namespace Tests\Feature\AdminV2;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Order entry is ONE page, and its panels run in dependency order.
 *
 * Products used to live on a second tab while the shipping method sat on the
 * first. The quote was computed correctly (refreshMethods() fires on every
 * item change), but the page taught a sequence it could not honour: you read
 * down to the shipping method, found an empty control, and had to discover
 * for yourself that the thing which fills it lived on a tab you had not
 * opened. A wizard was considered and rejected: this same view is the EDIT
 * page, where stepping through customer details to change one quantity is
 * hostile, and stepwise commits would break the one-page-one-save doctrine.
 *
 * The rule this pins: no panel asks for something whose inputs sit below it.
 */
class OrderEntryOneStepTest extends TestCase
{
    use RefreshDatabase;

    private const ORDERED = [
        'Customer',          // group drives product pricing
        'Products',          // needs the customer; drives everything below
        'Shipping address',  // where it goes
        'Billing address',   // independent, kept beside the other address
        'Shipping',          // needs products AND address; closes with the ledger
        'Payment and status',// how they settle what the ledger came to
    ];

    /**
     * The panel titles in DOM order. Read off the rendered <h2> rather than by
     * searching for each label: "Shipping" is a substring of "Shipping
     * address" and appears in a dozen field names besides, so a plain strpos
     * finds the wrong occurrence and passes for the wrong reason.
     */
    private function panelTitles(string $html): array
    {
        preg_match_all('/<h2 class="v-panel__title">\s*([^<\n]+)/', $html, $m);

        return array_values(array_filter(array_map('trim', $m[1])));
    }

    private function assertPanelsInDependencyOrder(string $html): void
    {
        $titles = $this->panelTitles($html);

        $this->assertSame(
            self::ORDERED,
            $titles,
            'Order entry panels are not in dependency order.'
        );
    }

    public function test_the_create_page_is_one_step_in_dependency_order(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get(route('admin.orders.create'))
            ->assertOk()
            ->getContent();

        $this->assertPanelsInDependencyOrder($html);
    }

    public function test_the_create_page_has_no_tabs_left(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get(route('admin.orders.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('v-tabs', $html);
        $this->assertStringNotContainsString("tab === 'products'", $html);
        $this->assertStringNotContainsString('Details tab', $html);
    }

    public function test_the_edit_page_renders_the_same_one_step_page(): void
    {
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Processing', 'sort_order' => 2]);
        $order = Order::create([
            'store_id'            => 1,
            'order_status_id'     => $status->id,
            'first_name'          => 'June',
            'last_name'           => 'Sillacos',
            'email'               => 'june@example.com',
            'phone'               => '09171234567',
            'shipping_first_name' => 'June',
            'shipping_last_name'  => 'Sillacos',
            'shipping_address_1'  => '123 Mango St',
            'shipping_city'       => 'Cebu City',
            'shipping_postcode'   => '6000',
            'subtotal'            => 1000,
            'total'               => 1000,
        ]);

        $html = $this->actingAs(User::factory()->create())
            ->get(route('admin.orders.edit', $order))
            ->assertOk()
            ->getContent();

        $this->assertPanelsInDependencyOrder($html);
        $this->assertStringNotContainsString('v-tabs', $html);
    }

    public function test_the_money_closes_the_panel_holding_its_last_input(): void
    {
        // The ledger is a FOOTER RULE, the same component and position the
        // order view uses under its items table. It was briefly a panel of its
        // own titled "Order total", which put a 300px column of figures alone
        // in a full width card and read as an empty box.
        $html = $this->actingAs(User::factory()->create())
            ->get(route('admin.orders.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('v-ledger', $html);
        $this->assertStringNotContainsString('Order total', $html);

        // It must sit AFTER the shipping cost input that completes it.
        $this->assertGreaterThan(
            strpos($html, 'name="shipping_cost"'),
            strpos($html, 'v-ledger'),
            'The ledger renders before the shipping cost that feeds it.'
        );
    }

    public function test_the_shipping_control_explains_itself_with_an_empty_basket(): void
    {
        // It used to render an empty select and say nothing, which read as
        // a broken dropdown rather than a control waiting on its inputs.
        $html = $this->actingAs(User::factory()->create())
            ->get(route('admin.orders.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Quoted once the order has products.', $html);
    }
}
