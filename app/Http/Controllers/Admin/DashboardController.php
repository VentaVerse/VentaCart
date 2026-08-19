<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\Cart;
use App\Models\Currency;
use App\Models\CustomerActivity;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Support\AdminNav;
use App\Support\Inventory;
use App\Support\LowStock;
use App\Support\RunningAccounts;
use App\Support\SalesPlot;
use App\Support\Standing;
use Cron\CronExpression;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
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
 *   5  latest orders   the day's orders as ledger lines, filled to seven
 *                      with the most recent when today runs short
 *   6  the rail        outstanding counts, automation health, and the
 *                      customer activity feed
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

    /**
     * A quiet morning still shows this many orders. Most mornings have no
     * trade yet, and a ledger that answers the merchant's first visit of the
     * day with a blank line was the owner's complaint: the old dashboard
     * always showed the last seven orders, whatever day they arrived on.
     */
    private const FILL_TO = 7;

    /** The activity feed is a glance, not a table. */
    private const ACTIVITY_ROWS = 8;

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
        $fulfillment = $this->fulfillmentWork($storeId);
        $lowStockCount = LowStock::count();

        $workLines = array_merge($fulfillment['lines'], [[
            'label' => 'Stock about to run out',
            'count' => $lowStockCount,
            'url'   => route('admin.products.index', ['stock' => 'low']),
        ]]);

        $automationHealth = $this->automationHealth();

        $cartsRecoverable = Cart::whereHas('items')
            ->where('updated_at', '<', $now->copy()->subHour())
            ->count();

        $standingLines = $this->standingLines($cartsRecoverable, $now);

        $shared = [
            'sheetDate'        => $today,
            'currency'         => $currency,
            'workLines'        => $workLines,
            'standingLines'    => $standingLines,
            'automationHealth' => $automationHealth,
        ];

        // One shape, always. A store with nothing to show reports zeros
        // rather than swapping to a different page: when a deploy left every
        // order's status snapshot NULL, the two-shape dashboard concluded the
        // store had never traded and an outage looked identical to a wiped
        // shop (owner ruling 2026-08-20).

        $pulse = Standing::for($storeId, $now);

        // The sales plot's period. The seven picker controls are real links
        // carrying ?range=, so the page answers every range without
        // JavaScript; an ordinary load carries no param and costs nothing
        // beyond the pulse it already computed, because the default series
        // IS the pulse's own plot.
        $range = (string) $request->query('range', '12m');
        if (! in_array($range, Standing::RANGES, true)) {
            $range = '12m';
        }

        $plot = $range === '12m'
            ? Standing::wrapTwelve($pulse['plot'])
            : Standing::series($storeId, $now, $range);

        $accountsFrom = $today->copy()->subDays(self::ACCOUNT_DAYS - 1);
        $accounts = RunningAccounts::for($storeId, $accountsFrom, $tomorrow);

        $inventory = Inventory::for($storeId);

        $entries = $this->entries($storeId, $today, $tomorrow);

        return view('admin.dashboard', array_merge($shared, [
            'standing'         => $this->standingSentence($pulse, $currency, $fulfillment),
            'pulse'            => $pulse,
            'bands'            => $this->pulseBands($pulse, $now),
            'usualLine'        => $this->usualLine($pulse, $currency),
            'plot'             => $plot,
            'salesRange'       => $range,
            'salesRanges'      => Standing::RANGE_LABELS,
            'stats'            => $this->statBlocks($pulse, $fulfillment, $lowStockCount, $inventory, $cartsRecoverable),
            // The two figures the band now leads with are not repeated as
            // work lines underneath it. What is left is what the band did
            // not take, and the list disappears when that is nothing.
            'workRemaining'    => array_slice($fulfillment['lines'], 1),
            'accounts'         => $accounts,
            'accountsLabel'    => 'Last ' . self::ACCOUNT_DAYS . ' days',
            'ring'             => $this->ring($accounts),
            'inventory'        => $inventory,
            'entries'          => $entries['rows'],
            'entriesTodayCount' => $entries['todayCount'],
            'entriesTruncated' => $entries['truncated'],
            'activities'       => $this->activities(),
        ]));
    }

    /**
     * One range of the sales plot, as the same payload the Blade partial
     * renders, for the picker's client-side swap. Behind the admin
     * middleware exactly like the page it serves; a range the picker does
     * not offer is not a series, it is a 404.
     */
    public function salesSeries(Request $request)
    {
        $range = (string) $request->query('range', '');

        abort_unless(in_array($range, Standing::RANGES, true), 404);

        $storeId = (int) config('app.store_id', 1);
        $currency = Currency::where('is_default', true)->value('symbol') ?: '';

        $series = Standing::series($storeId, Carbon::now(), $range);

        return response()->json(SalesPlot::payload($series, $currency));
    }

    /**
     * The five figures the page leads with, as filled blocks.
     *
     * WHY FIVE FILLED BLOCKS AT ALL. The sheet used to open with ruled
     * lines of the same weight as everything under them, and blurred, the
     * whole page read as one grey texture with no way in. These carry
     * saturated ground and a figure four times the size of the body, so a
     * merchant glancing at the tab knows the shape of the day before
     * reading a word. It is the layout the ERP arrived at for the same
     * problem, and it is the reason for this pass.
     *
     * COLOUR IS IDENTITY, NEVER STATE. The accent says WHICH measure you
     * are reading, so the same figure keeps the same hue whether it is
     * good news or bad, and the written label carries the meaning for
     * anyone not reading hue at all. Nothing here goes red because it is
     * low. State stays where it already lives: the change column, the
     * status badges, the work counts.
     *
     * EVERY BLOCK LINKS TO THE SCREEN THAT RESOLVES IT, which is what
     * separates a figure from a decoration. A block whose destination
     * cannot be named is not shown.
     *
     * THE FOURTH BLOCK IS NOT ALWAYS THE SAME MEASURE. Parcels to pack
     * belongs to the fulfillment module, and on a store running without it
     * there is no such figure and no queue to link to. Rather than print a
     * dash or drop to four blocks, the slot falls back to a core figure of
     * the same kind: work waiting, with a screen that clears it.
     *
     * @param  array{lines:array, counts:array, enabled:bool}  $fulfillment
     * @param  array{products:int, outOfStock:int, atSale:float}  $inventory
     */
    private function statBlocks(
        array $pulse,
        array $fulfillment,
        int $lowStockCount,
        array $inventory,
        int $cartsRecoverable
    ): array {
        $usual = 'a usual ' . $pulse['usual']['weekday'];

        $stats = [
            [
                'key' => 'revenue-today',
                'accent' => 'cyan',
                'icon' => 'banknote',
                'label' => 'Revenue today',
                'money' => true,
                'value' => $pulse['today']['revenue'],
                'note' => $this->changeSentence(
                    Standing::change($pulse['today']['revenue'], $pulse['usual']['revenue']),
                    $usual
                ),
                'url' => route('admin.orders.index'),
            ],
            [
                'key' => 'orders-today',
                'accent' => 'violet',
                'icon' => 'cart',
                'label' => 'Orders today',
                'money' => false,
                'value' => $pulse['today']['placed'],
                'note' => $this->changeSentence(
                    Standing::change((float) $pulse['today']['placed'], (float) $pulse['usual']['placed']),
                    $usual
                ),
                'url' => route('admin.orders.index'),
            ],
            [
                'key' => 'month-to-date',
                'accent' => 'green',
                'icon' => 'trending-up',
                'label' => $pulse['month']['label'] . ' so far',
                'money' => true,
                'value' => $pulse['month']['revenue'],
                // A store younger than a year has no reference at all,
                // which is a different fact from a reference of zero and
                // must not be dressed as one.
                'note' => $pulse['reference']['exists']
                    ? $this->changeSentence(
                        Standing::change($pulse['month']['revenue'], $pulse['reference']['revenue']),
                        $pulse['reference']['label']
                    )
                    : 'no record a year back',
                'url' => route('admin.orders.index'),
            ],
        ];

        if ($fulfillment['enabled']) {
            $toPack = (int) ($fulfillment['counts']['to_pack'] ?? 0);
            $handover = (int) ($fulfillment['counts']['to_handover'] ?? 0);

            $stats[] = [
                'key' => 'to-pack',
                'accent' => 'amber',
                'icon' => 'fulfillment',
                'label' => 'Parcels to pack',
                'money' => false,
                'value' => $toPack,
                'note' => $handover === 0
                    ? 'none awaiting handover'
                    : number_format($handover) . ' awaiting handover',
                'url' => $fulfillment['lines'][0]['url'],
            ];
        } elseif (Route::has('admin.abandoned-carts.index')) {
            $stats[] = [
                'key' => 'carts',
                'accent' => 'amber',
                'icon' => 'cart',
                'label' => 'Carts recoverable',
                'money' => false,
                'value' => $cartsRecoverable,
                'note' => 'idle over an hour',
                'url' => route('admin.abandoned-carts.index'),
            ];
        }

        $stats[] = [
            'key' => 'low-stock',
            'accent' => 'rose',
            'icon' => 'products',
            'label' => 'Stock about to run out',
            'money' => false,
            'value' => $lowStockCount,
            'note' => $inventory['outOfStock'] === 0
                ? 'nothing out of stock'
                : number_format($inventory['outOfStock']) . ' already out',
            'url' => route('admin.products.index', ['stock' => 'low']),
        ];

        return $stats;
    }

    /**
     * A change said as a phrase a person would use, against a named
     * reference. Standing::change() already refuses to invent a percentage
     * from nothing; this only decides the preposition, which differs by
     * direction and would read as machine output if it did not.
     *
     * @param  array{words:string, direction:string, percent:?float}  $change
     */
    private function changeSentence(array $change, string $reference): string
    {
        if ($change['words'] === 'from nothing') {
            return 'up from nothing on ' . $reference;
        }

        return match ($change['direction']) {
            'level' => 'level with ' . $reference,
            'ahead' => $change['words'] . ' of ' . $reference,
            default => $change['words'] . ' ' . $reference,
        };
    }

    /**
     * The part-to-whole reading, as a ring beside its own table.
     *
     * WHAT IT DIVIDES BY IS THE STORE'S OWN SHAPE. The ERP this mirrors
     * divides its orders by marketplace channel, which Venta has no
     * equivalent of: a Venta store is one shop. The honest equivalent is
     * how the trade divides itself, and there are two candidates. Customer
     * group wins when the store actually runs more than one carrying
     * trade, because that is a division the merchant chose and prices
     * against; otherwise it is the payment method, which every order
     * records and which genuinely differs in what it costs to operate.
     *
     * ORDERS, NOT REVENUE, and deliberately: the chips above this already
     * divide the money, so dividing it twice would say one thing in two
     * places. One large order can hold a small share of the ring and the
     * largest figure in the revenue column beside it, which is a fact
     * worth being able to see.
     *
     * NO EXTRA QUERY. Both candidates are already on the running accounts.
     *
     * @return array{by:string, scope:?string, total:int, segments:array}|null
     */
    private function ring(array $accounts): ?array
    {
        // RunningAccounts returns groups only when two or more carried
        // trade, so a store with one group falls through to the methods
        // without this having to know the rule.
        $useGroups = $accounts['groups'] !== [];
        $rows = $useGroups ? $accounts['groups'] : $accounts['payments'];

        if ($rows === []) {
            return null;
        }

        $total = array_sum(array_column($rows, 'orders'));

        if ($total <= 0) {
            return null;
        }

        $segments = [];
        foreach ($rows as $row) {
            $segments[] = [
                'label' => $row['label'],
                'orders' => (int) $row['orders'],
                'revenue' => (float) $row['revenue'],
                'share' => (int) $row['orders'] / $total,
            ];
        }

        return [
            'by' => $useGroups ? 'customer group' : 'payment method',
            // Group revenue comes only from orders placed against an
            // account, so the ring covers less than the period's trade and
            // says so rather than implying it is the whole.
            'scope' => $useGroups ? 'customer accounts only' : null,
            'total' => $total,
            'segments' => $segments,
        ];
    }

    /**
     * The pulse as one ruled band, in the ledger grammar the sheet already
     * speaks: a name on the left, the figure, its reference, and the change
     * between them. The month is measured against the same month a year
     * ago, and both columns are labelled with the exact span they cover,
     * because "this month" against "last year" is only honest if both sides
     * stop on the same day and a reader has to be able to see that they do.
     *
     * There used to be a second band setting today against a usual weekday,
     * and it repeated the same three row labels directly under the first:
     * two identical tables stacked, one of them mostly zeros for most of
     * every morning. Today's comparison lives on the band blocks' own notes
     * and in usualLine(); the twin table is gone.
     */
    private function pulseBands(array $pulse, Carbon $now): array
    {
        $month = $pulse['month'];
        $reference = $pulse['reference'];

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
     * The latest orders, newest first: today's, then enough earlier ones to
     * reach FILL_TO rows on a quiet morning.
     *
     * The sheet used to show only today's entries, which most mornings meant
     * "Nothing entered yet today" while the last real orders sat one screen
     * away. The owner ruled the old dashboard's card more informative: it
     * always showed the last seven orders. So today's rows lead (bounded by
     * MAX_ENTRIES + 1, so an exceptional day renders a sheet rather than a
     * thousand rows, and the extra row is what tells the view to offer
     * Orders) and earlier orders fill the ledger only when today is short.
     * Both reads are index seeks; the line items and statuses load once for
     * the merged set, never per order.
     *
     * @return array{rows:\Illuminate\Database\Eloquent\Collection, todayCount:int, truncated:bool}
     */
    private function entries(int $storeId, Carbon $today, Carbon $tomorrow): array
    {
        $days = Order::query()
            ->where('store_id', $storeId)
            ->whereNotNull('order_status_name')
            ->where('created_at', '>=', $today)
            ->where('created_at', '<', $tomorrow)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::MAX_ENTRIES + 1)
            ->get();

        $truncated = $days->count() > self::MAX_ENTRIES;
        $days = $days->take(self::MAX_ENTRIES);

        $fill = self::FILL_TO - $days->count();
        $earlier = $fill > 0
            ? Order::query()
                ->where('store_id', $storeId)
                ->whereNotNull('order_status_name')
                ->where('created_at', '<', $today)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit($fill)
                ->get()
            : Order::newModelInstance()->newCollection();

        $rows = $days->concat($earlier);
        $rows->load([
            'status:id,name',
            'products:id,order_id,name,variant_label,quantity',
        ]);

        return [
            'rows' => $rows,
            'todayCount' => $days->count(),
            'truncated' => $truncated,
        ];
    }

    /**
     * Every enabled automation, divided into the ones that need a look and
     * a count of the ones that do not. The unhealthy list is ordered worst
     * first: a failed run outranks a run that never happened, which outranks
     * a run that is merely late, and among the late ones the stalest leads.
     *
     * @return array{total:int, unhealthy:\Illuminate\Support\Collection, healthy:int}
     */
    private function automationHealth(): array
    {
        $automations = Automation::enabled()->orderBy('name')->get();

        $unhealthy = $automations
            ->filter(fn (Automation $automation) => $this->isAutomationUnhealthy($automation))
            ->sortBy(fn (Automation $automation) => match (true) {
                $automation->last_run_status === 'failed' => -2,
                $automation->last_run_at === null => -1,
                default => $automation->last_run_at->getTimestamp(),
            })
            ->values();

        return [
            'total' => $automations->count(),
            'unhealthy' => $unhealthy,
            'healthy' => $automations->count() - $unhealthy->count(),
        ];
    }

    /**
     * The Outstanding counts, each with one line of specificity where that
     * line is a single cheap read of a fact the merchant will act on. The
     * newest pending review names its product; the newest recoverable cart
     * says when it went idle. The SEO count stays bare: its newest row has
     * no one field that names it, and a detail that needs assembling is a
     * detail this page does not buy. Nothing here is asked unless the count
     * it decorates is above zero.
     */
    private function standingLines(int $cartsRecoverable, Carbon $now): array
    {
        // Reviews live in their extension; without it there is no queue
        // to report.
        $reviewsAlive = \Illuminate\Support\Facades\Route::has('admin.reviews.index')
            && class_exists(\Extensions\Reviews\Models\Review::class);

        $pendingReviews = $reviewsAlive ? \Extensions\Reviews\Models\Review::pending()->count() : 0;

        $reviewDetail = null;
        if ($reviewsAlive && $pendingReviews > 0) {
            $newest = \Extensions\Reviews\Models\Review::pending()->latest('id')->with('product:id,name')->first();
            $reviewDetail = $newest?->product ? 'Newest is for ' . $newest->product->name : null;
        }

        $cartDetail = null;
        if ($cartsRecoverable > 0) {
            $idleAt = Cart::whereHas('items')
                ->where('updated_at', '<', $now->copy()->subHour())
                ->max('updated_at');
            $cartDetail = $idleAt ? 'Newest went idle ' . Carbon::parse($idleAt)->diffForHumans() : null;
        }

        $lines = [];

        if ($reviewsAlive) {
            $lines[] = [
                'label'  => 'Reviews awaiting approval',
                'count'  => $pendingReviews,
                'detail' => $reviewDetail,
                'url'    => route('admin.reviews.index'),
            ];
        }

        if (Route::has('admin.extensions.seo.review')) {
            $lines[] = [
                'label'  => 'SEO suggestions pending',
                'count'  => \Extensions\Seo\Models\SeoSuggestion::pending()->count(),
                'detail' => null,
                'url'    => route('admin.extensions.seo.review'),
            ];
        }

        if (Route::has('admin.abandoned-carts.index')) {
            $lines[] = [
                'label'  => 'Carts recoverable',
                'count'  => $cartsRecoverable,
                'detail' => $cartDetail,
                'url'    => route('admin.abandoned-carts.index'),
            ];
        }

        return $lines;
    }

    /**
     * The last few things customers did, newest first. A feed, not a table:
     * it answers "is anyone out there" in a glance, and the customer's own
     * page carries their full history.
     */
    private function activities()
    {
        return CustomerActivity::query()
            ->with('customer:id,first_name,last_name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::ACTIVITY_ROWS)
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
     * Today against a usual weekday, as one quiet sentence rather than a
     * second table. The full twin table repeated the month table's three
     * row labels directly beneath them, and two of its figures were already
     * the band's own notes; what it alone carried was the average order, so
     * that is what the sentence keeps. On a morning with no trade yet it
     * states the reference instead, which is the figure a merchant opening
     * a blank day actually wants.
     */
    private function usualLine(array $pulse, string $currency): string
    {
        $today = $pulse['today'];
        $usual = $pulse['usual'];
        $money = fn (float $v) => $currency . number_format($v, 2);

        if ((int) $today['placed'] === 0) {
            $orders = (int) $usual['placed'] === 1 ? '1 order' : number_format($usual['placed']) . ' orders';

            return 'A usual ' . $usual['weekday'] . ' takes ' . $money((float) $usual['revenue']) . ' over ' . $orders . '.';
        }

        return 'Average order today ' . $money((float) $today['average'])
            . ', against ' . $money((float) $usual['average']) . ' on a usual ' . $usual['weekday'] . '.';
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
