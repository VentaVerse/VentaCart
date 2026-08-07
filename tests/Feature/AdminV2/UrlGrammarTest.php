<?php

namespace Tests\Feature\AdminV2;

use App\Models\User;
use App\Services\ExtensionManager;
use App\Support\Breadcrumbs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Task 3 of the admin-v2 IA & extension-kernel plan: the route monolith
 * split, the canonical URL grammar ({admin-prefix}/{section}/{entity}/{verb}),
 * and the legacy redirect map. See
 * docs/superpowers/specs/2026-08-07-admin-ia-and-extension-kernel-design.md
 * (Part 1) for the target this asserts against.
 */
class UrlGrammarTest extends TestCase
{
    use RefreshDatabase;

    /** Section roots the grammar map defines (spec §Part 1 URL map). */
    private const ALLOWED_ROOTS = [
        'dashboard', 'sales', 'catalog', 'customers', 'content',
        'marketing', 'settings', 'profile',
    ];

    // ------------------------------------------------------------------
    // New URLs: representative page per section, all 200.
    // ------------------------------------------------------------------

    public function test_dashboard_page_is_ok_at_new_url(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/'.$this->prefix().'/dashboard')->assertOk();
    }

    public function test_sales_page_is_ok_at_new_url(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/'.$this->prefix().'/sales/orders')->assertOk();
    }

    public function test_catalog_page_is_ok_at_new_url(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/'.$this->prefix().'/catalog/products')->assertOk();
    }

    public function test_customers_page_is_ok_at_new_url(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/'.$this->prefix().'/customers/customers')->assertOk();
    }

    public function test_content_page_is_ok_at_new_url(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/'.$this->prefix().'/content/pages')->assertOk();
    }

    public function test_marketing_page_is_ok_at_new_url(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/'.$this->prefix().'/marketing/coupons')->assertOk();
    }

    public function test_settings_page_is_ok_at_new_url(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/'.$this->prefix().'/settings')->assertOk();
    }

    public function test_settings_modules_page_is_ok_at_new_url(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/'.$this->prefix().'/settings/modules')->assertOk();
    }

    public function test_marketing_seo_workspace_is_ok_at_new_url(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/'.$this->prefix().'/marketing/seo')->assertOk();
    }

    public function test_settings_seo_engine_config_is_ok_at_new_url(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/'.$this->prefix().'/settings/modules/seo/settings')->assertOk();
    }

    // ------------------------------------------------------------------
    // Old URLs: 301 to the new home, Location asserted.
    // ------------------------------------------------------------------

    public function test_old_admin_root_redirects_to_dashboard(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/'.$this->prefix());

        $response->assertRedirect(route('admin.dashboard'));
        $response->assertStatus(301);
    }

    public function test_old_orders_url_redirects_to_sales_orders(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/'.$this->prefix().'/orders');

        $response->assertStatus(301);
        $response->assertRedirect(route('admin.orders.index'));
    }

    public function test_old_products_url_redirects_to_catalog_products(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/'.$this->prefix().'/products');

        $response->assertStatus(301);
        $response->assertRedirect(route('admin.products.index'));
    }

    public function test_old_settings_section_url_redirects_preserving_param(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/'.$this->prefix().'/settings/section/general');

        $response->assertStatus(301);
        $response->assertRedirect(route('admin.settings.section', 'general'));
    }

    public function test_old_extensions_seo_dashboard_redirects_to_marketing_seo(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/'.$this->prefix().'/extensions/seo');

        $response->assertStatus(301);
        $response->assertRedirect(route('admin.extensions.seo.dashboard'));
    }

    public function test_old_extensions_seo_settings_redirects_to_settings_modules_seo(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/'.$this->prefix().'/extensions/seo/settings');

        $response->assertStatus(301);
        $response->assertRedirect(route('admin.extensions.seo.settings'));
        $this->assertStringContainsString('/settings/modules/seo/settings', $response->headers->get('Location'));
    }

    public function test_old_abandoned_carts_settings_redirects_to_settings_modules(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/'.$this->prefix().'/abandoned-carts/settings');

        $response->assertStatus(301);
        $response->assertRedirect(route('admin.abandoned-carts.settings'));
        $this->assertStringContainsString('/settings/modules/abandoned-carts/settings', $response->headers->get('Location'));
    }

    public function test_old_abandoned_carts_index_still_resolves_operationally(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/'.$this->prefix().'/abandoned-carts');

        $response->assertStatus(301);
        $response->assertRedirect(route('admin.abandoned-carts.index'));
        $this->assertStringContainsString('/sales/abandoned-carts', $response->headers->get('Location'));
    }

