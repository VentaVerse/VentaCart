<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Adopt-on-install; see shipping-free's migration note. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('weight_based_methods')) {
            Schema::create('weight_based_methods', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('admin_name');
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('weight_based_rates')) {
            Schema::create('weight_based_rates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('weight_based_method_id')->constrained()->cascadeOnDelete();
                $table->decimal('weight_from', 8, 3)->default(0);
                $table->decimal('weight_to', 8, 3);
                $table->decimal('cost', 10, 2);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('weight_based_method_geo_zone')) {
            Schema::create('weight_based_method_geo_zone', function (Blueprint $table) {
                $table->foreignId('weight_based_method_id')->constrained()->cascadeOnDelete();
                $table->foreignId('geo_zone_id')->constrained()->cascadeOnDelete();
                $table->primary(['weight_based_method_id', 'geo_zone_id'], 'wbm_geo_zone_primary');
            });
        }

        if (! Schema::hasTable('weight_based_method_customer_group')) {
            Schema::create('weight_based_method_customer_group', function (Blueprint $table) {
                $table->foreignId('weight_based_method_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_group_id')->constrained()->cascadeOnDelete();
                $table->primary(['weight_based_method_id', 'customer_group_id'], 'wbm_customer_group_primary');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('weight_based_method_customer_group');
        Schema::dropIfExists('weight_based_method_geo_zone');
        Schema::dropIfExists('weight_based_rates');
        Schema::dropIfExists('weight_based_methods');
    }
};
