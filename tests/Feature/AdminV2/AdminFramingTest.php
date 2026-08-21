<?php

namespace Tests\Feature\AdminV2;

use App\Models\User;
use App\Services\ExtensionManager;
use Extensions\Banners\Models\BannerGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who may frame the admin, and how a framed page stays framed.
 *
 * Both halves matter for the layout editor's panel: without the header the
 * admin is frameable by anyone, and without the redirect carrying the flag a
 * save inside the panel rebuilds the whole sidebar inside it.
 */
class AdminFramingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ExtensionManager::class)->install('banners');
        app(ExtensionManager::class)->bootEnabled();
        $this->actingAs(User::factory()->create());
    }

    public function test_the_admin_may_be_framed_by_itself_and_by_nobody_else(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'");
    }

    public function test_the_storefront_is_left_alone(): void
    {
        $response = $this->get('/');

        $this->assertNull($response->headers->get('X-Frame-Options'));
        $this->assertNull($response->headers->get('Content-Security-Policy'));
    }

    public function test_a_save_inside_the_panel_lands_on_a_page_that_is_still_framed(): void
    {
        $group = BannerGroup::create(['name' => 'Hero', 'slug' => 'hero', 'is_active' => true]);

        $response = $this->put(route('admin.banners.update', $group) . '?embed=1', [
            'name' => 'Hero renamed',
            'slug' => 'hero',
            'height' => 500,
            'mobile_height' => 400,
            'speed' => 5000,
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('embed=1', $response->headers->get('Location'));
    }

    public function test_an_unframed_save_redirects_without_the_flag(): void
    {
        $group = BannerGroup::create(['name' => 'Hero', 'slug' => 'hero', 'is_active' => true]);

        $response = $this->put(route('admin.banners.update', $group), [
            'name' => 'Hero renamed',
            'slug' => 'hero',
            'height' => 500,
            'mobile_height' => 400,
            'speed' => 5000,
        ]);

        $response->assertRedirect();
        $this->assertStringNotContainsString('embed', $response->headers->get('Location'));
    }

    public function test_the_browsers_own_word_is_enough_without_the_flag(): void
    {
        $this->withHeader('Sec-Fetch-Dest', 'iframe')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('v-framed', false);
    }

    /**
     * Stamping a stranger's URL would tell them where the merchant was and
     * hand them a parameter we act on, so the guard is worth pinning.
     */
    public function test_a_redirect_off_our_host_is_never_stamped(): void
    {
        $prefix = trim((string) config('admin.prefix', 'admin'), '/');

        $request = \Illuminate\Http\Request::create('http://localhost/' . $prefix . '/anything?embed=1');

        $response = (new \App\Http\Middleware\AdminFraming())->handle(
            $request,
            fn () => new \Illuminate\Http\RedirectResponse('https://example.com/elsewhere')
        );

        $this->assertSame('https://example.com/elsewhere', $response->headers->get('Location'));
    }

    public function test_a_redirect_to_our_own_host_is_stamped(): void
    {
        $prefix = trim((string) config('admin.prefix', 'admin'), '/');

        $request = \Illuminate\Http\Request::create('http://localhost/' . $prefix . '/anything?embed=1');

        $response = (new \App\Http\Middleware\AdminFraming())->handle(
            $request,
            fn () => new \Illuminate\Http\RedirectResponse('http://localhost/' . $prefix . '/somewhere')
        );

        $this->assertStringContainsString('embed=1', $response->headers->get('Location'));
    }

    public function test_the_flag_is_never_added_twice(): void
    {
        $once = \App\Support\AdminFrame::url('/admin/thing');

        $this->assertSame($once, \App\Support\AdminFrame::url($once));
    }

    public function test_a_fragment_stays_at_the_end_where_the_browser_expects_it(): void
    {
        $this->assertSame('/admin/thing?embed=1#slides', \App\Support\AdminFrame::url('/admin/thing#slides'));
    }
}
