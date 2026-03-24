<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_statuses', function (Blueprint $table) {
            // Add a plain index on store_id first — MySQL needs it for the FK to stores
            $table->index('store_id', 'order_statuses_store_id_index');
        });

        Schema::table('order_statuses', function (Blueprint $table) {
            $table->dropUnique('order_statuses_store_id_slug_unique');
            $table->dropColumn('slug');
        });
    }

    public function down(): void
    {
        Schema::table('order_statuses', function (Blueprint $table) {
            $table->string('slug')->after('name')->default('');
            $table->unique(['store_id', 'slug']);
        });

        Schema::table('order_statuses', function (Blueprint $table) {
            $table->dropIndex('order_statuses_store_id_index');
        });
    }
};
