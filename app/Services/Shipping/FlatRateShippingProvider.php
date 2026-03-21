<?php

namespace App\Services\Shipping;

use App\Models\FlatShippingRate;
use App\Models\GeoZoneEntry;
use App\Models\PhProvince;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class FlatRateShippingProvider implements ShippingProviderInterface
{
    public function getKey(): string
    {
        return 'flat_rate';
    }

    public function getName(): string
    {
        return 'Flat Rate Shipping';
    }

    public function isEnabled(): bool
    {
        return (bool) Setting::where('group', 'shipping')
            ->where('key', 'flat_rate_enabled')
            ->value('value');
    }

    public function getRates(array $address, array $dimensions, float $weight, float $subtotal): array
    {
        try {
            $rates = FlatShippingRate::where('is_active', true)
                ->orderBy('sort_order')
                ->with('geoZone')
                ->get();

            if ($rates->isEmpty()) {
                return [];
            }

            $geoZoneId = $this->resolveGeoZoneId($address);

            foreach ($rates as $rate) {
                // Global rate (no geo zone set) — applies everywhere
                if (!$rate->geo_zone_id) {
                    return [[
                        'key' => 'flat_rate_global',
                        'name' => 'Standard Shipping',
                        'cost' => (float) $rate->cost,
                        'estimated_days' => null,
                        'service_type' => 'flat_rate',
                    ]];
                }

                // Zone-specific rate — check if customer's address matches
                if ($geoZoneId && $rate->geo_zone_id === $geoZoneId) {
                    $zoneName = $rate->geoZone?->name ?? 'Standard';
                    return [[
                        'key' => 'flat_rate_' . $rate->geo_zone_id,
                        'name' => "Shipping ({$zoneName})",
                        'cost' => (float) $rate->cost,
                        'estimated_days' => null,
                        'service_type' => 'flat_rate',
                    ]];
                }
            }

            return [];
        } catch (\Throwable $e) {
            Log::error('FlatRateShippingProvider: failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function resolveGeoZoneId(array $address): ?int
    {
        $province = $address['province'] ?? '';
        if (!$province) {
            return null;
        }

        $phProvince = PhProvince::where('name', $province)->first();
        if (!$phProvince) {
            return null;
        }

        $entry = GeoZoneEntry::where('ph_region_id', $phProvince->ph_region_id)->first();
        return $entry?->geo_zone_id;
    }
}
