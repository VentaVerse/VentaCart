<?php

namespace Tests\Feature\Banners;

use App\Models\User;
use App\Services\ExtensionManager;
use App\Support\SafeInput;
use App\Models\LayoutPlacement;
use Extensions\Banners\BannerData;
use Extensions\Banners\BannerPayload;
use Extensions\Banners\Models\Banner;
use Extensions\Banners\Models\BannerGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Banner slides are edited by whoever holds marketing, and every theme draws
 * them into links, inline styles and Alpine expressions. A slide is checked
 * when it is saved and again on its way to the storefront, so hostile input
 * never reaches a theme as written.
 */
class BannerInputTest extends TestCase
{
    use RefreshDatabase;

    private BannerGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        app(ExtensionManager::class)->install('banners');
        app(ExtensionManager::class)->bootEnabled();
        $this->actingAs(User::factory()->create());
        $this->group = BannerGroup::create(['name' => 'Hero', 'slug' => 'hero', 'is_active' => true, 'speed' => 5000, 'height' => 500, 'height_mobile' => 300]);
    }

    /** The home page with the group placed on it, as a shopper gets it. */
    private function storefront(): string
    {
        LayoutPlacement::create([
            'page_type' => 'home',
            'region' => 'content_top',
            'widget' => 'banners.group',
            'config' => ['banner_group' => 'hero'],
            'sort_order' => 0,
            'is_enabled' => true,
        ]);
        auth()->logout();

        return $this->get('/')->assertOk()->getContent();
    }

    /** @return array<string, array{string}> */
    public static function hostileLinks(): array
    {
        return [
            'script' => ['javascript:alert(1)'],
            'mixed case' => ['JaVaScRiPt:alert(1)'],
            'tab inside the scheme' => ["java\tscript:alert(1)"],
            'newline inside the scheme' => ["java\nscript:alert(1)"],
            'carriage return' => ["java\rscript:alert(1)"],
            'leading control character' => ["\x01javascript:alert(1)"],
            'no-break space' => ["java\u{00A0}script:alert(1)"],
            'zero-width space' => ["java\u{200B}script:alert(1)"],
            'character reference' => ['javascript&#58;alert(1)'],
            'named reference' => ['javascript&colon;alert(1)'],
            'data' => ['data:text/html,<script>alert(1)</script>'],
            'vbscript' => ['vbscript:msgbox(1)'],
            'protocol relative' => ['//evil.example/x'],
            'backslash' => ['/\\evil.example'],
            'quote' => ['/sale" onmouseover="alert(1)'],
            'space' => ['/sale now'],
            'credentials' => ['https://store.example@evil.example/'],
        ];
    }

    /** @dataProvider hostileLinks */
    public function test_a_hostile_link_is_refused(string $link): void
    {
        $this->assertSame('', SafeInput::link($link));
    }

    public function test_paths_anchors_and_web_addresses_are_kept(): void
    {
        foreach (['/sale', '#top', '?page=2', 'sale/shoes', 'https://example.com/a?b=1&c=2', 'http://example.test', 'mailto:hello@example.com', 'tel:+15550100'] as $link) {
            $this->assertSame($link, SafeInput::link($link), $link);
        }
        $this->assertSame('/sale', SafeInput::link('  /sale  '));
    }

    public function test_a_slide_with_a_script_link_is_not_saved(): void
    {
        $this->post(route('admin.banners.slides.store', $this->group), [
            'name' => 'Sale',
            'link' => "java\tscript:alert(document.cookie)",
            'layers' => '[]',
        ])->assertSessionHasErrors('link');

        $this->assertSame(0, Banner::count());
    }

    public function test_a_button_layer_with_a_script_link_is_not_saved(): void
    {
        $this->post(route('admin.banners.slides.store', $this->group), [
            'name' => 'Sale',
            'layers' => json_encode([['type' => 'button', 'content' => 'Buy', 'url' => "java\tscript:alert(1)"]]),
        ])->assertSessionHasErrors('layers');

        $this->assertSame(0, Banner::count());
    }

    public function test_an_unsafe_image_path_or_colour_is_not_saved(): void
    {
        $this->post(route('admin.banners.slides.store', $this->group), [
            'bg_image' => '../../.env',
            'bg_color' => 'red;background:url(//evil.example)',
            'bg_size' => 'cover;position:fixed',
            'layers' => '[]',
        ])->assertSessionHasErrors(['bg_image', 'bg_color', 'bg_size']);

        $this->assertSame(0, Banner::count());
    }

    public function test_layer_fields_are_kept_to_their_shapes_on_save(): void
    {
        $this->post(route('admin.banners.slides.store', $this->group), [
            'name' => 'Sale',
            'link' => '/sale',
            'bg_color' => '#112233',
            'bg_size' => 'contain',
            'layers' => json_encode([
                [
                    'type' => 'text',
                    'content' => "Big\nsale",
                    'x' => 'calc(1px);background:url(//evil.example)',
                    'y' => '40%',
                    'animation' => "x':0,[alert(document.domain)]:0,'y",
                    'color' => 'red;position:fixed',
                    'fontSize' => '99999',
                    'fontWeight' => '700;x',
                    'textTransform' => 'uppercase;display:none',
                    'opacity' => -5,
                    'delay' => '1e9',
                    'onclick' => 'alert(1)',
                ],
                ['type' => 'image', 'src' => 'banners/hero (1).jpg', 'width' => 300, 'height' => '', 'animation' => 'zoom-in'],
                ['type' => 'button', 'content' => 'Shop', 'url' => '/shop', 'bgColor' => '#FFF', 'paddingX' => 9999],
            ]),
        ])->assertSessionHasNoErrors();

        $banner = Banner::firstOrFail();
        [$text, $image, $button] = $banner->layers;

        $this->assertSame('0px', $text['x']);
        $this->assertSame('40%', $text['y']);
        $this->assertSame('none', $text['animation']);
        $this->assertSame('#ffffff', $text['color']);
        $this->assertSame(400, $text['fontSize']);
        $this->assertSame('400', $text['fontWeight']);
        $this->assertSame('none', $text['textTransform']);
        $this->assertSame(0, $text['opacity']);
        $this->assertSame(30000, $text['delay']);
        $this->assertSame("Big\nsale", $text['content']);
        $this->assertArrayNotHasKey('onclick', $text);

        $this->assertSame('banners/hero (1).jpg', $image['src']);
        $this->assertNull($image['height']);
        $this->assertSame('zoom-in', $image['animation']);

        $this->assertSame('/shop', $button['url']);
        $this->assertSame('#fff', $button['bgColor']);
        $this->assertSame(500, $button['paddingX']);
    }

    public function test_data_stored_before_the_checks_reaches_the_storefront_cleaned(): void
    {
        // Written straight to the table, the way an older release or an
        // import could have left it.
        Banner::create([
            'banner_group_id' => $this->group->id,
            'name' => 'Old',
            'bg_image' => 'banners/a.jpg\');background:url(//evil.example/t',
            'bg_color' => 'red;background:url(//evil.example/c)',
            'bg_size' => 'cover;position:fixed',
            'link' => "java\tscript:alert(1)",
            'is_active' => true,
            'layers' => [
                ['type' => 'text', 'content' => 'Hi', 'animation' => "x':0,[alert(document.domain)]:0,'y", 'x' => '10px;top:0'],
                ['type' => 'button', 'content' => 'Go', 'url' => 'javascript:alert(2)', 'color' => 'expression(alert(3))'],
                ['type' => 'script', 'content' => 'alert(4)'],
            ],
        ]);

        $payload = BannerPayload::group('hero');
        $slide = $payload['slides'][0];

        $this->assertSame('', $slide['url']);
        $this->assertNull($slide['image']);
        $this->assertNull($slide['bg_color']);
        $this->assertSame('cover', $slide['bg_size']);
        $this->assertCount(2, $slide['layers']);
        $this->assertSame('none', $slide['layers'][0]['animation']);
        $this->assertSame('0px', $slide['layers'][0]['x']);
        $this->assertSame('', $slide['layers'][1]['url']);
        $this->assertSame('#000000', $slide['layers'][1]['color']);

        $html = $this->storefront();

        $this->assertStringNotContainsString('alert', $html);
        $this->assertStringNotContainsString('evil.example', $html);
        $this->assertStringNotContainsString('javascript', $html);
    }

    public function test_the_default_theme_quotes_and_encodes_the_background_image(): void
    {
        Banner::create([
            'banner_group_id' => $this->group->id,
            'bg_image' => 'banners/hero (1).jpg',
            'is_active' => true,
            'layers' => [],
        ]);

        $html = $this->storefront();

        $this->assertStringContainsString("background-image: url(&#039;/storage/banners/hero%20%281%29.jpg&#039;)", $html);
    }

    public function test_a_slide_is_only_reached_through_its_own_group(): void
    {
        $other = BannerGroup::create(['name' => 'Other', 'slug' => 'other', 'is_active' => true, 'speed' => 5000, 'height' => 500, 'height_mobile' => 300]);
        $banner = Banner::create(['banner_group_id' => $other->id, 'name' => 'Theirs', 'is_active' => true]);

        $this->get(route('admin.banners.slides.edit', [$this->group, $banner]))->assertNotFound();
        $this->delete(route('admin.banners.slides.destroy', [$this->group, $banner]))->assertNotFound();
        $this->assertTrue($banner->fresh()->exists);
    }

    public function test_every_animation_the_editor_offers_is_allowed(): void
    {
        $editor = file_get_contents(base_path('extensions/banners/resources/views/editor.blade.php'));
        preg_match_all('/<option value="([a-z-]+)">/', substr($editor, strpos($editor, 'currentLayer.animation')), $m);

        $offered = array_unique(array_filter($m[1], fn ($v) => ! in_array($v, ['300', '400'], true)));
        $this->assertNotEmpty($offered);
        foreach ($offered as $animation) {
            $this->assertContains($animation, BannerData::ANIMATIONS);
        }
    }
}
