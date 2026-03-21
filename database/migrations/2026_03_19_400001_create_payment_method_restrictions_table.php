<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_method_restrictions', function (Blueprint $table) {
            $table->id();
            $table->string('payment_method')->unique(); // bank_transfer, hitpay, cod
            $table->json('geo_zone_ids')->nullable();
            $table->json('customer_group_ids')->nullable();
            $table->json('category_ids')->nullable();
            $table->json('brand_ids')->nullable();
            $table->json('shipping_method_keys')->nullable(); // e.g. ['weight_based','flat_rate']
            $table->decimal('min_total', 10, 2)->nullable();
            $table->decimal('max_total', 10, 2)->nullable();
            $table->decimal('min_weight', 8, 2)->nullable();
            $table->decimal('max_weight', 8, 2)->nullable();
            $table->integer('min_quantity')->nullable();
            $table->integer('max_quantity')->nullable();
            $table->json('exclude_geo_zone_ids')->nullable();
            $table->json('exclude_customer_group_ids')->nullable();
            $table->json('exclude_category_ids')->nullable();
            $table->json('exclude_brand_ids')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_method_restrictions');
    }
};
