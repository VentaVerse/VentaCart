<?php

namespace App\Services\Shipping;

use Illuminate\Support\Collection;

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
     * @return array [{key, name, cost, estimated_days?, service_type, partner_id?, product_size?}]
     */
    public function getRates(array $address, array $dimensions, float $weight, float $subtotal): array;

    /**
     * Build structured metadata from a selected quote for storage on the order.
     * Providers return their own data structure. Core stores it as-is in orders.shipping_meta.
     * Must include a 'provider' key identifying the extension.
     *
     * @param string $quoteKey The selected quote key (e.g. "dph_scheduled_quadx_L")
     * @param array $quoteData The full quote array from getRates()
     * @return array|null Structured meta or null if no meta needed
     */
    public function buildShippingMeta(string $quoteKey, array $quoteData): ?array;
}
