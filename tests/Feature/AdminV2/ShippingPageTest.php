<?php

namespace Tests\Feature\AdminV2;

use App\Models\CustomerGroup;
use App\Models\FlatShippingRate;
use App\Models\FreeShippingRule;
use App\Models\GeoZone;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Models\WeightBasedMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_renders_chain_in_real_order_with_origin_card(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.shipping-settings.index'))
            ->assertOk()->assertSee('v-admin', false)
            ->assertSeeInOrder(['Free Shipping', 'Weight Based', 'Flat Rate', 'DHL'])
            ->assertSee('Origin')->assertSee('settings[pickup_city]', false);
    }

    public function test_free_shipping_card_has_no_enable_toggle_but_shows_active_state(): void
    {
        $this->actingAs(User::factory()->create());

        FreeShippingRule::create([
            'name' => 'Big orders', 'is_active' => true, 'sort_order' => 0,
        ]);

        $response = $this->get(route('admin.shipping-settings.index'));
        $response->assertOk()->assertSee('Big orders');
        $response->assertDontSee('settings[free_shipping_enabled]', false);
    }

    public function test_provider_enable_toggle_forms_post_to_settings_update(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.shipping-settings.index'))
            ->assertOk()
            ->assertSee('settings[weight_based_enabled]', false)
            ->assertSee('settings[flat_rate_enabled]', false)
            ->assertSee('settings[dhl_enabled]', false);
    }

    public function test_flat_rate_table_shows_zone_and_group_names(): void
    {
        $this->actingAs(User::factory()->create());
        Store::create(['id' => 1, 'name' => 'Test Store', 'code' => 'test']);
        $zone = GeoZone::create(['name' => 'Metro Manila', 'is_active' => true]);
        $group = CustomerGroup::create(['store_id' => 1, 'name' => 'Resellers']);
        FlatShippingRate::create([
            'name' => 'Standard', 'geo_zone_id' => $zone->id, 'customer_group_id' => $group->id,
            'cost' => 150, 'is_active' => true, 'sort_order' => 0,
        ]);

        $this->get(route('admin.shipping-settings.index'))
            ->assertOk()->assertSee('Standard')->assertSee('Metro Manila')->assertSee('Resellers');
    }

    public function test_weight_based_bulk_update_payload_field_names_unchanged(): void
    {
        $this->actingAs(User::factory()->create());
        Store::firstOrCreate(['id' => 1], ['name' => 'Test Store', 'code' => 'test']);
        GeoZone::create(['name' => 'Metro Manila', 'is_active' => true]);
        CustomerGroup::create(['store_id' => 1, 'name' => 'Resellers']);
        $method = WeightBasedMethod::create([
            'name' => 'Domestic', 'admin_name' => 'Domestic', 'is_active' => true, 'sort_order' => 0,
        ]);

        $response = $this->get(route('admin.shipping-settings.index'));
        $response->assertOk();
        $response->assertSee('methods[' . $method->id . '][name]', false);
        $response->assertSee('methods[' . $method->id . '][admin_name]', false);
        $response->assertSee('methods[' . $method->id . '][is_active]', false);
        $response->assertSee('methods[' . $method->id . '][sort_order]', false);
        $response->assertSee('methods[' . $method->id . '][geo_zone_ids][]', false);
        $response->assertSee('methods[' . $method->id . '][customer_group_ids][]', false);
        $response->assertSee(route('admin.weight-based-methods.bulk-update'), false);
    }

    public function test_dhl_credentials_are_masked_not_shown_plain(): void
    {
        $this->actingAs(User::factory()->create());
        $plain = 'supersecretsiteid';
        Setting::updateOrCreate(
            ['group' => 'shipping', 'key' => 'dhl_site_id'],
            ['value' => \Illuminate\Support\Facades\Crypt::encryptString($plain)]
        );
        $expectedMask = str_repeat('*', strlen($plain) - 4) . substr($plain, -4);

        $response = $this->get(route('admin.shipping-settings.index'));
        $response->assertOk();
        $response->assertDontSee($plain);
        $response->assertSee($expectedMask);
    }

    public function test_origin_form_roundtrip_saves_pickup_fields(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.settings.update'), [
            'group' => 'shipping',
            '_redirect' => route('admin.shipping-settings.index'),
            'settings' => [
                'pickup_city' => 'Makati',
                'pickup_province' => 'Metro Manila',
                'pickup_contact_name' => 'Warehouse Team',
            ],
        ])->assertRedirect(route('admin.shipping-settings.index'));

        $this->assertDatabaseHas('settings', [
            'group' => 'shipping', 'key' => 'pickup_city', 'value' => 'Makati',
        ]);
    }
}
