<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Adopt-on-install; see the shipping extensions' migration note. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reviews')) {
            Schema::create('reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
                $table->string('token', 64)->unique();
                $table->string('name');
                $table->string('email')->nullable();
                $table->tinyInteger('rating')->nullable();
                $table->string('title')->nullable();
                $table->text('body')->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->boolean('is_verified_purchase')->default(false);
                $table->string('platform', 32)->nullable();
                $table->string('platform_review_id', 128)->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->index(['product_id', 'status']);
            });
        }

        if (! Schema::hasTable('review_images')) {
            Schema::create('review_images', function (Blueprint $table) {
                $table->id();
                $table->foreignId('review_id')->constrained()->cascadeOnDelete();
                $table->string('path');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('review_coupons')) {
            Schema::create('review_coupons', function (Blueprint $table) {
                $table->id();
                $table->foreignId('review_id')->constrained()->cascadeOnDelete();
                $table->string('coupon_code');
                $table->enum('discount_type', ['percent', 'fixed'])->default('percent');
                $table->decimal('discount_value', 10, 2);
                $table->date('expires_at')->nullable();
                $table->boolean('used')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('review_coupons');
        Schema::dropIfExists('review_images');
        Schema::dropIfExists('reviews');
    }
};
