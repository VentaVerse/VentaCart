<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The muted text colour every light preset shipped with sat between 2.3:1
 * and 3.1:1 on the surfaces it is painted on (address cards, the default
 * address, footer payment names): near invisible, and under WCAG AA.
 * The presets and theme.json now carry values darkened along their own
 * hue to 4.6:1 or better.
 *
 * Continuity: an existing store whose live look, or a saved copy of a
 * look, still holds the exact seeded value gets the matching new value
 * once, here. A value the merchant changed by hand is not the seeded one
 * and is left alone. A fresh install seeds the new values and skips this.
 */
return new class extends Migration
{
    private const LIFT = [
        '#9CA3AF' => '#696D75', // Standard, Centered, Clean Light, theme.json
        '#8D929B' => '#6A6D74', // Handbill
        '#96969E' => '#6A6A70', // Stockroom
    ];

    public function up(): void
    {
        if (\App\Support\InstallState::isFreshInstall() || ! Schema::hasTable('settings')) {
            return;
        }

        $rows = DB::table('settings')
            ->where('key', 'text_muted')
            ->where(fn ($q) => $q->where('group', 'colors')->orWhere('group', 'like', 'colors\_\_%'))
            ->get(['id', 'value']);

        foreach ($rows as $row) {
            $new = self::LIFT[strtoupper((string) $row->value)] ?? null;
            if ($new !== null) {
                DB::table('settings')->where('id', $row->id)->update(['value' => $new]);
            }
        }

        // ThemeService caches the settings forever under this key.
        \Illuminate\Support\Facades\Cache::forget('theme_settings');
    }

    public function down(): void
    {
        // The old values were the defect; nothing to restore.
    }
};
