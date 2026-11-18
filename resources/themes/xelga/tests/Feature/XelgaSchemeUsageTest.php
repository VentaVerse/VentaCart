<?php

namespace Themes\Xelga\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Themes\Xelga\Documents\SchemeUsage;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';

/**
 * Where each color scheme is used across the store: every section, block
 * and row of columns that names it, on every page, read from the draft
 * when there is one, else the published page, else the shipped one.
 */
class XelgaSchemeUsageTest extends TestCase
{
    use RefreshDatabase;
    use XelgaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installXelga();
    }

    /** The shipped home page with Text and Brand logos side by side in a row of halves. */
    private function homeWithRow(string $textScheme): array
    {
        $home = $this->defaults('home');
        $home['sections']['text-1']['settings']['scheme'] = $textScheme;
        $rows = [];
        foreach ($home['order'] as $sid) {
            if ($sid === 'brands-1') {
                continue;
            }
            $rows[] = $sid === 'text-1'
                ? ['id' => 'row-pair', 'split' => '6-6', 'columns' => [['text-1'], ['brands-1']], 'settings' => ['width' => 'page', 'scheme' => 'scheme-5', 'space' => 'md']]
                : ['id' => 'row-' . $sid, 'split' => '12', 'columns' => [[$sid]], 'settings' => ['width' => 'page', 'scheme' => '', 'space' => 'md']];
        }
        $home['rows'] = $rows;

        return $home;
    }

    /** @param list<array> $uses */
    private function has(array $uses, string $handle, ?string $section, ?string $block = null, ?string $row = null): bool
    {
        foreach ($uses as $use) {
            if ($use['handle'] === $handle && $use['section'] === $section && $use['block'] === $block && $use['row'] === $row) {
                return true;
            }
        }

        return false;
    }

    public function test_scheme_usage_counts_sections_blocks_and_rows_on_every_page(): void
    {
        $admin = $this->admin();

        // Category: published only, no draft left.
        $category = $this->defaults('category');
        $category['sections']['category-main-1']['settings']['scheme'] = 'scheme-3';
        $this->saveDraft($admin, 'category', $category, 0)->assertOk();
        $this->publish($admin, ['category' => 1])->assertOk();

        // Home: published with Text on scheme 2, then a draft moves it to scheme 4.
        $this->saveDraft($admin, 'home', $this->homeWithRow('scheme-2'), 0)->assertOk();
        $this->publish($admin, ['home' => 1])->assertOk();
        $this->actingAs($admin)->putJson(route('admin.xelga.documents.draft', ['handle' => 'home']), [
            'content' => $this->homeWithRow('scheme-4'),
            'draft_version' => 2,
            'with_rows' => true,
            'with_areas' => true,
        ])->assertOk();

        // Product: never saved, so the shipped page counts.
        $usage = SchemeUsage::forStore();

        foreach (['scheme-1', 'scheme-2', 'scheme-3', 'scheme-4', 'scheme-5'] as $id) {
            $this->assertArrayHasKey($id, $usage);
        }

        // The draft wins over the published page.
        $this->assertTrue($this->has($usage['scheme-4'], 'home', 'text-1'));
        $this->assertFalse($this->has($usage['scheme-2'], 'home', 'text-1'));
        // A row of columns names its background.
        $this->assertTrue($this->has($usage['scheme-5'], 'home', null, null, 'row-pair'));
        $row = collect($usage['scheme-5'])->firstWhere('row', 'row-pair');
        $this->assertSame('Row with Rich text and Brand logos', $row['label']);
        // A block names its own scheme.
        $this->assertTrue($this->has($usage['scheme-4'], 'home', 'promos-1', 'promos-1-b1'));
        $this->assertTrue($this->has($usage['scheme-3'], 'home', 'promos-1', 'promos-1-b2'));
        // Published only, shipped only, and the header and footer.
        $this->assertTrue($this->has($usage['scheme-3'], 'category', 'category-main-1'));
        $this->assertTrue($this->has($usage['scheme-1'], 'product', 'product-main-1'), 'A section without a stored scheme uses its default.');
        $this->assertTrue($this->has($usage['scheme-3'], 'footer', 'footer-1'));
        $this->assertTrue($this->has($usage['scheme-3'], 'header', 'announcement-1'));
        // One-column rows have no background of their own.
        $this->assertSame([], array_values(array_filter($usage['scheme-1'], fn ($u) => $u['row'] !== null)));

        foreach ($usage as $uses) {
            foreach ($uses as $use) {
                $this->assertSame(['handle', 'section', 'block', 'row', 'label'], array_keys($use));
                $this->assertNotSame('', $use['label']);
            }
        }

        // The editor reads the same through its endpoint.
        $response = $this->actingAs($admin)->getJson(route('admin.xelga.schemes.usage'))->assertOk();
        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
        $this->assertTrue($this->has($response->json('data.usage.scheme-5'), 'home', null, null, 'row-pair'));
    }

    public function test_scheme_usage_needs_theme_edit_permission(): void
    {
        $this->getJson(route('admin.xelga.schemes.usage'))->assertUnauthorized();
        $this->actingAs($this->staff())->getJson(route('admin.xelga.schemes.usage'))->assertForbidden();
        $this->actingAs($this->themeEditor())->getJson(route('admin.xelga.schemes.usage'))->assertOk()
            ->assertJsonPath('success', true);
    }
}
