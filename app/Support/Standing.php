<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Where the store stands, which is the first thing the day sheet says.
 *
 * "Is the business alright?" is a comparative question, so nothing here is
 * a bare figure: every measure arrives beside the reference it should be
 * read against, and the references are chosen so that a seasonal trade
 * cannot make a quiet January look like a collapse.
 *
 * WHY YEAR ON YEAR AND NOT MONTH ON MONTH. A music shop sells differently
 * in December than in February, so this month against last month says more
 * about the calendar than about the business. This month against the same
 * month a year ago holds the season still and moves only the year, which is
 * the comparison a seller actually reasons with. It is available because a
 * migrated store arrives carrying years of orders; a store with no history
 * simply has nothing in the reference column, and the sheet says so.
 *
 * LIKE FOR LIKE, ALWAYS. The month figure is the month SO FAR, so its
 * reference is last year's same month up to the same day and hour, never
 * the whole of it. A ninth-of-August compared against a whole August would
 * report a two thirds collapse every single year. The same rule governs the
 * plot: its final point on the earlier trace is truncated to the same span
 * as the final point on the current one, so the two lines end on figures
 * that mean the same thing.
 *
 * WHY A MEDIAN FOR THE WEEKDAY. Today's reference is the middle figure of
 * the last eight of the same weekday. A mean would let one exceptional
 * Saturday redefine what a usual Saturday is; the middle figure of eight
 * cannot be moved by either extreme.
 *
 * THREE QUERIES, none of which scans the orders table. The monthly series
 * is one aggregate over a two-year window; the same-span reference is one
 * aggregate over at most 31 days; the daily series is one aggregate over
 * eight weeks. All three seek on orders_store_created_money_index and read
 * only the index, which is what that index exists for.
 */
class Standing
{
    /** Months drawn on the plot, and therefore months compared. */
    public const PLOT_MONTHS = 12;

    /**
     * The seven periods the sales plot can be asked for, in the order the
     * picker offers them. '12m' is the default and is what the sheet
     * computes on an ordinary page load; every other range is asked for on
     * demand, either by the ?range= link or by the series endpoint.
     */
    public const RANGES = ['30d', '90d', 'this-month', 'last-month', 'this-year', '12m', 'all'];

    /** What the picker prints on each segment. */
    public const RANGE_LABELS = [
        '30d' => '30d',
        '90d' => '90d',
        'this-month' => 'This Month',
        'last-month' => 'Last Month',
        'this-year' => 'This Year',
        '12m' => '12m',
        'all' => 'All Time',
    ];

    /** How many of the same weekday make up "usual". */
    public const WEEKDAY_SAMPLES = 8;

    /** How long the two-year monthly series is held. See monthlyTotals(). */
    public const MONTHS_TTL = 300;

