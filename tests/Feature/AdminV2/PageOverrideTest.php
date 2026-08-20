<?php

namespace Tests\Feature\AdminV2;

use App\Models\Setting;
use App\Services\ThemeService;
use App\Support\StorefrontPageType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Per-page-type theme overrides (owner ruling 2026-08-20).
 *
 * A merchant looking at a page they do not like should be able to change that
 * page, not hunt for a global control and hope. The unit is the page TYPE: all
 * product pages share one set. Per-URL was declined because a store with 1,880
 * products would accumulate 1,880 override sets.
 */
class PageOverrideTest extends TestCase
{
    use RefreshDatabase;

    private ThemeService $theme;

    protected function setUp(): void
    {
        parent::setUp();
        $this->theme = app(ThemeService::class);
    }

    public function test_route_names_resolve_to_page_types(): void
    {
        $this->assertSame('account', StorefrontPageType::forRoute('account.addresses'));
        $this->assertSame('product', StorefrontPageType::forRoute('product.show'));
        $this->assertSame('checkout', StorefrontPageType::forRoute('checkout.confirmation'));
        $this->assertSame('home', StorefrontPageType::forRoute('home'));
        $this->assertSame('auth', StorefrontPageType::forRoute('customer.login'));
    }

    /** A page nobody classified renders the store's own look, not a guess. */
    public function test_an_unknown_route_gets_no_overrides(): void
    {
        $this->assertNull(StorefrontPageType::forRoute('some.future.route'));
        $this->assertNull(StorefrontPageType::forRoute(null));
        $this->assertSame([], $this->theme->pageOverrides(null, 'colors'));
    }

    public function test_an_override_reaches_the_page_it_belongs_to(): void
    {
        $this->theme->setPageOverrides('account', 'colors', ['text_muted' => '#112233']);

        $this->get('/account/addresses')->assertRedirect(); // guest, but the type still resolves
        $this->assertSame(['text_muted' => '#112233'], $this->theme->pageOverrides('account', 'colors'));
        $this->assertSame([], $this->theme->pageOverrides('product', 'colors'));
    }

    /** The storefront must actually emit it, after the global value. */
    public function test_the_storefront_emits_the_override_after_the_global_value(): void
    {
        Setting::updateOrCreate(['group' => 'colors', 'key' => 'text_muted'], ['value' => '#AAAAAA']);
        $this->theme->setPageOverrides('home', 'colors', ['text_muted' => '#112233']);

        $html = $this->get('/')->assertOk()->getContent();

        $global = strpos($html, '--color-text-muted: #AAAAAA');
        $override = strpos($html, '--color-text-muted: #112233');

        $this->assertNotFalse($global, 'The store value is missing.');
        $this->assertNotFalse($override, 'The page override never reached the page.');
        $this->assertGreaterThan($global, $override, 'The override is emitted BEFORE the global, so the cascade ignores it.');
    }

    /** An override equal to the global is not a decision; storing it would lie. */
    public function test_an_override_matching_the_global_is_not_stored(): void
    {
        Setting::updateOrCreate(['group' => 'colors', 'key' => 'primary'], ['value' => '#0E8A43']);

        $this->theme->setPageOverrides('cart', 'colors', ['primary' => '#0E8A43']);

        $this->assertSame([], $this->theme->pageOverrides('cart', 'colors'));
    }

    public function test_clearing_an_override_removes_it(): void
    {
        $this->theme->setPageOverrides('cart', 'colors', ['primary' => '#123456']);
        $this->theme->setPageOverrides('cart', 'colors', ['primary' => '']);

        $this->assertSame([], $this->theme->pageOverrides('cart', 'colors'));
    }

    public function test_the_counts_name_every_page_that_differs(): void
    {
        $this->theme->setPageOverrides('cart', 'colors', ['primary' => '#123456']);
        $this->theme->setPageOverrides('account', 'colors', ['primary' => '#654321', 'border' => '#111111']);

        $this->assertSame(['cart' => 1, 'account' => 2], $this->theme->pageOverrideCounts());
    }

    /**
     * A preset is the whole look, so overrides do not survive it. The editor
     * names which pages will lose their colours before this runs.
     */
    public function test_applying_a_preset_clears_every_override(): void
    {
        $this->theme->setPageOverrides('account', 'colors', ['text_muted' => '#112233']);

        $this->theme->applyPreset('stockroom');

        $this->assertSame([], $this->theme->pageOverrideCounts());
    }

    /**
     * The override namespace must never collide with the preset snapshot one
     * ("{family}__{slug}"), or customising a page type would silently write
     * into a preset of the same name.
     */
    public function test_the_override_namespace_cannot_collide_with_preset_snapshots(): void
    {
        $group = $this->theme->pageOverrideGroup('account', 'colors');

        $this->assertStringNotContainsString('__', $group);
        $this->theme->setPageOverrides('account', 'colors', ['primary' => '#abcdef']);

        $this->assertSame(0, Setting::where('group', 'like', '%\_\_%')->where('key', 'primary')->count());
    }

    public function test_an_unknown_page_type_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->theme->setPageOverrides('not-a-page', 'colors', ['primary' => '#000000']);
    }

    /* ---------------- the preview channel's guard ---------------- */

    /** A shopper must never receive the script that drives the preview. */
    public function test_a_guest_asking_for_preview_mode_does_not_get_it(): void
    {
        $html = $this->get('/?__preview=1')->assertOk()->getContent();

        $this->assertStringNotContainsString('preview', $this->scripts($html),
            'The preview script shipped to a guest who simply added a query flag.');
    }

    /** Nor does an admin on an ordinary visit, without asking for it. */
    public function test_an_admin_browsing_normally_does_not_load_it(): void
    {
        $this->actingAs(\App\Models\User::factory()->create());

        $this->assertStringNotContainsString('preview', $this->scripts($this->get('/')->getContent()));
    }

    public function test_an_admin_who_asks_for_it_gets_it(): void
    {
        $this->actingAs(\App\Models\User::factory()->create());

        $this->assertStringContainsString('preview', $this->scripts($this->get('/?__preview=1')->getContent()));
    }

    /** The frame reports which page it is showing, so the editor can scope. */
    public function test_the_page_stamps_its_type_for_the_preview_to_read(): void
    {
        $this->get('/')->assertOk()->assertSee('data-page-type="home"', false);
    }

    /** Only the src attributes, so prose or a class name never counts as a hit. */
    private function scripts(string $html): string
    {
        preg_match_all('/<script[^>]+src="([^"]+)"/', $html, $m);

        return implode(' ', $m[1] ?? []);
    }
}
