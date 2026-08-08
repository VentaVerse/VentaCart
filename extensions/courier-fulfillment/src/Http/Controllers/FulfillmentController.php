<?php

namespace Extensions\CourierFulfillment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\Setting;
use App\Support\SettingsNav;
use Extensions\CourierFulfillment\CourierManager;
use Extensions\CourierFulfillment\FulfillmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * The dedicated Fulfillment page. Self-contained in the extension — the generic
 * Sales > Order page is never touched. Lists orders, books them with a courier,
 * prints labels, cancels, refreshes tracking.
 */
class FulfillmentController extends Controller
{
    /**
     * fulfillment-tabs-v3 Task 3: the current URL vocabulary — every value
     * this page's own segment-bar links can ever set. 'all' stays a valid
     * (unlisted, no tab of its own) deep-link escape hatch, same as before
     * this task.
     */
    private const VALID_FILTERS = ['unpaid', 'to_ship', 'in_transit', 'delivered', 'failed', 'cancelled', 'all'];
    private const VALID_SUBS = ['to_pack', 'to_handover'];

    /**
     * Pre-Task-3 filter values a saved link/bookmark can still carry:
     * 'to_book' predates Task 2's to_pack rename, 'to_handover' and
     * 'booking' were their own top-level tabs before this task folded both
     * into To Ship's sub-tabs. 301 (house convention for a moved resource —
     * see ShippingSettingController::index()'s TAB_REDIRECTS) so an old
     * link converges on the new URL instead of staying broken forever.
     */
    private const LEGACY_FILTER_REDIRECTS = [
        'to_book'     => ['filter' => 'to_ship', 'sub' => 'to_pack'],
        'to_handover' => ['filter' => 'to_ship', 'sub' => 'to_handover'],
        'booking'     => ['filter' => 'to_ship', 'sub' => 'to_handover'],
    ];

