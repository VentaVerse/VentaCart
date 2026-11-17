<?php

namespace Themes\Xelga\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Themes\Xelga\Tests\XelgaTestHelpers;
use Themes\Xelga\Catalog\Catalog;
use Themes\Xelga\Documents\Document;
use Themes\Xelga\Settings\SanitizeContext;
use Themes\Xelga\Settings\Sanitizer;

require_once __DIR__ . '/../XelgaTestHelpers.php';

/**
 * Hostile values never reach storage: each setting type corrects what does
 * not fit and reports the change, through the editor's own endpoint.
 */
class XelgaSanitizerTest extends TestCase
{
    use RefreshDatabase;
    use XelgaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installXelga();
    }

    private function slide(array $settings): array
    {
        $home = $this->defaults('home');
        $home['sections']['hero-1']['blocks']['hero-1-b1']['settings'] = array_merge($home['sections']['hero-1']['blocks']['hero-1-b1']['settings'], $settings);

        return $home;
    }

    private function savedSlide(): array
    {
        return Document::where('handle', 'home')->firstOrFail()->draft['sections']['hero-1']['blocks']['hero-1-b1']['settings'];
    }

    private function adjustedPaths($response): array
    {
        return array_column($response->json('data.adjusted'), 'path');
    }

    public function test_script_in_rich_text_is_stripped(): void
    {
        $footer = $this->defaults('footer');
        $footer['sections']['footer-1']['block_order'][] = 'footer-1-text';
        $footer['sections']['footer-1']['blocks']['footer-1-text'] = [
            'type' => 'text', 'label' => 'About', 'disabled' => false,
            'settings' => ['heading' => 'About us', 'text' => '<p onclick="steal()">Hello <strong>there</strong><script>alert(1)</script><img src=x onerror=alert(2)><a href="javascript:alert(3)">click</a> <a href="https://example.com/a">ok</a></p><style>body{}</style>'],
        ];

        $response = $this->saveDraft($this->admin(), 'footer', $footer, 0)->assertOk();
        $this->assertContains('content.sections.footer-1.blocks.footer-1-text.settings.text', $this->adjustedPaths($response));

        $html = Document::where('handle', 'footer')->firstOrFail()->draft['sections']['footer-1']['blocks']['footer-1-text']['settings']['text'];
        foreach (['<script', 'alert(1)', 'onclick', 'onerror', '<img', 'javascript:', '<style'] as $bad) {
            $this->assertStringNotContainsString($bad, $html);
        }
        $this->assertStringContainsString('<strong>there</strong>', $html);
        $this->assertStringContainsString('href="https://example.com/a"', $html);
    }

    public function test_javascript_and_data_links_are_removed(): void
    {
        $response = $this->saveDraft($this->admin(), 'home', $this->slide([
            'button_link' => ['type' => 'url', 'href' => 'javascript:alert(1)'],
            'button2_link' => 'data:text/html;base64,PHNjcmlwdD4=',
        ]), 0)->assertOk();

        $slide = $this->savedSlide();
        $this->assertNull($slide['button_link']);
        $this->assertNull($slide['button2_link']);
        $this->assertContains('content.sections.hero-1.blocks.hero-1-b1.settings.button_link', $this->adjustedPaths($response));
    }

    public function test_structured_links_are_kept(): void
    {
        $this->saveDraft($this->admin(), 'home', $this->slide([
            'button_link' => ['type' => 'category', 'id' => 12],
            'button2_link' => ['type' => 'url', 'href' => 'https://example.com/size-guide'],
        ]), 0)->assertOk();

        $slide = $this->savedSlide();
        $this->assertSame(['type' => 'category', 'id' => 12], $slide['button_link']);
        $this->assertSame(['type' => 'url', 'href' => 'https://example.com/size-guide'], $slide['button2_link']);
    }

    public function test_out_of_range_numbers_are_clamped_and_snapped(): void
    {
        $home = $this->slide(['overlay' => 500]);
        $home['sections']['hero-1']['settings']['interval'] = '7.6';

        $response = $this->saveDraft($this->admin(), 'home', $home, 0)->assertOk();
        $settings = Document::where('handle', 'home')->firstOrFail()->draft['sections']['hero-1']['settings'];

        $this->assertSame(80, $this->savedSlide()['overlay']);
        $this->assertSame(7.5, $settings['interval']);
        $this->assertContains('content.sections.hero-1.blocks.hero-1-b1.settings.overlay', $this->adjustedPaths($response));

        $this->saveDraft($this->admin(), 'home', $this->slide(['overlay' => 'lots']), 1)->assertOk();
        $this->assertSame(35, $this->savedSlide()['overlay']);
    }

    public function test_unknown_choices_fall_back_to_the_default(): void
    {
        $home = $this->defaults('home');
        $home['sections']['hero-1']['settings']['variant'] = 'marquee-of-doom';
        $home['sections']['hero-1']['settings']['height'] = ['large'];
        $home['sections']['hero-1']['settings']['scheme'] = 'scheme-99';
        $home['sections']['hero-1']['settings']['autoplay'] = 'yes';
        $home['sections']['hero-1']['settings']['not_a_setting'] = 'kept?';

        $response = $this->saveDraft($this->admin(), 'home', $home, 0)->assertOk();
        $settings = Document::where('handle', 'home')->firstOrFail()->draft['sections']['hero-1']['settings'];

        $this->assertSame('full-image', $settings['variant']);
        $this->assertSame('medium', $settings['height']);
        $this->assertSame('scheme-1', $settings['scheme']);
        $this->assertTrue($settings['autoplay']);
        $this->assertArrayNotHasKey('not_a_setting', $settings);
        $this->assertContains('content.sections.hero-1.settings.variant', $this->adjustedPaths($response));
    }

    public function test_images_must_be_raster_files_in_the_media_library(): void
    {
        Storage::disk('public')->put('media/banners/real.jpg', 'jpeg bytes');
        Storage::disk('public')->put('media/banners/vector.svg', '<svg onload="alert(1)"/>');

        $this->saveDraft($this->admin(), 'home', $this->slide(['image' => 'media/banners/vector.svg']), 0)->assertOk();
        $this->assertSame('', $this->savedSlide()['image']);

        $this->saveDraft($this->admin(), 'home', $this->slide(['image' => '../../.env']), 1)->assertOk();
        $this->assertSame('', $this->savedSlide()['image']);

        $this->saveDraft($this->admin(), 'home', $this->slide(['image' => 'media/banners/missing.jpg']), 2)->assertOk();
        $this->assertSame('', $this->savedSlide()['image']);

        $this->saveDraft($this->admin(), 'home', $this->slide(['image' => 'media/banners/real.jpg']), 3)->assertOk();
        $this->assertSame('media/banners/real.jpg', $this->savedSlide()['image']);
    }

    public function test_text_is_stored_as_typed_and_escaped_when_drawn(): void
    {
        $admin = $this->admin();
        $this->saveDraft($admin, 'home', $this->slide(['heading' => '<script>alert("x")</script> Sale & more', 'text' => "Line one\nLine two\x07"]), 0)->assertOk();

        $slide = $this->savedSlide();
        $this->assertSame('<script>alert("x")</script> Sale & more', $slide['heading']);
        $this->assertSame("Line one\nLine two", $slide['text']);

        $this->publish($admin, ['home' => 1])->assertOk();
        $this->asGuest();
        $this->get('/')->assertOk()
            ->assertDontSee('<script>alert("x")</script>', false)
            ->assertSee('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; Sale &amp; more', false);
    }

    public function test_a_menu_must_be_one_the_store_has(): void
    {
        $header = $this->defaults('header');
        $header['sections']['header-1']['settings']['menu'] = 'no-such-menu';

        $this->saveDraft($this->admin(), 'header', $header, 0)->assertOk();
        $this->assertSame('', Document::where('handle', 'header')->firstOrFail()->draft['sections']['header-1']['settings']['menu']);
    }

    public function test_every_type_corrects_hostile_input_directly(): void
    {
        $sanitizer = new Sanitizer(SanitizeContext::forStore(['scheme-1', 'scheme-2']));
        $hero = Catalog::section('hero');
        $slide = $hero->blockSettings('slide');

        $this->assertSame('Hello world', $sanitizer->clean($slide['heading'], "Hello\x00world", 'h'));
        $this->assertSame(120, mb_strlen($sanitizer->clean($slide['heading'], str_repeat('a', 500), 'h')));
        $this->assertSame(0, $sanitizer->clean($slide['overlay'], -40, 'o'));
        $this->assertSame(6, $sanitizer->clean($hero->settings()['interval'], INF, 'i'));
        $this->assertNull($sanitizer->clean($slide['button_link'], ['type' => 'url', 'href' => 'vbscript:msgbox(1)'], 'l'));
        $this->assertNull($sanitizer->clean($slide['button_link'], ['type' => 'url', 'href' => '//evil.example.com/x'], 'l'));
        $this->assertNull($sanitizer->clean($slide['button_link'], ['type' => 'product', 'id' => -3], 'l'));
        $this->assertSame('scheme-1', $sanitizer->clean($hero->settings()['scheme'], '"><script>', 's'));
        $this->assertNotEmpty($sanitizer->adjusted());
    }

    public function test_bad_scheme_colors_are_refused(): void
    {
        $settings = $this->defaults('settings');
        $settings['schemes'][1]['colors']['bg'] = 'red;}body{display:none';

        $this->saveDraft($this->admin(), 'settings', $settings, 0)
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['content.schemes.1.colors.bg']]);

        $settings = $this->defaults('settings');
        $settings['schemes'] = array_slice($settings['schemes'], 0, 2);
        $this->saveDraft($this->admin(), 'settings', $settings, 0)->assertStatus(422)->assertJsonStructure(['errors' => ['content.schemes']]);
    }

    public function test_only_photo_files_are_drawn_as_pictures(): void
    {
        $disk = Storage::disk('public');
        foreach (['media/look.jpg', 'media/look.WEBP', 'media/notes.txt', 'media/logo.svg', 'media/page.html', 'media/noext'] as $path) {
            $disk->put($path, 'bytes');
        }
        \Themes\Xelga\Rendering\Picture::flush();

        $this->assertSame('media/look.jpg', \Themes\Xelga\Rendering\Picture::of('media/look.jpg')['path']);
        $this->assertSame('media/look.WEBP', \Themes\Xelga\Rendering\Picture::of('media/look.WEBP')['path']);
        foreach (['media/notes.txt', 'media/logo.svg', 'media/page.html', 'media/noext'] as $path) {
            $this->assertNull(\Themes\Xelga\Rendering\Picture::of($path), "{$path} was drawn as a photo.");
        }
    }
}
