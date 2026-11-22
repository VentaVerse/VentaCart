<?php

namespace App\Services;

use App\Models\Product;
use App\Support\ResolvedPrice;

/**
 * The one place that decides what a buyer pays. The price is the lowest of
 * the regular price, every active special price, and the lowest discount the
 * buyer qualifies for (their group's or all groups', quantity reached). A rule that is
 * not below the regular price never raises it.
 */
final class PriceResolver
{
    public const SOURCE_SPECIAL = 'special';
    public const SOURCE_DISCOUNT = 'discount';

    /**
     * $regularPrice is the variant's price, or the product's own when it has
     * no variants. A null $customerGroupId means no group: only discounts
     * for all customer groups apply.
     */
    public static function resolve(Product $product, float $regularPrice, int $quantity = 1, ?int $customerGroupId = null): ResolvedPrice
    {
        $product->loadMissing(['specials', 'discounts']);

        $regular = round($regularPrice, 2);
        $now = now();

        $special = null;
        $specialPrice = null;
        foreach ($product->specials as $row) {
            if (! $row->isActiveAt($now)) {
                continue;
            }
            $candidate = self::apply($row->price_type, (float) $row->value, $product, $regularPrice);
            if ($specialPrice === null || $candidate < $specialPrice) {
                $special = $row;
                $specialPrice = $candidate;
            }
        }

        $discount = null;
        $discountPrice = null;
        foreach ($product->discounts as $row) {
            $appliesToGroup = $row->customer_group_id === null
                || ($customerGroupId !== null && (int) $row->customer_group_id === $customerGroupId);
            if (! $appliesToGroup || (int) $row->quantity > $quantity) {
                continue;
            }
            $candidate = self::apply($row->price_type, (float) $row->value, $product, $regularPrice);
            // The lowest qualifying price wins, so buying more never costs
            // more per item.
            if ($discount === null || $candidate < $discountPrice) {
                $discount = $row;
                $discountPrice = $candidate;
            }
        }

        $specialWins = $specialPrice !== null && $specialPrice < $regular
            && ($discountPrice === null || $specialPrice <= $discountPrice);
        $discountWins = ! $specialWins && $discountPrice !== null && $discountPrice < $regular;

        if ($specialWins) {
            return new ResolvedPrice($specialPrice, $regular, self::SOURCE_SPECIAL, $special, null);
        }
        if ($discountWins) {
            return new ResolvedPrice($discountPrice, $regular, self::SOURCE_DISCOUNT, null, $discount);
        }

        return new ResolvedPrice($regular, $regular, null, null, null);
    }

    /**
     * What one rule charges. A fixed value keeps the variant's difference
     * from the product's own price; a percentage comes off the variant's
     * own price. Never below zero.
     */
    public static function apply(string $priceType, float $value, Product $product, float $regularPrice): float
    {
        $price = $priceType === 'percent'
            ? $regularPrice * (1 - $value / 100)
            : $value + ($regularPrice - (float) $product->price);

        return max(0.0, round($price, 2));
    }

    /**
     * The product page's "buy more, pay less" table: one row per distinct
     * discount quantity above 1 that applies to the group, ascending, kept
     * only when its price is below the unit price (quantity 1) and below the
     * row before. It uses resolve(), so a special that beats every tier
     * hides them. The unit price is shown separately.
     *
     * @return array<int, array{qty:int, price:float}>
     */
    public static function tiers(Product $product, float $regularPrice, ?int $customerGroupId): array
    {
        $product->loadMissing(['specials', 'discounts']);

        $quantities = $product->discounts
            ->filter(fn ($d) => $d->customer_group_id === null
                || ($customerGroupId !== null && (int) $d->customer_group_id === $customerGroupId))
            ->pluck('quantity')->map(fn ($q) => (int) $q)
            ->filter(fn ($q) => $q > 1)->unique()->sort()->values();

        $rows = [];
        $floor = (int) round(self::resolve($product, $regularPrice, 1, $customerGroupId)->price * 100);
        foreach ($quantities as $qty) {
            $price = self::resolve($product, $regularPrice, $qty, $customerGroupId)->price;
            $cents = (int) round($price * 100);
            if ($cents < $floor) {
                $rows[] = ['qty' => $qty, 'price' => $price];
                $floor = $cents;
            }
        }

        return $rows;
    }
}
