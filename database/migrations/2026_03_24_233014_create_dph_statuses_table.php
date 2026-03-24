<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dph_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->foreignId('order_status_id')->nullable()->constrained('order_statuses')->nullOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        DB::table('dph_statuses')->insert([
            ['code' => 'ASSIGNED',               'name' => 'Assigned',               'order_status_id' => null, 'sort_order' => 1,  'created_at' => now(), 'updated_at' => now()],
            ['code' => 'ACCEPTED',               'name' => 'Accepted',               'order_status_id' => null, 'sort_order' => 2,  'created_at' => now(), 'updated_at' => now()],
            ['code' => 'ASSIGNING_RIDER',        'name' => 'Assigning Rider',        'order_status_id' => null, 'sort_order' => 3,  'created_at' => now(), 'updated_at' => now()],
            ['code' => 'START_PICKUP',           'name' => 'Start Pickup',           'order_status_id' => null, 'sort_order' => 4,  'created_at' => now(), 'updated_at' => now()],
            ['code' => 'DONE_PICKUP',            'name' => 'Done Pickup',            'order_status_id' => null, 'sort_order' => 5,  'created_at' => now(), 'updated_at' => now()],
            ['code' => 'ARRIVED_AT_HUB',         'name' => 'Arrived at Hub',         'order_status_id' => null, 'sort_order' => 6,  'created_at' => now(), 'updated_at' => now()],
            ['code' => 'START_DELIVERY',         'name' => 'Start Delivery',         'order_status_id' => null, 'sort_order' => 7,  'created_at' => now(), 'updated_at' => now()],
            ['code' => 'DONE',                   'name' => 'Done',                   'order_status_id' => null, 'sort_order' => 8,  'created_at' => now(), 'updated_at' => now()],
            ['code' => 'FAILED_PICKUP',          'name' => 'Failed Pickup',          'order_status_id' => null, 'sort_order' => 9,  'created_at' => now(), 'updated_at' => now()],
            ['code' => 'FAILED_DELIVERY',        'name' => 'Failed Delivery',        'order_status_id' => null, 'sort_order' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'CANCEL',                 'name' => 'Cancelled',              'order_status_id' => null, 'sort_order' => 11, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'IN_TRANSIT_FOR_RETURN',  'name' => 'In Transit for Return',  'order_status_id' => null, 'sort_order' => 12, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'RETURNED',               'name' => 'Returned',               'order_status_id' => null, 'sort_order' => 13, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('dph_statuses');
    }
};
