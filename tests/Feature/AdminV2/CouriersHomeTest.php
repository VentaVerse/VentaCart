<?php

namespace Tests\Feature\AdminV2;

use App\Models\Setting;
use App\Models\ShippingCourier;
use App\Models\User;
use App\Services\ExtensionManager;
use App\Support\AdminNav;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeCourierFulfillment;
use Tests\TestCase;

/**
 * Couriers has one home: Sales (owner ruling, 2026-08-28). It is the store's
 * courier directory, order vocabulary like statuses, and core: the order
 * form and the shopper's tracking link read it with no fulfillment module
 * installed. It was filed under Settings and named "Couriers & Tracking",
 * which read as a shipping-rate page and, worse, as a notifier. The
 * fulfillment module does not own it and does not copy it; it points at it.
 */
class CouriersHomeTest extends TestCase
{
    use RefreshDatabase;

    private function prefix(): string
    {
        return config('admin.prefix', 'admin');
    }

    protected function tearDown(): void
    {
        if (class_exists(\Extensions\CourierFulfillment\CourierManager::class, false)) {
            $ref = new \ReflectionProperty(\Extensions\CourierFulfillment\CourierManager::class, 'registered');
            $ref->setAccessible(true);
            $registered = $ref->getValue();
            unset($registered['fake']);
            $ref->setValue(null, $registered);
        }
        parent::tearDown();
    }

    public function test_couriers_sits_under_sales_in_the_one_nav(): void
    {
        $sales = collect(AdminNav::groups(null))->firstWhere('label', 'Sales');
        $keys = collect($sales['items'])->pluck('key')->all();

        $this->assertContains('couriers', $keys);
        $this->assertContains('orders', $keys);

        // And nothing in the Settings space still claims it.
        foreach (config('admin-settings-nav') as $group) {
            $this->assertNotContains('couriers', collect($group['items'])->pluck('key')->all());
        }
    }

    public function test_the_url_grammar_lights_the_sales_item(): void
    {
        $this->assertSame(
            ['group' => 'Sales', 'item' => 'couriers'],
            AdminNav::activeItemKey($this->prefix() . '/sales/couriers')
        );
    }

    public function test_the_page_is_named_for_what_it_is_and_wears_the_classic_shell(): void
    {
        ShippingCourier::create(['store_id' => 1, 'name' => 'LBC Express', 'tracking_url' => 'https://lbc.test/{tracking_number}', 'is_active' => true, 'sort_order' => 1]);

        $this->actingAs(User::factory()->create())
            ->get('/' . $this->prefix() . '/sales/couriers')
            ->assertOk()
            ->assertSee('v-embed', false)
            ->assertSee('LBC Express')
            ->assertDontSee('Couriers &amp; Tracking', false)
            ->assertDontSee('Couriers & Tracking', false);
    }

    public function test_the_fulfillment_space_points_at_the_directory_rather_than_copying_it(): void
    {
        app(ExtensionManager::class)->install('courier-fulfillment');
        app(ExtensionManager::class)->bootEnabled();

        $this->actingAs(User::factory()->create())
            ->get('/' . $this->prefix() . '/extensions/courier-fulfillment/settings')
            ->assertOk()
            ->assertSee(route('admin.shipping-couriers.index'), false);
    }

    public function test_the_queue_says_where_to_add_a_courier_when_the_directory_is_empty(): void
    {
        app(ExtensionManager::class)->install('courier-fulfillment');
        app(ExtensionManager::class)->bootEnabled();
        \Extensions\CourierFulfillment\CourierManager::register(FakeCourierFulfillment::class);
        Setting::create(['group' => 'courier', 'key' => 'fake_enabled', 'value' => '1']);

        $url = '/' . $this->prefix() . '/extensions/courier-fulfillment';

        // No courier rows: the manual card is replaced by the off-card that
        // names the fix, instead of the option silently not existing.
        $this->actingAs(User::factory()->create())
            ->get($url)
            ->assertOk()
            ->assertSee('No couriers yet')
            ->assertSee(route('admin.shipping-couriers.index'), false);

        // One row: the real card is back and the off-card is gone.
        ShippingCourier::create(['store_id' => 1, 'name' => 'LBC Express', 'is_active' => true, 'sort_order' => 1]);

        $this->get($url)
            ->assertOk()
            ->assertDontSee('No couriers yet')
            ->assertSee('data-courier-card="__manual" @click', false);
    }
}
