<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ph_provinces')) return;

        // Rename provinces to latest official names (matching core zones)
        $renames = [
            'Compostela Valley' => 'Davao De Oro (Compostela Valley)',
            'North Cotabato' => 'Cotabato',
            'Samar (Western Samar)' => 'Samar',
            'Tawi-tawi' => 'Tawi-Tawi',
        ];

        foreach ($renames as $old => $new) {
            DB::table('ph_provinces')->where('name', $old)->update(['name' => $new]);
        }

        // Split Maguindanao into del Norte and del Sur (2022 official split)
        $mag = DB::table('ph_provinces')->where('name', 'Maguindanao')->first();
        if (!$mag) return;

        // Rename existing to del Norte
        DB::table('ph_provinces')->where('id', $mag->id)->update([
            'name' => 'Maguindanao del Norte',
        ]);

        // Create del Sur — use a unique code that won't conflict
        $existingCodes = DB::table('ph_provinces')->pluck('code')->toArray();
        $delSurCode = '199900000'; // Safe code unlikely to conflict
        if (in_array($delSurCode, $existingCodes)) {
            $delSurCode = '199900001';
        }

        // Check if del Sur already exists (from a previous manual fix)
        $existingDelSur = DB::table('ph_provinces')->where('name', 'Maguindanao del Sur')->first();
        if ($existingDelSur) return; // Already split

        $delSurId = DB::table('ph_provinces')->insertGetId([
            'ph_region_id' => $mag->ph_region_id,
            'code' => $delSurCode,
            'name' => 'Maguindanao del Sur',
        ]);

        // Del Norte municipalities (stay with original province ID)
        $delNorteCities = [
            'Barira', 'Buldon', 'Datu Blah T. Sinsuat', 'Datu Odin Sinsuat (Dinaig)',
            'Kabuntalan (Tumbao)', 'Matanog', 'Northern Kabuntalan', 'Parang',
            'Upi', 'Sultan Kudarat (Nuling)', 'Sultan Mastura', 'Talitay',
        ];

        // Move everything else to del Sur
        if (Schema::hasTable('ph_cities')) {
            DB::table('ph_cities')
                ->where('ph_province_id', $mag->id)
                ->whereNotIn('name', $delNorteCities)
                ->update(['ph_province_id' => $delSurId]);
        }
    }

    public function down(): void
    {
        // Not easily reversible — merging provinces back would lose data
    }
};
