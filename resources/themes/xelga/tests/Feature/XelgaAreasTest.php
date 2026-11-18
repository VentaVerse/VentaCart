<?php

namespace Themes\Xelga\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesSampleExtension;
use Tests\TestCase;
use Themes\Xelga\Documents\Defaults;
use Themes\Xelga\Documents\Layout;
use Themes\Xelga\Documents\LayoutRules;
use Themes\Xelga\Tests\XelgaExtensionHelpers;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';
require_once __DIR__ . '/../XelgaExtensionHelpers.php';

/**
 * Top, Content and Bottom: the areas of a page that has page content,
 * read from the real section catalog. The normalizer's own cases are the
 * shared fixtures in tests/fixtures/layout/areas.
 */
class XelgaAreasTest extends TestCase
{
    use RefreshDatabase;
    use UsesSampleExtension;
    use XelgaExtensionHelpers;
    use XelgaTestHelpers;

    private const SETTINGS = ['width' => 'page', 'scheme' => '', 'space' => 'md'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpXelgaWithExtension();
    }

    public function test_sides_and_areas_only_on_page_types_with_page_content(): void
    {
        foreach (['product', 'category', 'brand', 'brand-list', 'search', 'blog', 'blog-post', 'page', 'contact', 'not-found', 'product.gift-guide', 'sample_page'] as $handle) {
            $this->assertTrue(Layout::sidesAllowed($handle), $handle);
        }
        foreach (['home', 'cart', 'header', 'footer'] as $handle) {
            $this->assertFalse(Layout::sidesAllowed($handle), $handle);
        }
        $this->assertSame([], Defaults::mainTypes('home'));
        $this->assertSame(['product-main'], Defaults::mainTypes('product'));
        $this->assertTrue(app(LayoutRules::class, ['handle' => 'product'])->sidesAllowed());
        $this->assertFalse(app(LayoutRules::class, ['handle' => 'home'])->sidesAllowed());
    }

    public function test_a_page_with_sides_beside_every_row_keeps_every_section_in_order(): void
    {
        $content = Defaults::shipped('product');
        $this->assertNotEmpty($content['order']);
        $content['sections']['side-1'] = $this->text('Free returns');
        $content['sections']['side-2'] = $this->text('Secure checkout');
        // A banner the whole page wide, which no longer fits beside the sides.
        $content['sections']['wide-1'] = ['type' => 'hero', 'label' => '', 'disabled' => false, 'settings' => [], 'block_order' => [], 'blocks' => []];
        $content['order'] = ['wide-1', ...$content['order']];
        $rows = [];
        foreach ($content['order'] as $id) {
            $rows[] = ['id' => 'row-' . $id, 'split' => '12', 'columns' => [[$id]], 'settings' => self::SETTINGS];
        }
        $content['order'] = ['side-1', ...$content['order'], 'side-2'];
        $content['rows'] = $rows;
        $content['sides'] = ['left' => ['side-1'], 'right' => ['side-2']];
        $rules = app(LayoutRules::class, ['handle' => 'product']);

        $result = Layout::normalize($content, $rules)['content'];

        $ids = array_keys($content['sections']);
        sort($ids);
        $kept = $result['order'];
        sort($kept);
        $this->assertSame($ids, $kept, 'Every section is kept, each once.');

        $inRows = fn (array $doc) => array_values(array_filter(array_merge(...array_map(fn ($row) => array_merge(...$row['columns']), $doc['rows'])), 'is_string'));
        $this->assertSame($inRows($content), array_values(array_filter($result['order'], fn ($id) => in_array($id, $inRows($content), true))), 'The rows read in the same order.');
        foreach ($result['rows'] as $row) {
            $this->assertContains($row['area'], Layout::AREAS);
        }

        $again = Layout::normalize($result, $rules);
        $this->assertSame($result, $again['content']);
        $this->assertSame([], $again['moved']);
    }

    public function test_home_sides_move_to_rows_and_are_reported(): void
    {
        $content = Defaults::shipped('home');
        $content['sections']['t1'] = $this->text('Free returns');
        $content['sections']['t2'] = $this->text('Secure checkout');
        $content['order'] = ['t1', ...$content['order'], 't2'];
        $content['sides'] = ['left' => ['t1'], 'right' => ['t2']];

        $result = Layout::normalize($content, app(LayoutRules::class, ['handle' => 'home']));

        $this->assertArrayNotHasKey('sides', $result['content']);
        $this->assertSame(['t1', 't2'], array_slice($result['content']['order'], -2));
        // Plain rows say no more than the order, so they may be dropped; read them back.
        $rows = Layout::rows($result['content'], app(LayoutRules::class, ['handle' => 'home']));
        $this->assertSame([[['t1']], [['t2']]], array_column(array_slice($rows, -2), 'columns'));
        foreach ($rows as $row) {
            $this->assertArrayNotHasKey('area', $row, 'Home has no areas.');
        }
        $this->assertSame(['no-sides', 'no-sides'], array_column($result['moved'], 'reason'));
        $this->assertSame(['content.sections.t1', 'content.sections.t2'], array_column($result['moved'], 'path'));
    }

    public function test_a_page_waits_untouched_while_its_extension_is_away(): void
    {
        $content = Defaults::shipped('sample_page');
        $content['sections']['side-1'] = $this->text('Free returns');
        $content['order'] = ['side-1', ...$content['order']];
        $content['rows'] = [
            ['id' => 'row-a', 'split' => '6-6', 'columns' => [['sample-main'], ['sample-text']], 'settings' => self::SETTINGS],
        ];
        $content['sides'] = ['left' => ['side-1']];

        $this->switchOffSampleExtension();
        $this->forgetXelgaState();
        $rules = app(LayoutRules::class, ['handle' => 'sample_page']);
        $this->assertFalse($rules->pageTypeKnown());

        $this->assertSame(['content' => $content, 'moved' => []], Layout::normalize($content, $rules));
    }

    /** A Text section, narrow enough for a side. */
    private function text(string $heading): array
    {
        return ['type' => 'rich-text', 'label' => '', 'disabled' => false, 'settings' => ['variant' => 'start', 'heading' => $heading, 'text' => '<p>Free returns within 30 days.</p>'], 'block_order' => [], 'blocks' => []];
    }
}
