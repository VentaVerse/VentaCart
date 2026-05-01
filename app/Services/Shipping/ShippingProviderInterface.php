<?php

namespace App\Services\Shipping;

interface ShippingProviderInterface
{
    public function getKey(): string;
    public function getName(): string;
    public function isEnabled(): bool;

    /**
     * @param array $address {province, city, barangay?, lat?, lng?}
     * @param array $dimensions {length, width, height} in cm
     * @param float $weight in kg
     * @param float $subtotal cart subtotal for threshold calculations
     * @return array [{key, name, cost, estimated_days?, service_type}]
     */
    public function getRates(array $address, array $dimensions, float $weight, float $subtotal): array;

    /**
     * Build structured metadata from a selected quote for storage on the order.
     * Stored as-is in orders.shipping_meta.
     *
     * Standard keys every provider returns:
     *   - provider        string  Machine key (same as getKey()).
     *   - provider_label  string  Human module name ("Weight Based", "DHL Express", etc.).
     *   - courier_label   string  The name the customer saw at checkout (rate name,
     *                             courier name, or method name) — what admin order UI
     *                             displays as "Shipping (courier_label)".
     *   - quote_key       string  The full quote key (same as $quoteKey).
     *
     * Providers MAY add their own extras.
     */
    public function buildShippingMeta(string $quoteKey, array $quoteData): ?array;
}