    /**
     * @return array{
     *     month: array{label:string, revenue:float, placed:int},
     *     reference: array{label:string, revenue:float, placed:int, exists:bool},
     *     today: array{label:string, revenue:float, placed:int},
     *     usual: array{label:string, weekday:string, revenue:float, placed:int, samples:int},
     *     plot: array
     * }
     */
    public static function for(int $storeId, Carbon $now): array
    {
        $monthStart = $now->copy()->startOfMonth();

        // The two-year window the plot is drawn from: PLOT_MONTHS of trade
        // and the PLOT_MONTHS before them, so every point has a point
        // beneath it from a year earlier.
        $windowStart = $monthStart->copy()->subMonths(self::PLOT_MONTHS * 2 - 1);
        $months = self::monthlyTotals($storeId, $windowStart);

        // Last year's same month, cut at the same day and hour as now.
        $referenceFrom = $monthStart->copy()->subYear();
        $referenceTo = $now->copy()->subYear();
        $reference = self::totalsBetween($storeId, $referenceFrom, $referenceTo);

        $days = self::dailyTotals($storeId, $now->copy()->startOfDay()->subWeeks(self::WEEKDAY_SAMPLES));

        $todayKey = $now->format('Y-m-d');
        $today = $days[$todayKey] ?? ['revenue' => 0.0, 'placed' => 0, 'live' => 0];

        // The month is added up from the LIVE daily rows rather than taken
        // from the held series. The daily window is eight weeks and a month
        // is at most 31 days, so it is always in there, and this is what
        // stops a five minute old month figure from being smaller than the
        // live today figure sitting beside it.
        $month = self::monthToDate($monthStart, $now, $days);

        return [
            'month' => [
                'label' => $monthStart->format('F'),
                'revenue' => $month['revenue'],
                'placed' => $month['placed'],
                'average' => self::average($month),
            ],
            'reference' => [
                'label' => $referenceFrom->format('M Y'),
                'revenue' => $reference['revenue'],
                'placed' => $reference['placed'],
                'average' => self::average($reference),
                // A store younger than a year has no reference at all, which
                // is a different thing from a reference of zero.
                'exists' => $months !== [] && $referenceFrom->gte(self::firstMonthOf($months)),
            ],
            'today' => [
                'label' => $now->format('l'),
                'revenue' => $today['revenue'],
                'placed' => $today['placed'],
                'average' => self::average($today),
            ],
            'usual' => self::usualWeekday($now, $days),
            'plot' => self::plot($monthStart, $months, $reference, $month),
        ];
    }

    /**
     * The current month so far, added up from the live daily rows.
     *
     * @param  array<string, array{revenue:float, placed:int}>  $days
     * @return array{revenue:float, placed:int}
     */
    private static function monthToDate(Carbon $monthStart, Carbon $now, array $days): array
    {
        $revenue = 0.0;
        $placed = 0;
        $live = 0;

        for ($day = $monthStart->copy(); $day->lte($now); $day->addDay()) {
            $key = $day->format('Y-m-d');
            $revenue += $days[$key]['revenue'] ?? 0.0;
            $placed += $days[$key]['placed'] ?? 0;
            $live += $days[$key]['live'] ?? 0;
        }

        return ['revenue' => $revenue, 'placed' => $placed, 'live' => $live];
    }

    /**
     * The average order: revenue divided by the orders that revenue actually
     * came from, never by every order placed. A refund is a placed order and
     * is not money, so counting it in the divisor would report every refund
     * as a fall in basket size.
     *
     * @param  array{revenue:float, live:int}  $window
     */
    private static function average(array $window): float
    {
        return ($window['live'] ?? 0) > 0 ? $window['revenue'] / $window['live'] : 0.0;
    }

    /**
     * The change from a reference to a current figure, said the way a person
     * would say it rather than as a signed number.
     *
     * A percentage needs something to be a percentage OF, so a reference of
     * zero returns no percentage at all rather than infinity dressed up as
     * growth.
     *
     * @return array{words:string, direction:string, percent:?float}
     */
    public static function change(float $current, float $reference): array
    {
        if ($reference <= 0.0) {
            if ($current <= 0.0) {
                return ['words' => 'level', 'direction' => 'level', 'percent' => null];
            }

            return ['words' => 'from nothing', 'direction' => 'ahead', 'percent' => null];
        }

        $percent = ($current - $reference) / $reference * 100;

        // Under a tenth of a percent is a rounding artefact, not a movement.
        if (abs($percent) < 0.1) {
            return ['words' => 'level', 'direction' => 'level', 'percent' => 0.0];
        }

        $direction = $percent > 0 ? 'ahead' : 'behind';

        return [
            'words' => number_format(abs($percent), 1) . '% ' . $direction,
            'direction' => $direction,
            'percent' => round($percent, 1),
        ];
    }

