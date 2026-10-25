<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Uploading a theme or extension becomes a permission of its own,
 * extensions.upload, that a super admin can grant to a user group.
 *
 * Super Admin gets it (it holds every permission anyway). No other role
 * does: a package runs PHP on the server, so who may upload one is a
 * decision for the store, never a default.
 *
 * Safe on a fresh install (the seeder makes the row as well) and safe to run
 * again: an existing row and an existing grant are left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('permissions')) {
            return;
        }

        $id = DB::table('permissions')->where('slug', 'extensions.upload')->value('id');
        if ($id === null) {
            $id = DB::table('permissions')->insertGetId([
                'name' => 'Upload Extensions',
                'slug' => 'extensions.upload',
                'group' => 'extensions',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $superAdmin = DB::table('roles')->where('slug', 'super-admin')->value('id');
        if ($superAdmin !== null && ! DB::table('role_permission')->where('role_id', $superAdmin)->where('permission_id', $id)->exists()) {
            DB::table('role_permission')->insert(['role_id' => $superAdmin, 'permission_id' => $id]);
        }
    }

    public function down(): void
    {
        $id = DB::table('permissions')->where('slug', 'extensions.upload')->value('id');
        if ($id !== null) {
            DB::table('role_permission')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }
    }
};
