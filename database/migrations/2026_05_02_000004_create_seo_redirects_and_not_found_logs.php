<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generic SEO infrastructure:
 *   - `redirects`        — admin-managed source→destination 301/302/410 rules
 *   - `not_found_logs`   — deduped log of recent 404s, surfaced in admin so
 *                          the operator can decide what to redirect or 410
 *
 * Both are platform-agnostic (no OpenCart-specific assumptions). Stores that
 * migrated from any prior platform can use these to recover lost link equity
 * without us shipping migration-specific code.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('redirects')) {
            Schema::create('redirects', function (Blueprint $table) {
                $table->id();
                $table->string('source_path');                // e.g. "/old-product-slug"
                $table->string('destination_path');           // e.g. "/product/new-slug" — ignored when status_code=410
                $table->unsignedSmallInteger('status_code')->default(301);  // 301, 302, or 410
                $table->boolean('preserve_query')->default(false);  // append the request's query string to the destination
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('hits')->default(0);
                $table->timestamp('last_hit_at')->nullable();
                $table->string('note')->nullable();           // free-form admin note (why this exists)
                $table->timestamps();

                $table->unique('source_path');
                $table->index(['is_active', 'source_path']);
            });
        }

        if (! Schema::hasTable('not_found_logs')) {
            Schema::create('not_found_logs', function (Blueprint $table) {
                $table->id();
                $table->string('path');                       // request path, no query string
                $table->unsignedBigInteger('hits')->default(1);
                $table->timestamp('first_hit_at');
                $table->timestamp('last_hit_at');
                $table->string('last_query', 500)->nullable();
                $table->string('last_referer', 500)->nullable();
                $table->string('last_user_agent', 255)->nullable();
                $table->timestamps();

                $table->unique('path');
                $table->index('last_hit_at');
                $table->index('hits');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('not_found_logs');
        Schema::dropIfExists('redirects');
    }
};
