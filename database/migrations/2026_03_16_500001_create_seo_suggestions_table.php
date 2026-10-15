<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('current_meta_title')->nullable();
            $table->string('suggested_meta_title')->nullable();
            $table->text('current_meta_description')->nullable();
            $table->text('suggested_meta_description')->nullable();
            $table->string('current_meta_keywords')->nullable();
            $table->string('suggested_meta_keywords')->nullable();
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamps();
            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_suggestions');
    }
};
