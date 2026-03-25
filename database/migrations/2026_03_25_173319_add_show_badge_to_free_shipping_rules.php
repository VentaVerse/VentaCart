<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('free_shipping_rules', function (Blueprint $table) {
            $table->boolean('show_badge')->default(false)->after('apply_whole_cart');
        });
    }

    public function down(): void
    {
        Schema::table('free_shipping_rules', function (Blueprint $table) {
            $table->dropColumn('show_badge');
        });
    }
};
