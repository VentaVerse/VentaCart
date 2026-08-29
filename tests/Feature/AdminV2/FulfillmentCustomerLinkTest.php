<?php

namespace Tests\Feature\AdminV2;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\User;
use App\Services\ExtensionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * The customer on a fulfillment row is a link to their admin record when an
 * account stands behind the order (owner request, 2026-08-29). A guest
 * order, or one whose account has since been deleted, stays a name rather
 * than a link to a 404.
 */
class FulfillmentCustomerLinkTest extends TestCase
{
    use RefreshDatabase;

    private function queueUrl(): string
    {
        return '/' . config('admin.prefix', 'admin') . '/extensions/courier-fulfillment';
    }

    private function boot(): void
    {
        app(ExtensionManager::class)->install('courier-fulfillment');
        app(ExtensionManager::class)->bootEnabled();
        Setting::create(['group' => 'courier', 'key' => 'quadx_enabled', 'value' => '1']);
        Setting::create(['group' => 'courier', 'key' => 'quadx_mode', 'value' => 'staging']);
        Setting::create(['group' => 'courier', 'key' => 'quadx_staging_api_key', 'value' => Crypt::encryptString('k')]);
        Setting::create(['group' => 'courier', 'key' => 'quadx_staging_secret_key', 'value' => Crypt::encryptString('s')]);
    }

    private function toPack(): OrderStatus
    {
        $status = OrderStatus::firstOrCreate(['store_id' => 1, 'name' => 'Processing'], ['sort_order' => 2]);
        \Extensions\CourierFulfillment\PlacementMap::put($status->id, 'to_pack');
        \Tests\Support\FulfillmentSettings::selectFor('to_pack', (int) $status->id);

        return $status;
    }

    private function order(?int $customerId): Order
    {
        return Order::create([
            'store_id'            => 1,
            'order_status_id'     => $this->toPack()->id,
            'customer_id'         => $customerId,
            'first_name'          => 'June',
            'last_name'           => 'Sillacos',
            'email'               => 'june@example.com',
            'phone'               => '09171234567',
            'shipping_first_name' => 'June',
            'shipping_last_name'  => 'Sillacos',
            'shipping_address_1'  => '123 Mango St',
            'shipping_city'       => 'Cebu City',
            'shipping_state'      => 'Cebu',
            'shipping_postcode'   => '6000',
            'payment_method'      => 'bank_transfer',
            'subtotal'            => 1000,
            'shipping_cost'       => 100,
            'total'               => 1100,
        ]);
    }

    private function customer(): Customer
    {
        $group = CustomerGroup::create(['store_id' => 1, 'name' => 'Default', 'is_default' => true]);

        return Customer::create([
            'store_id' => 1, 'customer_group_id' => $group->id,
            'first_name' => 'June', 'last_name' => 'Sillacos',
            'email' => 'june@example.com', 'password' => bcrypt('x'),
        ]);
    }

    public function test_an_account_holders_name_links_to_their_admin_record(): void
    {
        $this->boot();
        $customer = $this->customer();
        $order = $this->order($customer->id);

        $this->actingAs(User::factory()->create())
            ->get($this->queueUrl() . '?filter=to_pack')
            ->assertOk()
            ->assertSee('#' . $order->id, false)
            ->assertSee(route('admin.customers.edit', $customer), false);
    }

    public function test_a_guest_order_stays_a_name(): void
    {
        $this->boot();
        $order = $this->order(null);

        $this->actingAs(User::factory()->create())
            ->get($this->queueUrl() . '?filter=to_pack')
            ->assertOk()
            ->assertSee('#' . $order->id, false)
            ->assertDontSee('/customers/customers/', false);
    }

    public function test_a_deleted_account_stays_a_name(): void
    {
        $this->boot();
        $customer = $this->customer();
        $order = $this->order($customer->id);
        $customer->delete();

        $this->actingAs(User::factory()->create())
            ->get($this->queueUrl() . '?filter=to_pack')
            ->assertOk()
            ->assertSee('#' . $order->id, false)
            ->assertDontSee('/customers/customers/' . $customer->id . '/edit', false);
    }
}
