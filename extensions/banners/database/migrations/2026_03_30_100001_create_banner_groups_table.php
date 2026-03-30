<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banner_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('autoplay')->default(true);
            $table->integer('speed')->default(5000);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('banners', function (Blueprint $table) {
            $table->foreignId('banner_group_id')->nullable()->after('id')->constrained('banner_groups')->nullOnDelete();
        });

        // Migrate existing banners into a default "Homepage Hero" group
        if (DB::table('banners')->exists()) {
            $groupId = DB::table('banner_groups')->insertGetId([
                'name' => 'Homepage Hero',
                'slug' => 'homepage-hero',
                'autoplay' => true,
                'speed' => 5000,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('banners')->whereNull('banner_group_id')->update(['banner_group_id' => $groupId]);
        }
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropConstrainedForeignId('banner_group_id');
        });

        Schema::dropIfExists('banner_groups');
    }
};
