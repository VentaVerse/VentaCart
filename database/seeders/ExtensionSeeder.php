<?php

namespace Database\Seeders;

use App\Models\Extension;
use App\Services\ExtensionManager;
use Illuminate\Database\Seeder;

/**
 * What a brand new Venta comes up running (owner ruling, 2026-08-19).
 *
 * A fresh store arrives with the handful of modules an ecommerce cannot live
 * without already installed and switched on: a look, a way to pay, a way to
 * ship, and the content features an owner expects to find and try on day one.
 * Everything else arrives NOT installed. It is still listed in the extension
 * directory with an Install button, so nothing is hidden; it simply owns no
 * tables in a database that has never used it.
 *
 * The list lives in each extension's own manifest (`default_enabled: true`),
 * not here, so shipping a new module is a manifest change and never an edit to
 * this file.
 *
 * THE ABSOLUTE RULE: this must never move a running store's switches. It is
 * gated on the registry being completely empty, which is true exactly once, on
 * the install that creates the database. A store that has answered the question
 * of what it runs has answered it forever, and every later `db:seed` on it is a
 * no-op. That is the same contract every other seeder in Venta now keeps: fill
 * the gaps, never overrule the shopkeeper.
 */
class ExtensionSeeder extends Seeder
{
    public function run(): void
    {
        // Not "are the defaults present" but "has this store ever been set up".
        // Checking per extension would re-install one an owner deliberately
        // removed, which is the same overreach in a smaller costume.
        if (Extension::query()->exists()) {
            return;
        }

        $manager = app(ExtensionManager::class);

        foreach ($manager->discovered() as $name => $manifest) {
            if (($manifest['default_enabled'] ?? false) !== true) {
                continue;
            }

            // install() runs the extension's own migrations and writes its
            // registry row enabled, which is exactly the state wanted here.
            $manager->install($name);
        }
    }
}
