<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_statuses', function (Blueprint $table) {
            // Named for the channel it governs. SMS keeps its own list in the
            // UniSMS extension, because SMS costs money per message.
            $table->boolean('emails_customer')->default(false)->after('customer_can_cancel');
        });
    }

    public function down(): void
    {
        Schema::table('order_statuses', function (Blueprint $table) {
            $table->dropColumn('emails_customer');
        });
    }
};
