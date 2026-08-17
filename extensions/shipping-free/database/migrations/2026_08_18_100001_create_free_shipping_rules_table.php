<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adopt-on-install: an existing deploy already has this table (core
 * migration history created it), so install adopts it with data intact;
 * a fresh install creates it in its final shape. Uninstall drops it —
 * the data goes with the extension, as the confirm dialog says.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('free_shipping_rules')) {
            return;
        }

        Schema::create('free_shipping_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('checkout_name')->nullable();
            $table->decimal('min_order_total', 10, 2)->nullable();
            $table->integer('min_quantity')->nullable();
            $table->json('brand_ids')->nullable();
            $table->json('category_ids')->nullable();
            $table->json('customer_group_ids')->nullable();
            $table->json('shipping_zone_ids')->nullable();
            $table->json('exclude_brand_ids')->nullable();
            $table->json('exclude_category_ids')->nullable();
            $table->json('exclude_customer_group_ids')->nullable();
            $table->boolean('apply_whole_cart')->default(false);
            $table->boolean('show_badge')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('free_shipping_rules');
    }
};
