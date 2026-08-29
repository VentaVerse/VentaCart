<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each order status gets a storefront tone (neutral, info, warning, success,
 * danger): the colour family of its badge in the customer's account. Until
 * now the badge derived a CSS class from the status NAME, so the statuses a
 * real store runs on (Unpaid, Start Pickup, Order Being Shipped) rendered as
 * plain text and any two-word name broke the class outright.
 *
 * Continuity: an existing store gets a tone suggested from each status's
 * name and stock rule once, here, so its badges are coloured at upgrade.
 * A fresh install is seeded with tones and skips the backfill. The
 * merchant edits tones on Settings, Order Status, from then on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_statuses', function (Blueprint $table) {
            $table->string('tone', 16)->default('neutral')->after('customer_can_cancel');
        });

        if (\App\Support\InstallState::isFreshInstall()) {
            return;
        }

        foreach (DB::table('order_statuses')->get(['id', 'name', 'deducts_stock']) as $status) {
            DB::table('order_statuses')->where('id', $status->id)->update([
                'tone' => \App\Support\StatusTone::suggest((string) $status->name, (bool) $status->deducts_stock),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('order_statuses', function (Blueprint $table) {
            $table->dropColumn('tone');
        });
    }
};