    public function index(Request $request)
    {
        // fulfillment-tabs-v3 Task 3: legacy alias, resolved BEFORE anything
        // else (no settings lookup, no query) — a bookmarked old link must
        // redirect even while the queue is paused. Every OTHER query param
        // (search, per_page, page) rides along unchanged via except(); only
        // 'filter' is replaced and 'sub' is added/overwritten.
        $rawFilter = $request->query('filter');
        if (is_string($rawFilter) && isset(self::LEGACY_FILTER_REDIRECTS[$rawFilter])) {
            return redirect()->route(
                'admin.fulfillment.index',
                array_merge($request->except(['filter', 'sub']), self::LEGACY_FILTER_REDIRECTS[$rawFilter]),
                301
            );
        }

        // "Pause" the queue without touching the extension's install/enable
        // state or any individual courier's own enabled flag — see
        // CourierSettingsController's report for the full history of this
        // setting. Opt-out semantics: installing + enabling the extension IS
        // the admin's intent signal, so missing/NULL means enabled and only
        // an explicit '0' pauses it (never a redirect-with-flash trap — the
        // page explains its own state instead).
        if (Setting::where('group', 'courier')->where('key', 'fulfillment_enabled')->value('value') === '0') {
            return view('courier-fulfillment::index', [
                'paused' => true,
                'canConfigureCouriers' => $this->canConfigureCouriers(),
            ]);
        }

        $couriers = CourierManager::enabled();
        $primary  = $couriers[0] ?? null;

        // fulfillment-tabs-v3 Task 3: 'filter' is one of the six top-level
        // segment-bar tabs (+ 'all' as a deep-link escape hatch); an
        // unrecognized value (never emitted by this page's own links, and
        // not one of the legacy aliases handled above) falls back to the
        // default tab rather than rendering an empty/broken page.
        $filter = in_array($rawFilter, self::VALID_FILTERS, true) ? $rawFilter : 'to_ship';

        // 'sub' only has meaning under 'to_ship' (spec: "sub ignored
        // elsewhere") — To Pack is the default sub-tab (where fulfillment
        // starts).
        $sub = null;
        if ($filter === 'to_ship') {
            $rawSub = $request->query('sub');
            $sub = in_array($rawSub, self::VALID_SUBS, true) ? $rawSub : 'to_pack';
        }

        // The bucket the query-building logic below actually filters by:
        // identical to $filter for every tab except To Ship, which defers to
        // its ACTIVE sub-tab's own courier-phase bucket name (to_pack /
        // to_handover — the same bucket names phaseTally() below already
        // produces; unchanged by this task).
        $bucket = $filter === 'to_ship' ? $sub : $filter;

        $perPage = (int) $request->query('per_page', 25);
        if (!in_array($perPage, [10, 25, 50], true)) {
            $perPage = 25;
        }

        // One base scope shared by the tab tallies and the listing, so the
        // segment-bar counts always describe exactly the set the operator is
        // paging through (search included).
        $base = Order::query()
            ->whereNotNull('order_status_id'); // Ghost/abandoned orders (no status) are never fulfilled — same default as the core Orders page.

        if ($s = $request->query('q')) {
            // Task 2 fix (code review, Critical): 'orders.' qualification is
            // load-bearing, not cosmetic — phaseTally() below clones $base
            // and joins order_statuses (fulfillment-tabs-v3 Task 2's stage
            // join), which ALSO has an 'id' column. An unqualified 'id' here
            // is unambiguous on $query's own listing (no join there) but
            // 500s with SQLSTATE 1052 "Column 'id' in where clause is
            // ambiguous" the moment the SAME closure runs inside phaseTally()'s
            // joined combos query — i.e. every numeric search, on every page
            // load, since phaseTally() always runs. Every other column this
            // closure touches (courier_tracking_number, shipping_first_name,
            // shipping_last_name) is orders-only and order_status_id below is
            // also orders-only (order_statuses has no such column) — 'id' is
            // the sole collision.
            $base->where(function ($x) use ($s) {
                if (is_numeric($s)) {
                    $x->orWhere('orders.id', $s);
                }
                $x->orWhere('courier_tracking_number', 'like', "%{$s}%")
                    ->orWhereRaw("CONCAT(shipping_first_name,' ',shipping_last_name) like ?", ["%{$s}%"]);
            });
        }

        // Phase tallies for the segment bar (and, via the same method, for
        // returns() below — one derivation, never two).
        [$phaseCounts, $phaseStatusGroups, $handoverByCourier, $unpaidClaimedGroups] = $this->phaseTally($base);

        // fulfillment-tabs-v3 Task 3: To Ship's own top-level count is the
        // sum of its two sub-tabs — 'booking' (async create in flight)
        // never has members of its own here (tabBucketForParts() already
        // folds it into 'to_handover' for tab membership), so summing just
        // these two never double-counts a row.
        $phaseCounts['to_ship'] = ($phaseCounts['to_pack'] ?? 0) + ($phaseCounts['to_handover'] ?? 0);

        $query = (clone $base)
            ->with('status:id,name,fulfillment_stage')
            ->with(['products' => function ($q) {
                // Item rows for the two-tier table: snapshot name/sku/image on
                // the line itself, live product image as the fallback.
                $q->select('id', 'order_id', 'product_id', 'name', 'sku', 'image', 'variant_label', 'quantity')
                    ->with('product:id,image');
            }])
            ->withCount('products')
            ->latest();

        // fulfillment-tabs-v3 Task 2 (amended by the Task 4 owner ruling):
        // STAGE-FIRST membership with ONE carve-out. Stage 'cancelled'
        // claims the order absolutely; stage 'unpaid' claims it only while
        // it has no live booking — an actively-booked order follows its
        // courier phase whatever its payment status
        // (FulfillmentService::tabBucketForParts()'s docblock has the full
        // rule). Courier-phase filters below therefore scope to stages
        // 'ready' AND 'unpaid' (an unpaid order can only reach them via a
        // booked combo the tally found), while the cancelled exception arm
        // stays 'ready'-only.
        //
        // fulfillment-tabs-v3 Task 3: branches on $bucket, not $filter — for
        // every tab except To Ship they're the same string, but To Ship
        // itself has no query shape of its own; its ACTIVE SUB-tab
        // ($bucket = 'to_pack' or 'to_handover') is what actually narrows
        // the listing, exactly as it did as a top-level tab before this task.
        if ($bucket === 'unpaid') {
            // Owner ruling: unpaid membership = stage 'unpaid' AND no live
            // booking — i.e. genuinely unbooked (no tracking number, not
            // async-creating), OR booked but with a booking whose phase is
            // 'cancelled' (the exact combos the tally already found for
            // unpaid-stage rows, so listing and count always agree).
            $query->whereHas('status', fn ($q) => $q->where('fulfillment_stage', 'unpaid'))
                ->where(function ($q) use ($unpaidClaimedGroups) {
                    $q->where(function ($unbooked) {
                        $unbooked->whereNull('courier_tracking_number')
                            ->where(function ($s) {
                                $s->whereNull('courier_status')
                                    ->orWhereNotIn('courier_status', FulfillmentService::ASYNC_CREATE_STATUSES);
                            });
                    });
                    if ($unpaidClaimedGroups !== []) {
                        $q->orWhere(function ($b) use ($unpaidClaimedGroups) {
                            $this->applyPhaseGroups($b, $unpaidClaimedGroups);
                        });
                    }
                });
        } elseif ($bucket === 'to_pack') {
            // Task T4 fix (+ final-review Finding 2): excludes BOTH async
            // bridging statuses ('creating' AND 'creation_failed' — also
            // tracking-number-null) — those belong to the To Handover/Failed
            // buckets respectively, never To Pack (see phaseTally()'s
            // matching fix above and FulfillmentService::ASYNC_CREATE_STATUSES).
            $query->whereHas('status', fn ($q) => $q->where('fulfillment_stage', 'ready'))
                ->whereNull('courier_tracking_number')
                ->where(function ($q) {
                    $q->whereNull('courier_status')
                        ->orWhereNotIn('courier_status', FulfillmentService::ASYNC_CREATE_STATUSES);
                });
        } elseif ($bucket === 'cancelled') {
            // Membership per the tab-model spec: stage 'cancelled' (a real
            // order cancel) claims the order regardless of courier state,
            // OR a stage-'ready' order whose courier phase itself bucketed
            // to 'cancelled' (a courier-side cancel/exception that hasn't
            // cancelled the core order). Both halves reuse the exact combos
            // phaseTally() already found for this bucket, so the listing
            // never disagrees with its own count.
            $groups = $phaseStatusGroups['cancelled'] ?? [];
            $query->where(function ($q) use ($groups) {
                $q->whereHas('status', fn ($s) => $s->where('fulfillment_stage', 'cancelled'))
                    ->orWhere(function ($ready) use ($groups) {
                        $ready->whereHas('status', fn ($s) => $s->where('fulfillment_stage', 'ready'))
                            ->where(function ($q2) {
                                $q2->whereNotNull('courier_tracking_number')
                                    ->orWhereIn('courier_status', FulfillmentService::ASYNC_CREATE_STATUSES);
                            });
                        $this->applyPhaseGroups($ready, $groups);
                    });
            });
        } elseif ($bucket !== 'all') {
            // A spine-phase tab (to_handover, in_transit, delivered, failed):
            // stage 'ready' orders whose (provider, raw status) combo
            // buckets into this phase — the exact combos found by the tally
            // above, so the list always matches its tab's count. ('all'
            // stays supported as a filter value for deep links even though
            // it no longer renders as a tab.)
            $groups = $phaseStatusGroups[$bucket] ?? [];
            // Task T4 fix (+ final-review Finding 2): a 'creating' or
            // 'creation_failed' order has no tracking number yet but must
            // still be reachable from its tab (To Handover / Failed) —
            // applyPhaseGroups() below narrows to the exact (provider,
            // status) combos the tally found for $bucket, so relaxing this
            // to admit those rows never leaks one into a phase it doesn't
            // belong to.
            //
            // Owner ruling (Task 4 fix round): stage 'unpaid' is admitted
            // here alongside 'ready' — an actively-booked unpaid order
            // follows its courier phase. It can only match via a BOOKED
            // combo the tally bucketed into this phase, so plain unpaid
            // orders never leak in. Stage 'cancelled' stays excluded
            // (absolute precedence).
            $query->whereHas('status', fn ($q) => $q->whereIn('fulfillment_stage', ['ready', 'unpaid']))
                ->where(function ($q) {
                    $q->whereNotNull('courier_tracking_number')
                        ->orWhereIn('courier_status', FulfillmentService::ASYNC_CREATE_STATUSES);
                });
            $this->applyPhaseGroups($query, $groups);
        }
        // 'all' — no extra constraint.

        $orders = $query->paginate($perPage)->withQueryString();

        // Build provider option sets for the booking modal (courier-agnostic).
        // 'mode' feeds step 1's mode chip (shown only when not production);
        // 'fee_estimate' is the per-courier estimate slot — always null until a
        // courier that can quote (SPX, Task T4) populates it at modal-open time.
        // Task T4 decision (deviates from the brief's stated PREFERENCE for
        // server-side rendering — documented here, not silently): pickup
        // slots are courier-level, not order-level, for every courier so
        // far (SPX's own getPickupSlots() — api.md §1.1 get_pickup_time —
        // ignores the Order argument entirely), so calling it once per
        // ENABLED courier here would be cheap in volume. But SpxClient::post()
        // has a 40s HTTP timeout, and this loop runs on EVERY queue page
        // load — if SPX is unreachable, every admin visiting the queue
        // (whether or not they ever open the booking modal) would stall on
        // that timeout. That's a strictly worse failure mode than the
        // "hammer SPX on page load" volume concern the brief raises for the
        // fee estimate, so pickup slots get the SAME lazy treatment: fetched
        // via AJAX only when SPX is actually chosen in step 2 (see the new
        // pickup-slots route + chooseCourier()'s JS below), never during
        // this page's own render.
        // Owner directive (2026-08-08, "unconfigured couriers visible with
        // configuration gate"): step 1 lists every FLAGGED courier
        // (CourierManager::flagged() — flag on, regardless of credential
        // completeness), not just $couriers (enabled() — flag on AND fully
        // configured). A flag-on-but-unconfigured courier still gets a
        // card, just a disabled one (see index.blade.php's step-1 markup) —
        // the bug this fixes was $couriers alone making it vanish with no
        // explanation. 'configured'/'configIssues' are what the view uses
        // to tell the two card states apart; every other key keeps its
        // pre-existing shape for a courier that IS fully configured, so
        // that card renders byte-identically to before.
        $courierOptions = [];
        foreach (CourierManager::flagged() as $c) {
            $configured = $c->isEnabled();
            $courierOptions[$c->getKey()] = [
                'name'         => $c->getName(),
                'configured'   => $configured,
                // Only meaningful (and only computed) when NOT configured —
                // a fully configured courier has nothing to report here.
                'configIssues' => $configured ? [] : $c->configurationIssues(),
                'settingsUrl'  => route('admin.fulfillment.settings.courier', ['courier' => $c->getKey()]),
                'services'     => $c->getServiceTypes(),
                'parcels'      => $c->getParcelOptions(),
                'payments'     => $c->getShippingPaymentOptions(),
                'mode'         => Setting::where('group', 'courier')->where('key', $c->getKey() . '_mode')->value('value'),
                'fee_estimate' => null,
            ];
        }

        return view('courier-fulfillment::index', [
            'orders' => $orders,
            'couriers' => $couriers,
            'primary' => $primary,
            'courierOptions' => $courierOptions,
            'filter' => $filter,
            'sub' => $sub,
            'perPage' => $perPage,
            'phaseCounts' => $phaseCounts,
            'handoverByCourier' => $handoverByCourier,
            'paused' => false,
            'canConfigureCouriers' => $this->canConfigureCouriers(),
        ]);
    }

