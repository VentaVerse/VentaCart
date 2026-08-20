<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How wide the side columns are, per page type.
 *
 * OpenCart and Journal compute the content width from which sidebars happen to
 * be present: both columns gives col-sm-6, one gives col-sm-9, none gives
 * col-sm-12. The ratio is a consequence, never a decision, so a merchant who
 * wants a narrow filter rail and a wide catalogue cannot have one.
 *
 * Here the ratio IS the decision. Widths are in eighths of the row, the left
 * and right are set independently, and the content takes whatever is left. A
 * width of 0 means no column at all, and a page with neither is a plain
 * full-width page with no grid wrapped around it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('layout_columns', function (Blueprint $table) {
            $table->id();
            $table->string('page_type', 32)->unique();
            $table->unsignedTinyInteger('left_width')->default(0);
            $table->unsignedTinyInteger('right_width')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('layout_columns');
    }
};
