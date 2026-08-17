<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->default(1);
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->string('name');
            $table->string('email');
            $table->enum('status', ['waiting', 'notified', 'failed'])->default('waiting');
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'product_variant_id', 'status'], 'sn_product_status_index');
            $table->unique(['email', 'product_id', 'product_variant_id'], 'sn_unique_subscription');
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_notifications');
    }
};
