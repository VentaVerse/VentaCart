<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A placed block can be styled on its own (box padding, background, border,
 * corners, shadow, text tone, title alignment), the way a row can. Null is
 * "as the theme draws it".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('layout_placements', function (Blueprint $table) {
            $table->json('style')->nullable()->after('config');
        });
    }

    public function down(): void
    {
        Schema::table('layout_placements', function (Blueprint $table) {
            $table->dropColumn('style');
        });
    }
};
