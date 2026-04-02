<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorldGeographicSeeder extends Seeder
{
    /**
     * Populate countries and zones (states/provinces) from the unified
     * geographic.json file. This is Venta's own maintained dataset,
     * derived from dr5hn/countries-states-cities-database with
     * PH zones corrected to match PSGC province names.
     */
    public function run(): void
    {
        $path = database_path('data/geographic.json');

        if (!file_exists($path)) {
            $this->command->error('database/data/geographic.json not found.');
            return;
        }

        $data = json_decode(file_get_contents($path), true);

        $this->command->info('Seeding countries and zones...');

        $countriesAdded = 0;
        $zonesAdded = 0;
        $zonesSkipped = 0;

        DB::beginTransaction();

        try {
            foreach ($data as $entry) {
                // Upsert country
                $country = Country::updateOrCreate(
                    ['iso2' => $entry['iso2']],
                    [
                        'iso3' => $entry['iso3'],
                        'name' => $entry['name'],
                        'phone_code' => $entry['phone_code'] ?: null,
                        'is_active' => true,
                        'sort_order' => 0,
                    ]
                );

                if ($country->wasRecentlyCreated) {
                    $countriesAdded++;
                }

                // Seed zones for this country
                foreach ($entry['zones'] as $zone) {
                    $existing = Zone::where('country_id', $country->id)
                        ->where('name', $zone['name'])
                        ->first();

                    if ($existing) {
                        // Fill in missing code if we have one
                        if (!$existing->code && $zone['code']) {
                            $existing->update(['code' => $zone['code']]);
                        }
                        $zonesSkipped++;
                    } else {
                        Zone::create([
                            'country_id' => $country->id,
                            'name' => $zone['name'],
                            'code' => $zone['code'] ?: null,
                            'is_active' => true,
                        ]);
                        $zonesAdded++;
                    }
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->command->error("Failed: {$e->getMessage()}");
            return;
        }

        $totalCountries = Country::count();
        $totalZones = Zone::count();
        $this->command->info("Countries: {$countriesAdded} added ({$totalCountries} total)");
        $this->command->info("Zones: {$zonesAdded} added, {$zonesSkipped} existed ({$totalZones} total)");
    }
}
