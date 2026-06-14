<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Build tool (maintainer-run, NOT an install step): transforms the raw PSGC
 * source files into the cleaned, ready-to-serve address files the storefront
 * reads. It replays — exactly — the remapping the old philippine-address
 * extension seeder performed, so the output matches what produced the live
 * customer addresses (synthetic "Metro Manila" province, HUCs demoted to
 * cities, NCR city renames, Manila districts).
 *
 * Re-run this when the raw PSGC dataset is refreshed:
 *   php artisan ph:build-address-data
 *
 * Input  (source):  database/data/ph/{region,province,muncity,barangay,city-zip-map}.json
 * Output (runtime): database/data/ph/{provinces,cities,districts,barangays}.json
 * Each output row carries its own PSGC `code` and its parent's `code`, so the
 * runtime linkage is an exact parent-code match (no fragile prefix guessing).
 */
class BuildPhAddressData extends Command
{
    protected $signature = 'ph:build-address-data';
    protected $description = 'Rebuild cleaned PH address files from raw PSGC source (replays the legacy remapping)';

    private const NCR_REGION_ID = 13;
    private const METRO_MANILA_CODE = '130000000';
    private const MANILA_CITY_CODE = '133900000';

    public function handle(): int
    {
        $base = database_path('data/ph');
        foreach (['province.json', 'muncity.json', 'barangay.json'] as $f) {
            if (!is_file("$base/$f")) {
                $this->error("Missing source file: data/ph/$f");
                return self::FAILURE;
            }
        }

        $rawProvinces = json_decode(file_get_contents("$base/province.json"), true);
        $rawCities    = json_decode(file_get_contents("$base/muncity.json"), true);
        $rawBarangays = json_decode(file_get_contents("$base/barangay.json"), true);
        $zipMap       = is_file("$base/city-zip-map.json")
            ? json_decode(file_get_contents("$base/city-zip-map.json"), true)
            : [];

        // ---- Provinces: real provinces + synthetic Metro Manila; NCR & HUCs deferred to cities ----
        $provinces = [['code' => self::METRO_MANILA_CODE, 'name' => 'Metro Manila']];
        $provinceByPrefix = [];   // first-4 code digits => province code
        $hucEntries = [];         // province-level rows that are really cities

        foreach ($rawProvinces as $p) {
            $code = (string) $p['code'];
            if ((int) $p['region_id'] === self::NCR_REGION_ID) {
                $hucEntries[] = $p;                       // NCR "provinces" are cities
                continue;
            }
            if (substr($code, 4) === '00000') {           // genuine province
                $provinces[] = ['code' => $code, 'name' => $p['description']];
                $provinceByPrefix[substr($code, 0, 4)] = $code;
            } else {
                $hucEntries[] = $p;                        // highly-urbanized city
            }
        }

        $resolveProvince = function (string $code) use ($provinceByPrefix): ?string {
            $byPrefix = $provinceByPrefix[substr($code, 0, 4)] ?? null;
            if ($byPrefix) {
                return $byPrefix;
            }
            return substr($code, 0, 2) === '13' ? self::METRO_MANILA_CODE : null;
        };

        // ---- Cities/municipalities (+ HUCs), linked to province by code ----
        $cities = [];
        $seenCity = [];
        $appendCity = function (string $code, string $name, string $provinceCode, $muncityId) use (&$cities, &$seenCity, $zipMap) {
            if (isset($seenCity[$code])) {
                return;
            }
            if ($provinceCode === self::METRO_MANILA_CODE) {
                $name = $this->renameMetroManila($name);
            }
            $cities[] = [
                'code'     => $code,
                'name'     => $name,
                'parent'   => $provinceCode,
                'zip_code' => ($muncityId !== null ? ($zipMap[(string) $muncityId] ?? null) : null),
            ];
            $seenCity[$code] = true;
        };

        foreach ($rawCities as $c) {
            $code = (string) $c['code'];
            $prov = $resolveProvince($code);
            if ($prov) {
                $appendCity($code, $c['description'], $prov, $c['muncity_id'] ?? null);
            }
        }
        foreach ($hucEntries as $h) {
            $code = (string) $h['code'];
            $prov = $resolveProvince($code);
            if ($prov) {
                $appendCity($code, $h['description'], $prov, null);
            }
        }

        // ---- Manila districts: split 1339xx (except Manila itself) out of cities ----
        $districts = [];
        $cities = array_values(array_filter($cities, function ($city) use (&$districts) {
            $isManilaDistrict = str_starts_with($city['code'], '1339') && $city['code'] !== self::MANILA_CITY_CODE;
            if ($isManilaDistrict) {
                $districts[] = ['code' => $city['code'], 'name' => $city['name'], 'parent' => self::MANILA_CITY_CODE];
                return false;
            }
            return true;
        }));

        // ---- Barangays: parent = the city/district sharing its first-6 code digits ----
        $parentByPrefix6 = [];
        foreach ($cities as $c) {
            $parentByPrefix6[substr($c['code'], 0, 6)] = $c['code'];
        }
        foreach ($districts as $d) {           // districts win for Manila prefixes
            $parentByPrefix6[substr($d['code'], 0, 6)] = $d['code'];
        }

        $barangays = [];
        foreach ($rawBarangays as $b) {
            $code = (string) $b['code'];
            $parent = $parentByPrefix6[substr($code, 0, 6)] ?? null;
            if ($parent) {
                $barangays[] = ['code' => $code, 'name' => $b['description'], 'parent' => $parent];
            }
        }

        // ---- Sort & write ----
        $byName = fn ($a, $b) => strcmp($a['name'], $b['name']);
        usort($provinces, $byName);
        usort($cities, $byName);
        usort($districts, $byName);
        usort($barangays, $byName);

        $this->writeJson("$base/provinces.json", $provinces);
        $this->writeJson("$base/cities.json", $cities);
        $this->writeJson("$base/districts.json", $districts);
        $this->writeJson("$base/barangays.json", $barangays);

        $this->info('Built cleaned PH address files:');
        $this->line('  provinces: ' . count($provinces) . ' (incl. Metro Manila)');
        $this->line('  cities:    ' . count($cities));
        $this->line('  districts: ' . count($districts) . ' (Manila)');
        $this->line('  barangays: ' . count($barangays));

        return self::SUCCESS;
    }

    private function renameMetroManila(string $name): string
    {
        if ($name === 'Quezon City') {
            return $name;
        }
        if (str_starts_with($name, 'City Of ') || str_starts_with($name, 'City of ')) {
            $name = substr($name, 8);
        }
        if (str_ends_with($name, ' City')) {
            $name = substr($name, 0, -5);
        }
        return $name;
    }

    private function writeJson(string $path, array $data): void
    {
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
