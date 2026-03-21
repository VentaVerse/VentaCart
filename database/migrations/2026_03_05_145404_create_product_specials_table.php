<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_specials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_group_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('price', 12, 2);
            $table->enum('display_mode', ['override', 'sale'])->default('sale');
            $table->integer('priority')->default(0);
            $table->date('date_start')->nullable();
            $table->date('date_end')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_specials');
    }
};
