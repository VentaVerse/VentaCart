<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class BadgeService
{
    private ThemeService $theme;

    public function __construct(ThemeService $theme)
    {
        $this->theme = $theme;
    }

    public function getBadgesForProduct(Product $product): array
    {
        $badges = [];

        // NEW badge
        if ($this->isNew($product)) {
            $badges[] = [
                'type' => 'new',
                'label' => 'NEW',
                'color' => '#22c55e',
                'text_color' => '#ffffff',
            ];
        }

        // HOT badge
        if ($this->isHot($product)) {
            $badges[] = [
                'type' => 'hot',
                'label' => 'HOT',
                'color' => $this->theme->get('colors.primary', '#E2293D'),
                'text_color' => '#ffffff',
            ];
        }

        // Free Shipping badge
        if ($this->qualifiesForFreeShippingBadge($product)) {
            $badges[] = [
                'type' => 'free_shipping',
                'label' => 'FREE SHIPPING',
                'color' => '#0891b2',
                'text_color' => '#ffffff',
            ];
        }

        // Sale badge
        $saleInfo = $this->getSaleInfo($product);
        if ($saleInfo) {
            $badges[] = [
                'type' => 'sale',
                'label' => '-' . $saleInfo['percent'] . '%',
                'color' => $this->theme->get('colors.primary', '#E2293D'),
                'text_color' => '#ffffff',
                'end_date' => $saleInfo['end_date'],
            ];
        }

        return $badges;
    }

    private function isNew(Product $product): bool
    {
        $newCount = (int) $this->theme->get('badges.new_count', 15);
        $newIds = $this->getNewProductIds($newCount);
        return in_array($product->id, $newIds);
    }

    private function isHot(Product $product): bool
    {
        $hotCount = (int) $this->theme->get('badges.hot_count', 5);
        $hotIds = $this->getHotProductIds($hotCount);
        return in_array($product->id, $hotIds);
    }

    private function getSaleInfo(Product $product): ?array
    {
        if (!$product->relationLoaded('specials')) {
            $product->load('specials');
        }

        $customerGroupId = \Illuminate\Support\Facades\Auth::guard('customer')->user()?->customer_group_id;

        $special = $product->specials
            ->where('display_mode', 'sale')
            ->filter(function ($s) use ($customerGroupId) {
                if ($s->date_start && $s->date_start > now()) return false;
                if ($s->date_end && $s->date_end < now()) return false;
                if ($s->customer_group_id !== null && $s->customer_group_id != $customerGroupId) return false;
                if ($s->quantity > 1) return false; // Quantity discounts don't show as sale badges
                return true;
            })
            ->sortBy('priority')
            ->first();

        if (!$special || $product->price <= 0) {
            return null;
        }

        return [
            'percent' => round((1 - $special->price / $product->price) * 100),
            'sale_price' => $special->price,
            'end_date' => $special->date_end?->toISOString(),
        ];
    }

    private function getNewProductIds(int $count): array
    {
        return Cache::remember('badge_new_ids', 3600, function () use ($count) {
            return Product::where('is_active', true)
                ->whereNotNull('date_available')
                ->latest('date_available')
                ->limit($count)
                ->pluck('id')
                ->toArray();
        });
    }

    private function getHotProductIds(int $count): array
    {
        return Cache::remember('badge_hot_ids', 3600, function () use ($count) {
            // For now, use view count as proxy for popularity
            // Will switch to order count when orders are tracked
            return Product::where('is_active', true)
                ->orderByDesc('views')
                ->limit($count)
                ->pluck('id')
                ->toArray();
        });
    }

    private function qualifiesForFreeShippingBadge(Product $product): bool
    {
        $rules = $this->getFreeShippingBadgeRules();
        if ($rules->isEmpty()) return false;

        $productBrandId = $product->brand_id;
        $productCategoryIds = $product->relationLoaded('categories')
            ? $product->categories->pluck('id')->toArray()
            : $product->categories()->pluck('categories.id')->toArray();

        foreach ($rules as $rule) {
            $brandMatch = empty($rule->brand_ids) || in_array($productBrandId, $rule->brand_ids);
            $categoryMatch = empty($rule->category_ids) || !empty(array_intersect($productCategoryIds, $rule->category_ids));
            $excludeBrand = !empty($rule->exclude_brand_ids) && in_array($productBrandId, $rule->exclude_brand_ids);
            $excludeCategory = !empty($rule->exclude_category_ids) && !empty(array_intersect($productCategoryIds, $rule->exclude_category_ids));

            if ($brandMatch && $categoryMatch && !$excludeBrand && !$excludeCategory) {
                return true;
            }
        }

        return false;
    }

    private function getFreeShippingBadgeRules()
    {
        return Cache::remember('free_shipping_badge_rules', 3600, function () {
            // Guarded: the model lives in the shipping-free extension;
            // no extension, no free shipping badges.
            if (! class_exists(\Extensions\ShippingFree\Models\FreeShippingRule::class)
                || ! \Illuminate\Support\Facades\Schema::hasTable('free_shipping_rules')) {
                return collect();
            }

            return \Extensions\ShippingFree\Models\FreeShippingRule::where('is_active', true)
                ->where('show_badge', true)
                ->get();
        });
    }

    public static function clearCache(): void
    {
        Cache::forget('badge_new_ids');
        Cache::forget('badge_hot_ids');
        Cache::forget('free_shipping_badge_rules');
    }
}
