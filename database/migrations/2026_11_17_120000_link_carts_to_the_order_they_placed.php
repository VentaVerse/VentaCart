<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One cart becomes one order, even when the Place order request arrives
 * twice (a double click, two tabs, a browser retrying on a slow network).
 *
 * carts.placed_order_id is the order the cart's lines last became, written
 * in the same locked transaction as the order. placed_lines_key and
 * placed_request_key are fingerprints of what that order was placed from
 * (the lines alone, and the lines with the shopper's checkout choices), so
 * a second request can tell a repeat of the same submit from a new one.
 *
 * orders.payment_url is the payment page an online method sent the shopper
 * to, so a repeated submit for an unpaid order goes back to the same page
 * instead of opening a second payment.
 *
 * No foreign key on placed_order_id: an order that is deleted simply stops
 * being found, and the cart places a new one.
 *
 * Safe on a fresh install and on a store that already has carts and orders:
 * the columns start empty, which reads as "this cart has not placed an
 * order yet", exactly how every cart behaved before.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('carts', 'placed_order_id')) {
            Schema::table('carts', function (Blueprint $table) {
                $table->unsignedBigInteger('placed_order_id')->nullable()->index('carts_placed_order_id_index');
                $table->string('placed_lines_key', 64)->nullable();
                $table->string('placed_request_key', 64)->nullable();
            });
        }

        if (! Schema::hasColumn('orders', 'payment_url')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->text('payment_url')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'payment_url')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('payment_url');
            });
        }

        if (Schema::hasColumn('carts', 'placed_order_id')) {
            Schema::table('carts', function (Blueprint $table) {
                $table->dropIndex('carts_placed_order_id_index');
                $table->dropColumn(['placed_order_id', 'placed_lines_key', 'placed_request_key']);
            });
        }
    }
};