    public function test_old_modules_url_redirects_to_settings_modules(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/'.$this->prefix().'/modules');

        $response->assertStatus(301);
        $response->assertRedirect(route('admin.modules.index'));
    }

    public function test_old_order_edit_url_redirects_preserving_the_order_param(): void
    {
        // The redirect closure never resolves route-model binding — it just
        // forwards the raw {order} path segment into route() positionally —
        // so a literal id is enough to prove param-preservation without a
        // real Order row.
        $this->actingAs(User::factory()->create());

        $response = $this->get('/'.$this->prefix().'/orders/123/edit');

        $response->assertStatus(301);
        $response->assertRedirect(route('admin.orders.edit', 123));
    }

    public function test_old_shipping_couriers_url_redirects_to_settings_couriers(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/'.$this->prefix().'/shipping-couriers');

        $response->assertStatus(301);
        $response->assertRedirect(route('admin.shipping-couriers.index'));
        $this->assertStringContainsString('/settings/couriers', $response->headers->get('Location'));
    }

    public function test_old_admin_banners_literal_url_redirects_to_settings_modules_banners(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/admin/banners');

        $response->assertStatus(301);
        $this->assertStringContainsString(
            '/'.$this->prefix().'/settings/modules/banners',
            $response->headers->get('Location')
        );
    }

    public function test_query_string_is_preserved_across_a_legacy_redirect(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/'.$this->prefix().'/products?search=strat&page=2');

        $response->assertStatus(301);
        $this->assertStringContainsString('search=strat', $response->headers->get('Location'));
        $this->assertStringContainsString('page=2', $response->headers->get('Location'));
    }

    // ------------------------------------------------------------------
    // Grammar conformance sweep: routes/web.php must carry zero admin
    // routes, and every admin.*-named route must live under the prefix
    // and start with one of the grammar's section roots.
    // ------------------------------------------------------------------

    public function test_no_admin_routes_left_in_web_php(): void
    {
        $webPhp = file_get_contents(base_path('routes/web.php'));

        // web.php still legitimately references config('admin.prefix') once,
        // in the CMS catch-all's slug exclusion regex — that's not an admin
        // route, just a guard so /{slug} never swallows the admin prefix.
        // What must be gone is the admin *group* itself.
        $this->assertStringNotContainsString(
            "->prefix(config('admin.prefix'",
            $webPhp,
            'routes/web.php should no longer build the admin prefix group directly — it must require routes/admin.php instead.'
        );
        $this->assertStringNotContainsString("name('admin.')->group", $webPhp);
        $this->assertStringContainsString("require __DIR__.'/admin.php'", $webPhp);
    }

    public function test_every_admin_named_route_conforms_to_the_url_grammar(): void
    {
        $prefix = $this->prefix();
        $checked = 0;

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            // 'admin.' (the bare group name, nothing appended) is what
            // every unnamed route inside Route::name('admin.')->group(...)
            // resolves to — that's exactly routes/admin/redirects.php's
            // legacy redirect closures, which deliberately answer at
            // non-conforming old URIs. Only routes with a REAL name
            // suffix (admin.orders.index, etc.) are pages the grammar
            // applies to.
            if ($name === null || $name === 'admin.' || ! str_starts_with($name, 'admin.')) {
                continue;
            }

            $uri = $route->uri();

            $this->assertTrue(
                str_starts_with($uri, $prefix.'/'),
                "Route [{$name}] URI [{$uri}] does not start with the admin prefix [{$prefix}]."
            );

            $relative = substr($uri, strlen($prefix) + 1);
            $root = explode('/', $relative)[0] ?? '';

            $this->assertContains(
                $root,
                self::ALLOWED_ROOTS,
                "Route [{$name}] URI [{$uri}] does not start with an allowed grammar section root."
            );

            $checked++;
        }

