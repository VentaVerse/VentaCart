<x-admin.shell title="Fulfillment">
    @push('head')
        @vite('extensions/courier-fulfillment/resources/css/fulfillment.css')
    @endpush
    <x-admin.page title="Fulfillment">
        <x-slot:actions>
            <x-admin.button variant="secondary" size="sm" href="{{ route('admin.fulfillment.settings') }}">Courier settings</x-admin.button>
        </x-slot:actions>

    @if ($paused)
        {{-- Explicitly paused (courier.fulfillment_enabled = '0') — an explanatory
             page, never a redirect. Bookings triggered by the ERP endpoint are a
             separate code path and keep working regardless of this toggle. --}}
        <x-admin.empty-state icon="fulfillment" title="Fulfillment is paused"
            description="The manual booking queue is turned off, so Fulfillment is hidden from the Sales menu and this page lists nothing. Orders booked automatically through the ERP integration (if configured) are unaffected. Switch it back on in Courier Settings.">
            @if ($canConfigureCouriers)
                <x-admin.button variant="secondary" size="sm" href="{{ route('admin.fulfillment.settings') }}">Go to Courier Settings</x-admin.button>
            @endif
        </x-admin.empty-state>
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
            <div class="v-flash" x-init="window.open(@js(session('label_url')), '_blank')">
                <span class="v-flash__text">Booked. The AWB should have opened in a new tab.</span>
                <a href="{{ session('label_url') }}" target="_blank" rel="noopener" class="v-flash__btn">Open AWB</a>
            </div>
        @endif

        @if (empty($couriers))
            {{-- The queue cannot book anything without a courier, so the
                 shared blocking-setup state says so and carries its own way
                 out. --}}
            <x-admin.empty-state variant="notice" title="No courier is enabled yet" class="mb-4">
                <a href="{{ route('admin.fulfillment.settings') }}" class="v-notice__btn">Configure a courier</a>
            </x-admin.empty-state>
        @endif

        {{-- Command bar: talk to the couriers. One control, on one 32px
             baseline, sharing the filter row's starting edge. It is a bare
             row rather than a sheet: a full-width raised panel holding a
             single button reads as a section whose contents failed to
             load. --}}
        <x-admin.toolbar>
            <form method="POST" action="{{ route('admin.fulfillment.poll') }}" data-v-form class="v-toolbar__actions">
                @csrf
                <x-admin.button type="submit" variant="primary">Refresh from couriers</x-admin.button>
            </form>
        </x-admin.toolbar>

        {{-- Segment bar: the eight tabs above, each with a mono count.
             Returns is a sibling page on the far side, not a ninth tab. To
             Ship's link always lands on its DEFAULT sub-tab (To Pack), the
             active sub-tab's own bar (below) is what lets the operator move
             to To Handover from there. --}}
        <x-admin.segment label="Fulfillment phase">
            @foreach ($phaseTabs as $key => $label)
                <x-admin.segment.item
                    :href="route('admin.fulfillment.index', array_filter(['filter' => $key, 'sub' => $key === 'to_ship' ? 'to_pack' : null, 'q' => request('q'), 'per_page' => request('per_page')], fn ($v) => $v !== null && $v !== ''))"
                    :active="$filter === $key"
                    :count="number_format($phaseCounts[$key] ?? 0)"
                    :count-key="$key">{{ $label }}</x-admin.segment.item>
            @endforeach
            @if (Route::has('admin.fulfillment.returns'))
                <x-slot:aside>
                    <a class="v-segbar__aside" href="{{ route('admin.fulfillment.returns') }}">
                        Returns
                        <x-admin.icon name="chevron-right" class="w-3 h-3" />
                    </a>
                </x-slot:aside>
            @endif
        </x-admin.segment>

        {{-- Sub-segment bar: To Ship's own To Pack / To Handover split —
             ERP x-segment--sub pattern (same segment anatomy, no
             trough/background, slightly smaller) so it reads as nested
             under the tab above it, never a second bar of equal weight.
             Renders ONLY under To Ship; $sub is ignored (and this bar never
             renders) on every other tab. --}}
        @if ($filter === 'to_ship')
            <x-admin.segment sub label="To Ship stage">
                @foreach ($subTabs as $key => $label)
                    <x-admin.segment.item
                        :href="route('admin.fulfillment.index', array_filter(['filter' => 'to_ship', 'sub' => $key, 'q' => request('q'), 'per_page' => request('per_page')], fn ($v) => $v !== null && $v !== ''))"
                        :active="$sub === $key"
                        :count="number_format($phaseCounts[$key] ?? 0)"
                        :count-key="$key">{{ $label }}</x-admin.segment.item>
                @endforeach
            </x-admin.segment>
        @endif

        {{-- Courier tally — the handover step is where the operator counts
             parcels per courier before each pickup arrives. Moved (Task 3)
             from the old top-level To Handover tab to its new home: To
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

        {{-- Filter row: search + page size, one job each. --}}
        <x-admin.filters :action="route('admin.fulfillment.index')">
            <input type="hidden" name="filter" value="{{ $filter }}">
            @if ($filter === 'to_ship')
                <input type="hidden" name="sub" value="{{ $sub }}">
            @endif
            <x-admin.input type="search" name="q" :value="request('q')" placeholder="Order #, tracking, customer&hellip;" aria-label="Search orders" class="v-filters__search" />
            <x-admin.select name="per_page" aria-label="Orders per page" class="v-filters__select" @change="$event.target.form.submit()">
                @foreach ([10, 25, 50] as $n)
                    <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }} per page</option>
                @endforeach
            </x-admin.select>
            <x-admin.button type="submit" variant="secondary">Search</x-admin.button>
            @if (request('q'))
                <a class="v-filters__reset" href="{{ route('admin.fulfillment.index', array_filter(['filter' => $filter, 'sub' => $filter === 'to_ship' ? $sub : null, 'per_page' => $perPage], fn ($v) => $v !== null && $v !== '')) }}">Reset</a>
            @endif
        </x-admin.filters>

        {{-- Two-tier table: a band of order-level facts above one row per
             item; total / courier / status / actions rowspanned beside them.
             Verification density, because a packer looks at the picture. --}}
        <x-admin.table tiered roomy>
            <x-slot:head>
                <th>Products</th>
                <th class="v-col-qty">Qty</th>
                <th class="v-col-amount">Total</th>
                <th class="v-col-courier">Courier</th>
                <th class="v-col-status">Status</th>
                <th class="v-col-act"><span class="v-sr">Actions</span></th>
            </x-slot:head>
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
                                <div class="v-tier__row">
                                    <div class="v-tier__left">
                                        <span class="v-tier__label">Customer</span>
                                        <span class="v-tier__value">{{ trim(($order->shipping_first_name ?? '') . ' ' . ($order->shipping_last_name ?? '')) ?: '-' }}</span>
                                        <span class="v-tier__label">Order</span>
                                        <a class="v-ref" href="{{ route('admin.orders.show', $order) }}">#{{ $order->id }}</a>
                                        <span class="v-tier__muted">({{ $order->products_count ?? 0 }} {{ ($order->products_count ?? 0) === 1 ? 'item' : 'items' }})</span>
                                    </div>
                                    <div class="v-tier__right">
                                        <span class="v-tier__label">Created</span>
                                        <span class="v-tier__value v-tier__value--mono">{{ optional($order->created_at)->format('M d, Y g:ia') }}</span>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @foreach ($displayItems as $idx => $item)
                            <tr class="v-subrow {{ $idx === $lineCount - 1 ? 'v-subrow--last' : '' }}">
                                <td>
                                    @if ($item)
                                        @php($itemImage = $item->image ?? $item->product?->image)
                                        <x-admin.item
                                            :name="$item->name ?: 'Unnamed product'"
                                            :image="$itemImage ? storage_url($itemImage) : null"
                                            :sku="trim((string) $item->sku) !== '' ? $item->sku : null"
                                            :variant="trim((string) $item->variant_label) !== '' ? $item->variant_label : null" />
                                    @else
                                        <span class="v-none">No items recorded</span>
                                    @endif
                                </td>
                                <td class="v-col-qty">
                                    @if ($item)
                                        <span class="v-qty">&times;{{ max(1, (int) $item->quantity) }}</span>
                                    @else
                                        <span class="v-none">-</span>
                                    @endif
                                </td>
                                @if ($idx === 0)
                                    <td class="v-col-amount" rowspan="{{ $lineCount }}">
                                        <span class="v-amount">&#8369;{{ number_format($order->total, 2) }}</span>
                                        <span class="v-amount__sub">{{ $isCod ? 'COD' : ucwords(str_replace('_', ' ', (string) $order->payment_method)) }}</span>
                                    </td>
                                    <td class="v-col-courier" rowspan="{{ $lineCount }}">
                                        @if ($order->courier_tracking_number)
                                            <span class="v-lead">{{ $orderCourier?->getName() ?? strtoupper((string) $order->courier_provider) }}</span>
                                            @if ($trackingUrl)
                                                <a class="v-sub" href="{{ $trackingUrl }}" target="_blank" rel="noopener">{{ $order->courier_tracking_number }}</a>
                                            @else
                                                <span class="v-sub">{{ $order->courier_tracking_number }}</span>
                                            @endif
                                        @elseif ($manuallyShipped)
                                            {{-- A parcel we arranged ourselves. The courier
                                                 is named as plainly as an integrated one, with
                                                 a quiet marker beside it so nobody goes hunting
                                                 for a waybill that was never printed. The
                                                 tracking number is optional here, so the cell
                                                 stops after the name when there isn't one. --}}
                                            <span class="v-lead">{{ $manualCourierName }}</span>
                                            <span class="v-manual">Manual</span>
                                            @if ($order->tracking_number)
                                                @if ($manualTrackingUrl)
                                                    <a class="v-sub" href="{{ $manualTrackingUrl }}" target="_blank" rel="noopener">{{ $order->tracking_number }}</a>
                                                @else
                                                    <span class="v-sub">{{ $order->tracking_number }}</span>
                                                @endif
                                            @endif
                                        @else
                                            <span class="v-none">-</span>
                                        @endif
                                    </td>
                                    <td class="v-col-status" rowspan="{{ $lineCount }}">
                                        <div class="v-cellstack">
                                            @if ($hasCourierData)
                                                {{-- A booked row reports its SHIPMENT's spine
                                                     phase, in that phase's shared tone. --}}
                                                <x-admin.badge :tone="$spineTone">{{ $spineLabel }}</x-admin.badge>
                                            @else
                                                {{-- No courier ever touched this order, so there
                                                     is no courier state to report: its core order
                                                     status is the honest answer (and the only
                                                     thing that decided which tab it is in). --}}
                                                <x-admin.badge tone="neutral">{{ $order->status?->name ?? 'Missing' }}</x-admin.badge>
                                            @endif
                                            @if ($order->courier_status)
                                                <span class="v-cellstack__note">{{ $humanizeStatus((string) $order->courier_status, $order->courier_provider) }}</span>
                                            @endif
                                            @if ($creationError)
                                                <span class="v-cellstack__error" title="{{ $creationError }}">{{ \Illuminate\Support\Str::limit($creationError, 90) }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="v-col-act" rowspan="{{ $lineCount }}">
                                        <div class="v-rowactions">
                                            {{-- The action table. Read the $rowBucket block in this
                                                 row's @php above for the table itself and for what
                                                 each gate subtracts: the ORDER actions (Book, Cancel
                                                 order, Cancel booking) are keyed on the row's tab and
                                                 nothing else, the COURIER actions (Print waybill,
                                                 Tracking) are keyed on the row's courier data and
                                                 nothing else. --}}
                                            @if ($canBook)
                                                {{-- Component-tag attributes only compile {{ }} echoes, not
                                                     @-directives — @js() here would render as literal text.
                                                     Js::from() is what @js() expands to; the {{ }} form is the
                                                     one that compiles on a component tag. --}}
                                                <x-admin.button type="button" size="sm" variant="primary" @click="openBooking({{ \Illuminate\Support\Js::from($rowData) }})">Book</x-admin.button>
                                            @endif
                                            @if ($offersWaybill)
                                                <x-admin.button size="sm" variant="secondary" href="{{ route('admin.fulfillment.label', $order) }}" target="_blank" rel="noopener">Print waybill</x-admin.button>
                                            @endif
                                            @if ($offersDrawer)
                                                {{-- One opener, three honest labels: a booking still
                                                     in flight says so, a failed async create opens on
                                                     its error, anything with a tracking number opens
                                                     on its timeline. --}}
                                                @if ($creating)
                                                    <x-admin.button type="button" size="sm" variant="secondary" @click="openDrawer({{ \Illuminate\Support\Js::from($rowData) }})">Creating&hellip;</x-admin.button>
                                                @elseif ($creationFailed && !$order->courier_tracking_number)
                                                    <x-admin.button type="button" size="sm" variant="secondary" @click="openDrawer({{ \Illuminate\Support\Js::from($rowData) }})">Details</x-admin.button>
                                                @else
                                                    <x-admin.button type="button" size="sm" variant="secondary" @click="openDrawer({{ \Illuminate\Support\Js::from($rowData) }})">Tracking</x-admin.button>
                                                @endif
                                            @endif
                                            @if ($offersCancelBooking)
                                                <x-admin.button type="button" size="sm" variant="ghost" class="v-rowactions__danger" @click="$dispatch('open-modal', 'cancel-fulfillment-{{ $order->id }}')">Cancel booking</x-admin.button>
                                            @endif
                                            @if ($offersCancelOrder)
                                                <x-admin.button type="button" size="sm" variant="ghost" class="v-rowactions__danger" @click="$dispatch('open-modal', 'cancel-order-{{ $order->id }}')">Cancel order</x-admin.button>
                                            @endif
                                            @if ($offersClearManual)
                                                <x-admin.button type="button" size="sm" variant="ghost" class="v-rowactions__danger" @click="$dispatch('open-modal', 'clear-manual-{{ $order->id }}')">Clear shipment</x-admin.button>
                                            @endif
                                        </div>
                                        @if ($offersCancelOrder)
                                            <x-admin.confirm-dialog name="cancel-order-{{ $order->id }}" title="Cancel order #{{ $order->id }}?"
                                                message="This sets the order status to your configured cancel status (returning any reserved stock) and moves it to the Cancelled tab. No courier is contacted."
                                                confirm="Cancel order" :action="route('admin.fulfillment.cancel-order', $order)" method="POST" />
                                        @endif
                                        @if ($offersCancelBooking)
                                            <x-admin.confirm-dialog name="cancel-fulfillment-{{ $order->id }}" title="Cancel booking for order #{{ $order->id }}?"
                                                message="This cancels the courier booking. The order returns to To Pack for rebooking."
                                                confirm="Cancel booking" :action="route('admin.fulfillment.cancel', $order)" method="POST" />
                                        @endif
                                        @if ($offersClearManual)
                                            <x-admin.confirm-dialog name="clear-manual-{{ $order->id }}" title="Clear the recorded shipment for order #{{ $order->id }}?"
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
                                <x-admin.empty-state icon="fulfillment" title="No orders in this view." />
                            </td>
                        </tr>
                    @endforelse
        </x-admin.table>

        {{-- Pager: "1 to N of M" mono left, Prev/Next right. --}}
        <x-admin.pager :paginator="$orders" />

        {{-- Detail drawer — the VIEWING surface (stepper, timeline, tracking,
             cancel). Booking happens in the modal below, never here. --}}
        <div x-show="drawerOpen" x-cloak class="fixed inset-0 z-50" @keydown.escape.window="closeDrawer()">
            <div class="absolute inset-0 bg-black/40" x-show="drawerOpen" x-transition.opacity @click="closeDrawer()"></div>

            {{-- Hand-rolled rather than x-admin.modal because it reads the
                 queue's own `active` order out of the parent Alpine scope,
                 which a component's slot cannot see. The dialog contract is
                 the shared one all the same: x-dialog moves focus in, holds
                 it, and hands it back to the row button that opened it. --}}
            <div class="v-modal absolute inset-y-0 right-0 w-full max-w-md bg-v-surface border-l border-v-border flex flex-col"
                 x-dialog="drawerOpen" tabindex="-1"
                 role="dialog" aria-modal="true" :aria-label="'Order #' + (active ? active.id : '') + ' fulfillment'"
                 x-show="drawerOpen"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="translate-x-full"
                 x-transition:enter-end="translate-x-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="translate-x-0"
                 x-transition:leave-end="translate-x-full"
                 @click.stop>
                <template x-if="active">
                    <div class="flex flex-col h-full">
                        {{-- Header --}}
                        <div class="px-5 py-4 border-b border-v-border flex items-start justify-between">
                            <div>
                                <h3 class="text-sm font-semibold text-v-text">Order <span x-text="'#' + active.id"></span></h3>
                                <p class="text-xs text-v-muted mt-0.5" x-text="active.date"></p>
                            </div>
                            <button type="button" @click="closeDrawer()" class="text-v-muted hover:text-v-text text-xl leading-none"
                                    aria-label="Close" data-v-dialog-initial><span aria-hidden="true">&times;</span></button>
                        </div>

                        {{-- Big stepper (happy path) + terminal phase badge.
                             failed / cancelled / returning are terminal BADGE
                             states on this stepper, not steps of their own. --}}
                        <div class="px-5 py-4 border-b border-v-border">
                            <div class="flex items-center">
                                @foreach (['Pending', 'Booked', 'Shipped', 'Delivered'] as $i => $label)
                                    <div class="flex flex-col items-center flex-none w-12">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold"
                                             :class="{{ $i }} <= active.phaseStep ? (active.phaseFailed && {{ $i }} === active.phaseStep ? 'bg-v-danger text-v-danger-text' : 'bg-v-accent text-v-accent-text') : 'bg-v-strong text-v-muted'">
                                            {{ $i + 1 }}
                                        </div>
                                        <span class="mt-1 text-[10px] text-v-muted text-center">{{ $label }}</span>
                                    </div>
                                    @if ($i < 3)
                                        <div class="flex-1 h-px mb-4" :class="{{ $i }} < active.phaseStep ? 'bg-v-accent' : 'bg-v-border'"></div>
                                    @endif
                                @endforeach
                            </div>
                            <template x-if="['failed', 'cancelled', 'returning'].includes(active.phase)">
                                <div class="mt-3">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold"
                                          :class="toneClasses[active.phaseTone] || toneClasses.neutral"
                                          x-text="active.phaseLabel"></span>
                                </div>
                            </template>
                        </div>

                        {{-- Tracking history. Gated on the tracking number, not
                             activelyBooked, so a CANCELLED booking's timeline
                             stays viewable (review fix, W2). --}}
                        <template x-if="active.trackingNumber && active.timeline && active.timeline.length">
                            <div class="px-5 py-4 border-b border-v-border">
                                <h4 class="v-label mb-2">Tracking history</h4>
                                <ul class="space-y-1.5">
                                    <template x-for="(event, idx) in active.timeline" :key="idx">
                                        <li class="flex items-baseline justify-between gap-3 text-sm">
                                            <span class="text-v-text" x-text="event.label"></span>
                                            <span class="text-xs text-v-muted whitespace-nowrap" x-text="event.at"></span>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </template>

                        {{-- Summary --}}
                        <div class="px-5 py-4 border-b border-v-border space-y-2 text-sm">
                            <div class="flex justify-between gap-4">
                                <span class="text-v-muted shrink-0">Customer</span>
                                <span class="font-medium text-v-text text-right" x-text="active.customerName"></span>
                            </div>
                            <div class="flex justify-between gap-4">
                                <span class="text-v-muted shrink-0">Order status</span>
                                <span class="font-medium text-v-text text-right" x-text="active.statusName"></span>
                            </div>
                            <div class="flex justify-between gap-4">
                                <span class="text-v-muted shrink-0">Items</span>
                                <span class="font-medium text-v-text text-right" x-text="active.itemsCount + ' item(s)'"></span>
                            </div>
                            <div class="flex justify-between gap-4">
                                <span class="text-v-muted shrink-0">Ship to</span>
                                <span class="font-medium text-v-text text-right" x-text="active.address"></span>
                            </div>
                            {{-- Only on a booked parcel: it is a fact about the
                                 shipment, recorded when the booking was made. --}}
                            <template x-if="active.shipsFrom">
                                <div class="flex justify-between gap-4">
                                    <span class="text-v-muted shrink-0">Ships from</span>
                                    <span class="font-medium text-v-text text-right" x-text="active.shipsFrom"></span>
                                </div>
                            </template>
                        </div>

                        {{-- Tracking body / booking hand-off --}}
                        <div class="px-5 py-4 flex-1 overflow-y-auto">
                            {{-- Task T4: honest "Creating…" state — an async
                                 courier's booking is mid-flight (no tracking
                                 number yet, courier_status='creating'). Never
                                 shows a "Book shipment" button here (that
                                 would just get rejected by
                                 FulfillmentService::book()'s async-in-progress
                                 guard) — this is a status message, not an
                                 action. --}}
                            <template x-if="active.creating">
                                <div class="rounded-v-md bg-v-sunken border border-v-border px-4 py-3 text-sm text-v-text">
                                    <span class="font-medium">Creating shipment with <span x-text="active.provider"></span>&hellip;</span>
                                </div>
                            </template>
                            @if (!empty($courierOptions) || !empty($manualCouriers))
                                <template x-if="!active.activelyBooked && !active.creating">
                                    <div class="space-y-3">
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
                                            <div class="rounded-v-md border border-v-danger bg-[color-mix(in_srgb,var(--danger)_10%,transparent)] px-3 py-2 text-xs text-v-danger">
                                                <span class="font-semibold">Booking failed:</span>
                                                <span x-text="active.creationError || 'the courier could not create this shipment.'"></span>
                                            </div>
                                        </template>
                                        <template x-if="active.rebookable && active.canBook">
                                            <div class="rounded-v-md bg-v-sunken border border-v-border px-3 py-2 text-xs text-v-muted">
                                                Previous booking <span class="v-num" x-text="active.trackingNumber"></span> was canceled. Booking again creates a new shipment.
                                            </div>
                                        </template>
                                        <template x-if="active.canBook">
                                            <x-admin.button type="button" variant="primary" class="w-full" @click="closeDrawer(); openBooking(active)">Book shipment</x-admin.button>
                                        </template>
                                    </div>
                                </template>
                            @else
                                <template x-if="!active.activelyBooked">
                                    <div class="rounded-v-md border border-v-warning bg-[color-mix(in_srgb,var(--warning)_12%,transparent)] px-4 py-3 text-sm text-v-warning">
                                        No courier is enabled yet.
                                        <a href="{{ route('admin.fulfillment.settings') }}" class="font-semibold underline">Configure a courier <x-admin.icon name="arrow-right" class="inline w-3.5 h-3.5 align-[-2px]" /></a>
                                    </div>
                                </template>
                            @endif

                            <template x-if="active.activelyBooked">
                                <div class="space-y-4">
                                    <div class="rounded-v-md bg-v-sunken border border-v-border px-4 py-3 space-y-2">
                                        <div class="flex justify-between text-sm">
                                            <span class="text-v-muted">Tracking #</span>
                                            <template x-if="active.trackingUrl">
                                                <a :href="active.trackingUrl" target="_blank" rel="noopener" class="v-num font-medium text-v-accent hover:underline" x-text="active.trackingNumber"></a>
                                            </template>
                                            <template x-if="!active.trackingUrl">
                                                <span class="v-num font-medium text-v-text" x-text="active.trackingNumber"></span>
                                            </template>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-v-muted">Courier</span>
                                            <span class="font-medium text-v-text" x-text="active.provider"></span>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-v-muted">Status</span>
                                            <span class="font-medium text-v-text" x-text="active.courierStatus || '-'"></span>
                                        </div>
                                        <template x-if="active.estimatedDelivery">
                                            <div class="flex justify-between text-sm">
                                                <span class="text-v-muted">Est. delivery</span>
                                                <span class="font-medium text-v-text" x-text="active.estimatedDelivery"></span>
                                            </div>
                                        </template>
                                        {{-- Task T4: two DISTINCTLY labeled figures, shown only
                                             when present — an ESTIMATED fee (quoted at booking time)
                                             is never confused with the ACTUAL fee (SPX's post-booking
                                             batch_get_asf, refreshed on every poll). --}}
                                        <template x-if="active.estimatedFee">
                                            <div class="flex justify-between text-sm">
                                                <span class="text-v-muted">Estimated shipping fee</span>
                                                <span class="font-medium text-v-text" x-text="active.estimatedFee"></span>
                                            </div>
                                        </template>
                                        <template x-if="active.actualFee">
                                            <div class="flex justify-between text-sm">
                                                <span class="text-v-muted">Actual shipping fee</span>
                                                <span class="font-medium text-v-text" x-text="active.actualFee"></span>
                                            </div>
                                        </template>
                                        <template x-if="active.trackingUrl">
                                            <div class="pt-1">
                                                <a :href="active.trackingUrl" target="_blank" rel="noopener" class="text-v-accent hover:underline text-sm">Track shipment <x-admin.icon name="arrow-right" class="inline w-3.5 h-3.5 align-[-2px]" /></a>
                                            </div>
                                        </template>
                                    </div>

                                    <div class="flex flex-col gap-2">
                                        <template x-if="active.canPrintWaybill">
                                            <a :href="labelUrl" target="_blank" rel="noopener" class="text-center px-4 py-2 border border-v-border text-v-text text-sm font-semibold rounded-v-md hover:bg-v-hover">Print AWB</a>
                                        </template>
                                        <form :action="trackAction" method="POST" data-v-form>
                                            @csrf
                                            <x-admin.button type="submit" variant="secondary" class="w-full">Refresh status</x-admin.button>
                                        </form>
                                        <template x-if="active.canCancelBooking">
                                            <x-admin.button type="button" variant="danger" class="w-full" @click="$dispatch('open-modal', 'cancel-fulfillment-' + active.id)">Cancel booking</x-admin.button>
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
            <div x-show="bookOpen" x-cloak class="fixed inset-0 z-[90] flex items-center justify-center p-4" @keydown.escape.window="closeBooking()">
                <div class="absolute inset-0 bg-black/50" @click="closeBooking()"></div>
                {{-- Hand-rolled for the same reason as the drawer above: it
                     needs `active`, `step` and the courier form state from the
                     queue's own scope. x-dialog gives it the same focus
                     contract every x-admin.modal has. --}}
                {{-- Two widths, one sheet: the courier step lays its options
                     out as columns and needs the room for up to four of them,
                     while the chosen courier's form is a column of fields and
                     reads worse stretched. Both widths are named in the
                     module's own stylesheet (never a class assembled in
                     Alpine), and the sheet animates between them. --}}
                <div x-show="bookOpen" x-transition
                     class="v-modal v-bookmodal relative w-full bg-v-surface border border-v-border rounded-v-lg max-h-full overflow-y-auto"
                     :class="step === 1 ? 'v-bookmodal--couriers' : ''"
                     x-dialog="bookOpen" tabindex="-1"
                     role="dialog" aria-modal="true" :aria-label="'Book order #' + (active ? active.id : '')">
                    <template x-if="active">
                        <div>
                            <header class="flex items-center justify-between px-5 pt-4">
                                <h2 class="text-[15px] font-semibold text-v-text">Book order <span class="v-num" x-text="'#' + active.id"></span></h2>
                                <button type="button" class="text-v-muted hover:text-v-text" @click="closeBooking()" aria-label="Close">
                                    <x-admin.icon name="x" class="w-4 h-4" />
                                </button>
                            </header>
                            <div class="px-5 py-4">
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
                                     gets a card here instead of silently vanishing (the bug
                                     that confused the owner — CourierManager::enabled()'s
                                     AND-credentials gate made a courier they'd switched on
                                     disappear with no explanation). That card is a plain
                                     <div>, never a <button @click="chooseCourier(...)">, so
                                     it structurally CANNOT advance to step 2 — there is no
                                     click handler to wire up, not merely a disabled one. --}}
                                <div x-show="step === 1">
                                    <p class="text-[13px] text-v-secondary mb-3">How will this order ship?</p>

                                    {{-- The wait is a real network call to every
                                         configured courier, so it is named rather than
                                         spun at: who is being asked, and about where.
                                         The cards are drawn only once every courier has
                                         answered (owner ruling, 2026-08-12). A card
                                         that rearranges itself under the operator's hand
                                         is worse than a pause they can read. --}}
                                    <div x-show="checking" class="v-choice__wait">
                                        <span class="v-choice__waitmark" aria-hidden="true"></span>
                                        <span x-text="waitLine"></span>
                                    </div>

                                    <div x-show="!checking">
                                        {{-- Every courier we could ask said no. One line,
                                             the refused cards still readable below it with
                                             their own reasons, and the manual card
                                             selectable exactly as it always is. --}}
                                        <p class="v-choice__note" x-show="allRefused" x-text="refusedLine"></p>
                                    <x-admin.choice cols>
                                        @foreach ($courierOptions as $key => $opt)
                                            @if ($opt['configured'])
                                                {{-- Three cards, one per verdict, exactly
                                                     one of them shown. Written out rather
                                                     than assembled in Alpine so each state
                                                     is a real, inspectable piece of markup:
                                                     the refusal genuinely cannot advance
                                                     (it is a <div> with no handler) and the
                                                     unreachable one genuinely can (a real
                                                     <button> wired to chooseCourier). --}}
                                                <x-admin.choice.card
                                                    :name="$opt['name']"
                                                    :mode="!empty($opt['mode']) && $opt['mode'] !== 'production' ? ucfirst($opt['mode']) : null"
                                                    :mark="$markFor($key)"
                                                    :icon="$markFor($key) ? null : 'fulfillment'"
                                                    data-courier-card="{{ $key }}"
                                                    data-courier-state="serviceable"
                                                    x-show="verdict('{{ $key }}') === 'serviceable'"
                                                    @click="chooseCourier('{{ $key }}')">
                                                    {{-- The cheapest parcel this courier
                                                         quoted for this address. A from-price,
                                                         because which parcel goes in the box is
                                                         still step 2's question. --}}
                                                    <x-slot:fee><span x-text="fromPrice('{{ $key }}')"></span></x-slot:fee>
                                                </x-admin.choice.card>

                                                {{-- Could not check. A timeout, a dead DNS
                                                     record, a failure on the courier's own
                                                     side, or a complaint about the parcel we
                                                     guessed is NOT a refusal of the address,
                                                     so this card stays clickable and says only
                                                     what is true: we could not ask.
                                                     Collapsing this into "not serviceable"
                                                     would make the modal lie during an
                                                     outage. --}}
                                                <x-admin.choice.card
                                                    :name="$opt['name']"
                                                    state="Could not check"
                                                    :mark="$markFor($key)"
                                                    :icon="$markFor($key) ? null : 'fulfillment'"
                                                    data-courier-card="{{ $key }}"
                                                    data-courier-state="unknown"
                                                    x-show="verdict('{{ $key }}') === 'unknown'"
                                                    @click="chooseCourier('{{ $key }}')">
                                                    <span x-show="sentenceFor('{{ $key }}')" x-text="sentenceFor('{{ $key }}')"></span>
                                                    <template x-if="!sentenceFor('{{ $key }}') && reason('{{ $key }}')">
                                                        <span>{{ $opt['name'] }} said:<span class="v-choice__said" x-text="reason('{{ $key }}')"></span></span>
                                                    </template>
                                                </x-admin.choice.card>

                                                {{-- Not serviceable. One sentence, ours,
                                                     naming what was actually refused: the
                                                     destination, the pickup origin, COD at
                                                     this address, or the postcode. The
                                                     courier's own words appear ONLY when we
                                                     did not recognise what it said (owner
                                                     ruling, 2026-08-12: printing both was
                                                     redundant, since ours names the place and
                                                     reads the same across couriers). --}}
                                                <x-admin.choice.card
                                                    :name="$opt['name']"
                                                    :available="false"
                                                    state="Not serviceable"
                                                    :mark="$markFor($key)"
                                                    :icon="$markFor($key) ? null : 'fulfillment'"
                                                    data-courier-card="{{ $key }}"
                                                    data-courier-state="not_serviceable"
                                                    x-show="verdict('{{ $key }}') === 'not_serviceable'">
                                                    <span x-show="sentenceFor('{{ $key }}')" x-text="sentenceFor('{{ $key }}')"></span>
                                                    <template x-if="!sentenceFor('{{ $key }}') && reason('{{ $key }}')">
                                                        <div>{{ $opt['name'] }} said:<span class="v-choice__said" x-text="reason('{{ $key }}')"></span></div>
                                                    </template>
                                                </x-admin.choice.card>
                                            @else
                                                <x-admin.choice.card
                                                    :name="$opt['name']"
                                                    :available="false"
                                                    state="Not configured"
                                                    :mark="$markFor($key)"
                                                    :icon="$markFor($key) ? null : 'fulfillment'"
                                                    data-courier-card="{{ $key }}"
                                                    data-courier-configured="false">@if (!empty($opt['configIssues']))Missing {{ implode(' / ', $opt['configIssues']) }}. @endif Configure in <a href="{{ $opt['settingsUrl'] }}" class="v-choice__offlink">Settings &rarr; Courier Fulfillment &rarr; {{ $opt['name'] }}</a>.</x-admin.choice.card>
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
                                                 one. The person is the mark: every option in
                                                 this row is a parcel, and what separates this
                                                 one is that somebody arranges it by hand
                                                 instead of an API doing it. --}}
                                            <x-admin.choice.card name="Another courier" mode="Manual" icon="user"
                                                data-courier-card="__manual" @click="chooseManual()" />
                                        @endif
                                    </x-admin.choice>
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
                                         submission (the browser still posts by name= regardless of
                                         x-model/x-ref), so QuadX's POST body is unchanged. Every
                                         field name, value vocabulary, and the action URL stay
                                         byte-identical to the pre-T4 form. --}}
                                    <form :action="bookAction" method="POST" data-v-form class="space-y-4" x-ref="bookForm">
                                        @csrf
                                        <input type="hidden" name="courier" :value="courier">
                                        <p class="text-[13px] text-v-secondary">Shipping with <span class="font-semibold text-v-text" x-text="current.name"></span></p>
                                        <template x-if="active.rebookable">
                                            <div class="rounded-v-md bg-v-sunken border border-v-border px-3 py-2 text-xs text-v-muted">
                                                Previous booking <span class="v-num" x-text="active.trackingNumber"></span> was canceled. Booking again creates a new shipment.
                                            </div>
                                        </template>
                                        <div class="grid grid-cols-2 gap-3">
                                            <x-admin.field name="service" label="Service" required>
                                                <x-admin.select name="service">
                                                    <template x-for="o in current.services" :key="o.value"><option :value="o.value" x-text="o.label"></option></template>
                                                </x-admin.select>
                                            </x-admin.field>
                                            {{-- Each parcel carries the exact fee this
                                                 courier quoted for it, from the same
                                                 answer step 1's from-price came from,
                                                 so choosing the parcel IS choosing the
                                                 price. A parcel the courier did not
                                                 price keeps its plain label: no figure
                                                 is better than an invented one. --}}
                                            <x-admin.field name="parcel" label="Parcel" required>
                                                <x-admin.select name="parcel" x-model="parcel">
                                                    <template x-for="o in current.parcels" :key="o.value"><option :value="o.value" x-text="parcelLabel(o)"></option></template>
                                                </x-admin.select>
                                            </x-admin.field>
                                        </div>
                                        {{-- A literal ≈ rather than the HTML entity: this
                                             is a PROP, so Blade escapes it and the entity
                                             printed itself on screen. --}}
                                        <x-admin.toggle name="insure" label="Insure shipment (≈1% fee, declared at order total)" />
                                        <div class="rounded-v-md bg-v-sunken border border-v-border px-3 py-2">
                                            <div class="text-xs font-medium text-v-muted mb-0.5">Buyer pays for goods</div>
                                            <div class="text-sm text-v-text" x-text="active.buyerPaymentLabel"></div>
                                        </div>
                                        <x-admin.field name="shipping_payment" label="You pay shipping" required>
                                            <x-admin.select name="shipping_payment">
                                                <template x-for="o in current.payments" :key="o.value"><option :value="o.value" x-text="o.label"></option></template>
                                            </x-admin.select>
                                        </x-admin.field>

                                        {{-- Origin choice, courier-agnostic. Rendered ONLY when the
                                             store keeps more than one pickup address: with one there
                                             is no choice to make, and an absent field means "the
                                             default" to PickupAddress::resolveFor() on the server.
                                             @if, not x-if: whether a picker exists at all is a
                                             property of the store, known at render time, not of the
                                             courier chosen in step 1. --}}
                                        @if (count($pickupAddresses ?? []) > 1)
                                            <x-admin.field name="pickup_address_id" label="Ships from" required>
                                                <x-admin.select name="pickup_address_id">
                                                    @foreach ($pickupAddresses as $pickup)
                                                        <option value="{{ $pickup['id'] }}" @selected($pickup['isDefault'])>{{ $pickup['label'] }} - {{ $pickup['summary'] }}</option>
                                                    @endforeach
                                                </x-admin.select>
                                            </x-admin.field>
                                        @endif

                                        {{-- Task T4: SPX-only step-2 additions — rendered at all
                                             only when SPX is actually an enabled courier (never
                                             dead markup for a store that's never touched SPX). Uses
                                             x-if (a real template, not x-show/CSS display:none) —
                                             load-bearing, not cosmetic: an x-show'd block STAYS in
                                             the DOM and its inputs STILL POST even while hidden,
                                             which would (a) corrupt QuadX's POST body with spurious
                                             pickup_slot/length_cm etc. fields the moment SPX is ALSO
                                             enabled, and (b) 422 every non-custom SPX booking (the
                                             hidden, empty length_cm/width_cm/height_cm/weight_kg
                                             inputs would still submit '' against a `numeric` rule).
                                             x-if removes the fields from the DOM (and therefore the
                                             form) entirely whenever this courier isn't the chosen
                                             one — the ONLY safe way to make fields conditional on a
                                             shared <form>. --}}
                                        @if (isset($courierOptions['spx']))
                                        <template x-if="courier === 'spx'">
                                        <div class="space-y-4">
                                            <template x-if="parcel === 'custom'">
                                                <div class="grid grid-cols-2 gap-3">
                                                    <x-admin.field name="length_cm" label="Length (cm)">
                                                        <x-admin.input name="length_cm" type="number" step="0.1" min="0.1" />
                                                    </x-admin.field>
                                                    <x-admin.field name="width_cm" label="Width (cm)">
                                                        <x-admin.input name="width_cm" type="number" step="0.1" min="0.1" />
                                                    </x-admin.field>
                                                    <x-admin.field name="height_cm" label="Height (cm)">
                                                        <x-admin.input name="height_cm" type="number" step="0.1" min="0.1" />
                                                    </x-admin.field>
                                                    <x-admin.field name="weight_kg" label="Weight (kg)">
                                                        <x-admin.input name="weight_kg" type="number" step="0.01" min="0.01" />
                                                    </x-admin.field>
                                                </div>
                                            </template>

                                            <x-admin.field name="pickup_slot" label="Pickup date (optional)" help="Drop-off is the default. SPX decides which dates are available for pickup.">
                                                <x-admin.select name="pickup_slot">
                                                    <option value="">Drop off myself</option>
                                                    <template x-for="o in spxPickupSlots" :key="o.value"><option :value="o.value" x-text="o.label"></option></template>
                                                </x-admin.select>
                                            </x-admin.field>
                                            <p x-show="spxPickupSlotsLoading" x-cloak class="text-xs text-v-muted">Loading pickup dates&hellip;</p>

                                            <div class="rounded-v-md bg-v-sunken border border-v-border px-3 py-2 flex items-center justify-between gap-3">
                                                <div>
                                                    <div class="text-xs font-medium text-v-muted">Estimated shipping fee</div>
                                                    <div class="text-sm font-semibold text-v-text" x-show="spxEstimate" x-text="spxEstimate ? spxEstimate.label : ''"></div>
                                                    <div class="text-xs text-v-danger" x-show="spxEstimateError" x-text="spxEstimateError"></div>
                                                    <div class="text-xs text-v-muted" x-show="!spxEstimate && !spxEstimateError">Not checked yet.</div>
                                                </div>
                                                <x-admin.button type="button" size="sm" variant="secondary" @click="getSpxEstimate()" x-bind:disabled="spxEstimateLoading">
                                                    <span x-show="!spxEstimateLoading">Get estimate</span><span x-show="spxEstimateLoading" x-cloak>Checking&hellip;</span>
                                                </x-admin.button>
                                            </div>
                                        </div>
                                        </template>
                                        @endif

                                        <div class="flex gap-2 pt-1">
                                            <x-admin.button type="button" variant="ghost" @click="backToCouriers()">Back</x-admin.button>
                                            <x-admin.button type="submit" variant="primary" class="flex-1" x-bind:disabled="!courier">Book shipment</x-admin.button>
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
                                        <form :action="manualAction" method="POST" data-v-form class="space-y-4">
                                            @csrf
                                            <p class="text-[13px] text-v-secondary">Recording a shipment for order <span class="v-num" x-text="'#' + active.id"></span></p>

                                            <x-admin.field name="shipping_courier_id" label="Courier" required>
                                                <x-admin.select name="shipping_courier_id" required>
                                                    @foreach ($manualCouriers as $manualCourier)
                                                        <option value="{{ $manualCourier['id'] }}">{{ $manualCourier['name'] }}</option>
                                                    @endforeach
                                                </x-admin.select>
                                            </x-admin.field>

                                            <x-admin.field name="tracking_number" label="Tracking number">
                                                <x-admin.input name="tracking_number" autocomplete="off" maxlength="190" />
                                            </x-admin.field>

                                            {{-- Nothing is sent to a carrier here, so the
                                                 operator says plainly what state the order
                                                 is now in. The same statuses the order page
                                                 offers, pre-selected to the one this module
                                                 would have applied on its own. --}}
                                            @if (!empty($orderStatuses) && count($orderStatuses) > 0)
                                                <x-admin.field name="order_status_id" label="Order status" required>
                                                    <x-admin.select name="order_status_id" required>
                                                        @foreach ($orderStatuses as $orderStatus)
                                                            <option value="{{ $orderStatus->id }}" @selected($orderStatus->id === $manualDefaultStatusId)>{{ $orderStatus->name }}</option>
                                                        @endforeach
                                                    </x-admin.select>
                                                </x-admin.field>
                                            @endif

                                            <x-admin.field name="note" label="Note">
                                                <x-admin.textarea name="note" rows="2" maxlength="500" />
                                            </x-admin.field>

                                            {{-- The hint carries the one consequence the
                                                 controls cannot name themselves: no courier is
                                                 contacted, and the order still moves on exactly
                                                 as a booked one would. --}}
                                            <div class="flex items-center gap-2 pt-1">
                                                <x-admin.button type="button" variant="ghost" @click="backToCouriers()">Back</x-admin.button>
                                                <x-admin.button type="submit" variant="primary" class="flex-1">Record shipment</x-admin.button>
                                                <x-admin.hint label="What recording a shipment does" class="v-hint--end">Venta contacts no courier and prints no waybill. The order moves to the status chosen above and advances by status from there.</x-admin.hint>
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
                // Same tone -> class map x-admin.badge renders server-side,
                // for the drawer's Alpine-driven terminal phase badge.
                toneClasses: {
                    neutral: 'bg-v-sunken text-v-secondary',
                    success: 'bg-v-accent-weak text-v-accent',
                    warning: 'bg-[color-mix(in_srgb,var(--warning)_12%,transparent)] text-v-warning',
                    danger: 'bg-[color-mix(in_srgb,var(--danger)_12%,transparent)] text-v-danger',
                    info: 'bg-[color-mix(in_srgb,var(--info)_12%,transparent)] text-v-info',
                },
                urlBase: '{{ url(config('admin.prefix', 'admin') . '/sales/fulfillment') }}',
                get current() { return this.options[this.courier] ?? {name:'',services:[],parcels:[],payments:[]}; },
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
    </x-admin.page>
</x-admin.shell>
