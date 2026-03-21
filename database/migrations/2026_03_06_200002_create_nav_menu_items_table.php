<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nav_menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('type', 20);
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('url')->nullable();
            $table->boolean('new_tab')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nav_menu_items');
    }
};
