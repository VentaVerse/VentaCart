<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index(['is_active', 'date_available'], 'products_active_date_index');
            $table->index(['brand_id', 'is_active'], 'products_brand_active_index');
        });

        Schema::table('product_images', function (Blueprint $table) {
            $table->index(['product_id', 'sort_order'], 'pi_product_sort_index');
        });

        Schema::table('product_specials', function (Blueprint $table) {
            $table->index(['product_id', 'display_mode', 'date_start', 'date_end'], 'ps_product_display_dates_index');
        });

        if (Schema::hasTable('reviews')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->index(['product_id', 'status', 'published_at'], 'reviews_product_status_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_active_date_index');
            $table->dropIndex('products_brand_active_index');
        });

        Schema::table('product_images', function (Blueprint $table) {
            $table->dropIndex('pi_product_sort_index');
        });

        Schema::table('product_specials', function (Blueprint $table) {
            $table->dropIndex('ps_product_display_dates_index');
        });

        if (Schema::hasTable('reviews')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->dropIndex('reviews_product_status_index');
            });
        }
    }
};
