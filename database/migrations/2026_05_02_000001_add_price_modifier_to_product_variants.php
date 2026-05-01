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
        Schema::table('product_variants', function (Blueprint $table) {
            $table->decimal('price_modifier', 12, 2)->default(0)->after('price');
        });

        DB::statement('
            UPDATE product_variants pv
            JOIN products p ON pv.product_id = p.id
            SET pv.price_modifier = pv.price - p.price
        ');
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('price_modifier');
        });
    }
};
