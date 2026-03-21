<?php

namespace App\Services;

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
        ?string $shippingMethodKey = null
    ): bool {
        // Normalise key: bank_transfer_1 => bank_transfer
        $baseKey = preg_replace('/_\d+$/', '', $paymentKey);

        $restriction = PaymentMethodRestriction::where('payment_method', $baseKey)->first();

        // No restriction record = no restrictions applied, always allowed
        if (!$restriction) {
            return true;
        }

        // Inactive restriction = no restrictions applied
        if (!$restriction->is_active) {
            return true;
        }

        // --- Inclusion conditions (AND logic: all must pass) ---

        // Geo zone check
        if (!empty($restriction->geo_zone_ids)) {
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

        // Shipping method check
        if (!empty($restriction->shipping_method_keys) && $shippingMethodKey !== null) {
            // Normalise DPH keys: dph_flash => dph
            $normalisedShipping = preg_replace('/^(dph)_.*/', '$1', $shippingMethodKey);
            $allowed = false;
            foreach ($restriction->shipping_method_keys as $allowedKey) {
                if ($allowedKey === $shippingMethodKey || $allowedKey === $normalisedShipping) {
                    $allowed = true;
                    break;
                }
            }
            if (!$allowed) {
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

        return true;
    }

    /**
     * Resolve the geo zone ID for a given address.
     */
    private static function resolveGeoZoneId(array $address): ?int
    {
        $state = $address['state'] ?? null;
        $city = $address['city'] ?? null;
        $countryCode = $address['country_code'] ?? 'PH';

        if (!$state && !$city) {
            return null;
        }

        $query = GeoZoneEntry::query()
            ->select('geo_zone_id')
            ->join('geo_zones', 'geo_zones.id', '=', 'geo_zone_entries.geo_zone_id')
            ->where('geo_zones.is_active', true);

        if ($countryCode === 'PH' && $state) {
            $query->where(function ($q) use ($state) {
                $q->whereNotNull('ph_province_id')
                  ->whereExists(function ($sub) use ($state) {
                      $sub->select(DB::raw(1))
                          ->from('ph_provinces')
                          ->whereColumn('ph_provinces.id', 'geo_zone_entries.ph_province_id')
                          ->where('ph_provinces.name', $state);
                  });
            });
        }

        $entry = $query->first();

        return $entry?->geo_zone_id;
    }
}
