<?php

namespace Themes\Xelga\Tests\Feature;

use App\Models\Setting;
use App\Services\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';

/**
 * Drafts are shown only inside the editor's preview: an admin with the
 * theme permission, an open window, and the window's nonce or the editor's
 * frame. Everyone else, whatever they add to the address, sees what is
 * published.
 */
class XelgaPreviewTest extends TestCase
{
    use RefreshDatabase;
    use XelgaTestHelpers;

    private const DRAFT = 'Only the editor sees this';

    private const LIVE = 'Phones, laptops and tablets, paid for at your door';

    protected function setUp(): void
    {
        parent::setUp();
        $this->installXelga();
        $this->saveDraft($this->admin(), 'home', $this->homeWithHeading(self::DRAFT), 0)->assertOk();
    }

    public function test_a_guest_adding_the_preview_parameter_sees_the_published_page(): void
    {
        $nonce = $this->openEditor($this->admin());
        $this->asGuest();

        foreach (['anything', $nonce, ''] as $value) {
            $this->get('/?xelga_preview=' . urlencode($value))->assertOk()->assertSee(self::LIVE)->assertDontSee(self::DRAFT);
        }
        $this->get('/', ['Sec-Fetch-Dest' => 'iframe'])->assertOk()->assertDontSee(self::DRAFT);
    }

    public function test_the_editor_preview_shows_the_draft(): void
    {
        $nonce = $this->openEditor($this->admin());

        $this->get('/?xelga_preview=' . $nonce)
            ->assertOk()
            ->assertSee(self::DRAFT)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'")
            ->assertHeader('Cache-Control', 'no-store, private');

        // A navigation inside the editor frame keeps the draft without the nonce.
        $this->get('/', ['Sec-Fetch-Dest' => 'iframe'])->assertOk()->assertSee(self::DRAFT);
    }

    public function test_a_preview_tab_with_the_nonce_shows_the_draft(): void
    {
        $nonce = $this->openEditor($this->admin());

        // A tab of its own sends no frame header; the nonce in the address is
        // what shows the draft. The page is drawn in preview mode, with the
        // headers that keep it out of search results and out of other sites' frames.
        $this->get('/?xelga_preview=' . $nonce, ['Sec-Fetch-Dest' => 'document'])
            ->assertOk()
            ->assertSee(self::DRAFT)
            ->assertDontSee(self::LIVE)
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'");
    }

    public function test_a_plain_tab_without_the_nonce_shows_the_published_page(): void
    {
        $this->openEditor($this->admin());

        // The same admin, the window open, a tab of its own without the nonce.
        $this->get('/', ['Sec-Fetch-Dest' => 'document'])
            ->assertOk()
            ->assertSee(self::LIVE)
            ->assertDontSee(self::DRAFT);
    }

    public function test_a_request_saving_an_older_session_does_not_close_the_window(): void
    {
        $admin = $this->admin();
        $nonce = $this->openEditor($admin);

        // The editor sends requests in parallel. One that read the session
        // before the window opened saves that older session back when it
        // ends; the window must survive it.
        $this->app['session.store']->flush();
        $this->actingAs($admin)->postJson(route('admin.xelga.presence'))->assertOk();

        $this->get('/?xelga_preview=' . $nonce)->assertOk()->assertSee(self::DRAFT);
    }

    public function test_an_admins_ordinary_tab_shows_the_published_page(): void
    {
        $nonce = $this->openEditor($this->admin());

        $this->get('/')->assertOk()->assertSee(self::LIVE)->assertDontSee(self::DRAFT);
        $this->get('/?xelga_preview=not-' . $nonce)->assertOk()->assertDontSee(self::DRAFT);
    }

    public function test_an_admin_without_the_theme_permission_never_sees_drafts(): void
    {
        $staff = $this->staff();
        $nonce = 'forged-' . str_repeat('a', 34);
        $this->actingAs($staff);
        \Themes\Xelga\Preview\PreviewSession::store(['nonce' => $nonce, 'until' => now()->addHour()->getTimestamp(), 'user' => $staff->id]);

        $this->get('/?xelga_preview=' . $nonce)->assertOk()->assertDontSee(self::DRAFT);
        $this->get('/', ['Sec-Fetch-Dest' => 'iframe'])->assertOk()->assertDontSee(self::DRAFT);
    }

