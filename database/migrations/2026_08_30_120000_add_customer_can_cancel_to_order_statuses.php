<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Customer may cancel" is a fact about a status: an order sitting on a
 * status with this on may be cancelled by its own customer from the
 * account page. Off everywhere by default; the merchant ticks the unpaid
 * ones (Pending, Unpaid, a COD-to-confirm status). Additive, instant on
 * MySQL 8, reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_statuses', function (Blueprint $table) {
            $table->boolean('customer_can_cancel')->default(false)->after('counts_as_revenue');
        });
    }

    public function down(): void
    {
        Schema::table('order_statuses', function (Blueprint $table) {
            $table->dropColumn('customer_can_cancel');
        });
    }
};
