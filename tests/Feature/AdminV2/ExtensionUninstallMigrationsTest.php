<?php

namespace Tests\Feature\AdminV2;

use App\Services\ExtensionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Uninstalling a package undoes every migration it ran, not only the ones
 * in the store's last batch. A package updated over time migrates in
 * several batches, and other packages migrate in between.
 */
class ExtensionUninstallMigrationsTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing/extensions-uninstall');
        File::deleteDirectory($this->root);
        config(['extensions.path' => $this->root]);

        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($this->root));
    }

    private function package(string $name): void
    {
        File::ensureDirectoryExists("{$this->root}/{$name}/database/migrations");
        file_put_contents("{$this->root}/{$name}/extension.json", json_encode([
            'name' => $name,
            'title' => ucfirst($name),
            'version' => '1.0.0',
            'type' => 'feature',
        ]));
    }

    private function migration(string $name, string $file, string $table): void
    {
        file_put_contents("{$this->root}/{$name}/database/migrations/{$file}.php", <<<PHP
        <?php

        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::create('{$table}', function (Blueprint \$table) {
                    \$table->id();
                });
            }

            public function down(): void
            {
                Schema::dropIfExists('{$table}');
            }
        };
        PHP);
    }

    private function manager(): ExtensionManager
    {
        return new ExtensionManager();
    }

    public function test_uninstall_drops_tables_from_every_batch_and_leaves_other_packages_alone(): void
    {
        // The first release, in its own batch.
        $this->package('sample-pack');
        $this->migration('sample-pack', '2026_01_01_000000_create_sample_pack_slides_table', 'sample_pack_slides');
        $this->manager()->install('sample-pack');

        // Another package migrates in between.
        $this->package('other-pack');
        $this->migration('other-pack', '2026_01_02_000000_create_other_pack_items_table', 'other_pack_items');
        $this->manager()->install('other-pack');

        // The update brings a second table, in a later batch.
        file_put_contents("{$this->root}/sample-pack/extension.json", json_encode([
            'name' => 'sample-pack', 'title' => 'Sample pack', 'version' => '1.1.0', 'type' => 'feature',
        ]));
        $this->migration('sample-pack', '2026_01_03_000000_create_sample_pack_cards_table', 'sample_pack_cards');
        $this->manager()->install('sample-pack');

        $batches = DB::table('migrations')
            ->whereIn('migration', ['2026_01_01_000000_create_sample_pack_slides_table', '2026_01_03_000000_create_sample_pack_cards_table'])
            ->pluck('batch')->unique();
        $this->assertCount(2, $batches, 'The package migrated in two batches.');

        $this->manager()->uninstall('sample-pack');

        $this->assertFalse(Schema::hasTable('sample_pack_slides'));
        $this->assertFalse(Schema::hasTable('sample_pack_cards'));
        $this->assertDatabaseMissing('migrations', ['migration' => '2026_01_01_000000_create_sample_pack_slides_table']);
        $this->assertDatabaseMissing('migrations', ['migration' => '2026_01_03_000000_create_sample_pack_cards_table']);
        $this->assertDatabaseMissing('extensions', ['name' => 'sample-pack']);

        // The other package and core keep their tables.
        $this->assertTrue(Schema::hasTable('other_pack_items'));
        $this->assertDatabaseHas('migrations', ['migration' => '2026_01_02_000000_create_other_pack_items_table']);
        $this->assertTrue(Schema::hasTable('products'));
        $this->assertTrue(Schema::hasTable('settings'));
    }
}