    /**
     * Revenue and orders placed for every month in the window, keyed Y-m.
     *
     * Grouped on the first seven characters of the timestamp rather than on
     * YEAR() and MONTH(), which SQLite does not have: one expression that
     * both drivers evaluate identically, so the suite tests the same SQL the
     * store runs. Measured the same on MySQL as the pair of functions it
     * replaces (21.4 ms against 22.4 ms), and it stays covered by
     * orders_store_created_money_index either way.
     *
     * THE ONE FIGURE ON THIS PAGE THAT IS NOT ASKED FRESH. Two years of
     * orders is the only part of the sheet whose cost grows with the size of
     * the store: 23 ms on a store carrying 8,192 orders inside the window,
     * and proportionally more on a larger one, on a page that loads at every
     * login. It is held for five minutes, which is short enough that a
     * merchant editing an order sees the month move on their next glance and
     * long enough that a morning of page loads costs it once.
     *
     * TODAY IS NEVER CACHED. The daily series and the standing sentence are
     * always read live, so the figure a merchant is watching change is the
     * one that changes; only the twelve-month shape behind it is held.
     *
     * The key carries the day and the revenue-bearing status ids, so
     * midnight rolls the window and a merchant ticking "counts as revenue"
     * on the Order Statuses page cannot be shown the answer they just
     * changed away from.
     *
     * @return array<string, array{revenue:float, placed:int}>
     */
    private static function monthlyTotals(int $storeId, Carbon $from): array
    {
        $ids = Revenue::statusIds($storeId);

        $key = 'standing.months.' . $storeId . '.' . $from->format('Y-m-d')
            . '.' . Carbon::now()->format('Y-m-d') . '.' . md5(implode(',', $ids));

        return Cache::remember($key, self::MONTHS_TTL, fn () => self::readMonthlyTotals($storeId, $from));
    }

