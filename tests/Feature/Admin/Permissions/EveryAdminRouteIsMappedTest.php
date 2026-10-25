<?php

namespace Tests\Feature\Admin\Permissions;

use App\Services\ExtensionManager;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * Every admin route, core and every bundled extension, either has an entry
 * in the permission map or is listed below as super admin only on purpose.
 * A new admin route that nobody mapped fails here, so it gets a decision
 * instead of quietly becoming super admin only.
 */
class EveryAdminRouteIsMappedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Admin routes left unmapped on purpose, so only a super admin opens
     * them. Name each one with the reason.
     *
     * @var list<string>
     */
    private const DELIBERATELY_SUPER_ADMIN_ONLY = [];

    protected function setUp(): void
    {
        parent::setUp();

        $manager = app(ExtensionManager::class);
        foreach ($manager->discovered()->keys() as $name) {
            if ($manager->state($name) === 'not_installed') {
                $manager->install($name);
            }
        }
        $manager->bootEnabled();
    }

    public function test_every_admin_route_is_mapped_or_deliberately_super_admin_only(): void
    {
        $permissions = app(AdminPermissions::class);
        $unmapped = [];

        foreach ($this->adminRoutes() as $route) {
            $name = (string) $route->getName();

            if ($name === '') {
                $unmapped[] = implode('|', $route->methods()) . ' /' . $route->uri() . ' (no route name)';

                continue;
            }

            if (! $permissions->isMapped($name) && ! in_array($name, self::DELIBERATELY_SUPER_ADMIN_ONLY, true)) {
                $unmapped[] = $name;
            }
        }

        $this->assertSame([], array_values(array_unique($unmapped)),
            'These admin routes have no entry in config/admin-permissions.php. Map each one, '
            . 'or list it in DELIBERATELY_SUPER_ADMIN_ONLY with the reason.');
    }

    public function test_every_mapped_route_needs_a_permission_the_catalog_defines(): void
    {
        $permissions = app(AdminPermissions::class);
        $known = AdminPermissions::slugs();
        $unknown = [];

        foreach ($this->adminRoutes() as $route) {
            $name = (string) $route->getName();

            foreach ($route->methods() as $method) {
                $required = $permissions->requirementFor($name, $method);

                if ($required === null || str_starts_with($required, '@')) {
                    continue;
                }

                if (! in_array($required, $known, true)) {
                    $unknown[] = "{$method} {$name} needs {$required}";
                }
            }
        }

        $this->assertSame([], $unknown, 'A map entry names a permission the catalog does not define.');
    }

    public function test_the_walk_saw_core_and_extension_routes(): void
    {
        $names = array_map(fn (Route $route) => $route->getName(), $this->adminRoutes());

        // Guards the walk itself: had the extensions not booted, the first
        // test would pass on core routes alone.
        $this->assertContains('admin.orders.index', $names);
        $this->assertContains('admin.attributes.index', $names);
        $this->assertContains('admin.coupons.index', $names);
        $this->assertContains('admin.theme.index', $names);
        $this->assertContains('admin.fulfillment.index', $names);
    }

    /** @return list<Route> */
    private function adminRoutes(): array
    {
        return array_values(array_filter(
            RouteFacade::getRoutes()->getRoutes(),
            fn (Route $route) => str_starts_with((string) $route->getName(), 'admin.')
                || in_array('admin.session.cap', $route->gatherMiddleware(), true)
        ));
    }
}
