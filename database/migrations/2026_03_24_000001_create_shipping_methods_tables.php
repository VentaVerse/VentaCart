<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['flat_rate', 'weight_based']);
            $table->unsignedBigInteger('geo_zone_id')->nullable();
            $table->unsignedBigInteger('customer_group_id')->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('geo_zone_id')->references('id')->on('geo_zones')->nullOnDelete();
            $table->foreign('customer_group_id')->references('id')->on('customer_groups')->nullOnDelete();
            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('shipping_method_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipping_method_id');
            $table->decimal('weight_from', 8, 3)->default(0);
            $table->decimal('weight_to', 8, 3);
            $table->decimal('cost', 10, 2);
            $table->timestamps();

            $table->foreign('shipping_method_id')->references('id')->on('shipping_methods')->cascadeOnDelete();
            $table->index('shipping_method_id');
        });

        // Migrate existing data
        $this->migrateData();
    }

    private function migrateData(): void
    {
        // Migrate flat shipping rates
        $flatRates = DB::table('flat_shipping_rates')->get();
        foreach ($flatRates as $rate) {
            $zoneName = $rate->geo_zone_id
                ? DB::table('geo_zones')->where('id', $rate->geo_zone_id)->value('name')
                : null;

            DB::table('shipping_methods')->insert([
                'name' => $zoneName ? "Flat Rate - {$zoneName}" : 'Standard Shipping',
                'type' => 'flat_rate',
                'geo_zone_id' => $rate->geo_zone_id,
                'customer_group_id' => null,
                'cost' => $rate->cost,
                'is_active' => $rate->is_active,
                'sort_order' => $rate->sort_order,
                'created_at' => $rate->created_at,
                'updated_at' => $rate->updated_at,
            ]);
        }

        // Migrate weight-based rates — group by geo_zone_id + customer_group_id
        $weightGroups = DB::table('shipping_zone_rates')
            ->select('geo_zone_id', 'customer_group_id')
            ->distinct()
            ->get();

        foreach ($weightGroups as $group) {
            $zoneName = $group->geo_zone_id
                ? DB::table('geo_zones')->where('id', $group->geo_zone_id)->value('name')
                : 'All Zones';

            $methodId = DB::table('shipping_methods')->insertGetId([
                'name' => "Shipping ({$zoneName})",
                'type' => 'weight_based',
                'geo_zone_id' => $group->geo_zone_id,
                'customer_group_id' => $group->customer_group_id,
                'cost' => null,
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $tiers = DB::table('shipping_zone_rates')
                ->where('geo_zone_id', $group->geo_zone_id)
                ->where('customer_group_id', $group->customer_group_id)
                ->get();

            foreach ($tiers as $tier) {
                DB::table('shipping_method_rates')->insert([
                    'shipping_method_id' => $methodId,
                    'weight_from' => $tier->weight_from,
                    'weight_to' => $tier->weight_to,
                    'cost' => $tier->cost,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_method_rates');
        Schema::dropIfExists('shipping_methods');
    }
};