        // Sanity check the sweep actually exercised something — a filter
        // bug that silently matched zero routes would otherwise pass.
        $this->assertGreaterThan(80, $checked);
    }

    /**
     * Task 6 (view directory moves into section folders): every literal
     * view('admin....') string in an Admin controller, and every literal
     * @include('admin....')/@extends('admin....') string in an admin Blade
     * file, must resolve via View::exists(). Dynamically-built names
     * (string concatenation, e.g. BankAccountController's per-method
     * partial) are intentionally excluded — the regex only captures a
     * quoted literal immediately followed by ',' or ')', which a
     * concatenation expression never is.
     */
    public function test_all_admin_views_resolve(): void
    {
        $names = [];

        $controllerFiles = File::allFiles(app_path('Http/Controllers/Admin'));
        foreach ($controllerFiles as $file) {
            $contents = $file->getContents();
            if (preg_match_all("/view\('([^']+)'\s*[,)]/", $contents, $matches)) {
                foreach ($matches[1] as $name) {
                    $names[$name] = true;
                }
            }
        }

        $viewFiles = File::allFiles(resource_path('views/admin'));
        foreach ($viewFiles as $file) {
            $contents = $file->getContents();
            if (preg_match_all("/@(?:include|extends|component)\('([^']+)'\s*[,)]/", $contents, $matches)) {
                foreach ($matches[1] as $name) {
                    if (str_starts_with($name, 'admin.')) {
                        $names[$name] = true;
                    }
                }
            }
        }

        $this->assertGreaterThan(70, count($names), 'View-reference sweep matched suspiciously few names — regex likely broken.');

        $missing = [];
        foreach (array_keys($names) as $name) {
            if (! View::exists($name)) {
                $missing[] = $name;
            }
        }

        $this->assertSame([], $missing, 'These referenced admin views do not resolve: '.implode(', ', $missing));
    }

    // ------------------------------------------------------------------
    // Task 7: path-derived breadcrumbs (App\Support\Breadcrumbs). Three
    // representative deep pages — an extension-kernel page (proving the
    // ExtensionManager manifest-title fallback), a plain admin-nav create
    // form, and a settings/modules/{name} sub-page — each rendered end to
    // end through x-admin.page's auto-derivation (no :breadcrumb passed).
    // ------------------------------------------------------------------

    public function test_breadcrumb_trail_on_extension_page_shows_settings_modules_and_manifest_title(): void
    {
        $this->actingAs(User::factory()->create());
        app(ExtensionManager::class)->install('banners');

        $response = $this->get('/'.$this->prefix().'/settings/modules/banners');

        $response->assertOk();
        $this->assertBreadcrumbTrail($response->getContent(), ['Settings', 'Modules', 'Banners']);
    }

    public function test_breadcrumb_trail_on_admin_nav_create_page(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/'.$this->prefix().'/content/pages/create');

        $response->assertOk();
        $this->assertBreadcrumbTrail($response->getContent(), ['Content', 'Pages', 'New Page']);
    }

    public function test_breadcrumb_trail_on_settings_modules_subpage(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/'.$this->prefix().'/settings/modules/abandoned-carts/rules');

        $response->assertOk();
        $this->assertBreadcrumbTrail($response->getContent(), ['Settings', 'Modules', 'Abandoned Carts', 'Rules']);
    }

    public function test_breadcrumbs_forpath_unit_cases(): void
    {
        $this->assertSame([], Breadcrumbs::forPath('/'.$this->prefix().'/dashboard', 'Dashboard'));

        $this->assertSame(
            [['label' => 'Sales'], ['label' => 'Orders', 'url' => route('admin.orders.index')]],
            Breadcrumbs::forPath('/'.$this->prefix().'/sales/orders', 'Orders')
        );

        $this->assertSame(
            [
                ['label' => 'Sales'],
                ['label' => 'Orders', 'url' => route('admin.orders.index')],
                ['label' => 'Edit Order #7'],
            ],
            Breadcrumbs::forPath('/'.$this->prefix().'/sales/orders/7/edit', 'Edit Order #7')
        );

        $this->assertSame(
            [
                ['label' => 'Settings', 'url' => route('admin.settings.index')],
                ['label' => 'Modules', 'url' => route('admin.modules.index')],
                ['label' => 'Banners', 'url' => url($this->prefix().'/settings/modules/banners')],
            ],
            Breadcrumbs::forPath('/'.$this->prefix().'/settings/modules/banners')
        );
    }

    /** Asserts each label appears, in order, inside the rendered breadcrumb <nav>. */
    private function assertBreadcrumbTrail(string $html, array $expectedLabels): void
    {
        $found = preg_match('/<nav[^>]*class="[^"]*mb-0\.5 flex items-center[^"]*"[^>]*>(.*?)<\/nav>/s', $html, $matches);
        $this->assertSame(1, $found, 'Breadcrumb <nav> not found in response HTML.');

        $trailText = trim(preg_replace('/\s+/', ' ', strip_tags($matches[1])));

        $lastPos = -1;
        foreach ($expectedLabels as $label) {
            $pos = strpos($trailText, $label, $lastPos + 1);
            $this->assertNotFalse($pos, "Expected breadcrumb label '{$label}' not found in order within trail: {$trailText}");
            $lastPos = $pos;
        }
    }

    private function prefix(): string
    {
        return config('admin.prefix', 'admin');
    }
}
