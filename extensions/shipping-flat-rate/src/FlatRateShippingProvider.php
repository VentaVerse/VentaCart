<?php

namespace Extensions\ShippingFlatRate;

use App\Services\Shipping\ShippingProviderInterface;
use App\Services\Shipping\ShippingQuoteService;
use Extensions\ShippingFlatRate\Models\FlatShippingRate;


use Illuminate\Support\Facades\Auth;
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
        // Registration is the switch: this provider only exists while its
        // extension is enabled, so there is no second setting to consult.
        return true;
    }

    /** One line for the admin chain page. */
    public function adminSummary(): string
    {
        $count = FlatShippingRate::count();

        return $count . ' flat ' . \Illuminate\Support\Str::plural('rate', $count);
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

            // Match the sibling providers: prefer the passed-in context group
            // (admin order entry / guest checkout supply it here), fall back to
            // the logged-in customer, then to the store default group.
            $context = ShippingQuoteService::getContext();
            $customerGroupId = \App\Models\CustomerGroup::effectiveId(
                $context['customer_group_id'] ?? Auth::guard('customer')->user()?->customer_group_id
            );
            $quotes = [];

            foreach ($rates as $rate) {
                // Customer group check
                if ($rate->customer_group_id && $rate->customer_group_id !== $customerGroupId) {
                    continue;
                }

                // Geo zone check
                if ($rate->geo_zone_id) {
                    if (!GeoZoneResolver::matchesGeoZone($address, $rate->geo_zone_id)) {
                        continue;
                    }
                }

                $quotes[] = [
                    'key' => 'flat_rate_' . $rate->id,
                    'name' => $rate->name ?: 'Flat Rate Shipping',
                    'cost' => (float) $rate->cost,
                    'estimated_days' => null,
                    'service_type' => 'flat_rate',
                ];
            }

            return $quotes;
        } catch (\Throwable $e) {
            Log::error('FlatRateShippingProvider: failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function buildShippingMeta(string $quoteKey, array $quoteData): ?array
    {
        return [
            'provider' => 'flat_rate',
            'provider_label' => 'Flat Rate',
            'courier_label' => 'Flat Rate',
            'quote_key' => $quoteKey,
        ];
    }
}
