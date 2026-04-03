<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('seo_suggestions', function (Blueprint $table) {
            if (!Schema::hasColumn('seo_suggestions', 'current_slug')) {
                $table->string('current_slug', 255)->nullable()->after('current_og_description');
            }
            if (!Schema::hasColumn('seo_suggestions', 'suggested_slug')) {
                $table->string('suggested_slug', 255)->nullable()->after('current_slug');
            }
        });
    }

    public function down(): void
    {
        Schema::table('seo_suggestions', function (Blueprint $table) {
            $table->dropColumn(['current_slug', 'suggested_slug']);
        });
    }
};
