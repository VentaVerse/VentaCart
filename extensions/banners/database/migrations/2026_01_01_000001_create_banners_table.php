<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('banners')) {
            return;
        }

        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('bg_image')->nullable();
            $table->string('bg_image_mobile')->nullable();
            $table->string('bg_color', 30)->default('#000000');
            $table->string('bg_size', 20)->default('cover');
            $table->string('link')->nullable();
            $table->boolean('link_new_tab')->default(false);
            $table->json('layers')->nullable();
            $table->integer('height')->default(500);
            $table->integer('height_mobile')->default(400);
            $table->boolean('full_width')->default(true);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
