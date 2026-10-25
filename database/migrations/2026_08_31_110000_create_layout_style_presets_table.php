<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Named row styles: a merchant styles one row and keeps
 * the result under a name to apply to other rows. Per theme, like layouts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('layout_style_presets', function (Blueprint $table) {
            $table->id();
            $table->string('theme', 64)->index();
            $table->string('name', 80);
            $table->json('style');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('layout_style_presets');
    }
};