    public function test_an_expired_or_closed_window_shows_the_published_page(): void
    {
        $admin = $this->admin();
        $nonce = $this->openEditor($admin);

        $this->travel(121)->minutes();
        $this->get('/?xelga_preview=' . $nonce)->assertOk()->assertDontSee(self::DRAFT);
        $this->travelBack();

        $nonce = $this->openEditor($admin);
        $this->actingAs($admin)->deleteJson(route('admin.xelga.preview.close'))->assertOk();
        $this->get('/?xelga_preview=' . $nonce)->assertOk()->assertDontSee(self::DRAFT);
    }

    public function test_a_window_belongs_to_the_admin_who_opened_it(): void
    {
        $nonce = $this->openEditor($this->admin());

        $this->actingAs($this->themeEditor());
        $this->get('/?xelga_preview=' . $nonce)->assertOk()->assertDontSee(self::DRAFT);
    }

    public function test_the_draft_tokens_stylesheet_is_only_served_inside_the_window(): void
    {
        $settings = $this->defaults('settings');
        $settings['schemes'][0]['colors']['accent'] = '#123456';
        $this->saveDraft($this->admin(), 'settings', $settings, 0)->assertOk();

        $nonce = $this->openEditor($this->admin());
        $this->get(route('xelga.tokens.draft', ['n' => $nonce]))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('--c-accent:#123456', false);

        $this->asGuest();
        $this->get(route('xelga.tokens.draft', ['n' => $nonce]))->assertNotFound();
        $this->get(route('xelga.tokens', ['version' => 0]))->assertOk()->assertDontSee('#123456', false);
    }

    public function test_loading_the_editor_page_opens_no_preview_window(): void
    {
        $this->actingAs($this->admin())->get(route('admin.xelga.editor'))->assertOk();

        $this->assertNull($this->previewWindow());
        $this->get('/', ['Sec-Fetch-Dest' => 'iframe'])->assertOk()->assertDontSee(self::DRAFT);
        $this->get('/?xelga_preview=' . str_repeat('a', 40))->assertOk()->assertDontSee(self::DRAFT);
    }

    public function test_a_link_to_the_editor_neither_rotates_nor_reopens_the_window(): void
    {
        $admin = $this->admin();
        $nonce = $this->openEditor($admin);

        // Following a link to the editor (from any site) leaves the open window as it is.
        $this->actingAs($admin)->get(route('admin.xelga.editor'))->assertOk();
        $this->assertSame($nonce, $this->previewWindow()['nonce'] ?? null);
        $this->get('/?xelga_preview=' . $nonce)->assertOk()->assertSee(self::DRAFT);

        // After Exit, the same link does not bring the window back.
        $this->actingAs($admin)->deleteJson(route('admin.xelga.preview.close'))->assertOk();
        $this->actingAs($admin)->get(route('admin.xelga.editor'))->assertOk();
        $this->assertNull($this->previewWindow());
        $this->get('/?xelga_preview=' . $nonce)->assertOk()->assertDontSee(self::DRAFT);
        $this->get('/', ['Sec-Fetch-Dest' => 'iframe'])->assertOk()->assertDontSee(self::DRAFT);

        // Opening takes a POST, which carries the CSRF token another site cannot read.
        $this->assertContains($this->actingAs($admin)->get(route('admin.xelga.preview.open'))->getStatusCode(), [404, 405]);
        $this->assertNull($this->previewWindow());
        $route = Route::getRoutes()->getByName('admin.xelga.preview.open');
        $this->assertSame(['POST'], $route->methods());
        $middleware = app('router')->gatherRouteMiddleware($route);
        $this->assertNotEmpty(array_filter($middleware, fn ($m) => is_string($m) && is_a($m, \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, true)));
        $this->assertNotEmpty(array_filter($middleware, fn ($m) => is_string($m) && str_contains($m, 'ThrottleRequests')));
    }

