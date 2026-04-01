<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('products', 'model')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('model');
            });
        }

        if (Schema::hasColumn('order_products', 'model')) {
            Schema::table('order_products', function (Blueprint $table) {
                $table->dropColumn('model');
            });
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('model')->nullable()->after('sku');
        });

        Schema::table('order_products', function (Blueprint $table) {
            $table->string('model')->nullable()->after('sku');
        });
    }
};
