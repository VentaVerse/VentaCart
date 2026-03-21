<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_zone_regions');
    }
};
