<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\AdminPermissions;
use Illuminate\Database\Seeder;

/**
 * The permission catalog (App\Support\AdminPermissions::GROUPS) and the three
 * shipped roles.
 *
 * Fills gaps and never overrules the store: a permission or role that
 * already exists is left as it is (an admin may have renamed it), and a
 * shipped role is only given its defaults for permissions this run created,
 * or when this run created the role. A permission a super admin took away
 * from Store Manager stays taken away however often the seeder runs.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $created = [];
        $all = [];

        foreach (AdminPermissions::slugs() as $slug) {
            $permission = Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => AdminPermissions::label($slug), 'group' => strtok($slug, '.')]
            );
            $all[$slug] = $permission->id;
            if ($permission->wasRecentlyCreated) {
                $created[$slug] = $permission->id;
            }
        }

        $roles = [
            Role::SUPER_ADMIN => ['Super Admin', 'Full access to everything'],
            'store-manager' => ['Store Manager', 'Runs the shop: orders, catalog, customers, marketing and content'],
            'staff' => ['Staff', 'Works orders; can look up products and customers'],
        ];

        foreach ($roles as $slug => [$name, $description]) {
            $role = Role::firstOrCreate(['slug' => $slug], ['name' => $name, 'description' => $description]);

            // Super Admin passes every check whatever it holds; it is given
            // the whole set anyway so the user group screen reads true.
            if ($slug === Role::SUPER_ADMIN) {
                $role->permissions()->syncWithoutDetaching(Permission::pluck('id'));

                continue;
            }

            $pool = $role->wasRecentlyCreated ? $all : $created;
            $grant = array_values(array_intersect_key($pool, array_flip(AdminPermissions::defaultsFor($slug))));

            if ($grant !== []) {
                $role->permissions()->syncWithoutDetaching($grant);
            }
        }
    }
}
