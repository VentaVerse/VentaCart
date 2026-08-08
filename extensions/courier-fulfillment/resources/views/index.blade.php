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
            description="The manual booking queue is turned off. Orders booked automatically through the ERP integration (if configured) are unaffected. This only hides this page.">
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
                $statusLabelsByProvider[$provider] = is_array($decoded) ? $decoded : [];
            }
            return $statusLabelsByProvider[$provider][$status] ?? ucwords(str_replace('_', ' ', $status));
        };

        // The seven operational spine tabs. `returning` is deliberately NOT a
        // tab — returns are a sibling page (the aside link), never an eighth
        // filter (the ERP rule this grammar is copied from).
        $phaseTabs = [
            'to_book'     => 'To Book',
            'booking'     => 'Booking',
            'to_handover' => 'To Handover',
            'in_transit'  => 'In Transit',
            'delivered'   => 'Delivered',
            'failed'      => 'Failed',
            'cancelled'   => 'Cancelled',
        ];
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
            <div class="cf-awb-flash" x-init="window.open(@js(session('label_url')), '_blank')">
                <span class="cf-awb-flash__text">Booked. The AWB should have opened in a new tab.</span>
                <a href="{{ session('label_url') }}" target="_blank" rel="noopener" class="cf-awb-flash__btn">Open AWB</a>
            </div>
        @endif

        @if (empty($couriers))
            <div class="mb-4 rounded-v-md border border-v-warning bg-[color-mix(in_srgb,var(--warning)_12%,transparent)] px-4 py-3 text-[13px] text-v-warning">
                No courier is enabled yet.
                <a href="{{ route('admin.fulfillment.settings') }}" class="font-semibold underline">Configure a courier &rarr;</a>
            </div>
        @endif

        {{-- Command bar: talk to the couriers. One 32px baseline. --}}
        <div class="cf-cmdbar">
            <form method="POST" action="{{ route('admin.fulfillment.poll') }}" data-v-form class="cf-cmdbar__row">
                @csrf
                <div class="cf-cmdbar__spacer"></div>
                <div class="cf-cmdbar__actions">
                    <x-admin.button type="submit" variant="primary">Poll courier statuses</x-admin.button>
                </div>
            </form>
            <p class="cf-cmdbar__hint">Pulls the latest status for every in-flight shipment from each enabled courier, the same pull the scheduled automation runs.</p>
        </div>

        {{-- Segment bar: the spine phases, each with a mono count. Returns is
             a sibling page on the far side, not an eighth tab. --}}
        <div class="cf-segment-bar">
            <div class="cf-segment" role="tablist" aria-label="Fulfillment phase">
                @foreach ($phaseTabs as $key => $label)
                    <a href="{{ route('admin.fulfillment.index', array_filter(['filter' => $key, 'q' => request('q'), 'per_page' => request('per_page')], fn ($v) => $v !== null && $v !== '')) }}"
                       class="cf-segment__item {{ $filter === $key ? 'is-active' : '' }}"
                       @if ($filter === $key) aria-current="page" @endif>
                        <span>{{ $label }}</span>
                        <span class="cf-segment__count" data-count-for="{{ $key }}">{{ number_format($phaseCounts[$key] ?? 0) }}</span>
                    </a>
                @endforeach
            </div>
            @if (Route::has('admin.fulfillment.returns'))
                <a class="cf-segment-bar__aside" href="{{ route('admin.fulfillment.returns') }}">
                    Returns
                    <x-admin.icon name="chevron-right" class="w-3 h-3" />
                </a>
            @endif
        </div>

        {{-- Courier tally — the handover step is where the operator counts
             parcels per courier before each pickup arrives. --}}
        @if ($filter === 'to_handover' && !empty($handoverByCourier))
            <div class="co-couriers">
                <span class="co-couriers__label">Parcels waiting for handover</span>
                @foreach ($handoverByCourier as $courierName => $count)
                    <span class="co-courier">
                        <span class="co-courier__count">{{ number_format($count) }}</span>
                        {{ $courierName }}
                    </span>
                @endforeach
            </div>
        @endif

        {{-- Filter row: search + page size, one job each. --}}
        <form method="GET" action="{{ route('admin.fulfillment.index') }}" class="cf-filters">
            <input type="hidden" name="filter" value="{{ $filter }}">
            <x-admin.input type="search" name="q" :value="request('q')" placeholder="Order #, tracking, customer&hellip;" aria-label="Search orders" class="cf-filters__search" />
            <x-admin.select name="per_page" aria-label="Orders per page" class="cf-filters__select" @change="$event.target.form.submit()">
                @foreach ([10, 25, 50] as $n)
                    <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }} per page</option>
                @endforeach
            </x-admin.select>
            <x-admin.button type="submit" variant="secondary">Search</x-admin.button>
            @if (request('q'))
                <a class="cf-filters__reset" href="{{ route('admin.fulfillment.index', ['filter' => $filter, 'per_page' => $perPage]) }}">Reset</a>
            @endif
        </form>

        {{-- Two-tier table: a band of order-level facts above one row per
             item; total / courier / status / actions rowspanned beside them. --}}
        <div class="cf-table">
            <div class="cf-table__scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Products</th>
                            <th class="cf-col-qty">Qty</th>
                            <th class="cf-col-total">Total</th>
                            <th class="cf-col-courier">Courier</th>
                            <th class="cf-col-status">Status</th>
                            <th class="cf-col-act"><span class="cf-sr">Actions</span></th>
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

                            // The row's spine bucket + tone: the one shared colour system every
                            // courier's statuses collapse onto (Support\PhaseTone).
                            $spinePhase = \Extensions\CourierFulfillment\FulfillmentService::phaseFor($order);
                            $spineTone  = \Extensions\CourierFulfillment\Support\PhaseTone::toneFor($spinePhase);
                            $spineLabel = ucwords(str_replace('_', ' ', $spinePhase));

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
                                'statusName' => $order->status?->name ?? 'Missing',
                                'itemsCount' => $order->products_count ?? 0,
                                'activelyBooked' => $activelyBooked,
                                'rebookable' => $rebookable,
                                'cancelable' => $cancelable,
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
                            ];

                            // Legacy shape: an order with no recorded items still renders one
                            // empty item row under its band, rather than a band with nothing
                            // attached to it.
                            $items = $order->products;
                            $displayItems = $items->isNotEmpty() ? $items->all() : [null];
                            $lineCount = count($displayItems);
                        @endphp
                        <tr class="co-band">
                            <td colspan="6">
                                <div class="co-band__row">
                                    <div class="co-band__left">
                                        <span class="co-band__label">Customer</span>
                                        <span class="co-band__value">{{ trim(($order->shipping_first_name ?? '') . ' ' . ($order->shipping_last_name ?? '')) ?: '-' }}</span>
                                        <span class="co-band__label">Order</span>
                                        <a class="co-sn" href="{{ route('admin.orders.show', $order) }}">#{{ $order->id }}</a>
                                        <span class="co-band__muted">({{ $order->products_count ?? 0 }} {{ ($order->products_count ?? 0) === 1 ? 'item' : 'items' }})</span>
                                    </div>
                                    <div class="co-band__right">
                                        <span class="co-band__label">Created</span>
                                        <span class="co-band__value co-band__value--mono">{{ optional($order->created_at)->format('M d, Y g:ia') }}</span>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @foreach ($displayItems as $idx => $item)
                            <tr class="co-itemrow {{ $idx === $lineCount - 1 ? 'co-itemrow--last' : '' }}">
                                <td>
                                    @if ($item)
                                        <div class="co-item">
                                            @php($itemImage = $item->image ?? $item->product?->image)
                                            <div class="order-img-wrap">
                                                @if ($itemImage)
                                                    <img class="order-img" src="{{ storage_url($itemImage) }}" alt="">
                                                @else
                                                    <span class="co-item__none">No image</span>
                                                @endif
                                            </div>
                                            <div class="co-item__body">
                                                <span class="co-item__name">{{ $item->name ?: 'Unnamed product' }}</span>
                                                <div class="co-item__meta">
                                                    @if (trim((string) $item->sku) !== '')
                                                        <span class="co-item__sku">{{ $item->sku }}</span>
                                                    @endif
                                                    @if (trim((string) $item->variant_label) !== '')
                                                        <span class="co-item__var">{{ $item->variant_label }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <span class="co-empty-mark">No items recorded</span>
                                    @endif
                                </td>
                                <td class="cf-col-qty">
                                    @if ($item)
                                        <span class="co-qty">&times;{{ max(1, (int) $item->quantity) }}</span>
                                    @else
                                        <span class="co-empty-mark">-</span>
                                    @endif
                                </td>
                                @if ($idx === 0)
                                    <td class="cf-col-total" rowspan="{{ $lineCount }}">
                                        <span class="co-total">&#8369;{{ number_format($order->total, 2) }}</span>
                                        <span class="co-total__method">{{ $isCod ? 'COD' : ucwords(str_replace('_', ' ', (string) $order->payment_method)) }}</span>
                                    </td>
                                    <td class="cf-col-courier" rowspan="{{ $lineCount }}">
                                        @if ($order->courier_tracking_number)
                                            <span class="co-carrier">{{ $orderCourier?->getName() ?? strtoupper((string) $order->courier_provider) }}</span>
                                            @if ($trackingUrl)
                                                <a class="co-ship__no" href="{{ $trackingUrl }}" target="_blank" rel="noopener">{{ $order->courier_tracking_number }}</a>
                                            @else
                                                <span class="co-ship__no">{{ $order->courier_tracking_number }}</span>
                                            @endif
                                        @else
                                            <span class="co-empty-mark">-</span>
                                        @endif
                                    </td>
                                    <td class="cf-col-status" rowspan="{{ $lineCount }}">
                                        <div class="co-status">
                                            <x-admin.badge :tone="$spineTone">{{ $spineLabel }}</x-admin.badge>
                                            @if ($order->courier_status)
                                                <span class="co-status__note">{{ $humanizeStatus((string) $order->courier_status, $order->courier_provider) }}</span>
                                            @endif
                                            @if ($creationError)
                                                <span class="co-status__error" title="{{ $creationError }}">{{ \Illuminate\Support\Str::limit($creationError, 90) }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="cf-col-act" rowspan="{{ $lineCount }}">
                                        <div class="co-actions">
                                            @if ($creating)
                                                {{-- Task T4: honest "Creating…" state — a second Book
                                                     click here would hit FulfillmentService::book()'s
                                                     async-in-progress guard anyway, but showing an
                                                     enabled Book/Rebook button on an order that's
                                                     already mid-booking is misleading. Still opens the
                                                     drawer (same JSON payload every other row action
                                                     uses) so the operator can see what's happening. --}}
                                                <x-admin.button type="button" size="sm" variant="secondary" @click="openDrawer({{ \Illuminate\Support\Js::from($rowData) }})">Creating&hellip;</x-admin.button>
                                            @elseif (!$activelyBooked && !empty($courierOptions))
                                                {{-- Component-tag attributes only compile {{ }} echoes, not
                                                     @-directives — @js() here would render as literal text.
                                                     Js::from() is what @js() expands to; the {{ }} form is the
                                                     one that compiles on a component tag. --}}
                                                <x-admin.button type="button" size="sm" variant="primary" @click="openBooking({{ \Illuminate\Support\Js::from($rowData) }})">{{ $rebookable ? 'Rebook' : 'Book' }}</x-admin.button>
                                            @endif
                                            @if ($activelyBooked)
                                                <x-admin.button size="sm" variant="secondary" href="{{ route('admin.fulfillment.label', $order) }}" target="_blank" rel="noopener">Print waybill</x-admin.button>
                                            @endif
                                            {{-- Any row that has ever been booked keeps its drawer
                                                 access — a CANCELLED booking (tracking number present,
                                                 not actively booked) still has a timeline and the
                                                 terminal `cancelled` badge to show, and Rebook alone
                                                 would orphan both (review fix, W2). --}}
                                            @if ($order->courier_tracking_number)
                                                <x-admin.button type="button" size="sm" variant="secondary" @click="openDrawer({{ \Illuminate\Support\Js::from($rowData) }})">Tracking</x-admin.button>
                                            @elseif ($creationFailed)
                                                {{-- Final-review fix (Finding 2): a creation_failed order has NO
                                                     tracking number, so the Tracking button above can't carry it —
                                                     but its drawer is where the full creation_error reads, so it
                                                     gets its own opener. --}}
                                                <x-admin.button type="button" size="sm" variant="secondary" @click="openDrawer({{ \Illuminate\Support\Js::from($rowData) }})">Details</x-admin.button>
                                            @endif
                                            @if ($cancelable)
                                                <x-admin.button type="button" size="sm" variant="ghost" class="co-actions__danger" @click="$dispatch('open-modal', 'cancel-fulfillment-{{ $order->id }}')">Cancel</x-admin.button>
                                            @endif
                                        </div>
                                        @if ($cancelable)
                                            <x-admin.confirm-dialog name="cancel-fulfillment-{{ $order->id }}" title="Cancel booking for order #{{ $order->id }}?"
                                                message="This cancels the courier booking. The order remains rebookable afterward."
                                                confirm="Cancel booking" :action="route('admin.fulfillment.cancel', $order)" method="POST" />
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
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Pager: "1 to N of M" mono left, Prev/Next right. --}}
        @if ($orders->total() > 0)
            <div class="cf-pager">
                <span class="cf-pager__count">{{ number_format($orders->firstItem() ?? 0) }} to {{ number_format($orders->lastItem() ?? 0) }} of {{ number_format($orders->total()) }}</span>
                <div class="cf-pager__nav">
                    @if ($orders->previousPageUrl())
                        <x-admin.button size="sm" variant="secondary" href="{{ $orders->previousPageUrl() }}">Previous</x-admin.button>
                    @else
                        <span class="v-btn inline-flex items-center justify-center gap-1.5 font-medium rounded-v-md h-[var(--control-h-sm)] px-2.5 text-xs bg-v-surface text-v-text border border-v-border" aria-disabled="true">Previous</span>
                    @endif
                    @if ($orders->nextPageUrl())
                        <x-admin.button size="sm" variant="secondary" href="{{ $orders->nextPageUrl() }}">Next</x-admin.button>
                    @else
                        <span class="v-btn inline-flex items-center justify-center gap-1.5 font-medium rounded-v-md h-[var(--control-h-sm)] px-2.5 text-xs bg-v-surface text-v-text border border-v-border" aria-disabled="true">Next</span>
                    @endif
                </div>
            </div>
        @endif

        {{-- Detail drawer — the VIEWING surface (stepper, timeline, tracking,
             cancel). Booking happens in the modal below, never here. --}}
        <div x-show="drawerOpen" x-cloak class="fixed inset-0 z-50" @keydown.escape.window="closeDrawer()">
            <div class="absolute inset-0 bg-black/40" x-show="drawerOpen" x-transition.opacity @click="closeDrawer()"></div>

            <div class="absolute inset-y-0 right-0 w-full max-w-md bg-v-surface border-l border-v-border shadow-2xl flex flex-col"
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
                            <button type="button" @click="closeDrawer()" class="text-v-muted hover:text-v-text text-xl leading-none">&times;</button>
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
                                <h4 class="text-xs font-semibold text-v-muted uppercase tracking-wider mb-2">Tracking history</h4>
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
                                    <p class="mt-1 text-xs text-v-muted">This usually takes a few seconds. Use "Poll courier statuses" or refresh the page shortly to see the tracking number.</p>
                                </div>
                            </template>
                            @if (!empty($courierOptions))
                                <template x-if="!active.activelyBooked && !active.creating">
                                    <div class="space-y-3">
                                        {{-- Final-review fix (Finding 2): the async create's permanent
                                             failure reason (courier_meta.creation_error) — surfaced here
                                             with the Book button right below it, since a creation_failed
                                             order MUST be rebookable. --}}
                                        <template x-if="active.creationFailed">
                                            <div class="rounded-v-md border border-v-danger bg-[color-mix(in_srgb,var(--danger)_10%,transparent)] px-3 py-2 text-xs text-v-danger">
                                                <span class="font-semibold">Booking failed:</span>
                                                <span x-text="active.creationError || 'the courier could not create this shipment.'"></span>
                                                Fix the cause and book again below.
                                            </div>
                                        </template>
                                        <template x-if="active.rebookable">
                                            <div class="rounded-v-md bg-v-sunken border border-v-border px-3 py-2 text-xs text-v-muted">
                                                Previous booking <span class="v-num" x-text="active.trackingNumber"></span> was canceled. Booking again creates a new shipment.
                                            </div>
                                        </template>
                                        <x-admin.button type="button" variant="primary" class="w-full" @click="closeDrawer(); openBooking(active)">Book shipment</x-admin.button>
                                    </div>
                                </template>
                            @else
                                <template x-if="!active.activelyBooked">
                                    <div class="rounded-v-md border border-v-warning bg-[color-mix(in_srgb,var(--warning)_12%,transparent)] px-4 py-3 text-sm text-v-warning">
                                        No courier is enabled yet.
                                        <a href="{{ route('admin.fulfillment.settings') }}" class="font-semibold underline">Configure a courier &rarr;</a>
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
                                                <a :href="active.trackingUrl" target="_blank" rel="noopener" class="text-v-accent hover:underline text-sm">Track shipment &rarr;</a>
                                            </div>
                                        </template>
                                    </div>

                                    <div class="flex flex-col gap-2">
                                        <a :href="labelUrl" target="_blank" rel="noopener" class="text-center px-4 py-2 border border-v-border text-v-text text-sm font-semibold rounded-v-md hover:bg-v-hover">Print AWB</a>
                                        <form :action="trackAction" method="POST" data-v-form>
                                            @csrf
                                            <x-admin.button type="submit" variant="secondary" class="w-full">Refresh status</x-admin.button>
                                        </form>
                                        <template x-if="active.cancelable">
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
             reachable — see the step-1 markup below. --}}
        @if (!empty($courierOptions))
            <div x-show="bookOpen" x-cloak class="fixed inset-0 z-[90] flex items-center justify-center p-4" @keydown.escape.window="closeBooking()">
                <div class="absolute inset-0 bg-black/50" @click="closeBooking()"></div>
                <div x-show="bookOpen" x-transition
                     class="relative w-full max-w-md bg-v-surface border border-v-border rounded-v-lg shadow-2xl max-h-full overflow-y-auto"
                     role="dialog" aria-modal="true" :aria-label="'Book order #' + (active ? active.id : '')">
                    <template x-if="active">
                        <div>
                            <header class="flex items-center justify-between px-5 pt-4">
                                <h2 class="text-[15px] font-semibold text-v-text">Book order <span class="v-num" x-text="'#' + active.id"></span></h2>
                                <button type="button" class="text-v-muted hover:text-v-text" @click="closeBooking()">
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
                                    <div class="cf-choice">
                                        @foreach ($courierOptions as $key => $opt)
                                            @if ($opt['configured'])
                                                <button type="button" class="cf-choice__card" data-courier-card="{{ $key }}" @click="chooseCourier('{{ $key }}')">
                                                    <span>
                                                        <span class="cf-choice__name">{{ $opt['name'] }}</span>
                                                        @if (!empty($opt['mode']) && $opt['mode'] !== 'production')
                                                            <span class="cf-choice__mode">{{ $opt['mode'] }}</span>
                                                        @endif
                                                        @if (!empty($opt['fee_estimate']))
                                                            <span class="cf-choice__fee">{{ $opt['fee_estimate']->label ?? ($opt['fee_estimate']->currency . ' ' . number_format($opt['fee_estimate']->amount, 2)) }}</span>
                                                        @endif
                                                    </span>
                                                    <x-admin.icon name="chevron-right" class="w-4 h-4 cf-choice__go" />
                                                </button>
                                            @else
                                                <div class="cf-choice__card cf-choice__card--unconfigured" data-courier-card="{{ $key }}" data-courier-configured="false">
                                                    <span class="cf-choice__unconfigured-body">
                                                        <span class="cf-choice__unconfigured-head">
                                                            <span class="cf-choice__name">{{ $opt['name'] }}</span>
                                                            <span class="cf-choice__badge-unconfigured">Not configured</span>
                                                        </span>
                                                        <span class="cf-choice__unconfigured-msg">
                                                            @if (!empty($opt['configIssues']))
                                                                Missing {{ implode(' / ', $opt['configIssues']) }}.
                                                            @endif
                                                            Configure in <a href="{{ $opt['settingsUrl'] }}" class="cf-choice__unconfigured-link">Settings &rarr; Courier Fulfillment &rarr; {{ $opt['name'] }}</a>.
                                                        </span>
                                                    </span>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>

                                {{-- Step 2: the chosen courier's form, from its option methods.
                                     For QuadX this is EXACTLY the pre-modal field set — courier,
                                     service, parcel, insure, shipping_payment — posting to the
                                     same route, so the request the server receives is unchanged. --}}
                                <div x-show="step === 2">
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
                                            <x-admin.field name="service" label="Service">
                                                <x-admin.select name="service">
                                                    <template x-for="o in current.services" :key="o.value"><option :value="o.value" x-text="o.label"></option></template>
                                                </x-admin.select>
                                            </x-admin.field>
                                            <x-admin.field name="parcel" label="Parcel">
                                                <x-admin.select name="parcel" x-model="parcel">
                                                    <template x-for="o in current.parcels" :key="o.value"><option :value="o.value" x-text="o.label"></option></template>
                                                </x-admin.select>
                                            </x-admin.field>
                                        </div>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <x-admin.toggle name="insure" />
                                            <span class="text-[13px] text-v-text">Insure shipment (&asymp;1% fee, declared at order total)</span>
                                        </label>
                                        <div class="rounded-v-md bg-v-sunken border border-v-border px-3 py-2">
                                            <div class="text-xs font-medium text-v-muted mb-0.5">Buyer pays for goods</div>
                                            <div class="text-sm text-v-text" x-text="active.buyerPaymentLabel"></div>
                                        </div>
                                        <x-admin.field name="shipping_payment" label="You pay shipping">
                                            <x-admin.select name="shipping_payment">
                                                <template x-for="o in current.payments" :key="o.value"><option :value="o.value" x-text="o.label"></option></template>
                                            </x-admin.select>
                                        </x-admin.field>

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
                // Task T4: SPX-only step-2 state. Fetched LAZILY (never on
                // page load — see FulfillmentController::index()'s
                // courierOptions comment for why) so a queue page visit
                // never costs an outbound SPX call.
                spxPickupSlots: [],
                spxPickupSlotsLoading: false,
                spxEstimate: null,
                spxEstimateError: null,
                spxEstimateLoading: false,
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
                get trackAction() { return this.urlBase + '/' + (this.active ? this.active.id : '') + '/track'; },
                get labelUrl() { return this.urlBase + '/' + (this.active ? this.active.id : '') + '/label'; },
                get estimateAction() { return this.urlBase + '/' + (this.active ? this.active.id : '') + '/estimate'; },
                get pickupSlotsUrl() { return this.urlBase + '/' + (this.active ? this.active.id : '') + '/pickup-slots'; },
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
                    this.step = 1;
                    this.bookOpen = true;
                    this.resetSpxState();
                },
                chooseCourier(key) {
                    this.courier = key;
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
                },
                closeBooking() {
                    this.bookOpen = false;
                    this.step = 1;
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
