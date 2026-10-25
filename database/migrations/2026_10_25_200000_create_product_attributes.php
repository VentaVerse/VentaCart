<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product attributes: named facts grouped for display (Farm: origin,
 * altitude; Pack: weight), with one value per product. An attribute says
 * whether shoppers can filter by it and whether cards show it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_group_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_filterable')->default(false);
            $table->boolean('show_on_card')->default(false);
            $table->timestamps();
        });

        Schema::create('product_attributes', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('value', 255);
            $table->primary(['product_id', 'attribute_id']);
            $table->index(['attribute_id', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attributes');
        Schema::dropIfExists('attributes');
        Schema::dropIfExists('attribute_groups');
    }
};
