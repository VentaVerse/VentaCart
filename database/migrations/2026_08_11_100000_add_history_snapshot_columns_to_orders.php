<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orders keep their own history.
 *
 * An order is an immutable historical record: every value its pages display
 * must be a value the order OWNS. The foreign keys stay, nullable, purely as
 * convenience links for navigation. Nothing rendered resolves through a
 * relationship, so deleting a status, a courier, a customer or a staff user
 * can never blank out what an order says about itself.
 *
 * This completes the 2026-07-02 snapshot work, which deliberately left order
 * status names resolving live. That decision is reversed here by the owner.
 *
 * Also hardens orders.store_id: it was ON DELETE CASCADE, meaning deleting a
 * store silently deleted every order it ever had. store_id is hardcoded to 1
 * today so the hazard is latent, but Venta ships to many deployments and a
 * financial record must never be collateral damage of a config delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_status_name')->nullable()->after('order_status_id');
            $table->string('customer_group_name')->nullable()->after('customer_id');
            $table->string('created_by_name')->nullable()->after('created_by');
            $table->string('shipping_courier_name')->nullable()->after('shipping_courier_id');
        });

        Schema::table('order_histories', function (Blueprint $table) {
            $table->string('order_status_name')->nullable()->after('order_status_id');
            $table->string('created_by_name')->nullable()->after('created_by');
        });

        // CASCADE -> RESTRICT. Drop and re-add; the column itself is untouched.
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->foreign('store_id')->references('id')->on('stores')->restrictOnDelete();
        });

        $this->backfill();
    }

    /**
     * Fill the snapshots for orders that already existed.
     *
     * Adding these columns without this left every pre-existing order with a
     * NULL snapshot, and the admin list, the dashboard and each customer's own
     * order history all read that as "this order never reached a status". On a
     * live store 27,706 orders vanished from every one of those screens on
     * deploy day (2026-08-20) while sitting untouched in the database. A schema
     * change that breaks the application until a human remembers a separate
     * command is not finished.
     *
     * Delegates to the same class orders:backfill-snapshots calls, so the
     * statements cannot drift between the automatic path and the repair tool.
     */
    private function backfill(): void
    {
        \App\Support\OrderSnapshotBackfill::run();
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
        });

        Schema::table('order_histories', function (Blueprint $table) {
            $table->dropColumn(['order_status_name', 'created_by_name']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'order_status_name',
                'customer_group_name',
                'created_by_name',
                'shipping_courier_name',
            ]);
        });
    }
};
