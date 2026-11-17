<?php

namespace Themes\Xelga\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Support\UsesSampleExtension;
use Tests\TestCase;
use Themes\Xelga\Catalog\Catalog;
use Themes\Xelga\Catalog\CatalogLinter;
use Themes\Xelga\Extensions\Registrar;
use Themes\Xelga\Tests\XelgaExtensionHelpers;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';
require_once __DIR__ . '/../XelgaExtensionHelpers.php';

/**
 * Section types from an extension join the catalog after the theme's own,
 * named `{extension}.{name}`, drawn with the extension's view and data class.
 */
class XelgaExtensionSectionsTest extends TestCase
{
    use RefreshDatabase;
    use UsesSampleExtension;
    use XelgaExtensionHelpers;
    use XelgaTestHelpers;

    private const FIXTURE = 'tests/fixtures/extensions/sample-extension/resources/xelga';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpXelgaWithExtension();
    }

    /** Run $check with an extra schema file in the fixture, removed afterwards. */
    private function withSchema(string $name, array $spec, callable $check): void
    {
        $file = base_path(self::FIXTURE . "/schemas/sections/{$name}.json");
        file_put_contents($file, json_encode($spec));
        try {
            $this->forgetXelgaState();
            Registrar::forgetLogged();
            $check();
        } finally {
            unlink($file);
            $this->forgetXelgaState();
        }
    }

    public function test_extension_types_join_the_catalog_stamped_with_their_source(): void
    {
        $card = Catalog::sections()['sample-extension.card'] ?? null;

        $this->assertNotNull($card);
        $this->assertSame('sample-extension', $card['source']);
        $this->assertSame('Sample Extension', $card['source_label']);
        $this->assertSame(realpath(base_path(self::FIXTURE)) . '/views/sections/sample-extension.card.blade.php', Catalog::section('sample-extension.card')->viewFile());
        $this->assertSame('s-sample-extension--card', $card['class']);
        $this->assertSame('Tests\\Fixtures\\Extensions\\SampleExtension\\Xelga\\Sections\\Card', $card['data_class']);

        $types = array_keys(Catalog::sections());
        $this->assertSame(['sample-extension.card', 'sample-extension.main', 'sample-extension.tile'], array_slice($types, -3), 'After the theme\'s own types.');
        $this->assertNull(Catalog::section('hero')->source());
        $this->assertSame('xelga::sections.hero', Catalog::section('hero')->view());
    }

    public function test_the_cache_key_follows_the_extension_and_its_version(): void
    {
        Catalog::sections();
        $cached = include app()->bootstrapPath('cache/xelga-catalog.php');
        $this->assertStringContainsString('|sample-extension@1.0.0', $cached['key']);

        $this->switchOffSampleExtension();
        $this->forgetXelgaState();

        $this->assertArrayNotHasKey('sample-extension.card', Catalog::sections());
        $cached = include app()->bootstrapPath('cache/xelga-catalog.php');
        $this->assertStringEndsWith('|', $cached['key']);
    }

    public function test_the_card_draws_with_its_view_and_data_class_on_the_product_page(): void
    {
        $this->product('Linen Shirt');
        $this->publishedDocument('product', $this->productWith(['card-1' => $this->card(['link' => ['type' => 'url', 'href' => 'https://example.com/shops/demo-store']])]));

        $this->page('/product/linen-shirt')
            ->assertSee('class="s s-sample-extension--card"', false)
            ->assertSee('data-section-type="sample-extension.card"', false)
            ->assertSee('data-size="70"', false)
            ->assertSee('Sold by Demo Store')
            ->assertSee('Ships tomorrow')
            ->assertSee('<a href="https://example.com/shops/demo-store" data-sample-link>', false);
    }

    /** @return array<string, array{0: string, 1: array, 2: string}> */
    public static function badSchemas(): array
    {
        return [
            'a type not named after the extension' => ['marketplace.other', ['type' => 'marketplace.other', 'label' => 'X'], 'its type must be'],
            'a type with a hyphen instead of a dot' => ['sample-extension-other', ['type' => 'sample-extension-other', 'label' => 'X'], 'its type must be'],
            'a file not named after its type' => ['other', ['type' => 'sample-extension.other', 'label' => 'X'], 'its type must be'],
            'a header section' => ['sample-extension.other', ['type' => 'sample-extension.other', 'label' => 'X', 'group' => 'header'], 'page templates only'],
            'scheme colors' => ['sample-extension.other', ['type' => 'sample-extension.other', 'label' => 'X', 'settings' => [['key' => 'c', 'type' => 'scheme-colors', 'label' => 'C']]], 'cannot use'],
            'an unknown setting type' => ['sample-extension.other', ['type' => 'sample-extension.other', 'label' => 'X', 'blocks' => ['types' => [['type' => 'b', 'settings' => [['key' => 's', 'type' => 'script', 'label' => 'S']]]]]], 'cannot use'],
            'a data class of another namespace' => ['sample-extension.other', ['type' => 'sample-extension.other', 'label' => 'X', 'data_class' => 'Themes\\Xelga\\Sections\\Header'], 'own namespace'],
            'a data class that is not one' => ['sample-extension.other', ['type' => 'sample-extension.other', 'label' => 'X', 'data_class' => 'Tests\\Fixtures\\Extensions\\SampleExtension\\SampleExtensionServiceProvider'], 'implement'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badSchemas')]
    public function test_a_schema_xelga_does_not_accept_is_skipped_and_logged(string $name, array $spec, string $reason): void
    {
        $this->withSchema($name, $spec, function () use ($reason) {
            Log::spy();

            $types = array_keys(Catalog::sections());

            $this->assertContains('sample-extension.card', $types, 'The other sections of the extension stay.');
            $this->assertSame([], array_values(array_filter($types, fn ($t) => str_contains($t, 'other'))));
            $this->assertContains('hero', $types);
            Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, $reason))->once();
        });
    }

    public function test_the_linter_passes_the_fixture_and_names_what_a_broken_copy_lacks(): void
    {
        $this->assertSame([], CatalogLinter::checkExtension('sample-extension'));
        $this->assertNotSame([], CatalogLinter::checkExtension('not-installed'));

        $css = base_path('tests/fixtures/extensions/sample-extension/resources/css/xelga.css');
        $original = (string) file_get_contents($css);
        try {
            file_put_contents($css, str_replace('[data-size="70"]{--size:70}', '', $original));
            $this->withSchema('sample-extension.broken', ['type' => 'sample-extension.broken', 'label' => 'Broken', 'templates' => ['nowhere'], 'settings' => []], function () {
                $problems = CatalogLinter::checkExtension('sample-extension');

                $this->assertContains('sample-extension: section sample-extension.broken: unknown template nowhere.', $problems);
                $this->assertContains('sample-extension: section sample-extension.broken: views/sections/sample-extension.broken.blade.php is missing.', $problems);
                $this->assertNotEmpty(array_filter($problems, fn ($p) => str_contains($p, 'no rule for size = 70')));
            });
        } finally {
            file_put_contents($css, $original);
        }
    }

    public function test_the_theme_lint_is_unchanged_by_an_extension(): void
    {
        $this->assertSame([], CatalogLinter::check());
    }
}
