<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Adopt-on-install; see shipping-free's migration note. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('flat_shipping_rates')) {
            return;
        }

        Schema::create('flat_shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->foreignId('geo_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_group_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('cost', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flat_shipping_rates');
    }
};
