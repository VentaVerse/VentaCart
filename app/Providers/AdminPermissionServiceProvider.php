<?php

namespace App\Providers;

use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Admin permissions as gates, so a controller, a Form Request, a Blade
 * @can or an extension asks the same question the route middleware does.
 *
 *   - Every permission slug is an ability: Gate::allows('orders.edit').
 *   - 'storefront.code' guards raw HTML, CSS or script the storefront
 *     prints (an HTML block, a custom HTML menu item, a theme's custom code
 *     field). It is super admin only and no role can be granted it.
 *   - A super admin passes every ability.
 */
class AdminPermissionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::before(function ($user) {
            return $user instanceof User && $user->isSuperAdmin() ? true : null;
        });

        Gate::define(AdminPermissions::STOREFRONT_CODE, fn ($user) => $user instanceof User && $user->isSuperAdmin());

        // Any ability nothing else defined is read as a permission slug.
        Gate::after(function ($user, string $ability, $result) {
            if ($result !== null || Gate::has($ability) || ! $user instanceof User) {
                return $result;
            }

            return $user->hasPermission($ability) ? true : null;
        });
    }
}
