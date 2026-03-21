<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('free_shipping_rules', function (Blueprint $table) {
            $table->boolean('apply_whole_cart')->default(false)->after('exclude_customer_group_ids');
        });
    }

    public function down(): void
    {
        Schema::table('free_shipping_rules', function (Blueprint $table) {
            $table->dropColumn('apply_whole_cart');
        });
    }
};
