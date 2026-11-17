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
 * package, a theme that brings its own editor, a theme that declares
 * system regions. This builds "sample-two" for the length of a test: a copy
 * of the sample, plus an editor of its own, system regions and placeholder
 * views for the system pieces. It is a copy, so a test may change its files
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
        // The copy draws its own system pieces, below.
        File::deleteDirectory($build.'/views/widgets/system');
        File::ensureDirectoryExists($build.'/views/widgets/system');

        $manifest = json_decode((string) file_get_contents($build.'/theme.json'), true);
        $manifest['name'] = 'Sample Two';
        $manifest['version'] = '1.0.0';
        $manifest['editor'] = 'admin.dashboard';
        $manifest['regions']['header'] = ['label' => 'Header', 'system' => true, 'page' => '*', 'defaults' => ['system.header']];
        // The product page ships the way a two-column theme does: the images
        // beside the buying pieces, then a full-width piece under both.
        $manifest['regions']['product_main'] = ['label' => 'Product page', 'system' => true, 'page' => 'product', 'defaults' => [
            ['columns' => [4, 4], 'cols' => [['system.product_images'], ['system.product_name', 'system.product_price', 'system.product_add_to_cart']]],
            'system.product_tabs',
        ]];
        $manifest['regions']['category_main'] = ['label' => 'Category page', 'system' => true, 'page' => 'category', 'defaults' => ['system.category_subcategories', 'system.category_products']];
        $manifest = array_replace_recursive($manifest, $overrides);
        file_put_contents($build.'/theme.json', json_encode($manifest, JSON_PRETTY_PRINT));

        // A placeholder for every system piece its regions take, so the
        // copy passes the contract however many pieces core registers.
        // Pieces core draws itself are left to core.
        foreach (\App\Support\Widgets::all() as $key => $spec) {
            if (! empty($spec['system']) && empty($spec['theme']) && empty($spec['optional']) && str_starts_with($key, 'system.')
                && array_intersect((array) $spec['regions'], array_keys($manifest['regions'])) !== []) {
                $piece = substr($key, strlen('system.'));
                file_put_contents($build."/views/widgets/system/{$piece}.blade.php", "<div class=\"sample-{$piece}\"></div>\n");
            }
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
