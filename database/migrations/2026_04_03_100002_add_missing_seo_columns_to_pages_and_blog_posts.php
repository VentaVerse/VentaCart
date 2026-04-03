<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            if (!Schema::hasColumn('pages', 'og_title')) {
                $table->string('og_title', 255)->nullable()->after('meta_description');
            }
            if (!Schema::hasColumn('pages', 'og_description')) {
                $table->string('og_description', 500)->nullable()->after(
                    Schema::hasColumn('pages', 'og_title') ? 'og_title' : 'meta_description'
                );
            }
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            if (!Schema::hasColumn('blog_posts', 'meta_keywords')) {
                $table->string('meta_keywords')->nullable()->after('meta_description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['og_title', 'og_description']);
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn('meta_keywords');
        });
    }
};
