<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('country_code');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_phone', 30)->nullable()->after('shipping_country_code');
            $table->string('billing_phone', 30)->nullable()->after('billing_country_code');
        });
    }

    public function down(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->dropColumn('phone');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['shipping_phone', 'billing_phone']);
        });
    }
};
