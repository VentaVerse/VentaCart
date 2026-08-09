<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\Cart;
use App\Models\Currency;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Review;
use App\Models\SeoSuggestion;
use App\Support\AdminNav;
use App\Support\LowStock;
use Cron\CronExpression;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The day sheet: today's page in the shop's daybook.
 *
 * Everything here answers one of five questions, in the order the sheet
 * asks them: what day is it and where does it stand, what did today take,
 * what does today need doing, what came in today, and what is outstanding
 * that is not about today at all.
 *
 * QUERY BUDGET. This page loads on every login, against a store carrying
 * ~27,700 orders. Nothing below scans that table: the day's three figures
 * and their two comparisons come out of ONE aggregate over an eight-day
 * window (seekable since the orders_store_created_index migration), the
 * work counts are one grouped query over a handful of status ids, and the
 * entries are one bounded window read. No count is derived by hydrating
 * models and counting them in PHP.
 *
 * THE FULFILLMENT MODULE IS OPTIONAL. Its three work lines read the
 * module's own status-to-placement mapping rather than any private copy of
 * it, and vanish entirely when the module is disabled or uninstalled. The
 * sheet is still a sheet without them.
 */
class DashboardController extends Controller
{
    /**
     * Status names whose orders were placed but never became money the
     * merchant keeps. Revenue and the average order leave them out;
     * "Orders placed" still counts them, because they were placed.
     *
     * A NAME list, and that is the honest limit of it: core carries no
     * "this status counts as revenue" flag, and a merchant who renames
     * Canceled to something else falls out of this set until they say so.
     * Both spellings of cancelled are here because OpenCart-migrated
     * stores carry one and fresh stores the other. "Canceled Reversal" is
     * deliberately absent: it means the cancellation itself was undone, so
     * the order is live again.
     */
    private const NON_REVENUE_STATUS_NAMES = [
        'Canceled', 'Cancelled', 'Denied', 'Failed',
        'Refunded', 'Reversed', 'Chargeback', 'Voided', 'Expired',
    ];

    /** The most entries the sheet prints before sending the reader to Orders. */
    private const MAX_ENTRIES = 50;

    public function index(Request $request)
    {
        $storeId = (int) config('app.store_id', 1);
        $now = Carbon::now();

        $today = $now->copy()->startOfDay();
        $tomorrow = $today->copy()->addDay();
        $yesterday = $today->copy()->subDay();
        $weekAgo = $today->copy()->subWeek();

        $currency = Currency::where('is_default', true)->value('symbol') ?: '';

        $figures = $this->dayFigures($storeId, $today, $tomorrow, $yesterday, $weekAgo);

        $fulfillment = $this->fulfillmentWork($storeId);
        $lowStockCount = LowStock::count();

        $workLines = array_merge($fulfillment['lines'], [[
            'label' => 'Stock about to run out',
            'count' => $lowStockCount,
            'url'   => route('admin.products.index', ['stock' => 'low']),
        ]]);

        $entries = $this->entries($storeId, $today, $tomorrow);
        $entriesTruncated = $entries->count() > self::MAX_ENTRIES;
        $entries = $entries->take(self::MAX_ENTRIES);

        // Only asked when today is blank, and answered off the primary key
        // rather than a date scan.
        $lastEntryAt = $entries->isEmpty()
            ? Order::where('store_id', $storeId)->whereNotNull('order_status_id')->latest('id')->value('created_at')
            : null;

        $automations = Automation::enabled()->orderBy('name')->get();
        $unhealthyAutomations = $automations->filter(
            fn (Automation $automation) => $this->isAutomationUnhealthy($automation)
        )->values();

        $standingLines = [
            [
                'label' => 'Reviews awaiting approval',
                'count' => Review::pending()->count(),
                'url'   => route('admin.reviews.index'),
            ],
            [
                'label' => 'SEO suggestions pending',
                'count' => SeoSuggestion::pending()->count(),
                'url'   => route('admin.extensions.seo.review'),
            ],
            [
                'label' => 'Carts recoverable',
                'count' => Cart::whereHas('items')->where('updated_at', '<', $now->copy()->subHour())->count(),
                'url'   => route('admin.abandoned-carts.index'),
            ],
        ];

        return view('admin.dashboard', [
            'sheetDate'            => $today,
            'standing'             => $this->standingSentence($figures, $currency, $fulfillment),
            'currency'             => $currency,
            'figures'              => $figures,
            'comparisons'          => [
                ['key' => 'yesterday', 'label' => 'Yesterday'],
                ['key' => 'weekAgo', 'label' => $weekAgo->format('D j M')],
            ],
            'workLines'            => $workLines,
            'entries'              => $entries,
            'entriesTruncated'     => $entriesTruncated,
            'lastEntryLabel'       => $this->whenLastEntryArrived($lastEntryAt, $today),
            'standingLines'        => $standingLines,
            'automations'          => $automations,
            'unhealthyAutomations' => $unhealthyAutomations,
        ]);
    }

