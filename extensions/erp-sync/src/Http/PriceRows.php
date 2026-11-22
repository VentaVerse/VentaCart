<?php

namespace Extensions\ErpSync\Http;

use App\Models\Product;
use App\Models\ProductDiscount;
use App\Models\ProductSpecial;
use App\Services\PriceResolver;

/**
 * A product's special prices and discounts as the ERP API shows them.
 *
 * One shape for the pricing endpoints and the product read, so a list read
 * from either can be sent straight back in a PUT. Each row carries the price
 * it works out to on the product's own price, so a sync can check a percent
 * without repeating the arithmetic. Times carry the store's offset.
 */
final class PriceRows
{
    /** @return list<array<string, mixed>> */
    public static function specials(Product $product): array
    {
        $regular = (float) $product->price;

        return $product->specials->map(fn (ProductSpecial $s) => [
            'id' => $s->id,
            'type' => $s->price_type,
            'value' => (float) $s->value,
            'starts_at' => $s->date_start?->toIso8601String(),
            'ends_at' => $s->date_end?->toIso8601String(),
            'price' => PriceResolver::apply($s->price_type, (float) $s->value, $product, $regular),
            'updated_at' => $s->updated_at?->toIso8601String(),
        ])->values()->all();
    }

    /** @return list<array<string, mixed>> */
    public static function discounts(Product $product): array
    {
        $regular = (float) $product->price;

        return $product->discounts->map(fn (ProductDiscount $d) => [
            'id' => $d->id,
            'customer_group_id' => $d->customer_group_id === null ? null : (int) $d->customer_group_id,
            'min_quantity' => (int) $d->quantity,
            'type' => $d->price_type,
            'value' => (float) $d->value,
            'price' => PriceResolver::apply($d->price_type, (float) $d->value, $product, $regular),
            'updated_at' => $d->updated_at?->toIso8601String(),
        ])->values()->all();
    }
}
