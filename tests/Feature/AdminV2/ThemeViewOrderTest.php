<?php

namespace Tests\Feature\AdminV2;

use App\Http\Middleware\RegisterThemeViews;
use App\Services\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\Support\UsesSampleTheme;
use Tests\TestCase;

/**
 * The live theme's views win, even in a process that has served another
 * theme before (a long-running worker after a theme switch).
 */
class ThemeViewOrderTest extends TestCase
{
    use RefreshDatabase;
    use UsesSampleTheme;

    public function test_switching_back_puts_the_live_themes_folder_first_again(): void
    {
        $this->installSampleTheme(activate: true);
        RegisterThemeViews::register();
        $this->assertSame(realpath(ThemeService::root('sample/views')), View::getFinder()->getPaths()[0]);

        app(ThemeService::class)->activateTheme(ThemeService::THEME);
        app(ThemeService::class)->clearCache();
        RegisterThemeViews::register();

        $paths = View::getFinder()->getPaths();
        $this->assertSame(realpath(ThemeService::root(ThemeService::THEME.'/views')), $paths[0]);
        $this->assertSame(1, count(array_keys($paths, $paths[0], true)), 'Registered once, not again on every request.');
    }
}
