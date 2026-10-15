<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the order cost the store, beside what it earned.
 *
 * These are commerce facts about an order, not facts about HitPay or QuadX:
 * any payment provider charges a fee, any courier charges for carriage. Core
 * is entitled to the fields; what it must never learn is who charged them or
 * how they were fetched. Extensions write them, the same way the fulfillment
 * extension already writes orders.courier_* and orders.tracking_number.
 *
 * NULLABLE, deliberately, and it carries meaning. Neither figure exists when
 * an order is placed: a payment fee is known once the payment settles, and a
 * courier's charge is known at booking. So null means "not known yet" and
 * 0.00 means "genuinely nothing to pay", and the order page says different
 * things for the two. A default of 0 would have erased that difference and
 * quietly reported unbooked parcels as free.
 *
 * The total is untouched and stays untouched. These never reduce it: the ERP
 * reads the total to know what the customer owed, and computes margin from
 * these separately.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('payment_fee', 10, 2)->nullable()->after('total');
            $table->decimal('shipping_cost_actual', 10, 2)->nullable()->after('payment_fee');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_fee', 'shipping_cost_actual']);
        });
    }
};
