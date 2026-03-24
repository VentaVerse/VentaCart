<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('platform', 32)->nullable()->after('is_verified_purchase');
            $table->string('platform_review_id', 128)->nullable()->after('platform');
            $table->string('email')->nullable()->change();

            $table->unique(['platform', 'platform_review_id'], 'reviews_platform_unique');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropUnique('reviews_platform_unique');
            $table->dropColumn(['platform', 'platform_review_id']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
