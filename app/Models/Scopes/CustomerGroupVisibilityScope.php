<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Customer-group product visibility.
 *
 * A product with NO rows in customer_group_product is public (everyone sees it).
 * A product WITH group rows is visible ONLY to logged-in customers whose
 * customer_group_id is among those groups — guests and other groups can't see
 * (or even resolve) it.
 *
 * Applies only to actual storefront requests:
 *  - Admin panel (web guard authenticated) sees ALL products — needed to manage them.
 *  - Console/queue (seeders, ERP sync, jobs) see ALL products.
 * So this never interferes with product management or background processing.
 */
class CustomerGroupVisibilityScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Bypass for everything that isn't a customer-facing storefront page:
        //  - CLI (seeders, queue, scheduled jobs)
        //  - admin panel (web guard authenticated)
        //  - API requests incl. the ERP sync (/api/*) — must see ALL products
        $request = request();
        if (app()->runningInConsole()
            || Auth::guard('web')->check()
            || ($request && $request->is('api/*'))) {
            return;
        }

        $groupId = Auth::guard('customer')->user()?->customer_group_id;

        $builder->where(function (Builder $q) use ($groupId) {
            // Public products: no group restriction at all.
            $q->whereDoesntHave('customerGroups');

            // Plus products explicitly granted to the viewer's group.
            if ($groupId) {
                $q->orWhereHas('customerGroups', function (Builder $x) use ($groupId) {
                    $x->where('customer_groups.id', $groupId);
                });
            }
        });
    }
}
