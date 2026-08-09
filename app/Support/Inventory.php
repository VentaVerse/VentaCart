<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductVariant;

/**
 * What the store is holding, in two queries.
 *
 * The day sheet's rail states three things about stock: how much of it
 * there is, how much of it has run out, and what the whole of it is worth
 * on the shelf. Each is a sum the store can actually stand behind.
 *
 * AT SALE PRICE, NEVER AT COST. The ERP this page mirrors states its
 * inventory at cost and prints a margin beside it, and Venta deliberately
 * does not: `products.cost` is populated on 307 of 1,881 rows here and on 0
 * of 92 variants, so a cost figure would be the value of a third of the
 * catalogue presented as the value of all of it, and a margin computed from
 * it would be worse. The sale price is on every row, so that is what is
 * summed. When cost becomes a figure a merchant maintains, this is the one
 * place that has to change.
 *
 * COUNTED WHERE THE QUANTITY LIVES. A product carrying variants keeps no
 * quantity of its own, so it is counted through its variants and never
 * through itself; a simple product is counted through itself. Adding both
 * halves of that would double every variant product's stock. Untracked
 * products are excluded from the value and the shortage entirely, because
 * a quantity nobody maintains is not a reading.
 *
 * TWO QUERIES, both aggregates. Nothing is hydrated and nothing is counted
 * in PHP: this page loads at every login and an inventory panel is not
 * worth a row of the catalogue.
 */
class Inventory
{
    /**
     * @return array{products:int, outOfStock:int, atSale:float}
     */
    public static function for(int $storeId): array
    {
        // The catalogue's own half: every active product is counted, but
        // only a simple one carries stock.
        $simple = Product::query()
            ->where('store_id', $storeId)
            ->where('is_active', true)
            ->selectRaw(
                'COUNT(*) AS products, '
                . 'SUM(CASE WHEN has_variants = 0 AND track_stock = 1 AND quantity <= 0 THEN 1 ELSE 0 END) AS out_of_stock, '
                . 'SUM(CASE WHEN has_variants = 0 AND track_stock = 1 AND quantity > 0 THEN price * quantity ELSE 0 END) AS at_sale'
            )
            ->first();

        // The variants' half. A variant is what a merchant reorders, so it
        // is the unit that runs out, exactly as LowStock already counts it.
        $variants = ProductVariant::query()
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('products.store_id', $storeId)
            ->where('products.is_active', true)
            ->where('products.track_stock', true)
            ->whereNull('products.deleted_at')
            ->where('product_variants.is_active', true)
            ->selectRaw(
                'SUM(CASE WHEN product_variants.quantity <= 0 THEN 1 ELSE 0 END) AS out_of_stock, '
                . 'SUM(CASE WHEN product_variants.quantity > 0 THEN product_variants.price * product_variants.quantity ELSE 0 END) AS at_sale'
            )
            ->first();

        return [
            'products' => (int) ($simple->products ?? 0),
            'outOfStock' => (int) ($simple->out_of_stock ?? 0) + (int) ($variants->out_of_stock ?? 0),
            'atSale' => (float) ($simple->at_sale ?? 0) + (float) ($variants->at_sale ?? 0),
        ];
    }
}
