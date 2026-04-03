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
            $methods = WeightBasedMethod::active()->with(['rates', 'geoZones', 'customerGroups'])->get();
            $context = ShippingQuoteService::getContext();
            $customerGroupId = $context['customer_group_id'] ?? Auth::guard('customer')->user()?->customer_group_id;
            $quotes = [];

            foreach ($methods as $method) {
                // Customer group check: if groups are assigned, customer must be in one of them
                if ($method->customerGroups->isNotEmpty()) {
                    if (!$customerGroupId || !$method->customerGroups->contains('id', $customerGroupId)) {
                        continue;
                    }
                }

                // Geo zone check: if zones are assigned, address must match at least one
                if ($method->geoZones->isNotEmpty()) {
                    $matchesAny = false;
                    foreach ($method->geoZones as $geoZone) {
                        if (GeoZoneResolver::matchesGeoZone($address, $geoZone->id)) {
                            $matchesAny = true;
                            break;
                        }
                    }
                    if (!$matchesAny) {
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

    public function buildShippingMeta(string $quoteKey, array $quoteData): ?array
    {
        return null;
    }
}
