<?php

namespace Themes\Xelga\Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\CustomerGroup;
use App\Models\Page;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;
use Themes\Xelga\Tests\XelgaTestHelpers;
use Themes\Xelga\Catalog\SettingSchema;
use Themes\Xelga\Documents\Document;
use Themes\Xelga\Documents\Shape;
use Themes\Xelga\Settings\SanitizeContext;
use Themes\Xelga\Settings\Sanitizer;

require_once __DIR__ . '/../XelgaTestHelpers.php';

/**
 * The full editor's endpoints: one section drawn on its own for the
 * preview, copies with fresh ids, and the read-only picker lookups.
 */
class XelgaEditorEndpointsTest extends TestCase
{
    use RefreshDatabase;
    use XelgaTestHelpers;

    private const DRAFT = 'Only the editor sees this';

    private const LIVE = 'Phones, laptops and tablets, paid for at your door';

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installXelga();
        $this->store = Store::firstOrCreate(['code' => 'demo'], ['name' => 'Demo Store']);
    }

    private function product(string $name, array $extra = []): Product
    {
        return Product::create($extra + ['store_id' => $this->store->id, 'name' => $name, 'slug' => \Illuminate\Support\Str::slug($name) . '-' . uniqid(), 'price' => 1250, 'is_active' => true]);
    }

    private function category(string $name, ?Category $parent = null, bool $active = true): Category
    {
        return Category::create(['store_id' => $this->store->id, 'parent_id' => $parent?->id, 'name' => $name, 'slug' => \Illuminate\Support\Str::slug($name) . '-' . uniqid(), 'is_active' => $active]);
    }

    private function pick(string $kind, array $query = [])
    {
        return $this->actingAs($this->admin())->getJson(route('admin.xelga.pick', ['kind' => $kind] + $query));
    }

    private function duplicate(string $handle, array $body)
    {
        return $this->actingAs($this->admin())->postJson(route('admin.xelga.documents.duplicate', ['handle' => $handle]), $body);
    }

    /** Every section and block id of one stored draft. @return list<string> */
    private function idsOf(array $content): array
    {
        $ids = [];
        foreach ($content['sections'] as $id => $section) {
            $ids[] = $id;
            array_push($ids, ...array_keys((array) $section['blocks']));
        }

        return $ids;
    }

    /* --------------------------------------------------------------------
       The editor page
       -------------------------------------------------------------------- */

    public function test_the_editor_shell_has_no_preview_frame(): void
    {
        $html = $this->actingAs($this->admin())->get(route('admin.xelga.editor'))->assertOk()->getContent();

        // The layout board is the whole editor: the store is only seen in a tab of its own.
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('frame.js', $html);
        $this->assertStringContainsString('id="xelga-editor"', $html);

        // No frame, so no message protocol to agree on with one.
        preg_match('#<script type="application/json" id="xelga-boot">(.*?)</script>#s', $html, $m);
        $boot = json_decode($m[1] ?? '', true);
        $this->assertIsArray($boot);
        $this->assertArrayNotHasKey('protocol', $boot);
        $this->assertArrayNotHasKey('origin', $boot['preview']);

        // The page shown before the editor's files are built links to the store instead of framing it.
        $shell = (string) file_get_contents(dirname(__DIR__, 2) . '/views/editor/shell.blade.php');
        $this->assertStringNotContainsString('<iframe', $shell);
    }

    /* --------------------------------------------------------------------
       One section on its own
       -------------------------------------------------------------------- */

    public function test_the_preview_answers_one_section_from_the_draft(): void
    {
        $this->saveDraft($this->admin(), 'home', $this->homeWithHeading(self::DRAFT), 0)->assertOk();
        $nonce = $this->openEditor($this->admin());

        $response = $this->get('/?xelga_preview=' . $nonce . '&xelga_section=hero-1')
            ->assertOk()
            ->assertHeader('X-Xelga-Section', 'hero-1')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $html = $response->getContent();
        $this->assertStringStartsWith('<section', ltrim($html));
        $this->assertStringContainsString('data-section-id="hero-1"', $html);
        $this->assertStringContainsString(self::DRAFT, $html);
        $this->assertStringNotContainsString('<html', $html);
        $this->assertStringNotContainsString('data-section-id="header-1"', $html);
    }

    public function test_a_section_of_the_header_or_a_new_section_is_answered_too(): void
    {
        $home = $this->defaults('home');
        $home['order'][] = 'hero-new1';
        $home['sections']['hero-new1'] = $home['sections']['hero-1'];
        $home['sections']['hero-new1']['label'] = 'Second banner';
        $home['sections']['hero-new1']['block_order'] = ['slide-new1'];
        $home['sections']['hero-new1']['blocks'] = ['slide-new1' => $home['sections']['hero-1']['blocks']['hero-1-b1']];
        $this->saveDraft($this->admin(), 'home', $home, 0)->assertOk();
        $nonce = $this->openEditor($this->admin());

        $this->get('/?xelga_preview=' . $nonce . '&xelga_section=hero-new1')->assertOk()->assertSee('data-block-id="slide-new1"', false);
        $this->get('/?xelga_preview=' . $nonce . '&xelga_section=header-1')->assertOk()->assertSee('data-section-id="header-1"', false);
    }

    public function test_unknown_or_malformed_sections_are_refused_in_the_preview(): void
    {
        $nonce = $this->openEditor($this->admin());

        $this->get('/?xelga_preview=' . $nonce . '&xelga_section=nope-404')->assertNotFound()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/?xelga_preview=' . $nonce . '&xelga_section=' . urlencode('<script>'))->assertStatus(422);
        $this->get('/?xelga_preview=' . $nonce . '&xelga_section[]=hero-1')->assertStatus(422);
    }

    public function test_outside_the_preview_the_section_parameter_is_ignored_and_no_draft_leaks(): void
    {
        $this->saveDraft($this->admin(), 'home', $this->homeWithHeading(self::DRAFT), 0)->assertOk();
        $nonce = $this->openEditor($this->admin());

        // The admin's ordinary tab: no nonce, no frame.
        $this->get('/?xelga_section=hero-1')->assertOk()->assertSee('<html', false)->assertSee(self::LIVE)->assertDontSee(self::DRAFT)->assertHeaderMissing('X-Xelga-Section');

        $this->asGuest();
        foreach (['/?xelga_section=hero-1', '/?xelga_preview=' . $nonce . '&xelga_section=hero-1'] as $url) {
            $this->get($url)->assertOk()->assertSee('<html', false)->assertSee(self::LIVE)->assertDontSee(self::DRAFT)->assertHeaderMissing('X-Xelga-Section');
        }
        $this->get('/?xelga_section=hero-1', ['Sec-Fetch-Dest' => 'iframe'])->assertOk()->assertDontSee(self::DRAFT);
    }

    public function test_section_redraws_are_limited_per_admin(): void
    {
        $admin = $this->admin();
        $nonce = $this->openEditor($admin);
        for ($i = 0; $i < 120; $i++) {
            RateLimiter::hit('xelga-section:' . $admin->id, 60);
        }

        $this->get('/?xelga_preview=' . $nonce . '&xelga_section=hero-1')->assertStatus(429)->assertHeader('Retry-After');
    }

    /* --------------------------------------------------------------------
       Copies with fresh ids
       -------------------------------------------------------------------- */

    public function test_a_section_copy_gets_new_ids_for_itself_and_its_blocks(): void
    {
        $response = $this->duplicate('home', ['section' => 'hero-1', 'draft_version' => 0])->assertOk();

        $newId = $response->json('data.new_id');
        $this->assertMatchesRegularExpression('/^hero-[a-z0-9]{4}$/', $newId);
        $draft = Document::where('handle', 'home')->firstOrFail()->draft;
        $this->assertSame(['hero-1', $newId], array_slice($draft['order'], 0, 2), 'The copy sits right after the original.');
        $copy = $draft['sections'][$newId];
        $this->assertSame('Main banner (copy)', $copy['label']);
        $this->assertCount(3, $copy['block_order']);
        $this->assertNotSame('hero-1-b1', $copy['block_order'][0]);
        $this->assertSame(
            $draft['sections']['hero-1']['blocks']['hero-1-b1']['settings']['heading'],
            $copy['blocks'][$copy['block_order'][0]]['settings']['heading'],
        );
        $this->assertSame(1, $response->json('data.document.draft_version'));

        // No id is used twice anywhere the preview could meet it.
        $all = array_merge($this->idsOf($draft), $this->idsOf($this->defaults('header')), $this->idsOf($this->defaults('footer')));
        $this->assertSame(count($all), count(array_unique($all)));
        foreach ($all as $id) {
            $this->assertMatchesRegularExpression(Shape::ID, $id);
        }
    }

    public function test_a_block_copy_sits_below_the_original(): void
    {
        $response = $this->duplicate('home', ['section' => 'hero-1', 'block' => 'hero-1-b1', 'draft_version' => 0])->assertOk();

        $newId = $response->json('data.new_id');
        $section = Document::where('handle', 'home')->firstOrFail()->draft['sections']['hero-1'];
        $this->assertSame(['hero-1-b1', $newId, 'hero-1-b2', 'hero-1-b3'], $section['block_order']);
        $this->assertStringEndsWith('(copy)', $section['blocks'][$newId]['label']);
    }

    public function test_copies_respect_limits_and_the_shared_draft(): void
    {
        // The header is one per page.
        $this->duplicate('header', ['section' => 'header-1', 'draft_version' => 0])->assertStatus(422)->assertJsonPath('code', 'invalid');

        // A banner holds at most five slides; the shipped one has three.
        $version = 0;
        for ($i = 0; $i < 2; $i++) {
            $version = $this->duplicate('home', ['section' => 'hero-1', 'block' => 'hero-1-b1', 'draft_version' => $version])->assertOk()->json('data.document.draft_version');
        }
        $this->duplicate('home', ['section' => 'hero-1', 'block' => 'hero-1-b1', 'draft_version' => $version])->assertStatus(422);

        // Someone else saved since: nothing is copied, their draft comes back.
        $this->duplicate('home', ['section' => 'hero-1', 'draft_version' => $version - 1])->assertStatus(409)->assertJsonPath('code', 'conflict');
        $this->duplicate('home', ['section' => 'gone-1', 'draft_version' => $version])->assertStatus(422);
        $this->duplicate('settings', ['section' => 'hero-1', 'draft_version' => 0])->assertStatus(422);
        $this->duplicate('home', ['section' => 'Hero 1', 'draft_version' => $version])->assertStatus(422);
    }

    /* --------------------------------------------------------------------
       Picker lookups
       -------------------------------------------------------------------- */

    public function test_product_lookups_answer_only_the_picker_fields(): void
    {
        $this->product('Linen Shirt Dress', ['image' => 'catalog/dress.jpg']);
        $this->product('Linen Wide Trousers', ['is_active' => false]);
        $this->product('Merino Cardigan', ['sku' => 'DEMO-001']);

        $results = $this->pick('products', ['q' => 'linen'])->assertOk()->assertJsonPath('data.more', false)->json('data.results');

        $this->assertSame(['Linen Shirt Dress', 'Linen Wide Trousers'], array_column($results, 'name'));
        $this->assertSame(['id', 'name', 'thumbnail', 'price', 'active'], array_keys($results[0]));
        $this->assertSame('/storage/catalog/dress.jpg', $results[0]['thumbnail']);
        $this->assertNotSame('', $results[0]['price']);
        $this->assertStringContainsString('1,250', $results[0]['price']);
        $this->assertFalse($results[1]['active']);

        // Every word typed must appear; a SKU pasted whole also finds it.
        $this->assertSame(['Linen Wide Trousers'], array_column($this->pick('products', ['q' => 'trousers linen'])->json('data.results'), 'name'));
        $this->assertSame(['Merino Cardigan'], array_column($this->pick('products', ['q' => 'DEMO-001'])->json('data.results'), 'name'));
    }

    public function test_product_lookups_page_by_twenty_and_name_ids_in_order(): void
    {
        $made = collect(range(1, 23))->map(fn ($i) => $this->product(sprintf('Demo product %02d', $i)));

        $this->pick('products')->assertJsonCount(20, 'data.results')->assertJsonPath('data.more', true);

        $ids = [$made[5]->id, $made[0]->id, $made[12]->id];
        $this->assertSame($ids, array_column($this->pick('products', ['ids' => implode(',', $ids)])->json('data.results'), 'id'));
        $this->pick('products', ['ids' => '1,javascript:alert(1)'])->assertStatus(422);
        $this->pick('products', ['q' => str_repeat('a', 81)])->assertStatus(422);
    }

    public function test_a_product_kept_for_one_customer_group_can_still_be_chosen(): void
    {
        $group = CustomerGroup::create(['store_id' => $this->store->id, 'name' => 'Resellers']);
        $product = $this->product('Reseller Linen Bundle');
        $product->customerGroups()->attach($group->id);

        $this->assertSame([$product->id], array_column($this->pick('products', ['q' => 'bundle'])->json('data.results'), 'id'));
    }

    public function test_category_brand_and_page_lookups(): void
    {
        $women = $this->category('Women');
        $this->category('Dresses', $women);
        $this->category('Dresses Archive', null, false);
        Brand::create(['store_id' => $this->store->id, 'name' => 'Demo Brand', 'slug' => 'demo-brand', 'is_active' => true]);
        Page::create(['title' => 'Size guide', 'slug' => 'size-guide', 'is_active' => true]);

        $categories = $this->pick('categories', ['q' => 'dresses'])->assertOk()->json('data.results');
        $this->assertSame(['Dresses Archive', 'Dresses'], array_column($categories, 'name'));
        $this->assertSame([null, 'Women'], array_column($categories, 'path'));
        $this->assertFalse($categories[0]['active']);

        $this->assertSame(['Demo Brand'], array_column($this->pick('brands', ['q' => 'demo'])->json('data.results'), 'name'));
        $this->assertSame(['Size guide'], array_column($this->pick('pages', ['q' => 'size'])->json('data.results'), 'name'));
        $this->pick('blog-posts')->assertOk()->assertJsonStructure(['data' => ['kind', 'results', 'more', 'available']]);
    }

    public function test_the_link_lookup_searches_every_kind_a_link_can_point_at(): void
    {
        $category = $this->category('Linen');
        $product = $this->product('Linen Shirt Dress');
        Page::create(['title' => 'Linen care', 'slug' => 'linen-care', 'is_active' => true]);

        $results = $this->pick('links', ['q' => 'linen'])->assertOk()->json('data.results');
        $this->assertSame(['category', 'product', 'page'], array_column($results, 'type'));
        $this->assertSame([$category->id, $product->id], array_column(array_slice($results, 0, 2), 'id'));

        $named = $this->pick('links', ['ids' => "product:{$product->id},category:{$category->id}"])->json('data.results');
        $this->assertEqualsCanonicalizing(['Linen', 'Linen Shirt Dress'], array_column($named, 'name'));
    }

    public function test_picker_lookups_are_throttled_per_admin(): void
    {
        $admin = $this->admin();
        for ($i = 0; $i < 120; $i++) {
            $this->actingAs($admin)->getJson(route('admin.xelga.pick', ['kind' => 'pages']))->assertOk();
        }
        $this->actingAs($admin)->getJson(route('admin.xelga.pick', ['kind' => 'pages']))->assertStatus(429);
        // Another admin has a budget of their own.
        $this->actingAs($this->themeEditor())->getJson(route('admin.xelga.pick', ['kind' => 'pages']))->assertOk();
    }

    /* --------------------------------------------------------------------
       Picker values on save
       -------------------------------------------------------------------- */

    public function test_picked_ids_are_checked_kept_in_order_and_cut_to_the_most(): void
    {
        $a = $this->product('Demo A');
        $b = $this->product('Demo B');
        $c = $this->product('Demo C');
        $sanitizer = new Sanitizer(SanitizeContext::forStore(['scheme-1']));

        $list = new SettingSchema(['key' => 'products', 'type' => 'product-picker', 'label' => 'Products', 'multiple' => true, 'max' => 2]);
        $this->assertSame([$c->id, $a->id], $sanitizer->clean($list, [$c->id, (string) $a->id, $c->id, 999999, $b->id], 'p'));

        $one = new SettingSchema(['key' => 'category', 'type' => 'category-picker', 'label' => 'Category']);
        $this->assertNull($sanitizer->clean($one, 424242, 'c'));
        $this->assertNull($sanitizer->clean($one, '1 OR 1=1', 'c2'));
        $this->assertSame($this->category('Women')->id, $sanitizer->clean($one, (string) Category::first()->id, 'c3'));

        $this->assertSame(['p', 'c', 'c2', 'c3'], array_column($sanitizer->adjusted(), 'path'));
    }

    public function test_a_huge_id_list_is_cut_before_the_database_is_asked(): void
    {
        $a = $this->product('Demo A');
        $b = $this->product('Demo B');
        $sanitizer = new Sanitizer(SanitizeContext::forStore(['scheme-1']));
        $list = new SettingSchema(['key' => 'products', 'type' => 'product-picker', 'label' => 'Products', 'multiple' => true, 'max' => 3]);

        // Repeats first, then far more distinct ids than the setting keeps.
        $huge = array_merge(array_fill(0, 50000, $a->id), [(string) $b->id], range(900000, 1100000));

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $started = microtime(true);
        $kept = $sanitizer->clean($list, $huge, 'p');
        $elapsed = microtime(true) - $started;
        $queries = \Illuminate\Support\Facades\DB::getQueryLog();
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $this->assertSame([$a->id, $b->id], $kept);
        $this->assertCount(1, $queries);
        // Only the three ids kept are asked about, not the whole list.
        $this->assertLessThan(400, strlen($queries[0]['query']));
        $this->assertStringNotContainsString('1100000', $queries[0]['query']);
        // A quadratic walk over this list takes minutes; a linear one, milliseconds.
        $this->assertLessThan(2.0, $elapsed);
    }
}
