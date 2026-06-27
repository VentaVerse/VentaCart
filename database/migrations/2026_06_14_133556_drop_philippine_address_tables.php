<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the standalone philippine-address extension's tables.
 *
 * The PH deep-address levels (city, barangay) are no longer stored in the DB;
 * they are read on-demand from bundled files in database/data/ph/. The single
 * sub-national level (province) lives in the generic `zones` table like every
 * other country. See docs/superpowers/specs/2026-06-14-global-localization-design.md
 */
return new class extends Migration
{
    public function down(): void
    {
        // Recreate the original structure (schema only — data is not restorable).
        if (Schema::hasTable('ph_regions')) {
            return;
        }

        Schema::create('ph_regions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 15)->unique();
            $table->string('name');
        });

        Schema::create('ph_provinces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ph_region_id')->constrained()->cascadeOnDelete();
            $table->string('code', 15)->unique();
            $table->string('name');
            $table->index('ph_region_id');
        });

        Schema::create('ph_cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ph_province_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('ph_cities')->cascadeOnDelete();
            $table->string('code', 15)->unique();
            $table->string('name');
            $table->string('zip_code', 10)->nullable();
            $table->index('ph_province_id');
        });

        Schema::create('ph_barangays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ph_city_id')->constrained()->cascadeOnDelete();
            $table->string('code', 15)->unique();
            $table->string('name');
            $table->index('ph_city_id');
        });
    }

    public function up(): void
    {
        Schema::dropIfExists('ph_barangays');
        Schema::dropIfExists('ph_cities');
        Schema::dropIfExists('ph_provinces');
        Schema::dropIfExists('ph_regions');
    }
};
