<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A theme editor's unsaved work, per admin, per theme, per page type. Kept
 * here rather than in the session so an expired sign-in or a closed tab
 * does not lose an afternoon's arranging.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('layout_drafts', function (Blueprint $table) {
            $table->id();
            $table->string('theme', 64);
            $table->string('page_type', 32);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('payload');
            $table->timestamps();
            $table->unique(['theme', 'page_type', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('layout_drafts');
    }
};
