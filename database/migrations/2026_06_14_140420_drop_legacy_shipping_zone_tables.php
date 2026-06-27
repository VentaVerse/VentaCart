<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the legacy "ShippingZone" system (shipping_zones + shipping_zone_regions),
 * which was keyed on Philippine regions (ph_region_id) and superseded by the generic
 * geo_zones system. The active shipping-rate table (shipping_zone_rates) is kept but
 * its vestigial shipping_zone_id column is dropped — rates now belong to geo_zones via
 * geo_zone_id. Nothing live referenced these tables (ShippingZoneController operates on
 * GeoZone). See docs/superpowers/specs/2026-06-14-global-localization-design.md
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('shipping_zone_rates', 'shipping_zone_id')) {
            Schema::table('shipping_zone_rates', function (Blueprint $table) {
                $table->dropForeign(['shipping_zone_id']);
                $table->dropIndex('szr_zone_group_index');
                $table->dropColumn('shipping_zone_id');
            });
        }

        Schema::dropIfExists('shipping_zone_regions');
        Schema::dropIfExists('shipping_zones');
    }

    public function down(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->default(1);
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('shipping_zone_regions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_zone_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('ph_region_id')->nullable();
            $table->unsignedBigInteger('ph_province_id')->nullable();
            $table->unsignedBigInteger('ph_city_id')->nullable();
            $table->timestamps();

            $table->index(['country_id', 'ph_region_id', 'ph_province_id', 'ph_city_id'], 'szr_location_index');
        });

        if (!Schema::hasColumn('shipping_zone_rates', 'shipping_zone_id')) {
            Schema::table('shipping_zone_rates', function (Blueprint $table) {
                $table->foreignId('shipping_zone_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
                $table->index(['shipping_zone_id', 'customer_group_id'], 'szr_zone_group_index');
            });
        }
    }
};
