<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Update customer_addresses
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->dropColumn(['company', 'address_2']);
            $table->string('barangay')->nullable()->after('city');
        });

        // Update orders shipping address
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['shipping_company', 'shipping_address_2']);
            $table->string('shipping_barangay')->nullable()->after('shipping_city');
        });
    }

    public function down(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->string('company')->nullable()->after('last_name');
            $table->text('address_2')->nullable()->after('address_1');
            $table->dropColumn('barangay');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_company')->nullable()->after('shipping_last_name');
            $table->text('shipping_address_2')->nullable()->after('shipping_address_1');
            $table->dropColumn('shipping_barangay');
        });
    }
};
