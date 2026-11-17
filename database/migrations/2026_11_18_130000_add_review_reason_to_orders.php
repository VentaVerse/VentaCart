<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * orders.review_reason: why the merchant must look at an order, shown on
 * its admin page until they mark it reviewed. Set when an order that gave
 * back its store credit (its payment was given up on) is paid after all,
 * and the customer's balance no longer covers taking the credit again.
 *
 * Safe on a fresh install and on a store that already has orders: the
 * column starts empty, which reads as "nothing to review".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'review_reason')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('review_reason', 500)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'review_reason')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('review_reason');
            });
        }
    }
};
