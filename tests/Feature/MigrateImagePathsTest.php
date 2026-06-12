<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Self-contained: builds only the tables the command touches, so it does not
 * depend on the full migration suite (which is currently sqlite-incompatible
 * via an unrelated geo_zone_entries migration).
 */
class MigrateImagePathsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        foreach (['brands', 'products'] as $t) {
            Schema::dropIfExists($t);
            Schema::create($t, function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('slug')->nullable();
                $table->string('image')->nullable();
            });
        }
        Schema::dropIfExists('product_images');
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('path')->nullable();
        });
    }

    private function disk()
    {
        return Storage::disk('public');
    }

    private function brand(string $image): int
    {
        return DB::table('brands')->insertGetId(['name' => 'Acme', 'slug' => 'acme', 'image' => $image]);
    }

    public function test_moves_catalog_file_and_rewrites_row(): void
    {
        $this->disk()->put('catalog/Acme/logo.png', 'x');
        $id = $this->brand('catalog/Acme/logo.png');

        $this->artisan('venta:migrate-image-paths --execute --i-have-a-backup')->assertSuccessful();

        $this->disk()->assertExists('brands/Acme/logo.png');
        $this->disk()->assertMissing('catalog/Acme/logo.png');
        $this->assertSame('brands/Acme/logo.png', DB::table('brands')->where('id', $id)->value('image'));
    }

    public function test_moves_thumbnail_sibling(): void
    {
        $this->disk()->put('catalog/Acme/c.png', 'x');
        $this->disk()->put('catalog/Acme/c_thumb.png', 't');
        $this->brand('catalog/Acme/c.png');

        $this->artisan('venta:migrate-image-paths --execute --i-have-a-backup')->assertSuccessful();

        $this->disk()->assertExists('brands/Acme/c.png');
        $this->disk()->assertExists('brands/Acme/c_thumb.png');
        $this->disk()->assertMissing('catalog/Acme/c_thumb.png');
    }

    public function test_heals_silent_failure_row(): void
    {
        // DB already says the new path, but the file sits at the legacy location.
        $this->disk()->put('catalog/Acme/b.png', 'x');
        $id = $this->brand('brands/Acme/b.png');

        $this->artisan('venta:migrate-image-paths --execute --i-have-a-backup')->assertSuccessful();

        $this->disk()->assertExists('brands/Acme/b.png');
        $this->disk()->assertMissing('catalog/Acme/b.png');
        $this->assertSame('brands/Acme/b.png', DB::table('brands')->where('id', $id)->value('image'));
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->disk()->put('catalog/Acme/d.png', 'x');
        $id = $this->brand('catalog/Acme/d.png');

        $this->artisan('venta:migrate-image-paths')->assertSuccessful(); // no --execute

        $this->disk()->assertExists('catalog/Acme/d.png');
        $this->disk()->assertMissing('brands/Acme/d.png');
        $this->assertSame('catalog/Acme/d.png', DB::table('brands')->where('id', $id)->value('image'));
    }

    public function test_execute_requires_backup_flag(): void
    {
        $this->disk()->put('catalog/Acme/e.png', 'x');
        $id = $this->brand('catalog/Acme/e.png');

        $this->artisan('venta:migrate-image-paths --execute')->assertFailed();

        $this->disk()->assertExists('catalog/Acme/e.png');
        $this->assertSame('catalog/Acme/e.png', DB::table('brands')->where('id', $id)->value('image'));
    }

    public function test_is_idempotent(): void
    {
        $this->disk()->put('catalog/Acme/f.png', 'x');
        $id = $this->brand('catalog/Acme/f.png');

        $this->artisan('venta:migrate-image-paths --execute --i-have-a-backup')->assertSuccessful();
        $this->artisan('venta:migrate-image-paths --execute --i-have-a-backup')->assertSuccessful();

        $this->disk()->assertExists('brands/Acme/f.png');
        $this->assertSame('brands/Acme/f.png', DB::table('brands')->where('id', $id)->value('image'));
    }

    public function test_products_subfolder_and_gallery(): void
    {
        $pid = DB::table('products')->insertGetId(['name' => 'Cable', 'slug' => 'cable', 'image' => 'catalog/Mogami/m.jpg']);
        DB::table('product_images')->insert(['product_id' => $pid, 'path' => 'catalog/Mogami/g.jpg']);
        $this->disk()->put('catalog/Mogami/m.jpg', 'x');
        $this->disk()->put('catalog/Mogami/g.jpg', 'y');

        $this->artisan('venta:migrate-image-paths --execute --i-have-a-backup')->assertSuccessful();

        $this->disk()->assertExists('products/Mogami/m.jpg');
        $this->disk()->assertExists('products/Mogami/g.jpg');
        $this->assertSame('products/Mogami/m.jpg', DB::table('products')->where('id', $pid)->value('image'));
        $this->assertSame('products/Mogami/g.jpg', DB::table('product_images')->where('product_id', $pid)->value('path'));
    }
}
