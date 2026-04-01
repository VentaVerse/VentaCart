<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $phCountryId = DB::table('countries')->where('iso2', 'PH')->value('id');
        if (!$phCountryId) return;

        // Delete 16 region entries that were incorrectly mixed in as zones
        $regionsToDelete = [
            'Autonomous Region in Muslim Mindanao',
            'Bicol',
            'Cagayan Valley',
            'Calabarzon',
            'Caraga',
            'Central Luzon',
            'Central Visayas',
            'Cordillera Administrative',
            'Davao',
            'Eastern Visayas',
            'Ilocos',
            'Mimaropa',
            'Northern Mindanao',
            'Soccsksargen',
            'Western Visayas',
            'Zamboanga Peninsula',
        ];

        DB::table('zones')
            ->where('country_id', $phCountryId)
            ->whereIn('name', $regionsToDelete)
            ->delete();

        // Rename zones to match PSGC names
        $renames = [
            'National Capital Region (Metro Manila)' => 'Metro Manila',
            'Western Samar' => 'Samar',
            'Davao de Oro' => 'Davao De Oro (Compostela Valley)',
            'Tawi-Tawi' => 'Tawi-Tawi', // ensure consistent capitalization
        ];

        foreach ($renames as $old => $new) {
            DB::table('zones')
                ->where('country_id', $phCountryId)
                ->where('name', $old)
                ->update(['name' => $new]);
        }

        // Delete Maguindanao del Sur if it exists (will be kept — it's a valid 2022 split)
        // Just ensure proper naming
        DB::table('zones')
            ->where('country_id', $phCountryId)
            ->where('name', 'Maguindanao del Norte')
            ->update(['name' => 'Maguindanao del Norte']);

        DB::table('zones')
            ->where('country_id', $phCountryId)
            ->where('name', 'Maguindanao del Sur')
            ->update(['name' => 'Maguindanao del Sur']);
    }

    public function down(): void
    {
        // Not reversible — region entries came from dr5hn seed data
    }
};
