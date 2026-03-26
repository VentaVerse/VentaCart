<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('seo_suggestions', function (Blueprint $table) {
            $table->string('entity_type', 50)->default('product')->after('id');
            $table->unsignedBigInteger('entity_id')->nullable()->after('entity_type');
            $table->string('suggested_og_title', 255)->nullable()->after('suggested_meta_keywords');
            $table->string('suggested_og_description', 500)->nullable()->after('suggested_og_title');
            $table->string('current_og_title', 255)->nullable()->after('suggested_og_description');
            $table->string('current_og_description', 500)->nullable()->after('current_og_title');
            $table->index(['entity_type', 'entity_id', 'status']);
        });

        DB::statement('UPDATE ' . DB::getTablePrefix() . 'seo_suggestions SET entity_id = product_id WHERE product_id IS NOT NULL');

        Schema::table('products', function (Blueprint $table) {
            $table->string('og_title', 255)->nullable()->after('meta_keywords');
            $table->string('og_description', 500)->nullable()->after('og_title');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('og_title', 255)->nullable()->after('meta_keywords');
            $table->string('og_description', 500)->nullable()->after('og_title');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->string('og_title', 255)->nullable()->after('meta_keywords');
            $table->string('og_description', 500)->nullable()->after('og_title');
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->string('og_title', 255)->nullable()->after('meta_description');
            $table->string('og_description', 500)->nullable()->after('og_title');
        });
    }

    public function down(): void
    {
        Schema::table('seo_suggestions', function (Blueprint $table) {
            $table->dropIndex(['entity_type', 'entity_id', 'status']);
            $table->dropColumn(['entity_type', 'entity_id', 'suggested_og_title', 'suggested_og_description', 'current_og_title', 'current_og_description']);
        });
        Schema::table('products', function (Blueprint $table) { $table->dropColumn(['og_title', 'og_description']); });
        Schema::table('categories', function (Blueprint $table) { $table->dropColumn(['og_title', 'og_description']); });
        Schema::table('brands', function (Blueprint $table) { $table->dropColumn(['og_title', 'og_description']); });
        Schema::table('blog_posts', function (Blueprint $table) { $table->dropColumn(['og_title', 'og_description']); });
    }
};
