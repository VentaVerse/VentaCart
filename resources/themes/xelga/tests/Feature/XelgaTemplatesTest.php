<?php

namespace Themes\Xelga\Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Themes\Xelga\Tests\XelgaTestHelpers;
use Themes\Xelga\Catalog\Catalog;
use Themes\Xelga\Documents\Assignment;
use Themes\Xelga\Documents\Document;
use Themes\Xelga\Documents\Revision;
use Themes\Xelga\Http\BootPayload;

require_once __DIR__ . '/../XelgaTestHelpers.php';

/**
 * Special layouts (custom templates) and the products, categories, brands,
 * pages and posts that use them: making, renaming, deleting, assigning, and
 * how they take part in drafts, publish and discard.
 */
class XelgaTemplatesTest extends TestCase
{
    use RefreshDatabase;
    use XelgaTestHelpers;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installXelga();
        $this->store = Store::firstOrCreate(['code' => 'demo'], ['name' => 'Demo Store']);
    }

    private function product(string $name, bool $active = true): Product
    {
        return Product::create(['store_id' => $this->store->id, 'name' => $name, 'slug' => Str::slug($name) . '-' . uniqid(), 'price' => 1250, 'is_active' => $active]);
    }

    private function category(string $name): Category
    {
        return Category::create(['store_id' => $this->store->id, 'name' => $name, 'slug' => Str::slug($name) . '-' . uniqid(), 'is_active' => true]);
    }

    private function make(string $label, string $pageType = 'product', ?string $from = null)
    {
        return $this->actingAs($this->admin())->postJson(route('admin.xelga.templates.store'), ['page_type' => $pageType, 'label' => $label, 'from' => $from]);
    }

    private function assign(string $type, array $ids, ?string $handle)
    {
        return $this->actingAs($this->admin())->putJson(route('admin.xelga.assignments.update'), ['subject_type' => $type, 'subject_ids' => $ids, 'handle' => $handle]);
    }

    /* --------------------------------------------------------------------
       Access, CSRF and limits
       -------------------------------------------------------------------- */

    public function test_every_template_route_needs_the_theme_permission(): void
    {
        $this->make('Gift guide')->assertCreated();
        $product = $this->product('Linen Shirt Dress');
        $staff = $this->staff();

        $this->actingAs($staff)->getJson(route('admin.xelga.templates.index'))->assertForbidden();
        $this->actingAs($staff)->postJson(route('admin.xelga.templates.store'), ['page_type' => 'product', 'label' => 'Not allowed'])->assertForbidden();
        $this->actingAs($staff)->patchJson(route('admin.xelga.templates.update', ['handle' => 'product.gift-guide']), ['label' => 'Not allowed'])->assertForbidden();
        $this->actingAs($staff)->deleteJson(route('admin.xelga.templates.destroy', ['handle' => 'product.gift-guide']))->assertForbidden();
        $this->actingAs($staff)->putJson(route('admin.xelga.assignments.update'), ['subject_type' => 'product', 'subject_ids' => [$product->id], 'handle' => 'product.gift-guide'])->assertForbidden();

        $this->assertSame('Gift guide', Document::where('handle', 'product.gift-guide')->value('label'));
        $this->assertSame(0, Assignment::count());

        // A theme editor who is not a super admin may use them.
        $this->actingAs($this->themeEditor())->getJson(route('admin.xelga.templates.index'))->assertOk();
    }

    public function test_writes_without_the_csrf_token_are_refused(): void
    {
        $this->make('Gift guide')->assertCreated();
        $product = $this->product('Linen Shirt Dress');
        $admin = $this->admin();

        // Tests skip the CSRF check unless the app runs outside the testing environment.
        $env = $this->app['env'];
        $this->app['env'] = 'local';
        try {
            $this->actingAs($admin)->postJson(route('admin.xelga.templates.store'), ['page_type' => 'product', 'label' => 'No token'])->assertStatus(419);
            $this->actingAs($admin)->patchJson(route('admin.xelga.templates.update', ['handle' => 'product.gift-guide']), ['label' => 'No token'])->assertStatus(419);
            $this->actingAs($admin)->deleteJson(route('admin.xelga.templates.destroy', ['handle' => 'product.gift-guide']))->assertStatus(419);
            $this->actingAs($admin)->putJson(route('admin.xelga.assignments.update'), ['subject_type' => 'product', 'subject_ids' => [$product->id], 'handle' => 'product.gift-guide'])->assertStatus(419);
        } finally {
            $this->app['env'] = $env;
        }

        $this->assertSame('Gift guide', Document::where('handle', 'product.gift-guide')->value('label'));
        $this->assertSame(0, Assignment::count());
    }

    public function test_template_writes_and_assignments_are_limited_per_admin(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->make("Layout {$i}")->assertCreated();
        }
        $this->make('One too many')->assertStatus(429);

        $product = $this->product('Linen Shirt Dress');
        for ($i = 0; $i < 60; $i++) {
            $this->assign('product', [$product->id], 'product.layout-1')->assertOk();
        }
        $this->assign('product', [$product->id], 'product.layout-1')->assertStatus(429);
    }

    /* --------------------------------------------------------------------
       Making, renaming, deleting
       -------------------------------------------------------------------- */

    public function test_a_layout_starts_as_a_draft_copy_of_its_page_template(): void
    {
        $product = $this->defaults('product');
        $res = $this->make('Gift guide')->assertCreated()
            ->assertJsonPath('data.template.handle', 'product.gift-guide')
            ->assertJsonPath('data.template.label', 'Gift guide')
            ->assertJsonPath('data.template.count', 0)
            ->assertJsonPath('data.document.has_draft', true)
            ->assertJsonPath('data.document.is_default', true);
        $this->assertSame(1, $res->json('data.drafts')['product.gift-guide']);

        $row = Document::where('handle', 'product.gift-guide')->firstOrFail();
        $this->assertSame('product', $row->page_type);
        $this->assertNull($row->published);
        $this->assertSame(1, $row->draft_version);
        $kept = array_values(array_filter($product['order'] ?? [], fn ($id) => Catalog::section($product['sections'][$id]['type'])?->allowedIn('product.gift-guide', 'template', 'product')));
        $this->assertSame($kept, $row->draft['order']);
        $this->assertCount(count($product['order'] ?? []) - count($kept), $res->json('data.left_out'));

        // The same name again is refused; another name that makes the same handle gets a number.
        $this->make('gift GUIDE')->assertStatus(422)->assertJsonPath('errors.label.0', 'A layout with that name already exists.');
        $this->make('  Gift   guide ')->assertStatus(422);
        $this->make('Gift guide!')->assertCreated()->assertJsonPath('data.template.handle', 'product.gift-guide-2');
        $this->make('Gift-guide')->assertCreated()->assertJsonPath('data.template.handle', 'product.gift-guide-3');
    }

    public function test_a_layout_can_start_from_another_layout_and_its_draft(): void
    {
        $this->make('Gift guide')->assertCreated();
        $content = Document::where('handle', 'product.gift-guide')->value('draft');
        $first = $content['order'][0] ?? null;
        if ($first !== null) {
            $content['sections'][$first]['label'] = 'Changed in the gift guide';
        }
        $this->saveDraft($this->admin(), 'product.gift-guide', $content, 1)->assertOk();

        $this->make('Gift guide for him', 'product', 'product.gift-guide')->assertCreated();
        $this->assertSame(Document::where('handle', 'product.gift-guide')->value('draft')['order'], Document::where('handle', 'product.gift-guide-for-him')->value('draft')['order']);
        if ($first !== null) {
            $this->assertSame('Changed in the gift guide', Document::where('handle', 'product.gift-guide-for-him')->value('draft')['sections'][$first]['label']);
        }
    }

    public function test_layouts_are_only_for_page_types_that_have_them_and_copy_only_their_own_page(): void
    {
        $this->make('Holiday', 'home')->assertStatus(422)->assertJsonValidationErrors('page_type');
        $this->make('Holiday', 'checkout')->assertStatus(422);
        $this->make('')->assertStatus(422)->assertJsonPath('errors.label.0', 'Give the layout a name.');
        $this->make(str_repeat('a', 61))->assertStatus(422);
        $this->make('Holiday', 'product', 'category')->assertStatus(422)->assertJsonValidationErrors('from');

        $this->make('Sale', 'category')->assertCreated();
        $this->make('Holiday', 'product', 'category.sale')->assertStatus(422);
        $this->make('Holiday', 'product', 'product.not-made')->assertNotFound();
        $this->make('Holiday', 'product', '../settings')->assertStatus(422);

        // A name made only of symbols still gets a usable handle.
        $this->make('★★★')->assertCreated()->assertJsonPath('data.template.handle', 'product.layout');
        $this->make(str_repeat('Long name ', 6))->assertCreated();
        $long = Document::where('label', trim(str_repeat('Long name ', 6)))->value('handle');
        $this->assertLessThanOrEqual(48, strlen(explode('.', $long, 2)[1]));
        $this->assertTrue(\Themes\Xelga\Rendering\TemplateResolver::isCustomOf($long, 'product'));
    }

    public function test_a_layout_can_be_renamed_but_not_onto_another_name(): void
    {
        $this->make('Gift guide')->assertCreated();
        $this->make('Clearance')->assertCreated();
        $admin = $this->admin();

        $this->actingAs($admin)->patchJson(route('admin.xelga.templates.update', ['handle' => 'product.gift-guide']), ['label' => 'Gifts for her'])
            ->assertOk()->assertJsonPath('data.template.label', 'Gifts for her')->assertJsonPath('data.template.handle', 'product.gift-guide');
        $this->actingAs($admin)->patchJson(route('admin.xelga.templates.update', ['handle' => 'product.gift-guide']), ['label' => 'clearance'])
            ->assertStatus(422)->assertJsonPath('errors.label.0', 'A layout with that name already exists.');
        $this->actingAs($admin)->patchJson(route('admin.xelga.templates.update', ['handle' => 'product.gift-guide']), ['label' => '   '])->assertStatus(422);
        $this->actingAs($admin)->patchJson(route('admin.xelga.templates.update', ['handle' => 'product.not-made']), ['label' => 'X'])->assertNotFound();
        // Standard templates are not special layouts.
        $this->assertContains($this->actingAs($admin)->patchJson('/admin/extensions/theme/xelga/templates/product', ['label' => 'X'])->status(), [404, 405]);
    }

    public function test_deleting_a_layout_removes_its_assignments_and_revisions(): void
    {
        $this->make('Gift guide')->assertCreated();
        $a = $this->product('Linen Shirt Dress');
        $b = $this->product('Merino Cardigan');
        $this->assign('product', [$a->id, $b->id], 'product.gift-guide')->assertOk();
        $this->publish($this->admin(), ['product.gift-guide' => 1])->assertOk();
        $id = Document::where('handle', 'product.gift-guide')->value('id');
        $this->assertSame(1, Revision::where('document_id', $id)->count());

        $this->actingAs($this->admin())->deleteJson(route('admin.xelga.templates.destroy', ['handle' => 'product.gift-guide']))
            ->assertOk()->assertJsonPath('data.cleared', 2)->assertJsonPath('data.templates', []);

        $this->assertNull(Document::where('handle', 'product.gift-guide')->first());
        $this->assertSame(0, Assignment::count());
        $this->assertSame(0, Revision::where('document_id', $id)->count());
        $this->actingAs($this->admin())->getJson(route('admin.xelga.documents.show', ['handle' => 'product.gift-guide']))->assertNotFound();
        $this->actingAs($this->admin())->deleteJson(route('admin.xelga.templates.destroy', ['handle' => 'product.gift-guide']))->assertNotFound();
        $this->assertContains($this->actingAs($this->admin())->deleteJson('/admin/extensions/theme/xelga/templates/home')->status(), [404, 405]);
    }

    /* --------------------------------------------------------------------
       Assignments
       -------------------------------------------------------------------- */

    public function test_each_subject_uses_one_layout_and_moving_it_says_so(): void
    {
        $this->make('Gift guide')->assertCreated();
        $this->make('Clearance')->assertCreated();
        $a = $this->product('Linen Shirt Dress');
        $b = $this->product('Merino Cardigan');

        $this->assign('product', [$a->id, $b->id], 'product.gift-guide')->assertOk()
            ->assertJsonPath('data.assigned', [$a->id, $b->id])->assertJsonPath('data.moved', []);

        $res = $this->assign('product', [$b->id], 'product.clearance')->assertOk()
            ->assertJsonPath('data.moved.0.subject_id', $b->id)
            ->assertJsonPath('data.moved.0.from', 'product.gift-guide')
            ->assertJsonPath('data.moved.0.from_label', 'Gift guide');
        $counts = collect($res->json('data.templates'))->pluck('count', 'handle')->all();
        $this->assertSame(['product.clearance' => 1, 'product.gift-guide' => 1], $counts);
        $this->assertSame(2, Assignment::count());
        $this->assertSame([$b->id], collect($res->json('data.templates'))->firstWhere('handle', 'product.clearance')['assigned']);

        // Assigning it again where it already is changes nothing and moves nothing.
        $this->assign('product', [$b->id], 'product.clearance')->assertOk()->assertJsonPath('data.moved', []);
        $this->assertSame(2, Assignment::count());

        // Clearing takes it back to the page's own template.
        $this->assign('product', [$a->id], null)->assertOk()->assertJsonPath('data.cleared', [$a->id]);
        $this->assertSame([$b->id], Assignment::pluck('subject_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_assignments_are_checked_against_core_and_the_page_type(): void
    {
        $this->make('Gift guide')->assertCreated();
        $this->make('Sale', 'category')->assertCreated();
        $product = $this->product('Linen Shirt Dress');
        $category = $this->category('Linen');

        $this->assign('product', [$product->id, 999999], 'product.gift-guide')->assertStatus(422)->assertJsonValidationErrors('subject_ids');
        $this->assign('category', [$category->id], 'product.gift-guide')->assertStatus(422)
            ->assertJsonPath('errors.handle.0', 'A product layout cannot be used for a category.');
        $this->assign('product', [$product->id], 'category.sale')->assertStatus(422);
        $this->assign('product', [$product->id], 'product')->assertStatus(422);
        $this->assign('product', [$product->id], 'product.not-made')->assertNotFound();
        $this->assign('order', [$product->id], 'product.gift-guide')->assertStatus(422)->assertJsonValidationErrors('subject_type');
        $this->assign('product', [], 'product.gift-guide')->assertStatus(422);
        $this->assign('product', range(1, 51), 'product.gift-guide')->assertStatus(422);
        $this->assign('product', ['1 or 1=1'], 'product.gift-guide')->assertStatus(422);
        $this->actingAs($this->admin())->putJson(route('admin.xelga.assignments.update'), ['subject_type' => 'product', 'subject_ids' => [$product->id]])->assertStatus(422);
        $this->assertSame(0, Assignment::count());

        $this->assign('category', [$category->id], 'category.sale')->assertOk();
        $this->assertSame(1, Assignment::count());
    }

    /* --------------------------------------------------------------------
       Drafts, publish, discard
       -------------------------------------------------------------------- */

    public function test_a_layout_saves_publishes_and_only_exists_once_made(): void
    {
        $admin = $this->admin();
        $this->saveDraft($admin, 'product.gift-guide', $this->defaults('product'), 0)->assertNotFound();
        $this->actingAs($admin)->getJson(route('admin.xelga.documents.show', ['handle' => 'product.gift-guide']))->assertNotFound();
        $this->assertSame(0, Document::count());

        $this->make('Gift guide')->assertCreated();
        $content = Document::where('handle', 'product.gift-guide')->value('draft');
        $this->saveDraft($admin, 'product.gift-guide', $content, 1)->assertOk()->assertJsonPath('data.document.draft_version', 2);
        $this->actingAs($admin)->getJson(route('admin.xelga.documents.show', ['handle' => 'product.gift-guide']))
            ->assertOk()->assertJsonPath('data.label', 'Gift guide')->assertJsonPath('data.page_type', 'product');

        // Publish must name every draft, the layout included.
        $this->publish($admin, [])->assertStatus(409);
        $published = $this->publish($admin, ['product.gift-guide' => 2])->assertOk();
        $this->assertSame(1, $published->json('data.documents')['product.gift-guide']['published_version']);
        $this->assertNotNull(Document::where('handle', 'product.gift-guide')->value('published'));
    }

    public function test_discard_deletes_layouts_never_published_and_keeps_published_ones(): void
    {
        $admin = $this->admin();
        $this->make('Gift guide')->assertCreated();
        $this->publish($admin, ['product.gift-guide' => 1])->assertOk();
        $content = Document::where('handle', 'product.gift-guide')->value('published');
        $this->saveDraft($admin, 'product.gift-guide', $content, 2)->assertOk();

        $this->make('Clearance')->assertCreated();
        $product = $this->product('Linen Shirt Dress');
        $this->assign('product', [$product->id], 'product.clearance')->assertOk();

        $this->actingAs($admin)->deleteJson(route('admin.xelga.documents.discard'), ['versions' => ['product.gift-guide' => 3, 'product.clearance' => 1]])
            ->assertOk()
            ->assertJsonPath('data.deleted_templates', ['product.clearance'])
            ->assertJsonPath('data.draft_versions', ['product.gift-guide' => 4]);

        $this->assertNull(Document::where('handle', 'product.clearance')->first());
        $this->assertSame(0, Assignment::count());
        $kept = Document::where('handle', 'product.gift-guide')->firstOrFail();
        $this->assertNull($kept->draft);
        $this->assertNotNull($kept->published);
    }

    public function test_a_main_section_cannot_be_removed_or_hidden(): void
    {
        $product = $this->defaults('product');
        $main = collect($product['sections'] ?? [])->filter(fn ($s) => (Catalog::section($s['type'])?->raw['main'] ?? false) === true)->keys()->first();
        if ($main === null) {
            $this->markTestSkipped('The shipped product template has no main section yet.');
        }
        $admin = $this->admin();

        $hidden = $product;
        $hidden['sections'][$main]['disabled'] = true;
        $this->saveDraft($admin, 'product', $hidden, 0)->assertStatus(422)->assertJsonValidationErrors("content.sections.{$main}.disabled");

        $removed = $product;
        unset($removed['sections'][$main]);
        $removed['order'] = array_values(array_diff($removed['order'], [$main]));
        $this->saveDraft($admin, 'product', $removed, 0)->assertStatus(422)->assertJsonValidationErrors('content.order');

        // Its blocks may be hidden and reordered.
        $blocks = $product['sections'][$main]['block_order'] ?? [];
        if (count($blocks) > 1) {
            $changed = $product;
            $changed['sections'][$main]['block_order'] = array_reverse($blocks);
            $changed['sections'][$main]['blocks'][$blocks[0]]['disabled'] = true;
            $this->saveDraft($admin, 'product', $changed, 0)->assertOk();
        }

        // A layout made from it keeps it too.
        $this->make('Gift guide')->assertCreated();
        $this->assertContains($main, Document::where('handle', 'product.gift-guide')->value('draft')['order']);
    }

    /* --------------------------------------------------------------------
       A layout for one record only
       -------------------------------------------------------------------- */

    public function test_a_layout_made_for_one_category_is_used_by_that_category_only(): void
    {
        config(['app.store_id' => $this->store->id]);
        $phones = $this->category('Phones');
        $laptops = $this->category('Laptops');

        // The board's button: a copy of the shared layout, then the category moved onto it.
        $this->make('Phones', 'category')->assertCreated();
        $this->assign('category', [$phones->id], 'category.phones')->assertOk()->assertJsonPath('data.assigned', [$phones->id]);

        $boot = BootPayload::build($this->admin());
        $layout = collect($boot['templates'])->firstWhere('handle', 'category.phones');
        $this->assertSame([$phones->id], $layout['assigned']);
        $this->assertSame(1, $layout['count']);
        // The shared layout keeps every other category: 2 in the store, 1 on its own layout.
        $this->assertSame(2, collect($boot['pages'])->firstWhere('type', 'category')['subject_count']);
        // Only that category uses it (shoppers see it once it is published).
        $this->assertSame([$phones->id], Assignment::query()->where('subject_type', 'category')->pluck('subject_id')->map(fn ($id) => (int) $id)->all());
        $this->assertNotContains($laptops->id, $layout['assigned']);
    }

    /* --------------------------------------------------------------------
       What the editor boots with
       -------------------------------------------------------------------- */

    public function test_the_boot_payload_lists_pages_layouts_drafts_and_preview_addresses(): void
    {
        $hidden = $this->product('Hidden Linen', active: false);
        $product = $this->product('Linen Shirt Dress');
        $other = $this->product('Merino Cardigan');
        $this->make('Gift guide')->assertCreated();
        $this->assign('product', [$other->id], 'product.gift-guide')->assertOk();

        $boot = BootPayload::build($this->admin());

        $pages = collect($boot['pages'])->keyBy('type');
        $this->assertSame(
            ['home', 'product', 'category', 'brand', 'brand-list', 'search', 'cart', 'blog', 'blog-post', 'page', 'contact', 'not-found', 'checkout', 'auth', 'account'],
            $pages->keys()->all(),
        );
        $this->assertSame(route('product.show', ['product' => $product->slug]), $pages['product']['url']);
        $this->assertSame(['type' => 'product', 'id' => $product->id, 'name' => 'Linen Shirt Dress', 'slug' => $product->slug], $pages['product']['subject']);
        $this->assertNotSame($hidden->id, $pages['product']['subject']['id']);
        $this->assertNull($pages['category']['url'], 'A store without categories has no category to preview.');
        $this->assertStringContainsString('q=linen', $pages['search']['url']);
        $this->assertSame('fixed', $pages['checkout']['kind']);
        $this->assertNull($pages['checkout']['handle']);
        $this->assertContains('cart-checkout', $pages['checkout']['groups']);
        $this->assertSame('product', $pages['product']['subject_type']);
        // Every product counts; the editor takes the special layouts' own off it.
        $this->assertSame(0, $pages['product']['subject_count'], 'Only the store\'s own records count.');
        config(['app.store_id' => $this->store->id]);
        $pages = collect(BootPayload::build($this->admin())['pages'])->keyBy('type');
        $this->assertSame(3, $pages['product']['subject_count']);
        $this->assertSame(0, $pages['category']['subject_count']);
        $this->assertArrayNotHasKey('subject_count', $pages['home']);

        $layout = collect($boot['templates'])->firstWhere('handle', 'product.gift-guide');
        $this->assertSame(1, $layout['count']);
        $this->assertSame($other->id, $layout['subject']['id']);
        $this->assertStringContainsString('xelga_template=product.gift-guide', $layout['url']);
        $this->assertStringContainsString($other->slug, $layout['url']);
        $this->assertSame(1, $boot['drafts']->{'product.gift-guide'});

        foreach (['templates_index', 'templates_store', 'templates_update', 'templates_destroy', 'assignments_update'] as $route) {
            $this->assertNotEmpty($boot['routes'][$route]);
        }
        $this->assertStringEndsWith('/templates/__HANDLE__', $boot['routes']['templates_update']);
    }
}
