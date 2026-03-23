<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->text('description')->nullable()->after('image');
            $table->string('meta_title', 255)->nullable()->after('is_active');
            $table->text('meta_description')->nullable()->after('meta_title');
            $table->string('meta_keywords', 500)->nullable()->after('meta_description');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn(['description', 'meta_title', 'meta_description', 'meta_keywords']);
        });
    }
};
