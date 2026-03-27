<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abandoned_cart_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('abandoned_cart_rule_id')->constrained('abandoned_cart_rules')->cascadeOnDelete();
            $table->foreignId('email_template_id')->constrained('email_templates')->cascadeOnDelete();
            $table->unsignedInteger('send_count')->default(0);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['cart_id', 'abandoned_cart_rule_id'], 'cart_rule_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abandoned_cart_notifications');
    }
};
