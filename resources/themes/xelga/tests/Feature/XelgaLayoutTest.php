<?php

namespace Themes\Xelga\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesSampleExtension;
use Tests\TestCase;
use Themes\Xelga\Catalog\Catalog;
use Themes\Xelga\Catalog\SectionSchema;
use Themes\Xelga\Documents\CatalogLayoutRules;
use Themes\Xelga\Documents\Defaults;
use Themes\Xelga\Documents\Handles;
use Themes\Xelga\Documents\Layout;
use Themes\Xelga\Documents\LayoutRules;
use Themes\Xelga\StoreTemplates\StoreTemplates;
use Themes\Xelga\Tests\XelgaExtensionHelpers;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';
require_once __DIR__ . '/../XelgaExtensionHelpers.php';

/**
 * Rows and sides in a page document: the normalizer that derives the
 * reading order from them, the row ids it makes, and the span each
 * section type asks for. The cases in tests/fixtures/layout are shared
 * with the editor's own tests, so both sides read a document alike.
 */
class XelgaLayoutTest extends TestCase
{
    use RefreshDatabase;
    use UsesSampleExtension;
    use XelgaExtensionHelpers;
    use XelgaTestHelpers;

    private const FIXTURES = __DIR__ . '/../fixtures/layout';

    private const EXTENSION = 'tests/fixtures/extensions/sample-extension/resources/xelga';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpXelgaWithExtension();
    }

    public function test_normalizer_cases(): void
    {
        // content-width/ holds the cases that measure a column beside the
        // sides, areas/ the cases of pages with areas.
        $files = [
            ...(glob(self::FIXTURES . '/[!_]*.json') ?: []),
            ...(glob(self::FIXTURES . '/content-width/[!_]*.json') ?: []),
            ...(glob(self::FIXTURES . '/areas/[!_]*.json') ?: []),
        ];
        $this->assertNotEmpty(glob(self::FIXTURES . '/areas/[!_]*.json'));

        foreach ($files as $file) {
            $case = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
            $rules = $this->fakeRules($case['rules']);
            $name = $case['name'];

            $result = Layout::normalize($case['input'], $rules);
            $this->assertSame($case['expected'], $result['content'], "{$name}: content");
            $this->assertSame($case['moved'], $result['moved'], "{$name}: moved");

            $again = Layout::normalize($case['expected'], $rules);
            $this->assertSame($case['expected'], $again['content'], "{$name}: normalizing twice changes nothing");
            $this->assertSame([], $again['moved'], "{$name}: normalizing twice moves nothing");

            if (isset($case['rows'])) {
                // The cases with areas give rows() the rules, so their rows carry an area.
                $withRules = str_contains($file, '/areas/') ? $rules : null;
                $this->assertSame($case['rows'], Layout::rows($case['input'], $withRules), "{$name}: rows");
            }
        }
    }

    public function test_every_shipped_and_store_document_is_unchanged_and_idempotent(): void
    {
        $documents = [];
        foreach (Defaults::handles() as $handle) {
            $documents["shipped {$handle}"] = [$handle, Defaults::shipped($handle)];
        }
        foreach (StoreTemplates::slugs() as $slug) {
            foreach (StoreTemplates::get($slug)['documents'] as $handle => $content) {
                $documents["store {$slug} {$handle}"] = [(string) $handle, $content];
            }
        }
        $documents['extension sample_page'] = ['sample_page', json_decode((string) file_get_contents(base_path(self::EXTENSION . '/templates/sample_page.json')), true)];
        $this->assertGreaterThan(20, count($documents));

        foreach ($documents as $name => [$handle, $content]) {
            $rules = app(LayoutRules::class, ['handle' => $handle]);
            $this->assertSame(['content' => $content, 'moved' => []], Layout::normalize($content, $rules), "{$name} is unchanged");

            if (Handles::kind($handle) === 'settings') {
                continue;
            }
            // The same document with its implied rows written out reads back as it was.
            $withRows = $content + ['rows' => Layout::rows($content)];
            $this->assertSame(['content' => $content, 'moved' => []], Layout::normalize($withRows, $rules), "{$name} with its rows written out");
        }
    }

    public function test_section_span_reads_section_and_variant(): void
    {
        $this->assertInstanceOf(CatalogLayoutRules::class, app(LayoutRules::class, ['handle' => 'home']));

        $this->assertSame(12, Catalog::section('hero')->minSpan());
        $this->assertSame(['min' => 12, 'variants' => []], Catalog::section('hero')->spans());
        $this->assertSame(3, Catalog::section('sample-extension.tile')->minSpan());

        $schema = new SectionSchema([
            'type' => 'demo-gallery',
            'span' => ['min' => 4],
            'variants' => [['value' => 'grid', 'label' => 'Grid'], ['value' => 'mosaic', 'label' => 'Mosaic', 'span' => 8]],
            'settings' => [['key' => 'variant', 'type' => 'select', 'label' => 'Layout', 'default' => 'grid']],
        ]);
        $this->assertSame(4, $schema->minSpan());
        $this->assertSame(4, $schema->minSpan('grid'));
        $this->assertSame(8, $schema->minSpan('mosaic'));
        $this->assertSame(4, $schema->minSpan('unknown'));
        $this->assertSame(['min' => 4, 'variants' => ['mosaic' => 8]], $schema->spans());

        // The rules read the section's chosen variant, else the variant setting's default.
        $spec = ['label' => 'Gallery', 'templates' => ['home']] + $schema->raw;
        $spec['settings'][0]['default'] = 'mosaic';
        $this->withSchema('sample-extension.gallery', $spec, function () {
            $this->assertNotNull(Catalog::section('sample-extension.gallery'));
            $rules = app(LayoutRules::class, ['handle' => 'home']);
            $this->assertSame(8, $rules->minSpan(['type' => 'sample-extension.gallery', 'settings' => []]));
            $this->assertSame(4, $rules->minSpan(['type' => 'sample-extension.gallery', 'settings' => ['variant' => 'grid']]));
            $this->assertSame(8, $rules->minSpan(['type' => 'sample-extension.gallery', 'settings' => ['variant' => 'mosaic']]));
            $this->assertSame(8, $rules->minSpan(['type' => 'sample-extension.gallery', 'settings' => (object) ['variant' => 'mosaic']]));
        });

        $rules = app(LayoutRules::class, ['handle' => 'product']);
        $this->assertSame(3, $rules->minSpan(['type' => 'sample-extension.tile', 'settings' => []]));
        $this->assertSame(12, $rules->minSpan(['type' => 'hero', 'settings' => []]));
        $this->assertSame(3, $rules->minSpan(['type' => 'no-such-section']), 'An unknown or dormant section never moves.');
        $this->assertTrue($rules->isMain(['type' => 'product-main']));
        $this->assertFalse($rules->isMain(['type' => 'sample-extension.tile']));
        $this->assertFalse($rules->isMain(['type' => 'no-such-section']));
    }

    public function test_the_shared_layout_fixture_matches_the_payload(): void
    {
        $shared = json_decode((string) file_get_contents(self::FIXTURES . '/_layout.json'), true, flags: JSON_THROW_ON_ERROR);

        // The fixture names the theme's own page types; with an extension installed the payload adds its page types.
        $payload = Layout::payload();
        $payload['sides_on'] = array_values(array_intersect($payload['sides_on'], Handles::TEMPLATES));
        $this->assertSame($shared, $payload);
        $this->assertSame(Layout::ROW_CHOICES, $shared['row_choices']);
        $this->assertSame(array_keys(Layout::SPLITS), array_keys($shared['splits']));
    }

    public function test_row_ids_lengthen_while_taken(): void
    {
        $this->assertSame('row-86f7e437', Layout::rowIdFor('a', []));
        $this->assertSame('row-86f7e437fa', Layout::rowIdFor('a', ['row-86f7e437']));
        $this->assertSame('row-86f7e437faa5', Layout::rowIdFor('a', ['row-86f7e437', 'row-86f7e437fa']));
    }

    /**
     * Rules as a case file states them: spans by type (12 when not listed),
     * main types, whether the page may have areas and sides, and the types
     * that keep a row of their own or belong in Content.
     */
    private function fakeRules(array $rules): LayoutRules
    {
        return new class($rules) implements LayoutRules
        {
            public function __construct(private readonly array $rules) {}

            public function minSpan(array $section): int
            {
                return (int) ($this->rules['min'][$section['type'] ?? ''] ?? 12);
            }

            public function isMain(array $section): bool
            {
                return in_array($section['type'] ?? null, $this->rules['main'], true);
            }

            public function sidesAllowed(): bool
            {
                return (bool) $this->rules['sides'];
            }

            public function ownRow(array $section): bool
            {
                return in_array($section['type'] ?? null, $this->rules['own_row'] ?? [], true);
            }

            public function contentOnly(array $section): bool
            {
                return in_array($section['type'] ?? null, $this->rules['content_only'] ?? [], true);
            }

            public function pageTypeKnown(): bool
            {
                return true;
            }
        };
    }

    /** Run $check with an extra section schema in the sample extension, removed afterwards. */
    private function withSchema(string $type, array $spec, callable $check): void
    {
        $file = base_path(self::EXTENSION . "/schemas/sections/{$type}.json");
        file_put_contents($file, json_encode(['type' => $type] + $spec));
        try {
            $this->forgetXelgaState();
            $check();
        } finally {
            unlink($file);
            $this->forgetXelgaState();
        }
    }
}
