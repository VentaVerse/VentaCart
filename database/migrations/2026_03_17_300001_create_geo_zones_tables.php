<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create geo_zones table
        Schema::create('geo_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Create geo_zone_entries table
        Schema::create('geo_zone_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('geo_zone_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('zone_id')->nullable();
            $table->unsignedBigInteger('ph_region_id')->nullable();
            $table->unsignedBigInteger('ph_province_id')->nullable();
            $table->unsignedBigInteger('ph_city_id')->nullable();
            $table->timestamps();

            $table->index(['geo_zone_id', 'ph_region_id', 'ph_province_id', 'ph_city_id'], 'gze_location_index');
        });

        // Migrate data from shipping_zones → geo_zones
        $shippingZones = DB::table('shipping_zones')->get();
        foreach ($shippingZones as $sz) {
            $geoZoneId = DB::table('geo_zones')->insertGetId([
                'name' => $sz->name,
                'description' => 'Migrated from shipping zone',
                'is_active' => $sz->status,
                'sort_order' => $sz->sort_order,
                'created_at' => $sz->created_at,
                'updated_at' => $sz->updated_at,
            ]);

            // Migrate region entries
            $regions = DB::table('shipping_zone_regions')->where('shipping_zone_id', $sz->id)->get();
            foreach ($regions as $r) {
                DB::table('geo_zone_entries')->insert([
                    'geo_zone_id' => $geoZoneId,
                    'country_id' => $r->country_id,
                    'ph_region_id' => $r->ph_region_id,
                    'ph_province_id' => $r->ph_province_id,
                    'ph_city_id' => $r->ph_city_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Update shipping_zone_rates to reference geo_zone_id
            // We'll add the column in the next step
        }

        // Add geo_zone_id to shipping_zone_rates and migrate references
        Schema::table('shipping_zone_rates', function (Blueprint $table) {
            $table->unsignedBigInteger('geo_zone_id')->nullable()->after('id');
        });

        // Map old shipping_zone_id → new geo_zone_id
        $mapping = [];
        $oldZones = DB::table('shipping_zones')->get();
        $newZones = DB::table('geo_zones')->where('description', 'Migrated from shipping zone')->get();
        foreach ($oldZones as $i => $old) {
            $new = $newZones->firstWhere('name', $old->name);
            if ($new) {
                $mapping[$old->id] = $new->id;
            }
        }

        foreach ($mapping as $oldId => $newId) {
            DB::table('shipping_zone_rates')
                ->where('shipping_zone_id', $oldId)
                ->update(['geo_zone_id' => $newId]);
        }

        // Update free_shipping_rules shipping_zone_ids to use geo_zone IDs
        $rules = DB::table('free_shipping_rules')->whereNotNull('shipping_zone_ids')->get();
        foreach ($rules as $rule) {
            $oldIds = json_decode($rule->shipping_zone_ids, true) ?: [];
            $newIds = array_map(fn($id) => $mapping[$id] ?? $id, $oldIds);
            DB::table('free_shipping_rules')
                ->where('id', $rule->id)
                ->update(['shipping_zone_ids' => json_encode(array_values(array_filter($newIds)))]);
        }
    }

    public function down(): void
    {
        Schema::table('shipping_zone_rates', function (Blueprint $table) {
            $table->dropColumn('geo_zone_id');
        });
        Schema::dropIfExists('geo_zone_entries');
        Schema::dropIfExists('geo_zones');
    }
};
