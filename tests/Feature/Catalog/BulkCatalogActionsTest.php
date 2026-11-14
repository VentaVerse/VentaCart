<?php

namespace Tests\Feature\Catalog;

use App\Models\Brand;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ticking rows on the Products and Brands lists and acting on all of them:
 * disable, enable and delete. Each bulk action must do exactly what the
 * single-row action does, only to the ticked rows, and stay behind the same
 * permissions.
 */
class BulkCatalogActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Demo Store']);
    }

    private function product(string $name, array $extra = []): Product
    {
        return Product::create(array_merge([
            'store_id' => 1, 'name' => $name, 'slug' => \Illuminate\Support\Str::slug($name),
            'price' => 100, 'is_active' => true,
        ], $extra));
    }

    private function brand(string $name): Brand
    {
        return Brand::create([
            'store_id' => 1, 'name' => $name, 'slug' => \Illuminate\Support\Str::slug($name), 'is_active' => true,
        ]);
    }

    public function test_the_lists_draw_a_tick_box_per_row_and_the_bulk_actions(): void
    {
        $this->actingAs(User::factory()->create());
        $this->product('Demo Mug');
        $this->brand('Demo Brand');

        $this->get(route('admin.products.index'))->assertOk()
            ->assertSee('aria-label="Select Demo Mug"', false)
            ->assertSee('Select every product on this page')
            ->assertSee(route('admin.products.bulk-status'))
            ->assertSee(route('admin.products.bulk-delete'));

        $this->get(route('admin.brands.index'))->assertOk()
            ->assertSee('aria-label="Select Demo Brand"', false)
            ->assertSee('Select every brand on this page')
            ->assertSee(route('admin.brands.bulk-status'))
            ->assertSee(route('admin.brands.bulk-delete'));
    }

    public function test_bulk_disable_and_enable_touch_only_the_ticked_products(): void
    {
        $this->actingAs(User::factory()->create());
        $a = $this->product('Demo A');
        $b = $this->product('Demo B');
        $c = $this->product('Demo C');

        $this->post(route('admin.products.bulk-status'), ['ids' => [$a->id, $b->id], 'active' => 0])
            ->assertRedirect()
            ->assertSessionHas('success', '2 products disabled.');

        $this->assertFalse($a->fresh()->is_active);
        $this->assertFalse($b->fresh()->is_active);
        $this->assertTrue($c->fresh()->is_active);

        $this->post(route('admin.products.bulk-status'), ['ids' => [$a->id], 'active' => 1])
            ->assertSessionHas('success', '1 product enabled.');
        $this->assertTrue($a->fresh()->is_active);
    }

    public function test_bulk_delete_removes_only_the_ticked_products(): void
    {
        $this->actingAs(User::factory()->create());
        $a = $this->product('Demo A');
        $b = $this->product('Demo B');
        $keep = $this->product('Demo Keep');

        $this->post(route('admin.products.bulk-delete'), ['ids' => [$a->id, $b->id]])
            ->assertSessionHas('success', '2 products deleted.');

        $this->assertDatabaseMissing('products', ['id' => $a->id]);
        $this->assertDatabaseMissing('products', ['id' => $b->id]);
        $this->assertDatabaseHas('products', ['id' => $keep->id]);
    }

    public function test_bulk_brand_actions_disable_and_delete_leaving_products_unbranded(): void
    {
        $this->actingAs(User::factory()->create());
        $gone = $this->brand('Demo Gone');
        $off = $this->brand('Demo Off');
        $keep = $this->brand('Demo Keep');
        $product = $this->product('Demo Branded', ['brand_id' => $gone->id]);

        $this->post(route('admin.brands.bulk-status'), ['ids' => [$off->id], 'active' => 0])
            ->assertSessionHas('success', '1 brand disabled.');
        $this->assertFalse($off->fresh()->is_active);
        $this->assertTrue($keep->fresh()->is_active);

        $this->post(route('admin.brands.bulk-delete'), ['ids' => [$gone->id]])
            ->assertSessionHas('success', '1 brand deleted.');
        $this->assertDatabaseMissing('brands', ['id' => $gone->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'brand_id' => null]);
        $this->assertDatabaseHas('brands', ['id' => $keep->id]);
    }

    public function test_a_selection_must_be_present_and_bounded(): void
    {
        $this->actingAs(User::factory()->create());
        $a = $this->product('Demo A');

        $this->post(route('admin.products.bulk-delete'), [])->assertSessionHasErrors('ids');
        $this->post(route('admin.products.bulk-delete'), ['ids' => range(1, 101)])->assertSessionHasErrors('ids');
        $this->post(route('admin.brands.bulk-status'), ['ids' => ['x'], 'active' => 0])->assertSessionHasErrors('ids.0');

        $this->assertDatabaseHas('products', ['id' => $a->id]);
    }

    public function test_an_admin_without_catalog_rights_cannot_run_bulk_actions(): void
    {
        $this->actingAs(User::factory()->role('clerk')->create());
        $a = $this->product('Demo A');
        $brand = $this->brand('Demo Brand');

        $this->post(route('admin.products.bulk-delete'), ['ids' => [$a->id]])->assertForbidden();
        $this->post(route('admin.products.bulk-status'), ['ids' => [$a->id], 'active' => 0])->assertForbidden();
        $this->post(route('admin.brands.bulk-delete'), ['ids' => [$brand->id]])->assertForbidden();
        $this->post(route('admin.brands.bulk-status'), ['ids' => [$brand->id], 'active' => 0])->assertForbidden();

        $this->assertTrue($a->fresh()->is_active);
        $this->assertDatabaseHas('brands', ['id' => $brand->id]);
    }
}
