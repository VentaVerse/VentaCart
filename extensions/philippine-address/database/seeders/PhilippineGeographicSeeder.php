<?php

namespace Extensions\PhilippineAddress\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PhilippineGeographicSeeder extends Seeder
{
    public function run(): void
    {
        $basePath = '/tmp/psgc';

        if (!file_exists("$basePath/region.json")) {
            $this->command->error('PSGC JSON files not found in /tmp/psgc/. Download them first:');
            $this->command->info('mkdir -p /tmp/psgc && cd /tmp/psgc');
            $this->command->info('curl -sLO https://raw.githubusercontent.com/jgngo/psgc-data/master/json/region.json');
            $this->command->info('curl -sLO https://raw.githubusercontent.com/jgngo/psgc-data/master/json/province.json');
            $this->command->info('curl -sLO https://raw.githubusercontent.com/jgngo/psgc-data/master/json/muncity.json');
            $this->command->info('curl -sLO https://raw.githubusercontent.com/jgngo/psgc-data/master/json/barangay.json');
            return;
        }

        $this->command->info('Importing Philippine geographic data...');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('ph_barangays')->truncate();
        DB::table('ph_cities')->truncate();
        DB::table('ph_provinces')->truncate();
        DB::table('ph_regions')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Import regions
        $regions = json_decode(file_get_contents("$basePath/region.json"), true);
        $regionDbIds = [];
        foreach ($regions as $r) {
            $id = DB::table('ph_regions')->insertGetId([
                'code' => $r['code'],
                'name' => $r['description'],
            ]);
            $regionDbIds[$r['region_id']] = $id;
        }
        $this->command->info("  Regions: " . count($regions));

        // Import provinces
        $allProvinces = json_decode(file_get_contents("$basePath/province.json"), true);
        $provinceCodeMap = [];
        $hucEntries = [];

        // Create "Metro Manila" as the province for NCR (region 13)
        $ncrRegionId = $regionDbIds[13] ?? null;
        $mmId = null;
        if ($ncrRegionId) {
            $mmId = DB::table('ph_provinces')->insertGetId([
                'ph_region_id' => $ncrRegionId,
                'code' => '130000000',
                'name' => 'Metro Manila',
            ]);
            $provinceCodeMap['_NCR'] = $mmId;
        }

        foreach ($allProvinces as $p) {
            $isNcr = $p['region_id'] == 13;
            if ($isNcr) {
                $hucEntries[] = $p;
                continue;
            }

            $isActualProvince = substr($p['code'], 4) === '00000';
            if ($isActualProvince) {
                $id = DB::table('ph_provinces')->insertGetId([
                    'ph_region_id' => $regionDbIds[$p['region_id']] ?? 1,
                    'code' => $p['code'],
                    'name' => $p['description'],
                ]);
                $prefix = substr($p['code'], 0, 4);
                $provinceCodeMap[$prefix] = $id;
            } else {
                $hucEntries[] = $p;
            }
        }

        $actualProvinceCount = DB::table('ph_provinces')->count();
        $this->command->info("  Provinces: $actualProvinceCount (+ " . count($hucEntries) . " HUCs as cities)");

        // Import cities/municipalities
        $cities = json_decode(file_get_contents("$basePath/muncity.json"), true);
        $cityMuncityMap = [];

        foreach ($cities as $c) {
            $prefix = substr($c['code'], 0, 4);
            $provinceId = $provinceCodeMap[$prefix] ?? null;
            if (!$provinceId && substr($c['code'], 0, 2) === '13') {
                $provinceId = $provinceCodeMap['_NCR'] ?? null;
            }
            if (!$provinceId) {
                continue;
            }

            $id = DB::table('ph_cities')->insertGetId([
                'ph_province_id' => $provinceId,
                'code' => $c['code'],
                'name' => $c['description'],
            ]);
            $cityMuncityMap[$c['muncity_id']] = $id;
        }

        // Insert HUCs as cities
        foreach ($hucEntries as $h) {
            if (DB::table('ph_cities')->where('code', $h['code'])->exists()) {
                continue;
            }
            $prefix = substr($h['code'], 0, 4);
            $provinceId = $provinceCodeMap[$prefix] ?? null;
            if (!$provinceId && substr($h['code'], 0, 2) === '13') {
                $provinceId = $provinceCodeMap['_NCR'] ?? null;
            }
            if (!$provinceId) {
                continue;
            }
            DB::table('ph_cities')->insertGetId([
                'ph_province_id' => $provinceId,
                'code' => $h['code'],
                'name' => $h['description'],
            ]);
        }

        $cityCount = DB::table('ph_cities')->count();
        $this->command->info("  Cities/Municipalities: $cityCount");

        // Import barangays
        $barangays = json_decode(file_get_contents("$basePath/barangay.json"), true);
        $inserted = 0;

        $cityCodeLookup = [];
        $dbCities = DB::table('ph_cities')->get(['id', 'code']);
        foreach ($dbCities as $dbCity) {
            $cityCodeLookup[substr($dbCity->code, 0, 6)] = $dbCity->id;
        }

        $chunks = array_chunk($barangays, 1000);
        foreach ($chunks as $chunk) {
            $batch = [];
            foreach ($chunk as $b) {
                $cityId = $cityMuncityMap[$b['muncity_id']] ?? null;
                if (!$cityId) {
                    $cityPrefix = substr($b['code'], 0, 6);
                    $cityId = $cityCodeLookup[$cityPrefix] ?? null;
                }
                if (!$cityId) {
                    continue;
                }
                $batch[] = [
                    'ph_city_id' => $cityId,
                    'code' => $b['code'],
                    'name' => $b['description'],
                ];
            }
            if ($batch) {
                DB::table('ph_barangays')->insert($batch);
                $inserted += count($batch);
            }
        }
        $this->command->info("  Barangays: $inserted");

        // Manila districts
        $manila = DB::table('ph_cities')->where('code', '133900000')->first();
        if ($manila) {
            $updated = DB::table('ph_cities')
                ->where('code', 'like', '1339%')
                ->where('id', '!=', $manila->id)
                ->update(['parent_id' => $manila->id]);
            $this->command->info("  Manila districts linked: $updated");
        }

        // Rename Metro Manila cities
        if ($mmId) {
            $mmCities = DB::table('ph_cities')
                ->where('ph_province_id', $mmId)
                ->whereNull('parent_id')
                ->get(['id', 'name']);

            $renamed = 0;
            foreach ($mmCities as $city) {
                if ($city->name === 'Quezon City') {
                    continue;
                }
                $newName = $city->name;
                if (str_starts_with($newName, 'City Of ') || str_starts_with($newName, 'City of ')) {
                    $newName = substr($newName, 8);
                }
                if (str_ends_with($newName, ' City')) {
                    $newName = substr($newName, 0, -5);
                }
                if ($newName !== $city->name) {
                    DB::table('ph_cities')->where('id', $city->id)->update(['name' => $newName]);
                    $renamed++;
                }
            }
            $this->command->info("  Metro Manila cities renamed: $renamed");
        }

        // Seed zip codes
        $zipMapFile = __DIR__ . '/../data/city-zip-map.json';
        if (file_exists($zipMapFile)) {
            $map = json_decode(file_get_contents($zipMapFile), true);
            foreach ($map as $cityId => $zip) {
                DB::table('ph_cities')->where('id', $cityId)->update(['zip_code' => $zip]);
            }
            $this->command->info("  Zip codes applied: " . count($map));
        }

        $this->command->info('Philippine geographic data imported successfully.');
    }
}
