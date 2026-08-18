<x-admin.shell title="Fulfillment" active="fulfillment">
    @push('head')
        @vite('extensions/courier-fulfillment/resources/css/fulfillment.css')
    @endpush

    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <span>Sales</span>
                <span aria-hidden="true">/</span>
                <span>Fulfillment</span>
            </div>
            <h1 class="v-head__title">Fulfillment</h1>
        </div>
        <div class="v-head__actions">
            <a href="{{ route('admin.fulfillment.settings') }}" class="v-btn v-btn--secondary">Courier settings</a>
            @unless ($paused)
                <form method="POST" action="{{ route('admin.fulfillment.poll') }}">
                    @csrf
                    <button type="submit" class="v-btn v-btn--primary">Refresh from couriers</button>
                </form>
            @endunless
        </div>
    </div>

    @if ($paused)
        {{-- Explicitly paused (courier.fulfillment_enabled = '0') — an explanatory
             page, never a redirect. Bookings triggered by the ERP endpoint are a
             separate code path and keep working regardless of this toggle. --}}
        <x-admin.panel>
            <div class="v-empty">
                <p class="v-empty__title">Fulfillment is paused</p>
                <p class="v-empty__line">The manual booking queue is turned off, so Fulfillment is hidden from the Sales menu and this page lists nothing. Orders booked automatically through the ERP integration (if configured) are unaffected. Switch it back on in Courier Settings.</p>
                @if ($canConfigureCouriers)
                    <a href="{{ route('admin.fulfillment.settings') }}" class="v-btn v-btn--secondary v-btn--sm">Go to Courier Settings</a>
                @endif
            </div>
        </x-admin.panel>
    @else

    @php
        // Cached status labels (populated per-courier by the "Refresh from {courier}"
        // button on the settings page) — same source the settings screen uses, never a
        // live call. Memoized per provider since a page of orders can span couriers.
        $statusLabelsByProvider = [];
        $humanizeStatus = function (string $status, ?string $provider) use (&$statusLabelsByProvider) {
            // Task T4: the internal bridging status an async courier
            // (SPX) sets while a booking is still mid-flight (see
            // FulfillmentService::phaseFor()'s docblock) — never part of
            // any courier's own {provider}_status_labels cache, so it
            // gets its own honest, courier-agnostic label here rather than
            // falling through to a generic ucwords() of the raw string
            // (which would've said "Creating" anyway, but naming it
            // explicitly keeps this the one place that decides how it reads).
            if ($status === 'creating') {
                return 'Creating…';
            }
            // Final-review fix (Finding 2): the async create's permanent-
            // failure bridging status (see FulfillmentService::
            // ASYNC_CREATE_STATUSES) — same reasoning as 'creating' above:
            // never part of any courier's own status-labels cache, so it
            // gets its honest label here.
            if ($status === 'creation_failed') {
                return 'Booking failed';
            }
            if ($provider === null) {
                return ucwords(str_replace('_', ' ', $status));
            }
            if (!array_key_exists($provider, $statusLabelsByProvider)) {
                $raw = \App\Models\Setting::where('group', 'courier')->where('key', $provider . '_status_labels')->value('value');
                $decoded = $raw ? (json_decode($raw, true) ?: []) : [];
                $labels = is_array($decoded) ? $decoded : [];

                // Owner task (2026-08-08, "SPX status from code to their
                // actual meaning"): a courier MAY expose a static, pre-known
                // vocabulary via statusLabels() (SpxFulfillment does — see
                // its docblock) so a live order reads its meaningful label
                // even before an admin ever visits Settings and clicks
                // Refresh. The cached setting (above) still wins for any
                // code it already covers — this only fills the gaps.
                $courierInstance = \Extensions\CourierFulfillment\CourierManager::get($provider);
                if ($courierInstance && method_exists($courierInstance, 'statusLabels')) {
                    $labels += $courierInstance->statusLabels();
                }

                $statusLabelsByProvider[$provider] = $labels;
            }
            return $statusLabelsByProvider[$provider][$status] ?? ucwords(str_replace('_', ' ', $status));
        };

        // The queue's shared tone words (Support\PhaseTone) mapped onto the
        // v badge vocabulary. One map, used by the rows here and mirrored
        // in the drawer's Alpine toneClasses below.
        $badgeTone = fn (string $tone) => [
            'neutral' => 'quiet', 'success' => 'good', 'warning' => 'warn',
            'danger' => 'bad', 'info' => 'info',
        ][$tone] ?? 'quiet';

        // The eight top-level tabs, in the owner's ruled order (2026-08-09).
        // Each is a GROUP that core order statuses are placed into
        // (Settings, Order Statuses): a tab is never a status, and the
        // status's placement is the only thing that decides which tab an
        // order appears in.
        //
        // Two of them are not placements, for opposite reasons:
        //
        //  - All is a VIEW over every order. No status can ever be placed
        //    there; each row simply renders the actions of the tab it
        //    really belongs to (see $rowBucket below).
        //  - To Ship is a HEADING: its count is its two sub-tabs added
        //    together, and it renders a SUB-segment bar (To Pack / To
        //    Handover) rather than being a single flat list.
        //
        // Unpaid sits first among the real placements (owner directive -
        // gateways auto-advance paid orders past it, so it's where
        // manual/reseller orders wait), and Failed sits before Delivered.
        // Lost & Damaged is the insurance-claim worklist: neither courier
        // exposes a claims API, so it is where a human chases the money.
        // Returns is deliberately NOT a tab - it is a sibling page (the
        // aside link), never a ninth filter (the ERP rule this grammar is
        // copied from).
        $phaseTabs = [
            'all'          => 'All',
            'unpaid'       => 'Unpaid',
            'to_ship'      => 'To Ship',
            'shipping'     => 'Shipping',
            'failed'       => 'Failed',
            'lost_damaged' => 'Lost & Damaged',
            'delivered'    => 'Delivered',
            'cancelled'    => 'Cancelled',
        ];

        // To Ship's own two sub-tabs — default sub is To Pack (where
        // fulfillment starts). Rendered only while the To Ship tab itself
        // is active (see the sub-segment bar block below).
        $subTabs = [
            'to_pack'     => 'To Pack',
            'to_handover' => 'To Handover',
        ];

        // The couriers the booking modal actually ASKS about coverage when
        // it opens: configured ones only, since a courier with no
        // credentials cannot be asked and its card already says so. Named
        // here (in the file's FIRST @php block, per the house rule that a
        // block must precede any inline @php in the same file) so the
        // modal's waiting line can say who is being waited on.
        $checkedCourierNames = [];
        foreach ($courierOptions ?? [] as $courierOption) {
            if (!empty($courierOption['configured'])) {
                $checkedCourierNames[] = $courierOption['name'];
            }
        }

        // Which couriers carry artwork of their own, bound to a plate in
        // fulfillment.css. A courier we have no logo for takes the drawn
        // mark instead, so a fourth column joining this row later arrives
        // as a card shaped like its neighbours rather than as a blank
        // plate waiting on a file.
        $markFor = function (string $courierKey) {
            return in_array($courierKey, ['quadx', 'spx'], true) ? $courierKey : null;
        };
    @endphp

    <div x-data="fulfillment()">

        @if (session('label_url'))
            {{-- Owner directive (2026-08-08, "AWB opens on a new tab after
                 booking"): FulfillmentController::book()'s SYNC success path
                 (tracking number already known) flashes 'label_url' alongside
                 the toast's own 'success' message (see toasts.blade.php).
                 x-init fires the same window.open() a manual "Print waybill"
                 click would trigger - a popup blocker can silently swallow
                 that, so this banner ALSO renders a real target="_blank"
                 "Open AWB" anchor as the fallback that always works. The
                 async 'creating' path never sets this flash key, so this
                 banner never renders for a still-pending booking. --}}
            <div class="v-notice v-notice--good v-between v-gap-sm" x-init="window.open(@js(session('label_url')), '_blank')">
                <span class="v-notice__text">Booked. The AWB should have opened in a new tab.</span>
                <a href="{{ session('label_url') }}" target="_blank" rel="noopener" class="v-btn v-btn--secondary v-btn--sm">Open AWB</a>
            </div>
        @endif

        @if (empty($couriers))
            {{-- The queue cannot book anything without a courier, so the
                 shared blocking-setup state says so and carries its own way
                 out. --}}
            <div class="v-notice v-between v-gap-sm">
                <span class="v-notice__title">No courier is enabled yet</span>
                <a href="{{ route('admin.fulfillment.settings') }}" class="v-btn v-btn--secondary v-btn--sm">Configure a courier</a>
            </div>
        @endif

        {{-- The eight tabs, each with a mono count. Returns is a sibling
             page on the far side, not a ninth tab. To Ship's link always
             lands on its DEFAULT sub-tab (To Pack); the sub bar below is
             what lets the operator move to To Handover from there. --}}
        <div class="v-tabs v-tabs--wrap" role="tablist" aria-label="Fulfillment phase">
            @foreach ($phaseTabs as $key => $label)
                <a class="v-tab" role="tab" aria-selected="{{ $filter === $key ? 'true' : 'false' }}"
                   href="{{ route('admin.fulfillment.index', array_filter(['filter' => $key, 'sub' => $key === 'to_ship' ? 'to_pack' : null, 'q' => request('q'), 'per_page' => request('per_page')], fn ($v) => $v !== null && $v !== '')) }}">
                    {{ $label }}<span class="v-tab__count" data-count-for="{{ $key }}">{{ number_format($phaseCounts[$key] ?? 0) }}</span>
                </a>
            @endforeach
            @if (Route::has('admin.fulfillment.returns'))
                <a class="v-tabs__aside" href="{{ route('admin.fulfillment.returns') }}">
                    Returns
                    <x-admin.icon name="chevron" />
                </a>
            @endif
        </div>

        {{-- Sub bar: To Ship's own To Pack / To Handover split, nested
             under the tab above it, never a second bar of equal weight.
             Renders ONLY under To Ship; $sub is ignored (and this bar never
             renders) on every other tab. --}}
        @if ($filter === 'to_ship')
            <div class="v-tabs v-tabs--sub" role="tablist" aria-label="To Ship stage">
                @foreach ($subTabs as $key => $label)
                    <a class="v-tab" role="tab" aria-selected="{{ $sub === $key ? 'true' : 'false' }}"
                       href="{{ route('admin.fulfillment.index', array_filter(['filter' => 'to_ship', 'sub' => $key, 'q' => request('q'), 'per_page' => request('per_page')], fn ($v) => $v !== null && $v !== '')) }}">
                        {{ $label }}<span class="v-tab__count" data-count-for="{{ $key }}">{{ number_format($phaseCounts[$key] ?? 0) }}</span>
                    </a>
                @endforeach
            </div>
        @endif

        <div class="v-settle {{ $filter === 'to_ship' ? 'v-gap' : '' }}">
            {{-- Courier tally — the handover step is where the operator counts
                 parcels per courier before each pickup arrives. Lives on To
                 Ship's To Handover sub-tab, and only there. --}}
            @if ($filter === 'to_ship' && $sub === 'to_handover' && !empty($handoverByCourier))
                <div class="v-tally">
                    <span class="v-tally__label">Parcels waiting for handover</span>
                    @foreach ($handoverByCourier as $courierName => $count)
                        <span class="v-tally__item">
                            <span class="v-tally__count">{{ number_format($count) }}</span>
                            {{ $courierName }}
                        </span>
                    @endforeach
                </div>
            @endif

            <form method="GET" action="{{ route('admin.fulfillment.index') }}" class="v-filterbar">
                <input type="hidden" name="filter" value="{{ $filter }}">
                @if ($filter === 'to_ship')
                    <input type="hidden" name="sub" value="{{ $sub }}">
                @endif
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Order #, tracking, customer…"
                       aria-label="Search orders" class="v-control v-filterbar__grow">
                <select name="per_page" aria-label="Orders per page" class="v-control v-w-name" onchange="this.form.submit()">
                    @foreach ([10, 25, 50] as $n)
                        <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }} per page</option>
                    @endforeach
                </select>
                <button type="submit" class="v-btn v-btn--secondary">Search</button>
                @if (request('q'))
                    <a class="v-link" href="{{ route('admin.fulfillment.index', array_filter(['filter' => $filter, 'sub' => $filter === 'to_ship' ? $sub : null, 'per_page' => $perPage], fn ($v) => $v !== null && $v !== '')) }}">Reset</a>
                @endif
            </form>

            {{-- Two-tier table: a band of order-level facts above one row per
                 item; total / courier / status / actions rowspanned beside them.
                 Verification density, because a packer looks at the picture. --}}
            <x-admin.panel flush>
                <div class="v-tablewrap">
                    <table class="v-table">
                        <thead>
                            <tr>
                                <th class="v-w-product">Products</th>
                                <th class="v-cell-num">Qty</th>
                                <th class="v-cell-num">Total</th>
                                <th class="v-w-courier">Courier</th>
                                <th class="v-w-status">Status</th>
                                <th class="v-cell-act"><span class="v-sr">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                    @forelse ($orders as $order)
                        @php
                            $meta = $order->courier_meta ? (json_decode($order->courier_meta, true) ?: []) : [];

                            // Tracking link extraction lives behind the interface — each courier
                            // owns its own courier_meta shape (CourierFulfillmentInterface::trackingLinks()).
                            // We just loop the result and take the first usable one.
                            $orderCourier = $order->courier_provider ? \Extensions\CourierFulfillment\CourierManager::get($order->courier_provider) : null;

                            // Phase and cancelability are derived from THIS row's own resolved
                            // courier, never $primary — a mixed-courier queue must not apply one
                            // courier's cancel rules/stepper to another courier's order. An
                            // unregistered provider degrades to the neutral phase and non-cancelable.
                            //
                            // Task T4: a 'creating' order (async courier, no
                            // tracking number yet — see FulfillmentService::
                            // phaseFor()'s docblock) must resolve through the
                            // courier's own statusPhase() same as any booked
                            // order, not fall into the default zero-step
                            // stepper just because the tracking number hasn't
                            // landed yet.
                            $creating = (string) $order->courier_status === 'creating';
                            // Final-review fix (Finding 2): 'creation_failed' (the async
                            // create's permanent-failure sibling) resolves through the
                            // courier's statusPhase() the same way — its stepper shows
                            // the failure at step 1 instead of a blank zero-step row.
                            $creationFailed = (string) $order->courier_status === 'creation_failed';
                            $phase = ($order->courier_tracking_number || $creating || $creationFailed)
                                ? ($orderCourier?->statusPhase($order->courier_status) ?? ['step' => 0, 'failed' => false])
                                : ['step' => 0, 'failed' => false];

                            // Does this row carry courier data at all? Everything
                            // courier-shaped on the row (its badge, its actions)
                            // hangs off this: an order that was never booked has
                            // no courier state to report, and the fulfillment
                            // page must not invent one for it. This is what keeps
                            // the 24,620 OpenCart-era Delivered orders from
                            // showing a "To Pack" courier badge they never earned.
                            $hasCourierData = (bool) ($order->courier_tracking_number || $order->courier_provider);

                            // A parcel leaving by a courier we do not integrate
                            // with (FulfillmentService::isManuallyShipped()).
                            // Deliberately NOT courier data in the sense above:
                            // there is no provider, no courier status and no
                            // waybill, so the row keeps reporting its CORE
                            // ORDER STATUS, which is the only thing moving it.
                            $manuallyShipped = \Extensions\CourierFulfillment\FulfillmentService::isManuallyShipped($order);
                            // The order's own snapshot first, so a renamed or
                            // deleted courier never rewrites what an old order
                            // says about itself.
                            $manualCourierName = $manuallyShipped
                                ? ($order->shipping_courier_name ?: ($order->shippingCourier?->name ?: 'Courier'))
                                : null;
                            // Stored at recording time from the courier's own
                            // {tracking_number} template; the accessor derives
                            // it for the pre-snapshot orders.
                            $manualTrackingUrl = $manuallyShipped ? $order->tracking_link : null;

                            // The row's spine bucket + tone: the one shared colour system every
                            // courier's statuses collapse onto (Support\PhaseTone). A SHIPMENT
                            // fact, never a tab fact (see FulfillmentService::tabBucket()).
                            $spinePhase = \Extensions\CourierFulfillment\FulfillmentService::phaseFor($order);
                            $spineTone  = \Extensions\CourierFulfillment\Support\PhaseTone::toneFor($spinePhase);
                            // Display-only: the spine VALUE stays courier-side
                            // vocabulary ('in_transit'), the chip reads the
                            // queue's own word for it ("Shipping").
                            $spineLabel = \Extensions\CourierFulfillment\Support\PhaseTone::labelFor($spinePhase);

                            $trackingUrl = null;
                            if ($orderCourier) {
                                foreach ($orderCourier->trackingLinks($order) as $link) {
                                    if (!empty($link['url'])) {
                                        $trackingUrl = $link['url'];
                                        break;
                                    }
                                }
                            }

                            $estimatedDelivery = $meta['booked_response']['estimated_delivery_date'] ?? null;
                            // Task T4: fee display — two DISTINCT figures,
                            // never conflated. estimatedFee comes from T2's
                            // create-response (courier_meta.estimated_shipping_fee,
                            // written once at booking time); actualFee comes
                            // from T2's fix-round persistence of 2.2's
                            // batch_get_asf (courier_meta.actual_fee.actual_shipping_fee,
                            // refreshed on every courier:poll-status cycle).
                            // Both are courier-agnostic reads — only SPX
                            // writes either key today, so this renders
                            // nothing for any other courier.
                            $estimatedFeeRaw = $meta['estimated_shipping_fee'] ?? null;
                            $actualFeeRaw = $meta['actual_fee']['actual_shipping_fee'] ?? null;
                            // Final-review fix (Finding 2): the courier's permanent
                            // async-create failure reason (written by
                            // resolvePendingCreation() into courier_meta.creation_error)
                            // — rendered in the status cell + drawer, never left invisible.
                            $creationError = $creationFailed ? ($meta['creation_error'] ?? null) : null;
                            // Canceled bookings are not terminal — a tracking number is still
                            // present but the order is rebookable (see FulfillmentService).
                            $activelyBooked = \Extensions\CourierFulfillment\FulfillmentService::isActivelyBooked($order);
                            $rebookable = (bool) $order->courier_tracking_number && !$activelyBooked;
                            // A null/unknown courier_status is always cancelable (fresh booking) —
                            // otherwise it must be one of the courier's own cancelable statuses.
                            // The $orderCourier guard also covers a booked row whose provider is no
                            // longer registered: it stays non-cancelable instead of erroring.
                            $cancelable = $activelyBooked && $orderCourier
                                && (!$order->courier_status || in_array($order->courier_status, $orderCourier->cancelableStatuses(), true));

                            // The row's tab: its core status placement, nothing
                            // else (FulfillmentService::tabBucket()). It is the
                            // only input to the ORDER actions below, which are
                            // hardcoded per tab exactly as the owner's
                            // 2026-08-09 rulings state:
                            //
                            //   Book           To Pack only
                            //   Cancel order   Unpaid and To Pack
                            //   Cancel booking To Handover only
                            //
                            // The two COURIER actions key on courier data, not
                            // on the tab (owner ruling, fix round): a parcel
                            // carries its own tracking and its own waybill
                            // wherever its core status happens to sit.
                            //
                            //   Tracking       any tab, whenever the row has
                            //                  courier data
                            //   Print waybill  any tab, whenever the booking
                            //                  is LIVE (isActivelyBooked())
                            //
                            // The original "every tab except Unpaid" rule was
                            // justified by "since it is not booked yet", and
                            // that justification is what governs: a normal
                            // unpaid order has no parcel, so keying on the
                            // data renders nothing there anyway. Keying on the
                            // TAB instead was what left an Unpaid-placed order
                            // holding a live AWB with zero actions - no
                            // waybill to reprint, no tracking to open, and
                            // (correctly) no Book either, since booking stays
                            // a To Pack action.
                            //
                            // Deriving the bucket PER ROW (rather than from
                            // the active tab) is also what makes the All tab
                            // work: its rows are drawn from every placement,
                            // and each one carries the buttons of the tab it
                            // really belongs to.
                            //
                            // One rule applies over all of it, and only ever
                            // SUBTRACTS: no action renders that the server
                            // would refuse.
                            $rowBucket = \Extensions\CourierFulfillment\FulfillmentService::tabBucket($order);

                            // Book: To Pack only, and only when the server
                            // would accept it - bookingBlockedReason() is the
                            // same single rule FulfillmentService::book() and
                            // the estimate endpoint enforce, so a rendered
                            // Book button can never be refused for placement,
                            // and the two idempotency guards (active booking,
                            // async create in flight) subtract the rest. An
                            // order with no courier configured at all has
                            // nothing to book with.
                            //
                            // A recorded manual shipment subtracts Book for the
                            // same reason an active booking does: the parcel has
                            // already left, and bookManual()/book() both refuse
                            // it server-side until the record is cleared.
                            $canBook = \Extensions\CourierFulfillment\FulfillmentService::bookingBlockedReason($order) === null
                                && !$activelyBooked
                                && !$creating
                                && !$manuallyShipped
                                && (!empty($courierOptions) || !empty($manualCouriers));

                            // Clear shipment: the undo for a recorded manual
                            // shipment. Offered across the two To Ship steps -
                            // To Handover, which is where recording moved the
                            // order, and To Pack, which is where clearing
                            // returns it, so an admin who moved the status by
                            // hand in between is never left with a courier
                            // record and no way to undo it. Past those the
                            // parcel is in transit or done and the order page's
                            // own tracking fields are where it is corrected -
                            // which is what clearManual() says when refusing.
                            // Same set, same order of reasoning, server-side.
                            $offersClearManual = $manuallyShipped && in_array($rowBucket, [
                                \Extensions\CourierFulfillment\FulfillmentService::UNBOOKED_PLACEMENT,
                                \Extensions\CourierFulfillment\FulfillmentService::BOOKED_PLACEMENT,
                            ], true);

                            // Cancel order: Unpaid and To Pack rows, minus the
                            // rows cancelOrder() itself refuses (an active
                            // booking, or an async create in flight - cancel
                            // the booking first).
                            $offersCancelOrder = in_array($rowBucket, ['unpaid', 'to_pack'], true)
                                && !$activelyBooked
                                && !$creating;

                            // Tracking / Creating / Details: one drawer opener,
                            // for any row that carries courier state worth
                            // opening, in ANY tab. Courier data is the whole
                            // gate - a row with no courier data has nothing to
                            // show, which is why an ordinary Unpaid order still
                            // renders no opener while an Unpaid-placed order
                            // holding a real parcel does. Includes an async
                            // create still in flight (provider set, tracking
                            // number not landed yet), whose drawer is the only
                            // place the "Creating..." state is explained.
                            $offersDrawer = $hasCourierData;

                            // Print waybill: any tab, whenever the row carries
                            // a LIVE parcel. "Live" is the never-render-a-
                            // refusal rule doing its job: a cancelled booking
                            // has no printable AWB and the couriers refuse it
                            // server-side (SpxFulfillment::printLabel() returns
                            // "Cancelled shipments have no printable waybill",
                            // even when a PDF is already cached, because that
                            // AWB is void). isActivelyBooked() is the exact
                            // server predicate, and it is phase-first, so a
                            // courier-vocabulary cancel (SPX '7001') reads the
                            // same as the literal 'canceled' the admin cancel
                            // writes.
                            $offersWaybill = $activelyBooked;

                            // Cancel booking: To Handover, while the courier
                            // still allows cancelling this shipment.
                            $offersCancelBooking = $rowBucket === 'to_handover' && $cancelable;

                            $timelineRaw = $meta['timeline'] ?? [];
                            $timeline = [];
                            if (is_array($timelineRaw)) {
                                foreach ($timelineRaw as $event) {
                                    if (!is_array($event) || !isset($event['status'], $event['at']) || !is_int($event['at'])) {
                                        continue;
                                    }
                                    $timeline[] = [
                                        'label' => $humanizeStatus((string) $event['status'], $order->courier_provider),
                                        'at' => \Carbon\Carbon::createFromTimestamp($event['at'])->timezone(config('app.timezone'))->format('M j, Y g:ia'),
                                    ];
                                }
                            }

                            // Buyer payment is derived from the order, never chosen by the
                            // admin — prevents mis-shipping a COD order as prepaid.
                            $isCod = \Extensions\CourierFulfillment\FulfillmentService::isCodOrder($order);
                            $buyerPaymentLabel = $isCod
                                ? 'COD: rider collects ₱' . number_format($order->total, 2)
                                : 'Prepaid, no collection';

                            $rowData = [
                                'id' => $order->id,
                                'date' => optional($order->created_at)->format('M d, Y g:ia'),
                                'customerName' => trim(($order->shipping_first_name ?? '') . ' ' . ($order->shipping_last_name ?? '')) ?: '-',
                                'address' => trim(collect([$order->shipping_address_1, $order->shipping_city, $order->shipping_state])->filter()->implode(', ')) ?: '-',
                                // The place a courier is being asked about,
                                // in the customer's own words: barangay,
                                // city, province. Named in the booking
                                // modal's waiting line and in its refusal
                                // sentences, so an operator reads WHERE the
                                // answer is about and not just which courier
                                // gave it.
                                'destination' => trim(collect([$order->shipping_barangay, $order->shipping_city, $order->shipping_state])->filter()->implode(', ')) ?: 'this address',
                                // Named on its own when a courier's refusal is
                                // about the postcode specifically, which is a
                                // ten-second fix once somebody says so.
                                'postcode' => $order->shipping_postcode,
                                'statusName' => $order->status?->name ?? 'Missing',
                                // The two checkout messages: the store note is
                                // packing context, the courier note is what the
                                // booking hands the rider (see the couriers'
                                // payload builders reading courier_comment).
                                'storeNote' => $order->comment,
                                'courierNote' => $order->courier_comment,
                                'itemsCount' => $order->products_count ?? 0,
                                'activelyBooked' => $activelyBooked,
                                'rebookable' => $rebookable,
                                'cancelable' => $cancelable,
                                // The drawer is a VIEWING surface opened from a
                                // row, so it offers exactly what that row's tab
                                // offers and never more: these three flags are
                                // the same ones the row's own buttons render
                                // from, passed through rather than re-derived
                                // client-side.
                                'canBook' => $canBook,
                                'canPrintWaybill' => $offersWaybill,
                                'canCancelBooking' => $offersCancelBooking,
                                'trackingNumber' => $order->courier_tracking_number,
                                'provider' => strtoupper((string) $order->courier_provider),
                                'courierStatus' => $order->courier_status,
                                'trackingUrl' => $trackingUrl,
                                'estimatedDelivery' => $estimatedDelivery,
                                'creating' => $creating,
                                'creationFailed' => $creationFailed,
                                'creationError' => $creationError,
                                'estimatedFee' => is_numeric($estimatedFeeRaw) ? ('₱' . number_format((float) $estimatedFeeRaw, 2)) : null,
                                'actualFee' => is_numeric($actualFeeRaw) ? ('₱' . number_format((float) $actualFeeRaw, 2)) : null,
                                'phaseStep' => $phase['step'],
                                'phaseFailed' => $phase['failed'],
                                'phase' => $spinePhase,
                                'phaseTone' => $spineTone,
                                'phaseLabel' => $spineLabel,
                                'timeline' => $timeline,
                                'buyerPaymentLabel' => $buyerPaymentLabel,
                                // Where this parcel actually left from, snapshotted onto
                                // courier_meta at booking time (FulfillmentService::book()),
                                // so it stays true after the address is renamed or deleted.
                                'shipsFrom' => isset($meta['pickup_address']['label'])
                                    ? trim($meta['pickup_address']['label'] . ' - ' . ($meta['pickup_address']['summary'] ?? ''), ' -')
                                    : null,
                            ];

                            // Legacy shape: an order with no recorded items still renders one
                            // empty item row under its band, rather than a band with nothing
                            // attached to it.
                            $items = $order->products;
                            $displayItems = $items->isNotEmpty() ? $items->all() : [null];
                            $lineCount = count($displayItems);
                        @endphp
                        <tr class="v-tier">
                            <td colspan="6">
                                <div class="v-tierrow">
                                    <span class="v-tierrow__side">
                                        <span class="v-tierlabel">Customer</span>
                                        <span>{{ trim(($order->shipping_first_name ?? '') . ' ' . ($order->shipping_last_name ?? '')) ?: '-' }}</span>
                                        <span class="v-tierlabel">Order</span>
                                        <a class="v-rowlink v-mono" href="{{ route('admin.orders.show', $order) }}">#{{ $order->id }}</a>
                                        <span class="v-cell-quiet">({{ $order->products_count ?? 0 }} {{ ($order->products_count ?? 0) === 1 ? 'item' : 'items' }})</span>
                                    </span>
                                    <span class="v-tierrow__side">
                                        <span class="v-tierlabel">Created</span>
                                        <span class="v-mono">{{ optional($order->created_at)->format('M d, Y g:ia') }}</span>
                                    </span>
                                </div>
                            </td>
                        </tr>
                        @foreach ($displayItems as $idx => $item)
                            <tr>
                                <td>
                                    @if ($item)
                                        @php($itemImage = $item->image ?? $item->product?->image)
                                        {{-- The SKU captured on the line already resolves to the
                                             variant's own code when the buyer picked one, so one
                                             field answers "which exact thing" for both cases. --}}
                                        @php($itemSku = trim((string) $item->sku))
                                        @php($itemVariant = trim((string) $item->variant_label))
                                        <span class="v-namerow v-fulfilitem">
                                            @if ($itemImage)
                                                <img src="{{ storage_url($itemImage) }}" alt="" class="v-thumb">
                                            @else
                                                <span class="v-thumb v-thumb--blank"><x-admin.icon name="products" /></span>
                                            @endif
                                            <span>
                                                {{ $item->name ?: 'Unnamed product' }}
                                                {{-- Identifier first, then the variation, each named
                                                     rather than left to be inferred from its
                                                     typeface. The rule between them only appears
                                                     when there are two facts to separate. --}}
                                                @if ($itemSku !== '' || $itemVariant !== '')
                                                    <span class="v-fulfilitem__meta">
                                                        @if ($itemSku !== '')
                                                            <span class="v-metafact">
                                                                <span class="v-metakey">SKU:</span>
                                                                <span class="v-mono v-cell-quiet">{{ $itemSku }}</span>
                                                            </span>
                                                        @endif
                                                        @if ($itemSku !== '' && $itemVariant !== '')
                                                            <span class="v-metasep" aria-hidden="true">|</span>
                                                        @endif
                                                        @if ($itemVariant !== '')
                                                            <span class="v-metafact">
                                                                <span class="v-metakey">Variant:</span>
                                                                <span class="v-variant">{{ $itemVariant }}</span>
                                                            </span>
                                                        @endif
                                                    </span>
                                                @endif
                                            </span>
                                        </span>
                                    @else
                                        <span class="v-cell-quiet">No items recorded</span>
                                    @endif
                                </td>
                                <td class="v-cell-num v-mono">
                                    @if ($item)
                                        &times;{{ max(1, (int) $item->quantity) }}
                                    @else
                                        <span class="v-cell-quiet">-</span>
                                    @endif
                                </td>
                                @if ($idx === 0)
                                    <td class="v-cell-num" rowspan="{{ $lineCount }}">
                                        <span class="v-mono">&#8369;{{ number_format($order->total, 2) }}</span>
                                        <span class="v-cell-quiet v-block">{{ $isCod ? 'COD' : ucwords(str_replace('_', ' ', (string) $order->payment_method)) }}</span>
                                    </td>
                                    <td class="v-w-courier" rowspan="{{ $lineCount }}">
                                        @if ($order->courier_tracking_number)
                                            <span class="v-lead">{{ $orderCourier?->getName() ?? strtoupper((string) $order->courier_provider) }}</span>
                                            @if ($trackingUrl)
                                                <a class="v-link v-mono" href="{{ $trackingUrl }}" target="_blank" rel="noopener">{{ $order->courier_tracking_number }}</a>
                                            @else
                                                <span class="v-cell-quiet v-mono">{{ $order->courier_tracking_number }}</span>
                                            @endif
                                        @elseif ($manuallyShipped)
                                            {{-- A parcel we arranged ourselves. The courier
                                                 is named as plainly as an integrated one, with
                                                 a quiet marker beside it so nobody goes hunting
                                                 for a waybill that was never printed. The
                                                 tracking number is optional here, so the cell
                                                 stops after the name when there isn't one. --}}
                                            <span class="v-lead">{{ $manualCourierName }}</span>
                                            <span class="v-factchip">Manual</span>
                                            @if ($order->tracking_number)
                                                @if ($manualTrackingUrl)
                                                    <a class="v-link v-mono v-block" href="{{ $manualTrackingUrl }}" target="_blank" rel="noopener">{{ $order->tracking_number }}</a>
                                                @else
                                                    <span class="v-cell-quiet v-mono v-block">{{ $order->tracking_number }}</span>
                                                @endif
                                            @endif
                                        @else
                                            <span class="v-cell-quiet">-</span>
                                        @endif
                                    </td>
                                    <td class="v-w-status" rowspan="{{ $lineCount }}">
                                        <div class="v-cellstack">
                                            @if ($hasCourierData)
                                                {{-- A booked row reports its SHIPMENT's spine
                                                     phase, in that phase's shared tone. --}}
                                                <span class="v-badge v-badge--{{ $badgeTone($spineTone) }}">{{ $spineLabel }}</span>
                                            @else
                                                {{-- No courier ever touched this order, so there
                                                     is no courier state to report: its core order
                                                     status is the honest answer (and the only
                                                     thing that decided which tab it is in). --}}
                                                <span class="v-badge v-badge--quiet">{{ $order->status?->name ?? 'Missing' }}</span>
                                            @endif
                                            @if ($order->courier_status)
                                                <span class="v-cellstack__note">{{ $humanizeStatus((string) $order->courier_status, $order->courier_provider) }}</span>
                                            @endif
                                            @if ($creationError)
                                                <span class="v-cellstack__error" title="{{ $creationError }}">{{ \Illuminate\Support\Str::limit($creationError, 90) }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="v-cell-act" rowspan="{{ $lineCount }}">
                                        <div class="v-actstack">
                                            {{-- The action table. Read the $rowBucket block in this
                                                 row's @php above for the table itself and for what
                                                 each gate subtracts: the ORDER actions (Book, Cancel
                                                 order, Cancel booking) are keyed on the row's tab and
                                                 nothing else, the COURIER actions (Print waybill,
                                                 Tracking) are keyed on the row's courier data and
                                                 nothing else. --}}
                                            @if ($canBook)
                                                {{-- Component-tag attributes only compile {{ }} echoes, not
                                                     @-directives — Js::from() is what @js() expands to. --}}
                                                <button type="button" class="v-btn v-btn--primary v-btn--sm" @click="openBooking({{ \Illuminate\Support\Js::from($rowData) }})">Book</button>
                                            @endif
                                            @if ($offersWaybill)
                                                <a href="{{ route('admin.fulfillment.label', $order) }}" class="v-btn v-btn--secondary v-btn--sm" target="_blank" rel="noopener">Print waybill</a>
                                            @endif
                                            @if ($offersDrawer)
                                                {{-- One opener, three honest labels: a booking still
                                                     in flight says so, a failed async create opens on
                                                     its error, anything with a tracking number opens
                                                     on its timeline. --}}
                                                @if ($creating)
                                                    <button type="button" class="v-btn v-btn--secondary v-btn--sm" @click="openDrawer({{ \Illuminate\Support\Js::from($rowData) }})">Creating&hellip;</button>
                                                @elseif ($creationFailed && !$order->courier_tracking_number)
                                                    <button type="button" class="v-btn v-btn--secondary v-btn--sm" @click="openDrawer({{ \Illuminate\Support\Js::from($rowData) }})">Details</button>
                                                @else
                                                    <button type="button" class="v-btn v-btn--secondary v-btn--sm" @click="openDrawer({{ \Illuminate\Support\Js::from($rowData) }})">Tracking</button>
                                                @endif
                                            @endif
                                            @if ($offersCancelBooking)
                                                <button type="button" class="v-btn v-btn--danger v-btn--sm" @click="$dispatch('v-confirm', 'cancel-fulfillment-{{ $order->id }}')">Cancel booking</button>
                                            @endif
                                            @if ($offersCancelOrder)
                                                <button type="button" class="v-btn v-btn--danger v-btn--sm" @click="$dispatch('v-confirm', 'cancel-order-{{ $order->id }}')">Cancel order</button>
                                            @endif
                                            @if ($offersClearManual)
                                                <button type="button" class="v-btn v-btn--danger v-btn--sm" @click="$dispatch('v-confirm', 'clear-manual-{{ $order->id }}')">Clear shipment</button>
                                            @endif
                                        </div>
                                        @if ($offersCancelOrder)
                                            <x-admin.confirm name="cancel-order-{{ $order->id }}" title="Cancel order #{{ $order->id }}?"
                                                message="This sets the order status to your configured cancel status (returning any reserved stock) and moves it to the Cancelled tab. No courier is contacted."
                                                confirm="Cancel order" :action="route('admin.fulfillment.cancel-order', $order)" method="POST" />
                                        @endif
                                        @if ($offersCancelBooking)
                                            <x-admin.confirm name="cancel-fulfillment-{{ $order->id }}" title="Cancel booking for order #{{ $order->id }}?"
                                                message="This cancels the courier booking. The order returns to To Pack for rebooking."
                                                confirm="Cancel booking" :action="route('admin.fulfillment.cancel', $order)" method="POST" />
                                        @endif
                                        @if ($offersClearManual)
                                            <x-admin.confirm name="clear-manual-{{ $order->id }}" title="Clear the recorded shipment for order #{{ $order->id }}?"
                                                message="This forgets the courier, tracking number and tracking link, and returns the order to To Pack. No courier is contacted."
                                                confirm="Clear shipment" :action="route('admin.fulfillment.clear-manual', $order)" method="POST" />
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="v-empty">
                                    <p class="v-empty__title">No orders in this view</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.panel>

            <x-admin.pager :paginator="$orders" />
        </div>

        {{-- Detail drawer — the VIEWING surface (stepper, timeline, tracking,
             cancel). Booking happens in the modal below, never here. --}}
        <div x-show="drawerOpen" x-cloak class="v-drawerwrap" @keydown.escape.window="closeDrawer()">
            <div class="v-drawerwrap__scrim" x-show="drawerOpen" x-transition.opacity @click="closeDrawer()"></div>

            <div class="v-drawer" x-show="drawerOpen" x-transition.opacity tabindex="-1"
                 role="dialog" aria-modal="true" :aria-label="'Order #' + (active ? active.id : '') + ' fulfillment'"
                 @click.stop>
                <template x-if="active">
                    <div class="v-drawer__grow">
                        <div class="v-drawer__head">
                            <div>
                                <h3 class="v-drawer__title">Order <span x-text="'#' + active.id"></span></h3>
                                <p class="v-drawer__when" x-text="active.date"></p>
                            </div>
                            <button type="button" class="v-drawer__x" @click="closeDrawer()" aria-label="Close">
                                <x-admin.icon name="x" />
                            </button>
                        </div>

                        {{-- Big stepper (happy path) + terminal phase badge.
                             failed / cancelled / returning are terminal BADGE
                             states on this stepper, not steps of their own. --}}
                        <div class="v-drawer__sec">
                            <div class="v-steps">
                                @foreach (['Pending', 'Booked', 'Shipped', 'Delivered'] as $i => $label)
                                    <div class="v-steps__stop">
                                        <div class="v-steps__dot"
                                             :class="{{ $i }} <= active.phaseStep ? (active.phaseFailed && {{ $i }} === active.phaseStep ? 'is-bad' : 'is-on') : ''">
                                            {{ $i + 1 }}
                                        </div>
                                        <span class="v-steps__name">{{ $label }}</span>
                                    </div>
                                    @if ($i < 3)
                                        <div class="v-steps__link" :class="{{ $i }} < active.phaseStep && 'is-on'"></div>
                                    @endif
                                @endforeach
                            </div>
                            <template x-if="['failed', 'cancelled', 'returning'].includes(active.phase)">
                                <div class="v-gap">
                                    <span :class="toneClasses[active.phaseTone] || toneClasses.neutral" x-text="active.phaseLabel"></span>
                                </div>
                            </template>
                        </div>

                        {{-- Tracking history. Gated on the tracking number, not
                             activelyBooked, so a CANCELLED booking's timeline
                             stays viewable (review fix, W2). --}}
                        <template x-if="active.trackingNumber && active.timeline && active.timeline.length">
                            <div class="v-drawer__sec">
                                <span class="v-field__label">Tracking history</span>
                                <ul class="v-eventlist">
                                    <template x-for="(event, idx) in active.timeline" :key="idx">
                                        <li>
                                            <span x-text="event.label"></span>
                                            <span x-text="event.at"></span>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </template>

                        {{-- Summary --}}
                        <div class="v-drawer__sec">
                            <div class="v-kvs">
                                <div class="v-kv"><span>Customer</span><span x-text="active.customerName"></span></div>
                                <div class="v-kv"><span>Order status</span><span x-text="active.statusName"></span></div>
                                <div class="v-kv"><span>Items</span><span x-text="active.itemsCount + ' item(s)'"></span></div>
                                <div class="v-kv"><span>Ship to</span><span x-text="active.address"></span></div>
                                <template x-if="active.storeNote">
                                    <div class="v-kv"><span>Message to Store</span><span x-text="active.storeNote"></span></div>
                                </template>
                                <template x-if="active.courierNote">
                                    <div class="v-kv"><span>Message to Courier</span><span x-text="active.courierNote"></span></div>
                                </template>
                                {{-- Only on a booked parcel: it is a fact about the
                                     shipment, recorded when the booking was made. --}}
                                <template x-if="active.shipsFrom">
                                    <div class="v-kv"><span>Ships from</span><span x-text="active.shipsFrom"></span></div>
                                </template>
                            </div>
                        </div>

                        {{-- Tracking body / booking hand-off --}}
                        <div class="v-drawer__sec">
                            {{-- Task T4: honest "Creating…" state — an async
                                 courier's booking is mid-flight (no tracking
                                 number yet, courier_status='creating'). Never
                                 shows a "Book shipment" button here (that
                                 would just get rejected by
                                 FulfillmentService::book()'s async-in-progress
                                 guard) — this is a status message, not an
                                 action. --}}
                            <template x-if="active.creating">
                                <div class="v-well">
                                    <span>Creating shipment with <strong x-text="active.provider"></strong>&hellip;</span>
                                </div>
                            </template>
                            @if (!empty($courierOptions) || !empty($manualCouriers))
                                <template x-if="!active.activelyBooked && !active.creating">
                                    <div class="v-settle">
                                        {{-- Final-review fix (Finding 2): the async create's permanent
                                             failure reason (courier_meta.creation_error) - surfaced here,
                                             with the Book button right below it when this order can in
                                             fact be booked. `canBook` is the row's own answer from the
                                             SAME rule the server enforces
                                             (FulfillmentService::bookingBlockedReason()), so the drawer
                                             never offers a booking the queue would not, and never one
                                             that would be refused. Owner directive (2026-08-09, "no
                                             explanatory sentence whose only job is to describe why a
                                             control is absent"): an order that is not placed under To
                                             Pack simply gets no Book button and no caption for it, same
                                             as the row it was opened from. --}}
                                        <template x-if="active.creationFailed">
                                            <div class="v-notice v-notice--bad">
                                                <span class="v-notice__title">Booking failed:</span>
                                                <span class="v-notice__text" x-text="active.creationError || 'the courier could not create this shipment.'"></span>
                                            </div>
                                        </template>
                                        <template x-if="active.rebookable && active.canBook">
                                            <div class="v-well">
                                                Previous booking <span class="v-mono" x-text="active.trackingNumber"></span> was canceled. Booking again creates a new shipment.
                                            </div>
                                        </template>
                                        <template x-if="active.canBook">
                                            <div class="v-actstack">
                                                <button type="button" class="v-btn v-btn--primary" @click="closeDrawer(); openBooking(active)">Book shipment</button>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            @else
                                <template x-if="!active.activelyBooked">
                                    <div class="v-notice">
                                        <span class="v-notice__text">No courier is enabled yet.
                                            <a href="{{ route('admin.fulfillment.settings') }}" class="v-notice__act">Configure a courier</a>
                                        </span>
                                    </div>
                                </template>
                            @endif

                            <template x-if="active.activelyBooked">
                                <div class="v-settle">
                                    <div class="v-well">
                                        <div class="v-kvs">
                                            <div class="v-kv">
                                                <span>Tracking #</span>
                                                <template x-if="active.trackingUrl">
                                                    <a :href="active.trackingUrl" target="_blank" rel="noopener" class="v-link v-mono" x-text="active.trackingNumber"></a>
                                                </template>
                                                <template x-if="!active.trackingUrl">
                                                    <span class="v-mono" x-text="active.trackingNumber"></span>
                                                </template>
                                            </div>
                                            <div class="v-kv"><span>Courier</span><span x-text="active.provider"></span></div>
                                            <div class="v-kv"><span>Status</span><span x-text="active.courierStatus || '-'"></span></div>
                                            <template x-if="active.estimatedDelivery">
                                                <div class="v-kv"><span>Est. delivery</span><span x-text="active.estimatedDelivery"></span></div>
                                            </template>
                                            {{-- Task T4: two DISTINCTLY labeled figures, shown only
                                                 when present — an ESTIMATED fee (quoted at booking time)
                                                 is never confused with the ACTUAL fee (SPX's post-booking
                                                 batch_get_asf, refreshed on every poll). --}}
                                            <template x-if="active.estimatedFee">
                                                <div class="v-kv"><span>Estimated shipping fee</span><span class="v-mono" x-text="active.estimatedFee"></span></div>
                                            </template>
                                            <template x-if="active.actualFee">
                                                <div class="v-kv"><span>Actual shipping fee</span><span class="v-mono" x-text="active.actualFee"></span></div>
                                            </template>
                                            <template x-if="active.trackingUrl">
                                                <div class="v-kv"><span></span><a :href="active.trackingUrl" target="_blank" rel="noopener" class="v-link">Track shipment</a></div>
                                            </template>
                                        </div>
                                    </div>

                                    <div class="v-actstack">
                                        <template x-if="active.canPrintWaybill">
                                            <a :href="labelUrl" target="_blank" rel="noopener" class="v-btn v-btn--secondary">Print AWB</a>
                                        </template>
                                        <form :action="trackAction" method="POST">
                                            @csrf
                                            <button type="submit" class="v-btn v-btn--secondary">Refresh status</button>
                                        </form>
                                        <template x-if="active.canCancelBooking">
                                            <button type="button" class="v-btn v-btn--danger" @click="$dispatch('v-confirm', 'cancel-fulfillment-' + active.id)">Cancel booking</button>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Booking modal — the courier-first two-step flow (the ERP's
             arrange-shipment pattern). Step 1: choose the courier. Step 2:
             that courier's own form. Titled with the order number. Gated on
             $courierOptions (CourierManager::flagged() — flag ON,
             regardless of credential completeness), NOT $couriers
             (enabled() — flag on AND fully configured): a flag-on-but-
             unconfigured courier must still open this modal so its step-1
             card (with its "not configured" message + settings link) is
             reachable — see the step-1 markup below.

             Also opens on $manualCouriers alone: a store with no integration
             at all still ships parcels, and recording one is the whole point
             of the third choice. --}}
        @if (!empty($courierOptions) || !empty($manualCouriers))
            <div x-show="bookOpen" x-cloak class="v-overlay" @click.self="closeBooking()" @keydown.escape.window="closeBooking()">
                {{-- Two widths, one sheet: the courier step lays its options
                     out as columns and needs the room for up to four of them,
                     while the chosen courier's form is a column of fields and
                     reads worse stretched. Both widths are named in the
                     module's own stylesheet (never a class assembled in
                     Alpine), and the sheet animates between them.

                     The wide width is earned by the CARDS, not by the step:
                     while we are still asking the couriers there is one
                     sentence to show, and a 54rem sheet holding one line
                     reads as a broken layout. So the sheet opens at its
                     reading width and widens as the answers land, which is
                     what the max-width transition was always for. --}}
                <div x-show="bookOpen" x-transition.opacity
                     class="v-modal v-bookmodal"
                     :class="step === 1 && !checking ? 'v-bookmodal--couriers' : ''"
                     tabindex="-1"
                     role="dialog" aria-modal="true" :aria-label="'Book order #' + (active ? active.id : '')">
                    <template x-if="active">
                        <div>
                            <header class="v-between">
                                <h2 class="v-modal__title">Book order <span class="v-mono" x-text="'#' + active.id"></span></h2>
                                <button type="button" class="v-drawer__x" @click="closeBooking()" aria-label="Close">
                                    <x-admin.icon name="x" />
                                </button>
                            </header>
                            <div class="v-gap">
                                {{-- Step 1: one large choice card per FLAGGED courier
                                     (CourierManager::flagged() — flag ON, regardless of
                                     credential completeness). Rendered server-side so the
                                     markup IS the list of choices. The fee-estimate slot
                                     renders only when the courier quoted one (estimateFee()
                                     non-null — QuadX never does).

                                     Owner directive (2026-08-08, "unconfigured couriers
                                     visible with configuration gate"): a courier whose
                                     ENABLED FLAG is on but isn't fully configured yet
                                     (missing credentials — $opt['configured'] is false) still
                                     gets a card here instead of silently vanishing. That card
                                     is a plain <div>, never a <button @click>, so it
                                     structurally CANNOT advance to step 2. --}}
                                <div x-show="step === 1">
                                    <p class="v-panel__note v-gap-sm">How will this order ship?</p>

                                    {{-- The wait is a real network call to every
                                         configured courier, so it is named rather than
                                         spun at: who is being asked, and about where.
                                         The cards are drawn only once every courier has
                                         answered (owner ruling, 2026-08-12). --}}
                                    <div x-show="checking" class="v-choicewait">
                                        <span class="v-choicewait__mark" aria-hidden="true"></span>
                                        <span x-text="waitLine"></span>
                                    </div>

                                    <div x-show="!checking">
                                        {{-- Every courier we could ask said no. One line,
                                             the refused cards still readable below it with
                                             their own reasons, and the manual card
                                             selectable exactly as it always is. --}}
                                        <p class="v-choicenote" x-show="allRefused" x-text="refusedLine"></p>
                                        <div class="v-choicegrid">
                                        @foreach ($courierOptions as $key => $opt)
                                            @php($optMark = $markFor($key))
                                            @if ($opt['configured'])
                                                {{-- Three cards, one per verdict, exactly
                                                     one of them shown. Written out rather
                                                     than assembled in Alpine so each state
                                                     is a real, inspectable piece of markup:
                                                     the refusal genuinely cannot advance
                                                     (it is a <div> with no handler) and the
                                                     unreachable one genuinely can (a real
                                                     <button> wired to chooseCourier). --}}
                                                <button type="button" class="v-choicecard" data-courier-card="{{ $key }}" data-courier-state="serviceable" x-show="verdict('{{ $key }}') === 'serviceable'" @click="chooseCourier('{{ $key }}')">
                                                    @if ($optMark)
                                                        <span class="v-choicecard__mark v-choicecard__mark--{{ $optMark }}" aria-hidden="true"></span>
                                                    @else
                                                        <span class="v-choicecard__mark v-choicecard__mark--drawn" aria-hidden="true"><x-admin.icon name="package" /></span>
                                                    @endif
                                                    <span class="v-choicecard__name">{{ $opt['name'] }}</span>
                                                    @if (!empty($opt['mode']) && $opt['mode'] !== 'production')
                                                        <span class="v-choicecard__tags"><span class="v-choicecard__tag">{{ ucfirst($opt['mode']) }}</span></span>
                                                    @endif
                                                    {{-- The cheapest parcel this courier quoted for
                                                         this address. A from-price, because which
                                                         parcel goes in the box is still step 2's
                                                         question. --}}
                                                    <span class="v-choicecard__fee" x-text="fromPrice('{{ $key }}')"></span>
                                                </button>

                                                {{-- Could not check. A timeout, a dead DNS
                                                     record, a failure on the courier's own
                                                     side, or a complaint about the parcel we
                                                     guessed is NOT a refusal of the address,
                                                     so this card stays clickable and says only
                                                     what is true: we could not ask. --}}
                                                <button type="button" class="v-choicecard v-choicecard--caution" data-courier-card="{{ $key }}" data-courier-state="unknown" x-show="verdict('{{ $key }}') === 'unknown'" @click="chooseCourier('{{ $key }}')">
                                                    @if ($optMark)
                                                        <span class="v-choicecard__mark v-choicecard__mark--{{ $optMark }}" aria-hidden="true"></span>
                                                    @else
                                                        <span class="v-choicecard__mark v-choicecard__mark--drawn" aria-hidden="true"><x-admin.icon name="package" /></span>
                                                    @endif
                                                    <span class="v-choicecard__name">{{ $opt['name'] }}</span>
                                                    <span class="v-choicecard__tags"><span class="v-choicecard__tag">Could not check</span></span>
                                                    <span class="v-choicecard__msg">
                                                        <span x-show="sentenceFor('{{ $key }}')" x-text="sentenceFor('{{ $key }}')"></span>
                                                        <template x-if="!sentenceFor('{{ $key }}') && reason('{{ $key }}')">
                                                            <span>{{ $opt['name'] }} said: <span x-text="reason('{{ $key }}')"></span></span>
                                                        </template>
                                                    </span>
                                                </button>

                                                {{-- Not serviceable. One sentence, ours,
                                                     naming what was actually refused. The
                                                     courier's own words appear ONLY when we
                                                     did not recognise what it said. --}}
                                                <div class="v-choicecard v-choicecard--off"
                                                    data-courier-card="{{ $key }}" data-courier-state="not_serviceable"
                                                    x-show="verdict('{{ $key }}') === 'not_serviceable'">
                                                    @if ($optMark)
                                                        <span class="v-choicecard__mark v-choicecard__mark--{{ $optMark }}" aria-hidden="true"></span>
                                                    @else
                                                        <span class="v-choicecard__mark v-choicecard__mark--drawn" aria-hidden="true"><x-admin.icon name="package" /></span>
                                                    @endif
                                                    <span class="v-choicecard__name">{{ $opt['name'] }}</span>
                                                    <span class="v-choicecard__tags"><span class="v-choicecard__tag">Not serviceable</span></span>
                                                    <span class="v-choicecard__msg">
                                                        <span x-show="sentenceFor('{{ $key }}')" x-text="sentenceFor('{{ $key }}')"></span>
                                                        <template x-if="!sentenceFor('{{ $key }}') && reason('{{ $key }}')">
                                                            <span>{{ $opt['name'] }} said: <span x-text="reason('{{ $key }}')"></span></span>
                                                        </template>
                                                    </span>
                                                </div>
                                            @else
                                                <div class="v-choicecard v-choicecard--off"
                                                    data-courier-card="{{ $key }}" data-courier-configured="false">
                                                    @if ($optMark)
                                                        <span class="v-choicecard__mark v-choicecard__mark--{{ $optMark }}" aria-hidden="true"></span>
                                                    @else
                                                        <span class="v-choicecard__mark v-choicecard__mark--drawn" aria-hidden="true"><x-admin.icon name="package" /></span>
                                                    @endif
                                                    <span class="v-choicecard__name">{{ $opt['name'] }}</span>
                                                    <span class="v-choicecard__tags"><span class="v-choicecard__tag">Not configured</span></span>
                                                    <span class="v-choicecard__msg">@if (!empty($opt['configIssues']))Missing {{ implode(' / ', $opt['configIssues']) }}. @endif Configure in <a href="{{ $opt['settingsUrl'] }}">Extensions &rarr; Fulfillment &rarr; {{ $opt['name'] }}</a>.</span>
                                                </div>
                                            @endif
                                        @endforeach
                                        {{-- The third way a parcel leaves, on the same
                                             footing as the integrated couriers rather than
                                             below them as a fallback: both integrations
                                             refuse addresses they do not serve, and for
                                             those the merchant arranges an LBC or a J&T by
                                             hand. Named for what it is; the Manual qualifier
                                             is what tells the operator what picking it means
                                             (nothing is sent, nothing comes back). --}}
                                        @if (!empty($manualCouriers))
                                            {{-- A drawn mark rather than a logo, because there
                                                 is no one courier behind this card to carry
                                                 one. --}}
                                            <button type="button" class="v-choicecard" data-courier-card="__manual" @click="chooseManual()">
                                                <span class="v-choicecard__mark v-choicecard__mark--drawn" aria-hidden="true"><x-admin.icon name="fulfillment" /></span>
                                                <span class="v-choicecard__name">Another courier</span>
                                                <span class="v-choicecard__tags"><span class="v-choicecard__tag">Manual</span></span>
                                            </button>
                                        @endif
                                        </div>
                                    </div>
                                </div>

                                {{-- Step 2: the chosen courier's form, from its option methods.
                                     For QuadX this is EXACTLY the pre-modal field set — courier,
                                     service, parcel, insure, shipping_payment — posting to the
                                     same route, so the request the server receives is unchanged. --}}
                                <div x-show="step === 2 && !manual">
                                    {{-- Task T4: x-ref (for the estimate button's FormData read)
                                         and x-model="parcel" (so the SPX-only section below can
                                         react to the custom-dims sentinel) are the ONLY two
                                         additions to this form's own markup — both inert for
                                         submission. Every field name, value vocabulary, and the
                                         action URL stay byte-identical to the pre-T4 form. --}}
                                    <form :action="bookAction" method="POST" class="v-settle" x-ref="bookForm">
                                        @csrf
                                        <input type="hidden" name="courier" :value="courier">
                                        <p class="v-panel__note">Shipping with <strong x-text="current.name"></strong></p>
                                        <template x-if="active.rebookable">
                                            <div class="v-well">
                                                Previous booking <span class="v-mono" x-text="active.trackingNumber"></span> was canceled. Booking again creates a new shipment.
                                            </div>
                                        </template>

                                        {{-- Origin first (owner ruling 2026-08-19): where the
                                             parcel leaves from is chosen before what carries it
                                             and in what, because the origin is what the service
                                             list and every quoted fee are measured from. It is
                                             courier-agnostic and always present, even when the
                                             store keeps a single address: the operator is
                                             committing to a collection point and should see
                                             which one rather than trust a silent default.
                                             The option carries the address's NAME; its street
                                             lines confirm underneath, so the list stays
                                             scannable and the choice stays checkable.
                                             @if, not x-if: whether the store has any address at
                                             all is known at render time, not per courier. --}}
                                        @if (count($pickupAddresses ?? []) > 0)
                                            <x-admin.field name="pickup_address_id" label="Ships from" required>
                                                <select name="pickup_address_id" x-model="pickupId" class="v-control">
                                                    @foreach ($pickupAddresses as $pickup)
                                                        <option value="{{ $pickup['id'] }}">{{ $pickup['label'] }}</option>
                                                    @endforeach
                                                </select>
                                            </x-admin.field>
                                            {{-- Outside the field: x-admin.field wraps its slot in
                                                 the <label>, and a summary that changes with the
                                                 selection would keep rewriting the accessible name
                                                 of the control it describes. --}}
                                            <p class="v-subnote v-cell-quiet v-gap-sm" x-text="pickupSummary"></p>
                                        @else
                                            {{-- Nothing to collect from. The booking would fall
                                                 through to a null default and be refused by the
                                                 courier, so say so here where it can be fixed. --}}
                                            <div class="v-well">
                                                No pickup address on file.
                                                <a class="v-link" href="{{ route('admin.fulfillment.pickup-addresses.index') }}">Add one</a>
                                                so couriers know where to collect.
                                            </div>
                                        @endif

                                        <div class="v-grid v-grid--2">
                                            <x-admin.field name="service" label="Service" required>
                                                <select name="service" class="v-control">
                                                    <template x-for="o in current.services" :key="o.value"><option :value="o.value" x-text="o.label"></option></template>
                                                </select>
                                            </x-admin.field>
                                            {{-- Each parcel carries the exact fee this
                                                 courier quoted for it, from the same
                                                 answer step 1's from-price came from,
                                                 so choosing the parcel IS choosing the
                                                 price. A parcel the courier did not
                                                 price keeps its plain label: no figure
                                                 is better than an invented one. --}}
                                            <x-admin.field name="parcel" label="Parcel" required>
                                                <select name="parcel" x-model="parcel" class="v-control">
                                                    <template x-for="o in current.parcels" :key="o.value"><option :value="o.value" x-text="parcelLabel(o)"></option></template>
                                                </select>
                                            </x-admin.field>
                                        </div>
                                        <label class="v-toggle">
                                            <input type="checkbox" name="insure" value="1">
                                            <span class="v-toggle__track" aria-hidden="true"></span>
                                            <span>Insure shipment (≈1% fee, declared at order total)</span>
                                        </label>
                                        <div class="v-well">
                                            <div class="v-kv"><span>Buyer pays for goods</span><span x-text="active.buyerPaymentLabel"></span></div>
                                        </div>
                                        <x-admin.field name="shipping_payment" label="You pay shipping" required>
                                            <select name="shipping_payment" class="v-control">
                                                <template x-for="o in current.payments" :key="o.value"><option :value="o.value" x-text="o.label"></option></template>
                                            </select>
                                        </x-admin.field>

                                        {{-- Task T4: SPX-only step-2 additions — rendered at all
                                             only when SPX is actually an enabled courier. Uses
                                             x-if (a real template, not x-show) — load-bearing:
                                             an x-show'd block STAYS in the DOM and its inputs
                                             STILL POST even while hidden, which would (a) corrupt
                                             QuadX's POST body the moment SPX is ALSO enabled, and
                                             (b) 422 every non-custom SPX booking (hidden empty
                                             dims would submit '' against a numeric rule). x-if
                                             removes the fields from the DOM entirely whenever
                                             this courier isn't the chosen one — the ONLY safe way
                                             to make fields conditional on a shared <form>. --}}
                                        @if (isset($courierOptions['spx']))
                                        <template x-if="courier === 'spx'">
                                        <div class="v-settle">
                                            <template x-if="parcel === 'custom'">
                                                <div class="v-grid v-grid--2">
                                                    <x-admin.field name="length_cm" label="Length (cm)">
                                                        <input name="length_cm" type="number" step="0.1" min="0.1" class="v-control">
                                                    </x-admin.field>
                                                    <x-admin.field name="width_cm" label="Width (cm)">
                                                        <input name="width_cm" type="number" step="0.1" min="0.1" class="v-control">
                                                    </x-admin.field>
                                                    <x-admin.field name="height_cm" label="Height (cm)">
                                                        <input name="height_cm" type="number" step="0.1" min="0.1" class="v-control">
                                                    </x-admin.field>
                                                    <x-admin.field name="weight_kg" label="Weight (kg)">
                                                        <input name="weight_kg" type="number" step="0.01" min="0.01" class="v-control">
                                                    </x-admin.field>
                                                </div>
                                            </template>

                                            <x-admin.field name="pickup_slot" label="Pickup date (optional)" hint="Drop-off is the default. SPX decides which dates are available for pickup.">
                                                <select name="pickup_slot" class="v-control">
                                                    <option value="">Drop off myself</option>
                                                    <template x-for="o in spxPickupSlots" :key="o.value"><option :value="o.value" x-text="o.label"></option></template>
                                                </select>
                                            </x-admin.field>
                                            <p x-show="spxPickupSlotsLoading" x-cloak class="v-subnote v-cell-quiet">Loading pickup dates&hellip;</p>

                                            <div class="v-well v-between">
                                                <div>
                                                    <div class="v-field__label">Estimated shipping fee</div>
                                                    <div class="v-mono" x-show="spxEstimate" x-text="spxEstimate ? spxEstimate.label : ''"></div>
                                                    <div class="v-subnote v-tone-bad" x-show="spxEstimateError" x-text="spxEstimateError"></div>
                                                    <div class="v-subnote v-cell-quiet" x-show="!spxEstimate && !spxEstimateError">Not checked yet.</div>
                                                </div>
                                                <button type="button" class="v-btn v-btn--secondary v-btn--sm" @click="getSpxEstimate()" :disabled="spxEstimateLoading">
                                                    <span x-show="!spxEstimateLoading">Get estimate</span><span x-show="spxEstimateLoading" x-cloak>Checking&hellip;</span>
                                                </button>
                                            </div>
                                        </div>
                                        </template>
                                        @endif

                                        <div class="v-actrow">
                                            <button type="button" class="v-btn v-btn--ghost" @click="backToCouriers()">Back</button>
                                            <button type="submit" class="v-btn v-btn--primary v-actrow__grow" :disabled="!courier">Book shipment</button>
                                        </div>
                                    </form>
                                </div>

                                {{-- Step 2, manual: three answers and nothing else,
                                     because nothing is being negotiated with anybody.
                                     A SIBLING form, never extra fields inside the
                                     courier form above — an x-show'd block stays in the
                                     DOM and its inputs still post, so sharing one form
                                     would put shipping_courier_id into every QuadX
                                     booking. Two forms cannot contaminate each other:
                                     the browser submits only the one whose button was
                                     pressed.

                                     Rendered only when the store keeps an active
                                     courier, which is the same condition that put the
                                     card on step 1. --}}
                                @if (!empty($manualCouriers))
                                    <div x-show="step === 2 && manual">
                                        <form :action="manualAction" method="POST" class="v-settle">
                                            @csrf
                                            <p class="v-panel__note">Recording a shipment for order <span class="v-mono" x-text="'#' + active.id"></span></p>

                                            <x-admin.field name="shipping_courier_id" label="Courier" required>
                                                <select name="shipping_courier_id" required class="v-control">
                                                    @foreach ($manualCouriers as $manualCourier)
                                                        <option value="{{ $manualCourier['id'] }}">{{ $manualCourier['name'] }}</option>
                                                    @endforeach
                                                </select>
                                            </x-admin.field>

                                            <x-admin.field name="tracking_number" label="Tracking number">
                                                <input name="tracking_number" autocomplete="off" maxlength="190" class="v-control">
                                            </x-admin.field>

                                            {{-- Nothing is sent to a carrier here, so the
                                                 operator says plainly what state the order
                                                 is now in. The same statuses the order page
                                                 offers, pre-selected to the one this module
                                                 would have applied on its own. --}}
                                            @if (!empty($orderStatuses) && count($orderStatuses) > 0)
                                                <x-admin.field name="order_status_id" label="Order status" required>
                                                    <select name="order_status_id" required class="v-control">
                                                        @foreach ($orderStatuses as $orderStatus)
                                                            <option value="{{ $orderStatus->id }}" @selected($orderStatus->id === $manualDefaultStatusId)>{{ $orderStatus->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </x-admin.field>
                                            @endif

                                            <x-admin.field name="note" label="Note">
                                                <textarea name="note" rows="2" maxlength="500" class="v-control"></textarea>
                                            </x-admin.field>

                                            {{-- The tip carries the one consequence the
                                                 controls cannot name themselves: no courier is
                                                 contacted, and the order still moves on exactly
                                                 as a booked one would. --}}
                                            <div class="v-actrow">
                                                <button type="button" class="v-btn v-btn--ghost" @click="backToCouriers()">Back</button>
                                                <button type="submit" class="v-btn v-btn--primary v-actrow__grow">Record shipment</button>
                                                <x-admin.help text="Venta contacts no courier and prints no waybill. The order moves to the status chosen above and advances by status from there." />
                                            </div>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        @endif
    </div>

    @push('scripts')
    <script>
        function fulfillment() {
            return {
                drawerOpen: false,
                bookOpen: false,
                step: 1,
                active: null,
                courier: '',
                parcel: '',
                // Step 2 has two shapes now: an integrated courier's own form,
                // or the manual record. One flag decides which, and it is
                // cleared everywhere `courier` is, so the two can never both
                // be showing.
                manual: false,
                // Task T4: SPX-only step-2 state. Fetched LAZILY (never on
                // page load — see FulfillmentController::index()'s
                // courierOptions comment for why) so a queue page visit
                // never costs an outbound SPX call.
                spxPickupSlots: [],
                spxPickupSlotsLoading: false,
                spxEstimate: null,
                spxEstimateError: null,
                spxEstimateLoading: false,
                // Serviceability (owner directive, 2026-08-12): every
                // configured courier's answer to "do you deliver here",
                // asked once when the modal opens and held for this
                // session only. The map is keyed by courier and each
                // entry is {status, reason, fees, currency, from}:
                // status is one of serviceable / not_serviceable /
                // unknown, and an ABSENT courier reads as unknown, which
                // is the safe direction: unknown is still bookable.
                serviceability: {},
                checking: false,
                // Who gets asked. Configured couriers only; a courier
                // with no credentials cannot answer and its card already
                // says why.
                checkedCouriers: @js($checkedCourierNames),
                options: @js($courierOptions),
                // The store's collection points, and which one the server
                // would choose unaided. Held here so step 2 can confirm the
                // chosen address's street lines beneath the name.
                pickups: @js($pickupAddresses),
                defaultPickupId: @js($defaultPickupId),
                pickupId: @js($defaultPickupId),
                // Same tone -> badge map the rows render server-side, for
                // the drawer's Alpine-driven terminal phase badge.
                toneClasses: {
                    neutral: 'v-badge v-badge--quiet',
                    success: 'v-badge v-badge--good',
                    warning: 'v-badge v-badge--warn',
                    danger: 'v-badge v-badge--bad',
                    info: 'v-badge v-badge--info',
                },
                urlBase: '{{ url(config('admin.prefix', 'admin') . '/sales/fulfillment') }}',
                get current() { return this.options[this.courier] ?? {name:'',services:[],parcels:[],payments:[]}; },
                // The chosen origin's street lines. Compared as strings:
                // a select's value is always one, the ids are integers.
                get pickupSummary() {
                    const p = this.pickups.find(x => String(x.id) === String(this.pickupId));
                    return p ? p.summary : '';
                },
                get bookAction() { return this.urlBase + '/' + (this.active ? this.active.id : '') + '/book'; },
                get manualAction() { return this.urlBase + '/' + (this.active ? this.active.id : '') + '/book-manual'; },
                get trackAction() { return this.urlBase + '/' + (this.active ? this.active.id : '') + '/track'; },
                get labelUrl() { return this.urlBase + '/' + (this.active ? this.active.id : '') + '/label'; },
                get estimateAction() { return this.urlBase + '/' + (this.active ? this.active.id : '') + '/estimate'; },
                get pickupSlotsUrl() { return this.urlBase + '/' + (this.active ? this.active.id : '') + '/pickup-slots'; },
                get serviceabilityUrl() { return this.urlBase + '/' + (this.active ? this.active.id : '') + '/serviceability'; },
                // What is happening and why it is taking a moment. Named,
                // because it is a real call to somebody else's network.
                get waitLine() {
                    const who = this.checkedCouriers.length > 1
                        ? this.checkedCouriers.slice(0, -1).join(', ') + ' and ' + this.checkedCouriers[this.checkedCouriers.length - 1]
                        : (this.checkedCouriers[0] || '');
                    return 'Asking ' + who + ' whether they deliver to ' + (this.active ? this.active.destination : '');
                },
                // Every courier that answered, refused. Never true when
                // there was nobody to ask: no courier means no refusal.
                get allRefused() {
                    const keys = Object.keys(this.serviceability);
                    return keys.length > 0 && keys.every(k => this.serviceability[k].status === 'not_serviceable');
                },
                // "Will carry this order", not "delivers here": a refusal can
                // be about COD or the pickup origin rather than the
                // destination, and this one line has to be true of all of
                // them.
                get refusedLine() {
                    return 'No integrated courier will carry this order to ' + (this.active ? this.active.destination : '') + '.';
                },
                verdict(key) {
                    const v = this.serviceability[key];
                    return v ? v.status : 'unknown';
                },
                // The courier's own words, verbatim. Shown ONLY when
                // sentenceFor() below has nothing of its own to say, i.e.
                // when we did not recognise what the courier refused.
                reason(key) {
                    const v = this.serviceability[key];
                    return (v && v.reason) ? v.reason : '';
                },
                courierName(key) {
                    return (this.options[key] && this.options[key].name) ? this.options[key].name : key;
                },
                // OUR sentence for a failure we recognise: what was actually
                // refused, named, and reading the same whichever courier said
                // it. Returns '' when we did not recognise the failure, which
                // is what makes the card print the courier's own words
                // instead.
                sentenceFor(key) {
                    const v = this.serviceability[key];
                    if (!v) return '';
                    const c = this.courierName(key);
                    const place = this.active ? this.active.destination : '';
                    switch (v.kind) {
                        case 'destination':
                            return c + ' does not deliver to ' + place + '.';
                        case 'origin':
                            return c + ' will not collect from your pickup address.';
                        // Deliverable, just not with cash collected at the
                        // door. Saying "does not deliver" here would be
                        // wrong, and this store sells heavily on COD.
                        case 'cod':
                            return c + ' will not collect cash on delivery at ' + place + '.';
                        case 'postcode':
                            return c + ' does not accept the postcode' + (this.active && this.active.postcode ? ' ' + this.active.postcode : '') + ' on this address.';
                        // About the parcel we guessed, not about the address:
                        // the real parcel is chosen in step 2, where Get
                        // estimate quotes it exactly.
                        case 'parcel':
                            return c + ' refused the parcel this check quoted, so its coverage here is unconfirmed. Choose a parcel and check the fee.';
                        case 'slot':
                            return c + ' would not accept the pickup arrangement this check used, so its coverage here is unconfirmed.';
                        case 'their_side':
                            return c + ' had a failure on its own side, so its coverage here is unconfirmed.';
                        case 'unreachable':
                            return c + ' did not answer, so its coverage here is unconfirmed.';
                    }
                    // Unrecognised, and the courier said something: its words
                    // are the only useful description we have.
                    if (v.reason) return '';
                    return v.status === 'unknown' ? (c + ' did not answer, so its coverage here is unconfirmed.') : '';
                },
                // The cheapest parcel this courier quoted, as a from-price.
                fromPrice(key) {
                    const v = this.serviceability[key];
                    return (v && v.from !== null && v.from !== undefined) ? ('from ' + this.peso(v.from)) : '';
                },
                // The exact fee for one parcel option, appended to its own
                // label in step 2. A parcel with no quote keeps its plain
                // label rather than gaining a zero.
                parcelLabel(option) {
                    const v = this.serviceability[this.courier];
                    const fee = (v && v.fees) ? v.fees[option.value] : null;
                    return (fee === null || fee === undefined) ? option.label : (option.label + ' (' + this.peso(fee) + ')');
                },
                peso(amount) {
                    return '₱' + Number(amount).toFixed(2);
                },
                openDrawer(order) {
                    this.active = order;
                    this.drawerOpen = true;
                },
                closeDrawer() {
                    this.drawerOpen = false;
                },
                // Courier-first: the modal always opens on step 1 with no
                // courier chosen — choosing one is what reveals its form.
                openBooking(order) {
                    this.active = order;
                    this.courier = '';
                    this.manual = false;
                    this.step = 1;
                    this.bookOpen = true;
                    // Each booking starts from the store's default origin,
                    // never from whatever the previous order was sent from.
                    this.pickupId = this.defaultPickupId;
                    this.resetSpxState();
                    this.loadServiceability();
                },
                // Asked on OPEN, and the cards wait for it (owner ruling,
                // 2026-08-12). The server holds every courier to a 5s
                // budget, so this pause is bounded even when a courier is
                // unreachable, and an unreachable courier comes back as
                // `unknown`, which still renders a card the operator can
                // click.
                loadServiceability() {
                    this.serviceability = {};
                    if (!this.active || this.checkedCouriers.length === 0) {
                        this.checking = false;
                        return;
                    }
                    this.checking = true;
                    // The modal serves every row, so an answer that lands
                    // after the operator has moved to another order belongs
                    // to nobody: it is dropped rather than shown against the
                    // wrong address.
                    const askedFor = this.active.id;
                    fetch(this.serviceabilityUrl, { headers: { 'Accept': 'application/json' } })
                        .then(r => r.json())
                        .then(d => {
                            if (!this.active || this.active.id !== askedFor) return;
                            this.serviceability = (d && d.couriers) ? d.couriers : {};
                            this.checking = false;
                        })
                        .catch(() => {
                            if (!this.active || this.active.id !== askedFor) return;
                            // We could not even ask our own server. Every
                            // courier reads as unknown, which is honest and
                            // leaves every card clickable.
                            this.serviceability = {};
                            this.checking = false;
                        });
                },
                // The third choice: no courier key, because no integration owns
                // this shipment. That is the same distinction the server keeps
                // by leaving courier_provider null.
                chooseManual() {
                    this.courier = '';
                    this.manual = true;
                    this.step = 2;
                    this.resetSpxState();
                },
                chooseCourier(key) {
                    this.courier = key;
                    this.manual = false;
                    this.step = 2;
                    this.parcel = '';
                    this.resetSpxState();
                    // Task T4: pickup slots are fetched HERE — the moment the
                    // admin actually picks SPX — never during the queue's
                    // own page load (see index()'s courierOptions comment).
                    if (key === 'spx') {
                        this.loadSpxPickupSlots();
                    }
                },
                backToCouriers() {
                    this.step = 1;
                    this.manual = false;
                },
                closeBooking() {
                    this.bookOpen = false;
                    this.step = 1;
                    this.manual = false;
                },
                resetSpxState() {
                    this.spxPickupSlots = [];
                    this.spxPickupSlotsLoading = false;
                    this.spxEstimate = null;
                    this.spxEstimateError = null;
                    this.spxEstimateLoading = false;
                },
                loadSpxPickupSlots() {
                    if (!this.active) return;
                    this.spxPickupSlotsLoading = true;
                    fetch(this.pickupSlotsUrl + '?courier=spx', { headers: { 'Accept': 'application/json' } })
                        .then(r => r.json())
                        .then(d => { this.spxPickupSlots = (d && d.slots) ? d.slots : []; })
                        .catch(() => { this.spxPickupSlots = []; })
                        .finally(() => { this.spxPickupSlotsLoading = false; });
                },
                // Task T4: lazy fee estimate — reads the form's OWN current
                // values (via x-ref="bookForm") so the estimate always
                // reflects whatever parcel/payment/insure choice is
                // currently selected, without duplicating that state here.
                getSpxEstimate() {
                    if (!this.active || !this.$refs.bookForm) return;
                    this.spxEstimateLoading = true;
                    this.spxEstimateError = null;
                    this.spxEstimate = null;
                    fetch(this.estimateAction, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                        body: new FormData(this.$refs.bookForm),
                    })
                        .then(r => r.json())
                        .then(d => {
                            if (d && d.ok) {
                                this.spxEstimate = d;
                            } else {
                                this.spxEstimateError = (d && d.message) ? d.message : 'Estimate unavailable.';
                            }
                        })
                        .catch(() => { this.spxEstimateError = 'Estimate request failed.'; })
                        .finally(() => { this.spxEstimateLoading = false; });
                },
            };
        }
    </script>
    @endpush
    @endif
</x-admin.shell>
