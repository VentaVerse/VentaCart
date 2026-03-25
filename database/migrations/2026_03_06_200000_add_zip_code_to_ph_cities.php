<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('ph_cities', 'zip_code')) {
            return;
        }

        Schema::table('ph_cities', function (Blueprint $table) {
            $table->string('zip_code', 10)->nullable()->after('name');
        });

        // Seed zip codes from pre-built mapping
        $mapFile = base_path('database/data/city-zip-map.json');
        if (file_exists($mapFile)) {
            $map = json_decode(file_get_contents($mapFile), true);
            foreach ($map as $cityId => $zip) {
                \Illuminate\Support\Facades\DB::table('ph_cities')
                    ->where('id', $cityId)
                    ->update(['zip_code' => $zip]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('ph_cities', function (Blueprint $table) {
            $table->dropColumn('zip_code');
        });
    }
};
