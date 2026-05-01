<?php

namespace App\Services\Shipping;

use App\Models\Setting;
use App\Services\DhlExpressService;
use Illuminate\Support\Facades\Log;

class DhlShippingProvider implements ShippingProviderInterface
{
    public function getKey(): string
    {
        return 'dhl_express';
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

        $origin = [
            'country_code' => 'PH',
            'city' => Setting::where('group', 'shipping')->where('key', 'pickup_city')->value('value') ?? 'Manila',
            'postal_code' => Setting::where('group', 'shipping')->where('key', 'pickup_postal_code')->value('value') ?? '1800',
        ];

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
