<?php

namespace App\Services;

use App\Models\Product;
use App\Support\ResolvedPrice;

/**
 * The one place that decides what a buyer pays. The price is the lowest of
 * the regular price, every active special price, and the buyer's group
 * discount at the deepest quantity tier the line reaches. A rule that is
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
            // The deepest tier reached wins even when a shallower one is
            // cheaper; within a tier the lowest price wins.
            if ($discount === null
                || (int) $row->quantity > (int) $discount->quantity
                || ((int) $row->quantity === (int) $discount->quantity && $candidate < $discountPrice)) {
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
     * The quantity steps a group sees: one row per discount quantity that
     * applies to it, in ascending order, keeping only rows whose price
     * differs from the row before.
     *
     * @return array<int, array{qty:int, price:float}>
     */
    public static function tiers(Product $product, float $regularPrice, ?int $customerGroupId): array
    {
        $product->loadMissing(['specials', 'discounts']);

        $quantities = $product->discounts
            ->filter(fn ($d) => $d->customer_group_id === null
                || ($customerGroupId !== null && (int) $d->customer_group_id === $customerGroupId))
            ->pluck('quantity')->map(fn ($q) => (int) $q)->unique()->sort()->values();

        $rows = [];
        $previous = round($regularPrice, 2);
        foreach ($quantities as $qty) {
            $price = self::resolve($product, $regularPrice, $qty, $customerGroupId)->price;
            if ($price !== $previous) {
                $rows[] = ['qty' => $qty, 'price' => $price];
                $previous = $price;
            }
        }

        return $rows;
    }
}
