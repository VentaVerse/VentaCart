<?php

namespace Tests\Feature\CourierFulfillment;

use Extensions\CourierFulfillment\Couriers\Spx\SpxLocations;
use App\Services\ExtensionManager;
use Extensions\CourierFulfillment\Couriers\Spx\SpxRefusals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SPX's location list (PSGC names placed on SPX's own levels) and SPX's
 * refusals in plain words. No HTTP.
 */
class SpxLocationsAndRefusalsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ExtensionManager::class)->install('courier-fulfillment');
        app(ExtensionManager::class)->bootEnabled();
    }

    private function locations(): SpxLocations
    {
        return new SpxLocations();
    }

    public function test_a_psgc_address_is_placed_on_spx_levels(): void
    {
        $at = $this->locations()->resolve(['state' => 'Cebu', 'city' => 'Cebu City', 'barangay' => 'Apas']);

        $this->assertTrue($at['ok']);
        $this->assertSame(['state' => 'Visayas', 'city' => 'Cebu', 'district' => 'Cebu City', 'street' => 'Apas'], $at['location']);
        $this->assertTrue(SpxLocations::supports($at['flags'], SpxLocations::DELIVER));
        $this->assertTrue(SpxLocations::supports($at['flags'], SpxLocations::COD_DELIVER));
    }

    public function test_names_match_on_a_folded_key(): void
    {
        $this->assertSame('makati', SpxLocations::key('City of Makati'));
        $this->assertSame('makati', SpxLocations::key('Makati City'));
        $this->assertSame('santonino', SpxLocations::key('Sto. Niño'));
        $this->assertSame('barangay5', SpxLocations::key('Bgy. No. 5', false));

        $at = $this->locations()->resolve(['state' => 'Metro Manila', 'city' => 'City of Makati', 'barangay' => 'BEL-AIR']);
        $this->assertTrue($at['ok']);
        $this->assertSame('Bel-air', $at['location']['street']);
    }

    public function test_a_municipality_spx_spells_differently_is_found_through_its_alias(): void
    {
        $at = $this->locations()->resolve(['state' => 'Cebu', 'city' => 'Cordova', 'barangay' => 'Alegria']);

        $this->assertTrue($at['ok']);
        $this->assertSame('Cordoba', $at['location']['district']);
    }

    public function test_manila_is_listed_by_sub_district(): void
    {
        $withDistrict = $this->locations()->resolve(['state' => 'Metro Manila', 'city' => 'Manila', 'district' => 'Ermita', 'barangay' => 'Barangay 659']);
        $this->assertTrue($withDistrict['ok']);
        $this->assertSame('Ermita', $withDistrict['location']['district']);

        // Without the sub-district, a barangay that names one place is still placed.
        $without = $this->locations()->resolve(['state' => 'Metro Manila', 'city' => 'Manila', 'barangay' => 'Barangay 659-A']);
        $this->assertTrue($without['ok']);
        $this->assertSame('Ermita', $without['location']['district']);
    }

    public function test_an_address_spx_does_not_list_says_which_level_is_missing(): void
    {
        $this->assertSame(['ok' => false, 'missing' => 'province'], $this->locations()->resolve(['state' => 'Atlantis', 'city' => 'Cebu City', 'barangay' => 'Apas']));
        $this->assertSame(['ok' => false, 'missing' => 'city'], $this->locations()->resolve(['state' => 'Cebu', 'city' => 'Nowhere', 'barangay' => 'Apas']));
        $this->assertSame(['ok' => false, 'missing' => 'barangay'], $this->locations()->resolve(['state' => 'Cebu', 'city' => 'Cebu City', 'barangay' => 'Not A Barangay']));
        // Never the city standing in for a missing barangay.
        $this->assertSame(['ok' => false, 'missing' => 'barangay'], $this->locations()->resolve(['state' => 'Cebu', 'city' => 'Cebu City', 'barangay' => '']));
    }

    public function test_the_location_list_ships_with_the_extension_and_holds_only_places(): void
    {
        $path = SpxLocations::defaultPath();
        $this->assertFileExists($path);

        $rows = json_decode((string) file_get_contents($path), true);
        $this->assertGreaterThan(40000, count($rows));
        foreach (array_slice($rows, 0, 200) as $row) {
            $this->assertCount(5, $row);
            $this->assertMatchesRegularExpression('/^[01]{6}$/', $row[4]);
        }
    }

    public function test_spx_size_rules_read_as_plain_sentences(): void
    {
        $refusal = SpxRefusals::explain(13051, 'check rule|7 |ruleName| MaxLengthExtremumRule failed, 35.000000 exceed max value 25.000000', ['length_cm' => 35]);
        $this->assertSame('Too long for SPX. Length is 35 cm. SPX takes up to 25 cm.', $refusal->message());

        $weight = SpxRefusals::explain(13101, 'parcel weight should less than or equal to 10 kg', ['weight_kg' => 12.5]);
        $this->assertSame('Too heavy for SPX. Weight is 12.5 kg. SPX takes up to 10 kg.', $weight->message());

        $min = SpxRefusals::explain(13051, 'MinWidthExtremumRule failed, 1.000000 less than min value 2.000000');
        $this->assertSame('Too narrow for SPX. Width is 1 cm. SPX takes at least 2 cm.', $min->message());
    }

    public function test_outer_codes_read_as_plain_sentences(): void
    {
        $this->assertSame("SPX doesn't collect COD here. Check the delivery address.", SpxRefusals::explain(10017, 'whatever SPX said')->message());
        $this->assertSame('COD not set up with SPX. Add a bank account with SPX to send COD parcels.', SpxRefusals::explain(12054, 'cod not allowed')->message());
        $this->assertSame('Too long for SPX. Length is 160 cm.', SpxRefusals::explain(10084, '', ['length_cm' => 160])->message());
        $this->assertSame('Phone number not accepted by SPX. Check the phone number.', SpxRefusals::explain(11001, 'deliver_phone invalid')->message());
        $this->assertSame(SpxRefusals::generic()->message(), SpxRefusals::explain(99999, 'internal')->message());
    }

    public function test_which_codes_are_refusals(): void
    {
        $this->assertTrue(SpxRefusals::isRefusal(10016));
        $this->assertTrue(SpxRefusals::isRefusal(13051));
        $this->assertTrue(SpxRefusals::isRefusal(12054));
        // SPX's own trouble, the account and auth families: never the shipment's fault.
        $this->assertFalse(SpxRefusals::isRefusal(10020));
        $this->assertFalse(SpxRefusals::isRefusal(1007));
        $this->assertFalse(SpxRefusals::isRefusal(null));
    }
}
