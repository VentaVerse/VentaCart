<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrderStatusSeeder extends Seeder
{
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
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
