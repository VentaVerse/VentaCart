<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes DPH (Deliveries PH) and decouples DHL from DPH's pickup-origin keys.
 *
 *  1. Copies any existing shipping.dph_pickup_* settings to shipping.pickup_*
 *     so the warehouse origin survives the rename. Preserves admin-entered data.
 *  2. Deletes all dph_* settings rows.
 *  3. Drops the orders.dph_courier_name column.
 *  4. Drops shipment_events, shipments, dph_statuses, dph_api_logs tables.
 *
 * Live continuity: DhlShippingProvider now reads pickup_* — copying the values
 * first means international DHL quotes keep using the real warehouse origin
 * instead of the hard-coded "Manila" / "1800" fallback.
 */
return new class extends Migration {
    public function up(): void
    {
        $this->migratePickupOriginSettings();
        $this->deleteDphSettings();
        $this->dropDphCourierColumn();
        $this->dropDphTables();
    }

    public function down(): void
    {
        // Intentionally non-reversible. DPH is gone and the data is unrecoverable.
    }

    private function migratePickupOriginSettings(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        $renameMap = [
            'dph_pickup_address'        => 'pickup_address',
            'dph_pickup_city'           => 'pickup_city',
            'dph_pickup_province'       => 'pickup_province',
            'dph_pickup_barangay'       => 'pickup_barangay',
            'dph_pickup_postal_code'    => 'pickup_postal_code',
            'dph_pickup_lat'            => 'pickup_lat',
            'dph_pickup_lng'            => 'pickup_lng',
            'dph_pickup_contact_name'   => 'pickup_contact_name',
            'dph_pickup_contact_number' => 'pickup_contact_number',
            'dph_pickup_contact_email'  => 'pickup_contact_email',
        ];

        foreach ($renameMap as $oldKey => $newKey) {
            $oldValue = DB::table('settings')
                ->where('group', 'shipping')
                ->where('key', $oldKey)
                ->value('value');

            if ($oldValue === null) {
                continue;
            }

            DB::table('settings')->updateOrInsert(
                ['group' => 'shipping', 'key' => $newKey],
                ['value' => $oldValue, 'updated_at' => now()]
            );
        }
    }

    private function deleteDphSettings(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')
            ->where('group', 'shipping')
            ->where('key', 'like', 'dph_%')
            ->delete();
    }

    private function dropDphCourierColumn(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'dph_courier_name')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('dph_courier_name');
            });
        }
    }

    private function dropDphTables(): void
    {
        Schema::dropIfExists('shipment_events');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('dph_statuses');
        Schema::dropIfExists('dph_api_logs');
    }
};
