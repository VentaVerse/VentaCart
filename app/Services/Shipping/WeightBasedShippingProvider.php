<?php

namespace App\Services\Shipping;

use App\Models\GeoZone;
use App\Models\GeoZoneEntry;
use App\Models\PhCity;
use App\Models\PhProvince;
use App\Models\Setting;
use App\Models\ShippingZoneRate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class WeightBasedShippingProvider implements ShippingProviderInterface
{
    public function getKey(): string
    {
        return 'weight_based';
    }

    public function getName(): string
    {
        return 'Weight-Based Shipping';
    }

    public function isEnabled(): bool
    {
        return (bool) Setting::where('group', 'shipping')
            ->where('key', 'weight_based_enabled')
            ->value('value');
    }

    public function getRates(array $address, array $dimensions, float $weight, float $subtotal): array
    {
        try {
            $geoZone = $this->resolveGeoZone($address);

            if (!$geoZone) {
                return [];
            }

            $customerGroupId = null;
            if (Auth::guard('customer')->check()) {
                $customerGroupId = Auth::guard('customer')->user()->customer_group_id;
            }

            $rate = $this->findRate($geoZone->id, $weight, $customerGroupId);

            if (!$rate && $customerGroupId) {
                $rate = $this->findRate($geoZone->id, $weight, null);
            }

            if (!$rate) {
                return [];
            }

            if ($rate->free_shipping_threshold && $subtotal >= (float) $rate->free_shipping_threshold) {
                return [[
                    'key' => "weight_based_{$geoZone->id}",
                    'name' => "Shipping ({$geoZone->name})",
                    'cost' => 0,
                    'estimated_days' => null,
                    'service_type' => 'weight_based',
                ]];
            }

            return [[
                'key' => "weight_based_{$geoZone->id}",
                'name' => "Shipping ({$geoZone->name})",
                'cost' => (float) $rate->cost,
                'estimated_days' => null,
                'service_type' => 'weight_based',
            ]];
        } catch (\Throwable $e) {
            Log::error('WeightBasedShippingProvider: failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Resolve which geo zone matches the delivery address.
     * Priority: city > province > region > country > default.
     */
    private function resolveGeoZone(array $address): ?GeoZone
    {
        $province = $address['province'] ?? '';
        $city = $address['city'] ?? '';

        $phProvince = PhProvince::where('name', $province)->first();
        $phCity = $phProvince
            ? PhCity::where('ph_province_id', $phProvince->id)->where('name', $city)->first()
            : null;

        $baseQuery = GeoZoneEntry::query()
            ->whereHas('geoZone', fn ($q) => $q->where('is_active', true));

        // Most specific: city match
        if ($phCity) {
            $entry = (clone $baseQuery)->where('ph_city_id', $phCity->id)->first();
            if ($entry) {
                return $entry->geoZone;
            }
        }

        // Province match
        if ($phProvince) {
            $entry = (clone $baseQuery)->where('ph_province_id', $phProvince->id)
                ->whereNull('ph_city_id')->first();
            if ($entry) {
                return $entry->geoZone;
            }
        }

        // Region match
        if ($phProvince) {
            $entry = (clone $baseQuery)->where('ph_region_id', $phProvince->ph_region_id)
                ->whereNull('ph_province_id')->whereNull('ph_city_id')->first();
            if ($entry) {
                return $entry->geoZone;
            }
        }

        // Default: first active geo zone marked as sort_order 0 or lowest
        return GeoZone::where('is_active', true)->orderBy('sort_order')->first();
    }

    private function findRate(int $geoZoneId, float $weight, ?int $customerGroupId): ?ShippingZoneRate
    {
        return ShippingZoneRate::where('geo_zone_id', $geoZoneId)
            ->where('customer_group_id', $customerGroupId)
            ->where('weight_from', '<=', $weight)
            ->where('weight_to', '>=', $weight)
            ->first();
    }
}
