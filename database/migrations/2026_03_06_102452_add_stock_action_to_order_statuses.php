<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_statuses', function (Blueprint $table) {
            $table->string('stock_action')->nullable()->after('is_default');
        });

        // Set sensible defaults
        DB::table('order_statuses')->whereIn('slug', ['processing', 'payment-received', 'shipped', 'delivered', 'complete'])
            ->update(['stock_action' => 'decrement']);
        DB::table('order_statuses')->whereIn('slug', ['canceled', 'expired'])
            ->update(['stock_action' => 'increment']);
    }

    public function down(): void
    {
        Schema::table('order_statuses', function (Blueprint $table) {
            $table->dropColumn('stock_action');
        });
    }
};
