<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The store's baseline order statuses.
 *
 * SETUP POLICY (owner ruling, 2026-08-09): this seeder ships NO
 * fulfillment-specific statuses. The courier fulfillment module is
 * configured deliberately at setup, so a fresh install deliberately has no
 * To-Handover-placed status until the merchant creates one under Sales,
 * Order Statuses - and the fulfillment settings hub carries a setup notice
 * saying exactly that, because booking refuses without one.
 *
 * placementFor() below still classifies whatever names DO exist (including
 * fulfillment-shaped names a merchant creates, or an OpenCart-era store
 * brings with it), which is a different job from seeding them.
 */
class OrderStatusSeeder extends Seeder
{
    /**
     * Status names (case-insensitive) that default to the "unpaid" fulfillment placement.
     */
    private const UNPAID_NAMES = ['Unpaid', 'Pending', 'Quote Ready for Payment'];

    /**
     * Status names (case-insensitive) that default to the "cancelled" fulfillment placement.
     */
    private const CANCELLED_NAMES = [
        'Canceled', 'Canceled Reversal', 'Denied', 'Expired', 'Failed',
        'Refunded', 'Reversed', 'Voided', 'Chargeback',
    ];

    /**
     * Status names (case-insensitive) that default to the "delivered" fulfillment placement.
     */
    private const DELIVERED_NAMES = ['Delivered', 'Complete'];

    /**
     * Status names (case-insensitive) that default to the "shipping" fulfillment placement.
     */
    private const SHIPPING_NAMES = ['Shipped', 'In Transit to Manila'];

    /**
     * Status names (case-insensitive) that default to the "to_handover" fulfillment placement.
     */
    private const TO_HANDOVER_NAMES = ['Order Being Shipped', 'Done Pickup', 'Start Pickup'];

    public function run(): void
    {
        $storeId = 1;
        $now = now();

        $statuses = [
            ['name' => 'Pending', 'is_default' => true, 'sort_order' => 1],
            ['name' => 'Processing', 'is_default' => false, 'sort_order' => 2],
            ['name' => 'Shipped', 'is_default' => false, 'sort_order' => 3],
            ['name' => 'Complete', 'is_default' => false, 'sort_order' => 5],
            ['name' => 'Canceled', 'is_default' => false, 'sort_order' => 7],
            ['name' => 'Denied', 'is_default' => false, 'sort_order' => 8],
            ['name' => 'Failed', 'is_default' => false, 'sort_order' => 10],
            ['name' => 'Refunded', 'is_default' => false, 'sort_order' => 11],
            ['name' => 'Expired', 'is_default' => false, 'sort_order' => 14],
            ['name' => 'Payment Received', 'is_default' => false, 'sort_order' => 17],
            ['name' => 'Unpaid', 'is_default' => false, 'sort_order' => 24],
            ['name' => 'Delivered', 'is_default' => false, 'sort_order' => 26],
        ];

        foreach ($statuses as $status) {
            DB::table('order_statuses')->insertOrIgnore([
                'store_id' => $storeId,
                'name' => $status['name'],
                'is_default' => $status['is_default'],
                'sort_order' => $status['sort_order'],
                'fulfillment_placement' => self::placementFor($status['name']),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Resolve the default fulfillment placement for a status name (case-insensitive match).
     */
    public static function placementFor(string $name): string
    {
        $needle = mb_strtolower($name);

        if (in_array($needle, array_map('mb_strtolower', self::UNPAID_NAMES), true)) {
            return 'unpaid';
        }

        if (in_array($needle, array_map('mb_strtolower', self::CANCELLED_NAMES), true)) {
            return 'cancelled';
        }

        if (in_array($needle, array_map('mb_strtolower', self::DELIVERED_NAMES), true)) {
            return 'delivered';
        }

        if (in_array($needle, array_map('mb_strtolower', self::SHIPPING_NAMES), true)) {
            return 'shipping';
        }

        if (in_array($needle, array_map('mb_strtolower', self::TO_HANDOVER_NAMES), true)) {
            return 'to_handover';
        }

        return 'to_pack';
    }
}
