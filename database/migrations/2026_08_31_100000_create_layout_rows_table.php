<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rows and columns inside a region (Plaza plan, phase 1).
 *
 * A region used to be a flat list of placements, one under the other. A row
 * groups placements into columns on the storefront's eight-column grid, so a
 * merchant can put three widgets side by side or a wide one beside a narrow
 * one. A placement without a row is still valid and still renders: it is an
 * implicit one-column row, which is exactly what every existing placement
 * was, so nothing changes on deploy and no data is rewritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('layout_rows', function (Blueprint $table) {
            $table->id();
            $table->string('theme', 64)->index();
            $table->string('page_type', 32);
            $table->string('region', 64);
            $table->unsignedInteger('sort_order')->default(0);
            // Column widths in eighths, e.g. [8], [4,4], [2,4,2]. Always sums to 8.
            $table->json('columns');
            // Per-row style arrives with phase 2; the column is here so that
            // phase adds no schema change.
            $table->json('style')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->index(['theme', 'page_type', 'region', 'sort_order'], 'layout_rows_lookup');
        });

        Schema::table('layout_placements', function (Blueprint $table) {
            $table->foreignId('row_id')->nullable()->after('region')->constrained('layout_rows')->nullOnDelete();
            $table->unsignedTinyInteger('column_index')->default(0)->after('row_id');
        });
    }

    public function down(): void
    {
        Schema::table('layout_placements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('row_id');
            $table->dropColumn('column_index');
        });

        Schema::dropIfExists('layout_rows');
    }
};
