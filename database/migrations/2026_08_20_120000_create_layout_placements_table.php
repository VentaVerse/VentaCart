<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a module renders, on which pages, in what order.
 *
 * Venta had exactly one of these and it only worked on the home page:
 * homepage_sections, read by HomeController, rendered by an if/elseif chain in
 * home.blade.php. That is why banners can only exist on the home page. Not a
 * business rule, just the only view that ever rendered sections.
 *
 * Regions are NOT stored here. A region is declared by the active theme in its
 * theme.json, so a theme that invents "faceplate" gets it as a placeable region
 * without core knowing the word. What is stored is the merchant's decision:
 * this widget, on these pages, in this region, in this order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('layout_placements', function (Blueprint $table) {
            $table->id();

            // A key from App\Support\StorefrontPageType, or '*' for every page.
            $table->string('page_type', 32)->index();

            // Must be declared by the active theme. A placement whose region the
            // theme does not declare is kept and reported, never deleted: losing
            // a merchant's layout because they tried a theme is how you teach
            // them never to try another one.
            $table->string('region', 64);

            // A key in the widget registry, e.g. 'catalog.products'.
            $table->string('widget', 64);

            $table->string('title')->nullable();
            $table->json('config')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);

            $table->timestamps();

            $table->index(['page_type', 'region', 'sort_order'], 'layout_placements_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('layout_placements');
    }
};
