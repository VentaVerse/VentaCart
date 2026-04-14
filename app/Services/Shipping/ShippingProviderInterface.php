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
     * Stored as-is in orders.shipping_meta. Admin UI relies on this shape.
     *
     * Standard keys every provider MUST return:
     *   - provider        string  Machine key (same as getKey()), used for dispatch/logic
     *   - provider_label  string  Human module name ("Delivery PH", "Weight Based", etc.)
     *   - courier_label   string  The name the customer saw at checkout — the courier,
     *                             rate, or method name. This is what admin order UI displays
     *                             as "Shipping (courier_label)".
     *                             - DPH: partner courier name (e.g. "NinjaVan")
     *                             - Weight Based: the rate's checkout name
     *                             - Flat Rate: "Flat Rate"
     *                             - Free Shipping: rule's checkout_name (fallback to name)
     *                             - DHL: DHL service label
     *   - quote_key       string  The full quote key (same as $quoteKey)
     *
     * Providers MAY add their own extras (partner_id, service_type, product_size, etc.).
     *
     * @param string $quoteKey The selected quote key (e.g. "dph_scheduled_quadx_L")
     * @param array $quoteData The full quote array from getRates()
     * @return array|null Structured meta or null if no meta needed
     */
    public function buildShippingMeta(string $quoteKey, array $quoteData): ?array;
}
