<?php

namespace App\Services\Shipping;

use App\Models\GeoZoneEntry;
use App\Models\PhCity;
use App\Models\PhProvince;

class GeoZoneResolver
{
    /**
     * Check if an address falls within a specific geo zone.
     */
    public static function matchesGeoZone(array $address, int $geoZoneId): bool
    {
        $province = $address['province'] ?? '';
        $city = $address['city'] ?? '';
        $countryCode = $address['country_code'] ?? '';

        $entries = GeoZoneEntry::where('geo_zone_id', $geoZoneId)->get();
        if ($entries->isEmpty()) {
            return false;
        }

        // Resolve PH address components
        $phProvince = $province ? PhProvince::where('name', $province)->first() : null;
        $phCity = ($phProvince && $city)
            ? PhCity::where('ph_province_id', $phProvince->id)->where('name', $city)->first()
            : null;

        foreach ($entries as $entry) {
            // City-level match (most specific)
            if ($entry->ph_city_id && $phCity && $entry->ph_city_id === $phCity->id) {
                return true;
            }

            // Province-level match
            if ($entry->ph_province_id && !$entry->ph_city_id && $phProvince && $entry->ph_province_id === $phProvince->id) {
                return true;
            }

            // Region-level match
            if ($entry->ph_region_id && !$entry->ph_province_id && !$entry->ph_city_id && $phProvince && $entry->ph_region_id === $phProvince->ph_region_id) {
                return true;
            }

            // Country-level match
            if ($entry->country_id && !$entry->ph_region_id && !$entry->ph_province_id && !$entry->ph_city_id) {
                $countryMatch = \App\Models\Country::where('id', $entry->country_id)->where('iso2', $countryCode)->exists();
                if ($countryMatch) {
                    return true;
                }
            }
        }

        return false;
    }
}
