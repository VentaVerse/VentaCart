<?php

namespace Themes\Xelga\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Themes\Xelga\Tests\XelgaTestHelpers;
use Themes\Xelga\Documents\Document;
use Themes\Xelga\Rendering\Storefront;

require_once __DIR__ . '/../XelgaTestHelpers.php';

/**
 * The main banner and the swipe row's moving settings: hidden slides never
 * reach shoppers and take no turn, the first visible slide is the one shown
 * in every layout, a banner with every slide hidden is left out for shoppers,
 * and the transition and autoplay settings are kept within their choices.
 */
class XelgaHeroTest extends TestCase
{
    use RefreshDatabase;
    use XelgaTestHelpers;

    private const LAYOUTS = ['full-image', 'split', 'slideshow', 'collage'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->installXelga();
    }

    /** A home page with one banner of three slides; $hidden lists the slides (0-based) that are hidden. */
    private function bannerHome(string $layout, array $hidden, array $settings = []): array
    {
        $blocks = [];
        foreach (['First', 'Second', 'Third'] as $i => $word) {
            $blocks["hero-t-b{$i}"] = ['type' => 'slide', 'label' => '', 'disabled' => in_array($i, $hidden, true),
                'settings' => ['heading' => "{$word} slide heading", 'button_label' => '']];
        }

        return ['schema' => 1, 'order' => ['hero-t'], 'sections' => ['hero-t' => [
            'type' => 'hero', 'label' => '', 'disabled' => false,
            'settings' => ['variant' => $layout] + $settings,
            'block_order' => array_keys($blocks), 'blocks' => $blocks,
        ]]];
    }

    private function publishHome(array $content): void
    {
        $admin = $this->admin();
        $version = (int) (Document::where('handle', 'home')->value('draft_version') ?? 0);
        $this->saveDraft($admin, 'home', $content, $version)->assertOk();
        $this->publish($admin, ['home' => $version + 1])->assertOk();
        $this->asGuest();
        Storefront::forgetRequestState();
    }

    private function guestHome(): string
    {
        Storefront::forgetRequestState();

        return $this->get('/')->assertOk()->getContent();
    }

    private function previewHome(): string
    {
        $nonce = $this->openEditor($this->admin());
        Storefront::forgetRequestState();
        $html = $this->get('/?xelga_preview=' . $nonce)->assertOk()->getContent();
        $this->asGuest();

        return $html;
    }

    /** The opening tag of one slide, found by its block id. */
    private function slideTag(string $html, int $index): ?string
    {
        return preg_match('/<div class="hero__slide[^"]*"[^>]*data-block-id="hero-t-b' . $index . '"[^>]*>/', $html, $m) ? $m[0] : null;
    }

    public function test_hidden_slides_are_never_sent_to_shoppers(): void
    {
        $this->publishHome($this->bannerHome('slideshow', [1]));
        $html = $this->guestHome();

        $this->assertStringContainsString('First slide heading', $html);
        $this->assertStringContainsString('Third slide heading', $html);
        $this->assertStringNotContainsString('Second slide heading', $html);
        $this->assertNull($this->slideTag($html, 1));
        $this->assertSame(2, substr_count($html, 'data-block-type="slide"'));
        // The visible slides are numbered without the gap the hidden one would leave.
        $this->assertStringContainsString('data-slot="2"', $this->slideTag($html, 2));
    }

    public function test_the_preview_keeps_hidden_slides_marked_hidden_and_out_of_the_order(): void
    {
        $this->publishHome($this->bannerHome('slideshow', [1]));
        $html = $this->previewHome();

        $tag = $this->slideTag($html, 1);
        $this->assertNotNull($tag);
        $this->assertMatchesRegularExpression('/\shidden(\s|>)/', $tag);
        $this->assertStringNotContainsString('data-slot', $tag);
        $this->assertStringContainsString('data-slot="2"', $this->slideTag($html, 2));
    }

