<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $groups = [
            'dashboard' => ['view'],
            'products' => ['view', 'create', 'edit', 'delete'],
            'categories' => ['view', 'create', 'edit', 'delete'],
            'orders' => ['view', 'create', 'edit', 'delete'],
            'customers' => ['view', 'create', 'edit', 'delete'],
            'reports' => ['view'],
            'settings' => ['view', 'edit'],
            'users' => ['view', 'create', 'edit', 'delete'],
        ];

        $permissions = [];
        foreach ($groups as $group => $actions) {
            foreach ($actions as $action) {
                // firstOrCreate, not create: a permission the store already
                // has is left exactly as it is (an admin may have renamed it),
                // and one it is missing is added. Re-running must not duplicate
                // the set or collide with the slug's unique index.
                $permissions[] = \App\Models\Permission::firstOrCreate(
                    ['slug' => "{$group}.{$action}"],
                    [
                        'name' => ucfirst($action) . ' ' . ucfirst($group),
                        'group' => $group,
                    ]
                );
            }
        }

        // Super Admin - gets all permissions
        $superAdmin = \App\Models\Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'description' => 'Full access to everything']
        );
        $superAdmin->permissions()->syncWithoutDetaching(collect($permissions)->pluck('id'));

        // Store Manager - everything except user management and settings
        $manager = \App\Models\Role::firstOrCreate(
            ['slug' => 'store-manager'],
            ['name' => 'Store Manager', 'description' => 'Manage store operations']
        );
        $manager->permissions()->syncWithoutDetaching(
            collect($permissions)->whereNotIn('group', ['users', 'settings'])->pluck('id')
        );

        // Staff - view only + order management
        $staff = \App\Models\Role::firstOrCreate(
            ['slug' => 'staff'],
            ['name' => 'Staff', 'description' => 'Limited access for staff']
        );
        $staff->permissions()->syncWithoutDetaching(
            collect($permissions)->filter(function ($p) {
                return $p->slug === 'dashboard.view'
                    || str_starts_with($p->slug, 'orders.')
                    || $p->slug === 'products.view'
                    || $p->slug === 'customers.view';
            })->pluck('id')
        );
    }
}
