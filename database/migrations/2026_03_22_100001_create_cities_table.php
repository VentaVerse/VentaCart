<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('zone_id')->index();
            $table->string('name');
            $table->boolean('is_active')->default(true);

            $table->foreign('zone_id')->references('id')->on('zones')->cascadeOnDelete();
            $table->index(['zone_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
