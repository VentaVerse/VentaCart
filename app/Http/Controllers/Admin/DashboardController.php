<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\Cart;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\SeoSuggestion;
use App\Models\Setting;
use App\Support\AdminNav;
use App\Support\LowStock;
use App\Support\RunningAccounts;
use App\Support\Standing;
use Cron\CronExpression;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The day sheet: today's page in the shop's daybook.
 *
 * It is read straight down, and it now leads with STANDING rather than with
 * work. A merchant opens the admin once at the start of the day and wants
 * one question answered before anything else: is the business alright. That
 * question is comparative, so no figure on the pulse appears without the
 * reference it should be read against, and the references are year on year
 * because retail is seasonal and month on month lies about it.
 *
 * The movements, in order:
 *
 *   1  the head        the date, and one plain sentence of where today is
 *   2  the pulse       this month and today, each against its own reference,
 *                      over a plot of two years of revenue
 *   3  needs doing     the handoff into the day's actual work
 *   4  running accounts what has been selling, and how it was paid for
 *   5  today's entries the day's orders as ledger lines
 *   6  outstanding     what is waiting and is not about today
 *
 * A STORE THAT HAS NEVER TRADED IS NOT AN EDGE CASE. It is the first thing
 * every Venta merchant sees, so it gets its own composition rather than a
 * page of zeros: the pulse, the accounts and the entries are not drawn at
 * all, and an opening movement names what the store still needs before it
 * can take an order. See openingLines().
 *
 * QUERY BUDGET. This page loads on every login against a store carrying
 * ~27,700 orders. Nothing below scans that table. The pulse is three
 * aggregates that read only orders_store_created_money_index; the running
 * accounts are five queries bounded by their stated period; the work counts
 * are one grouped query over a handful of status ids; the entries are one
 * bounded window read. No count is derived by hydrating models and counting
 * them in PHP.
 *
 * THE FULFILLMENT MODULE IS OPTIONAL. Its three work lines read the
 * module's own status-to-placement mapping rather than any private copy of
 * it, and vanish entirely when the module is disabled or uninstalled. The
 * sheet is still a sheet without them.
 */
class DashboardController extends Controller
{
    /** The most entries the sheet prints before sending the reader to Orders. */
    private const MAX_ENTRIES = 50;

    /** The window the running accounts state and cover. */
    private const ACCOUNT_DAYS = 30;

    public function index(Request $request)
    {
        $storeId = (int) config('app.store_id', 1);
        $now = Carbon::now();

        $today = $now->copy()->startOfDay();
        $tomorrow = $today->copy()->addDay();

        $currency = Currency::where('is_default', true)->value('symbol') ?: '';

        // Asked off an index and answered by the first matching row. It
        // decides the whole shape of the page, so it is asked first and
        // nothing expensive runs behind it on a store with no trade.
        $hasTraded = Order::query()
            ->where('store_id', $storeId)
            ->whereNotNull('order_status_id')
            ->exists();

        $fulfillment = $this->fulfillmentWork($storeId);
        $lowStockCount = LowStock::count();

        $workLines = array_merge($fulfillment['lines'], [[
            'label' => 'Stock about to run out',
            'count' => $lowStockCount,
            'url'   => route('admin.products.index', ['stock' => 'low']),
        ]]);

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

        $shared = [
            'sheetDate'            => $today,
            'currency'             => $currency,
            'hasTraded'            => $hasTraded,
            'workLines'            => $workLines,
            'standingLines'        => $standingLines,
            'automations'          => $automations,
            'unhealthyAutomations' => $unhealthyAutomations,
        ];

        if (! $hasTraded) {
            return view('admin.dashboard', array_merge($shared, [
                'standing'     => 'No orders yet.',
                'openingLines' => $this->openingLines($storeId),
                'workNeeded'   => $this->anyCount($workLines),
                'standingNeeded' => $this->anyCount($standingLines) || $unhealthyAutomations->isNotEmpty(),
            ]));
        }

        $pulse = Standing::for($storeId, $now);

        $accountsFrom = $today->copy()->subDays(self::ACCOUNT_DAYS - 1);
        $accounts = RunningAccounts::for($storeId, $accountsFrom, $tomorrow);

        $entries = $this->entries($storeId, $today, $tomorrow);
        $entriesTruncated = $entries->count() > self::MAX_ENTRIES;
        $entries = $entries->take(self::MAX_ENTRIES);

        // Only asked when today is blank, and answered off the primary key
        // rather than a date scan.
        $lastEntryAt = $entries->isEmpty()
            ? Order::where('store_id', $storeId)->whereNotNull('order_status_id')->latest('id')->value('created_at')
            : null;

        return view('admin.dashboard', array_merge($shared, [
            'standing'         => $this->standingSentence($pulse, $currency, $fulfillment),
            'pulse'            => $pulse,
            'bands'            => $this->pulseBands($pulse, $now),
            'plot'             => $pulse['plot'],
            'accounts'         => $accounts,
            'accountsLabel'    => 'Last ' . self::ACCOUNT_DAYS . ' days',
            'entries'          => $entries,
            'entriesTruncated' => $entriesTruncated,
            'lastEntryLabel'   => $this->whenLastEntryArrived($lastEntryAt, $today),
        ]));
    }

