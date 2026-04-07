<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dph_api_logs', function (Blueprint $table) {
            $table->id();
            $table->string('method', 10);
            $table->string('endpoint');
            $table->string('order_id')->nullable()->index();
            $table->integer('status_code')->nullable();
            $table->integer('response_time_ms')->default(0);
            $table->json('request_body')->nullable();
            $table->json('response_body')->nullable();
            $table->boolean('success')->default(false);
            $table->string('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dph_api_logs');
    }
};
