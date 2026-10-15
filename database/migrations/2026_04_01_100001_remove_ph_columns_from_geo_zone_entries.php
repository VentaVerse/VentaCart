<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Delete entries that only had PH references (no country_id or zone_id)
        DB::table('geo_zone_entries')
            ->whereNull('country_id')
            ->whereNull('zone_id')
            ->delete();

        // The foreign key goes first. On MySQL and MariaDB the composite
        // location index is the one backing the foreign key on geo_zone_id,
        // so the index cannot be dropped while the key exists. Dropping the
        // index first used to fail silently here, the column drops below then
        // only shrank it, and re-adding it further down collided with its own
        // name: every fresh install on MySQL or MariaDB stopped at this
        // migration. SQLite never showed it, which is what the tests run on.
        try {
            Schema::table('geo_zone_entries', function (Blueprint $table) {
                $table->dropForeign(['geo_zone_id']);
            });
        } catch (\Throwable) {
            // FK may already be dropped or have a different name
        }

        // Then the old location index. SQLite also refuses to drop columns
        // that are still referenced by an index.
        if (Schema::hasIndex('geo_zone_entries', 'gze_location_index')) {
            Schema::table('geo_zone_entries', function (Blueprint $table) {
                $table->dropIndex('gze_location_index');
            });
        }

        // Drop PH columns if they still exist
        $columns = Schema::getColumnListing('geo_zone_entries');
        $toDrop = array_intersect(['ph_region_id', 'ph_province_id', 'ph_city_id'], $columns);
        if (!empty($toDrop)) {
            Schema::table('geo_zone_entries', function (Blueprint $table) use ($toDrop) {
                $table->dropColumn($toDrop);
            });
        }

        Schema::table('geo_zone_entries', function (Blueprint $table) {
            $table->index(['geo_zone_id', 'country_id', 'zone_id'], 'gze_location_index');
            $table->foreign('geo_zone_id')->references('id')->on('geo_zones')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('geo_zone_entries', function (Blueprint $table) {
            $table->dropIndex('gze_location_index');
            $table->unsignedBigInteger('ph_region_id')->nullable();
            $table->unsignedBigInteger('ph_province_id')->nullable();
            $table->unsignedBigInteger('ph_city_id')->nullable();
        });
    }
};