    /**
     * Command-bar "Poll courier statuses" — runs the same courier:poll-status
     * command the scheduled automation runs, on demand. Registered into the
     * console kernel explicitly first: the extension's ServiceProvider only
     * registers its command when runningInConsole(), so an HTTP-triggered
     * Artisan::call() would otherwise hit "command not found".
     */
    public function poll()
    {
        app(\Illuminate\Contracts\Console\Kernel::class)
            ->registerCommand(new \Extensions\CourierFulfillment\Console\Commands\PollCourierStatus());

        @set_time_limit(300); // same cap the Automations "Run now" applies

        try {
            \Illuminate\Support\Facades\Artisan::call('courier:poll-status');
            $lines = array_values(array_filter(array_map('trim', explode("\n", \Illuminate\Support\Facades\Artisan::output()))));
            $summary = $lines !== [] ? end($lines) : 'Poll finished.';

            return back()->with('success', $summary);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Fulfillment poll action failed', ['error' => $e->getMessage()]);

            return back()->with('error', 'Poll failed: ' . $e->getMessage());
        }
    }

    /**
     * Task W4: returns monitoring — a sibling PAGE, never an eighth queue
     * tab (see the segment bar's own comment in the view). Lists orders
     * whose spine phase is 'returning' (a parcel on its way back) or
     * 'failed' (broke somewhere in its journey) — exactly the two phases
     * the ERP precedent treats as "coming back", grouped together because
     * an operator watching this page cares about both the same way.
     *
     * Phase membership comes from the SAME (provider, status) tally
     * index() uses for its tab counts (phaseTally() below) — never a
     * second, parallel derivation that could disagree with the queue.
     *
     * fulfillment-tabs-v3 Task 2 (amended by the Task 4 owner ruling):
     * STAGE-FIRST, same as index()'s tabs — a stage-'cancelled' order does
     * not belong here even if its courier phase happens to be
     * 'returning'/'failed' (a real order cancel outranks a courier-side
     * exception), while a stage-'unpaid' order with an ACTIVE booking
     * follows its courier phase and does (see
     * FulfillmentService::tabBucketForParts()'s docblock). phaseTally()
     * never populates $phaseStatusGroups from stage-'cancelled' combos, so
     * the explicit stage filter below is defense in depth, not the only
     * guard.
     *
     * This is a MONITORING surface only. The full customer-initiated RMA
     * process (refunds, restock guidance) is a distinct product feature,
     * tracked as a roadmap card rather than built here.
     */
    public function returns(Request $request)
    {
        $base = Order::query()->whereNotNull('order_status_id');

        [, $phaseStatusGroups] = $this->phaseTally($base);

        $groups = array_merge_recursive(
            $phaseStatusGroups['returning'] ?? [],
            $phaseStatusGroups['failed'] ?? []
        );

        // Owner ruling (Task 4 fix round): stage 'unpaid' admitted alongside
        // 'ready', same as the queue's spine-phase tabs — an actively-booked
        // order follows its courier phase (a returning/failed parcel is
        // coming back regardless of payment status). 'cancelled' stays
        // excluded (absolute precedence).
        $query = (clone $base)
            ->whereHas('status', fn ($q) => $q->whereIn('fulfillment_stage', ['ready', 'unpaid']))
            ->whereNotNull('courier_tracking_number');
        $this->applyPhaseGroups($query, $groups);

        $orders = $query->latest()->paginate(25)->withQueryString();

        return view('courier-fulfillment::returns', [
            'orders' => $orders,
        ]);
    }

