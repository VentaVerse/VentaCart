<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop foreign key on products table first
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['manufacturer_id']);
        });

        // Rename the table
        Schema::rename('manufacturers', 'brands');

        // Rename the column on products
        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('manufacturer_id', 'brand_id');
        });

        // Re-add foreign key with new names
        Schema::table('products', function (Blueprint $table) {
            $table->foreign('brand_id')->references('id')->on('brands')->nullOnDelete();
        });

        // Update theme setting key
        \App\Models\Setting::where('group', 'product_page')
            ->where('key', 'show_manufacturer')
            ->update(['key' => 'show_brand']);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
        });

        Schema::rename('brands', 'manufacturers');

        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('brand_id', 'manufacturer_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreign('manufacturer_id')->references('id')->on('manufacturers')->nullOnDelete();
        });

        \App\Models\Setting::where('group', 'product_page')
            ->where('key', 'show_brand')
            ->update(['key' => 'show_manufacturer']);
    }
};
