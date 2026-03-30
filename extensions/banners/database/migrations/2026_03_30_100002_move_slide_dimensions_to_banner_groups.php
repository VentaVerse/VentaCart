<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add dimension columns to banner_groups
        Schema::table('banner_groups', function (Blueprint $table) {
            $table->integer('height')->default(500)->after('speed');
            $table->integer('height_mobile')->default(400)->after('height');
            $table->boolean('full_width')->default(true)->after('height_mobile');
        });

        // Copy values from first slide of each group
        $groups = DB::table('banner_groups')->get();
        foreach ($groups as $group) {
            $firstSlide = DB::table('banners')
                ->where('banner_group_id', $group->id)
                ->orderBy('sort_order')
                ->first();

            if ($firstSlide) {
                DB::table('banner_groups')->where('id', $group->id)->update([
                    'height' => $firstSlide->height,
                    'height_mobile' => $firstSlide->height_mobile,
                    'full_width' => $firstSlide->full_width,
                ]);
            }
        }

        // Drop columns from banners
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn(['height', 'height_mobile', 'full_width']);
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->integer('height')->default(500);
            $table->integer('height_mobile')->default(400);
            $table->boolean('full_width')->default(true);
        });

        Schema::table('banner_groups', function (Blueprint $table) {
            $table->dropColumn(['height', 'height_mobile', 'full_width']);
        });
    }
};
