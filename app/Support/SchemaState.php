<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a table exists, without asking the database on every request.
 *
 * Code that may run before an extension's tables exist (a redirect check,
 * a theme's own documents, a free shipping note) guards itself with
 * Schema::hasTable(). On MySQL each of those is an information_schema query
 * of a few milliseconds, and a storefront page made four to seven of them
 * before doing any work.
 *
 * A table found to exist is remembered: for the rest of the process and,
 * through the cache, for later requests. A missing table is never
 * remembered, so a table created by an install or a migration is seen the
 * moment it exists. Running migrations forgets everything remembered
 * (AppServiceProvider listens for MigrationsEnded), which covers a table
 * dropped by an uninstall or a rollback.
 */
final class SchemaState
{
    public const CACHE_KEY = 'schema_tables_present';

    /** @var array<string, true>|null tables known to exist, loaded from the cache once per process */
    private static ?array $present = null;

    public static function hasTable(string $table): bool
    {
        if (self::$present === null) {
            self::$present = [];
            try {
                $cached = Cache::get(self::CACHE_KEY);
                if (is_array($cached)) {
                    self::$present = array_fill_keys(array_filter($cached, 'is_string'), true);
                }
            } catch (\Throwable) {
                // No usable cache yet (an install, a broken store): ask the database.
            }
        }

        if (isset(self::$present[$table])) {
            return true;
        }

        if (! Schema::hasTable($table)) {
            return false;
        }

        self::$present[$table] = true;
        try {
            Cache::forever(self::CACHE_KEY, array_keys(self::$present));
        } catch (\Throwable) {
            // Remembered for this process only.
        }

        return true;
    }

    /** Reload from the cache on the next check: a queue worker does this before each job. */
    public static function forgetInProcess(): void
    {
        self::$present = null;
    }

    /** Forget every remembered table (after migrations, or in a test that drops one). */
    public static function forget(): void
    {
        self::$present = null;
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable) {
            // Nothing to forget without a cache.
        }
    }
}
