<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('geo_zones', function (Blueprint $table) {
            $table->string('source', 50)->nullable()->after('sort_order');
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::table('geo_zones', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn('source');
        });
    }
};
