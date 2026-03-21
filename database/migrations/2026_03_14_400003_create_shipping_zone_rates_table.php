<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zone_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_zone_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('customer_group_id')->nullable();
            $table->decimal('weight_from', 8, 3)->default(0);
            $table->decimal('weight_to', 8, 3);
            $table->decimal('cost', 10, 2);
            $table->decimal('free_shipping_threshold', 10, 2)->nullable();
            $table->timestamps();

            $table->index(['shipping_zone_id', 'customer_group_id'], 'szr_zone_group_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_zone_rates');
    }
};
