<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();

            // Snapshot (persists even if product is deleted/changed)
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('model')->nullable();
            $table->string('variant_label')->nullable();

            $table->decimal('price', 12, 2);
            $table->integer('quantity')->default(1);
            $table->decimal('total', 12, 2);

            $table->boolean('is_custom')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_products');
    }
};
