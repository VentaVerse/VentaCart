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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 128);
            $table->enum('type', ['percent', 'fixed', 'free_shipping']);
            $table->decimal('discount_value', 10, 2)->default(0);
            $table->decimal('min_order_total', 10, 2)->nullable();
            $table->decimal('max_discount', 10, 2)->nullable();
            $table->boolean('free_shipping')->default(false);
            $table->enum('customer_eligibility', ['all', 'logged_in', 'guest'])->default('all');
            $table->unsignedBigInteger('customer_group_id')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_per_customer')->default(1);
            $table->unsignedInteger('times_used')->default(0);
            $table->date('date_start')->nullable();
            $table->date('date_end')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('source', 32)->nullable();
            $table->timestamps();

            $table->foreign('customer_group_id')
                ->references('id')
                ->on('customer_groups')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
