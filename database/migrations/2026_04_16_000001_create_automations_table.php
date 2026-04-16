<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('automations')) {
            return;
        }

        Schema::create('automations', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name', 128);
            $table->string('command', 128);
            $table->text('description')->nullable();
            $table->string('schedule_preset', 32);
            $table->json('schedule_params');
            $table->string('cron_expression', 64);
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_run_status', 16)->nullable();
            $table->text('last_run_output')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automations');
    }
};
