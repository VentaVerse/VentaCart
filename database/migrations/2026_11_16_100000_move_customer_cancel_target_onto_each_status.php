<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Where a customer cancellation sends an order is now chosen on each status
 * that allows it, next to its "Customer may cancel" switch, instead of in one
 * store-wide checkout setting. Each status can send its cancellations to a
 * different place (an unpaid order to Canceled, a shipped one to Returned).
 *
 * Existing stores keep behaving as before: every status that already allows
 * cancelling takes the old setting's status, provided that status still
 * exists in the same store. The old setting is then removed so nothing reads
 * a value the admin can no longer see. Safe on a fresh install (no setting,
 * nothing copied) and safe to run again (the column check guards it).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('order_statuses', 'customer_cancel_status_id')) {
            Schema::table('order_statuses', function (Blueprint $table) {
                $table->unsignedBigInteger('customer_cancel_status_id')->nullable()->after('customer_can_cancel');
            });
        }

        $old = DB::table('settings')
            ->where('group', 'checkout')->where('key', 'customer_cancel_status_id')
            ->value('value');

        if ($old !== null && ctype_digit((string) $old)) {
            $target = DB::table('order_statuses')->where('id', (int) $old)->first(['id', 'store_id']);

            if ($target) {
                DB::table('order_statuses')
                    ->where('store_id', $target->store_id)
                    ->where('customer_can_cancel', true)
                    ->where('id', '!=', $target->id)
                    ->whereNull('customer_cancel_status_id')
                    ->update(['customer_cancel_status_id' => $target->id]);
            }
        }

        DB::table('settings')->where('group', 'checkout')->where('key', 'customer_cancel_status_id')->delete();
    }

    public function down(): void
    {
        if (! Schema::hasColumn('order_statuses', 'customer_cancel_status_id')) {
            return;
        }

        // One store-wide value can only hold one choice: keep the most used.
        $target = DB::table('order_statuses')
            ->whereNotNull('customer_cancel_status_id')
            ->groupBy('customer_cancel_status_id')
            ->orderByRaw('count(*) desc')
            ->value('customer_cancel_status_id');

        if ($target !== null) {
            DB::table('settings')->updateOrInsert(
                ['group' => 'checkout', 'key' => 'customer_cancel_status_id'],
                ['value' => (string) $target],
            );
        }

        Schema::table('order_statuses', function (Blueprint $table) {
            $table->dropColumn('customer_cancel_status_id');
        });
    }
};
