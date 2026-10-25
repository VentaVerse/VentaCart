<?php

namespace App\Services\Shipping;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ShippingQuoteService
{
    /**
     * Shared context for providers that need customer/cart info.
     * Set via getQuotes() $context parameter. Providers access via static getter.
     */
    private static array $context = [];

    /**
     * The chain's sign-in sheet (mirror of PaymentManager / CourierManager).
     * Method extensions register their quote provider from their service
     * providers with a sequence; the chain evaluates in sequence order, so
     * free shipping (10) is always consulted before the paid methods it can
     * replace. A repo-wide search for calls to register() enumerates every
     * shipping method.
     *
     * @var array<class-string<ShippingProviderInterface>, int> class => sequence
     */
    private static array $registered = [];

    public static function getContext(): array
    {
        return static::$context;
    }

    public static function register(string $class, int $sequence): void
    {
        self::$registered[$class] = $sequence;
    }

    /** @return ShippingProviderInterface[] in chain (sequence) order */
    public function getProviders(): array
    {
        $classes = self::$registered;
        asort($classes);

        return array_map(fn (string $class) => new $class(), array_keys($classes));
    }

    /** Test hook. */
    public static function reset(): void
    {
        self::$registered = [];
    }

    /**
     * Get shipping quotes from enabled providers.
     * If free shipping qualifies (service_type=free_shipping), it overrides all paid methods.
     *
     * @param array $context Optional context: customer_group_id, brand_ids, category_ids, items (for admin order entry)
     */
    public function getQuotes(array $deliveryAddress, Collection $cartItems, float $subtotal, array $context = []): array
    {
        static::$context = $context;
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

        static::$context = [];

        // Only free shipping RULES override other methods — not custom methods priced at zero
        $freeShippingQuotes = array_filter($quotes, fn ($q) => ($q['service_type'] ?? '') === 'free_shipping');
        if (!empty($freeShippingQuotes)) {
            return array_values($freeShippingQuotes);
        }

        return $quotes;
    }

    /**
     * Aggregate dimensions from cart items.
     */
    private function aggregateDimensions(Collection $cartItems): array
    {
        $totalWeight = 0;
        $maxLength = 0;
        $maxWidth = 0;
        $maxHeight = 0;

        foreach ($cartItems as $item) {
            // Support both Eloquent CartItem models and plain objects (admin order entry)
            if (isset($item->weight)) {
                // Plain object with pre-calculated dimensions
                $totalWeight += ($item->weight ?: 0) * ($item->quantity ?? 1);
                $maxLength = max($maxLength, $item->length ?? 0);
                $maxWidth = max($maxWidth, $item->width ?? 0);
                $maxHeight = max($maxHeight, $item->height ?? 0);
            } else {
                // Eloquent model with product/variant relationships
                $product = $item->product;
                if (!$product) continue;

                $itemWeight = $item->productVariant?->weight ?: $product->weight;
                $totalWeight += ($itemWeight ?: 0) * $item->quantity;

                $maxLength = max($maxLength, $product->length ?: 0);
                $maxWidth = max($maxWidth, $product->width ?: 0);
                $maxHeight = max($maxHeight, $product->height ?: 0);
            }
        }

        return [
            'weight' => $totalWeight ?: 0.5,
            'length' => $maxLength ?: 10,
            'width' => $maxWidth ?: 10,
            'height' => $maxHeight ?: 10,
        ];
    }
}
