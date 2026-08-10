<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Products are hard deleted from here on.
 *
 * A soft-deleted product still holds its SKU, and the SKU is the key the ERP
 * and every other external integration syncs on. A deleted product that keeps
 * its SKU is a deleted product that still blocks the SKU, so "deleted" has to
 * mean the row is gone.
 *
 * Order of operations matters and is not interchangeable: purge the already
 * soft-deleted rows FIRST, then drop the column. Dropping the column first
 * would resurrect every trashed product into the live catalog and the
 * storefront, because nothing would be left to mark them as deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'deleted_at')) {
            return;
        }

        // Purge first. The children go with them through the existing
        // ON DELETE CASCADE foreign keys; order_products.product_id is
        // ON DELETE SET NULL and keeps its own name/sku/price snapshot, so
        // order history is untouched by this.
        DB::table('products')->whereNotNull('deleted_at')->delete();

        Schema::table('products', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'deleted_at')) {
            return;
        }

        // The column comes back empty. The rows purged in up() are NOT
        // recoverable, which is intended: the owner had already deleted those
        // products, and this migration only finished the job.
        Schema::table('products', function (Blueprint $table) {
            $table->softDeletes();
        });
    }
};
