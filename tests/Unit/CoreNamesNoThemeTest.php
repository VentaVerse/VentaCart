<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Core never depends on the theme that ships with it: no core file names
 * that theme's classes or tables, so core keeps working with the theme
 * folder removed or another theme live. The bundled theme is named once,
 * as a folder slug, in config/themes.php.
 */
class CoreNamesNoThemeTest extends TestCase
{
    private const FOLDERS = ['app', 'config', 'database', 'routes', 'extensions', 'resources/views', 'resources/js', 'resources/css'];

    public function test_no_core_file_names_the_bundled_themes_classes_or_tables(): void
    {
        $root = dirname(__DIR__, 2);
        $found = [];

        foreach (self::FOLDERS as $folder) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $folder, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if (! $file->isFile() || ! preg_match('/\.(php|js|css|json)$/', $file->getFilename())) {
                    continue;
                }
                $text = (string) file_get_contents($file->getPathname());
                if (preg_match('/Themes\\\\+Xelga|\bxelga_/i', $text)) {
                    $found[] = substr($file->getPathname(), strlen($root) + 1);
                }
            }
        }

        $this->assertSame([], $found, 'Core names the bundled theme\'s classes or tables.');
    }
}
