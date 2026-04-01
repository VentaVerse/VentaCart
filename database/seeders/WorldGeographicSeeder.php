<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WorldGeographicSeeder extends Seeder
{
    /**
     * Populate countries, zones (states/provinces), and cities from
     * dr5hn/countries-states-cities-database.
     *
     * Data files:
     *   database/data/countries-supplement.json  — countries missing from CountrySeeder
     *   database/data/zones-cities.json          — zones + nested city names keyed by ISO2
     *
     * Works for both fresh installs and existing databases.
     * Uses country_id (post-decoupling schema).
     */
    public function run(): void
    {
        $this->command->info('Loading geographic data files...');

        $supplementPath = database_path('data/countries-supplement.json');
        $zonesCitiesPath = database_path('data/zones-cities.json');

        if (!file_exists($supplementPath) || !file_exists($zonesCitiesPath)) {
            $this->command->error('Data files not found in database/data/. Aborting.');
            return;
        }

        $supplement = json_decode(file_get_contents($supplementPath), true);
        $zonesData = json_decode(file_get_contents($zonesCitiesPath), true);

        // ── Step 1: Add missing countries ──
        $this->seedCountries($supplement);

        // ── Step 2: Seed zones and cities ──
        $this->seedZonesAndCities($zonesData);
    }

    protected function seedCountries(array $supplement): void
    {
        $added = 0;

        foreach ($supplement as $row) {
            $exists = Country::where('iso2', $row['iso2'])->exists();
            if ($exists) {
                continue;
            }

            Country::create([
                'iso2' => $row['iso2'],
                'iso3' => $row['iso3'] ?: strtoupper(substr($row['name'], 0, 3)),
                'name' => $row['name'],
                'phone_code' => $row['phone_code'] ?: null,
                'is_active' => true,
                'sort_order' => 0,
            ]);
            $added++;
        }

        $this->command->info("Countries: {$added} added.");
    }

    protected function seedZonesAndCities(array $data): void
    {
        // Build iso2 → country_id mapping
        $countryIdMap = Country::pluck('id', 'iso2')->toArray();

        // Detect schema: does zones table have country_id or country_code?
        $hasCountryId = Schema::hasColumn('zones', 'country_id');
        $hasCountryCode = Schema::hasColumn('zones', 'country_code');

        $zonesAdded = 0;
        $zonesSkipped = 0;
        $citiesAdded = 0;
        $batch = [];
        $batchSize = 1000;

        DB::beginTransaction();

        try {
            foreach ($data as $countryCode => $zones) {
                $countryId = $countryIdMap[$countryCode] ?? null;
                if (!$countryId) continue;

                foreach ($zones as $zoneEntry) {
                    $zoneName = $zoneEntry['name'];
                    $zoneCode = $zoneEntry['code'] ?? null;

                    if ($zoneCode && strlen($zoneCode) > 10) {
                        $zoneCode = substr($zoneCode, 0, 10);
                    }

                    // Check if zone already exists (support both schema versions)
                    if ($hasCountryId) {
                        $zone = Zone::where('country_id', $countryId)->where('name', $zoneName)->first();
                    } else {
                        $zone = Zone::where('country_code', $countryCode)->where('name', $zoneName)->first();
                    }

                    if ($zone) {
                        if (!$zone->code && $zoneCode) {
                            $zone->update(['code' => $zoneCode]);
                        }
                        $zonesSkipped++;
                    } else {
                        $zoneData = [
                            'name' => $zoneName,
                            'code' => $zoneCode,
                            'is_active' => true,
                        ];

                        if ($hasCountryId) {
                            $zoneData['country_id'] = $countryId;
                        }
                        if ($hasCountryCode) {
                            $zoneData['country_code'] = $countryCode;
                        }

                        $zone = Zone::create($zoneData);
                        $zonesAdded++;
                    }

                    // Add cities for this zone
                    $cities = $zoneEntry['cities'] ?? [];
                    if (empty($cities)) continue;

                    $existingCityCount = City::where('zone_id', $zone->id)->count();
                    if ($existingCityCount > 0) continue;

                    foreach ($cities as $cityName) {
                        $batch[] = [
                            'zone_id' => $zone->id,
                            'name' => $cityName,
                            'is_active' => true,
                        ];

                        if (count($batch) >= $batchSize) {
                            City::insert($batch);
                            $citiesAdded += count($batch);
                            $batch = [];
                        }
                    }
                }
            }

            if (!empty($batch)) {
                City::insert($batch);
                $citiesAdded += count($batch);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->command->error("Failed: {$e->getMessage()}");
            return;
        }

        $this->command->info("Zones: {$zonesAdded} added, {$zonesSkipped} already existed.");
        $this->command->info("Cities: {$citiesAdded} added.");
    }
}
