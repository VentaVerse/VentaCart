<?php

namespace App\Services\Shipping;

use App\Models\Country;
use App\Models\GeoZoneEntry;
use App\Models\Zone;

class GeoZoneResolver
{
    /**
     * Check if an address falls within a specific geo zone.
     *
     * Matching precedence (most specific wins):
     * 1. Country + Zone match (e.g., PH + Metro Manila)
     * 2. Country-only match (e.g., PH with no zone specified)
     */
    public static function matchesGeoZone(array $address, int $geoZoneId): bool
    {
        $state = $address['state'] ?? $address['province'] ?? '';
        $countryId = $address['country_id'] ?? null;

        $entries = GeoZoneEntry::where('geo_zone_id', $geoZoneId)->get();
        if ($entries->isEmpty()) {
            return false;
        }

        // Resolve country and zone from core tables (only active countries)
        $country = $countryId ? Country::where('id', $countryId)->where('is_active', true)->first() : null;
        $zone = ($country && $state)
            ? Zone::where('country_id', $country->id)->where('name', $state)->first()
            : null;

        foreach ($entries as $entry) {
            // Zone-level match (most specific): country + zone
            if ($entry->country_id && $entry->zone_id && $country && $zone) {
                if ($entry->country_id === $country->id && $entry->zone_id === $zone->id) {
                    return true;
                }
            }

            // Country-level match: country only, no zone specified on entry
            if ($entry->country_id && !$entry->zone_id && $country) {
                if ($entry->country_id === $country->id) {
                    return true;
                }
            }
        }

        return false;
    }
}
