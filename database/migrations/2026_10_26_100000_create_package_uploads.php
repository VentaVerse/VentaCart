<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every theme or extension archive uploaded in the admin: who sent it, what
 * it held, and what became of it. A package folder that an upload put on
 * disk is the only kind a later upload may replace.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20)->nullable();
            $table->string('slug', 64)->nullable();
            $table->string('package', 80)->nullable()->index();
            $table->string('version', 40)->nullable();
            $table->string('previous_version', 40)->nullable();
            $table->string('result', 20);
            $table->text('message')->nullable();
            $table->string('filename');
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_uploads');
    }
};
