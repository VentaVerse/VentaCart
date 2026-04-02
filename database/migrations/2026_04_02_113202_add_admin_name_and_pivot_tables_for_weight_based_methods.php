<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add admin_name column
        Schema::table('weight_based_methods', function (Blueprint $table) {
            $table->string('admin_name')->after('name')->default('');
        });

        // Populate admin_name from existing name for current records
        DB::table('weight_based_methods')->whereRaw('admin_name = ""')->update([
            'admin_name' => DB::raw('name'),
        ]);

        // Create pivot table for geo zones
        Schema::create('weight_based_method_geo_zone', function (Blueprint $table) {
            $table->unsignedBigInteger('weight_based_method_id');
            $table->unsignedBigInteger('geo_zone_id');

            $table->primary(['weight_based_method_id', 'geo_zone_id'], 'wbm_gz_primary');
            $table->foreign('weight_based_method_id', 'wbm_gz_method_fk')->references('id')->on('weight_based_methods')->cascadeOnDelete();
            $table->foreign('geo_zone_id', 'wbm_gz_zone_fk')->references('id')->on('geo_zones')->cascadeOnDelete();
        });

        // Create pivot table for customer groups
        Schema::create('weight_based_method_customer_group', function (Blueprint $table) {
            $table->unsignedBigInteger('weight_based_method_id');
            $table->unsignedBigInteger('customer_group_id');

            $table->primary(['weight_based_method_id', 'customer_group_id'], 'wbm_cg_primary');
            $table->foreign('weight_based_method_id', 'wbm_cg_method_fk')->references('id')->on('weight_based_methods')->cascadeOnDelete();
            $table->foreign('customer_group_id', 'wbm_cg_group_fk')->references('id')->on('customer_groups')->cascadeOnDelete();
        });

        // Migrate existing single FK data into pivot tables
        $methods = DB::table('weight_based_methods')->get(['id', 'geo_zone_id', 'customer_group_id']);
        foreach ($methods as $m) {
            if ($m->geo_zone_id) {
                DB::table('weight_based_method_geo_zone')->insert([
                    'weight_based_method_id' => $m->id,
                    'geo_zone_id' => $m->geo_zone_id,
                ]);
            }
            if ($m->customer_group_id) {
                DB::table('weight_based_method_customer_group')->insert([
                    'weight_based_method_id' => $m->id,
                    'customer_group_id' => $m->customer_group_id,
                ]);
            }
        }

        // Drop old single FK columns
        Schema::table('weight_based_methods', function (Blueprint $table) {
            $table->dropForeign(['geo_zone_id']);
            $table->dropForeign(['customer_group_id']);
            $table->dropColumn(['geo_zone_id', 'customer_group_id']);
        });
    }

    public function down(): void
    {
        Schema::table('weight_based_methods', function (Blueprint $table) {
            $table->unsignedBigInteger('geo_zone_id')->nullable();
            $table->unsignedBigInteger('customer_group_id')->nullable();
            $table->foreign('geo_zone_id')->references('id')->on('geo_zones')->nullOnDelete();
            $table->foreign('customer_group_id')->references('id')->on('customer_groups')->nullOnDelete();
        });

        Schema::dropIfExists('weight_based_method_customer_group');
        Schema::dropIfExists('weight_based_method_geo_zone');

        Schema::table('weight_based_methods', function (Blueprint $table) {
            $table->dropColumn('admin_name');
        });
    }
};
