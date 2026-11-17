<?php

namespace Themes\Xelga\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesSampleExtension;
use Tests\TestCase;
use Themes\Xelga\Catalog\Catalog;
use Themes\Xelga\Catalog\CatalogLinter;
use Themes\Xelga\Catalog\SectionSchema;
use Themes\Xelga\Tests\XelgaExtensionHelpers;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';
require_once __DIR__ . '/../XelgaExtensionHelpers.php';

/**
 * The group of tiles the section picker shows each section type in: what
 * the linter holds the theme's own schemas to, and what the editor's boot
 * data carries for every type.
 */
class XelgaPickerGroupTest extends TestCase
{
    use RefreshDatabase;
    use UsesSampleExtension;
    use XelgaExtensionHelpers;
    use XelgaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpXelgaWithExtension();
    }

    /** Run $check on the linter's problems with a throwaway section of the theme, removed afterwards. */
    private function withOwnSection(array $extra, callable $check): void
    {
        $schema = Catalog::root('schemas/sections/zz-probe.json');
        file_put_contents($schema, json_encode($extra + [
            'type' => 'zz-probe', 'label' => 'Probe', 'group' => 'template', 'templates' => ['home'],
            'class' => 's-zz-probe', 'settings' => [],
        ]));
        try {
            $check(CatalogLinter::check());
        } finally {
            @unlink($schema);
            Catalog::flush();
        }
    }

    public function test_lint_accepts_only_the_four_picker_groups(): void
    {
        $wrong = 'Section zz-probe: picker_group must be banners, products, categories or text.';
        foreach (SectionSchema::PICKER_GROUPS as $group) {
            $this->withOwnSection(['picker_group' => $group], function (array $problems) use ($wrong) {
                $this->assertNotContains($wrong, $problems);
                $this->assertSame([], array_values(array_filter($problems, fn ($p) => str_contains($p, 'picker_group'))));
            });
        }
        foreach (['photos', 'Banners', '', 3, null, ['text']] as $group) {
            $this->withOwnSection(['picker_group' => $group], fn (array $problems) => $this->assertContains($wrong, $problems, json_encode($group)));
        }
    }

    public function test_lint_needs_a_picker_group_on_every_section_that_can_be_added(): void
    {
        $missing = 'Section zz-probe: needs a picker_group (banners, products, categories or text).';
        $this->withOwnSection([], fn (array $problems) => $this->assertContains($missing, $problems));
        // A main section and a header section are never offered, so they name none.
        $this->withOwnSection(['main' => true], fn (array $problems) => $this->assertNotContains($missing, $problems));
        $this->withOwnSection(['group' => 'header', 'templates' => []], fn (array $problems) => $this->assertNotContains($missing, $problems));

        $this->assertSame([], CatalogLinter::check());
    }

    public function test_every_section_the_picker_offers_names_its_group(): void
    {
        $offered = array_filter(Catalog::sections(), fn (array $spec) => ($spec['group'] ?? null) === 'template' && ($spec['main'] ?? false) !== true && ! isset($spec['source']));
        $this->assertNotEmpty($offered);
        foreach ($offered as $type => $spec) {
            $this->assertContains($spec['picker_group'] ?? null, SectionSchema::PICKER_GROUPS, $type);
        }
        $this->assertSame('banners', Catalog::section('hero')->pickerGroup());
        $this->assertSame('products', Catalog::section('featured-products')->pickerGroup());
        $this->assertSame('categories', Catalog::section('brand-logos')->pickerGroup());
        $this->assertSame('text', Catalog::section('usp-bar')->pickerGroup());
    }

    public function test_the_boot_payload_carries_a_picker_group_for_every_type(): void
    {
        $html = $this->actingAs($this->admin())->get(route('admin.xelga.editor'))->getContent();
        preg_match('/<script type="application\/json" id="xelga-boot">(.*?)<\/script>/s', (string) $html, $m);
        $boot = json_decode(html_entity_decode($m[1] ?? '', ENT_QUOTES), true);
        $this->assertIsArray($boot);

        $sections = $boot['catalog']['sections'];
        foreach ($sections as $type => $section) {
            $this->assertArrayHasKey('picker_group', $section, $type);
            if (isset($section['source'])) {
                // An extension's sections are grouped under the extension's name.
                $this->assertNull($section['picker_group'], $type);
                $this->assertNotSame('', $section['source_label'], $type);
            } else {
                $this->assertContains($section['picker_group'], SectionSchema::PICKER_GROUPS, $type);
                $this->assertSame(Catalog::section($type)->pickerGroup(), $section['picker_group'], $type);
            }
        }
        $this->assertSame('banners', $sections['hero']['picker_group']);
        // A type that names no group, like a header part, is shown under text and trust if it is ever offered.
        $this->assertSame('text', $sections['header']['picker_group']);
        $this->assertNull($sections['sample-extension.card']['picker_group']);
    }
}
