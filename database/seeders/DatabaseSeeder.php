<?php

namespace Database\Seeders;

use App\Models\User;
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

        // Create super admin user. Username is derived from the email
        // local-part (the part before "@"), which is how every other admin
        // account gets its username too (see the one-off backfill for
        // existing installs and the Users CRUD's admin-entered value).
        $superAdmin = \App\Models\Role::where('slug', 'super-admin')->first();
        User::firstOrCreate(
            ['email' => 'admin@venta.local'],
            [
                'name' => 'Admin',
                'username' => 'admin',
                'password' => bcrypt('password'),
                'role_id' => $superAdmin?->id,
                'is_active' => true,
            ]
        );
    }
}
