<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What UniSMS was asked and what it answered, one row per attempt, so
 * "did the customer get the text" has an answer on this extension's own
 * page. And a switch per template, so a message can be paused without
 * being deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_templates', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('message');
        });

        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 16);                 // status_change | test
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->unsignedBigInteger('order_status_id')->nullable();
            $table->unsignedBigInteger('sms_template_id')->nullable();
            $table->string('recipient', 32);
            $table->string('sender_id', 32)->nullable();
            $table->text('message');
            $table->string('status', 16)->index();        // sent | failed | throttled
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('gateway_reference', 100)->nullable();
            $table->text('response')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');

        Schema::table('sms_templates', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
