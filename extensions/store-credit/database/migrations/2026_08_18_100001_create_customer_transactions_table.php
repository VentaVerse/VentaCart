<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Adopt-on-install; see the shipping extensions' migration note. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_transactions')) {
            Schema::create('customer_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('description');
                $table->decimal('amount', 15, 2);
                $table->timestamp('created_at')->useCurrent();

                $table->index('customer_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_transactions');
    }
};
