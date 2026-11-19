<?php

namespace Tests\Feature\Media;

use App\Support\MediaLibrary;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The developer page on the media library is what a theme or extension
 * author reads before calling window.VentaCart.media. Every option the
 * library reads, and every field it hands back, must be described there,
 * so an option added to the code without a line in the docs fails here.
 */
class MediaDocsTest extends TestCase
{
    private const DOC = 'docs/media-library.md';

    private function doc(): string
    {
        $path = base_path(self::DOC);
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /**
     * The names read from `options` in the library's view (open and mount
     * share it) and in its entry file (opener, onClose, manage).
     *
     * @return list<string>
     */
    private function optionNames(): array
    {
        $names = [];

        foreach (['resources/js/media-library/view.js', 'resources/js/media-library/index.js'] as $file) {
            preg_match_all('/\boptions\??\.([A-Za-z_]\w*)/', (string) file_get_contents(base_path($file)), $m);
            $names = [...$names, ...$m[1]];
        }

        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }

    #[Test]
    public function every_option_the_library_reads_is_documented(): void
    {
        $doc = $this->doc();
        $names = $this->optionNames();

        // Guards the pattern itself: the library reads more than a dozen.
        $this->assertGreaterThanOrEqual(10, count($names), 'Too few option names found; the pattern no longer matches the code.');

        foreach ($names as $name) {
            $this->assertStringContainsString("`{$name}`", $doc, "Option {$name} is read by the media library but not described in " . self::DOC . '.');
        }
    }

    #[Test]
    public function every_field_of_a_chosen_image_is_documented(): void
    {
        $doc = $this->doc();
        $view = (string) file_get_contents(base_path('resources/js/media-library/view.js'));

        $this->assertSame(1, preg_match('/function describe\(file\) \{\s*return \{(.*?)\};/s', $view, $m), 'describe() not found in view.js.');
        preg_match_all('/^\s*(\w+):/m', $m[1], $fields);

        $this->assertNotEmpty($fields[1]);
        foreach ($fields[1] as $field) {
            $this->assertStringContainsString("`{$field}`", $doc, "Field {$field} of a chosen image is not described in " . self::DOC . '.');
        }
    }

    #[Test]
    public function the_limits_and_permissions_match_the_code(): void
    {
        $doc = $this->doc();

        $this->assertStringContainsString((string) (MediaLibrary::MAX_KB / 1024) . ' MB', $doc);
        $this->assertStringContainsString((string) MediaLibrary::MAX_SIDE, $doc);
        $this->assertStringContainsString((string) MediaLibrary::PER_PAGE, $doc);

        foreach (['media.view', 'media.create', 'media.edit', 'media.delete', 'media-reads', 'media-writes', 'media-imports', 'ventacart:media-ready', 'media:dimensions'] as $term) {
            $this->assertStringContainsString($term, $doc);
        }
    }
}
