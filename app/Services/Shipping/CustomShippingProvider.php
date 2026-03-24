<?php

namespace App\Services\Shipping;

use App\Models\ShippingMethod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CustomShippingProvider implements ShippingProviderInterface
{
    public function getKey(): string
    {
        return 'custom';
    }

    public function getName(): string
    {
        return 'Custom Shipping Methods';
    }

    public function isEnabled(): bool
    {
        return ShippingMethod::where('is_active', true)->exists();
    }

    public function getRates(array $address, array $dimensions, float $weight, float $subtotal): array
    {
        try {
            $methods = ShippingMethod::active()->with('rates')->get();
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

                // Resolve cost
                if ($method->type === 'flat_rate') {
                    $cost = (float) $method->cost;
                } else {
                    // Weight-based: find matching tier
                    $tier = $method->rates
                        ->where('weight_from', '<=', $weight)
                        ->where('weight_to', '>=', $weight)
                        ->first();

                    if (!$tier) {
                        continue; // No matching weight tier
                    }
                    $cost = (float) $tier->cost;
                }

                $quotes[] = [
                    'key' => 'shipping_method_' . $method->id,
                    'name' => $method->name,
                    'cost' => $cost,
                    'estimated_days' => null,
                    'service_type' => 'custom',
                ];
            }

            return $quotes;
        } catch (\Throwable $e) {
            Log::error('CustomShippingProvider: failed', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
