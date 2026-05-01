<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Variants now express their price as a signed delta from the product's base
 * price (price_modifier), with the absolute price still stored on `price` for
 * ERP/API compatibility.
 *
 *   price_modifier  = signed offset from product.price
 *   price           = product.price + price_modifier  (kept in sync on save)
 *
 * Backfills existing rows from current absolute prices: each variant's
 * implied delta is (variant.price - product.price). For product 1779
 * (variants 720/1000/1280 against base 720), this yields 0/+280/+560.
 *
 * The price column stays — every consumer outside the admin form (cart, ERP
 * sync, storefront) keeps reading the absolute total, no breakage.
 */
return new class extends Migration {
    public function up(): void
    {
        // Idempotent column add — survives a partial re-run where MariaDB
        // auto-committed the DDL on the first attempt but the migration row
        // never made it into the migrations table because the backfill below
        // crashed.
        if (! Schema::hasColumn('product_variants', 'price_modifier')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->decimal('price_modifier', 12, 2)->default(0)->after('price');
            });
        }

        // Raw UPDATE must apply the configured table prefix manually — the
        // query builder and Schema facade do this automatically, but
        // DB::statement() goes straight to MySQL with the literal name.
        $prefix = DB::getTablePrefix();
        DB::statement("
            UPDATE {$prefix}product_variants pv
            JOIN {$prefix}products p ON pv.product_id = p.id
            SET pv.price_modifier = pv.price - p.price
        ");
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('price_modifier');
        });
    }
};
