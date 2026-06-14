<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Currency as a payment-method restriction condition: allow a method only for
 * (or block it for) the shopper's active display currency.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_method_restrictions', function (Blueprint $table) {
            $table->json('currency_codes')->nullable()->after('shipping_method_keys');
            $table->json('exclude_currency_codes')->nullable()->after('exclude_brand_ids');
        });
    }

    public function down(): void
    {
        Schema::table('payment_method_restrictions', function (Blueprint $table) {
            $table->dropColumn(['currency_codes', 'exclude_currency_codes']);
        });
    }
};
