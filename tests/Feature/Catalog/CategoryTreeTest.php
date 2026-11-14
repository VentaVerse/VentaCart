<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Support\CategoryTree;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Categories nest as deep as the merchant needs: Guitars > Electric Guitars
 * > Strings > Phosphor Bronze. The parent is picked by searching full paths,
 * a category can never sit under itself, a category page holds its whole
 * branch, and the list enables, disables or deletes several at once.
 */
class CategoryTreeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([StoreSeeder::class, RolePermissionSeeder::class]);
        $this->actingAs(User::factory()->role('store-manager')->create());
    }

    private function make(string $name, ?Category $parent = null): Category
    {
        $this->post(route('admin.categories.store'), [
            'store_id' => 1, 'name' => $name, 'parent_id' => $parent?->id, 'is_active' => '1',
        ])->assertSessionHasNoErrors();

        return Category::where('name', $name)->sole();
    }

    /** @return array{Category, Category, Category, Category} */
    private function guitars(): array
    {
        $guitars = $this->make('Guitars');
        $electric = $this->make('Electric Guitars', $guitars);
        $strings = $this->make('Strings', $electric);
        $bronze = $this->make('Phosphor Bronze', $strings);

        return [$guitars, $electric, $strings, $bronze];
    }

    public function test_the_parent_search_offers_every_level_by_its_full_path(): void
    {
        $this->guitars();

        $this->assertSame([
            'Guitars',
            'Guitars > Electric Guitars',
            'Guitars > Electric Guitars > Strings',
            'Guitars > Electric Guitars > Strings > Phosphor Bronze',
        ], array_column(CategoryTree::parentOptions(), 'name'));

        $this->get(route('admin.categories.create'))->assertOk()
            ->assertSee('role="combobox"', false)
            ->assertViewHas('parentOptions', fn (array $options) => in_array('Guitars > Electric Guitars > Strings', array_column($options, 'name'), true));
    }

    public function test_a_category_cannot_move_under_itself_or_its_own_subcategory(): void
    {
        [$guitars, $electric, $strings] = $this->guitars();

        $this->assertNotContains($strings->id, array_column(CategoryTree::parentOptions($electric->id), 'id'));
        $this->assertNotContains($electric->id, array_column(CategoryTree::parentOptions($electric->id), 'id'));

        $this->put(route('admin.categories.update', $electric), [
            'store_id' => 1, 'name' => 'Electric Guitars', 'parent_id' => $strings->id, 'is_active' => '1',
        ])->assertSessionHasErrors('parent_id');
        $this->assertSame($guitars->id, $electric->fresh()->parent_id);
    }

    public function test_a_category_page_holds_its_whole_branch(): void
    {
        [$guitars, , , $bronze] = $this->guitars();
        $product = Product::create(['store_id' => 1, 'name' => 'Demo Strings Set', 'slug' => 'demo-strings-set', 'sku' => 'DEMO-001', 'price' => 450, 'quantity' => 5, 'is_active' => true]);
        $product->categories()->attach($bronze->id);

        $this->assertContains($bronze->id, $guitars->fresh()->selfAndDescendantIds());

        $this->withoutVite();
        $this->get('/category/' . $guitars->slug)->assertOk()->assertSee('Demo Strings Set');
    }

    public function test_the_branch_follows_a_move_without_waiting_for_the_cache(): void
    {
        [$guitars, , $strings] = $this->guitars();
        $guitars->selfAndDescendantIds();
        $bass = $this->make('Bass Guitars');

        $this->put(route('admin.categories.update', $strings), [
            'store_id' => 1, 'name' => 'Strings', 'parent_id' => $bass->id, 'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertNotContains($strings->id, $guitars->fresh()->selfAndDescendantIds());
        $this->assertContains($strings->id, $bass->fresh()->selfAndDescendantIds());
    }

    public function test_the_list_draws_every_level_with_tick_boxes(): void
    {
        $this->guitars();

        $this->get(route('admin.categories.index'))->assertOk()
            ->assertSee('Phosphor Bronze')
            ->assertSee('x-model.number="selected"', false)
            ->assertSee(route('admin.categories.bulk-status'), false)
            ->assertSee(route('admin.categories.bulk-delete'), false);
    }

    public function test_ticked_categories_are_disabled_and_enabled_together(): void
    {
        [$guitars, $electric] = $this->guitars();

        $this->post(route('admin.categories.bulk-status'), ['active' => '0', 'ids' => [$guitars->id, $electric->id]])
            ->assertRedirect(route('admin.categories.index'));
        $this->assertFalse($guitars->fresh()->is_active);
        $this->assertFalse($electric->fresh()->is_active);

        $this->post(route('admin.categories.bulk-status'), ['active' => '1', 'ids' => [$guitars->id]]);
        $this->assertTrue($guitars->fresh()->is_active);
        $this->assertFalse($electric->fresh()->is_active);
    }

    public function test_deleting_ticked_categories_moves_what_stays_to_its_nearest_surviving_parent(): void
    {
        [$guitars, $electric, $strings, $bronze] = $this->guitars();

        $this->post(route('admin.categories.bulk-delete'), ['ids' => [$electric->id, $strings->id]])
            ->assertRedirect(route('admin.categories.index'));

        $this->assertNull($electric->fresh());
        $this->assertNull($strings->fresh());
        $this->assertSame($guitars->id, $bronze->fresh()->parent_id);
    }

    public function test_bulk_delete_needs_the_right_to_delete(): void
    {
        [$guitars] = $this->guitars();
        $role = Role::create(['name' => 'Catalog editor', 'slug' => 'catalog-editor']);
        $role->permissions()->sync(Permission::whereIn('slug', ['categories.view', 'categories.edit'])->pluck('id'));
        $this->actingAs(User::factory()->role('catalog-editor')->create());

        $this->post(route('admin.categories.bulk-status'), ['active' => '0', 'ids' => [$guitars->id]])->assertRedirect();
        $this->post(route('admin.categories.bulk-delete'), ['ids' => [$guitars->id]])->assertForbidden();
        $this->assertNotNull($guitars->fresh());
    }

    public function test_a_loop_in_the_data_does_not_hang_the_tree(): void
    {
        [$guitars, $electric] = $this->guitars();
        Category::whereKey($guitars->id)->update(['parent_id' => $electric->id]);

        $this->assertNotEmpty(CategoryTree::branch($guitars->id));
        CategoryTree::options();
        $this->get(route('admin.categories.index'))->assertOk();
    }
}
