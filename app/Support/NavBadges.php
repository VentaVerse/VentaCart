<?php

namespace App\Support;

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
                if (class_exists(\Extensions\Reviews\Models\Review::class)) {
                    $badges['admin.reviews.index'] = \Extensions\Reviews\Models\Review::where('status', 'pending')->count();
                }
            }

            // To Pack only (owner ruling 2026-08-19): the badge is a call
            // to action, and packing is the store's move. To Handover is
            // the courier's wait, so it stays off the badge and lives in
            // the queue's own tab counts.
            if (Route::has('admin.fulfillment.index')
                && class_exists(\Extensions\CourierFulfillment\PlacementMap::class)
                && Schema::hasTable(\Extensions\CourierFulfillment\PlacementMap::TABLE)) {
                $badges['admin.fulfillment.index'] = DB::table('orders')
                    ->join(\Extensions\CourierFulfillment\PlacementMap::TABLE . ' as cf_p', 'orders.order_status_id', '=', 'cf_p.order_status_id')
                    ->where('cf_p.placement', 'to_pack')
                    ->count();
            }

            return array_filter($badges);
        });
    }
}