    /**
     * Whether the current admin can see the "Go to Courier Settings" link on
     * the paused empty-state — same permission the Settings hub itself
     * checks for this page (App\Support\SettingsNav::groups()), so we never
     * point a lower-privileged admin at a link they can't use.
     */
    private function canConfigureCouriers(): bool
    {
        $item = SettingsNav::find('fulfillment');
        if (!$item) {
            return false;
        }

        return $item['permission'] === null || (Auth::user()?->hasPermission($item['permission']) ?? false);
    }

    /**
     * Phase is a per-courier PHP derivation (statusPhase()), which SQL can't
     * group by directly — but it's a pure function of (provider, raw status,
     * booked-or-not), so GROUP BY those three (PLUS the order's fulfillment
     * stage, via a join to order_statuses — fulfillment-tabs-v3 Task 2) and
     * bucket the handful of combos in PHP. Work stays proportional to the
     * couriers' status vocabularies times the 3 stages, never to the number
     * of orders. Shared by index()'s segment-bar tallies and returns()'s
     * listing — the one place either page turns raw (stage, provider,
     * status) rows into spine buckets.
     *
     * The join is a safe INNER join: $base always carries
     * whereNotNull('order_status_id'), and orders.order_status_id is a
     * nullOnDelete foreign key onto order_statuses.id — a non-null value is
     * therefore guaranteed to match exactly one row, never dropped or
     * duplicated by the join.
     *
     * Bucket membership is STAGE-FIRST with the owner-ruled unpaid carve-out
     * (FulfillmentService::tabBucketForParts()'s docblock has the full
     * rule): stage 'cancelled' claims the bucket outright; stage 'unpaid'
     * claims it only while the order has no live booking (phase 'to_pack'
     * or 'cancelled') — an actively-booked unpaid order buckets by its
     * courier phase, exactly like a per-row tabBucket() call would. The
     * grouped query already carries everything needed to express this:
     * (stage, provider, status, unbooked) fully determines both the phase
     * and the stage rule, so the tally and the per-row bucket can never
     * disagree.
     *
     * $phaseStatusGroups (the per-bucket provider/status index
     * applyPhaseGroups() replays for the listing query) is populated from
     * every BOOKED combo that bucketed into a courier phase — 'ready' and
     * (post-ruling) pipeline 'unpaid' combos alike — but never from
     * stage-'cancelled' rows (reachable only via their stage filter, so the
     * Cancelled tab's exception arm stays 'ready'-scoped) and never from an
     * unpaid-claimed combo. $unpaidClaimedGroups is the small complement:
     * the BOOKED unpaid-stage combos whose booking is no longer live (phase
     * 'cancelled') — the unpaid listing needs them to admit those rows
     * alongside the genuinely unbooked ones.
     *
     * @param \Illuminate\Database\Eloquent\Builder $base
     * @return array{0: array<string,int>, 1: array<string, array<string, array<int, ?string>>>, 2: array<string,int>, 3: array<string, array<int, ?string>>}
     *   [phaseCounts, phaseStatusGroups, handoverByCourier, unpaidClaimedGroups]
     */
    private function phaseTally($base): array
    {
        $combos = (clone $base)
            ->join('order_statuses', 'orders.order_status_id', '=', 'order_statuses.id')
            ->selectRaw('order_statuses.fulfillment_stage as fulfillment_stage, orders.courier_provider as courier_provider, orders.courier_status as courier_status, (orders.courier_tracking_number IS NULL) as unbooked, COUNT(*) as n')
            ->groupByRaw('order_statuses.fulfillment_stage, orders.courier_provider, orders.courier_status, (orders.courier_tracking_number IS NULL)')
            ->get();

        $phaseCounts = array_fill_keys(\Extensions\CourierFulfillment\Support\PhaseTone::PHASES, 0);
        $handoverByCourier = [];      // courier display name => waiting-parcel count (the ERP's co-couriers tally)
        $phaseStatusGroups = [];      // bucket => provider key => raw statuses present in the DB right now (booked combos that bucketed into a courier phase)
        $unpaidClaimedGroups = [];    // provider key => raw statuses: BOOKED unpaid-stage combos whose booking is no longer live (owner ruling — these stay under Unpaid)
        foreach ($combos as $row) {
            $stage = (string) $row->fulfillment_stage;

            // Task T4 fix (+ final-review Finding 2): a 'creating' or
            // 'creation_failed' order is 'unbooked' by this query's own
            // definition (courier_tracking_number IS NULL) but IS/WAS
            // mid-booking with an async courier (SPX) — same fix as
            // FulfillmentService::phaseFor(), applied here so the segment
            // bar's tally never disagrees with the per-row badge.
            $treatAsBooked = !$row->unbooked
                || in_array((string) $row->courier_status, FulfillmentService::ASYNC_CREATE_STATUSES, true);
            $phase = $treatAsBooked
                ? FulfillmentService::phaseForParts($row->courier_provider, $row->courier_status)
                : 'to_pack';

            // Stage-first bucketing with the unpaid carve-out (owner
            // ruling) — one shared rule (tabBucketForParts()), so the
            // grouped tally and a per-row tabBucket() always agree. A
            // stage-'ready'/'unpaid' 'booking' phase (async create in
            // flight) collapses into 'to_handover' for TAB membership only —
            // phaseForParts() above still reports the honest 'booking'
            // phase for any row-level badge that reads it separately.
            $bucket = FulfillmentService::tabBucketForParts($stage, $phase);
            $phaseCounts[$bucket] = ($phaseCounts[$bucket] ?? 0) + (int) $row->n;

            if ($treatAsBooked && $stage !== 'cancelled') {
                if ($bucket === 'unpaid') {
                    // Booked, unpaid-stage, phase 'cancelled': claimed by
                    // Unpaid — recorded separately so the unpaid listing can
                    // admit these rows next to the genuinely unbooked ones.
                    $unpaidClaimedGroups[(string) $row->courier_provider][] = $row->courier_status;
                } else {
                    $phaseStatusGroups[$bucket][(string) $row->courier_provider][] = $row->courier_status;
                    if ($bucket === 'to_handover') {
                        $name = CourierManager::get((string) $row->courier_provider)?->getName()
                            ?: (strtoupper((string) $row->courier_provider) ?: 'Unknown courier');
                        $handoverByCourier[$name] = ($handoverByCourier[$name] ?? 0) + (int) $row->n;
                    }
                }
            }
        }

        return [$phaseCounts, $phaseStatusGroups, $handoverByCourier, $unpaidClaimedGroups];
    }

