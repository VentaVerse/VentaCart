<?php

namespace App\Support;

use App\Models\Product;
use App\Services\CartService;
use App\Services\PriceResolver;

/**
 * The one place a storefront view gets a product's price from.
 *
 * Cards, widgets and product pages must show what the cart will charge. A
 * view that picks a price on its own drifts from the cart: it misses the
 * default customer group's discounts for guests, or weighs a special
 * against a discount another way, and the shopper sees one price on the
 * card and another at checkout. This asks PriceResolver, as the cart does,
 * for both the amount and the rule behind it.
 */
final class Pricing
{
    /**
     * @param  int|null  $variantId  a variant of this product; its own price is the regular price
     * @param  int  $quantity  the quantity the price is for (quantity tiers start above 1)
     */
    public static function display(Product $product, ?int $variantId = null, int $quantity = 1): DisplayPrice
    {
        $product->loadMissing(['specials', 'discounts']);

        $regular = (float) $product->price;

        if ($variantId !== null) {
            $variant = $product->relationLoaded('variants')
                ? $product->variants->firstWhere('id', $variantId)
                : $product->variants()->whereKey($variantId)->first();

            // A variant that is switched off cannot be bought, so it has no
            // price of its own to show.
            if ($variant !== null && $variant->is_active && $variant->price !== null) {
                $regular = (float) $variant->price;
            }
        }

        $quantity = max(1, $quantity);
        $resolved = PriceResolver::resolve($product, $regular, $quantity, CartService::customerGroupId());
        // A countdown runs to the special's end; a discount never ends.
        $special = $resolved->source === PriceResolver::SOURCE_SPECIAL ? $resolved->special : null;

        return new DisplayPrice(
            price: $resolved->price,
            regularPrice: $resolved->regularPrice,
            source: $resolved->source,
            special: $special,
            endsAt: $special?->date_end,
        );
    }
}
