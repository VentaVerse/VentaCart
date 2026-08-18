<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The order-totals pipeline (wave: order totals): every modifier line an
 * order carried (coupon, store credit, future fees), stored as it was
 * shown at checkout. The legacy coupon_code/coupon_discount columns stay
 * and keep being written by the coupons extension for everything that
 * already reads them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->json('total_lines')->nullable()->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('total_lines');
        });
    }
};
