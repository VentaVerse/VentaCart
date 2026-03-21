<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('footer_columns', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('type', 30);
            $table->json('config')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('footer_columns');
    }
};
