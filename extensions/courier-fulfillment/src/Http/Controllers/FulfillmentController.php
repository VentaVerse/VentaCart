<?php

namespace Extensions\CourierFulfillment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
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
        [$phaseCounts, $phaseStatusGroups, $handoverByCourier] = $this->phaseTally($base);

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

        // fulfillment-tabs-v3 Task 2: STAGE-FIRST membership. An order's
        // Venta order-status stage claims it before any courier phase is
        // ever consulted (FulfillmentService::tabBucket()'s docblock has the
        // full rule) — 'unpaid'/'cancelled' filters are therefore pure
        // stage filters, and every courier-phase filter below is additionally
        // scoped to stage 'ready' so a stage-claimed order never leaks into
        // a courier-phase tab it no longer belongs to.
        //
        // fulfillment-tabs-v3 Task 3: branches on $bucket, not $filter — for
        // every tab except To Ship they're the same string, but To Ship
        // itself has no query shape of its own; its ACTIVE SUB-tab
        // ($bucket = 'to_pack' or 'to_handover') is what actually narrows
        // the listing, exactly as it did as a top-level tab before this task.
        if ($bucket === 'unpaid') {
            $query->whereHas('status', fn ($q) => $q->where('fulfillment_stage', 'unpaid'));
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
            $query->whereHas('status', fn ($q) => $q->where('fulfillment_stage', 'ready'))
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
     * fulfillment-tabs-v3 Task 2: STAGE-FIRST, same as index()'s tabs — a
     * stage-'unpaid' or stage-'cancelled' order does not belong here even
     * if its courier phase happens to be 'returning'/'failed' (a real order
     * cancel outranks a courier-side exception; see
     * FulfillmentService::tabBucket()'s docblock). phaseTally() already
     * only populates $phaseStatusGroups from stage-'ready' combos, so the
     * explicit stage filter below is defense in depth, not the only guard.
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

        $query = (clone $base)
            ->whereHas('status', fn ($q) => $q->where('fulfillment_stage', 'ready'))
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
     * Bucket membership is STAGE-FIRST (FulfillmentService::
     * tabBucketForParts()'s docblock has the full rule): stage 'unpaid'/
     * 'cancelled' claims the order's bucket outright; only a stage-'ready'
     * order buckets by its courier phase, and $phaseStatusGroups (the
     * per-bucket provider/status index applyPhaseGroups() replays for the
     * listing query) is populated ONLY from stage-'ready' combos — an
     * 'unpaid'/'cancelled'-stage order is never reachable through a
     * (provider, status) group, only through the stage filters index()
     * builds directly for those two buckets.
     *
     * @param \Illuminate\Database\Eloquent\Builder $base
     * @return array{0: array<string,int>, 1: array<string, array<string, array<int, ?string>>>, 2: array<string,int>}
     *   [phaseCounts, phaseStatusGroups, handoverByCourier]
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
        $phaseStatusGroups = [];      // bucket => provider key => raw statuses present in the DB right now (stage-'ready' combos only)
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

            // fulfillment-tabs-v3 Task 2: stage claims the bucket first; a
            // stage-'ready' order's courier 'booking' phase (async create in
            // flight) collapses into 'to_handover' for TAB membership only —
            // phaseForParts() above still reports the honest 'booking' phase
            // for any row-level badge that reads it separately.
            $bucket = FulfillmentService::tabBucketForParts($stage, $phase);
            $phaseCounts[$bucket] = ($phaseCounts[$bucket] ?? 0) + (int) $row->n;

            if ($stage === 'ready' && $treatAsBooked) {
                $phaseStatusGroups[$bucket][(string) $row->courier_provider][] = $row->courier_status;
                if ($bucket === 'to_handover') {
                    $name = CourierManager::get((string) $row->courier_provider)?->getName()
                        ?: (strtoupper((string) $row->courier_provider) ?: 'Unknown courier');
                    $handoverByCourier[$name] = ($handoverByCourier[$name] ?? 0) + (int) $row->n;
                }
            }
        }

        return [$phaseCounts, $phaseStatusGroups, $handoverByCourier];
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

        $order->forceFill(['courier_status' => 'canceled'])->save();
        \Extensions\CourierFulfillment\StatusMapper::apply($order, 'canceled');
        return back()->with('success', "Cancelled booking for order #{$order->id}.");
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
