<?php

namespace Themes\Xelga\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\UsesSampleExtension;
use Tests\TestCase;
use Themes\Xelga\Extensions\Extensions;
use Themes\Xelga\Extensions\Registrar;
use Themes\Xelga\Rendering\Storefront;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';

/**
 * An extension registers its parts folder by listening for `xelga.extend`.
 * Each check that fails is logged and skips the part; nothing throws.
 */
class XelgaExtensionRegistrationTest extends TestCase
{
    use RefreshDatabase;
    use UsesSampleExtension;
    use XelgaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installXelga();
        Registrar::forgetLogged();
    }

    private function fresh(): Extensions
    {
        Storefront::forgetRequestState();

        return Extensions::current();
    }

    /** Listen as an extension would, with what it hands the registrar. */
    private function listen(string $name, string $path): void
    {
        Event::listen(Registrar::EVENT, fn (Registrar $xelga) => $xelga->parts($name, $path));
    }

    public function test_without_a_listener_nothing_is_registered(): void
    {
        $this->assertSame([], $this->fresh()->all());
        $this->assertSame('', $this->fresh()->fingerprint());
    }

    public function test_the_fixture_extension_registers_its_parts(): void
    {
        $this->installSampleExtension();

        $part = $this->fresh()->get('sample-extension');

        $this->assertNotNull($part);
        $this->assertSame('Sample Extension', $part->title);
        $this->assertSame('1.0.0', $part->version);
        $this->assertSame(realpath(base_path('tests/fixtures/extensions/sample-extension/resources/xelga')), $part->path);
        $this->assertSame('Tests\\Fixtures\\Extensions\\SampleExtension\\', $part->namespace);
        $this->assertSame('extensions/sample-extension/resources/css/xelga.css', $part->stylesheet);
        $this->assertSame(['card.json', 'main.json'], array_map('basename', $part->schemaFiles()));
        $this->assertSame(['sample_page'], array_keys($part->templates()));
        $this->assertSame('card', $part->placements()[0]['key']);
        $this->assertSame('sample-extension@1.0.0', $this->fresh()->fingerprint());
        $this->assertSame('xelga-sample-extension', $part->viewNamespace());
        $this->assertTrue(view()->exists('xelga-sample-extension::sections.card'));
    }

    public function test_the_preview_address_must_be_a_page_of_this_store(): void
    {
        $this->installSampleExtension();
        $this->assertSame(url('/sample-extension/things/lamp'), $this->fresh()->previewUrl('sample_page'));

        Event::listen(Registrar::EVENT, fn (Registrar $xelga) => $xelga->previewUrl('other_page', fn () => 'https://example.com/elsewhere'));
        $this->assertNull($this->fresh()->previewUrl('other_page'));
        $this->assertNull($this->fresh()->previewUrl('unknown_page'));
    }

    public function test_a_switched_off_extension_is_skipped_and_logged(): void
    {
        $this->installSampleExtension();
        $this->switchOffSampleExtension();
        Log::spy();

        $this->assertSame([], $this->fresh()->all());
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'not installed and switched on'))->once();
    }

    /** @return array<string, array{0: string, 1: callable(): string, 2: string}> */
    public static function refusals(): array
    {
        $fixture = fn () => base_path('extensions/sample-extension/resources/xelga');

        return [
            'a malformed name' => ['Sample Extension', $fixture, 'lower case letters'],
            'an extension that is not installed' => ['store-credit', $fixture, 'not installed and switched on'],
            'a folder outside the extension' => ['sample-extension', fn () => base_path('resources/themes/xelga'), 'inside the extension'],
            'the extension folder itself' => ['sample-extension', fn () => base_path('extensions/sample-extension'), 'inside the extension'],
            'a folder that does not exist' => ['sample-extension', fn () => base_path('extensions/sample-extension/resources/nothing'), 'inside the extension'],
        ];
    }

    #[DataProvider('refusals')]
    public function test_a_part_that_fails_a_check_is_skipped_and_logged(string $name, callable $path, string $reason): void
    {
        $this->linkSampleExtension();
        app(\App\Services\ExtensionManager::class)->install('sample-extension');
        Log::spy();
        $this->listen($name, $path());

        $this->assertSame([], $this->fresh()->all());
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, $reason))->once();
    }

    public function test_a_second_registration_of_the_same_extension_is_skipped(): void
    {
        $this->installSampleExtension();
        Log::spy();
        $this->listen('sample-extension', base_path('extensions/sample-extension/resources/xelga'));

        $this->assertCount(1, $this->fresh()->all());
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'registered already'))->once();
    }

    public function test_a_parts_folder_holding_a_link_is_skipped(): void
    {
        $link = base_path('tests/fixtures/extensions/sample-extension/resources/xelga/views/linked');
        symlink(base_path('resources/themes/xelga/views'), $link);
        try {
            $this->installSampleExtension();
            Log::spy();

            $this->assertSame([], $this->fresh()->all());
            Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'holds a link'))->once();
        } finally {
            unlink($link);
        }
    }

    public function test_a_parts_folder_for_another_api_or_with_a_stylesheet_elsewhere_is_skipped(): void
    {
        $file = base_path('tests/fixtures/extensions/sample-extension/resources/xelga/xelga.json');
        $original = (string) file_get_contents($file);
        try {
            $this->installSampleExtension();
            foreach ([
                ['{"api": 2}', 'hooks API'],
                ['{"api": 1, "stylesheet": "../../../../../../.env"}', 'stylesheet'],
                ['{"api": 1, "stylesheet": "src/Xelga/Sections/Card.php"}', 'stylesheet'],
            ] as [$json, $reason]) {
                file_put_contents($file, $json);
                Registrar::forgetLogged();
                Log::spy();

                $this->assertSame([], $this->fresh()->all(), $json);
                Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, $reason))->atLeast()->once();
            }
        } finally {
            file_put_contents($file, $original);
        }
    }

    public function test_a_listener_that_throws_is_reported_and_the_storefront_still_draws(): void
    {
        Event::listen(Registrar::EVENT, fn () => throw new \RuntimeException('listener broke'));

        $this->assertSame([], $this->fresh()->all());
        $this->get('/')->assertOk();
    }
}
