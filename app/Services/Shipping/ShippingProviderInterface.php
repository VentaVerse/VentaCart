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
}
