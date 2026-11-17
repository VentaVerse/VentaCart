<?php

namespace Themes\Xelga\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesSampleExtension;
use Tests\TestCase;
use Themes\Xelga\Catalog\Catalog;
use Themes\Xelga\Catalog\CatalogLinter;
use Themes\Xelga\Documents\Layout;
use Themes\Xelga\Tests\XelgaExtensionHelpers;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';
require_once __DIR__ . '/../XelgaExtensionHelpers.php';

/**
 * A section's width in the catalog: what the linter holds the theme's and
 * an extension's schemas to, and what the editor's boot data carries.
 */
class XelgaSpanCatalogTest extends TestCase
{
    use RefreshDatabase;
    use UsesSampleExtension;
    use XelgaExtensionHelpers;
    use XelgaTestHelpers;

    private const SECTION_SCHEMA = 'tests/fixtures/extensions/sample-extension/resources/xelga/schemas/sections';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpXelgaWithExtension();
    }

    /** Run $check with a throwaway section of the theme (schema and optional stylesheet), removed afterwards. */
    private function withOwnSection(array $span, ?string $css, callable $check, array $extra = []): void
    {
        $schema = Catalog::root('schemas/sections/zz-probe.json');
        $sheet = Catalog::root('css/sections/zz-probe.css');
        file_put_contents($schema, json_encode($extra + [
            'type' => 'zz-probe', 'label' => 'Probe', 'group' => 'template', 'templates' => ['home'],
            'class' => 's-zz-probe', 'settings' => [],
        ] + $span));
        if ($css !== null) {
            file_put_contents($sheet, $css);
        }
        try {
            $check(CatalogLinter::check());
        } finally {
            @unlink($schema);
            @unlink($sheet);
            Catalog::flush();
        }
    }

    public function test_lint_refuses_a_span_outside_the_allowed_values(): void
    {
        $this->withOwnSection(['span' => ['min' => 5]], '.s-zz-probe { container-type: inline-size; }', function (array $problems) {
            $this->assertContains('Section zz-probe: span.min must be 3, 4, 6, 8 or 12.', $problems);
        });
        $this->withOwnSection(['span' => ['min' => 6]], '.s-zz-probe { container-type: inline-size; }', function (array $problems) {
            $this->assertNotContains('Section zz-probe: span.min must be 3, 4, 6, 8 or 12.', $problems);
        }, ['variants' => [['value' => 'a', 'label' => 'A', 'span' => 7]]]);
    }

    public function test_lint_refuses_a_narrow_section_without_its_own_container(): void
    {
        $message = 'Section zz-probe: can sit in a narrow column, so css/sections/zz-probe.css must set container-type: inline-size on .s-zz-probe itself.';
        $this->withOwnSection(['span' => ['min' => 4]], null, fn (array $problems) => $this->assertContains($message, $problems));
        $this->withOwnSection(['span' => ['min' => 4]], '.s-zz-probe .inner { container-type: inline-size; }', fn (array $problems) => $this->assertContains($message, $problems));
        $this->withOwnSection(['span' => ['min' => 4]], '.s-zz-probe { container-type: inline-size; }', fn (array $problems) => $this->assertNotContains($message, $problems));
        $this->withOwnSection(['span' => ['min' => 12]], null, fn (array $problems) => $this->assertNotContains($message, $problems));
    }

    public function test_lint_refuses_container_type_on_main_columns_or_sides(): void
    {
        $selectors = ['main', '.xr__col', '.xp__side', '.xp__content', '.s-product', 'body > main', '.xr__col:first-child'];
        foreach ($selectors as $selector) {
            $this->withOwnSection(['span' => ['min' => 12]], "{$selector} {\n  container-type: inline-size;\n}", function (array $problems) use ($selector) {
                $this->assertContains("css/sections/zz-probe.css: {$selector} sets a container; only a section sets one, on itself.", $problems, $selector);
            });
        }
        $this->withOwnSection(['span' => ['min' => 12]], '@media (min-width: 750px) { .xr__col { container: col / inline-size; } }', function (array $problems) {
            $this->assertContains('css/sections/zz-probe.css: .xr__col sets a container; only a section sets one, on itself.', $problems);
        });
        $this->withOwnSection(['span' => ['min' => 12]], '.s-product-extra, .xr__column { color: red; } /* main { container-type: size; } */', function (array $problems) {
            $this->assertSame([], array_values(array_filter($problems, fn ($p) => str_contains($p, 'sets a container'))));
        });
        $this->assertSame([], CatalogLinter::check());
    }

    public function test_lint_refuses_a_main_section_with_a_narrow_span(): void
    {
        $this->withOwnSection(['span' => ['min' => 6]], '.s-zz-probe { container-type: inline-size; }', function (array $problems) {
            $this->assertContains('Section zz-probe: a main section is always full width, so no span may be below 12.', $problems);
        }, ['main' => true]);

        $this->withOwnSection(['span' => ['min' => 12]], null, function (array $problems) {
            $this->assertContains('Section zz-probe: a main section is always full width, so no span may be below 12.', $problems);
        }, ['main' => true, 'variants' => [['value' => 'a', 'label' => 'A', 'span' => 6]]]);

        $file = base_path(self::SECTION_SCHEMA . '/sample-extension.main.json');
        $original = (string) file_get_contents($file);
        try {
            $spec = json_decode($original, true);
            $spec['span'] = ['min' => 6];
            file_put_contents($file, json_encode($spec));
            $this->forgetXelgaState();
            $this->assertContains(
                'sample-extension: section sample-extension.main: a main section is always full width, so no span may be below 12.',
                CatalogLinter::checkExtension('sample-extension'),
            );
            $spec['span'] = ['min' => 5];
            file_put_contents($file, json_encode($spec));
            $this->forgetXelgaState();
            $this->assertContains('sample-extension: section sample-extension.main: span.min must be 3, 4, 6, 8 or 12.', CatalogLinter::checkExtension('sample-extension'));
        } finally {
            file_put_contents($file, $original);
            $this->forgetXelgaState();
        }
    }

    public function test_an_extension_section_without_span_stays_full_width(): void
    {
        $card = Catalog::section('sample-extension.card');
        $this->assertSame(12, $card->minSpan());
        $this->assertSame(['min' => 12, 'variants' => []], $card->spans());
        $this->assertSame(3, Catalog::section('sample-extension.tile')->minSpan());

        // Its own stylesheet is its business: no container check on an extension's section.
        $this->assertSame([], CatalogLinter::checkExtension('sample-extension'));
    }

    public function test_the_boot_payload_carries_spans_and_the_layout(): void
    {
        $html = $this->actingAs($this->admin())->get(route('admin.xelga.editor'))->getContent();
        preg_match('/<script type="application\/json" id="xelga-boot">(.*?)<\/script>/s', (string) $html, $m);
        $boot = json_decode(html_entity_decode($m[1] ?? '', ENT_QUOTES), true);
        $this->assertIsArray($boot);

        $sections = $boot['catalog']['sections'];
        $this->assertSame(['min' => 12, 'variants' => []], $sections['hero']['span']);
        $this->assertSame(['min' => 4, 'variants' => ['strip' => 6]], $sections['usp-bar']['span']);
        $this->assertSame(['min' => 12, 'variants' => []], $sections['sample-extension.card']['span']);
        $this->assertSame(['min' => 3, 'variants' => []], $sections['sample-extension.tile']['span']);
        foreach ($sections as $type => $section) {
            $this->assertSame(Catalog::section($type)->spans(), $section['span'], $type);
        }

        $this->assertSame(Layout::payload(), $boot['layout']);
        $this->assertSame(Layout::ROW_CHOICES, $boot['layout']['row_choices']);
        $this->assertContains('sample_page', $boot['layout']['sides_on'], 'An extension page type may have sides too.');
        $this->assertNotContains('cart', $boot['layout']['sides_on']);
    }
}
