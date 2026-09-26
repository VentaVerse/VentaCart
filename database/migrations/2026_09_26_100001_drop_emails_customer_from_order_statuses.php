<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The automatic status email is no longer a fact about the status.
 *
 * Owner ruling 2026-09-26: core emails a customer about a status change only
 * when someone asks (Notify on the order page, notify on the API). Which
 * statuses email automatically belongs to the extension that automates order
 * handling, as it did in OpenCart, so the column added the day before goes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('order_statuses', 'emails_customer')) {
            Schema::table('order_statuses', function (Blueprint $table) {
                $table->dropColumn('emails_customer');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('order_statuses', 'emails_customer')) {
            Schema::table('order_statuses', function (Blueprint $table) {
                $table->boolean('emails_customer')->default(false)->after('customer_can_cancel');
            });
        }
    }
};