    /**
     * @return array<string, array{revenue:float, placed:int}>
     */
    private static function readMonthlyTotals(int $storeId, Carbon $from): array
    {
        $live = Revenue::condition($storeId);

        $rows = Order::query()
            ->where('store_id', $storeId)
            ->whereNotNull('orders.order_status_name')
            ->where('created_at', '>=', $from)
            ->selectRaw(
                'SUBSTR(created_at, 1, 7) AS ym, COUNT(*) AS placed, '
                . "SUM(CASE WHEN {$live} THEN 1 ELSE 0 END) AS live, "
                . "SUM(CASE WHEN {$live} THEN total ELSE 0 END) AS revenue"
            )
            ->groupByRaw('ym')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->ym] = [
                'revenue' => (float) $row->revenue,
                'placed' => (int) $row->placed,
                'live' => (int) $row->live,
            ];
        }

        ksort($out);

        return $out;
    }

    /**
     * Revenue and orders placed for every day in the window, keyed Y-m-d.
     *
     * @return array<string, array{revenue:float, placed:int}>
     */
    private static function dailyTotals(int $storeId, Carbon $from, ?Carbon $to = null): array
    {
        $live = Revenue::condition($storeId);

        $rows = Order::query()
            ->where('store_id', $storeId)
            ->whereNotNull('orders.order_status_name')
            ->where('created_at', '>=', $from)
            ->when($to, fn ($query) => $query->where('created_at', '<', $to))
            ->selectRaw(
                'SUBSTR(created_at, 1, 10) AS d, COUNT(*) AS placed, '
                . "SUM(CASE WHEN {$live} THEN 1 ELSE 0 END) AS live, "
                . "SUM(CASE WHEN {$live} THEN total ELSE 0 END) AS revenue"
            )
            ->groupByRaw('d')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->d] = [
                'revenue' => (float) $row->revenue,
                'placed' => (int) $row->placed,
                'live' => (int) $row->live,
            ];
        }

        return $out;
    }

    /**
     * One aggregate over one window, [$from, $to) half open.
     *
     * @return array{revenue:float, placed:int}
     */
    private static function totalsBetween(int $storeId, Carbon $from, Carbon $to): array
    {
        $live = Revenue::condition($storeId);

        $row = Order::query()
            ->where('store_id', $storeId)
            ->whereNotNull('orders.order_status_name')
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to)
            ->selectRaw(
                "COUNT(*) AS placed, SUM(CASE WHEN {$live} THEN 1 ELSE 0 END) AS live, "
                . "SUM(CASE WHEN {$live} THEN total ELSE 0 END) AS revenue"
            )
            ->first();

        return [
            'revenue' => (float) ($row->revenue ?? 0),
            'placed' => (int) ($row->placed ?? 0),
            'live' => (int) ($row->live ?? 0),
        ];
    }

    /**
     * What a usual weekday looks like: the middle figure of the last eight
     * of the same weekday, today excluded.
     *
     * A day on which nothing sold is a sample worth having, so the dates are
     * generated rather than taken from the rows that happen to exist. Reading
     * only the days present would make "a usual Sunday" mean "a usual Sunday
     * on which something sold", which is a different and much rosier claim.
     *
     * @param  array<string, array{revenue:float, placed:int}>  $days
     * @return array{label:string, weekday:string, revenue:float, placed:int, samples:int}
     */
    private static function usualWeekday(Carbon $now, array $days): array
    {
        $revenues = [];
        $placed = [];
        $averages = [];

        for ($week = 1; $week <= self::WEEKDAY_SAMPLES; $week++) {
            $key = $now->copy()->subWeeks($week)->format('Y-m-d');
            $day = $days[$key] ?? ['revenue' => 0.0, 'placed' => 0, 'live' => 0];
            $revenues[] = (float) $day['revenue'];
            $placed[] = (float) $day['placed'];
            // Each day's own average, then the middle one. Dividing the
            // median revenue by the median count would be a ratio of two
            // unrelated days.
            $averages[] = self::average($day);
        }

        return [
            'label' => 'A usual ' . $now->format('l'),
            'weekday' => $now->format('l'),
            'revenue' => self::median($revenues),
            'placed' => (int) round(self::median($placed)),
            'average' => self::median($averages),
            'samples' => self::WEEKDAY_SAMPLES,
        ];
    }

    /**
     * @param  array<int, float>  $values
     */
    private static function median(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }

        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        return $count % 2 === 1
            ? $values[$middle]
            : ($values[$middle - 1] + $values[$middle]) / 2;
    }

    /**
     * The two traces: this year's last PLOT_MONTHS of revenue, and the same
     * months a year earlier.
     *
     * ONE measure and therefore ONE vertical scale. Two scales on one frame
     * is the worst thing a chart can do, because where the lines cross is
     * then decided by the scaling rather than by the trade. Both traces here
     * are revenue in the same currency, so they share a scale honestly and a
     * crossing means what it looks like it means.
     *
     * The earlier trace's final point is the same-span figure, not the whole
     * month, so the two lines end on comparable ground.
     *
     * @param  array<string, array{revenue:float, placed:int}>  $months
     * @param  array{revenue:float, placed:int}  $reference
     * @param  array{revenue:float, placed:int}  $monthSoFar
     */
    private static function plot(Carbon $monthStart, array $months, array $reference, array $monthSoFar): array
    {
        $labels = [];
        $full = [];
        $earlierLabels = [];
        $current = [];
        $earlier = [];

        for ($i = self::PLOT_MONTHS - 1; $i >= 0; $i--) {
            $month = $monthStart->copy()->subMonths($i);
            $year = $month->copy()->subYear();

            $labels[] = $month->format('M');
            $full[] = $month->format('F Y');
            $earlierLabels[] = $year->format('F Y');
            $current[] = $months[$month->format('Y-m')]['revenue'] ?? 0.0;
            $earlier[] = $months[$year->format('Y-m')]['revenue'] ?? 0.0;
        }

        // The last pair is the only one where one side is incomplete, so it
        // is the only one that needs cutting to match. The current side is
        // the live month rather than the held one, for the same reason the
        // band beneath the plot uses the live figure.
        $current[self::PLOT_MONTHS - 1] = $monthSoFar['revenue'];
        $earlier[self::PLOT_MONTHS - 1] = $reference['revenue'];

        $peak = max(array_merge($current, $earlier));

        return [
            'labels' => $labels,
            'full' => $full,
            'earlierLabels' => $earlierLabels,
            'current' => $current,
            'earlier' => $earlier,
            'peak' => (float) $peak,
            'currentPeak' => (float) max($current),
            'earlierPeak' => (float) max($earlier),
            'hasTrade' => $peak > 0,
        ];
    }

    /**
     * The sales plot for one named range.
     *
     * GRANULARITY FOLLOWS THE SPAN. A month of trade read at monthly
     * resolution is one point, which is not a plot; ninety days at monthly
     * resolution is three. So the four short ranges (30d, 90d, This Month,
     * Last Month) are drawn day by day, and the three long ones (This Year,
     * 12m, All Time) month by month.
     *
     * THE OVERLAY IS ALWAYS THE SAME GROUND A YEAR EARLIER, never the
     * preceding window. A seasonal store's preceding 30 days answer a
     * different question ("what did the calendar just do") from the one the
     * dashed line exists to answer ("is this season ahead of last season").
     * All Time is the one range with no overlay, because a trace that IS
     * the whole history has no earlier self to be compared with.
     *
     * COST. Each range is one or two grouped aggregates over
     * orders_store_created_money_index, held for MONTHS_TTL exactly like
     * the twelve-month series; the only live reads outside the cache are
     * the sub-month cut aggregates, each bounded by 31 days.
     */
    public static function series(int $storeId, Carbon $now, string $range): array
    {
        $today = $now->copy()->startOfDay();
        $tomorrow = $today->copy()->addDay();
        $monthStart = $now->copy()->startOfMonth();
        $lastMonthStart = $monthStart->copy()->subMonth();

        return match ($range) {
            '30d' => self::dailySeries($storeId, $now, '30d', $today->copy()->subDays(29), $tomorrow, 'Last 30 days'),
            '90d' => self::dailySeries($storeId, $now, '90d', $today->copy()->subDays(89), $tomorrow, 'Last 90 days'),
            'this-month' => self::dailySeries($storeId, $now, 'this-month', $monthStart, $tomorrow, $monthStart->format('F Y')),
            'last-month' => self::dailySeries($storeId, $now, 'last-month', $lastMonthStart, $monthStart, $lastMonthStart->format('F Y')),
            'this-year' => self::yearSeries($storeId, $now),
            'all' => self::allTimeSeries($storeId, $now),
            default => self::twelveMonthSeries($storeId, $now),
        };
    }

    /**
     * The metadata that turns the twelve-month plot into a range series,
     * shared by the page's default path (which already has the plot on the
     * pulse) and by series() (which builds it fresh for the endpoint).
     */
    public static function wrapTwelve(array $plot): array
    {
        $last = count($plot['current']) - 1;

        return $plot + [
            'range' => '12m',
            'granularity' => 'month',
            'title' => 'Last 12 months',
            'hasEarlier' => true,
            'nowName' => 'This year',
            'thenName' => 'A year earlier',
            'now' => ['label' => $plot['full'][$last], 'sofar' => true, 'value' => (float) $plot['current'][$last]],
        ];
    }

    /**
     * The twelve-month series built without the rest of the pulse, for the
     * endpoint. Same cached monthly window, same same-span reference, and
     * the month so far read as one live aggregate rather than from the
     * eight-week daily series the full pulse happens to carry.
     */
    private static function twelveMonthSeries(int $storeId, Carbon $now): array
    {
        $monthStart = $now->copy()->startOfMonth();
        $months = self::monthlyTotals($storeId, $monthStart->copy()->subMonths(self::PLOT_MONTHS * 2 - 1));
        $reference = self::totalsBetween($storeId, $monthStart->copy()->subYear(), $now->copy()->subYear());
        $monthSoFar = self::totalsBetween($storeId, $monthStart, $now);

        return self::wrapTwelve(self::plot($monthStart, $months, $reference, $monthSoFar));
    }

    /**
     * A daily-resolution series over [$from, $to), with the same dates a
     * year earlier beneath it.
     *
     * LIKE FOR LIKE AT THE END, at daily grain too: when the window is
     * still open (it ends after now), the overlay's final point is last
     * year's same day cut at the same hour, for the same reason the
     * monthly plot cuts its final month. A closed window (Last Month)
     * compares two complete spans and cuts nothing.
     */
    private static function dailySeries(int $storeId, Carbon $now, string $range, Carbon $from, Carbon $to, string $title): array
    {
        $open = $to->gt($now);

        $data = self::rememberSeries($storeId, $range, function () use ($storeId, $now, $from, $to, $open) {
            return [
                'current' => self::dailyTotals($storeId, $from, $to),
                'earlier' => self::dailyTotals($storeId, $from->copy()->subYear(), $to->copy()->subYear()),
                'cut' => $open
                    ? self::totalsBetween($storeId, $now->copy()->startOfDay()->subYear(), $now->copy()->subYear())
                    : null,
            ];
        });

        $labels = [];
        $full = [];
        $earlierLabels = [];
        $current = [];
        $earlier = [];

        for ($day = $from->copy(); $day->lt($to); $day->addDay()) {
            $then = $day->copy()->subYear();
            $labels[] = $day->format('j M');
            $full[] = $day->format('j F Y');
            $earlierLabels[] = $then->format('j F Y');
            $current[] = $data['current'][$day->format('Y-m-d')]['revenue'] ?? 0.0;
            $earlier[] = $data['earlier'][$then->format('Y-m-d')]['revenue'] ?? 0.0;
        }

        if ($data['cut'] !== null && $earlier !== []) {
            $earlier[count($earlier) - 1] = $data['cut']['revenue'];
        }

        $sameMonth = in_array($range, ['this-month', 'last-month'], true);

        return self::assemble([
            'range' => $range,
            'granularity' => 'day',
            'title' => $title,
            'nowName' => $title,
            'thenName' => $sameMonth ? $from->copy()->subYear()->format('F Y') : 'Same days a year earlier',
            'labels' => $labels,
            'full' => $full,
            'earlierLabels' => $earlierLabels,
            'current' => $current,
            'earlier' => $earlier,
            'now' => $open
                ? ['label' => 'Today', 'sofar' => true, 'value' => (float) end($current)]
                : ['label' => $title, 'sofar' => false, 'value' => (float) array_sum($current)],
        ]);
    }

    /**
     * January to the current month, against the same months last year. Read
     * from the SAME cached two-year window as the twelve-month plot (the
     * window always reaches back past last January), so switching between
     * the two costs no new grouped query. The final pair is patched live
     * and cut to the same span, exactly as the twelve-month plot patches
     * its own.
     */
    private static function yearSeries(int $storeId, Carbon $now): array
    {
        $monthStart = $now->copy()->startOfMonth();
        $jan = $now->copy()->startOfYear();
        $months = self::monthlyTotals($storeId, $monthStart->copy()->subMonths(self::PLOT_MONTHS * 2 - 1));

        $labels = [];
        $full = [];
        $earlierLabels = [];
        $current = [];
        $earlier = [];

        for ($month = $jan->copy(); $month->lte($monthStart); $month->addMonth()) {
            $year = $month->copy()->subYear();
            $labels[] = $month->format('M');
            $full[] = $month->format('F Y');
            $earlierLabels[] = $year->format('F Y');
            $current[] = $months[$month->format('Y-m')]['revenue'] ?? 0.0;
            $earlier[] = $months[$year->format('Y-m')]['revenue'] ?? 0.0;
        }

        $last = count($current) - 1;
        $current[$last] = self::totalsBetween($storeId, $monthStart, $now)['revenue'];
        $earlier[$last] = self::totalsBetween($storeId, $monthStart->copy()->subYear(), $now->copy()->subYear())['revenue'];

        return self::assemble([
            'range' => 'this-year',
            'granularity' => 'month',
            'title' => $now->format('Y'),
            'nowName' => $now->format('Y'),
            'thenName' => $now->copy()->subYear()->format('Y'),
            'labels' => $labels,
            'full' => $full,
            'earlierLabels' => $earlierLabels,
            'current' => $current,
            'earlier' => $earlier,
            'now' => ['label' => $monthStart->format('F Y'), 'sofar' => true, 'value' => (float) $current[$last]],
        ]);
    }

    /**
     * Every month the store has ever traded, first to now, one trace and no
     * overlay: the whole history has no earlier self. One grouped aggregate
     * over the covering index, held for MONTHS_TTL like every other series;
     * only the current month's point is patched live.
     */
    private static function allTimeSeries(int $storeId, Carbon $now): array
    {
        $monthStart = $now->copy()->startOfMonth();

        $data = self::rememberSeries($storeId, 'all', function () use ($storeId) {
            $firstAt = Order::query()
                ->where('store_id', $storeId)
                ->whereNotNull('order_status_name')
                ->min('created_at');

            $from = $firstAt ? Carbon::parse($firstAt)->startOfMonth() : null;

            return [
                'from' => $from?->toDateString(),
                'months' => $from ? self::readMonthlyTotals($storeId, $from) : [],
            ];
        });

        $from = $data['from'] ? Carbon::parse($data['from']) : $monthStart->copy();

        $labels = [];
        $full = [];
        $current = [];

        for ($month = $from->copy(); $month->lte($monthStart); $month->addMonth()) {
            $labels[] = $month->format('M Y');
            $full[] = $month->format('F Y');
            $current[] = $data['months'][$month->format('Y-m')]['revenue'] ?? 0.0;
        }

        $last = count($current) - 1;
        $current[$last] = self::totalsBetween($storeId, $monthStart, $now)['revenue'];

        return self::assemble([
            'range' => 'all',
            'granularity' => 'month',
            'title' => 'All time',
            'nowName' => 'All time',
            'thenName' => null,
            'labels' => $labels,
            'full' => $full,
            'earlierLabels' => [],
            'current' => $current,
            'earlier' => null,
            'now' => ['label' => $monthStart->format('F Y'), 'sofar' => true, 'value' => (float) $current[$last]],
        ]);
    }

    /**
     * The peaks every series carries, derived once so no builder can forget
     * one. A series with no overlay has no earlier peak rather than a peak
     * of zero, which is a different fact.
     */
    private static function assemble(array $series): array
    {
        $hasEarlier = $series['earlier'] !== null;
        $currentPeak = $series['current'] === [] ? 0.0 : (float) max($series['current']);
        $earlierPeak = $hasEarlier && $series['earlier'] !== [] ? (float) max($series['earlier']) : null;

        return $series + [
            'hasEarlier' => $hasEarlier,
            'currentPeak' => $currentPeak,
            'earlierPeak' => $earlierPeak,
            'peak' => max($currentPeak, $earlierPeak ?? 0.0),
            'hasTrade' => max($currentPeak, $earlierPeak ?? 0.0) > 0,
        ];
    }

    /**
     * The same five-minute hold the twelve-month window gets, one key per
     * range: dated so midnight rolls the window, salted with the
     * revenue-bearing status ids so an edit on the Order Statuses page is
     * never answered with the rule it just changed away from.
     */
    private static function rememberSeries(int $storeId, string $range, \Closure $read): array
    {
        $key = 'standing.series.' . $storeId . '.' . $range . '.'
            . Carbon::now()->format('Y-m-d') . '.' . md5(implode(',', Revenue::statusIds($storeId)));

        return Cache::remember($key, self::MONTHS_TTL, $read);
    }

    /**
     * The first month the store has any record of inside the window, used to
     * tell "no trade a year ago" apart from "no records that far back".
     *
     * @param  array<string, array{revenue:float, placed:int}>  $months
     */
    private static function firstMonthOf(array $months): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', array_key_first($months) . '-01')->startOfMonth();
    }
}
