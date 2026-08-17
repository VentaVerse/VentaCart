<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Adopt-on-install; see the shipping extensions' migration note. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('seo_suggestions')) {
            Schema::create('seo_suggestions', function (Blueprint $table) {
                $table->id();
                $table->string('entity_type', 50)->default('product');
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('current_meta_title')->nullable();
                $table->string('suggested_meta_title')->nullable();
                $table->text('current_meta_description')->nullable();
                $table->text('suggested_meta_description')->nullable();
                $table->string('current_meta_keywords')->nullable();
                $table->string('suggested_meta_keywords')->nullable();
                $table->string('suggested_og_title')->nullable();
                $table->string('suggested_og_description', 500)->nullable();
                $table->string('current_og_title')->nullable();
                $table->string('current_og_description', 500)->nullable();
                $table->string('current_slug')->nullable();
                $table->string('suggested_slug')->nullable();
                $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
                $table->timestamp('generated_at');
                $table->timestamps();
                $table->index(['entity_type', 'entity_id']);
            });
        }

        if (! Schema::hasTable('seo_reports')) {
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
                $table->timestamp('generated_at');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('redirects')) {
            Schema::create('redirects', function (Blueprint $table) {
                $table->id();
                $table->string('source_path')->unique();
                $table->string('destination_path');
                $table->unsignedSmallInteger('status_code')->default(301);
                $table->boolean('preserve_query')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('hits')->default(0);
                $table->timestamp('last_hit_at')->nullable();
                $table->string('note')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('not_found_logs')) {
            Schema::create('not_found_logs', function (Blueprint $table) {
                $table->id();
                $table->string('path')->unique();
                $table->unsignedBigInteger('hits')->default(1);
                $table->timestamp('first_hit_at');
                $table->timestamp('last_hit_at');
                $table->string('last_query', 500)->nullable();
                $table->string('last_referer', 500)->nullable();
                $table->string('last_user_agent')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('not_found_logs');
        Schema::dropIfExists('redirects');
        Schema::dropIfExists('seo_reports');
        Schema::dropIfExists('seo_suggestions');
    }
};
