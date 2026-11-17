<?php

namespace Tests\Support;

use App\Services\ExtensionManager;
use App\Services\ThemeService;
use Illuminate\Support\Facades\File;

/**
 * A second theme for tests of the theme platform itself.
 *
 * Every test runs on the sample theme (tests/fixtures/themes/sample), but
 * much of the platform only matters once there are two: installing a
 * package, switching themes, a theme that brings its own editor. This
 * builds "sample-two" for the length of a test: a copy of the sample with
 * an editor of its own. It is a copy, so a test may change its files
 * without touching the fixture, and it is removed when the test ends.
 */
trait UsesSampleTheme
{
    protected string $sampleTheme = 'sample-two';

    /** @param  array<string, mixed>  $overrides  keys laid over the copy's theme.json */
    protected function makeSampleTheme(array $overrides = []): void
    {
        $source = base_path('tests/fixtures/themes/sample');
        $build = storage_path('framework/testing/themes/'.$this->sampleTheme);
        $link = resource_path('themes/'.$this->sampleTheme);

        File::deleteDirectory($build);
        File::copyDirectory($source, $build);

        $manifest = json_decode((string) file_get_contents($build.'/theme.json'), true);
        $manifest['name'] = 'Sample Two';
        $manifest['version'] = '1.0.0';
        $manifest['editor'] = 'admin.dashboard';
        $manifest = array_replace_recursive($manifest, $overrides);
        file_put_contents($build.'/theme.json', json_encode($manifest, JSON_PRETTY_PRINT));

        if (is_link($link)) {
            unlink($link);
        }
        symlink($build, $link);

        $this->beforeApplicationDestroyed(function () use ($build, $link) {
            if (is_link($link)) {
                unlink($link);
            }
            File::deleteDirectory($build);
        });

        // Discovery is cached per instance; let the next lookup see the new folder.
        app()->forgetInstance(ExtensionManager::class);
    }

    /** @param  array<string, mixed>  $manifest  keys laid over the sample's theme.json */
    protected function installSampleTheme(bool $activate = false, array $manifest = []): void
    {
        $this->makeSampleTheme($manifest);
        app(ExtensionManager::class)->install(ThemeService::registryName($this->sampleTheme));
        if ($activate) {
            app(ThemeService::class)->activateTheme($this->sampleTheme);
            // Its stylesheet is never compiled, so pages render without built assets.
            $this->withoutVite();
        }
        app(ThemeService::class)->clearCache();
    }
}