    /**
     * Constrains a BOOKED-orders query to exactly the (provider, status)
     * combos in $groups (as produced by phaseTally() above) — the query-side
     * mirror of the phase bucketing, so a listing's rows always match the
     * tally that selected them. An empty $groups (phase currently has no
     * members) constrains to nothing rather than leaving the query open.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array<string, array<int, ?string>> $groups provider key => raw statuses
     */
    private function applyPhaseGroups($query, array $groups): void
    {
        if ($groups === []) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->where(function ($q) use ($groups) {
            foreach ($groups as $provider => $statuses) {
                $q->orWhere(function ($x) use ($provider, $statuses) {
                    $provider === ''
                        ? $x->whereNull('courier_provider')
                        : $x->where('courier_provider', $provider);
                    $withNull = in_array(null, $statuses, true);
                    $notNull  = array_values(array_filter($statuses, fn ($v) => $v !== null));
                    $x->where(function ($y) use ($withNull, $notNull) {
                        if ($notNull !== []) {
                            $y->whereIn('courier_status', $notNull);
                        }
                        if ($withNull) {
                            $y->orWhereNull('courier_status');
                        }
                    });
                });
            }
        });
    }

    public function book(Request $request, Order $order)
    {
        $data = $request->validate([
            'courier'          => 'required|string',
            'service'          => 'required|string',
            'parcel'           => 'required|string',
            'shipping_payment' => 'required|string',
            'insure'           => 'sometimes|boolean',
            // Task T4: SPX's step-2 escape hatches — the base QuadX field
            // set above is completely unchanged (still the only fields it
            // submits), these are purely additive/optional so an SPX
            // booking can pass its custom-dims parcel + chosen pickup slot
            // through to SpxFulfillment::createShipment()'s existing
            // $opts['length_cm'|'width_cm'|'height_cm'|'weight_kg'|
            // 'pickup_slot'] reads (see resolveParcelDims()/
            // fulfillmentInfo() — both already handled these keys since
            // T2, they just never had a way to arrive from this form).
            //
            // Review fix (Critical): the browser ALWAYS submits
            // pickup_slot (pre-selected "Drop off myself", value="") even
            // when SPX isn't the chosen courier at all (the field lives in
            // the shared form's DOM whenever it exists — see the
            // x-if-not-x-show fix's own docblock). Laravel's
            // ConvertEmptyStringsToNull middleware turns that "" into null
            // BEFORE validation runs, so `sometimes|string` (present but
            // not a string) rejected EVERY booking with a 422 — this was
            // never exercised by a test because every test posted the key
            // as absent, not as "". `nullable` is required alongside
            // `sometimes` so a present-but-null value passes.
            'length_cm'        => 'sometimes|nullable|numeric|min:0.01',
            'width_cm'         => 'sometimes|nullable|numeric|min:0.01',
            'height_cm'        => 'sometimes|nullable|numeric|min:0.01',
            'weight_kg'        => 'sometimes|nullable|numeric|min:0.01',
            'pickup_slot'      => 'sometimes|nullable|string',
        ]);

        // Review fix (Critical): drop any of the optional keys above that
        // validated as present-but-null (the blank "Drop off myself" /
        // empty custom-dims case) BEFORE they reach FulfillmentService::
        // book() -> createShipment()'s $opts — so every downstream read
        // sees either a real value or a genuinely ABSENT key, never an
        // explicit null muddying `$opts['x'] ?? $default` fallbacks.
        $data = array_filter($data, fn ($v) => $v !== null);

        if (FulfillmentService::isActivelyBooked($order)) {
            return back()->with('error', "Order #{$order->id} is already booked ({$order->courier_tracking_number}).");
        }

        // Shared booking core (same path the ERP API uses).
        $res = app(\Extensions\CourierFulfillment\FulfillmentService::class)->book($order, $data);

        if (!$res['success']) {
            return back()->with('error', "Booking failed: " . ($res['error'] ?? 'unknown error'));
        }

        // Final-review fix (Finding 2): an async courier (SPX) can return
        // success with NO tracking number yet (courier_status='creating' —
        // the bounded create-poll window elapsed). "Booked - tracking ."
        // read as a broken booking; say what actually happened instead.
        // Owner directive (2026-08-08, "AWB opens on a new tab after
        // booking"): this async path never carries a 'label_url' flash -
        // there is no AWB to open yet, so the queue view's auto-open banner
        // (index.blade.php) never renders here. Unchanged from before this
        // directive.
        if (empty($res['tracking_number'])) {
            $courierName = CourierManager::get((string) ($res['courier'] ?? ''))?->getName()
                ?: strtoupper((string) ($res['courier'] ?? '')) ?: 'The courier';

            return back()->with('success', "Booking submitted. {$courierName} is creating the shipment. The tracking number will appear here in a moment (refresh or poll statuses).");
        }

        // Owner directive (2026-08-08, "AWB opens on a new tab after
        // booking"): a synchronous success (tracking number already known)
        // also flashes the label route's own URL - never the courier's raw
        // printLabel() URL directly, so this stays the SAME authenticated,
        // cache-aware route the "Print waybill" button and drawer's "Print
        // AWB" link already use (see label() below). The queue view reads
        // this flash key to auto-open the AWB in a new tab, with a visible
        // "Open AWB" button as the fallback for when a popup blocker
        // swallows the automatic open.
        return back()
            ->with('success', "Booked, tracking {$res['tracking_number']}.")
            ->with('label_url', route('admin.fulfillment.label', $order));
    }

