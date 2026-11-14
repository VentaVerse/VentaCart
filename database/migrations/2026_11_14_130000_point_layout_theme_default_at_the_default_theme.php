<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The theme column of layout_placements and layout_columns took the core
 * theme's old slug as its column default. A row written without a theme
 * landed under a slug no theme answers to, so it showed in neither the
 * editor nor the storefront. The default follows the core theme's new slug.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->defaultTo('default');
    }

    public function down(): void
    {
        $this->defaultTo('venta');
    }

    private function defaultTo(string $slug): void
    {
        foreach (['layout_placements', 'layout_columns'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'theme')) {
                Schema::table($table, function (Blueprint $t) use ($slug) {
                    $t->string('theme', 64)->default($slug)->change();
                });
            }
        }
    }
};
