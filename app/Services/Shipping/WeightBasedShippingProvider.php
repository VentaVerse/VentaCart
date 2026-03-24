<?php

namespace App\Services\Shipping;

use App\Models\Setting;
use App\Models\WeightBasedMethod;
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
            $methods = WeightBasedMethod::active()->with('rates')->get();
            $customerGroupId = Auth::guard('customer')->user()?->customer_group_id;
            $quotes = [];

            foreach ($methods as $method) {
                // Customer group check
                if ($method->customer_group_id && $method->customer_group_id !== $customerGroupId) {
                    continue;
                }

                // Geo zone check
                if ($method->geo_zone_id) {
                    if (!GeoZoneResolver::matchesGeoZone($address, $method->geo_zone_id)) {
                        continue;
                    }
                }

                // Find matching weight tier
                $tier = $method->rates
                    ->where('weight_from', '<=', $weight)
                    ->where('weight_to', '>=', $weight)
                    ->first();

                if (!$tier) {
                    continue;
                }

                $quotes[] = [
                    'key' => 'weight_based_' . $method->id,
                    'name' => $method->name,
                    'cost' => (float) $tier->cost,
                    'estimated_days' => null,
                    'service_type' => 'weight_based',
                ];
            }

            return $quotes;
        } catch (\Throwable $e) {
            Log::error('WeightBasedShippingProvider: failed', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
