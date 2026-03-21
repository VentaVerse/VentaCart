<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->string('district')->nullable()->after('city');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_district')->nullable()->after('shipping_city');
        });
    }

    public function down(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->dropColumn('district');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('shipping_district');
        });
    }
};