    public function test_the_first_visible_slide_is_the_one_shown_in_every_layout(): void
    {
        foreach (self::LAYOUTS as $layout) {
            $this->publishHome($this->bannerHome($layout, [0]));

            foreach (['shopper' => $this->guestHome(), 'preview' => $this->previewHome()] as $who => $html) {
                $second = $this->slideTag($html, 1);
                $this->assertNotNull($second, "{$layout} for the {$who}");
                $this->assertStringContainsString('is-active', $second, "{$layout} for the {$who}");
                $this->assertStringContainsString('data-slot="1"', $second, "{$layout} for the {$who}");
                $this->assertMatchesRegularExpression('/<h2 class="hero__title"[^>]*>Second slide heading</', $html, "{$layout} for the {$who}");
                $first = $this->slideTag($html, 0);
                if ($first !== null) {
                    $this->assertStringNotContainsString('is-active', $first, "{$layout} for the {$who}");
                }
            }
        }
    }

    public function test_a_banner_with_every_slide_hidden_is_left_out_for_shoppers(): void
    {
        $this->publishHome($this->bannerHome('slideshow', [0, 1, 2]));

        $this->assertStringNotContainsString('data-section-type="hero"', $this->guestHome());

        $preview = $this->previewHome();
        $this->assertStringContainsString('data-section-type="hero"', $preview);
        $this->assertStringContainsString('All slides are hidden', $preview);
    }

    public function test_one_visible_slide_has_no_slideshow_controls(): void
    {
        $this->publishHome($this->bannerHome('slideshow', [0, 2]));

        $this->assertMatchesRegularExpression('/<div class="hero__controls"\s+hidden/', $this->guestHome());
    }

    public function test_the_transition_is_kept_within_its_choices_and_defaults_to_fade(): void
    {
        $admin = $this->admin();
        $this->saveDraft($admin, 'home', $this->bannerHome('slideshow', [], ['slide_change' => 'spin']), 0)->assertOk();
        $this->assertSame('fade', Document::where('handle', 'home')->firstOrFail()->draft['sections']['hero-t']['settings']['slide_change']);

        foreach (['fade', 'slide', 'slide-up', 'zoom', 'cover', 'slow-zoom'] as $i => $choice) {
            $this->saveDraft($admin, 'home', $this->bannerHome('slideshow', [], ['slide_change' => $choice]), $i + 1)->assertOk();
            $this->assertSame($choice, Document::where('handle', 'home')->firstOrFail()->draft['sections']['hero-t']['settings']['slide_change']);
        }

        // A banner saved before the setting existed draws with the default.
        $this->asGuest();
        $this->publishHome($this->bannerHome('slideshow', []));
        $this->assertStringContainsString('data-slide-change="fade"', $this->guestHome());
    }

    public function test_the_banner_autoplay_setting_is_kept_and_reaches_the_page(): void
    {
        $this->publishHome($this->bannerHome('slideshow', [], ['autoplay' => false]));
        $html = $this->guestHome();
        $this->assertStringContainsString('data-autoplay="false"', $html);
        $this->assertMatchesRegularExpression('/data-hero-pause[^>]*hidden/', $html);

        $this->publishHome($this->bannerHome('slideshow', []));
        $this->assertStringContainsString('data-autoplay="true"', $this->guestHome());
    }

    public function test_the_swipe_row_autoplay_settings_are_sanitized_and_defaulted(): void
    {
        $home = ['schema' => 1, 'order' => ['p'], 'sections' => ['p' => [
            'type' => 'featured-products', 'label' => '', 'disabled' => false, 'block_order' => [], 'blocks' => [],
            'settings' => ['variant' => 'carousel', 'autoplay' => 'yes', 'interval' => 99],
        ]]];
        $this->saveDraft($this->admin(), 'home', $home, 0)->assertOk();
        $settings = Document::where('handle', 'home')->firstOrFail()->draft['sections']['p']['settings'];

        $this->assertTrue($settings['autoplay']);
        $this->assertSame(10, $settings['interval']);

        $home['sections']['p']['settings'] = ['variant' => 'carousel', 'interval' => 1, 'autoplay' => false];
        $this->saveDraft($this->admin(), 'home', $home, 1)->assertOk();
        $settings = Document::where('handle', 'home')->firstOrFail()->draft['sections']['p']['settings'];
        $this->assertFalse($settings['autoplay']);
        $this->assertSame(2, $settings['interval']);

        // Half seconds are allowed, as on the banner.
        $home['sections']['p']['settings'] = ['variant' => 'carousel', 'interval' => 4.5];
        $this->saveDraft($this->admin(), 'home', $home, 2)->assertOk();
        $settings = Document::where('handle', 'home')->firstOrFail()->draft['sections']['p']['settings'];
        $this->assertEquals(4.5, $settings['interval']);
    }

