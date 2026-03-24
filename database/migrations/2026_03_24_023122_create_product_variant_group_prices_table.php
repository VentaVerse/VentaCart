<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variant_group_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_variant_id');
            $table->unsignedBigInteger('customer_group_id');
            $table->decimal('price', 10, 2);
            $table->timestamps();

            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->foreign('customer_group_id')->references('id')->on('customer_groups')->cascadeOnDelete();
            $table->unique(['product_variant_id', 'customer_group_id'], 'variant_group_price_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_group_prices');
    }
};
