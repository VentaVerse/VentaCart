<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Merging folders that are the same folder wearing different punctuation.
 *
 * The migrated catalogue grew six pairs: Wampler and wampler, Friedman and
 * friedman, "TC Electronic" and tc_electronic, and three more. Nothing in the
 * admin distinguishes them, so uploads land in whichever one the merchant
 * happened to open and the library forks a little further every week.
 */
class MediaTidyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        Storage::fake('public');
    }

    private function putFile(string $path): void
    {
        Storage::disk('public')->put($path, 'bytes');
    }

    private function product(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'store_id' => 1, 'name' => 'A', 'slug' => 'a-' . uniqid(), 'price' => 1, 'quantity' => 1,
        ], $attributes));
    }

    public function test_it_reports_without_changing_anything(): void
    {
        $this->putFile('products/Wampler/one.jpg');
        $this->putFile('products/wampler/two.jpg');

        $this->artisan('media:tidy')->assertSuccessful();

        // Both folders still stand: the default is a dry run.
        Storage::disk('public')->assertExists('products/Wampler/one.jpg');
        Storage::disk('public')->assertExists('products/wampler/two.jpg');
    }

    public function test_apply_merges_into_the_folder_that_already_holds_more(): void
    {
        $this->putFile('products/Wampler/one.jpg');
        $this->putFile('products/Wampler/two.jpg');
        $this->putFile('products/wampler/three.jpg');

        $this->artisan('media:tidy --apply')->assertSuccessful();

        Storage::disk('public')->assertExists('products/Wampler/three.jpg');
        $this->assertFalse(Storage::disk('public')->exists('products/wampler'));
    }

    /** A merge that leaves the database behind breaks every product in it. */
    public function test_every_stored_reference_moves_with_the_files(): void
    {
        $this->putFile('products/Wampler/keep.jpg');
        $this->putFile('products/wampler/moved.jpg');

        $main = $this->product(['image' => 'products/wampler/moved.jpg']);
        $gallery = $this->product();
        ProductImage::create(['product_id' => $gallery->id, 'path' => 'products/wampler/moved.jpg', 'sort_order' => 0]);
        $variant = $gallery->variants()->create([
            'sku' => 'V1', 'price' => 1, 'quantity' => 1, 'is_active' => true,
            'image' => 'products/wampler/moved.jpg',
        ]);

        $this->artisan('media:tidy --apply')->assertSuccessful();

        $this->assertSame('products/Wampler/moved.jpg', $main->fresh()->image);
        $this->assertSame('products/Wampler/moved.jpg', ProductImage::first()->path);
        $this->assertSame('products/Wampler/moved.jpg', $variant->fresh()->image, 'a variant photo moves too');
    }

    /**
     * Two different files sharing a name is the one case where a merge could
     * destroy something a product points at.
     */
    public function test_a_name_collision_keeps_both_files(): void
    {
        $this->putFile('products/Wampler/same.jpg');
        $this->putFile('products/Wampler/filler.jpg');
        $this->putFile('products/wampler/same.jpg');

        $this->artisan('media:tidy --apply')->assertSuccessful();

        $files = Storage::disk('public')->files('products/Wampler');

        $this->assertCount(3, $files, 'the incoming file was renamed rather than overwriting');
        Storage::disk('public')->assertExists('products/Wampler/same.jpg');
    }

    public function test_punctuation_and_spacing_collapse_the_same_way_as_case(): void
    {
        $this->putFile('products/TC Electronic/a.jpg');
        $this->putFile('products/TC Electronic/b.jpg');
        $this->putFile('products/tc_electronic/c.jpg');

        $this->artisan('media:tidy --apply')->assertSuccessful();

        Storage::disk('public')->assertExists('products/TC Electronic/c.jpg');
        $this->assertFalse(Storage::disk('public')->exists('products/tc_electronic'));
    }

    public function test_folders_that_are_genuinely_different_are_left_alone(): void
    {
        $this->putFile('products/Wampler/a.jpg');
        $this->putFile('products/Boss/b.jpg');

        $this->artisan('media:tidy --apply')->assertSuccessful();

        Storage::disk('public')->assertExists('products/Wampler/a.jpg');
        Storage::disk('public')->assertExists('products/Boss/b.jpg');
    }
}