    /**
     * Revenue taken, orders placed and the average order, for today and for
     * the two references beside it: yesterday, and the same weekday a week
     * back (a Saturday compares to a Saturday; comparing a Saturday to a
     * Friday would read as a collapse every Monday).
     *
     * Yesterday and last week are WHOLE days, today is the day so far. That
     * asymmetry is real and is the reason the comparison columns are set
     * quietly rather than as a percentage delta: a morning figure against a
     * finished day is a reference point, not a verdict.
     *
     * ONE query. Nine conditional aggregates over an eight-day window,
     * seekable on (store_id, created_at), so the work is proportional to a
     * week of orders and never to the table.
     *
     * The paid/unpaid split the day sheet's brief also proposed is NOT
     * here: core has no record of payment (no paid_at, no paid flag), and
     * the only honest "unpaid" in this application is the fulfillment
     * module's own placement, which would make a core figure vanish with an
     * optional module. It appears as a work line instead, where it belongs.
     */
    private function dayFigures(int $storeId, Carbon $today, Carbon $tomorrow, Carbon $yesterday, Carbon $weekAgo): array
    {
        $cancelledIds = OrderStatus::where('store_id', $storeId)
            ->whereIn('name', self::NON_REVENUE_STATUS_NAMES)
            ->pluck('id')
            ->all();

        // No cancelled statuses configured => every placed order is revenue.
        $live = $cancelledIds === []
            ? '1'
            : 'orders.order_status_id NOT IN (' . implode(',', array_map('intval', $cancelledIds)) . ')';

        $windows = [
            'today'     => [$today, $tomorrow],
            'yesterday' => [$yesterday, $today],
            'weekAgo'   => [$weekAgo, $weekAgo->copy()->addDay()],
        ];

        $selects = [];
        $bindings = [];
        foreach ($windows as $key => [$from, $to]) {
            $in = 'created_at >= ? AND created_at < ?';
            $selects[] = "SUM(CASE WHEN {$in} THEN 1 ELSE 0 END) AS placed_{$key}";
            $selects[] = "SUM(CASE WHEN {$in} AND {$live} THEN 1 ELSE 0 END) AS live_{$key}";
            $selects[] = "SUM(CASE WHEN {$in} AND {$live} THEN total ELSE 0 END) AS revenue_{$key}";
            array_push($bindings, $from, $to, $from, $to, $from, $to);
        }

        $row = Order::query()
            ->where('store_id', $storeId)
            // Ghost orders (no status) are not placed orders: the core
            // Orders page hides them by default and the day sheet agrees.
            ->whereNotNull('orders.order_status_id')
            ->where('created_at', '>=', $weekAgo)
            ->selectRaw(implode(', ', $selects), $bindings)
            ->first();

        $revenue = [];
        $placed = [];
        $average = [];
        foreach (array_keys($windows) as $key) {
            $revenue[$key] = (float) ($row->{"revenue_{$key}"} ?? 0);
            $placed[$key] = (int) ($row->{"placed_{$key}"} ?? 0);
            $liveCount = (int) ($row->{"live_{$key}"} ?? 0);
            $average[$key] = $liveCount > 0 ? $revenue[$key] / $liveCount : 0.0;
        }

        return [
            [
                'key' => 'revenue',
                'label' => 'Revenue taken',
                'money' => true,
                'hint' => 'Cancelled, refunded and failed orders are left out.',
                'values' => $revenue,
            ],
            [
                'key' => 'placed',
                'label' => 'Orders placed',
                'money' => false,
                'hint' => null,
                'values' => $placed,
            ],
            [
                'key' => 'average',
                'label' => 'Average order',
                'money' => true,
                'hint' => 'Revenue taken divided by the orders it came from.',
                'values' => $average,
            ],
        ];
    }

