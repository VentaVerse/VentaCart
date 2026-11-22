<?php

namespace App\Support;

use App\Models\ProductDiscount;
use App\Models\ProductSpecial;

/**
 * The outcome of pricing one product (or variant) for one buyer and quantity.
 * source says which rule set the price, and is null when the regular price
 * is paid; special and discount carry the winning row for that source.
 */
final class ResolvedPrice
{
    public function __construct(
        public readonly float $price,
        public readonly float $regularPrice,
        public readonly ?string $source,
        public readonly ?ProductSpecial $special,
        public readonly ?ProductDiscount $discount,
    ) {}
}
