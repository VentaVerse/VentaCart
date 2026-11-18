<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The media library keeps each image's width and height, and renaming or
 * moving images becomes a permission of its own, media.edit.
 *
 * media_files holds one row per image path. Rows are written when an image
 * is uploaded and filled in the first time an older image is browsed, so a
 * store that already has thousands of images needs no backfill here.
 *
 * The path is compared byte for byte. MySQL and MariaDB compare text without
 * regard to case by default, and a store can hold media/products/Demo and
 * media/products/demo side by side: each needs its own row. SQLite already
 * compares exactly.
 *
 * media.edit goes to every role that can already add images, since those
 * are the people who file them. Safe on a fresh install (no roles yet; the
 * seeder grants the shipped roles their defaults) and safe to run again: an
 * existing table, row or grant is left alone. The slug is written out here
 * rather than read from App\Support\AdminPermissions, so this migration does
 * the same thing whenever it runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('media_files')) {
            $binary = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);

            Schema::create('media_files', function (Blueprint $table) use ($binary) {
                $table->id();
                $path = $table->string('path', 512);
                if ($binary) {
                    // Named together: a store whose connection uses the older
                    // three-byte utf8 would refuse a utf8mb4 collation alone.
                    $path->charset('utf8mb4')->collation('utf8mb4_bin');
                }
                $path->unique();
                $table->unsignedInteger('width');
                $table->unsignedInteger('height');
                $table->timestamps();
            });
        }

        $this->addEditPermission();
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');

        if (! Schema::hasTable('permissions')) {
            return;
        }

        $id = DB::table('permissions')->where('slug', 'media.edit')->value('id');
        if ($id !== null) {
            DB::table('role_permission')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }
    }

    private function addEditPermission(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $now = now();
        $id = DB::table('permissions')->where('slug', 'media.edit')->value('id');

        if ($id === null) {
            $id = DB::table('permissions')->insertGetId([
                'name' => 'Edit Media',
                'slug' => 'media.edit',
                'group' => 'media',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $roles = DB::table('role_permission')
            ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
            ->where('permissions.slug', 'media.create')
            ->pluck('role_permission.role_id')
            ->all();

        $already = DB::table('role_permission')->where('permission_id', $id)->pluck('role_id')->all();

        $rows = [];
        foreach (array_diff(array_unique($roles), $already) as $roleId) {
            $rows[] = ['role_id' => $roleId, 'permission_id' => $id];
        }

        if ($rows !== []) {
            DB::table('role_permission')->insert($rows);
        }
    }
};
