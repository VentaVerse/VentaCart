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
    public function index(Request $request)
    {
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

        $filter  = $request->query('filter', 'to_book');
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
            $base->where(function ($x) use ($s) {
                if (is_numeric($s)) {
                    $x->orWhere('id', $s);
                }
                $x->orWhere('courier_tracking_number', 'like', "%{$s}%")
                    ->orWhereRaw("CONCAT(shipping_first_name,' ',shipping_last_name) like ?", ["%{$s}%"]);
            });
        }

        // Phase tallies for the segment bar (and, via the same method, for
        // returns() below — one derivation, never two).
        [$phaseCounts, $phaseStatusGroups, $handoverByCourier] = $this->phaseTally($base);

        $query = (clone $base)
            ->with('status:id,name')
            ->with(['products' => function ($q) {
                // Item rows for the two-tier table: snapshot name/sku/image on
                // the line itself, live product image as the fallback.
                $q->select('id', 'order_id', 'product_id', 'name', 'sku', 'image', 'variant_label', 'quantity')
                    ->with('product:id,image');
            }])
            ->withCount('products')
            ->latest();

        if ($filter === 'to_book') {
            // Task T4 fix (+ final-review Finding 2): excludes BOTH async
            // bridging statuses ('creating' AND 'creation_failed' — also
            // tracking-number-null) — those belong to the Booking/Failed
            // tabs respectively, never To Book (see phaseTally()'s
            // matching fix above and FulfillmentService::ASYNC_CREATE_STATUSES).
            $query->whereNull('courier_tracking_number')
                ->where(function ($q) {
                    $q->whereNull('courier_status')
                        ->orWhereNotIn('courier_status', FulfillmentService::ASYNC_CREATE_STATUSES);
                });
        } elseif ($filter !== 'all') {
            // A spine-phase tab: booked orders whose (provider, raw status)
            // combo buckets into this phase — the exact combos found by the
            // tally above, so the list always matches its tab's count.
            // ('all' stays supported as a filter value for deep links even
            // though it no longer renders as a tab.)
            $groups = $phaseStatusGroups[$filter] ?? [];
            // Task T4 fix (+ final-review Finding 2): a 'creating' or
            // 'creation_failed' order has no tracking number yet but must
            // still be reachable from its tab (Booking / Failed) —
            // applyPhaseGroups() below narrows to the exact (provider,
            // status) combos the tally found for $filter, so relaxing this
            // to admit those rows never leaks one into a phase it doesn't
            // belong to.
            $query->where(function ($q) {
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
        $item = SettingsNav::find('fulfillment');
        if (!$item) {
            return false;
        }

        return $item['permission'] === null || (Auth::user()?->hasPermission($item['permission']) ?? false);
    }

    /**
     * Phase is a per-courier PHP derivation (statusPhase()), which SQL can't
     * group by directly — but it's a pure function of (provider, raw status,
     * booked-or-not), so GROUP BY those three and bucket the handful of
     * combos in PHP. Work stays proportional to the couriers' status
     * vocabularies, never to the number of orders. Shared by index()'s
     * segment-bar tallies and returns()'s listing — the one place either
     * page turns raw (provider, status) rows into spine phases.
     *
     * @param \Illuminate\Database\Eloquent\Builder $base
     * @return array{0: array<string,int>, 1: array<string, array<string, array<int, ?string>>>, 2: array<string,int>}
     *   [phaseCounts, phaseStatusGroups, handoverByCourier]
     */
    private function phaseTally($base): array
    {
        $combos = (clone $base)
            ->selectRaw('courier_provider, courier_status, (courier_tracking_number IS NULL) as unbooked, COUNT(*) as n')
            ->groupByRaw('courier_provider, courier_status, (courier_tracking_number IS NULL)')
            ->get();

        $phaseCounts = array_fill_keys(\Extensions\CourierFulfillment\Support\PhaseTone::PHASES, 0);
        $handoverByCourier = [];      // courier display name => waiting-parcel count (the ERP's co-couriers tally)
        $phaseStatusGroups = [];      // phase => provider key => raw statuses present in the DB right now
        foreach ($combos as $row) {
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
                : 'to_book';
            $phaseCounts[$phase] = ($phaseCounts[$phase] ?? 0) + (int) $row->n;

            if ($treatAsBooked) {
                $phaseStatusGroups[$phase][(string) $row->courier_provider][] = $row->courier_status;
                if ($phase === 'to_handover') {
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
        // the bounded create-poll window elapsed). "Booked — tracking ."
        // read as a broken booking; say what actually happened instead.
        if (empty($res['tracking_number'])) {
            $courierName = CourierManager::get((string) ($res['courier'] ?? ''))?->getName()
                ?: strtoupper((string) ($res['courier'] ?? '')) ?: 'The courier';

            return back()->with('success', "Booking submitted. {$courierName} is creating the shipment. The tracking number will appear here in a moment (refresh or poll statuses).");
        }

        return back()->with('success', "Booked, tracking {$res['tracking_number']}.");
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
