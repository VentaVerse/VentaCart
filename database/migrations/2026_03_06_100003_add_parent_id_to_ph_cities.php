<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ph_cities') || Schema::hasColumn('ph_cities', 'parent_id')) {
            return;
        }

        Schema::table('ph_cities', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('ph_province_id')
                ->constrained('ph_cities')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ph_cities', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};
