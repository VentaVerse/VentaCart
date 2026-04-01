<?php

namespace App\Services\Shipping;

use App\Models\Country;
use App\Models\FreeShippingRule;
use App\Models\GeoZoneEntry;
use App\Models\Zone;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class FreeShippingRuleProvider implements ShippingProviderInterface
{
    public function getKey(): string
    {
        return 'free_shipping';
    }

    public function getName(): string
    {
        return 'Free Shipping';
    }

    public function isEnabled(): bool
    {
        return FreeShippingRule::where('is_active', true)->exists();
    }

    public function getRates(array $address, array $dimensions, float $weight, float $subtotal): array
    {
        try {
            $rules = FreeShippingRule::where('is_active', true)->orderBy('sort_order')->get();
            if ($rules->isEmpty()) {
                return [];
            }

            $customerGroupId = Auth::guard('customer')->user()?->customer_group_id;
            $cartItems = $this->getCartItems();

            if ($cartItems->isEmpty()) {
                return [];
            }

            $cartBrandIds = $cartItems->pluck('product.brand_id')->filter()->unique()->values()->all();
            $cartCategoryIds = $cartItems->flatMap(function ($item) {
                return $item->product?->categories?->pluck('id') ?? collect();
            })->unique()->values()->all();
            $cartQuantity = $cartItems->sum('quantity');
            $zoneId = $this->resolveZoneId($address);

            foreach ($rules as $rule) {
                if ($this->ruleMatches($rule, $subtotal, $cartQuantity, $cartBrandIds, $cartCategoryIds, $customerGroupId, $zoneId)) {
                    return [[
                        'key' => 'free_shipping',
                        'name' => $rule->name ?: 'Free Shipping',
                        'cost' => 0,
                        'estimated_days' => null,
                        'service_type' => 'free_shipping',
                    ]];
                }
            }

            return [];
        } catch (\Throwable $e) {
            Log::error('FreeShippingRuleProvider: failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function ruleMatches(
        FreeShippingRule $rule,
        float $subtotal,
        int $cartQuantity,
        array $cartBrandIds,
        array $cartCategoryIds,
        ?int $customerGroupId,
        ?int $zoneId
    ): bool {
        // Min order total
        if ($rule->min_order_total && $subtotal < (float) $rule->min_order_total) {
            return false;
        }

        // Min quantity
        if ($rule->min_quantity && $cartQuantity < $rule->min_quantity) {
            return false;
        }

        // Customer group inclusion
        if (!empty($rule->customer_group_ids)) {
            if (!$customerGroupId || !in_array($customerGroupId, $rule->customer_group_ids)) {
                return false;
            }
        }

        // Customer group exclusion
        if (!empty($rule->exclude_customer_group_ids)) {
            if ($customerGroupId && in_array($customerGroupId, $rule->exclude_customer_group_ids)) {
                return false;
            }
        }

        // Shipping zone
        if (!empty($rule->shipping_zone_ids)) {
            if (!$zoneId || !in_array($zoneId, $rule->shipping_zone_ids)) {
                return false;
            }
        }

        if ($rule->apply_whole_cart) {
            // "Apply whole cart" mode: if ANY item in cart matches the brand/category conditions,
            // the ENTIRE cart gets free shipping regardless of other items
            $hasQualifyingItem = false;

            if (!empty($rule->brand_ids)) {
                foreach ($cartBrandIds as $bid) {
                    if (in_array($bid, $rule->brand_ids)) {
                        $hasQualifyingItem = true;
                        break;
                    }
                }
                if (!$hasQualifyingItem) return false;
            }

            if (!empty($rule->category_ids)) {
                foreach ($cartCategoryIds as $cid) {
                    if (in_array($cid, $rule->category_ids)) {
                        $hasQualifyingItem = true;
                        break;
                    }
                }
                if (!$hasQualifyingItem) return false;
            }
        } else {
            // Normal mode: ALL items must match brand/category conditions
            if (!empty($rule->brand_ids)) {
                foreach ($cartBrandIds as $bid) {
                    if (!in_array($bid, $rule->brand_ids)) {
                        return false;
                    }
                }
            }

            if (!empty($rule->category_ids)) {
                foreach ($cartCategoryIds as $cid) {
                    if (!in_array($cid, $rule->category_ids)) {
                        return false;
                    }
                }
            }
        }

        // Brand exclusion — cart must NOT contain items from these brands
        if (!empty($rule->exclude_brand_ids)) {
            foreach ($cartBrandIds as $bid) {
                if (in_array($bid, $rule->exclude_brand_ids)) {
                    return false;
                }
            }
        }

        // Category exclusion
        if (!empty($rule->exclude_category_ids)) {
            foreach ($cartCategoryIds as $cid) {
                if (in_array($cid, $rule->exclude_category_ids)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function getCartItems()
    {
        return app(\App\Services\CartService::class)->getItems();
    }

    private function resolveZoneId(array $address): ?int
    {
        $state = $address['state'] ?? $address['province'] ?? '';
        $countryId = $address['country_id'] ?? null;

        if (!$countryId) {
            return null;
        }

        $country = Country::where('id', $countryId)->where('is_active', true)->first();
        if (!$country) {
            return null;
        }

        // Try zone-level match first
        if ($state) {
            $zone = Zone::where('country_id', $country->id)->where('name', $state)->first()
                ?? Zone::where('country_id', $country->id)->where('name', 'like', "%{$state}%")->first();
            if ($zone) {
                $entry = GeoZoneEntry::where('country_id', $country->id)
                    ->where('zone_id', $zone->id)
                    ->first();
                if ($entry) {
                    return $entry->geo_zone_id;
                }
            }
        }

        // Fall back to country-level match
        $entry = GeoZoneEntry::where('country_id', $country->id)
            ->whereNull('zone_id')
            ->first();

        return $entry?->geo_zone_id;
    }
}
