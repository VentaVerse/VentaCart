<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            StoreSeeder::class,
            RolePermissionSeeder::class,
            OrderStatusSeeder::class,
            WorldGeographicSeeder::class,
            ThemeSeeder::class,
            EmailTemplateSeeder::class,
            AutomationSeeder::class,
            // Last of the core set: installing an extension runs its own
            // migrations, so everything core owns should already be in place.
            // A no-op on any store that has been set up before.
            ExtensionSeeder::class,
        ]);

        // Run seeders registered by extensions (e.g. PH Address)
        foreach (config('venta.extension_seeders', []) as $seeder) {
            $this->call($seeder);
        }

        // No admin is seeded: the first visit to the admin creates one (see
        // FirstAdminController), so a store never ships with a known login.
    }
}
