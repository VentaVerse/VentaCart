<?php

namespace App\Services\Shipping;

use App\Models\Setting;
use App\Services\DhlExpressService;
use Illuminate\Support\Facades\Log;

class DhlShippingProvider implements ShippingProviderInterface
{
    public function getKey(): string
    {
        return 'dhl';
    }

    public function getName(): string
    {
        return 'DHL Express';
    }

    public function isEnabled(): bool
    {
        return (bool) Setting::where('group', 'shipping')
            ->where('key', 'dhl_enabled')
            ->value('value');
    }

    public function getRates(array $address, array $dimensions, float $weight, float $subtotal): array
    {
        $service = new DhlExpressService();

        if (!$service->isConfigured()) {
            return [];
        }

        $origin = $this->origin();

        // Build destination from address — resolve country_id to ISO code for DHL API
        $destCountryId = $address['country_id'] ?? null;
        $destCountry = $destCountryId
            ? \App\Models\Country::where('id', $destCountryId)->value('iso2')
            : 'PH';
        $destCity = $address['city'] ?? '';
        $destPostal = $address['postal_code'] ?? $address['postcode'] ?? '';

        // DHL is for international only — skip if destination is PH
        if ($destCountry === 'PH') {
            return [];
        }

        $pieces = [[
            'weight' => max($weight, 0.5),
            'length' => max($dimensions['length'] ?? 10, 1),
            'width' => max($dimensions['width'] ?? 10, 1),
            'height' => max($dimensions['height'] ?? 10, 1),
        ]];

        try {
            $quotes = $service->getRateQuote($origin, [
                'country_code' => $destCountry,
                'city' => $destCity,
                'postal_code' => $destPostal,
            ], $pieces);

            return collect($quotes)
                ->filter(function ($q) {
                    return $q['cost'] > 0;
                })
                ->map(function ($q) {
                    return [
                        'key' => 'dhl_' . strtolower($q['code']),
                        'name' => 'DHL ' . $q['name'],
                        'cost' => (float) $q['cost'],
                        'estimated_days' => $q['estimated_days'],
                        'service_type' => 'dhl_express',
                    ];
                })
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::error('DhlShippingProvider: failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Where a DHL quote is measured from.
     *
     * This used to read settings(group=shipping, key=pickup_city/
     * pickup_postal_code). Those keys are retired: the store's origin is now
     * a row the courier-fulfillment module owns, which is also where every
     * courier booking reads it, so there is one address to edit rather than
     * two that silently disagree.
     *
     * Core must stay coherent with that module removed (PRODUCT.md,
     * "modules are additive"), so this is a guarded table read rather than a
     * reference to the extension's model, and it keeps the same
     * Manila/1800 fallbacks it always had for a store that has no origin on
     * file at all.
     */
    private function origin(): array
    {
        $table = 'courier_fulfillment_pickup_addresses';
        $pickup = \Illuminate\Support\Facades\Schema::hasTable($table)
            ? \Illuminate\Support\Facades\DB::table($table)->where('is_default', true)->orderBy('id')->first()
            : null;

        return [
            'country_code' => 'PH',
            'city' => $pickup->city ?? 'Manila',
            'postal_code' => $pickup->postcode ?? '1800',
        ];
    }

    public function buildShippingMeta(string $quoteKey, array $quoteData): ?array
    {
        return [
            'provider' => 'dhl_express',
            'provider_label' => 'DHL Express',
            'courier_label' => $quoteData['name'] ?? 'DHL Express',
            'quote_key' => $quoteKey,
        ];
    }
}
