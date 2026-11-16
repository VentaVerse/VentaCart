<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The store's emails are sent from the store's own email (Settings > General,
 * the stores.email column) under the site name, so the separate sender
 * settings mail.from_address and mail.from_name are retired.
 *
 * An existing From address is carried onto the store record when the store
 * has no email yet, so a store keeps sending from, and receiving order
 * alerts at, the same address. The old From name is not carried anywhere:
 * the site name is the From name now, and keeping a second name for the
 * same store would let the two drift apart.
 *
 * Safe on a fresh install (no store row or no setting: nothing to copy) and
 * safe to run again (the settings are gone, and a store email that is
 * already set is never overwritten).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $fromAddress = DB::table('settings')
            ->where('group', 'mail')
            ->where('key', 'from_address')
            ->value('value');
        $fromAddress = is_string($fromAddress) ? trim($fromAddress) : '';

        if ($fromAddress !== ''
            && mb_strlen($fromAddress) <= 255
            && filter_var($fromAddress, FILTER_VALIDATE_EMAIL, FILTER_FLAG_EMAIL_UNICODE) !== false
            && Schema::hasTable('stores')
        ) {
            DB::table('stores')
                ->where('id', (int) config('app.store_id', 1))
                ->where(fn ($q) => $q->whereNull('email')->orWhere('email', ''))
                ->update(['email' => $fromAddress, 'updated_at' => now()]);
        }

        DB::table('settings')
            ->where('group', 'mail')
            ->whereIn('key', ['from_address', 'from_name'])
            ->delete();

        $this->forgetCachedSettings();
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $email = Schema::hasTable('stores')
            ? DB::table('stores')->where('id', (int) config('app.store_id', 1))->value('email')
            : null;
        $siteName = DB::table('settings')
            ->where('group', 'identity')
            ->where('key', 'site_name')
            ->value('value');

        $now = now();

        foreach (['from_address' => $email, 'from_name' => $siteName] as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['group' => 'mail', 'key' => $key],
                ['value' => is_string($value) ? $value : '', 'created_at' => $now, 'updated_at' => $now]
            );
        }

        $this->forgetCachedSettings();
    }

    /** The settings are cached whole; drop the copy so it is rebuilt. */
    private function forgetCachedSettings(): void
    {
        try {
            Cache::forget('theme_settings');
        } catch (\Throwable) {
            // An unreachable cache store must not fail the migration.
        }
    }
};
