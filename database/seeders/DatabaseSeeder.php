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
            CountrySeeder::class,
            ZoneSeeder::class,
            PhilippineGeographicSeeder::class,
            ThemeSeeder::class,
            EmailTemplateSeeder::class,
        ]);

        // Create super admin user
        $superAdmin = \App\Models\Role::where('slug', 'super-admin')->first();
        User::firstOrCreate(
            ['email' => 'admin@venta.local'],
            [
                'name' => 'Admin',
                'password' => bcrypt('password'),
                'role_id' => $superAdmin?->id,
                'is_active' => true,
            ]
        );
    }
}
