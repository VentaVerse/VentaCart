<?php

namespace Tests\Feature\Address;

use App\Services\Address\DeepAddressData;
use Tests\TestCase;

/**
 * The bundled PH address files: ZIPs from PHLPost keyed by PSGC code (the old
 * map was keyed by a drifted muncity_id, giving Pateros Abra's 2800), Manila
 * district ZIPs, the region table, and the cascade saved addresses rely on.
 */
class PhAddressDataTest extends TestCase
{
    private DeepAddressData $data;

    protected function setUp(): void
    {
        parent::setUp();
        $this->data = app(DeepAddressData::class);
    }

    /** @return array<string, ?string> name => zip_code for one level under a parent */
    private function zips(string $level, ?string $parent): array
    {
        return collect($this->data->level('PH', $level, $parent))->pluck('zip_code', 'name')->all();
    }

    private function code(string $level, ?string $parent, string $name): string
    {
        $match = collect($this->data->level('PH', $level, $parent))->firstWhere('name', $name);
        $this->assertNotNull($match, "$name not found at $level");

        return $match['id'];
    }

    public function test_metro_manila_zips_are_corrected_and_filled(): void
    {
        $zips = $this->zips('city', '130000000');

        $this->assertSame('1620', $zips['Pateros']);
        $this->assertSame('1630', $zips['Taguig']);
        $this->assertSame('1500', $zips['San Juan']);
        $this->assertSame('1100', $zips['Quezon City']);
        $this->assertSame('1200', $zips['Makati']);
        $this->assertSame('1600', $zips['Pasig']);
        $this->assertSame('1000', $zips['Manila']);
        $this->assertNotContains(null, $zips, 'every Metro Manila city has a ZIP');
    }

    public function test_cities_missing_from_the_old_map_have_their_zip(): void
    {
        $laguna = $this->code('state', null, 'Laguna');
        $pampanga = $this->code('state', null, 'Pampanga');

        $this->assertSame('4025', $this->zips('city', $laguna)['Cabuyao City']);
        $this->assertSame('2009', $this->zips('city', $pampanga)['Angeles City']);
    }

    public function test_manila_districts_carry_their_own_zip(): void
    {
        $zips = $this->zips('district', '133900000');

        $this->assertCount(14, $zips);
        $this->assertSame('1008', $zips['Sampaloc']);
        $this->assertSame('1006', $zips['Binondo']);
        $this->assertSame('1000', $zips['Ermita']);
        $this->assertSame('1011', $zips['Pandacan']);
        $this->assertSame('1012', $zips['Tondo I / Ii']);
        $this->assertNotContains(null, $zips);
    }

    public function test_the_zip_map_is_keyed_by_known_psgc_codes(): void
    {
        $map = json_decode(file_get_contents(database_path('data/ph/city-zip-map.json')), true);
        $codes = collect(json_decode(file_get_contents(database_path('data/ph/cities.json')), true))
            ->merge(json_decode(file_get_contents(database_path('data/ph/districts.json')), true))
            ->pluck('code')->flip();

        foreach ($map as $code => $zip) {
            $this->assertTrue($codes->has((string) $code), "unknown code $code");
            $this->assertMatchesRegularExpression('/^\d{4}$/', $zip, "bad zip for $code");
        }
    }

    public function test_region_lookup_by_province_code(): void
    {
        $this->assertSame(
            ['code' => '13', 'designation' => 'NCR', 'name' => 'National Capital Region', 'short' => 'Metro Manila'],
            $this->data->region('PH', '130000000'),
        );

        $laguna = $this->data->region('PH', '043400000');
        $this->assertSame('Region IV-A', $laguna['designation']);
        $this->assertSame('Calabarzon', $laguna['short']);

        $this->assertSame('BARMM', $this->data->region('PH', '153800000')['designation']);   // Maguindanao, old ARMM 15
        $this->assertSame('Region XIII', $this->data->region('PH', '166700000')['designation']); // Surigao del Norte
        $this->assertSame('Region IV-B', $this->data->region('PH', '175300000')['designation']); // Palawan
        $this->assertSame('CAR', $this->data->region('PH', '140100000')['designation']);         // Abra
        $this->assertSame('Region VI', $this->data->region('PH', '063000000')['designation']);   // Iloilo
        $this->assertSame('Region VII', $this->data->region('PH', '072200000')['designation']);  // Cebu
    }

    public function test_negros_and_siquijor_are_in_the_negros_island_region(): void
    {
        foreach (['064500000', '074600000', '076100000', '074610000'] as $code) {
            $this->assertSame('NIR', $this->data->region('PH', $code)['designation'], $code);
            $this->assertSame('Negros Island Region', $this->data->region('PH', $code)['name']);
        }
    }

    public function test_region_lookup_tolerates_int_stored_codes_and_unknowns(): void
    {
        $this->assertSame('Region I', $this->data->region('PH', '12800000')['designation']);  // 012800000 lost its zero
        $this->assertNull($this->data->region('PH', '990000000'));
        $this->assertNull($this->data->region('PH', ''));
        $this->assertNull($this->data->region('SG', '130000000'));
    }

    public function test_every_province_has_a_region(): void
    {
        foreach ($this->data->level('PH', 'state') as $province) {
            $this->assertNotNull($this->data->region('PH', $province['code']), $province['name']);
        }
    }

    public function test_existing_lookups_are_unchanged(): void
    {
        $this->assertCount(82, $this->data->level('PH', 'state'));
        $this->assertSame('130000000', $this->code('state', null, 'Metro Manila'));
        $this->assertSame('137404000', $this->code('city', '130000000', 'Quezon City'));
        $this->assertSame('133906000', $this->code('district', '133900000', 'Sampaloc'));
        $this->assertNotEmpty(collect($this->data->level('PH', 'barangay', '133906000'))->firstWhere('name', 'Barangay 556'));

        // Saved addresses match by name, so the bundled names stay as they were.
        $this->assertSame('118200000', $this->code('state', null, 'Compostela Valley'));
        $this->assertSame('064500000', $this->code('state', null, 'Negros Occidental'));

        $cities = json_decode(file_get_contents(database_path('data/ph/cities.json')), true);
        $barangays = json_decode(file_get_contents(database_path('data/ph/barangays.json')), true);
        $this->assertCount(1632, $cities);
        $this->assertCount(41954, $barangays);
        foreach ($cities as $city) {
            $this->assertArrayHasKey('zip_code', $city);   // the extensions' catalog reads it
        }
    }
}
