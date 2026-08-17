<?php

namespace App\Support;

use App\Models\Review;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Work waiting behind a nav entry, keyed by the entry's route name.
 * The sidebar renders these as small brass counts beside the item.
 *
 * Only true worklists carry a badge: a count that asks to be cleared.
 * Abandoned carts deliberately has none; its scope is thousands of
 * stale carts, which is a report, not a queue.
 *
 * Cached briefly because this runs on every admin page load; a badge
 * that lags a minute is still telling the truth about where work is.
 */
class NavBadges
{
    public static function counts(): array
    {
        return Cache::remember('admin.nav-badges', 60, function () {
            $badges = [];

            if (Route::has('admin.reviews.index') && Schema::hasTable('reviews')) {
                $badges['admin.reviews.index'] = Review::where('status', 'pending')->count();
            }

            // The fulfillment queue's To Ship tally (to pack + to handover),
            // through the same placement map the queue's own tabs count with.
            if (Route::has('admin.fulfillment.index')
                && class_exists(\Extensions\CourierFulfillment\PlacementMap::class)
                && Schema::hasTable(\Extensions\CourierFulfillment\PlacementMap::TABLE)) {
                $badges['admin.fulfillment.index'] = DB::table('orders')
                    ->join(\Extensions\CourierFulfillment\PlacementMap::TABLE . ' as cf_p', 'orders.order_status_id', '=', 'cf_p.order_status_id')
                    ->whereIn('cf_p.placement', ['to_pack', 'to_handover'])
                    ->count();
            }

            return array_filter($badges);
        });
    }
}
