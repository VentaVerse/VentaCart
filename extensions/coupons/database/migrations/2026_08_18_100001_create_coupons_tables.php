<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Adopt-on-install; see the shipping extensions' migration note. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coupons')) {
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
                $table->unsignedInteger('usage_per_customer')->nullable();
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

        if (! Schema::hasTable('coupon_restrictions')) {
            Schema::create('coupon_restrictions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
                $table->enum('restriction_type', ['product', 'category', 'brand']);
                $table->unsignedBigInteger('restriction_id');
                $table->unique(['coupon_id', 'restriction_type', 'restriction_id'], 'coupon_restriction_unique');
            });
        }

        if (! Schema::hasTable('coupon_usages')) {
            Schema::create('coupon_usages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->decimal('discount_amount', 10, 2);
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
        Schema::dropIfExists('coupon_restrictions');
        Schema::dropIfExists('coupons');
    }
};
