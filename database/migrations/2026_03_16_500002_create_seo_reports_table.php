<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->default(1);
            $table->integer('total_products_analyzed');
            $table->integer('suggestions_generated');
            $table->integer('auto_accepted')->default(0);
            $table->integer('pending_review')->default(0);
            $table->decimal('health_score', 5, 2)->default(0);
            $table->json('top_opportunities')->nullable();
            $table->json('search_insights')->nullable();
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_reports');
    }
};
