<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Adopt-on-install; see the shipping extensions' migration note. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_campaigns')) {
            Schema::create('email_campaigns', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('store_id')->default(1);
                $table->string('subject', 255);
                $table->longText('body')->nullable();
                $table->enum('audience_type', ['group', 'subscribers', 'all', 'selected']);
                $table->json('audience_params')->nullable();
                $table->enum('status', ['draft', 'queued', 'sent', 'failed'])->default('draft');
                $table->unsignedInteger('recipients_total')->default(0);
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->index(['store_id', 'status']);
            });
        }

        if (! Schema::hasTable('email_campaign_templates')) {
            Schema::create('email_campaign_templates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('store_id')->default(1);
                $table->string('name', 128);
                $table->string('subject', 255)->nullable();
                $table->longText('body')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_campaign_templates');
        Schema::dropIfExists('email_campaigns');
    }
};
