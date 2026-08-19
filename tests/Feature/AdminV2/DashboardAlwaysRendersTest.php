<?php

namespace Tests\Feature\AdminV2;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Owner ruling, 2026-08-20: the dashboard always renders. A store with nothing
 * to show reports zeros; it does not swap to a different page.
 *
 * It used to have two shapes, chosen by whether the store had ever traded. The
 * reasoning was sound on its face: five saturated blocks reading zero is a poor
 * first impression. What it cost was worse. When a deploy left every order's
 * status snapshot NULL, the dashboard concluded the store had never traded and
 * showed the day-one page, so an outage that hid 27,706 orders looked exactly
 * like a shop that had been wiped. One shape that reports zero would have said
 * "your counts are wrong", which is a bug report rather than a panic.
 *
 * The payment-method cards go at the same time. One card per method restating
 * a share of revenue the figures above already give, and a merchant taking
 * money four ways got four cards for no gain.
 */
class DashboardAlwaysRendersTest extends TestCase
{
    use RefreshDatabase;

    private function dashboard()
    {
        Store::firstOrCreate(['id' => 1], ['name' => 'Test Store', 'code' => 'test']);
        $this->actingAs(User::factory()->create());

        return $this->get('/' . config('admin.prefix', 'admin') . '/dashboard');
    }

    public function test_a_store_with_no_orders_still_gets_the_real_dashboard(): void
    {
        $this->dashboard()
            ->assertOk()
            ->assertSee('Running accounts', false);
    }

    /** The day-one composition is gone entirely, not merely deprioritised. */
    public function test_the_separate_first_run_page_is_gone(): void
    {
        $this->dashboard()
            ->assertOk()
            ->assertDontSee('To open', false);
    }

    /**
     * Seeded with real paid orders across two payment methods, because with an
     * empty store the cards would not have rendered anyway and the assertion
     * would pass without proving anything.
     */
    public function test_the_payment_method_cards_are_gone(): void
    {
        Store::firstOrCreate(['id' => 1], ['name' => 'Test Store', 'code' => 'test']);
        $status = OrderStatus::create([
            'store_id' => 1, 'name' => 'Complete', 'sort_order' => 1,
            'is_default' => true, 'deducts_stock' => true, 'counts_as_revenue' => true,
        ]);

        foreach ([['cod', 500], ['bank_transfer', 900]] as [$method, $total]) {
            Order::create([
                'store_id' => 1, 'order_status_id' => $status->id,
                'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => "{$method}@example.com",
                'payment_method' => $method, 'subtotal' => $total, 'total' => $total,
            ]);
        }

        $this->dashboard()
            ->assertOk()
            ->assertDontSee('v-chans', false)
            ->assertDontSee('v-chan__', false);
    }
}
