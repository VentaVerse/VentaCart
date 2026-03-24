<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the unified tables (data was migrated from old tables which are still intact)
        Schema::dropIfExists('shipping_method_rates');
        Schema::dropIfExists('shipping_methods');

        // Improve flat_shipping_rates: add name + customer_group_id
        Schema::table('flat_shipping_rates', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
            $table->unsignedBigInteger('customer_group_id')->nullable()->after('geo_zone_id');
            $table->foreign('customer_group_id')->references('id')->on('customer_groups')->nullOnDelete();
        });

        // Set default names from geo zone
        $rates = \DB::table('flat_shipping_rates')->get();
        foreach ($rates as $rate) {
            $zoneName = $rate->geo_zone_id
                ? \DB::table('geo_zones')->where('id', $rate->geo_zone_id)->value('name')
                : null;
            \DB::table('flat_shipping_rates')->where('id', $rate->id)->update([
                'name' => $zoneName ? "Flat Rate - {$zoneName}" : 'Standard Shipping',
            ]);
        }

        // Create independent weight-based shipping methods
        Schema::create('weight_based_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('geo_zone_id')->nullable();
            $table->unsignedBigInteger('customer_group_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('geo_zone_id')->references('id')->on('geo_zones')->nullOnDelete();
            $table->foreign('customer_group_id')->references('id')->on('customer_groups')->nullOnDelete();
        });

        Schema::create('weight_based_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('weight_based_method_id');
            $table->decimal('weight_from', 8, 3)->default(0);
            $table->decimal('weight_to', 8, 3);
            $table->decimal('cost', 10, 2);
            $table->timestamps();

            $table->foreign('weight_based_method_id')->references('id')->on('weight_based_methods')->cascadeOnDelete();
        });

        // Migrate old shipping_zone_rates into the new structure
        $groups = \DB::table('shipping_zone_rates')
            ->select('geo_zone_id', 'customer_group_id')
            ->distinct()
            ->get();

        foreach ($groups as $group) {
            $zoneName = $group->geo_zone_id
                ? \DB::table('geo_zones')->where('id', $group->geo_zone_id)->value('name')
                : 'All Zones';

            $methodId = \DB::table('weight_based_methods')->insertGetId([
                'name' => "Shipping ({$zoneName})",
                'geo_zone_id' => $group->geo_zone_id,
                'customer_group_id' => $group->customer_group_id,
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $tiers = \DB::table('shipping_zone_rates')
                ->where('geo_zone_id', $group->geo_zone_id)
                ->where('customer_group_id', $group->customer_group_id)
                ->get();

            foreach ($tiers as $tier) {
                \DB::table('weight_based_rates')->insert([
                    'weight_based_method_id' => $methodId,
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
        Schema::dropIfExists('weight_based_rates');
        Schema::dropIfExists('weight_based_methods');

        Schema::table('flat_shipping_rates', function (Blueprint $table) {
            $table->dropForeign(['customer_group_id']);
            $table->dropColumn(['name', 'customer_group_id']);
        });
    }
};
