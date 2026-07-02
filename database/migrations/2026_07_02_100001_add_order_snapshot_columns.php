<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Order historical snapshots: orders keep permanent copies of the human-facing
 * facts that were true at order time (payment title, tracking link, country
 * names, item photo path), so later config changes/deletions never rewrite
 * history. See docs/superpowers/specs/2026-07-02-order-snapshots-design.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_method_title')->nullable()->after('payment_method');
            $table->string('tracking_link', 2048)->nullable()->after('tracking_number');
            $table->string('shipping_country_name')->nullable()->after('shipping_country_id');
            $table->string('billing_country_name')->nullable()->after('billing_country_id');
        });

        Schema::table('order_products', function (Blueprint $table) {
            $table->string('image', 2048)->nullable()->after('sku');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_method_title', 'tracking_link', 'shipping_country_name', 'billing_country_name']);
        });
        Schema::table('order_products', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }
};
