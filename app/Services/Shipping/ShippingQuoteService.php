<?php

namespace App\Services\Shipping;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ShippingQuoteService
{
    private function getProviders(): array
    {
        return [
            new FreeShippingRuleProvider(),
            new DeliveryPhShippingProvider(),
            new WeightBasedShippingProvider(),
            new DhlShippingProvider(),
            new FlatRateShippingProvider(),
        ];
    }

    /**
     * Get shipping quotes from enabled providers.
     * If free shipping qualifies, it is the only option returned.
     */
    public function getQuotes(array $deliveryAddress, Collection $cartItems, float $subtotal): array
    {
        $dimensions = $this->aggregateDimensions($cartItems);
        $quotes = [];

        foreach ($this->getProviders() as $provider) {
            if (!$provider->isEnabled()) {
                continue;
            }

            try {
                $providerRates = $provider->getRates(
                    $deliveryAddress,
                    $dimensions,
                    $dimensions['weight'],
                    $subtotal
                );
                $quotes = array_merge($quotes, $providerRates);
            } catch (\Throwable $e) {
                Log::error("ShippingQuoteService: {$provider->getKey()} provider failed", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // If any free shipping option qualified, it overrides all paid methods
        $freeQuotes = array_filter($quotes, fn ($q) => isset($q['cost']) && (float) $q['cost'] === 0.0);
        if (!empty($freeQuotes)) {
            return array_values($freeQuotes);
        }

        return $quotes;
    }

    /**
     * Aggregate dimensions from cart items.
     * Weight: sum all items. Dimensions: largest single item's dimensions.
     */
    private function aggregateDimensions(Collection $cartItems): array
    {
        $totalWeight = 0;
        $maxLength = 0;
        $maxWidth = 0;
        $maxHeight = 0;

        foreach ($cartItems as $item) {
            $product = $item->product;
            if (!$product) continue;

            $itemWeight = $item->productVariant?->weight ?: $product->weight;
            $totalWeight += ($itemWeight ?: 0) * $item->quantity;

            $maxLength = max($maxLength, $product->length ?: 0);
            $maxWidth = max($maxWidth, $product->width ?: 0);
            $maxHeight = max($maxHeight, $product->height ?: 0);
        }

        return [
            'weight' => $totalWeight ?: 0.5,
            'length' => $maxLength ?: 10,
            'width' => $maxWidth ?: 10,
            'height' => $maxHeight ?: 10,
        ];
    }
}
