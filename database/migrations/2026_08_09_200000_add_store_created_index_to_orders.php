<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every "what happened in this window" question the admin asks reads
 * orders by store and date: the dashboard's day figures, and any report
 * that follows. Without this index MySQL answers them off
 * `orders_store_id_foreign` alone and then filters ~27,700 rows by hand,
 * measured at 91ms per window query on the development machine.
 *
 * Composite and in this order because store_id is always an equality and
 * created_at is always the range: the leading equality lets the range
 * seek, which the reverse order cannot do.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['store_id', 'created_at'], 'orders_store_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_store_created_index');
        });
    }
};
