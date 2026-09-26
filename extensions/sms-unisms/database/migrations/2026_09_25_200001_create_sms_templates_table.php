<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a text says, per order status.
 *
 * One sentence for every status could not work: "Tracking no.
 * {tracking_number}" is right on Shipped and nonsense on Pending. A template
 * is a merchant's own named message, and each order status belongs to at
 * most one of them, which the unique key on the pivot enforces. A status
 * with no template never texts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);
            $table->text('message');
            $table->timestamps();
        });

        Schema::create('sms_template_statuses', function (Blueprint $table) {
            $table->foreignId('sms_template_id')->constrained('sms_templates')->cascadeOnDelete();
            $table->foreignId('order_status_id')->unique()->constrained('order_statuses')->cascadeOnDelete();
            $table->primary(['sms_template_id', 'order_status_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_template_statuses');
        Schema::dropIfExists('sms_templates');
    }
};
