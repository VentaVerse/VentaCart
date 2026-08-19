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
        'marketing', 'extensions', 'settings', 'profile',
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

    public function test_marketing_page_moved_to_coupons_extension_home(): void
    {
        // Coupons became a total-type extension (order-totals wave): its
        // one home is extensions/coupons and the old marketing mount
        // 301s in, path preserved.
        $this->actingAs(User::factory()->create());

        $manager = app(\App\Services\ExtensionManager::class);
        $manager->install('coupons');
        $manager->bootEnabled();

        $this->get('/'.$this->prefix().'/extensions/coupons')->assertOk();
        $this->get('/'.$this->prefix().'/marketing/coupons')
            ->assertStatus(301)
            ->assertRedirectContains('/extensions/coupons');
    }

    public function test_settings_page_is_ok_at_new_url(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/'.$this->prefix().'/settings')->assertOk();
    }

    public function test_settings_modules_page_is_ok_at_new_url(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/'.$this->prefix().'/extensions')->assertOk();
    }

    public function test_marketing_seo_workspace_is_ok_at_new_url(): void
    {
        $this->actingAs(User::factory()->create());
        // SEO ships NOT installed (owner ruling 2026-08-19), and its routes
        // only exist once it is, so the test installs it exactly as the
        // coupons and fulfillment tests above do.
        app(\App\Services\ExtensionManager::class)->install('seo');
        app(\App\Services\ExtensionManager::class)->bootEnabled();

        $this->get('/'.$this->prefix().'/extensions/seo')->assertOk();
    }

    public function test_settings_seo_engine_config_is_ok_at_new_url(): void
    {
        $this->actingAs(User::factory()->create());
        // SEO ships NOT installed (owner ruling 2026-08-19), and its routes
        // only exist once it is, so the test installs it exactly as the
        // coupons and fulfillment tests above do.
        app(\App\Services\ExtensionManager::class)->install('seo');
        app(\App\Services\ExtensionManager::class)->bootEnabled();

        $this->get('/'.$this->prefix().'/extensions/seo/settings')->assertOk();
    }

    /**
     * Task 2 (fulfillment-polish): courier-fulfillment's operational queue,
     * now mounted under Sales instead of a bare '/fulfillment' root. The
     * extension is installed in-test (real extension.json, not a fixture —
     * same pattern as ExtensionLifecycleTest's banners/courier-fulfillment
     * coverage) since its routes only exist once installed + enabled.
     */
    public function test_fulfillment_queue_page_is_ok_at_new_url(): void
    {
        $this->actingAs(User::factory()->create());
        app(ExtensionManager::class)->install('courier-fulfillment');
        // Task 4: 'fulfillment_enabled' is opt-out (missing/NULL means active),
        // so no setting write is needed here — see FulfillmentController::index().

        $this->get('/'.$this->prefix().'/extensions/courier-fulfillment')->assertOk();
    }

    /** Task 2: courier-fulfillment's settings page, now under Settings > Modules. */
    public function test_fulfillment_settings_page_is_ok_at_new_url(): void
    {
        $this->actingAs(User::factory()->create());
        app(ExtensionManager::class)->install('courier-fulfillment');

        $this->get('/'.$this->prefix().'/extensions/courier-fulfillment')->assertOk();
    }

    /**
     * Task W3 (spx-courier plan): per-courier settings pages under the hub,
     * grammar-conformant at extensions/courier-fulfillment/{courier}.
     * An unregistered courier key is a 404, never a rendered page.
     */
    public function test_fulfillment_courier_settings_page_is_ok_at_new_url(): void
    {
        $this->actingAs(User::factory()->create());
        app(ExtensionManager::class)->install('courier-fulfillment');

        $this->get('/'.$this->prefix().'/extensions/courier-fulfillment/quadx')->assertOk();
        $this->get('/'.$this->prefix().'/extensions/courier-fulfillment/nope')->assertNotFound();
    }

    /**
     * Task W4 (spx-courier plan): the returns monitoring page, one segment
     * deeper than the queue itself — grammar-conformant at
     * sales/fulfillment/returns.
     */
    public function test_fulfillment_returns_page_is_ok_at_new_url(): void
    {
        $this->actingAs(User::factory()->create());
        app(ExtensionManager::class)->install('courier-fulfillment');

        $this->get('/'.$this->prefix().'/extensions/courier-fulfillment/returns')->assertOk();
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

    public function test_extensions_seo_is_the_dashboard_itself(): void
    {
        // One home (owner ruling 2026-08-19): the SEO workspace lives AT
        // extensions/seo; the old marketing/seo mount 301s into it.
        app(\App\Services\ExtensionManager::class)->install('seo');
        $this->actingAs(User::factory()->create());

        $this->get('/'.$this->prefix().'/extensions/seo')->assertOk();

        $this->get('/'.$this->prefix().'/marketing/seo')
            ->assertStatus(301)
            ->assertRedirect('/'.$this->prefix().'/extensions/seo');
    }

    public function test_extensions_seo_settings_is_a_real_page_again(): void
    {
        // Wave 0 repin: the engine settings' home IS extensions/seo/settings
        // now; the legacy redirect that used to occupy this URL is gone
        // (it would have shadowed the real route in the collection).
        $this->actingAs(User::factory()->create());
        // SEO ships NOT installed (owner ruling 2026-08-19), and its routes
        // only exist once it is, so the test installs it exactly as the
        // coupons and fulfillment tests above do.
        app(\App\Services\ExtensionManager::class)->install('seo');
        app(\App\Services\ExtensionManager::class)->bootEnabled();

        $this->get('/'.$this->prefix().'/extensions/seo/settings')->assertOk();
        $this->get('/'.$this->prefix().'/settings/modules/seo/settings')
            ->assertStatus(301);
    }

    public function test_old_abandoned_carts_settings_redirects_to_settings_modules(): void
    {
        $this->actingAs(User::factory()->create());
        app(ExtensionManager::class)->install('abandoned-carts');

        $response = $this->get('/'.$this->prefix().'/abandoned-carts/settings');

        $response->assertStatus(301);
        $response->assertRedirect(route('admin.abandoned-carts.settings'));
        $this->assertStringContainsString('/extensions/abandoned-carts/settings', $response->headers->get('Location'));
    }

    public function test_old_abandoned_carts_index_still_resolves_operationally(): void
    {
        $this->actingAs(User::factory()->create());
        app(ExtensionManager::class)->install('abandoned-carts');

        $response = $this->get('/'.$this->prefix().'/abandoned-carts');

        $response->assertStatus(301);
        $response->assertRedirect(route('admin.abandoned-carts.index'));
        $this->assertStringContainsString('/extensions/abandoned-carts', $response->headers->get('Location'));
    }

    public function test_old_modules_url_redirects_to_settings_modules(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/'.$this->prefix().'/modules');

        $response->assertStatus(301);
        $response->assertRedirect(route('admin.extensions.index'));
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

    /**
     * Task 2: old flat '/fulfillment' URLs 301 to their new grammar homes.
     * Deliberately does NOT install courier-fulfillment first — these
     * redirects must work even when the extension is currently disabled/not
     * installed (adminLegacyExtensionRedirect() builds the destination as a
     * literal path, never via route($name), for exactly this reason; see
     * routes/admin/redirects.php).
     */
    public function test_old_fulfillment_url_redirects_to_sales_fulfillment(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/'.$this->prefix().'/fulfillment');

        $response->assertStatus(301);
        $this->assertStringContainsString('/'.$this->prefix().'/extensions/courier-fulfillment', $response->headers->get('Location'));
    }

    public function test_old_fulfillment_settings_url_redirects_to_settings_modules_courier_fulfillment(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/'.$this->prefix().'/fulfillment/settings');

        $response->assertStatus(301);
        $this->assertStringContainsString('/'.$this->prefix().'/extensions/courier-fulfillment', $response->headers->get('Location'));
    }

    public function test_old_fulfillment_label_url_redirects_preserving_the_order_param(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/'.$this->prefix().'/fulfillment/456/label');

        $response->assertStatus(301);
        $this->assertStringContainsString('/'.$this->prefix().'/extensions/courier-fulfillment/456/label', $response->headers->get('Location'));
    }

    public function test_old_admin_banners_literal_url_redirects_to_settings_modules_banners(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/admin/banners');

        $response->assertStatus(301);
        $this->assertStringContainsString(
            '/'.$this->prefix().'/extensions/banners',
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

        // Floor drops as conversions move controllers into extensions/
        // (their view references live in the extensions' own tests).
        $this->assertGreaterThan(30, count($names), 'View-reference sweep matched suspiciously few names — regex likely broken.');

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
    // form, and a extensions/{name} sub-page — each rendered end to
    // end through x-admin.page's auto-derivation (no :breadcrumb passed).
    // ------------------------------------------------------------------

    public function test_breadcrumb_trail_on_extension_page_shows_settings_modules_and_manifest_title(): void
    {
        $this->actingAs(User::factory()->create());
        app(ExtensionManager::class)->install('banners');

        $response = $this->get('/'.$this->prefix().'/extensions/banners');

        $response->assertOk();
        // v repin: the settings space IS Settings, so the trail starts
        // at Modules like every other v settings page.
        $this->assertBreadcrumbTrail($response->getContent(), ['Extensions', 'Banners']);
    }

    /** Task 2: the Fulfillment sidebar item (admin-nav) supplies the queue's crumb. */
    public function test_breadcrumb_trail_on_fulfillment_queue_page(): void
    {
        $this->actingAs(User::factory()->create());
        app(ExtensionManager::class)->install('courier-fulfillment');

        $response = $this->get('/'.$this->prefix().'/extensions/courier-fulfillment');

        $response->assertOk();
        $this->assertBreadcrumbTrail($response->getContent(), ['Sales', 'Fulfillment']);
    }

    /**
     * Task 2: the settings page has no matching settings-nav item (the
     * reserved hub-card slot's key is 'fulfillment', deliberately distinct
     * from the extension's own name/URL segment 'courier-fulfillment') —
     * proving the crumb falls through to ExtensionManager's manifest title,
     * same mechanism the banners test above exercises.
     */
    public function test_breadcrumb_trail_on_fulfillment_settings_page(): void
    {
        $this->actingAs(User::factory()->create());
        app(ExtensionManager::class)->install('courier-fulfillment');

        $response = $this->get('/'.$this->prefix().'/extensions/courier-fulfillment/settings');

        $response->assertOk();
        $this->assertBreadcrumbTrail($response->getContent(), ['Extensions', 'Fulfillment']);
    }

    /**
     * Task W3: a per-courier settings page is one segment deeper than the
     * extension's extensions/{name} mount, so the derivation's
     * "deeper than the module" rule appends the page's own title (the
     * courier's name) as the trailing crumb — no new plumbing.
     */
    public function test_breadcrumb_trail_on_fulfillment_courier_settings_page(): void
    {
        $this->actingAs(User::factory()->create());
        app(ExtensionManager::class)->install('courier-fulfillment');

        $response = $this->get('/'.$this->prefix().'/extensions/courier-fulfillment/quadx');

        $response->assertOk();
        $this->assertBreadcrumbTrail($response->getContent(), ['Extensions', 'Fulfillment', 'QuadX']);
    }

    /**
     * Task W4: the returns page is one segment deeper than the queue's own
     * admin-nav entry ('fulfillment'), so the "deeper than the entity" rule
     * appends the page's own title ('Returns') as the trailing crumb.
     */
    public function test_breadcrumb_trail_on_fulfillment_returns_page(): void
    {
        $this->actingAs(User::factory()->create());
        app(ExtensionManager::class)->install('courier-fulfillment');

        $response = $this->get('/'.$this->prefix().'/extensions/courier-fulfillment/returns');

        $response->assertOk();
        $this->assertBreadcrumbTrail($response->getContent(), ['Sales', 'Fulfillment', 'Returns']);
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
        app(ExtensionManager::class)->install('abandoned-carts');

        $response = $this->get('/'.$this->prefix().'/extensions/abandoned-carts/rules');

        $response->assertOk();
        $this->assertBreadcrumbTrail($response->getContent(), ['Extensions', 'Abandoned Carts', 'Rules']);
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
                ['label' => 'Extensions', 'url' => route('admin.extensions.index')],
                ['label' => 'Banners', 'url' => url($this->prefix().'/extensions/banners')],
            ],
            Breadcrumbs::forPath('/'.$this->prefix().'/extensions/banners/slides')
        );
    }

    /** Asserts each label appears, in order, inside the rendered breadcrumb <nav>. */
    private function assertBreadcrumbTrail(string $html, array $expectedLabels): void
    {
        // Two trail generations: the v2 shell's <nav class="v-day__trail">
        // and the v head's <div class="v-head__trail"> both count.
        $found = preg_match('/<(nav|div)[^>]*class="[^"]*(?:v-day__trail|v-head__trail)[^"]*"[^>]*>(.*?)<\/\1>/s', $html, $matches);
        $this->assertSame(1, $found, 'Breadcrumb <nav> not found in response HTML.');

        $trailText = trim(preg_replace('/\s+/', ' ', strip_tags($matches[2])));

        $lastPos = -1;
        foreach ($expectedLabels as $label) {
            $pos = strpos($trailText, $label, $lastPos + 1);
            $this->assertNotFalse($pos, "Expected breadcrumb label '{$label}' not found in order within trail: {$trailText}");
            $lastPos = $pos;
        }
    }

    /**
     * Every string used as a ROUTE name anywhere in the app must resolve.
     *
     * Guards the Plan-5 name-freeze: view/controller/config paths were
     * re-sectioned, and a route name accidentally rewritten to match the new
     * folder grammar (e.g. 'admin.catalog.products.edit') fails only at render
     * time on the one page that uses it. Covers route()/->route()/Route::has()
     * call sites plus the SEO entity map's dynamic editRoute values, which no
     * static call-site scan can see.
     */
    public function test_every_route_name_referenced_in_code_resolves(): void
    {
        // Core views may name an EXTENSION-provided route, guarded by
        // Route::has() so the page stays coherent without that module (the
        // shipping page's link to the fulfillment module's pickup addresses
        // is one). Those names only exist once the extension is booted, so
        // every discovered extension is installed here before the route
        // table is read - otherwise this guard would report a perfectly
        // correct, guarded reference as stale.
        $manager = app(\App\Services\ExtensionManager::class);
        foreach ($manager->discovered()->keys() as $extension) {
            $manager->install($extension);
        }
        $manager->bootEnabled();

        $names = collect(app('router')->getRoutes())
            ->map(fn ($r) => $r->getName())
            ->filter()
            ->flip();

        // Route parameter names and dead unrouted Breeze scaffolding views.
        $ignore = ['coupon', 'pattern', 'type', 'token', 'register', 'verification.send'];

        $stale = [];

        foreach ([['app', '.php'], ['resources/views', '.blade.php'], ['config', '.php']] as [$dir, $ext]) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir)));

            foreach ($files as $file) {
                if (! $file->isFile() || ! str_ends_with($file->getFilename(), $ext)) {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());

                if (preg_match_all('/(?:->route|[^_a-zA-Z>]route|Route::has|routeIs)\(\s*[\'"]([a-zA-Z0-9_.\-]+)[\'"]/', $contents, $matches)) {
                    foreach ($matches[1] as $name) {
                        if (! $names->has($name) && ! in_array($name, $ignore, true)) {
                            $stale[$name] = str_replace(base_path().'/', '', $file->getPathname());
                        }
                    }
                }
            }
        }

        // Dynamic route names the SEO workspace resolves at render time
        // (the controller lives in the seo extension, booted above).
        $controller = app(\Extensions\Seo\Http\Controllers\SeoExtensionController::class);
        $method = new \ReflectionMethod($controller, 'entityConfigs');
        $method->setAccessible(true);

        foreach ($method->invoke($controller) as $type => $config) {
            $route = $config['editRoute'] ?? null;

            if ($route !== null && ! $names->has($route)) {
                $stale[$route] = "SeoExtensionController::entityConfigs()[{$type}]";
            }
        }

        $this->assertSame([], $stale, 'Unresolvable route names: '.json_encode($stale, JSON_PRETTY_PRINT));
    }

    private function prefix(): string
    {
        return config('admin.prefix', 'admin');
    }
}