    /**
     * The three work lines the fulfillment module owns, or none at all.
     *
     * MEMBERSHIP IS NOT REIMPLEMENTED HERE. An order belongs to a tab when
     * its core order status is placed in that tab, which is exactly what
     * FulfillmentService::tabBucket() answers per row and what the queue's
     * own tally answers in SQL. This reads the same module-owned mapping
     * (PlacementMap) for the status ids, then counts orders on those ids in
     * one grouped query. Three placements, a handful of ids, one index seek
     * each: the queue's own tally groups over EVERY order because it has to
     * total tabs the sheet does not show.
     *
     * THE GATE IS THE SIDEBAR'S OWN. AdminNav::visibleItem() answers the
     * whole composite question already: the route is registered (module
     * installed, enabled and booted), this user holds the permission, and
     * the merchant has not paused the feature. A queue the sidebar refuses
     * to link to must not be linked to from here either. class_exists is
     * the belt to that braces, because a runtime PSR-4 autoloader can leave
     * an extension's classes loadable in a long-lived process after it has
     * been disabled.
     *
     * Core names no fulfillment URL: the module publishes the URL for a
     * placement itself (FulfillmentService::queueUrl), so the fact that To
     * Pack is a sub-tab under To Ship stays inside the module that decided
     * it.
     */
    private function fulfillmentWork(int $storeId): array
    {
        $off = ['enabled' => false, 'lines' => [], 'counts' => []];

        if (AdminNav::visibleItem('fulfillment', Auth::user()) === null
            || ! class_exists(\Extensions\CourierFulfillment\PlacementMap::class)
            || ! class_exists(\Extensions\CourierFulfillment\FulfillmentService::class)) {
            return $off;
        }

        // placement => the line's own wording, in the daybook's voice
        $wanted = [
            'to_pack'     => 'Parcels to pack',
            'to_handover' => 'Parcels awaiting handover',
            'unpaid'      => 'Orders unpaid',
        ];

        $idsByPlacement = [];
        $allIds = [];
        foreach (array_keys($wanted) as $placement) {
            $ids = \Extensions\CourierFulfillment\PlacementMap::statusIdsFor($placement);
            $idsByPlacement[$placement] = $ids;
            $allIds = array_merge($allIds, $ids);
        }

        $perStatus = [];
        if ($allIds !== []) {
            $perStatus = Order::query()
                ->where('store_id', $storeId)
                ->whereIn('order_status_id', $allIds)
                ->groupBy('order_status_id')
                ->pluck(DB::raw('COUNT(*)'), 'order_status_id')
                ->all();
        }

        $lines = [];
        $counts = [];
        foreach ($wanted as $placement => $label) {
            $count = 0;
            foreach ($idsByPlacement[$placement] as $id) {
                $count += (int) ($perStatus[$id] ?? 0);
            }

            $counts[$placement] = $count;
            $lines[] = [
                'label' => $label,
                'count' => $count,
                'url'   => \Extensions\CourierFulfillment\FulfillmentService::queueUrl($placement),
            ];
        }

        return ['enabled' => true, 'lines' => $lines, 'counts' => $counts];
    }

    /**
     * Today's orders, earliest first, because a daybook page is read down.
     *
     * Bounded by the day and then by MAX_ENTRIES + 1, so a store having an
     * exceptional day renders a sheet rather than a thousand rows, and the
     * extra row is what tells the view to offer Orders. Line items are
     * eager loaded off their snapshot columns so the "what they bought"
     * column costs one query rather than one per order.
     */
    private function entries(int $storeId, Carbon $today, Carbon $tomorrow)
    {
        return Order::query()
            ->where('store_id', $storeId)
            ->whereNotNull('order_status_id')
            ->where('created_at', '>=', $today)
            ->where('created_at', '<', $tomorrow)
            ->with([
                'status:id,name',
                'products:id,order_id,name,variant_label,quantity',
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(self::MAX_ENTRIES + 1)
            ->get();
    }

    /**
     * One plain sentence of where today stands, composed from the figures
     * already computed. Never an alarm and never advice: it says what is
     * true, and a quiet day is allowed to read as a quiet day.
     */
    private function standingSentence(array $figures, string $currency, array $fulfillment): string
    {
        $placed = $this->figureValue($figures, 'placed', 'today');
        $revenue = $this->figureValue($figures, 'revenue', 'today');
        $toPack = $fulfillment['counts']['to_pack'] ?? null;

        $packing = match (true) {
            $toPack === null => null,
            $toPack === 0 => 'nothing waiting to be packed',
            $toPack === 1 => '1 parcel waiting to be packed',
            default => $toPack . ' parcels waiting to be packed',
        };

        if ($placed === 0) {
            return $packing === null
                ? 'No orders yet today.'
                : 'No orders yet today, and ' . $packing . '.';
        }

        $orders = $placed === 1 ? '1 order so far' : $placed . ' orders so far';
        $taken = $currency . number_format($revenue, 2) . ' taken';

        return $packing === null
            ? $orders . ', ' . $taken . '.'
            : $orders . ', ' . $taken . ', and ' . $packing . '.';
    }

    /**
     * When the last order actually arrived, said the way a person would.
     * "1 day ago" is a duration; a merchant looking at a blank page wants
     * the day and the hour, and beyond a week they want the date.
     */
    private function whenLastEntryArrived(?Carbon $at, Carbon $today): ?string
    {
        if (! $at) {
            return null;
        }

        $time = $at->format('g:i a');
        $daysBack = (int) $at->copy()->startOfDay()->diffInDays($today);

        if ($daysBack === 1) {
            return 'yesterday at ' . $time;
        }

        if ($daysBack < 7) {
            return 'on ' . $at->format('l') . ' at ' . $time;
        }

        return 'on ' . $at->format('j F Y');
    }

    private function figureValue(array $figures, string $key, string $window): int|float
    {
        foreach ($figures as $figure) {
            if ($figure['key'] === $key) {
                return $figure['values'][$window] ?? 0;
            }
        }

        return 0;
    }

    /**
     * An enabled automation is unhealthy if its last run failed, or if it
     * missed the run the cron expression says should already have happened
     * (never run, or last run predates that expected run time).
     */
    private function isAutomationUnhealthy(Automation $automation): bool
    {
        if ($automation->last_run_status === 'failed') {
            return true;
        }

        if (! $automation->cron_expression) {
            return false;
        }

        try {
            $expectedRun = (new CronExpression($automation->cron_expression))->getPreviousRunDate(now());
        } catch (Throwable $e) {
            return false;
        }

        return ! $automation->last_run_at || $automation->last_run_at->lt($expectedRun);
    }
}
