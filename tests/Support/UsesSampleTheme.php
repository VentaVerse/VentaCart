<?php

namespace Tests\Support;

use App\Services\ExtensionManager;
use App\Services\ThemeService;
use Illuminate\Support\Facades\File;

/**
 * A second theme for tests of the theme platform itself.
 *
 * Core ships one theme, but much of the platform only matters once there
 * are two: installing a package, a theme that brings its own editor, a
 * theme that declares system regions. This builds "sample" for the length
 * of a test: the default theme's manifest, styles and views, plus an editor
 * of its own, system regions and placeholder views for the system pieces.
 * It is assembled from the default theme each time, so it never drifts
 * from it, and removed when the test ends.
 */
trait UsesSampleTheme
{
    protected string $sampleTheme = 'sample';

    protected function makeSampleTheme(): void
    {
        $default = resource_path('themes/'.ThemeService::THEME);
        $build = storage_path('framework/testing/themes/'.$this->sampleTheme);
        $link = resource_path('themes/'.$this->sampleTheme);

        File::deleteDirectory($build);
        File::ensureDirectoryExists($build.'/views/widgets/system');

        $manifest = json_decode((string) file_get_contents($default.'/theme.json'), true);
        $manifest['name'] = 'Sample';
        $manifest['editor'] = 'admin.dashboard';
        $manifest['regions']['header'] = ['label' => 'Header', 'system' => true, 'page' => '*', 'defaults' => ['system.header']];
        $manifest['regions']['product_main'] = ['label' => 'Product page', 'system' => true, 'page' => 'product', 'defaults' => ['system.product_buy']];
        file_put_contents($build.'/theme.json', json_encode($manifest, JSON_PRETTY_PRINT));

        foreach (['css', 'js'] as $dir) {
            symlink($default.'/'.$dir, $build.'/'.$dir);
        }
        foreach (File::directories($default.'/views') as $dir) {
            if (basename($dir) !== 'widgets') {
                symlink($dir, $build.'/views/'.basename($dir));
            }
        }
        foreach (File::files($default.'/views') as $file) {
            symlink($file->getPathname(), $build.'/views/'.$file->getFilename());
        }
        foreach (File::directories($default.'/views/widgets') as $dir) {
            symlink($dir, $build.'/views/widgets/'.basename($dir));
        }
        foreach (['cart', 'header', 'logo', 'nav', 'product_brand', 'product_buy', 'product_related', 'product_tabs', 'search', 'topbar'] as $piece) {
            file_put_contents($build."/views/widgets/system/{$piece}.blade.php", "<div class=\"sample-{$piece}\"></div>\n");
        }

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

    protected function installSampleTheme(bool $activate = false): void
    {
        $this->makeSampleTheme();
        app(ExtensionManager::class)->install(ThemeService::registryName($this->sampleTheme));
        if ($activate) {
            app(ThemeService::class)->activateTheme($this->sampleTheme);
            // Its stylesheet is never compiled, so pages render without built assets.
            $this->withoutVite();
        }
        app(ThemeService::class)->clearCache();
    }
}