    /**
     * Task T4: the LAZY per-order fee estimate — a small "Get estimate"
     * button in the booking modal's step 2 (SPX section), never called on
     * page load. Reuses the exact same option set book() accepts (plus the
     * chosen courier) so estimateFee() sees exactly what createShipment()
     * would if the admin clicked Book right now. Courier-agnostic on
     * purpose (any courier whose estimateFee() returns non-null works
     * here, not just SPX) — CourierFulfillmentInterface::estimateFee()
     * already exists (W2's interface evolution #4); this is simply its
     * first real caller. JSON-only: this is an AJAX endpoint, never a page.
     */
    public function estimate(Request $request, Order $order)
    {
        // Review fix (Critical): same nullable fix as book() above — the
        // browser always submits pickup_slot="" (-> null after
        // ConvertEmptyStringsToNull) even for a drop-off booking, and this
        // endpoint is hit by the SAME form's "Get estimate" button via
        // FormData(bookForm), so it 422'd identically.
        $data = $request->validate([
            'courier'          => 'required|string',
            'service'          => 'sometimes|string',
            'parcel'           => 'sometimes|string',
            'shipping_payment' => 'sometimes|string',
            'insure'           => 'sometimes|boolean',
            'length_cm'        => 'sometimes|nullable|numeric|min:0.01',
            'width_cm'         => 'sometimes|nullable|numeric|min:0.01',
            'height_cm'        => 'sometimes|nullable|numeric|min:0.01',
            'weight_kg'        => 'sometimes|nullable|numeric|min:0.01',
            'pickup_slot'      => 'sometimes|nullable|string',
        ]);

        // Stage gate (owner ruling, Task 4 fix round): same refusal
        // FulfillmentService::book() applies — an unpaid/cancelled-stage
        // order is not bookable, so quoting a fee for it is misleading and
        // would still hit the courier's API for nothing.
        $stage = $order->status?->fulfillment_stage ?? 'ready';
        if (in_array($stage, ['unpaid', 'cancelled'], true)) {
            $message = $stage === 'unpaid'
                ? "Order #{$order->id} is unpaid. Set its status to a ready stage (e.g. Processing) before booking."
                : "Order #{$order->id} is cancelled. Restore it to a ready stage (e.g. Processing) before booking.";

            return response()->json(['ok' => false, 'message' => $message]);
        }

        $courier = CourierManager::get((string) $data['courier']);
        if (!$courier || !$courier->isEnabled()) {
            // Same gate + same reasoning as FulfillmentService::book()'s
            // courier_unavailable branch (owner directive, 2026-08-08) — a
            // flagged-but-unconfigured courier gets a message naming it and
            // pointing at Settings, never a raw estimateFee()/client error.
            $message = ($courier && CourierManager::isFlagged($courier->getKey()))
                ? "{$courier->getName()} is not properly configured. Check its credentials in Settings."
                : 'Selected courier is not available.';

            return response()->json(['ok' => false, 'message' => $message]);
        }

        // Review fix (Critical): drop present-but-null keys before they
        // reach estimateFee()'s $opts — same reasoning as book() above.
        $opts = collect($data)->except('courier')->filter(fn ($v) => $v !== null)->all();
        $opts['insure'] = $request->boolean('insure');

        $estimate = $courier->estimateFee($order, $opts);
        if (!$estimate) {
            return response()->json(['ok' => false, 'message' => 'An estimate is not available yet. Check the pickup address and this order\'s shipping address are complete.']);
        }

        return response()->json([
            'ok'       => true,
            'amount'   => $estimate->amount,
            'currency' => $estimate->currency,
            'label'    => $estimate->label ?? ($estimate->currency . ' ' . number_format($estimate->amount, 2)),
        ]);
    }

