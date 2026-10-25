<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A product with variants now carries the stock of its switched-on variants
 * only (owner ruling 2026-10-16). The total is derived, never typed, so this
 * recomputes it once for every such product; from here every writer keeps
 * it through Product::syncStockFromVariants(). Same shape as the earlier
 * price-modifier resync.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')->where('has_variants', true)->update([
            'quantity' => DB::raw('(SELECT COALESCE(SUM(pv.quantity), 0) FROM product_variants pv WHERE pv.product_id = products.id AND pv.is_active = 1)'),
        ]);
    }

    public function down(): void
    {
        DB::table('products')->where('has_variants', true)->update([
            'quantity' => DB::raw('(SELECT COALESCE(SUM(pv.quantity), 0) FROM product_variants pv WHERE pv.product_id = products.id)'),
        ]);
    }
};
