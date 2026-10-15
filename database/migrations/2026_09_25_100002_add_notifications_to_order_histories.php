<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_histories', function (Blueprint $table) {
            // What each channel did with this change: sent or not, and why
            // not. We record that we sent and that the gateway accepted it.
            // We never claim the handset received it.
            $table->json('notifications')->nullable()->after('notify_customer');
        });
    }

    public function down(): void
    {
        Schema::table('order_histories', function (Blueprint $table) {
            $table->dropColumn('notifications');
        });
    }
};
