<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ph_regions')) {
            return; // Already exists — skip (migrated from core or previous install)
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

    public function down(): void
    {
        Schema::dropIfExists('ph_barangays');
        Schema::dropIfExists('ph_cities');
        Schema::dropIfExists('ph_provinces');
        Schema::dropIfExists('ph_regions');
    }
};