    /**
     * Task T4: pickup slots, fetched lazily (see index()'s courierOptions
     * comment for why this ISN'T rendered eagerly into that payload) —
     * called once when the admin actually picks a courier with slots to
     * offer (SPX) in the booking modal's step 2. Courier-agnostic: any
     * registered courier's getPickupSlots() works here.
     */
    public function pickupSlots(Request $request, Order $order)
    {
        $courier = CourierManager::get((string) $request->query('courier', ''));
        if (!$courier || !$courier->isEnabled()) {
            return response()->json(['ok' => false, 'slots' => []]);
        }

        return response()->json(['ok' => true, 'slots' => $courier->getPickupSlots($order)]);
    }

    /**
     * Cancel the courier BOOKING (never the order itself — that's
     * cancelOrder() below). fulfillment-tabs-v3 Task 4 semantics: once the
     * courier CONFIRMS the cancel (the outbound call itself is unchanged —
     * QuadX/SPX's own cancelShipment() contracts are frozen by their tests),
     * the previous booking is snapshotted into courier_meta.booking_history
     * (courier, tracking number, timeline, booking meta, admin_cancelled,
     * cancelled_at) and the LIVE courier fields are reset, returning the
     * order to To Ship > To Pack for rebooking. Pre-Task-4 behavior (write
     * courier_status='canceled' + StatusMapper::apply()) parked the order in
     * the Cancelled tab as an interim state — an admin-initiated booking
     * cancel is NOT an order cancel, so it must not read as one. The
     * Cancelled tab's courier-exception arm now naturally excludes admin
     * cancels: they carry no cancelled courier state at all, and a LATE
     * webhook/poll event for the old tracking number can't resurrect one
     * (CourierWebhookIngestor's provider/tracking verification ignores
     * events that don't match the order's CURRENT — now empty — booking).
     */
    public function cancel(Order $order)
    {
        $courier = CourierManager::get((string) $order->courier_provider);
        if (!$courier || !$order->courier_tracking_number) {
            return back()->with('error', 'No active courier booking on this order.');
        }

        $result = $courier->cancelShipment($order->courier_tracking_number);
        if (!$result['success']) {
            return back()->with('error', 'Cancel failed: ' . ($result['error'] ?? 'unknown error'));
        }

        // The courier confirmed — only NOW does the reset happen.
        $tracking = $order->courier_tracking_number;
        $meta = $order->courier_meta ? (json_decode($order->courier_meta, true) ?: []) : [];
        $history = (isset($meta['booking_history']) && is_array($meta['booking_history']))
            ? $meta['booking_history']
            : [];
        $history[] = [
            'courier'         => (string) $order->courier_provider,
            'tracking_number' => $tracking,
            'status'          => $order->courier_status,
            'timeline'        => $meta['timeline'] ?? [],
            // The full booking meta minus the history itself (never nested).
            'meta'            => \Illuminate\Support\Arr::except($meta, ['booking_history']),
            'admin_cancelled' => true,
            'cancelled_at'    => now()->toIso8601String(),
        ];

        $fill = [
            'courier_provider'        => null,
            'courier_status'          => null,
            'courier_tracking_number' => null,
            // booking_history is the ONE key that persists through the reset.
            'courier_meta'            => json_encode(['booking_history' => $history]),
        ];
        // Core snapshot columns (Order Historical Snapshots): booked by this
        // extension at booking time, so cleared here IF they still point at
        // the shipment just cancelled — a customer must never be shown a
        // voided AWB. A manually-entered core tracking number (order page's
        // own courier/tracking flow) is left alone. The cached label PDF of
        // the old tracking number stays on disk harmlessly — nothing links
        // to it after the reset (label/label-file resolve via
        // courier_tracking_number, now null).
        if ((string) $order->tracking_number === (string) $tracking) {
            $fill['tracking_number'] = null;
            $fill['tracking_link']   = null;
        }
        $order->forceFill($fill)->save();

        OrderHistory::create([
            'order_id'        => $order->id,
            'order_status_id' => $order->order_status_id,
            'comment'         => 'Courier booking cancelled: ' . $tracking . ' (' . $courier->getName() . '). Order returned to To Pack.',
            'notify_customer' => false,
        ]);

        return back()->with('success', "Cancelled booking {$tracking} for order #{$order->id}. The order is back in To Ship, To Pack.");
    }