    public function test_the_swipe_row_carries_its_autoplay_settings_and_pause_button(): void
    {
        \App\Models\Product::create([
            'store_id' => \App\Models\Store::firstOrCreate(['code' => 'demo'], ['name' => 'Demo Store'])->id,
            'name' => 'Linen Shirt Dress', 'slug' => 'linen-shirt-dress', 'sku' => 'DEMO-001', 'price' => 1250, 'quantity' => 5, 'is_active' => true,
        ]);
        $this->publishHome(['schema' => 1, 'order' => ['p'], 'sections' => ['p' => [
            'type' => 'featured-products', 'label' => '', 'disabled' => false, 'block_order' => [], 'blocks' => [],
            'settings' => ['variant' => 'carousel', 'source' => 'newest'],
        ]]]);
        $html = $this->guestHome();

        $this->assertMatchesRegularExpression('/data-section-type="featured-products"[^>]*data-autoplay="true"[^>]*data-interval="5"/s', $html);
        $this->assertStringContainsString('data-rail-pause', $html);
    }

    public function test_text_settings_saved_on_the_banner_before_they_moved_to_slides_still_draw(): void
    {
        // A page published before text position, text background and darkening moved to the slides.
        $old = $this->bannerHome('full-image', [], ['text_x' => 'end', 'text_y' => 'top', 'box' => 'panel', 'overlay' => 60]);
        $old['sections']['hero-t']['blocks']['hero-t-b2']['settings']['text_x'] = 'center';
        $this->publishHome($this->bannerHome('full-image', []));
        $row = Document::where('handle', 'home')->firstOrFail();
        $row->published = $old;
        $row->save();

        $first = $this->slideTag($this->guestHome(), 0);
        foreach (['data-text-x="end"', 'data-text-y="top"', 'data-box="panel"', 'data-overlay="60"'] as $attr) {
            $this->assertStringContainsString($attr, $first);
        }

        // Saving the page again writes the old values onto each slide that has none of its own.
        $version = (int) $row->fresh()->draft_version;
        $this->saveDraft($this->admin(), 'home', $old, $version)->assertOk();
        $draft = Document::where('handle', 'home')->firstOrFail()->draft['sections']['hero-t'];
        $this->assertArrayNotHasKey('text_x', $draft['settings']);
        $slide = $draft['blocks']['hero-t-b0']['settings'];
        $this->assertSame(['end', 'top', 'panel', 60], [$slide['text_x'], $slide['text_y'], $slide['box'], $slide['overlay']]);
        $this->assertSame('center', $draft['blocks']['hero-t-b2']['settings']['text_x']);
    }

    public function test_each_slide_draws_its_own_text_settings(): void
    {
        $home = $this->bannerHome('slideshow', []);
        $home['sections']['hero-t']['blocks']['hero-t-b1']['settings'] += ['text_x' => 'center', 'box' => 'panel', 'overlay' => 10];
        $this->publishHome($home);
        $html = $this->guestHome();

        $this->assertStringContainsString('data-text-x="start"', $this->slideTag($html, 0));
        $this->assertStringContainsString('data-overlay="35"', $this->slideTag($html, 0));
        foreach (['data-text-x="center"', 'data-box="panel"', 'data-overlay="10"'] as $attr) {
            $this->assertStringContainsString($attr, $this->slideTag($html, 1));
        }
        $this->assertDoesNotMatchRegularExpression('/data-section-type="hero"[^>]*data-text-x/', $html);
    }
}
