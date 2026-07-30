<?php

namespace App\Services;

use App\Models\CustomerGroup;
use App\Models\GeoZoneEntry;
use App\Models\PaymentMethodRestriction;
use Illuminate\Support\Facades\DB;

class PaymentRestrictionService
{
    /**
     * Check if a payment method is allowed given the current order context.
     *
     * @param string     $paymentKey   e.g. 'bank_transfer', 'bank_transfer_1', 'hitpay', 'cod'
     * @param array      $address      ['state'=>..., 'city'=>..., 'country_code'=>...]
     * @param float      $subtotal     Cart subtotal
     * @param float|null $weight       Total cart weight (kg)
     * @param int|null   $quantity     Total item count
     * @param int|null   $customerGroupId  Customer's group ID
     * @param array      $cartCategoryIds  Unique category IDs in the cart
     * @param array      $cartBrandIds     Unique brand IDs in the cart
     * @param string|null $shippingMethodKey  Selected shipping method key
     */
    public static function isAllowed(
        string $paymentKey,
        array $address,
        float $subtotal,
        ?float $weight = null,
        ?int $quantity = null,
        ?int $customerGroupId = null,
        array $cartCategoryIds = [],
        array $cartBrandIds = [],
        ?string $shippingMethodKey = null,
        ?string $currencyCode = null
    ): bool {
        // Guests and unregistered customers (admin order entry) have no group;
        // treat them as the store default group so Default-group payment rules
        // apply to them instead of being skipped.
        $customerGroupId = CustomerGroup::effectiveId($customerGroupId);

        // Exact-key restriction wins (e.g. a restriction configured specifically
        // for 'bank_transfer_1'); otherwise fall back to the family/base key
        // (e.g. 'bank_transfer' covers all bank accounts).
        $restriction = PaymentMethodRestriction::where('payment_method', $paymentKey)->first();

        if (!$restriction) {
            $method = PaymentManager::get($paymentKey);
            $baseKey = $method?->getBaseKey() ?? preg_replace('/_\d+$/', '', $paymentKey);

            if ($baseKey !== $paymentKey) {
                $restriction = PaymentMethodRestriction::where('payment_method', $baseKey)->first();
            }
        }

        // No restriction record = no restrictions applied, always allowed
        if (!$restriction) {
            return true;
        }

        // Inactive restriction = no restrictions applied
        if (!$restriction->is_active) {
            return true;
        }

        // Whether we have enough address data to evaluate geo-zone rules. A
        // customer can reach checkout before choosing/saving an address; in that
        // case group/total/quantity/currency rules still apply, but geo-zone
        // rules simply can't be evaluated yet — skip them (don't block) instead
        // of bypassing every restriction.
        $hasAddress = !empty($address['state']) || !empty($address['city']);

        // --- Inclusion conditions (AND logic: all must pass) ---

        // Geo zone check (only when the address is known)
        if ($hasAddress && !empty($restriction->geo_zone_ids)) {
            $matchedGeoZoneId = self::resolveGeoZoneId($address);
            if (!$matchedGeoZoneId || !in_array($matchedGeoZoneId, $restriction->geo_zone_ids)) {
                return false;
            }
        }

        // Customer group check
        if (!empty($restriction->customer_group_ids) && $customerGroupId !== null) {
            if (!in_array($customerGroupId, $restriction->customer_group_ids)) {
                return false;
            }
        }

        // Cart must contain at least one product from specified categories
        if (!empty($restriction->category_ids) && !empty($cartCategoryIds)) {
            if (empty(array_intersect($restriction->category_ids, $cartCategoryIds))) {
                return false;
            }
        }

        // Cart must contain at least one product from specified brands
        if (!empty($restriction->brand_ids) && !empty($cartBrandIds)) {
            if (empty(array_intersect($restriction->brand_ids, $cartBrandIds))) {
                return false;
            }
        }

        if (!empty($restriction->shipping_method_keys) && $shippingMethodKey !== null) {
            if (!in_array($shippingMethodKey, $restriction->shipping_method_keys, true)) {
                return false;
            }
        }

        // Active display currency must be among the allowed currencies
        if (!empty($restriction->currency_codes) && $currencyCode !== null) {
            if (!in_array($currencyCode, $restriction->currency_codes, true)) {
                return false;
            }
        }

        // Min/max total
        if ($restriction->min_total && $subtotal < (float) $restriction->min_total) {
            return false;
        }
        if ($restriction->max_total && $subtotal > (float) $restriction->max_total) {
            return false;
        }

        // Min/max weight
        if ($weight !== null) {
            if ($restriction->min_weight && $weight < (float) $restriction->min_weight) {
                return false;
            }
            if ($restriction->max_weight && $weight > (float) $restriction->max_weight) {
                return false;
            }
        }

        // Min/max quantity
        if ($quantity !== null) {
            if ($restriction->min_quantity && $quantity < $restriction->min_quantity) {
                return false;
            }
            if ($restriction->max_quantity && $quantity > $restriction->max_quantity) {
                return false;
            }
        }

        // --- Exclusion conditions (any match = blocked) ---

        // Exclude geo zones
        if (!empty($restriction->exclude_geo_zone_ids)) {
            $matchedGeoZoneId = $matchedGeoZoneId ?? self::resolveGeoZoneId($address);
            if ($matchedGeoZoneId && in_array($matchedGeoZoneId, $restriction->exclude_geo_zone_ids)) {
                return false;
            }
        }

        // Exclude customer groups
        if (!empty($restriction->exclude_customer_group_ids) && $customerGroupId !== null) {
            if (in_array($customerGroupId, $restriction->exclude_customer_group_ids)) {
                return false;
            }
        }

        // Exclude categories: if cart has ANY product from excluded categories, block
        if (!empty($restriction->exclude_category_ids) && !empty($cartCategoryIds)) {
            if (!empty(array_intersect($restriction->exclude_category_ids, $cartCategoryIds))) {
                return false;
            }
        }

        // Exclude brands
        if (!empty($restriction->exclude_brand_ids) && !empty($cartBrandIds)) {
            if (!empty(array_intersect($restriction->exclude_brand_ids, $cartBrandIds))) {
                return false;
            }
        }

        // Exclude active display currency
        if (!empty($restriction->exclude_currency_codes) && $currencyCode !== null) {
            if (in_array($currencyCode, $restriction->exclude_currency_codes, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve the geo zone ID for a given address.
     * Uses GeoZoneResolver for consistent matching with the shipping system.
     */
    private static function resolveGeoZoneId(array $address): ?int
    {
        $state = $address['state'] ?? null;
        $city = $address['city'] ?? null;

        if (!$state && !$city) {
            return null;
        }

        // Map address keys to what GeoZoneResolver expects
        $resolverAddress = [
            'province' => $state,
            'city' => $city,
            'country_id' => $address['country_id'] ?? null,
        ];

        // Check each active geo zone for a match
        $geoZones = \App\Models\GeoZone::where('is_active', true)->pluck('id');

        foreach ($geoZones as $geoZoneId) {
            if (\App\Services\Shipping\GeoZoneResolver::matchesGeoZone($resolverAddress, $geoZoneId)) {
                return $geoZoneId;
            }
        }

        return null;
    }
}
