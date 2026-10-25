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

        // The shop a fresh install opens on. Declines to run the moment the
        // catalogue holds anything, so an existing store is never given demo
        // goods by an update.
        $this->call(DemoCatalogSeeder::class);

        // And the home page that shows it. After the catalogue, because it
        // declines to arrange a store with nothing in it.
        $this->call(DemoLayoutSeeder::class);

        // No admin is seeded: the first visit to the admin creates one (see
        // FirstAdminController), so a store never ships with a known login.
    }
}
