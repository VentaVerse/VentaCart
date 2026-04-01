<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Build iso2 → id mapping
        $countryMap = DB::table('countries')->pluck('id', 'iso2')->toArray();

        // --- 1. ZONES: country_code (varchar) → country_id (bigint FK) ---
        Schema::table('zones', function (Blueprint $table) {
            $table->unsignedBigInteger('country_id')->nullable()->after('id');
        });

        // Migrate data
        foreach ($countryMap as $iso2 => $countryId) {
            DB::table('zones')->where('country_code', $iso2)->update(['country_id' => $countryId]);
        }

        // Drop old FK and column
        Schema::table('zones', function (Blueprint $table) {
            $table->dropForeign(['country_code']);
            $table->dropColumn('country_code');
        });

        // Add proper FK
        Schema::table('zones', function (Blueprint $table) {
            $table->foreign('country_id')->references('id')->on('countries')->cascadeOnDelete();
            $table->index('country_id');
        });

        // --- 2. CUSTOMER_ADDRESSES: country_code → country_id ---
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->unsignedBigInteger('country_id')->nullable()->after('country_code');
        });

        foreach ($countryMap as $iso2 => $countryId) {
            DB::table('customer_addresses')->where('country_code', $iso2)->update(['country_id' => $countryId]);
        }

        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->dropColumn('country_code');
        });

        // --- 3. ORDERS: shipping_country_code → shipping_country_id, billing_country_code → billing_country_id ---
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('shipping_country_id')->nullable()->after('shipping_country_code');
            $table->unsignedBigInteger('billing_country_id')->nullable()->after('billing_country_code');
        });

        foreach ($countryMap as $iso2 => $countryId) {
            DB::table('orders')->where('shipping_country_code', $iso2)->update(['shipping_country_id' => $countryId]);
            DB::table('orders')->where('billing_country_code', $iso2)->update(['billing_country_id' => $countryId]);
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['shipping_country_code', 'billing_country_code']);
        });

        // --- 4. Drop unique constraint on countries.iso2 ---
        Schema::table('countries', function (Blueprint $table) {
            $table->dropUnique('countries_iso2_unique');
            $table->index('iso2');
        });
    }

    public function down(): void
    {
        $countryMap = DB::table('countries')->pluck('iso2', 'id')->toArray();

        // Restore countries.iso2 unique
        Schema::table('countries', function (Blueprint $table) {
            $table->dropIndex(['iso2']);
            $table->unique('iso2');
        });

        // Restore orders
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_country_code', 3)->nullable()->after('shipping_postcode');
            $table->string('billing_country_code', 3)->nullable()->after('billing_postcode');
        });
        foreach ($countryMap as $id => $iso2) {
            DB::table('orders')->where('shipping_country_id', $id)->update(['shipping_country_code' => $iso2]);
            DB::table('orders')->where('billing_country_id', $id)->update(['billing_country_code' => $iso2]);
        }
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['shipping_country_id', 'billing_country_id']);
        });

        // Restore customer_addresses
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->string('country_code', 3)->nullable()->after('postcode');
        });
        foreach ($countryMap as $id => $iso2) {
            DB::table('customer_addresses')->where('country_id', $id)->update(['country_code' => $iso2]);
        }
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->dropColumn('country_id');
        });

        // Restore zones
        Schema::table('zones', function (Blueprint $table) {
            $table->string('country_code', 2)->nullable()->after('id');
        });
        foreach ($countryMap as $id => $iso2) {
            DB::table('zones')->where('country_id', $id)->update(['country_code' => $iso2]);
        }
        Schema::table('zones', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropIndex(['country_id']);
            $table->dropColumn('country_id');
            $table->foreign('country_code')->references('iso2')->on('countries')->cascadeOnDelete();
        });
    }
};
