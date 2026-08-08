<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrderStatusSeeder extends Seeder
{
    /**
     * Status names (case-insensitive) that default to the "unpaid" fulfillment stage.
     */
    private const UNPAID_NAMES = ['Unpaid', 'Pending', 'Quote Ready for Payment'];

    /**
     * Status names (case-insensitive) that default to the "cancelled" fulfillment stage.
     */
    private const CANCELLED_NAMES = [
        'Canceled', 'Canceled Reversal', 'Denied', 'Expired', 'Failed',
        'Refunded', 'Reversed', 'Voided', 'Chargeback',
    ];

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
                'fulfillment_stage' => self::stageFor($status['name']),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Resolve the default fulfillment stage for a status name (case-insensitive match).
     */
    public static function stageFor(string $name): string
    {
        $needle = mb_strtolower($name);

        if (in_array($needle, array_map('mb_strtolower', self::UNPAID_NAMES), true)) {
            return 'unpaid';
        }

        if (in_array($needle, array_map('mb_strtolower', self::CANCELLED_NAMES), true)) {
            return 'cancelled';
        }

        return 'ready';
    }
}
