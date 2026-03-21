<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('dph_post_id')->nullable()->index();
            $table->string('dph_partner_id');
            $table->string('dph_partner_name');
            $table->string('dph_service_type')->default('scheduled');
            $table->string('dph_status')->nullable();
            $table->string('dph_sub_status')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('tracking_url')->nullable();
            $table->string('shareable_link')->nullable();
            $table->decimal('approximate_fee', 10, 2)->default(0);
            $table->decimal('actual_fee', 10, 2)->default(0);
            $table->decimal('final_fee', 10, 2)->default(0);
            $table->timestamp('booked_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
