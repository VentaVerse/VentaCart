<?php

namespace Tests\Feature\AdminV2;

use App\Models\Country;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Services\ExtensionManager;
use Extensions\CourierFulfillment\Console\Commands\ImportPickupAddressFromSettings;
use Extensions\CourierFulfillment\Models\PickupAddress;
use Extensions\CourierFulfillment\Support\PickupCountry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Pickup addresses: the module-owned CRUD that replaced the eight flat
 * settings(group=shipping, key=pickup_*) rows a store could only ever have
 * one of.
 *
 * The payload half of this change (both couriers still building exactly the
 * block they built from the settings) is pinned separately in
 * tests/Unit/CourierPickupAddressPayloadTest.php.
 */
class FulfillmentPickupAddressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Store::create(['id' => 1, 'name' => 'Test Store', 'code' => 'test']);
        app(ExtensionManager::class)->install('courier-fulfillment');
        app(ExtensionManager::class)->bootEnabled();

        // The console kernel has already resolved its command list by the
        // time an extension provider boots inside a test, so the command is
        // registered explicitly here - the same thing
        // FulfillmentController::poll() does for courier:poll-status. On a
        // real CLI run the provider boots first and no such step is needed.
        app(\Illuminate\Contracts\Console\Kernel::class)
            ->registerCommand(new ImportPickupAddressFromSettings());

        // The countries table is empty under RefreshDatabase (it is seeded,
        // not migrated), so the two rows these tests need are created here.
        // A second country exists purely so "the country is always the PH
        // row" can be tested against a real, forgeable alternative id.
        Country::create(['iso2' => 'PH', 'iso3' => 'PHL', 'name' => 'Philippines', 'phone_code' => '63', 'is_active' => true]);
        Country::create(['iso2' => 'US', 'iso3' => 'USA', 'name' => 'United States', 'phone_code' => '1', 'is_active' => true]);
    }

    private function admin(): User
    {
        return User::factory()->create();
    }

    /** @return array<string, mixed> */
    private function formData(array $overrides = []): array
    {
        return array_merge([
            'label'         => 'Main warehouse',
            'contact_name'  => 'Warehouse Team',
            'contact_phone' => '09171112222',
            'address_1'     => '456 Warehouse Ave',
            'state'         => 'Metro Manila',
            'city'          => 'Makati',
            'district'      => null,
            'barangay'      => 'Bel-Air',
            'postcode'      => '1200',
        ], $overrides);
    }

    private function makeAddress(array $overrides = []): PickupAddress
    {
        return PickupAddress::create(array_merge($this->formData(), [
            'country_id' => Country::where('iso2', 'PH')->value('id'),
        ], $overrides));
    }

    // ------------------------------------------------------------------
    // The table belongs to the module, not to core.
    // ------------------------------------------------------------------

    public function test_the_table_is_created_by_the_extension_and_dropped_on_uninstall(): void
    {
        $this->assertTrue(Schema::hasTable('courier_fulfillment_pickup_addresses'));

        app(ExtensionManager::class)->uninstall('courier-fulfillment');

        $this->assertFalse(Schema::hasTable('courier_fulfillment_pickup_addresses'));
    }

    // ------------------------------------------------------------------
    // CRUD round trip.
    // ------------------------------------------------------------------

    public function test_the_index_stands_in_for_an_empty_list(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.fulfillment.pickup-addresses.index'))
            ->assertOk()
            ->assertSee('No pickup address yet');
    }

    public function test_crud_round_trip(): void
    {
        $admin = $this->admin();

        // Create.
        $this->actingAs($admin)
            ->post(route('admin.fulfillment.pickup-addresses.store'), $this->formData())
            ->assertRedirect(route('admin.fulfillment.pickup-addresses.index'));

        $address = PickupAddress::first();
        $this->assertNotNull($address);
        $this->assertSame('Main warehouse', $address->label);
        $this->assertSame('Warehouse Team', $address->contact_name);
        $this->assertSame('09171112222', $address->contact_phone);
        // The very first address holds the default whether or not the form
        // asked: a store with addresses and no default cannot book at all.
        $this->assertTrue($address->is_default);

        // Read (index + edit form).
        $this->actingAs($admin)
            ->get(route('admin.fulfillment.pickup-addresses.index'))
            ->assertOk()
            ->assertSee('Main warehouse')
            ->assertSee('Warehouse Team');

        $this->actingAs($admin)
            ->get(route('admin.fulfillment.pickup-addresses.edit', $address))
            ->assertOk()
            ->assertSee('456 Warehouse Ave');

        // Update.
        $this->actingAs($admin)
            ->put(route('admin.fulfillment.pickup-addresses.update', $address), $this->formData([
                'label'     => 'Renamed warehouse',
                'address_1' => '789 Depot Road',
            ]))
            ->assertRedirect(route('admin.fulfillment.pickup-addresses.index'));

        $address->refresh();
        $this->assertSame('Renamed warehouse', $address->label);
        $this->assertSame('789 Depot Road', $address->address_1);

        // Delete (needs a second address, see the last-address rule below).
        $second = $this->makeAddress(['label' => 'Spare depot']);

        $this->actingAs($admin)
            ->delete(route('admin.fulfillment.pickup-addresses.destroy', $second))
            ->assertRedirect();

        $this->assertNull(PickupAddress::find($second->id));
        $this->assertSame(1, PickupAddress::count());
    }

    public function test_the_create_form_renders(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.fulfillment.pickup-addresses.create'))
            ->assertOk()
            ->assertSee('Mobile number');
    }

    // ------------------------------------------------------------------
    // Exactly one default.
    // ------------------------------------------------------------------

    public function test_exactly_one_address_is_default_however_many_ask_for_it(): void
    {
        $admin = $this->admin();

        foreach (['One', 'Two', 'Three'] as $label) {
            $this->actingAs($admin)->post(
                route('admin.fulfillment.pickup-addresses.store'),
                $this->formData(['label' => $label, 'is_default' => '1'])
            );
        }

        $this->assertSame(3, PickupAddress::count());
        $this->assertSame(1, PickupAddress::where('is_default', true)->count());
        // Last one to claim it holds it.
        $this->assertSame('Three', PickupAddress::where('is_default', true)->value('label'));
    }

    public function test_promoting_an_address_demotes_the_previous_default(): void
    {
        $first = $this->makeAddress(['label' => 'First']);
        $second = $this->makeAddress(['label' => 'Second']);

        $this->assertTrue($first->fresh()->is_default);
        $this->assertFalse($second->fresh()->is_default);

        $this->actingAs($this->admin())
            ->post(route('admin.fulfillment.pickup-addresses.default', $second))
            ->assertRedirect();

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
        $this->assertSame(1, PickupAddress::where('is_default', true)->count());
    }

    public function test_saving_a_non_default_address_never_steals_the_flag(): void
    {
        $first = $this->makeAddress(['label' => 'First']);
        $second = $this->makeAddress(['label' => 'Second']);

        $this->actingAs($this->admin())->put(
            route('admin.fulfillment.pickup-addresses.update', $second),
            $this->formData(['label' => 'Second renamed'])
        );

        $this->assertTrue($first->fresh()->is_default);
        $this->assertFalse($second->fresh()->is_default);
    }

    // ------------------------------------------------------------------
    // Delete rules.
    // ------------------------------------------------------------------

    public function test_the_last_remaining_address_cannot_be_deleted(): void
    {
        $only = $this->makeAddress();

        $this->actingAs($this->admin())
            ->delete(route('admin.fulfillment.pickup-addresses.destroy', $only))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNotNull(PickupAddress::find($only->id));
    }

    public function test_deleting_the_default_promotes_the_oldest_remaining_address(): void
    {
        $first = $this->makeAddress(['label' => 'First']);
        $second = $this->makeAddress(['label' => 'Second']);
        $third = $this->makeAddress(['label' => 'Third']);

        $this->assertTrue($first->fresh()->is_default);

        $this->actingAs($this->admin())
            ->delete(route('admin.fulfillment.pickup-addresses.destroy', $first))
            ->assertRedirect();

        $this->assertNull(PickupAddress::find($first->id));
        $this->assertTrue($second->fresh()->is_default);
        $this->assertFalse($third->fresh()->is_default);
        $this->assertSame(1, PickupAddress::where('is_default', true)->count());
    }

    // ------------------------------------------------------------------
    // The country is fixed.
    // ------------------------------------------------------------------

    public function test_the_country_is_the_ph_row_resolved_by_iso(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.fulfillment.pickup-addresses.store'), $this->formData());

        $this->assertSame(
            Country::where('iso2', PickupCountry::ISO)->value('id'),
            PickupAddress::first()->country_id
        );
    }

    public function test_a_forged_country_id_never_reaches_the_record(): void
    {
        $us = Country::where('iso2', 'US')->value('id');
        $ph = Country::where('iso2', 'PH')->value('id');

        $this->actingAs($this->admin())->post(
            route('admin.fulfillment.pickup-addresses.store'),
            $this->formData(['country_id' => $us])
        );

        $address = PickupAddress::first();
        $this->assertSame($ph, $address->country_id);

        // And the same on edit.
        $this->actingAs($this->admin())->put(
            route('admin.fulfillment.pickup-addresses.update', $address),
            $this->formData(['country_id' => $us])
        );

        $this->assertSame($ph, $address->fresh()->country_id);
    }

    public function test_the_form_shows_the_country_but_offers_no_control_for_it(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.fulfillment.pickup-addresses.create'))
            ->assertOk()
            ->assertSee('Philippines')
            ->assertDontSee('name="country_id"', false);
    }

    public function test_a_missing_country_row_fails_loudly_instead_of_writing_a_null(): void
    {
        Country::where('iso2', 'PH')->update(['is_active' => false]);

        $this->actingAs($this->admin())
            ->get(route('admin.fulfillment.pickup-addresses.create'))
            ->assertOk()
            ->assertSee('missing or inactive');

        $this->actingAs($this->admin())
            ->post(route('admin.fulfillment.pickup-addresses.store'), $this->formData())
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, PickupAddress::count());
    }

    // ------------------------------------------------------------------
    // Validation.
    // ------------------------------------------------------------------

    public function test_the_contact_name_and_mobile_number_are_required(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.fulfillment.pickup-addresses.store'), $this->formData([
                'contact_name'  => '',
                'contact_phone' => '',
            ]))
            ->assertSessionHasErrors(['contact_name', 'contact_phone']);
    }

    /**
     * The form accepts exactly the shapes the courier payload accepts,
     * because both go through Support\PhPhone.
     */
    public function test_the_mobile_number_accepts_the_ph_formats_the_couriers_accept(): void
    {
        foreach (['09171112222', '+63 917 111 2222', '639171112222', '9171112222'] as $i => $phone) {
            $this->actingAs($this->admin())
                ->post(route('admin.fulfillment.pickup-addresses.store'), $this->formData([
                    'label'         => 'Depot ' . $i,
                    'contact_phone' => $phone,
                ]))
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(4, PickupAddress::count());
    }

    public function test_a_landline_is_not_a_mobile_number(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.fulfillment.pickup-addresses.store'), $this->formData([
                'contact_phone' => '028123456',
            ]))
            ->assertSessionHasErrors('contact_phone');
    }

    // ------------------------------------------------------------------
    // The cascade endpoint.
    // ------------------------------------------------------------------

    public function test_the_admin_cascade_endpoint_requires_authentication(): void
    {
        $this->get(route('admin.address.deep', ['country' => 'PH', 'level' => 'state']))
            ->assertRedirect(route('login'));
    }

    public function test_the_admin_cascade_endpoint_returns_the_storefront_shape(): void
    {
        $admin = $this->get(route('admin.address.deep', ['country' => 'PH', 'level' => 'state']));
        $this->assertTrue($admin->isRedirect(), 'The admin cascade must not answer a guest.');

        $adminBody = $this->actingAs($this->admin())
            ->get(route('admin.address.deep', ['country' => 'PH', 'level' => 'state']))
            ->assertOk()
            ->json();

        $storefrontBody = $this->get(route('storefront.address.deep', ['country' => 'PH', 'level' => 'state']))
            ->assertOk()
            ->json();

        $this->assertSame($storefrontBody, $adminBody);
        $this->assertNotEmpty($adminBody);
        $this->assertArrayHasKey('id', $adminBody[0]);
        $this->assertArrayHasKey('name', $adminBody[0]);
        $this->assertArrayHasKey('code', $adminBody[0]);
    }

    public function test_the_admin_cascade_endpoint_filters_by_parent(): void
    {
        $admin = $this->admin();

        $provinces = $this->actingAs($admin)
            ->get(route('admin.address.deep', ['country' => 'PH', 'level' => 'state']))
            ->json();

        $cities = $this->actingAs($admin)
            ->get(route('admin.address.deep', ['country' => 'PH', 'level' => 'city']) . '?parent=' . $provinces[0]['id'])
            ->json();

        $this->assertNotEmpty($cities);

        // Without a parent a dependent level answers nothing at all, exactly
        // as DeepAddressData documents.
        $unparented = $this->actingAs($admin)
            ->get(route('admin.address.deep', ['country' => 'PH', 'level' => 'city']))
            ->json();

        $this->assertSame([], $unparented);
    }

    // ------------------------------------------------------------------
    // The one-off import command.
    // ------------------------------------------------------------------

    private function seedRetiredSettings(): void
    {
        Setting::create(['group' => 'shipping', 'key' => 'pickup_address', 'value' => 'Street']);
        Setting::create(['group' => 'shipping', 'key' => 'pickup_barangay', 'value' => 'San Bartolome']);
        Setting::create(['group' => 'shipping', 'key' => 'pickup_city', 'value' => 'Quezon City']);
        Setting::create(['group' => 'shipping', 'key' => 'pickup_province', 'value' => 'Metro Manila']);
        Setting::create(['group' => 'shipping', 'key' => 'pickup_postal_code', 'value' => '1116']);
        Setting::create(['group' => 'shipping', 'key' => 'pickup_contact_name', 'value' => 'Paolo']);
        Setting::create(['group' => 'shipping', 'key' => 'pickup_contact_number', 'value' => '09998889999']);
        Setting::create(['group' => 'shipping', 'key' => 'pickup_contact_email', 'value' => 'ops@example.com']);
    }

    public function test_the_import_command_produces_one_default_row_from_the_retired_settings(): void
    {
        $this->seedRetiredSettings();

        $this->assertSame(0, Artisan::call('fulfillment:import-pickup-address'));

        $this->assertSame(1, PickupAddress::count());

        $address = PickupAddress::first();
        $this->assertTrue($address->is_default);
        $this->assertSame('Street', $address->address_1);
        $this->assertSame('San Bartolome', $address->barangay);
        $this->assertNull($address->district);
        $this->assertSame('Quezon City', $address->city);
        $this->assertSame('Metro Manila', $address->state);
        $this->assertSame('1116', $address->postcode);
        $this->assertSame('Paolo', $address->contact_name);
        $this->assertSame('09998889999', $address->contact_phone);
        $this->assertSame(Country::where('iso2', 'PH')->value('id'), $address->country_id);

        // The retired contact email has no destination (owner ruling: no
        // courier payload ever carried one, the API key is the account
        // identity), so the table has no column for it at all.
        $this->assertFalse(Schema::hasColumn('courier_fulfillment_pickup_addresses', 'contact_email'));

        // One source of truth: every row it consumed is gone, the retired
        // email row included.
        $this->assertSame(0, Setting::where('group', 'shipping')->where('key', 'like', 'pickup_%')->count());
    }

    public function test_the_import_command_is_idempotent(): void
    {
        $this->seedRetiredSettings();

        Artisan::call('fulfillment:import-pickup-address');
        Artisan::call('fulfillment:import-pickup-address');
        Artisan::call('fulfillment:import-pickup-address');

        $this->assertSame(1, PickupAddress::count());
        $this->assertSame(1, PickupAddress::where('is_default', true)->count());
    }

    public function test_the_import_command_never_touches_an_address_the_operator_already_added(): void
    {
        $this->seedRetiredSettings();
        $mine = $this->makeAddress(['label' => 'Mine']);

        Artisan::call('fulfillment:import-pickup-address');

        $this->assertSame(1, PickupAddress::count());
        $this->assertSame('Mine', PickupAddress::first()->label);
        $this->assertSame($mine->id, PickupAddress::first()->id);
    }

    public function test_the_import_command_is_a_no_op_on_a_clean_install(): void
    {
        $this->assertSame(0, Artisan::call('fulfillment:import-pickup-address'));
        $this->assertSame(0, PickupAddress::count());
    }

    public function test_the_dry_run_writes_nothing(): void
    {
        $this->seedRetiredSettings();

        Artisan::call('fulfillment:import-pickup-address', ['--dry-run' => true]);

        $this->assertSame(0, PickupAddress::count());
        $this->assertSame(8, Setting::where('group', 'shipping')->where('key', 'like', 'pickup_%')->count());
    }
}
