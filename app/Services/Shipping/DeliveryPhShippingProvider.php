<?php

namespace App\Services\Shipping;

use App\Models\Setting;
use App\Services\DeliveryPhService;
use Illuminate\Support\Facades\Log;

class DeliveryPhShippingProvider implements ShippingProviderInterface
{
    public function getKey(): string
    {
        return 'dph';
    }

    public function getName(): string
    {
        return 'Deliveries PH';
    }

    public function isEnabled(): bool
    {
        return DeliveryPhService::isEnabled();
    }

    public function getRates(array $address, array $dimensions, float $weight, float $subtotal): array
    {
        try {
            $dph = app(DeliveryPhService::class);
            $pickupAddress = $this->getPickupAddress();

            $quotes = [];

            $scheduledRates = $dph->getScheduledRates(
                [
                    'province' => $pickupAddress['province'],
                    'city' => self::normalizeCityName($pickupAddress['city']),
                    'barangay' => $pickupAddress['barangay'] ?: self::normalizeCityName($pickupAddress['city']),
                ],
                [
                    'province' => $address['province'] ?? '',
                    'city' => self::normalizeCityName($address['city'] ?? ''),
                    'barangay' => $address['barangay'] ?: self::normalizeCityName($address['city'] ?? ''),
                ],
                $dimensions,
                $weight
            );

            foreach ($scheduledRates as $rate) {
                $partnerId = $rate['id'] ?? '';
                $products = $rate['products'] ?? [];
                $estimatedDays = $rate['estimatedDeliveryDays'] ?? null;

                $cheapest = collect($products)->sortBy('price')->first();
                if ($cheapest) {
                    $quotes[] = [
                        'key' => "dph_scheduled_{$partnerId}_{$cheapest['size']}",
                        'name' => ($rate['name'] ?? $partnerId) . ' (' . ($cheapest['size'] ?? '') . ')',
                        'cost' => (float) $cheapest['price'],
                        'estimated_days' => $estimatedDays,
                        'service_type' => 'scheduled',
                        'partner_id' => $partnerId,
                        'product_size' => $cheapest['size'] ?? '',
                    ];
                }
            }

            return $quotes;
        } catch (\Throwable $e) {
            Log::error('DeliveryPhShippingProvider: failed to fetch rates', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function buildShippingMeta(string $quoteKey, array $quoteData): ?array
    {
        return [
            'provider' => 'dph',
            'partner_id' => $quoteData['partner_id'] ?? null,
            'partner_name' => $quoteData['name'] ?? null,
            'service_type' => $quoteData['service_type'] ?? 'scheduled',
            'product_size' => $quoteData['product_size'] ?? null,
            'quote_key' => $quoteKey,
        ];
    }

    /**
     * Convert PSGC city names to DPH-friendly format.
     * e.g. "City Of Antipolo (Capital)" → "Antipolo City"
     *      "City Of Batac" → "Batac City"
     */
    private static function normalizeCityName(string $name): string
    {
        // Strip "(Capital)" or similar parenthetical suffixes
        $name = preg_replace('/\s*\(.*?\)\s*$/', '', $name);

        // Convert "City Of X" → "X City"
        if (preg_match('/^City Of\s+(.+)$/i', $name, $m)) {
            return trim($m[1]) . ' City';
        }

        return $name;
    }

    private function getPickupAddress(): array
    {
        $settings = Setting::where('group', 'shipping')
            ->whereIn('key', [
                'dph_pickup_address', 'dph_pickup_city', 'dph_pickup_province',
                'dph_pickup_barangay', 'dph_pickup_postal_code',
                'dph_pickup_lat', 'dph_pickup_lng',
                'dph_pickup_contact_name', 'dph_pickup_contact_number', 'dph_pickup_contact_email',
            ])
            ->pluck('value', 'key');

        return [
            'address' => $settings->get('dph_pickup_address', ''),
            'city' => $settings->get('dph_pickup_city', ''),
            'province' => $settings->get('dph_pickup_province', ''),
            'barangay' => $settings->get('dph_pickup_barangay', ''),
            'postal_code' => $settings->get('dph_pickup_postal_code', ''),
            'lat' => $settings->get('dph_pickup_lat', ''),
            'lng' => $settings->get('dph_pickup_lng', ''),
            'name' => $settings->get('dph_pickup_contact_name', ''),
            'phone' => $settings->get('dph_pickup_contact_number', ''),
            'email' => $settings->get('dph_pickup_contact_email', ''),
        ];
    }
}
