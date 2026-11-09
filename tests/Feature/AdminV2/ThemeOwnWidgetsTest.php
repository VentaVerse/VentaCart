<?php

namespace Tests\Feature\AdminV2;

use App\Models\LayoutPlacement;
use App\Services\LayoutSaver;
use App\Services\ThemeService;
use App\Support\LayoutInventory;
use App\Support\Regions;
use App\Support\ThemeContract;
use App\Support\Widgets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesSampleTheme;
use Tests\TestCase;

/**
 * A theme's own widgets stay with that theme. Every installed theme boots,
 * live or not, so these tests stand two themes side by side: the default
 * one and the sample. Each sees the shared widgets (core's and the
 * extensions') plus its own, never the other's.
 */
class ThemeOwnWidgetsTest extends TestCase
{
    use RefreshDatabase;
    use UsesSampleTheme;

    private const SAMPLE_ONLY = 'sample.ticker';

    private const ELSEWHERE = 'elsewhere.ticker';

    protected function setUp(): void
    {
        parent::setUp();
        $this->installSampleTheme();

        Widgets::register(self::SAMPLE_ONLY, ['title' => 'Sample ticker', 'module' => 'sample', 'theme' => 'sample', 'view' => 'widgets.sample.ticker']);
        // A theme that is not on disk: its widgets must reach no one.
        Widgets::register(self::ELSEWHERE, ['title' => 'Elsewhere ticker', 'module' => 'elsewhere', 'theme' => 'elsewhere', 'view' => 'widgets.elsewhere.ticker']);
    }

    protected function tearDown(): void
    {
        Widgets::forget(self::SAMPLE_ONLY);
        Widgets::forget(self::ELSEWHERE);
        Widgets::forget('shared.key');
        parent::tearDown();
    }

    private function asTheme(string $slug): void
    {
        app(ThemeService::class)->overlay(['identity' => ['active_theme' => $slug]]);
    }

    public function test_the_live_default_theme_does_not_see_another_themes_widget(): void
    {
        $this->assertFalse(Widgets::has(self::SAMPLE_ONLY));
        $this->assertArrayNotHasKey(self::SAMPLE_ONLY, Widgets::all());
        $this->assertNotContains(self::SAMPLE_ONLY, array_column(LayoutInventory::catalogue(Regions::all()), 'key'));
    }

    public function test_a_theme_sees_its_own_widget_and_the_shared_ones(): void
    {
        $this->asTheme('sample');

        $this->assertTrue(Widgets::has(self::SAMPLE_ONLY));
        $keys = array_column(LayoutInventory::catalogue(Regions::all()), 'key');
        $this->assertContains(self::SAMPLE_ONLY, $keys);
        $this->assertContains('catalog.products', $keys, 'Core widgets are shared.');
        $this->assertNotContains(self::ELSEWHERE, $keys, 'Another theme\'s widget never reaches it.');
    }

    public function test_two_themes_may_register_the_same_key_without_replacing_each_other(): void
    {
        Widgets::register('shared.key', ['title' => 'Sample version', 'theme' => 'sample']);
        Widgets::register('shared.key', ['title' => 'Elsewhere version', 'theme' => 'elsewhere']);

        $this->asTheme('sample');
        $this->assertSame('Sample version', Widgets::get('shared.key')['title']);

        Widgets::forget('shared.key');
        $this->assertNull(Widgets::get('shared.key'), 'Forgetting a key forgets it for every owner.');
    }

    public function test_a_layout_cannot_save_another_themes_widget(): void
    {
        app(LayoutSaver::class)->save('home', [
            'columns' => ['left' => 0, 'right' => 0],
            'rows' => [[
                'id' => null, 'region' => 'content_top', 'columns' => [8], 'enabled' => true,
                'placements' => [
                    ['widget' => self::SAMPLE_ONLY, 'column' => 0, 'config' => []],
                    ['widget' => 'content.text', 'column' => 0, 'config' => []],
                ],
            ]],
        ]);

        $this->assertSame(['content.text'], LayoutPlacement::pluck('widget')->all());
    }

    public function test_the_install_check_asks_a_theme_only_for_its_own_and_the_shared_views(): void
    {
        $problems = ThemeContract::check(ThemeService::root('sample'));

        $this->assertContains('View [widgets.sample.ticker] is missing.', $problems, 'Its own widget needs its view.');
        $this->assertNotContains('View [widgets.elsewhere.ticker] is missing.', $problems);
    }
}
