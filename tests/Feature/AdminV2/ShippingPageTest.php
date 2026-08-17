<?php

namespace Tests\Feature\AdminV2;

use App\Models\Country;
use App\Models\CustomerGroup;
use App\Models\FlatShippingRate;
use App\Models\FreeShippingRule;
use App\Models\GeoZone;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Models\WeightBasedMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class ShippingPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The Origin card is gone: the store's pickup origin moved into the
     * courier-fulfillment module (Settings > Modules > Courier Fulfillment >
     * Pickup addresses), where a store can keep more than one. This page
     * links to it while that module is installed and says nothing about a
     * pickup_* setting either way.
     */
    public function test_page_renders_chain_in_real_order_and_points_at_the_module_owned_origin(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.shipping-settings.index'))
            ->assertOk()->assertSee('v3-embed', false)
            ->assertSeeInOrder(['Free Shipping', 'Weight Based', 'Flat Rate', 'DHL'])
            ->assertDontSee('settings[pickup_city]', false)
            ->assertDontSee('settings[pickup_contact_name]', false);
    }

    /**
     * Owner ruling (2026-08-17): pickup addresses belong to the fulfillment
     * module, and core Shipping is the manual shipping process. The page
     * shows no pickup panel even with the module installed; the module's own
     * hub carries the link instead.
     */
    public function test_the_page_does_not_couple_to_the_module_owned_pickup_addresses(): void
    {
        $this->actingAs(User::factory()->create());
        app(\App\Services\ExtensionManager::class)->install('courier-fulfillment');
        app(\App\Services\ExtensionManager::class)->bootEnabled();

        $this->get(route('admin.shipping-settings.index'))
            ->assertOk()
            ->assertDontSee('Pickup addresses')
            ->assertDontSee(route('admin.fulfillment.pickup-addresses.index'), false);

        $this->get(route('admin.fulfillment.settings'))
            ->assertOk()
            ->assertSee('Pickup addresses')
            ->assertSee(route('admin.fulfillment.pickup-addresses.index'), false);
    }

    public function test_index_lists_four_providers_with_configure_links_to_their_own_pages(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get(route('admin.shipping-settings.index'));

        $response->assertOk();
        $response->assertSee(route('admin.shipping-settings.free-shipping'), false);
        $response->assertSee(route('admin.shipping-settings.weight-based'), false);
        $response->assertSee(route('admin.shipping-settings.flat-rate'), false);
        $response->assertSee(route('admin.shipping-settings.dhl'), false);
    }

    public function test_index_shows_provider_summaries(): void
    {
        $this->actingAs(User::factory()->create());
        Store::firstOrCreate(['id' => 1], ['name' => 'Test Store', 'code' => 'test']);

        FreeShippingRule::create(['name' => 'Big orders', 'is_active' => true, 'sort_order' => 0]);
        FreeShippingRule::create(['name' => 'Promo', 'is_active' => false, 'sort_order' => 1]);

        $method = WeightBasedMethod::create(['name' => 'Domestic', 'admin_name' => 'Domestic', 'is_active' => true, 'sort_order' => 0]);
        $method->rates()->create(['weight_from' => 0, 'weight_to' => 5, 'cost' => 100]);

        FlatShippingRate::create(['name' => 'Standard', 'cost' => 150, 'is_active' => true, 'sort_order' => 0]);
        FlatShippingRate::create(['name' => 'Pickup', 'cost' => 0, 'is_active' => true, 'sort_order' => 1]);

        $response = $this->get(route('admin.shipping-settings.index'));

        $response->assertOk();
        $response->assertSee('1 active rule');
        $response->assertSee('1 rate tier');
        $response->assertSee('2 flat rates');
        $response->assertSee('Not configured');
    }

    public function test_dhl_summary_shows_configured_when_credentials_present(): void
    {
        $this->actingAs(User::factory()->create());
        Setting::updateOrCreate(
            ['group' => 'shipping', 'key' => 'dhl_site_id'],
            ['value' => Crypt::encryptString('siteid123')]
        );
        Setting::updateOrCreate(
            ['group' => 'shipping', 'key' => 'dhl_password'],
            ['value' => Crypt::encryptString('secretpass')]
        );

        $response = $this->get(route('admin.shipping-settings.index'));

        $response->assertOk()->assertSee('Configured')->assertDontSee('Not configured');
    }

    public function test_free_shipping_card_has_no_enable_toggle_but_shows_active_state(): void
    {
        $this->actingAs(User::factory()->create());

        FreeShippingRule::create([
            'name' => 'Big orders', 'is_active' => true, 'sort_order' => 0,
        ]);

        $response = $this->get(route('admin.shipping-settings.index'));
        $response->assertOk();
        $response->assertDontSee('settings[free_shipping_enabled]', false);
    }

    /**
     * The standard row (owner ruling 2026-08-18): each switchable provider
     * posts to its own enable/disable endpoint — no batched toggles. Free
     * Shipping has no switch (rule-driven), so no endpoint renders for it.
     */
    public function test_provider_rows_carry_the_standard_switch(): void
    {
        $this->actingAs(User::factory()->create());

        $page = $this->get(route('admin.shipping-settings.index'))->assertOk();
        $page->assertSee(route('admin.shipping-settings.enable', 'weight-based'), false);
        $page->assertSee(route('admin.shipping-settings.enable', 'flat-rate'), false);
        $page->assertSee(route('admin.shipping-settings.enable', 'dhl'), false);
        $page->assertDontSee('v3-toggle', false);

        $this->post(route('admin.shipping-settings.enable', 'flat-rate'))
            ->assertRedirect(route('admin.shipping-settings.index'));

        $this->assertDatabaseHas('settings', ['group' => 'shipping', 'key' => 'flat_rate_enabled', 'value' => '1']);

        $this->get(route('admin.shipping-settings.index'))
            ->assertSee(route('admin.shipping-settings.disable', 'flat-rate'), false);

        $this->post(route('admin.shipping-settings.disable', 'flat-rate'));
        $this->assertDatabaseHas('settings', ['group' => 'shipping', 'key' => 'flat_rate_enabled', 'value' => '0']);

        $refused = $this->post('/'.config('admin.prefix', 'admin').'/extensions/shipping/free-shipping/enable');
        $this->assertGreaterThanOrEqual(400, $refused->status());
    }

    // ==================== Per-provider pages ====================

    public function test_free_shipping_page_renders_in_new_shell_and_shows_rules(): void
    {
        $this->actingAs(User::factory()->create());

        FreeShippingRule::create(['name' => 'Big orders', 'is_active' => true, 'sort_order' => 0]);

        $response = $this->get(route('admin.shipping-settings.free-shipping'));

        $response->assertOk()->assertSee('v3-embed', false)->assertSee('Big orders');
    }

    public function test_weight_based_page_renders_in_new_shell_with_bulk_update_field_names(): void
    {
        $this->actingAs(User::factory()->create());
        Store::firstOrCreate(['id' => 1], ['name' => 'Test Store', 'code' => 'test']);
        GeoZone::create(['name' => 'Metro Manila', 'is_active' => true]);
        CustomerGroup::create(['store_id' => 1, 'name' => 'Resellers']);
        $method = WeightBasedMethod::create([
            'name' => 'Domestic', 'admin_name' => 'Domestic', 'is_active' => true, 'sort_order' => 0,
        ]);

        $response = $this->get(route('admin.shipping-settings.weight-based'));

        $response->assertOk()->assertSee('v3-embed', false);
        $response->assertSee('methods[' . $method->id . '][name]', false);
        $response->assertSee('methods[' . $method->id . '][admin_name]', false);
        $response->assertSee('methods[' . $method->id . '][is_active]', false);
        $response->assertSee('methods[' . $method->id . '][sort_order]', false);
        $response->assertSee('methods[' . $method->id . '][geo_zone_ids][]', false);
        $response->assertSee('methods[' . $method->id . '][customer_group_ids][]', false);
        $response->assertSee(route('admin.weight-based-methods.bulk-update'), false);
    }

    public function test_flat_rate_page_renders_in_new_shell_and_shows_zone_and_group_names(): void
    {
        $this->actingAs(User::factory()->create());
        Store::create(['id' => 1, 'name' => 'Test Store', 'code' => 'test']);
        $zone = GeoZone::create(['name' => 'Metro Manila', 'is_active' => true]);
        $group = CustomerGroup::create(['store_id' => 1, 'name' => 'Resellers']);
        FlatShippingRate::create([
            'name' => 'Standard', 'geo_zone_id' => $zone->id, 'customer_group_id' => $group->id,
            'cost' => 150, 'is_active' => true, 'sort_order' => 0,
        ]);

        $response = $this->get(route('admin.shipping-settings.flat-rate'));

        $response->assertOk()->assertSee('v3-embed', false)
            ->assertSee('Standard')->assertSee('Metro Manila')->assertSee('Resellers');
    }

    public function test_dhl_page_renders_in_new_shell_with_masked_credentials(): void
    {
        $this->actingAs(User::factory()->create());
        $plain = 'supersecretsiteid';
        Setting::updateOrCreate(
            ['group' => 'shipping', 'key' => 'dhl_site_id'],
            ['value' => Crypt::encryptString($plain)]
        );
        $expectedMask = str_repeat('*', strlen($plain) - 4) . substr($plain, -4);

        $response = $this->get(route('admin.shipping-settings.dhl'));

        $response->assertOk()->assertSee('v3-embed', false);
        $response->assertDontSee($plain);
        $response->assertSee($expectedMask);
    }

    public function test_dhl_credentials_save_roundtrip(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.settings.update'), [
            'group' => 'shipping',
            '_redirect' => route('admin.shipping-settings.dhl'),
            'settings' => [
                'dhl_account_number' => '642344995',
            ],
        ])->assertRedirect(route('admin.shipping-settings.dhl'));

        $this->assertDatabaseHas('settings', [
            'group' => 'shipping', 'key' => 'dhl_account_number', 'value' => '642344995',
        ]);
    }

    // ==================== Legacy ?tab= redirect ====================

    public function test_legacy_tab_query_redirects_to_flat_rate_page(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.shipping-settings.index', ['tab' => 'flat-rate']))
            ->assertRedirect(route('admin.shipping-settings.flat-rate'));
    }

    public function test_legacy_tab_query_redirects_to_weight_based_page(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.shipping-settings.index', ['tab' => 'weight-based']))
            ->assertRedirect(route('admin.shipping-settings.weight-based'));
    }

    public function test_legacy_tab_query_redirects_to_free_shipping_page(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.shipping-settings.index', ['tab' => 'free-shipping']))
            ->assertRedirect(route('admin.shipping-settings.free-shipping'));
    }

    public function test_legacy_tab_query_redirects_to_dhl_page(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.shipping-settings.index', ['tab' => 'dhl']))
            ->assertRedirect(route('admin.shipping-settings.dhl'));
    }

    public function test_unknown_tab_query_does_not_redirect(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.shipping-settings.index', ['tab' => 'bogus']))
            ->assertOk();
    }

    // ==================== Rate tester stays on index ====================

    public function test_rate_tester_returns_quotes_flash(): void
    {
        $this->actingAs(User::factory()->create());
        Store::firstOrCreate(['id' => 1], ['name' => 'Test Store', 'code' => 'test']);

        $country = Country::firstOrCreate(
            ['iso2' => 'PH'],
            ['iso3' => 'PHL', 'name' => 'Philippines', 'phone_code' => '+63', 'is_active' => true]
        );

        $response = $this->from(route('admin.shipping-settings.index'))
            ->post(route('admin.shipping-settings.test'), [
                'country_id' => $country->id,
                'province' => 'Metro Manila',
                'city' => 'Quezon City',
                'weight' => 1.5,
                'subtotal' => 2500,
            ]);

        $response->assertRedirect(route('admin.shipping-settings.index'));
        $response->assertSessionHas('rate_test');
    }
}