    /**
     * The pulse as two ruled bands, in the ledger grammar the sheet already
     * speaks: a name on the left, the figure, its reference, and the change
     * between them.
     *
     * Two bands rather than one because the two questions have different
     * references and mixing them into one table would imply a shared one.
     * The month is measured against the same month a year ago; today is
     * measured against what this weekday usually looks like.
     *
     * Both bands are labelled with the exact span they cover, because "this
     * month" against "last year" is only honest if both sides stop on the
     * same day, and a reader has to be able to see that they do.
     */
    private function pulseBands(array $pulse, Carbon $now): array
    {
        $month = $pulse['month'];
        $reference = $pulse['reference'];
        $today = $pulse['today'];
        $usual = $pulse['usual'];

        $throughDay = $now->format('jS');

        return [
            [
                'key' => 'month',
                'title' => 'This month against last year',
                'hint' => null,
                // A store younger than a year has no reference at all, which
                // is a different fact from a reference of zero and must not
                // be set as one.
                'referenceMissing' => ! $reference['exists'],
                'columns' => [
                    ['key' => 'now', 'label' => $month['label'] . ' to the ' . $throughDay, 'lead' => true],
                    ['key' => 'ref', 'label' => $reference['label'] . ' to the ' . $throughDay, 'lead' => false],
                ],
                'rows' => [
                    [
                        'label' => 'Revenue taken',
                        'money' => true,
                        'hint' => 'Only orders at a status set to count as revenue.',
                        'values' => ['now' => $month['revenue'], 'ref' => $reference['revenue']],
                        'change' => Standing::change($month['revenue'], $reference['revenue']),
                    ],
                    [
                        'label' => 'Orders placed',
                        'money' => false,
                        'hint' => null,
                        'values' => ['now' => $month['placed'], 'ref' => $reference['placed']],
                        'change' => Standing::change((float) $month['placed'], (float) $reference['placed']),
                    ],
                    [
                        'label' => 'Average order',
                        'money' => true,
                        'hint' => 'Revenue taken divided by the orders it came from.',
                        'values' => ['now' => $month['average'], 'ref' => $reference['average']],
                        'change' => Standing::change($month['average'], $reference['average']),
                    ],
                ],
            ],
            [
                'key' => 'today',
                'title' => 'Today against a usual ' . $usual['weekday'],
                'hint' => 'The middle figure of the last ' . $usual['samples'] . ' ' . $usual['weekday'] . 's.',
                'referenceMissing' => false,
                'columns' => [
                    ['key' => 'now', 'label' => 'Today', 'lead' => true],
                    ['key' => 'ref', 'label' => $usual['label'], 'lead' => false],
                ],
                'rows' => [
                    [
                        'label' => 'Revenue taken',
                        'money' => true,
                        'hint' => null,
                        'values' => ['now' => $today['revenue'], 'ref' => $usual['revenue']],
                        'change' => Standing::change($today['revenue'], $usual['revenue']),
                    ],
                    [
                        'label' => 'Orders placed',
                        'money' => false,
                        'hint' => null,
                        'values' => ['now' => $today['placed'], 'ref' => $usual['placed']],
                        'change' => Standing::change((float) $today['placed'], (float) $usual['placed']),
                    ],
                    [
                        'label' => 'Average order',
                        'money' => true,
                        'hint' => null,
                        'values' => ['now' => $today['average'], 'ref' => $usual['average']],
                        'change' => Standing::change($today['average'], $usual['average']),
                    ],
                ],
            ],
        ];
    }

    /**
     * What a store still needs before it can take its first order.
     *
     * The same line grammar as every other movement, because a count with a
     * destination is the sheet's whole vocabulary and a brand new store
     * deserves the product's own voice rather than a welcome panel. Three
     * counts, two queries, and every one of them is a real reading of the
     * store rather than a checklist item ticked by having visited a page.
     */
    private function openingLines(int $storeId): array
    {
        $products = Product::where('store_id', $storeId)->count();

        // Both groups in one read, and filtered in PHP rather than with a
        // LIKE, because the underscore in "_enabled" is a LIKE wildcard and
        // escaping it differs between MySQL and SQLite.
        $flags = Setting::query()
            ->whereIn('group', ['payment', 'shipping'])
            ->get(['group', 'key', 'value']);

        $on = fn (string $group) => $flags
            ->filter(fn ($setting) => $setting->group === $group
                && str_ends_with((string) $setting->key, '_enabled')
                && (string) $setting->value === '1')
            ->count();

        return [
            [
                'label' => 'Products in the catalog',
                'count' => $products,
                'url'   => route('admin.products.index'),
            ],
            [
                'label' => 'Payment methods on',
                'count' => $on('payment'),
                'url'   => route('admin.payment-settings.index'),
            ],
            [
                'label' => 'Shipping methods on',
                'count' => $on('shipping'),
                'url'   => route('admin.shipping-settings.index'),
            ],
        ];
    }

    /**
     * @param  array<int, array{count:int}>  $lines
     */
    private function anyCount(array $lines): bool
    {
        foreach ($lines as $line) {
            if (($line['count'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
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
    private function standingSentence(array $pulse, string $currency, array $fulfillment): string
    {
        $placed = (int) $pulse['today']['placed'];
        $revenue = (float) $pulse['today']['revenue'];
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
