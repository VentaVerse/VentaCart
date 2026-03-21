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
                $permissions[] = \App\Models\Permission::create([
                    'name' => ucfirst($action) . ' ' . ucfirst($group),
                    'slug' => "{$group}.{$action}",
                    'group' => $group,
                ]);
            }
        }

        // Super Admin - gets all permissions
        $superAdmin = \App\Models\Role::create([
            'name' => 'Super Admin',
            'slug' => 'super-admin',
            'description' => 'Full access to everything',
        ]);
        $superAdmin->permissions()->attach(collect($permissions)->pluck('id'));

        // Store Manager - everything except user management and settings
        $manager = \App\Models\Role::create([
            'name' => 'Store Manager',
            'slug' => 'store-manager',
            'description' => 'Manage store operations',
        ]);
        $manager->permissions()->attach(
            collect($permissions)->whereNotIn('group', ['users', 'settings'])->pluck('id')
        );

        // Staff - view only + order management
        $staff = \App\Models\Role::create([
            'name' => 'Staff',
            'slug' => 'staff',
            'description' => 'Limited access for staff',
        ]);
        $staff->permissions()->attach(
            collect($permissions)->filter(function ($p) {
                return $p->slug === 'dashboard.view'
                    || str_starts_with($p->slug, 'orders.')
                    || $p->slug === 'products.view'
                    || $p->slug === 'customers.view';
            })->pluck('id')
        );
    }
}
