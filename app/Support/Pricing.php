<?php

namespace App\Support;

use App\Models\Product;
use App\Services\CartService;

/**
 * The one place a storefront view gets a product's price from.
 *
 * Cards, widgets and product pages must show what the cart will charge. A
 * view that picks a special on its own drifts from the cart: it misses the
 * default customer group's specials for guests, or breaks a tie another
 * way, and the shopper sees one price on the card and another at checkout.
 * This asks CartService for both the amount and the special behind it.
 */
final class Pricing
{
    /**
     * @param  int|null  $variantId  a variant of this product; its own price is the regular price
     * @param  int  $quantity  the quantity the price is for (quantity tiers start above 1)
     */
    public static function display(Product $product, ?int $variantId = null, int $quantity = 1): DisplayPrice
    {
        $product->loadMissing('specials');

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
        $groupId = CartService::customerGroupId();
        $special = CartService::winningSpecial($product, $quantity, $groupId);

        return new DisplayPrice(
            price: CartService::resolvePrice($product, $regular, $quantity, $groupId),
            regularPrice: $regular,
            special: $special,
            displayMode: $special?->display_mode,
            endsAt: $special?->date_end,
        );
    }
}