    /**
     * fulfillment-tabs-v3 Task 4: the REAL order cancel — sets the core
     * order status to the module setting `cancel_order_status_id` (default:
     * the status named "Canceled"; configurable on the fulfillment settings
     * hub) through the SAME status-change path the admin order page uses
     * (OrderController::addHistory(): history entry + order_status_id update
     * + StockService::adjustForStatusChange(), notify OFF here), so stock
     * and history behave identically. Offered on Unpaid rows (their only
     * action) and To Pack rows (alongside Book). Never touches any courier:
     * an order with an ACTIVE booking (or one mid-async-create) is refused
     * with a friendly message — cancel the booking first.
     */
    public function cancelOrder(Order $order)
    {
        if (FulfillmentService::isActivelyBooked($order)) {
            return back()->with('error', "Order #{$order->id} still has an active courier booking ({$order->courier_tracking_number}). Cancel the booking first, then cancel the order.");
        }
        if ((string) $order->courier_status === 'creating') {
            return back()->with('error', "Order #{$order->id} is mid-booking with a courier. Wait for the booking to finish, cancel the booking, then cancel the order.");
        }

        $configuredId = Setting::where('group', 'courier')->where('key', 'cancel_order_status_id')->value('value');
        $status = $configuredId ? \App\Models\OrderStatus::find((int) $configuredId) : null;
        // Default (and fallback for a configured id whose status row was
        // since deleted): the status named "Canceled" — the same default the
        // settings hub's select pre-selects.
        $status = $status ?? \App\Models\OrderStatus::where('name', 'Canceled')->first();
        if (!$status) {
            return back()->with('error', 'No cancel status is configured. Choose one under Settings, Modules, Courier Fulfillment.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $status) {
            // Shared status-change core — the exact mechanics the order
            // page's addHistory() applies (history row + status update +
            // stock adjustment), notify OFF: no customer email from here.
            \App\Services\OrderStatusChanger::apply(
                $order,
                $status->id,
                'Order cancelled from the fulfillment queue.',
                false,
                Auth::id(),
            );
        });

        return back()->with('success', "Order #{$order->id} cancelled ({$status->name}).");
    }

    public function label(Request $request, Order $order)
    {
        $courier = CourierManager::get((string) $order->courier_provider);
        if (!$courier || !$order->courier_tracking_number) {
            return back()->with('error', 'No courier booking to print.');
        }

        $format = $request->query('format', 'zebra');
        $result = $courier->printLabel($order->courier_tracking_number, $format);

        if (!$result['success'] || empty($result['url'])) {
            return back()->with('error', 'Label not available: ' . ($result['error'] ?? 'no URL returned'));
        }

        // Either the provider's own presigned URL (e.g. QuadX's S3 link), or
        // (Task T3) this app's own label-file route when the courier's
        // printLabel() chose to cache the asset instead — see
        // CourierFulfillmentInterface::printLabel()'s docblock, sanctioned
        // evolution #2. redirect()->away() works identically either way.
        return redirect()->away($result['url']);
    }

    /**
     * Task T3: streams a courier label PDF this app already downloaded +
     * cached locally (see SpxFulfillment::printLabel()/labelStoragePath()).
     * Same admin-auth middleware as every other route in this group — the
     * whole POINT of caching server-side instead of exposing the
     * provider's own (perishable, e.g. SPX: 30-minute / 10-open) link is
     * that this route is the only way to reach the file. 404s (rather than
     * an error flash) when nothing has been cached yet — this route is
     * never linked to before printLabel() has run at least once via /label.
     */
    public function labelFile(Order $order)
    {
        if (!$order->courier_provider || !$order->courier_tracking_number) {
            abort(404);
        }

        $path = "courier-labels/{$order->courier_provider}/{$order->courier_tracking_number}.pdf";
        $disk = Storage::disk('local');

        if (!$disk->exists($path)) {
            abort(404);
        }

        return $disk->download($path, "label-order-{$order->id}.pdf");
    }

    public function track(Order $order)
    {
        $courier = CourierManager::get((string) $order->courier_provider);
        if (!$courier || !$order->courier_tracking_number) {
            return back()->with('error', 'No courier booking to track.');
        }

        $result = $courier->getTracking($order->courier_tracking_number);
        if (!$result['success']) {
            return back()->with('error', 'Tracking failed: ' . ($result['error'] ?? 'unknown error'));
        }

        $fill = [];
        if (!empty($result['status'])) {
            $fill['courier_status'] = $result['status'];
        }

        $links = $result['links'] ?? null;
        $timeline = $result['timeline'] ?? null;
        if (!empty($links) || !empty($timeline)) {
            $meta = $order->courier_meta ? (json_decode($order->courier_meta, true) ?: []) : [];
            if (!empty($links)) {
                $meta['tracking_links'] = $links;
            }
            if (!empty($timeline)) {
                $meta['timeline'] = $timeline;
            }
            $fill['courier_meta'] = json_encode($meta);
        }

        // Core tracking_link snapshot: prefer the delivery URL, then pickup. When
        // this call returned no usable link, keep whatever core already has —
        // never null out a previously stored link.
        $trackingLink = null;
        foreach ([$links['delivery'] ?? null, $links['pickup'] ?? null] as $u) {
            if (is_string($u) && str_starts_with($u, 'http')) {
                $trackingLink = $u;
                break;
            }
        }
        // raw column, NOT the accessor — the accessor live-derives and would trigger relation queries / freeze derived URLs.
        $trackingLink = $trackingLink ?? $order->getRawOriginal('tracking_link');
        if ($trackingLink !== $order->getRawOriginal('tracking_link')) {
            $fill['tracking_link'] = $trackingLink;
        }

        if (!empty($fill)) {
            // courier_* columns aren't in core Order::$fillable — must forceFill.
            $order->forceFill($fill)->save();
        }
        if (!empty($result['status'])) {
            \Extensions\CourierFulfillment\StatusMapper::apply($order, $result['status']);
        }
        return back()->with('success', "Status: " . ($result['status'] ?? 'unchanged'));
    }
}
