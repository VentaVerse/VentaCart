<?php

namespace Extensions\CourierFulfillment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\ShippingCourier;
use App\Support\SettingsNav;
use Extensions\CourierFulfillment\CourierManager;
use Extensions\CourierFulfillment\FulfillmentService;
use Extensions\CourierFulfillment\Http\Requests\ManualShipmentRequest;
use Extensions\CourierFulfillment\Models\PickupAddress;
use Extensions\CourierFulfillment\PlacementMap;
use Extensions\CourierFulfillment\ServiceabilityService;
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
     * The URL vocabulary: every value this page's own segment-bar links can
     * ever set, in the order the bar renders them (owner rulings,
     * 2026-08-09).
     *
     * 'all' and 'to_ship' are the two entries that are NOT placements:
     * 'all' is a VIEW over every order (no status is ever placed there),
     * 'to_ship' is a heading over the to_pack / to_handover sub-tabs. Every
     * other value is a placement from the module's own mapping (PlacementMap).
     */
    private const VALID_FILTERS = [
        'all', 'unpaid', 'to_ship', 'shipping',
        'failed', 'lost_damaged', 'delivered', 'cancelled',
    ];
    private const VALID_SUBS = ['to_pack', 'to_handover'];

    /**
     * Retired filter values a saved link/bookmark can still carry:
     * 'to_book' predates the to_pack rename, 'to_handover' and 'booking'
     * were their own top-level tabs before To Ship folded both into its
     * sub-tabs, and 'in_transit' is what the Shipping tab was called before
     * the 2026-08-09 rulings renamed it. 301 (house convention for a moved
     * resource, see ShippingSettingController::index()'s TAB_REDIRECTS) so
     * an old link converges on the new URL instead of staying broken
     * forever.
     */
    private const LEGACY_FILTER_REDIRECTS = [
        'to_book'     => ['filter' => 'to_ship', 'sub' => 'to_pack'],
        'to_handover' => ['filter' => 'to_ship', 'sub' => 'to_handover'],
        'booking'     => ['filter' => 'to_ship', 'sub' => 'to_handover'],
        'in_transit'  => ['filter' => 'shipping'],
    ];

    public function index(Request $request)
    {
        // fulfillment-tabs-v Task 3: legacy alias, resolved BEFORE anything
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

        // 'filter' is one of the eight top-level segment-bar tabs; an
        // unrecognized value (never emitted by this page's own links, and
        // not one of the legacy aliases handled above) falls back to the
        // default tab rather than rendering an empty/broken page. To Ship
        // stays the default: it is where fulfillment work actually starts,
        // and All is a lookup surface, not a worklist.
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
        // identical to $filter for every tab except To Ship, which is a
        // HEADING with no membership of its own and defers to its ACTIVE
        // sub-tab's placement (to_pack / to_handover). Every value here is a
        // placement from the module's own mapping, one per tab, with the
        // single exception of 'all', a VIEW that constrains nothing.
        $bucket = $filter === 'to_ship' ? $sub : $filter;

        $perPage = (int) $request->query('per_page', 25);
        if (!in_array($perPage, [10, 25, 50], true)) {
            $perPage = 25;
        }

        // One base scope shared by the tab tallies and the listing, so the
        // segment-bar counts always describe exactly the set the operator is
        // paging through (search included).
        // 'orders.' qualification is load-bearing, not cosmetic: the tallies
        // below clone this scope and join the module's placement table, which
        // has its own order_status_id column - an unqualified name there is
        // ambiguous and 500s every page load.
        $base = Order::query()
            ->whereNotNull('orders.order_status_id'); // Ghost/abandoned orders (no status) are never fulfilled — same default as the core Orders page.

        if ($s = $request->query('q')) {
            // Task 2 fix (code review, Critical): 'orders.' qualification is
            // load-bearing, not cosmetic. The tallies below clone $base and
            // join order_statuses, which ALSO has an 'id' column, so an
            // unqualified 'id' here (unambiguous on $query's own listing,
            // which has no join) 500s with SQLSTATE 1052 "Column 'id' in
            // where clause is ambiguous" the moment the SAME closure runs
            // inside a joined tally query - i.e. every numeric search, on
            // every page load, since the tallies always run. Every other
            // column this closure touches (courier_tracking_number,
            // shipping_first_name, shipping_last_name) is orders-only, and
            // order_status_id below is also orders-only (order_statuses has
            // no such column): 'id' is the sole collision.
            $base->where(function ($x) use ($s) {
                if (is_numeric($s)) {
                    $x->orWhere('orders.id', $s);
                }
                $x->orWhere('courier_tracking_number', 'like', "%{$s}%")
                    ->orWhereRaw("CONCAT(shipping_first_name,' ',shipping_last_name) like ?", ["%{$s}%"]);
            });
        }

        // Segment-bar tallies: ONE grouped query over the placement of each
        // order's core status. Nothing else feeds a count. 'all' comes out of
        // that same query (see placementTally()), so it can never disagree
        // with the tabs it sits above, and it includes the orders whose
        // status is UNPLACED - they belong to no tab but are still orders.
        $phaseCounts = $this->placementTally($base);
        $handoverByCourier = $this->handoverByCourier($base);

        // To Ship is a HEADING, not a placement: its count is exactly its
        // two sub-tabs added together, and the two are disjoint placements,
        // so no row can be counted twice.
        $phaseCounts['to_ship'] = ($phaseCounts['to_pack'] ?? 0) + ($phaseCounts['to_handover'] ?? 0);

        $query = (clone $base)
            ->with('status:id,name')
            // Core's own manual courier record (orders.shipping_courier_id),
            // eager loaded so a page of manually shipped rows costs one query
            // rather than one per row. The row still prefers the order's
            // shipping_courier_name snapshot; this is the fallback for the
            // pre-snapshot orders, and the source of a tracking link the
            // accessor has to derive.
            ->with('shippingCourier:id,name,tracking_url')
            ->with(['products' => function ($q) {
                // Item rows for the two-tier table: snapshot name/sku/image on
                // the line itself, live product image as the fallback.
                $q->select('id', 'order_id', 'product_id', 'name', 'sku', 'image', 'variant_label', 'quantity')
                    ->with('product:id,image');
            }])
            ->withCount('products')
            ->latest();

        // MEMBERSHIP, the whole listing rule (fulfillment-placement spec,
        // section 2): the rows of a tab are the orders whose core status is
        // mapped to that tab's placement. One where clause, no courier column
        // anywhere in it, and it is the exact SQL counterpart of the tally
        // above and of FulfillmentService::tabBucket() per row - the three
        // cannot disagree because all three read the same module table.
        //
        // A sub-select against the module's mapping table (never a join onto
        // order_statuses, which no longer carries a placement column): an
        // order whose status has NO mapping matches no bucket here, which is
        // exactly why an unplaced order shows up in All and nowhere else.
        //
        // Branches on $bucket, not $filter: they are the same string for
        // every tab except To Ship, which is a heading whose ACTIVE SUB-tab
        // ('to_pack' or 'to_handover') is what narrows the listing. The All
        // tab adds no constraint at all: it lists every order in scope,
        // placed or not, and each row still renders the actions of the tab it
        // really belongs to (the view derives that per row from tabBucket()).
        if ($bucket !== 'all') {
            $query->whereIn('orders.order_status_id', function ($q) use ($bucket) {
                $q->select('order_status_id')
                    ->from(PlacementMap::TABLE)
                    ->where('placement', $bucket);
            });
        }

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

        // The couriers a parcel can leave by WITHOUT an integration: core's own
        // shipping_couriers table (LBC Express, J&T, Gogo), which already
        // carries the {tracking_number} URL template the recorded link is
        // built from. Nothing new is registered or configured for this; the
        // store's courier list simply becomes reachable from the queue.
        $manualCouriers = ShippingCourier::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (ShippingCourier $c) => ['id' => $c->id, 'name' => $c->name])
            ->values()
            ->all();

        return view('courier-fulfillment::index', [
            'orders' => $orders,
            'couriers' => $couriers,
            'manualCouriers' => $manualCouriers,
            'primary' => $primary,
            'courierOptions' => $courierOptions,
            'filter' => $filter,
            'sub' => $sub,
            'perPage' => $perPage,
            'phaseCounts' => $phaseCounts,
            'handoverByCourier' => $handoverByCourier,
            'paused' => false,
            'canConfigureCouriers' => $this->canConfigureCouriers(),
            // Owner directive (2026-08-12): recording a manual shipment is a
            // status change like any other, so the operator names the status.
            // The SAME list the order page offers (every status of this
            // store, in its own sort order), never a subset this module
            // invents, and defaulted to what the module would have applied on
            // its own so ignoring the field changes nothing.
            'orderStatuses' => OrderStatus::where('store_id', (int) config('app.store_id', 1))
                ->orderBy('sort_order')
                ->get(['id', 'name']),
            'manualDefaultStatusId' => FulfillmentService::bookedStatus()?->id,
            // Booking-time origin choice. A store with one address never sees
            // a picker (the default is the only answer); with several, step 2
            // offers them with the default pre-selected. Courier-agnostic:
            // both adapters resolve the same way through
            // PickupAddress::resolveFor().
            'pickupAddresses' => PickupAddress::query()
                ->orderByDesc('is_default')
                ->orderBy('label')
                ->get()
                ->map(fn (PickupAddress $a) => [
                    'id'        => $a->id,
                    'label'     => $a->label,
                    'summary'   => $a->summary(),
                    'isDefault' => $a->is_default,
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Command-bar "Refresh from couriers" — runs the same courier:poll-status
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
     * Returns monitoring - a sibling PAGE, never an eighth queue tab (see
     * the segment bar's own comment in the view). Lists orders whose LIVE
     * COURIER STATE is 'returning' (a parcel on its way back) or 'failed'
     * (broke somewhere in its journey) - exactly the two phases the ERP
     * precedent treats as "coming back", grouped together because an
     * operator watching this page cares about both the same way.
     *
     * DELIBERATELY INDEPENDENT OF PLACEMENT (fulfillment-placement spec):
     * this page is not a tab and does not follow the tab rule. The tabs
     * answer "where does this ORDER sit in my workflow" (core status
     * placement, nothing else); this page answers "which PARCELS are coming
     * back at me right now", which is a fact about the shipment and can only
     * come from the courier. A cancelled or delivered-placed order whose
     * parcel is physically returning still belongs on this list, precisely
     * because no tab would ever show it.
     *
     * That is why this is the ONE surface that derives from
     * FulfillmentService::phaseForParts() (and therefore each courier's own
     * statusPhase()): it is monitoring courier state on purpose, not
     * deciding tab membership. Only BOOKED rows (a tracking number exists)
     * can qualify.
     *
     * This is a MONITORING surface only. The full customer-initiated RMA
     * process (refunds, restock guidance) is a distinct product feature,
     * tracked as a roadmap card rather than built here.
     */
    public function returns(Request $request)
    {
        $base = Order::query()->whereNotNull('order_status_id');

        $groups = $this->courierPhaseGroups($base, ['returning', 'failed']);

        $query = (clone $base)->whereNotNull('courier_tracking_number');
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
        // Wave 0: same manifest-owned rule as the configure gate — never a
        // core registry lookup (the retired SettingsNav entry).
        return \Illuminate\Support\Facades\Gate::allows('courier-fulfillment.configure');
    }

    /**
     * The segment bar's counts: ONE grouped query over the module's own
     * placement mapping. No courier column is read, no PHP bucketing happens,
     * and no phase is derived - because membership is the placement and
     * nothing else (FulfillmentService::tabBucket()). Work is proportional to
     * the number of DISTINCT placements (nine, counting the unplaced group),
     * never to the number of orders.
     *
     * A LEFT join, deliberately: an order whose status carries no mapping
     * must not be dropped from the tally, or the All count would quietly
     * disagree with the list underneath it. Those rows group under a NULL
     * placement, land in 'all' and in no tab - the same rule tabBucket()
     * applies per row.
     *
     * 'all' is produced HERE, from this one query's own row set, rather than
     * summed from the eight tab counts afterwards: summing would silently
     * drop the unplaced orders that the All tab does in fact list.
     *
     * @param \Illuminate\Database\Eloquent\Builder $base
     * @return array<string,int> placement => count (every placement present, 0 when empty) plus 'all'
     */
    private function placementTally($base): array
    {
        $rows = (clone $base)
            ->leftJoin(
                PlacementMap::TABLE . ' as cf_placements',
                'orders.order_status_id',
                '=',
                'cf_placements.order_status_id',
            )
            ->selectRaw('cf_placements.placement as placement, COUNT(*) as n')
            ->groupBy('cf_placements.placement')
            ->get();

        $counts = array_fill_keys(FulfillmentService::PLACEMENTS, 0);
        $total = 0;

        foreach ($rows as $row) {
            $n = (int) $row->n;
            $total += $n;

            if ($row->placement === null) {
                continue; // unplaced: counted in All only
            }

            $placement = (string) $row->placement;
            if (array_key_exists($placement, $counts)) {
                $counts[$placement] += $n;
            }
        }

        // All is a VIEW, not a placement: every order in scope, placed or not.
        $counts['all'] = $total;

        return $counts;
    }

    /**
     * "Parcels waiting for handover", per courier - the chips the operator
     * counts against before a pickup arrives, shown only on the To Handover
     * sub-tab. This is NOT membership (the tab's rows are already decided by
     * placement above): it is a breakdown OF that tab, so it only reads the
     * courier each already-included row happens to carry. Rows with no
     * courier on them contribute to no chip, which is the honest answer -
     * there is no parcel to hand over.
     *
     * @param \Illuminate\Database\Eloquent\Builder $base
     * @return array<string,int> courier display name => waiting-parcel count
     */
    private function handoverByCourier($base): array
    {
        $rows = (clone $base)
            ->join(
                PlacementMap::TABLE . ' as cf_placements',
                'orders.order_status_id',
                '=',
                'cf_placements.order_status_id',
            )
            ->where('cf_placements.placement', FulfillmentService::BOOKED_PLACEMENT)
            ->whereNotNull('orders.courier_provider')
            ->selectRaw('orders.courier_provider as provider, COUNT(*) as n')
            ->groupBy('orders.courier_provider')
            ->get();

        $byCourier = [];
        foreach ($rows as $row) {
            $name = CourierManager::get((string) $row->provider)?->getName()
                ?: (strtoupper((string) $row->provider) ?: 'Unknown courier');
            $byCourier[$name] = ($byCourier[$name] ?? 0) + (int) $row->n;
        }

        return $byCourier;
    }

    /**
     * The returns page's own derivation, and the ONLY place a courier phase
     * still selects a set of orders (see returns()'s docblock for why that
     * page is deliberately not a tab). Phase is a per-courier PHP function
     * (statusPhase()) that SQL cannot group by, but it is pure in
     * (provider, raw status), so GROUP BY those two and bucket the handful
     * of combos in PHP: work stays proportional to the couriers' status
     * vocabularies, never to the number of orders. The result feeds
     * applyPhaseGroups() below, which replays exactly those combos as the
     * listing's where clause.
     *
     * @param \Illuminate\Database\Eloquent\Builder $base
     * @param array<int,string> $phases spine phases to collect
     * @return array<string, array<int, ?string>> provider key => raw statuses
     */
    private function courierPhaseGroups($base, array $phases): array
    {
        $combos = (clone $base)
            ->whereNotNull('orders.courier_tracking_number')
            ->selectRaw('orders.courier_provider as courier_provider, orders.courier_status as courier_status')
            ->groupByRaw('orders.courier_provider, orders.courier_status')
            ->get();

        $groups = [];
        foreach ($combos as $row) {
            $phase = FulfillmentService::phaseForParts($row->courier_provider, $row->courier_status);
            if (in_array($phase, $phases, true)) {
                $groups[(string) $row->courier_provider][] = $row->courier_status;
            }
        }

        return $groups;
    }

    /**
     * Constrains a BOOKED-orders query to exactly the (provider, status)
     * combos in $groups (as produced by courierPhaseGroups() above) - the
     * query-side mirror of the phase bucketing, so the returns listing's
     * rows always match the derivation that selected them. An empty $groups
     * (no order is in those phases) constrains to nothing rather than
     * leaving the query open.
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
            // Which of the store's pickup addresses this parcel leaves from.
            // Absent (one address on file, so the picker never rendered)
            // means "the default": PickupAddress::resolveFor() decides that,
            // once, for every courier. `nullable` alongside `sometimes` for
            // the same ConvertEmptyStringsToNull reason documented above.
            'pickup_address_id' => 'sometimes|nullable|integer|exists:courier_fulfillment_pickup_addresses,id',
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
     * Record a parcel leaving by a courier this app does not integrate with:
     * no API call, no waybill, no polling. See
     * FulfillmentService::bookManual() for why this is a branch of its own
     * rather than a pseudo-courier, and for the columns it writes.
     *
     * A refusal comes back on the row it was refused for, in the same flash
     * channel a refused integrated booking uses, so the two read alike.
     */
    public function bookManual(ManualShipmentRequest $request, Order $order)
    {
        $res = app(FulfillmentService::class)->bookManual($order, $request->validated());

        if (!$res['success']) {
            return back()->with('error', $res['error'] ?? 'The shipment could not be recorded.');
        }

        $tracking = $res['tracking_number'] ?? null;

        $message = $tracking
            ? "Shipment recorded with {$res['courier']}, tracking {$tracking}."
            : "Shipment recorded with {$res['courier']}. Add the tracking number on the order when you have it.";

        // Which state the order is now in, named, because the operator chose
        // it and a status change they cannot see confirmed is a status change
        // they will go and check.
        if (!empty($res['status'])) {
            $message .= " Order status set to {$res['status']}.";
        }

        return back()->with('success', $message);
    }

    /**
     * The undo for the above: forget the recorded courier, tracking number and
     * tracking link, and return the order to To Pack so it can be recorded
     * again or booked with an integrated courier instead. Contacts nobody,
     * because nobody was contacted to create it.
     */
    public function clearManual(Order $order)
    {
        $res = app(FulfillmentService::class)->clearManual($order);

        if (!$res['success']) {
            return back()->with('error', $res['error'] ?? 'The recorded shipment could not be cleared.');
        }

        return back()->with('success', $res['status']
            ? "Cleared the recorded shipment for order #{$order->id}. The order is back in To Ship, To Pack as {$res['status']}."
            : "Cleared the recorded shipment for order #{$order->id}.");
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
            // The estimate is quoted from the SAME origin the booking would
            // leave from, so a store with several addresses does not get a
            // fee measured from one and a parcel collected from another.
            'pickup_address_id' => 'sometimes|nullable|integer|exists:courier_fulfillment_pickup_addresses,id',
        ]);

        // Placement gate: the same single refusal FulfillmentService::book()
        // applies (shared, never a second copy of the rule) - an order that
        // is not placed under To Pack cannot be booked, so quoting a fee for
        // it is misleading and would still hit the courier's API for nothing.
        $blocked = FulfillmentService::bookingBlockedReason($order);
        if ($blocked !== null) {
            return response()->json(['ok' => false, 'message' => $blocked]);
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
     * Who will carry this parcel? Asked once, when the booking modal opens,
     * and answered for every configured courier at once (owner ruling,
     * 2026-08-12: the modal waits and then draws settled cards, rather than
     * drawing cards that rearrange themselves under the operator's hand).
     *
     * Lazy for the same reason the estimate and pickup-slot endpoints are:
     * the queue page itself must never pay for an outbound courier call, so
     * this runs on modal open and not on page load. The per-courier budget
     * (ServiceabilityService::TIMEOUT_SECONDS) is what keeps that wait
     * bounded.
     *
     * Creates nothing. Both endpoints behind it are quote endpoints.
     * JSON-only: an AJAX endpoint, never a page.
     */
    public function serviceability(Order $order)
    {
        // The same placement gate estimate() applies, and for the same
        // reason: an order that cannot be booked cannot be shipped by
        // anyone, so asking two couriers about it spends outbound calls on a
        // question with no consequence. The modal only ever opens from a
        // bookable row, so this is defence in depth rather than a state the
        // UI can reach. The caller reads a refusal here as "could not
        // check", which is the honest reading and leaves every card
        // clickable.
        if (FulfillmentService::bookingBlockedReason($order) !== null) {
            return response()->json(['ok' => false, 'couriers' => [], 'all_refused' => false]);
        }

        $verdicts = ServiceabilityService::forOrder($order);

        return response()->json([
            'ok'          => true,
            'couriers'    => array_map(fn ($v) => $v->toArray(), $verdicts),
            // Every courier we could ask said no. The modal prints one plain
            // line above the cards for this; the manual card stays selectable
            // exactly as it always is.
            'all_refused' => ServiceabilityService::allRefused($verdicts),
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
     * cancelOrder() below). fulfillment-tabs-v Task 4 semantics: once the
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
        // The whole confirmed-cancel transition lives in
        // FulfillmentService::cancelBooking() (shared with the ERP API);
        // this wrapper only translates its report into flashes.
        $res = FulfillmentService::cancelBooking($order, Auth::id());

        return $res['success']
            ? back()->with('success', $res['message'])
            : back()->with('error', $res['error']);
    }

    /**
     * fulfillment-tabs-v Task 4: the REAL order cancel — sets the core
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
        // Shared with the ERP API: FulfillmentService::refreshTracking()
        // pulls live status, persists timeline/links/courier_status and the
        // core tracking_link snapshot, and runs StatusMapper.
        $res = FulfillmentService::refreshTracking($order);

        return $res['success']
            ? back()->with('success', 'Status: ' . ($res['status'] ?? 'unchanged'))
            : back()->with('error', $res['error']);
    }
}
