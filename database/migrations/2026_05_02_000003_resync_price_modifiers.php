<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resync `price_modifier` from current absolute `price` values.
 *
 * Why a second pass when migration 2026_05_02_000001 already backfilled
 * the column?
 *
 * Between that migration and the model `saving` boot hook landing in the
 * same release as this migration, every ERP `POST /api/v1/products` push
 * hit `syncVariantsFromErp`, which deletes and recreates variants. Those
 * recreations set only `price`, leaving `price_modifier` at its column
 * default of 0 — silently wiping the original backfill on any product
 * ERP touched. On stores with nightly ERP syncs, that's everything.
 *
 * The boot hook (now in `App\Models\ProductVariant::booted`) prevents
 * future drift by recomputing the modifier on any variant write that
 * dirties `price`. This migration is the one-time cleanup.
 *
 * Idempotent — derives modifier purely from the current price columns,
 * so re-running it is a no-op on already-correct data.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('product_variants') || ! Schema::hasColumn('product_variants', 'price_modifier')) {
            return;
        }

        // Backfilled via the query builder (not a raw UPDATE...JOIN) so this
        // migration runs on any supported driver, not just MySQL.
        DB::table('product_variants')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->whereNotNull('product_variants.price')
            ->whereNotNull('products.price')
            ->select('product_variants.id as variant_id', 'product_variants.price as variant_price', 'products.price as product_price')
            ->orderBy('product_variants.id')
            ->get()
            ->each(function ($row) {
                DB::table('product_variants')
                    ->where('id', $row->variant_id)
                    ->update(['price_modifier' => $row->variant_price - $row->product_price]);
            });

        $total = DB::table('product_variants')->count();
        $nonzero = DB::table('product_variants')->where('price_modifier', '!=', 0)->count();
        echo "Resynced price_modifier on {$total} variants ({$nonzero} non-zero modifiers)" . PHP_EOL;
    }

    public function down(): void
    {
        // No-op: this migration normalizes a derived column. Reverting it
        // would mean rewriting modifiers to 0 — which is the broken state
        // we just fixed.
    }
};
