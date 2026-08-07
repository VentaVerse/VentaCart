<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extensions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('version');
            $table->boolean('enabled')->default(true);
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();
        });

        // Data continuity for EXISTING installs only: banners was hand-wired via
        // bootstrap/providers.php + composer.json before the extension kernel
        // existed, so its migrations (extensions/banners/database/migrations)
        // already ran long ago on every live deploy via the old unconditional
        // loadMigrationsFrom() wiring — banner_groups/banners tables already
        // exist there. Seed its registry row here so ExtensionManager keeps
        // booting it post-upgrade — without this it would vanish from
        // installed sites the moment this migration runs (no admin ever ran
        // an "install" step for it).
        //
        // A genuinely FRESH install has neither table: banners' migrations
        // were never run (nothing sweeps extensions/*/database/migrations
        // into a bare `php artisan migrate` until the kernel boots the
        // extension, which requires this very row to exist — install() is
        // the only thing that runs them for a new deploy). Seeding the row
        // unconditionally would claim banners is "enabled" while its tables
        // don't exist, so this check is load-bearing, not decorative: fresh
        // installs must get banners via the Modules page instead.
        //
        // This is NOT a general pattern: new extensions are installed via
        // the Modules page, not seeded in migrations. See CLAUDE.md /
        // no-install-specific-migrations guidance — this is the sanctioned
        // exception (continuity for a feature that predates the kernel).
        if (Schema::hasTable('banner_groups') || Schema::hasTable('banners')) {
            DB::table('extensions')->insert([
                'name' => 'banners',
                'version' => '1.0.0',
                'enabled' => true,
                'installed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('extensions');
    }
};