    public function test_opening_the_editor_again_keeps_the_open_windows_nonce(): void
    {
        $admin = $this->admin();
        $first = $this->openEditor($admin);

        // A refresh, Back and Forward, or a second tab opens again: a request
        // still on its way with the first nonce must keep showing the draft.
        $again = $this->openEditor($admin);
        $this->assertSame($first, $again);
        $this->get('/?xelga_preview=' . $first)->assertOk()->assertSee(self::DRAFT);

        // After Exit the next open starts a fresh window with a new nonce.
        $this->actingAs($admin)->deleteJson(route('admin.xelga.preview.close'))->assertOk();
        $fresh = $this->openEditor($admin);
        $this->assertNotSame($first, $fresh);
        $this->get('/?xelga_preview=' . $first)->assertOk()->assertDontSee(self::DRAFT);
    }

    public function test_a_redirect_inside_the_preview_keeps_the_preview_on_this_store_only(): void
    {
        Route::middleware('web')->get('/xelga-test-redirect/{to}', fn (string $to) => redirect(match ($to) {
            'same' => '/account/login?next=checkout',
            'absolute' => url('/cart'),
            'external' => 'https://example.com/elsewhere',
            'relative-external' => '//example.com/elsewhere',
            'backslash' => '/\\example.com/elsewhere',
            'scheme-no-host' => 'http:/\\example.com',
            'other-port' => 'http://' . request()->getHost() . '/cart',
        }));

        $admin = $this->admin();
        $nonce = $this->openEditor($admin);

        $same = $this->actingAs($admin)->get('/xelga-test-redirect/same?xelga_preview=' . $nonce);
        $same->assertRedirect();
        $this->assertStringContainsString('xelga_preview=' . $nonce, $same->headers->get('Location'));
        $this->assertStringContainsString('next=checkout', $same->headers->get('Location'));

        $absolute = $this->actingAs($admin)->get('/xelga-test-redirect/absolute?xelga_preview=' . $nonce);
        $this->assertStringContainsString('xelga_preview=' . $nonce, $absolute->headers->get('Location'));

        foreach (['external', 'relative-external', 'backslash', 'scheme-no-host'] as $to) {
            $away = $this->actingAs($admin)->get('/xelga-test-redirect/' . $to . '?xelga_preview=' . $nonce);
            $this->assertStringNotContainsString('xelga_preview', $away->headers->get('Location'), $to);
        }

        // Outside the preview a redirect is left exactly as it was.
        $this->asGuest();
        $this->assertSame('/account/login?next=checkout', parse_url($this->get('/xelga-test-redirect/same')->headers->get('Location'), PHP_URL_PATH) . '?' . parse_url($this->get('/xelga-test-redirect/same')->headers->get('Location'), PHP_URL_QUERY));
    }

    public function test_after_a_preview_the_next_guest_sees_the_live_theme(): void
    {
        // Xelga installed but not live: the preview lays it over the live theme for one request.
        Setting::updateOrCreate(['group' => 'identity', 'key' => 'active_theme'], ['value' => \Tests\TestCase::SAMPLE_THEME]);
        $themes = app(ThemeService::class);
        $themes->clearCache();
        $themes->reload();
        $live = $themes->getActiveTheme();
        $this->assertSame(\Tests\TestCase::SAMPLE_THEME, $live);
        $this->get('/')->assertOk()->assertDontSee(self::LIVE);

        $nonce = $this->openEditor($this->admin());
        $this->get('/?xelga_preview=' . $nonce)->assertOk()->assertSee(self::DRAFT);

        // The same application serves the next request, as a long-running worker would.
        $this->assertSame($live, app(ThemeService::class)->getActiveTheme());
        $this->asGuest();
        $this->get('/')->assertOk()->assertDontSee(self::LIVE)->assertDontSee(self::DRAFT);
        $this->assertSame($live, app(ThemeService::class)->getActiveTheme());
        $this->assertStringNotContainsString('/xelga', (string) (app('view')->getFinder()->getPaths()[0] ?? ''));
    }
}
