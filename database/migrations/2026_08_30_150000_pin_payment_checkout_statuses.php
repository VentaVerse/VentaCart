<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * COD and Bank Transfer no longer fall back to the store's Default status
 * when their "Order status on checkout" is unset (owner ruling 2026-08-30):
 * a method with none selected is not offered at checkout at all.
 *
 * Continuity: an existing store that was living on that fallback gets the
 * Default status it was effectively using written into each method's
 * setting, once, only where nothing was chosen, so neither method
 * disappears from its checkout at upgrade. A fresh install pins nothing
 * and chooses on each method's page.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (\App\Support\InstallState::isFreshInstall()) {
            return;
        }
        if (! Schema::hasTable('settings') || ! Schema::hasTable('order_statuses')) {
            return;
        }

        $default = DB::table('order_statuses')->where('is_default', true)->orderBy('id')->value('id');
        if (! $default) {
            return;
        }

        foreach (['cod_success_status_id', 'bank_transfer_success_status_id'] as $key) {
            $current = DB::table('settings')->where('group', 'payment')->where('key', $key)->value('value');
            $alive = $current && DB::table('order_statuses')->where('id', (int) $current)->exists();
            if ($alive) {
                continue;
            }
            DB::table('settings')->updateOrInsert(
                ['group' => 'payment', 'key' => $key],
                ['value' => (string) $default, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        // A pinned choice is exactly what the store was doing; leave it.
    }
};
