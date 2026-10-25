<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Area templates: the rows of one region, with their
 * widgets and settings, kept under a name so a merchant can load the same
 * arrangement into another page or another region. Per theme, like layouts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('layout_templates', function (Blueprint $table) {
            $table->id();
            $table->string('theme', 64)->index();
            $table->string('name', 80);
            $table->string('region', 64)->nullable();
            $table->json('rows');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('layout_templates');
    }
};
