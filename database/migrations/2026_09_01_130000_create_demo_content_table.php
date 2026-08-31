<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A register of the records a template installed as its demo content, so a
 * later template can take its own away again without ever guessing which
 * products the merchant made themselves.
 *
 * Ownership is kept here rather than as a column on products and posts
 * because the tables it has to cover belong to different owners: products are
 * core, blog posts are an extension. One register keeps that seam out of the
 * schema of either.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_content', function (Blueprint $table) {
            $table->id();
            $table->string('preset', 64);
            $table->string('record_type', 64);
            $table->unsignedBigInteger('record_id');
            $table->timestamps();

            $table->unique(['record_type', 'record_id']);
            $table->index('preset');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_content');
    }
};
