<?php

namespace Tests\Feature\Api;

use App\Support\ApiAbility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Every API route must need a permission.
 *
 * An unmapped route is reachable by any key at all, whatever its ticks, and
 * nothing about it looks wrong: the request succeeds, the log shows a normal
 * call, and the permission system quietly stops meaning what the admin screen
 * says it means. So a route added later fails this test until it is placed.
 */
class ApiAbilityCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Extension routes only exist once their extensions have booted.
        app(\App\Services\ExtensionManager::class)->bootEnabled();
    }

    public function test_every_api_route_is_claimed_by_exactly_one_area(): void
    {
        $unmapped = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (! str_starts_with($uri, 'api/v1')) {
                continue;
            }

            foreach ($route->methods() as $method) {
                if (! in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                    continue;
                }

                // The real path shape, with route parameters made concrete.
                $path = preg_replace('/\{[^}]+\}/', 'x', $uri);
                $request = Request::create('/' . $path, $method);

                if (ApiAbility::for($request) === null) {
                    $unmapped[] = "{$method} /{$uri}";
                }
            }
        }

        $this->assertSame([], $unmapped,
            "These API routes need an entry in ApiAbility::map():\n  " . implode("\n  ", $unmapped));
    }

    public function test_every_entry_names_a_real_area_and_mode(): void
    {
        foreach (ApiAbility::map() as [$verb, $pattern, $area, $mode]) {
            $this->assertContains($area, ApiAbility::AREAS, "{$verb} {$pattern} names an unknown area");
            $this->assertContains($mode, ApiAbility::MODES, "{$verb} {$pattern} names an unknown mode");
        }
    }

    /** The nested fulfillment routes must not be swept up by orders. */
    public function test_fulfillment_on_the_order_path_is_not_orders(): void
    {
        $this->assertSame(['fulfillment', 'read'], ApiAbility::for(Request::create('/api/v1/orders/9/tracking', 'GET')));
        $this->assertSame(['fulfillment', 'write'], ApiAbility::for(Request::create('/api/v1/orders/9/book-courier', 'POST')));
        $this->assertSame(['orders', 'read'], ApiAbility::for(Request::create('/api/v1/orders/9', 'GET')));
        $this->assertSame(['orders', 'write'], ApiAbility::for(Request::create('/api/v1/orders/9/status', 'PUT')));
    }

    /** A quote changes nothing, so a read-only key may ask for one. */
    public function test_an_estimate_is_a_read_despite_being_a_post(): void
    {
        $this->assertSame(['fulfillment', 'read'], ApiAbility::for(Request::create('/api/v1/orders/9/estimate', 'POST')));
    }
}
