<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Variant types per product (e.g., "Color", "Size", "Length")
        Schema::create('variant_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g., "Color", "Size"
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Variant type values (e.g., Color -> "Red", "Blue"; Size -> "S", "M", "L")
        Schema::create('variant_type_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_type_id')->constrained()->cascadeOnDelete();
            $table->string('value'); // e.g., "Red", "Large"
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Actual variant combinations with their own SKU, price, cost, stock
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku');
            $table->decimal('price', 12, 2);
            $table->decimal('cost', 12, 2)->default(0);
            $table->integer('quantity')->default(0);
            $table->decimal('weight', 8, 3)->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('legacy_oc_id')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'sku']);
        });

        // Links variant to its type values (e.g., variant #1 = Red + Large)
        Schema::create('product_variant_values', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_type_value_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_variant_id', 'variant_type_value_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variant_values');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('variant_type_values');
        Schema::dropIfExists('variant_types');
    }
};
