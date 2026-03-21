<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $storeId = 1;
        $existing = DB::table('order_statuses')
            ->where('store_id', $storeId)
            ->pluck('slug')
            ->toArray();

        $newStatuses = [
            ['slug' => 'booked', 'name' => 'Booked', 'stock_action' => null, 'sort_order' => 10],
            ['slug' => 'confirmed', 'name' => 'Confirmed', 'stock_action' => null, 'sort_order' => 11],
            ['slug' => 'pickup-started', 'name' => 'Pickup Started', 'stock_action' => null, 'sort_order' => 12],
            ['slug' => 'picked-up', 'name' => 'Picked Up', 'stock_action' => null, 'sort_order' => 13],
            ['slug' => 'in-transit', 'name' => 'In Transit', 'stock_action' => null, 'sort_order' => 14],
            ['slug' => 'out-for-delivery', 'name' => 'Out for Delivery', 'stock_action' => null, 'sort_order' => 15],
            ['slug' => 'failed-pickup', 'name' => 'Failed Pickup', 'stock_action' => null, 'sort_order' => 20],
            ['slug' => 'failed-delivery', 'name' => 'Failed Delivery', 'stock_action' => null, 'sort_order' => 21],
            ['slug' => 'returning', 'name' => 'Returning', 'stock_action' => null, 'sort_order' => 22],
            ['slug' => 'returned', 'name' => 'Returned', 'stock_action' => 'increment', 'sort_order' => 23],
        ];

        $now = now();
        foreach ($newStatuses as $status) {
            if (!in_array($status['slug'], $existing)) {
                DB::table('order_statuses')->insert([
                    'store_id' => $storeId,
                    'name' => $status['name'],
                    'slug' => $status['slug'],
                    'is_default' => false,
                    'stock_action' => $status['stock_action'],
                    'sort_order' => $status['sort_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $newSlugs = [
            'booked', 'confirmed', 'pickup-started', 'picked-up',
            'in-transit', 'out-for-delivery', 'failed-pickup',
            'failed-delivery', 'returning', 'returned',
        ];

        DB::table('order_statuses')
            ->where('store_id', 1)
            ->whereIn('slug', $newSlugs)
            ->delete();
    }
};
