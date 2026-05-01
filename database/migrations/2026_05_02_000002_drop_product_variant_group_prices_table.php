<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The variant-modifier redesign collapses customer-group pricing into the
 * `product_specials` table (display_mode='override' rows). Per-variant
 * per-group pricing is no longer part of the model — there's no admin UI
 * to manage it, ERP doesn't push to it, and storefront/cart code paths that
 * read it are being removed in the same cleanup PR.
 *
 * On live this drops a small number of legacy rows (residue from the
 * OpenCart→Venta migration). The row count is logged for paranoia.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('product_variant_group_prices')) {
            $count = DB::table('product_variant_group_prices')->count();
            echo "Dropping product_variant_group_prices ({$count} rows discarded)" . PHP_EOL;
            Schema::drop('product_variant_group_prices');
        }
    }

    public function down(): void
    {
        Schema::create('product_variant_group_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_group_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 12, 2);
            $table->timestamps();
            $table->unique(['product_variant_id', 'customer_group_id'], 'pvgp_variant_group_unique');
        });
    }
};
