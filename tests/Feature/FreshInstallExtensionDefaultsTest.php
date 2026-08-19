<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\ExtensionManager;
use Database\Seeders\ExtensionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Owner ruling, 2026-08-19, in three parts:
 *
 *   1. A FRESH install arrives with the handful of modules an ecommerce cannot
 *      live without already installed and switched on.
 *   2. Everything else arrives NOT INSTALLED. It still shows in the directory
 *      with an Install button, so nothing is hidden; it simply owns no tables
 *      in a database that has never used it.
 *   3. An update NEVER changes a running store's on/off state, in either
 *      direction. That rule is absolute and is the same principle the seeders
 *      follow: fill the gaps, never overrule the shopkeeper.
 *
 * Rule 3 was already honoured by the continuity migrations. Rules 1 and 2 were
 * not implemented at all, and what a fresh install actually produced was
 * accidental: every continuity migration tested for "an existing deploy" by
 * asking whether the extension's own table existed, and core migrations create
 * those tables on a fresh install too, so the test answered yes to a database
 * seconds old. A new store came up with Blog, Coupons, Reviews, SEO and Bank
 * Transfer switched on and Cash on Delivery switched off, which nobody chose.
 */
class FreshInstallExtensionDefaultsTest extends TestCase
{
    use RefreshDatabase;

    /** Installed AND enabled on a fresh install. */
    private const ENABLED = [
        'theme',
        'blog',
        'coupons',
        'reviews',
        'shipping-free',
        'shipping-flat-rate',
        'payment-cod',
    ];

    /** Present in the directory with an Install button, owning no tables. */
    private const NOT_INSTALLED = [
        'seo',
        'payment-bank-transfer',
        'payment-hitpay',
        'shipping-weight-based',
        'abandoned-carts',
        'banners',
        'courier-fulfillment',
        'erp-sync',
        'shipping-dhl',
        'stock-alerts',
        'store-credit',
        'email-campaigns',
    ];

    /**
     * Migrating a brand new database must install nothing by itself. The
     * continuity migrations exist to carry an EXISTING store's choices across;
     * on a database that has never been a store there is nothing to carry, and
     * anything they wrote here would be a default nobody picked.
     */
    public function test_migrating_a_fresh_database_installs_no_extensions(): void
    {
        $this->assertSame(
            0,
            DB::table('extensions')->count(),
            'Continuity migrations wrote extension rows into a fresh install.'
        );
    }

    public function test_a_fresh_install_enables_exactly_the_essentials(): void
    {
        $this->seed(ExtensionSeeder::class);

        $enabled = DB::table('extensions')->where('enabled', true)->pluck('name')->sort()->values()->all();

        $this->assertSame(collect(self::ENABLED)->sort()->values()->all(), $enabled);
    }

    public function test_a_fresh_install_leaves_every_other_module_uninstalled(): void
    {
        $this->seed(ExtensionSeeder::class);

        foreach (self::NOT_INSTALLED as $name) {
            $this->assertFalse(
                DB::table('extensions')->where('name', $name)->exists(),
                "{$name} should not be installed on a fresh install."
            );
        }
    }

    /**
     * Rule 2 says "not installed", not "hidden". Every module Venta ships stays
     * listed in the directory so the owner can turn it on whenever they like.
     */
    public function test_the_uninstalled_modules_are_still_offered_in_the_directory(): void
    {
        $discovered = app(ExtensionManager::class)->discovered()->keys()->all();

        foreach (self::NOT_INSTALLED as $name) {
            $this->assertContains($name, $discovered, "{$name} vanished from the extension directory.");
        }
    }

    /**
     * Rule 3, the absolute one. A store that turned Blog off and Stock Alerts
     * on must come out of an upgrade exactly as it went in.
     */
    public function test_seeding_an_existing_store_never_moves_its_switches(): void
    {
        // A shop that has been running and made its own choices.
        DB::table('extensions')->insert([
            ['name' => 'blog',         'version' => '1.0.0', 'enabled' => false, 'installed_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'stock-alerts', 'version' => '1.0.0', 'enabled' => true,  'installed_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->seed(ExtensionSeeder::class);

        $this->assertFalse((bool) DB::table('extensions')->where('name', 'blog')->value('enabled'), 'An upgrade re-enabled a module the store had switched off.');
        $this->assertTrue((bool) DB::table('extensions')->where('name', 'stock-alerts')->value('enabled'), 'An upgrade disabled a module the store had switched on.');

        // And it must not quietly add the rest of the default set either: this
        // store already answered the question of what it runs.
        $this->assertSame(2, DB::table('extensions')->count(), 'An upgrade installed modules into a store that never asked for them.');
    }

    /**
     * The detector itself. A fresh install is entirely migration batch 1; a
     * store that has migrated before is on at least batch 2 by the time these
     * run. Every earlier attempt at this test (does the table exist, are there
     * settings) answered "existing store" to a database seconds old, because
     * core migrations create those tables and write twelve settings rows of
     * their own.
     */
    public function test_the_fresh_install_detector_reads_the_migration_batch(): void
    {
        $this->assertTrue(
            \App\Support\InstallState::isFreshInstall(),
            'A database built in a single migration run should read as a fresh install.'
        );

        // A store that has migrated before: this run lands in a later batch.
        DB::table('migrations')->insert(['migration' => '2099_01_01_000000_a_later_deploy', 'batch' => 2]);

        $this->assertFalse(
            \App\Support\InstallState::isFreshInstall(),
            'A database that has migrated more than once is a running store, not a fresh install.'
        );
    }

    /**
     * The trap the old discriminators fell into, pinned so nobody reinstates
     * them: both signals they relied on are present on a fresh install.
     */
    public function test_a_fresh_install_already_has_the_tables_and_settings_the_old_checks_trusted(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('blog_posts'));
        $this->assertTrue(
            Setting::where('group', 'payment')->exists(),
            'payment settings exist on a fresh install, which is why the legacy check misfired.'
        );
    }
}
