<?php

namespace Themes\Xelga\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesSampleExtension;
use Tests\TestCase;
use Themes\Xelga\Catalog\CatalogLinter;
use Themes\Xelga\Documents\Document;
use Themes\Xelga\Documents\Layout;
use Themes\Xelga\Documents\LayoutRules;
use Themes\Xelga\Extensions\Registrar;
use Themes\Xelga\Rendering\Storefront;
use Themes\Xelga\Tests\XelgaExtensionHelpers;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';
require_once __DIR__ . '/../XelgaExtensionHelpers.php';

/**
 * The Announcement bar as a section like any other: in the header band,
 * the footer band or any page, once per band and at most once per area,
 * always in a row of its own. And Filters, which belongs beside the
 * listing: in a side or in Content, never in Top or Bottom.
 */
class XelgaAnnouncementBarTest extends TestCase
{
    use RefreshDatabase;
    use UsesSampleExtension;
    use XelgaExtensionHelpers;
    use XelgaTestHelpers;

    private const SETTINGS = ['width' => 'page', 'scheme' => '', 'space' => 'md'];

    private const EXTENSION_SCHEMAS = 'tests/fixtures/extensions/sample-extension/resources/xelga/schemas/sections';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpXelgaWithExtension();
    }

    /** A bar with one message; block ids are unique in a document, so each bar names its own. */
    private function bar(string $text = 'Cash on delivery nationwide', ?string $block = null): array
    {
        static $made = 0;
        $block ??= 'message-' . ++$made;

        return [
            'type' => 'announcement-bar', 'label' => '', 'disabled' => false,
            'settings' => ['variant' => 'rotate', 'interval' => 5, 'show_arrows' => true, 'scheme' => 'scheme-3'],
            'block_order' => [$block],
            'blocks' => [$block => ['type' => 'message', 'label' => 'Message', 'disabled' => false, 'settings' => ['text' => $text, 'link' => null]]],
        ];
    }

    private function text(string $heading): array
    {
        return ['type' => 'rich-text', 'label' => '', 'disabled' => false, 'settings' => ['variant' => 'start', 'heading' => $heading, 'text' => '<p>Free returns within 30 days.</p>'], 'block_order' => [], 'blocks' => []];
    }

    private function filters(): array
    {
        return ['type' => 'filters', 'label' => '', 'disabled' => false, 'settings' => [], 'block_order' => [], 'blocks' => []];
    }

    private function row(string $id, array $columns, ?string $area = null, string $split = '12'): array
    {
        $row = ['id' => $id, 'split' => $split, 'columns' => $columns, 'settings' => self::SETTINGS];

        return $area === null ? $row : $row + ['area' => $area];
    }

    /**
     * The document with one full-width row per entry of $layout, in order:
     * section id => area.
     *
     * @param  array<string, string>  $layout
     */
    private function withRows(array $content, array $layout): array
    {
        $content['order'] = array_keys($layout);
        $content['rows'] = [];
        foreach ($layout as $id => $area) {
            $content['rows'][] = $this->row('row-' . $id, [[$id]], $area);
        }

        return $content;
    }

    /** The shipped product page with these bars, each in its own row: section id => area. */
    private function productWithBars(array $bars): array
    {
        $product = $this->defaults('product');
        foreach (array_keys($bars) as $id) {
            $product['sections'][$id] = $this->bar();
        }
        $in = fn (string $area) => array_filter($bars, fn (string $a) => $a === $area);
        $layout = [...$in('top'), 'breadcrumbs-1' => 'top', ...$in('content'), 'product-main-1' => 'content'];
        foreach (array_slice($product['order'], 2) as $id) {
            $layout[$id] = 'bottom';
        }

        return $this->withRows($product, [...$layout, ...$in('bottom')]);
    }

    private function draft(string $handle): array
    {
        return Document::where('handle', $handle)->firstOrFail()->draft;
    }

    public function test_the_bar_can_be_added_to_the_header_the_footer_and_any_page(): void
    {
        $header = $this->defaults('header');
        $this->assertSame(['announcement-bar', 'header'], array_values(array_map(fn ($id) => $header['sections'][$id]['type'], $header['order'])));
        $this->saveDraft($this->admin(), 'header', $header, 0)->assertOk();

        $footer = $this->defaults('footer');
        $footer['sections']['bar-1'] = $this->bar();
        $footer['order'][] = 'bar-1';
        $this->saveDraft($this->admin(), 'footer', $footer, 0)->assertOk();
        $this->assertSame(['footer-1', 'bar-1'], $this->draft('footer')['order']);

        $this->saveWithRows('product', $this->productWithBars(['bar-1' => 'top']), 0)->assertOk()->assertJsonPath('data.adjusted', []);
        $this->assertSame('top', collect($this->draft('product')['rows'])->firstWhere('id', 'row-bar-1')['area']);

        $home = $this->defaults('home');
        $home['sections']['bar-1'] = $this->bar();
        array_splice($home['order'], 1, 0, ['bar-1']);
        $this->saveWithRows('home', $home, 0)->assertOk();
        $this->assertSame(['hero-1', 'bar-1', 'usp-1'], array_slice($this->draft('home')['order'], 0, 3));
    }

    public function test_one_bar_per_band(): void
    {
        $header = $this->defaults('header');
        $header['sections']['bar-2'] = $this->bar('Ships in 1 to 2 working days');
        array_unshift($header['order'], 'bar-2');

        $this->saveDraft($this->admin(), 'header', $header, 0)->assertStatus(422)
            ->assertJsonValidationErrors(['content.sections.announcement-1.type']);
    }

    public function test_at_most_three_bars_on_a_page_with_areas(): void
    {
        $four = $this->productWithBars(['bar-1' => 'top', 'bar-2' => 'content', 'bar-3' => 'bottom', 'bar-4' => 'bottom']);
        $this->saveWithRows('product', $four, 0)->assertStatus(422)
            ->assertJsonValidationErrors(['content.sections.bar-4.type']);

        $three = $this->productWithBars(['bar-1' => 'top', 'bar-2' => 'content', 'bar-3' => 'bottom']);
        $this->saveWithRows('product', $three, 0)->assertOk()->assertJsonPath('data.adjusted', []);

        $home = $this->defaults('home');
        $home['sections']['bar-1'] = $this->bar();
        $home['sections']['bar-2'] = $this->bar();
        array_splice($home['order'], 1, 0, ['bar-1']);
        $home['order'][] = 'bar-2';
        $this->saveWithRows('home', $home, 0)->assertStatus(422)
            ->assertJsonValidationErrors(['content.sections.bar-2.type']);
    }

    public function test_a_bar_is_copied_only_into_an_area_with_room_for_it(): void
    {
        $product = $this->productWithBars(['bar-1' => 'top', 'bar-2' => 'bottom']);
        $version = (int) $this->saveWithRows('product', $product, 0)->assertOk()->json('data.document.draft_version');

        // The page holds two bars, but each area only its own.
        $areas = Layout::sectionAreas($this->draft('product'), app(LayoutRules::class, ['handle' => 'product']));
        $this->assertSame('top', $areas['bar-1']);
        $this->assertSame('bottom', $areas['bar-2']);
        $this->assertSame('content', $areas['product-main-1']);

        // The copy would sit just under its original, in Top, which has its bar already.
        $this->actingAs($this->admin())->postJson(route('admin.xelga.documents.duplicate', ['handle' => 'product']), ['section' => 'bar-1', 'draft_version' => $version])
            ->assertStatus(422)->assertJsonPath('errors.section.0', 'Top already has an announcement bar.');

        // Home has no areas: its limit counts the page.
        $home = $this->defaults('home');
        $home['sections']['bar-1'] = $this->bar();
        array_splice($home['order'], 1, 0, ['bar-1']);
        $version = (int) $this->saveWithRows('home', $home, 0)->assertOk()->json('data.document.draft_version');
        $this->assertSame([], Layout::sectionAreas($this->draft('home'), app(LayoutRules::class, ['handle' => 'home'])));
        $this->actingAs($this->admin())->postJson(route('admin.xelga.documents.duplicate', ['handle' => 'home']), ['section' => 'bar-1', 'draft_version' => $version])
            ->assertStatus(422)->assertJsonPath('errors.section.0', 'A page holds at most 1 Announcement bar.');
    }

    public function test_two_bars_in_one_area_after_a_side_is_added_still_save(): void
    {
        $product = $this->defaults('product');
        $product['sections']['bar-1'] = $this->bar();
        $product['sections']['bar-2'] = $this->bar('Ships in 1 to 2 working days');
        $product['sections']['banner-1'] = $this->defaults('home')['sections']['hero-1'];
        $layout = ['bar-1' => 'top', 'breadcrumbs-1' => 'top', 'bar-2' => 'content', 'banner-1' => 'content', 'product-main-1' => 'content'];
        foreach (array_slice($this->defaults('product')['order'], 2) as $id) {
            $layout[$id] = 'bottom';
        }
        $product = $this->withRows($product, $layout);

        $version = (int) $this->saveWithRows('product', $product, 0)->assertOk()->assertJsonPath('data.adjusted', [])->json('data.document.draft_version');
        $this->assertSame('content', collect($this->draft('product')['rows'])->firstWhere('id', 'row-bar-2')['area']);

        // A full-width banner no longer fits beside a side, so it goes to Top
        // and takes every Content row above it, the second bar's too.
        $product['sections']['side-1'] = $this->text('Size guide');
        $product['order'][] = 'side-1';
        $product['sides'] = ['left' => ['side-1'], 'right' => []];
        $adjusted = $this->saveWithRows('product', $product, $version)->assertOk()->json('data.adjusted');

        $this->assertContains(['path' => 'content.sections.banner-1', 'reason' => 'too-wide', 'value' => 'top'], $adjusted);
        $this->assertContains(['path' => 'content.sections.bar-2', 'reason' => 'in-order', 'value' => 'top'], $adjusted);
        $draft = $this->draft('product');
        $areas = array_column($draft['rows'], 'area', 'id');
        $this->assertSame('top', $areas['row-bar-1']);
        $this->assertSame('top', $areas['row-bar-2']);
        $this->assertSame(['bar-1', 'breadcrumbs-1', 'bar-2', 'banner-1', 'side-1', 'product-main-1'], array_slice($draft['order'], 0, 6));

        $version = (int) Document::where('handle', 'product')->value('draft_version');
        $this->publish($this->admin(), ['product' => $version])->assertOk();
        $this->assertSame($draft['order'], Document::where('handle', 'product')->firstOrFail()->published['order']);
    }

    public function test_a_bar_in_a_column_moves_to_a_row_of_its_own(): void
    {
        $home = $this->defaults('home');
        $home['sections']['bar-1'] = $this->bar();
        array_splice($home['order'], array_search('text-1', $home['order'], true), 0, ['bar-1']);
        $home['rows'] = [$this->row('row-pair', [['bar-1'], ['text-1']], null, '6-6')];

        $adjusted = $this->saveWithRows('home', $home, 0)->assertOk()->json('data.adjusted');

        $reasons = array_column($adjusted, 'reason', 'path');
        $this->assertSame('own-row', $reasons['content.sections.bar-1'] ?? null);
        $rows = $this->draft('home')['rows'];
        $own = collect($rows)->first(fn (array $row) => $row['columns'] === [['bar-1']]);
        $this->assertNotNull($own, 'The bar has a row to itself.');
        $this->assertSame('12', $own['split']);
    }

    public function test_filters_in_top_moves_into_content(): void
    {
        $category = $this->defaults('category');
        $category['sections']['filters-1'] = $this->filters();
        $category = $this->withRows($category, ['filters-1' => 'top', 'breadcrumbs-1' => 'top', 'category-main-1' => 'content', 'recent-1' => 'bottom']);

        $this->saveWithRows('category', $category, 0)->assertOk()
            ->assertJsonPath('data.adjusted', [['path' => 'content.sections.filters-1', 'reason' => 'content-only', 'value' => 'row-filters-1']]);

        $draft = $this->draft('category');
        $this->assertSame(['row-breadcrumbs-1' => 'top', 'row-filters-1' => 'content', 'row-category-main-1' => 'content', 'row-recent-1' => 'bottom'], array_column($draft['rows'], 'area', 'id'));
        $this->assertSame(['breadcrumbs-1', 'filters-1', 'category-main-1', 'recent-1'], $draft['order']);
    }

    public function test_filters_in_a_content_column_stays(): void
    {
        $category = $this->defaults('category');
        $category['sections'] += ['filters-1' => $this->filters(), 'text-a' => $this->text('Sizes'), 'text-b' => $this->text('Fabrics'), 'text-c' => $this->text('Care')];
        $category['order'] = ['breadcrumbs-1', 'filters-1', 'text-a', 'text-b', 'text-c', 'category-main-1', 'recent-1'];
        $category['rows'] = [
            $this->row('row-crumbs', [['breadcrumbs-1']], 'top'),
            $this->row('row-quarters', [['filters-1'], ['text-a'], ['text-b'], ['text-c']], 'content', '3-3-3-3'),
            $this->row('row-main', [['category-main-1']], 'content'),
            $this->row('row-recent', [['recent-1']], 'bottom'),
        ];

        $this->saveWithRows('category', $category, 0)->assertOk()->assertJsonPath('data.adjusted', []);

        $draft = $this->draft('category');
        $this->assertSame($category['rows'], $draft['rows']);
        $this->assertSame($category['order'], $draft['order']);
    }

    public function test_shipped_header_and_footer_groups_still_save_and_read_unchanged(): void
    {
        // The menus a store has, so a band's menu blocks keep the menu they name.
        \App\Models\NavMenu::firstOrCreate(['key' => 'main'], ['name' => 'Main menu']);
        \App\Models\NavMenu::firstOrCreate(['key' => 'footer'], ['name' => 'Footer menu']);
        $versions = ['settings' => 0, 'header' => 0, 'footer' => 0, 'home' => 0];
        $check = function (string $name, string $handle, array $content) use (&$versions): void {
            $response = $this->saveDraft($this->admin(), $handle, $content, $versions[$handle])->assertOk();
            $this->assertSame([], $response->json('data.adjusted'), "{$name} {$handle}");
            $versions[$handle] = (int) $response->json('data.document.draft_version');
            $this->assertSame($content['order'], $this->draft($handle)['order'], "{$name} {$handle}");
            $this->assertSame($content, Storefront::withLockedSections($content, $handle), "{$name} {$handle}");
        };

        foreach (['header', 'footer'] as $handle) {
            $check('default', $handle, $this->defaults($handle));
        }

        // Each store template's bands, as applying it stores them.
        $stores = array_map(fn (string $file) => basename(dirname($file)), glob(base_path('resources/themes/xelga/store-templates/*/store.json')));
        $this->assertGreaterThan(1, count($stores));
        foreach ($stores as $store) {
            $applied = $this->actingAs($this->admin())->postJson(route('admin.xelga.store-templates.apply', ['slug' => $store]), ['bring' => 'all', 'versions' => $versions])->assertOk();
            foreach (array_keys($versions) as $handle) {
                $versions[$handle] = (int) Document::where('handle', $handle)->value('draft_version');
            }
            foreach (['header', 'footer'] as $handle) {
                $this->assertSame([], $applied->json("data.adjusted.{$handle}"), "{$store} {$handle}");
                $check($store, $handle, $this->draft($handle));
            }
        }
    }

    public function test_the_linter_refuses_shared_groups_in_an_extension_section(): void
    {
        $this->assertSame([], CatalogLinter::checkExtension('sample-extension'));

        $file = base_path(self::EXTENSION_SCHEMAS . '/sample-extension.tile.json');
        $original = (string) file_get_contents($file);
        $spec = json_decode($original, true);
        try {
            file_put_contents($file, json_encode($spec + ['shared_groups' => ['header'], 'own_row' => true, 'limit_per_area' => true, 'content_only' => true]));
            $this->forgetXelgaState();
            Registrar::forgetLogged();
            $problems = CatalogLinter::checkExtension('sample-extension');

            foreach (['shared_groups', 'own_row', 'limit_per_area', 'content_only'] as $key) {
                $this->assertContains("sample-extension: section sample-extension.tile: {$key} is for the theme's own sections only.", $problems);
            }
            $this->assertSame([], \Themes\Xelga\Catalog\Catalog::section('sample-extension.tile')->sharedGroups());
            $this->assertFalse(\Themes\Xelga\Catalog\Catalog::section('sample-extension.tile')->allowedIn('header', 'group', null));
        } finally {
            file_put_contents($file, $original);
            $this->forgetXelgaState();
        }
    }

    public function test_the_linter_keeps_a_content_only_section_narrow_enough_for_two_sides(): void
    {
        $this->assertSame([], CatalogLinter::check());

        $file = base_path('resources/themes/xelga/schemas/sections/filters.json');
        $original = (string) file_get_contents($file);
        $spec = json_decode($original, true);
        try {
            file_put_contents($file, json_encode(['span' => ['min' => 8]] + $spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            \Themes\Xelga\Catalog\Catalog::flush();

            $this->assertContains('Section filters: content_only, so every span must fit the 6 twelfths Content keeps between two sides.', CatalogLinter::check());
        } finally {
            file_put_contents($file, $original);
            \Themes\Xelga\Catalog\Catalog::flush();
        }
    }
}
