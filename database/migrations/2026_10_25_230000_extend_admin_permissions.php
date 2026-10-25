<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Admin routes now check permissions, so the catalog grows to cover every
 * admin screen, and the access each user had before checks existed is
 * carried over.
 *
 *   1. The new permissions are added. A slug the store already has is left
 *      alone.
 *   2. Store Manager and Staff get their new defaults, and Super Admin gets
 *      every new permission.
 *   3. Any other role gets a new permission when it already holds the old
 *      one that used to cover the same screens (a role that could edit
 *      products can still edit brands). Groups with no such parent
 *      (marketing, content, layout, themes, extensions without settings)
 *      are left for a super admin to grant from User Groups: granting too
 *      little is recoverable, granting too much is not.
 *   4. A user with no role had full access while nothing was checked, and
 *      after this change a user with no role has none. Every such user is
 *      put in Super Admin, so nobody is locked out of their own store.
 *
 * Safe on a fresh install (no roles, no users: only step 1 runs, and the
 * seeder grants the shipped roles their defaults) and safe to run on a store
 * that already has any of this. The lists are written out here, not read
 * from App\Support\AdminPermissions, so this migration does the same thing
 * whenever it runs.
 */
return new class extends Migration
{
    /** slug => [name, group, parent slug or null] */
    private const NEW = [
        'couriers.view' => ['View Couriers', 'couriers', 'orders.view'],
        'couriers.create' => ['Create Couriers', 'couriers', 'orders.create'],
        'couriers.edit' => ['Edit Couriers', 'couriers', 'orders.edit'],
        'couriers.delete' => ['Delete Couriers', 'couriers', 'orders.delete'],
        'reviews.view' => ['View Reviews', 'reviews', 'products.view'],
        'reviews.edit' => ['Edit Reviews', 'reviews', 'products.edit'],
        'reviews.delete' => ['Delete Reviews', 'reviews', 'products.delete'],
        'brands.view' => ['View Brands', 'brands', 'products.view'],
        'brands.create' => ['Create Brands', 'brands', 'products.create'],
        'brands.edit' => ['Edit Brands', 'brands', 'products.edit'],
        'brands.delete' => ['Delete Brands', 'brands', 'products.delete'],
        'tags.view' => ['View Tags', 'tags', 'products.view'],
        'tags.create' => ['Create Tags', 'tags', 'products.create'],
        'tags.edit' => ['Edit Tags', 'tags', 'products.edit'],
        'tags.delete' => ['Delete Tags', 'tags', 'products.delete'],
        'attributes.view' => ['View Attributes', 'attributes', 'products.view'],
        'attributes.create' => ['Create Attributes', 'attributes', 'products.create'],
        'attributes.edit' => ['Edit Attributes', 'attributes', 'products.edit'],
        'attributes.delete' => ['Delete Attributes', 'attributes', 'products.delete'],
        'media.view' => ['View Media', 'media', 'products.view'],
        'media.create' => ['Create Media', 'media', 'products.create'],
        'media.delete' => ['Delete Media', 'media', 'products.delete'],
        'customers.impersonate' => ['Impersonate Customers', 'customers', 'customers.edit'],
        'customer-groups.view' => ['View Customer Groups', 'customer-groups', 'customers.view'],
        'customer-groups.create' => ['Create Customer Groups', 'customer-groups', 'customers.create'],
        'customer-groups.edit' => ['Edit Customer Groups', 'customer-groups', 'customers.edit'],
        'customer-groups.delete' => ['Delete Customer Groups', 'customer-groups', 'customers.delete'],
        'marketing.view' => ['View Marketing', 'marketing', null],
        'marketing.create' => ['Create Marketing', 'marketing', null],
        'marketing.edit' => ['Edit Marketing', 'marketing', null],
        'marketing.delete' => ['Delete Marketing', 'marketing', null],
        'pages.view' => ['View Pages', 'pages', null],
        'pages.create' => ['Create Pages', 'pages', null],
        'pages.edit' => ['Edit Pages', 'pages', null],
        'pages.delete' => ['Delete Pages', 'pages', null],
        'blog.view' => ['View Blog', 'blog', null],
        'blog.create' => ['Create Blog', 'blog', null],
        'blog.edit' => ['Edit Blog', 'blog', null],
        'blog.delete' => ['Delete Blog', 'blog', null],
        'menus.view' => ['View Menus', 'menus', null],
        'menus.create' => ['Create Menus', 'menus', null],
        'menus.edit' => ['Edit Menus', 'menus', null],
        'menus.delete' => ['Delete Menus', 'menus', null],
        'layout.view' => ['View Layout', 'layout', 'settings.view'],
        'layout.edit' => ['Edit Layout', 'layout', 'settings.edit'],
        'themes.view' => ['View Themes', 'themes', 'settings.view'],
        'themes.edit' => ['Edit Themes', 'themes', 'settings.edit'],
        'extensions.view' => ['View Extensions', 'extensions', 'settings.view'],
        'extensions.edit' => ['Edit Extensions', 'extensions', 'settings.edit'],
    ];

    /** Groups (or exact slugs) the shipped roles get among the new permissions. */
    private const DEFAULTS = [
        'store-manager' => [
            'couriers', 'reviews', 'brands', 'tags', 'attributes', 'media',
            'customers', 'customer-groups', 'marketing', 'pages', 'blog', 'menus',
        ],
        'staff' => ['couriers.view'],
    ];

    public function up(): void
    {
        $now = now();

        $existing = DB::table('permissions')->pluck('id', 'slug')->all();
        $created = [];

        foreach (self::NEW as $slug => [$name, $group]) {
            if (isset($existing[$slug])) {
                continue;
            }
            $created[$slug] = DB::table('permissions')->insertGetId([
                'name' => $name,
                'slug' => $slug,
                'group' => $group,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $roles = DB::table('roles')->pluck('id', 'slug')->all();

        foreach ($roles as $roleSlug => $roleId) {
            $grant = match (true) {
                $roleSlug === 'super-admin' => array_keys($created),
                isset(self::DEFAULTS[$roleSlug]) => $this->defaults(self::DEFAULTS[$roleSlug], array_keys($created)),
                default => $this->inherited((int) $roleId, array_keys($created)),
            };

            $this->grant((int) $roleId, array_map(fn ($slug) => $created[$slug], $grant));
        }

        $this->carryOverUsersWithoutRole($now);
    }

    public function down(): void
    {
        // The role assignments are not undone: taking full access away from
        // users who had it would lock them out, which is the one thing this
        // migration exists to prevent. The new permissions go, and with them
        // (by cascade) every grant of them.
        DB::table('permissions')->whereIn('slug', array_keys(self::NEW))->delete();
    }

    /** @return list<string> */
    private function defaults(array $entries, array $createdSlugs): array
    {
        return array_values(array_filter($createdSlugs, function (string $slug) use ($entries) {
            return in_array($slug, $entries, true) || in_array(strtok($slug, '.'), $entries, true);
        }));
    }

    /** @return list<string> */
    private function inherited(int $roleId, array $createdSlugs): array
    {
        $held = DB::table('role_permission')
            ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
            ->where('role_permission.role_id', $roleId)
            ->pluck('permissions.slug')
            ->all();

        return array_values(array_filter($createdSlugs, function (string $slug) use ($held) {
            $parent = self::NEW[$slug][2];

            return $parent !== null && in_array($parent, $held, true);
        }));
    }

    private function grant(int $roleId, array $permissionIds): void
    {
        if ($permissionIds === []) {
            return;
        }

        $already = DB::table('role_permission')->where('role_id', $roleId)->pluck('permission_id')->all();
        $rows = [];
        foreach (array_diff($permissionIds, $already) as $permissionId) {
            $rows[] = ['role_id' => $roleId, 'permission_id' => $permissionId];
        }
        if ($rows !== []) {
            DB::table('role_permission')->insert($rows);
        }
    }

    private function carryOverUsersWithoutRole($now): void
    {
        if (! DB::table('users')->whereNull('role_id')->exists()) {
            return;
        }

        $superAdminId = DB::table('roles')->where('slug', 'super-admin')->value('id');

        if ($superAdminId === null) {
            $superAdminId = DB::table('roles')->insertGetId([
                'name' => 'Super Admin',
                'slug' => 'super-admin',
                'description' => 'Full access to everything',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->grant((int) $superAdminId, DB::table('permissions')->pluck('id')->all());
        }

        DB::table('users')->whereNull('role_id')->update(['role_id' => $superAdminId]);
    }
};
