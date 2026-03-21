<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flat_shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('geo_zone_id')->nullable(); // null = all zones (global)
            $table->decimal('cost', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('geo_zone_id')->references('id')->on('geo_zones')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flat_shipping_rates');
    }
};
