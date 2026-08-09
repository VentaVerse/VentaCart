<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;

/**
 * One definition of "about to run out", shared by everything that asks.
 *
 * The predicate used to be written out three times (the stock:check-low
 * command, the dashboard's count, and now the catalog's Low stock filter),
 * with a comment in the dashboard promising to keep its copy in step by
 * hand. That promise is what this class replaces: a store's alert mail, the
 * day sheet's work line, and the filtered product list are the same set or
 * they are a bug.
 *
 * "Low" is deliberately NOT "out": quantity has to be at least 1. Something
 * already at zero has run out, which is a different fact needing a
 * different answer, and folding the two together would make the number the
 * merchant acts on unreadable.
 */
class LowStock
{
    public const DEFAULT_THRESHOLD = 5;

    public static function threshold(): int
    {
        $configured = Setting::where('group', 'stock')
            ->where('key', 'low_stock_threshold')
            ->value('value');

        return (int) ($configured ?: self::DEFAULT_THRESHOLD);
    }

    /** Simple products (no variants) whose own quantity is low. */
    public static function products(?int $threshold = null): Builder
    {
        $threshold ??= self::threshold();

        return Product::query()
            ->where('is_active', true)
            ->where('track_stock', true)
            ->where('has_variants', false)
            ->where('quantity', '>', 0)
            ->where('quantity', '<=', $threshold);
    }

    /** Variants whose own quantity is low, on a tracked, active product. */
    public static function variants(?int $threshold = null): Builder
    {
        $threshold ??= self::threshold();

        return ProductVariant::query()
            ->where('is_active', true)
            ->where('quantity', '>', 0)
            ->where('quantity', '<=', $threshold)
            ->whereHas('product', function (Builder $q) {
                $q->where('is_active', true)->where('track_stock', true);
            });
    }

    /**
     * How many sellable lines are low: simple products plus low variants,
     * counted separately because a variant is what the merchant reorders,
     * not its parent.
     */
    public static function count(?int $threshold = null): int
    {
        $threshold ??= self::threshold();

        return self::products($threshold)->count() + self::variants($threshold)->count();
    }

    /**
     * Narrows a PRODUCT listing to the rows behind that count: a simple
     * product that is itself low, or a product carrying at least one low
     * variant. Written as one nested where so it composes with whatever
     * filters the caller already applied.
     */
    public static function scopeProductListing(Builder $query, ?int $threshold = null): Builder
    {
        $threshold ??= self::threshold();

        return $query->where(function (Builder $outer) use ($threshold) {
            $outer->where(function (Builder $simple) use ($threshold) {
                $simple->where('is_active', true)
                    ->where('track_stock', true)
                    ->where('has_variants', false)
                    ->where('quantity', '>', 0)
                    ->where('quantity', '<=', $threshold);
            })->orWhere(function (Builder $withVariants) use ($threshold) {
                $withVariants->where('is_active', true)
                    ->where('track_stock', true)
                    ->whereHas('variants', function (Builder $v) use ($threshold) {
                        $v->where('is_active', true)
                            ->where('quantity', '>', 0)
                            ->where('quantity', '<=', $threshold);
                    });
            });
        });
    }
}
