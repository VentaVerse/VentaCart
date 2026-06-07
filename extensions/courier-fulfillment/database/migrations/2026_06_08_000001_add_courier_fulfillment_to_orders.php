<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Courier fulfillment columns on orders, added by the courier-fulfillment
 * extension. A Venta install WITHOUT this extension never gets these columns —
 * the feature is fully removable. Idempotent guards so it's safe regardless of
 * install order.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'courier_provider')) {
                $table->string('courier_provider')->nullable()->after('tracking_number');
            }
            if (!Schema::hasColumn('orders', 'courier_tracking_number')) {
                $table->string('courier_tracking_number')->nullable()->after('courier_provider');
            }
            if (!Schema::hasColumn('orders', 'courier_status')) {
                $table->string('courier_status')->nullable()->after('courier_tracking_number');
            }
            if (!Schema::hasColumn('orders', 'courier_meta')) {
                $table->json('courier_meta')->nullable()->after('courier_status');
            }
            if (!Schema::hasColumn('orders', 'courier_booked_at')) {
                $table->timestamp('courier_booked_at')->nullable()->after('courier_meta');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }
        Schema::table('orders', function (Blueprint $table) {
            foreach (['courier_provider', 'courier_tracking_number', 'courier_status', 'courier_meta', 'courier_booked_at'] as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
