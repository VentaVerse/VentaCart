<x-admin.shell title="Fulfillment">
    <x-admin.page title="Fulfillment">
        <x-slot:actions>
            <x-admin.button variant="secondary" size="sm" href="{{ route('admin.fulfillment.settings') }}">Courier settings</x-admin.button>
        </x-slot:actions>

    @php
        // Recursively hunt for the first http(s) URL inside courier_meta.booked_response.tracking_links —
        // couriers return this in wildly different shapes (string, nested array, or null).
        $extractTrackingUrl = function ($value) use (&$extractTrackingUrl) {
            if (is_string($value)) {
                return str_starts_with($value, 'http') ? $value : null;
            }
            if (is_array($value)) {
                foreach ($value as $v) {
                    if ($found = $extractTrackingUrl($v)) {
                        return $found;
                    }
                }
            }
            return null;
        };

        // Statuses the primary courier still allows canceling from — computed once,
        // not per row. A null/unknown current status is always cancelable (fresh booking).
        $cancelableStatuses = $primary?->cancelableStatuses() ?? [];

        // Cached QuadX status labels (populated by the "Refresh from QuadX" button on
        // the settings page) — same source the settings screen uses, never a live call.
        $cachedLabelsRaw = \App\Models\Setting::where('group', 'courier')->where('key', 'quadx_status_labels')->value('value');
        $cachedLabels = $cachedLabelsRaw ? (json_decode($cachedLabelsRaw, true) ?: []) : [];
        $cachedLabels = is_array($cachedLabels) ? $cachedLabels : [];
        $humanizeStatus = function (string $status) use ($cachedLabels) {
            return $cachedLabels[$status] ?? ucwords(str_replace('_', ' ', $status));
        };
    @endphp

    <div x-data="fulfillment()" class="space-y-4">

        @if (empty($couriers))
            <div class="rounded-v-md border border-v-warning bg-[color-mix(in_srgb,var(--warning)_12%,transparent)] px-4 py-3 text-[13px] text-v-warning">
                No courier is enabled yet.
                <a href="{{ route('admin.fulfillment.settings') }}" class="font-semibold underline">Configure a courier &rarr;</a>
            </div>
        @endif

        {{-- Filters --}}
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
            <div class="flex flex-wrap gap-2" role="tablist" aria-label="Filter fulfillment queue">
                @foreach (['to_book' => 'To Book', 'in_transit' => 'In Transit', 'delivered' => 'Delivered', 'all' => 'All'] as $key => $label)
                    <a href="{{ route('admin.fulfillment.index', ['filter' => $key, 'q' => request('q')]) }}"
                       class="inline-flex items-center whitespace-nowrap rounded-full border px-3 py-1.5 text-[13px] font-medium transition-colors
                           {{ $filter === $key ? 'border-v-accent bg-v-accent-weak text-v-accent' : 'border-v-border bg-v-surface text-v-secondary hover:bg-v-hover hover:text-v-text' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('admin.fulfillment.index') }}" class="flex items-center gap-2">
                <input type="hidden" name="filter" value="{{ $filter }}">
                <x-admin.input type="text" name="q" :value="request('q')" placeholder="Search order #, tracking, customer…" class="w-64" />
                <x-admin.button type="submit" variant="secondary" size="sm">Search</x-admin.button>
            </form>
        </div>

        {{-- Orders table --}}
        <x-admin.table>
            <x-slot:head>
                <x-admin.table.th>Order</x-admin.table.th>
                <x-admin.table.th>Customer</x-admin.table.th>
                <x-admin.table.th>Order Status</x-admin.table.th>
                <x-admin.table.th>Items</x-admin.table.th>
                <x-admin.table.th>Progress</x-admin.table.th>
                <x-admin.table.th>Tracking/Status</x-admin.table.th>
                <x-admin.table.th align="right">Action</x-admin.table.th>
            </x-slot:head>
            @forelse ($orders as $order)
                @php
                    $meta = $order->courier_meta ? (json_decode($order->courier_meta, true) ?: []) : [];
                    $phase = $order->courier_tracking_number
                        ? ($primary?->statusPhase($order->courier_status) ?? ['step' => 0, 'failed' => false])
                        : ['step' => 0, 'failed' => false];

                    // URL preference: fresh courier_meta.tracking_links (delivery, then
                    // pickup) persisted on refresh, else fall back to whatever shape the
                    // provider returned at booking time inside booked_response.
                    $trackingUrl = null;
                    $metaLinks = $meta['tracking_links'] ?? null;
                    if (is_array($metaLinks)) {
                        foreach ([$metaLinks['delivery'] ?? null, $metaLinks['pickup'] ?? null] as $candidate) {
                            if (is_string($candidate) && str_starts_with($candidate, 'http')) {
                                $trackingUrl = $candidate;
                                break;
                            }
                        }
                    }
                    if (!$trackingUrl) {
                        $trackingUrl = $extractTrackingUrl($meta['booked_response']['tracking_links'] ?? null);
                    }

                    $estimatedDelivery = $meta['booked_response']['estimated_delivery_date'] ?? null;
                    // Canceled bookings are not terminal — a tracking number is still
                    // present but the order is rebookable (see FulfillmentService).
                    $activelyBooked = \Extensions\CourierFulfillment\FulfillmentService::isActivelyBooked($order);
                    $rebookable = (bool) $order->courier_tracking_number && !$activelyBooked;
                    // A null/unknown courier_status is always cancelable (fresh booking) —
                    // otherwise it must be one of the courier's own cancelable statuses.
                    $cancelable = $activelyBooked
                        && (!$order->courier_status || in_array($order->courier_status, $cancelableStatuses, true));

                    $timelineRaw = $meta['timeline'] ?? [];
                    $timeline = [];
                    if (is_array($timelineRaw)) {
                        foreach ($timelineRaw as $event) {
                            if (!is_array($event) || !isset($event['status'], $event['at']) || !is_int($event['at'])) {
                                continue;
                            }
                            $timeline[] = [
                                'label' => $humanizeStatus((string) $event['status']),
                                'at' => \Carbon\Carbon::createFromTimestamp($event['at'])->timezone(config('app.timezone'))->format('M j, Y g:ia'),
                            ];
                        }
                    }

                    // Buyer payment is derived from the order, never chosen by the
                    // admin — prevents mis-shipping a COD order as prepaid.
                    $isCod = \Extensions\CourierFulfillment\FulfillmentService::isCodOrder($order);
                    $buyerPaymentLabel = $isCod
                        ? 'COD — rider collects ₱' . number_format($order->total, 2)
                        : 'Prepaid — no collection';

                    $rowData = [
                        'id' => $order->id,
                        'date' => optional($order->created_at)->format('M d, Y g:ia'),
                        'customerName' => trim(($order->shipping_first_name ?? '') . ' ' . ($order->shipping_last_name ?? '')) ?: '—',
                        'address' => trim(collect([$order->shipping_address_1, $order->shipping_city, $order->shipping_state])->filter()->implode(', ')) ?: '—',
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
                        'phaseStep' => $phase['step'],
                        'phaseFailed' => $phase['failed'],
                        'timeline' => $timeline,
                        'buyerPaymentLabel' => $buyerPaymentLabel,
                    ];
                @endphp
                <x-admin.table.row class="cursor-pointer" @click="openDrawer(@js($rowData))">
                    <x-admin.table.cell>
                        <div class="v-num font-medium text-v-text">#{{ $order->id }}</div>
                        <div class="text-[11px] text-v-muted">{{ optional($order->created_at)->format('M d, Y') }}</div>
                    </x-admin.table.cell>
                    <x-admin.table.cell class="text-v-secondary">{{ trim($order->shipping_first_name . ' ' . $order->shipping_last_name) ?: '—' }}</x-admin.table.cell>
                    <x-admin.table.cell>
                        <x-admin.badge :tone="$order->order_status_id ? 'neutral' : 'danger'">
                            {{ $order->status?->name ?? 'Missing' }}
                        </x-admin.badge>
                    </x-admin.table.cell>
                    <x-admin.table.cell class="text-v-secondary">{{ $order->products_count ?? 0 }} item(s)</x-admin.table.cell>
                    <x-admin.table.cell>
                        <div class="flex items-center gap-0.5" title="{{ $order->courier_status ?: 'not booked' }}">
                            @for ($i = 0; $i < 4; $i++)
                                <span class="w-2.5 h-2.5 rounded-full {{ $i <= $phase['step'] ? ($phase['failed'] && $i === $phase['step'] ? 'bg-v-danger' : 'bg-v-accent') : 'bg-v-strong' }}"></span>
                                @if ($i < 3)<span class="w-4 h-px {{ $i < $phase['step'] ? 'bg-v-accent' : 'bg-v-border' }}"></span>@endif
                            @endfor
                        </div>
                    </x-admin.table.cell>
                    <x-admin.table.cell>
                        @if ($order->courier_tracking_number)
                            @if ($trackingUrl)
                                <a href="{{ $trackingUrl }}" target="_blank" rel="noopener" @click.stop class="v-num text-v-accent hover:underline text-xs">{{ $order->courier_tracking_number }}</a>
                            @else
                                <div class="v-num text-v-text text-xs">{{ $order->courier_tracking_number }}</div>
                            @endif
                            <div class="text-[11px] text-v-muted">{{ strtoupper($order->courier_provider) }} &middot; {{ $order->courier_status ?: 'booked' }}</div>
                        @else
                            <span class="text-v-muted">—</span>
                        @endif
                    </x-admin.table.cell>
                    <x-admin.table.cell align="right">
                        <div class="whitespace-nowrap">
                            @if ($activelyBooked)
                                <a href="{{ route('admin.fulfillment.label', $order) }}" target="_blank" rel="noopener" @click.stop class="text-v-accent hover:underline text-xs mr-2">Print AWB</a>
                                <form method="POST" action="{{ route('admin.fulfillment.track', $order) }}" data-v-form class="inline">
                                    @csrf
                                    <button type="submit" @click.stop class="text-v-secondary hover:text-v-text text-xs mr-2">Refresh</button>
                                </form>
                                @if ($cancelable)
                                    <button type="button" @click.stop="$dispatch('open-modal', 'cancel-fulfillment-{{ $order->id }}')" class="text-v-danger hover:opacity-80 text-xs">Cancel</button>
                                @endif
                            @elseif (!empty($couriers))
                                <x-admin.button type="button" size="sm" variant="primary" @click.stop="openDrawer(@js($rowData))">{{ $rebookable ? 'Rebook' : 'Book' }}</x-admin.button>
                            @endif
                        </div>
                        @if ($cancelable)
                            <x-admin.confirm-dialog name="cancel-fulfillment-{{ $order->id }}" title="Cancel booking for order #{{ $order->id }}?"
                                message="This cancels the courier booking. The order remains rebookable afterward."
                                confirm="Cancel booking" :action="route('admin.fulfillment.cancel', $order)" method="POST" />
                        @endif
                    </x-admin.table.cell>
                </x-admin.table.row>
            @empty
                <tr>
                    <td colspan="7" class="px-4 py-2">
                        <x-admin.empty-state icon="fulfillment" title="No orders in this view." />
                    </td>
                </tr>
            @endforelse
            @if ($orders->hasPages())
                <x-slot:footer>
                    {{ $orders->links() }}
                </x-slot:footer>
            @endif
        </x-admin.table>

        {{-- Detail drawer --}}
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

                        {{-- Big stepper --}}
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
                        </div>

                        {{-- Tracking history --}}
                        <template x-if="active.activelyBooked && active.timeline && active.timeline.length">
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

                        {{-- Booking / tracking body --}}
                        <div class="px-5 py-4 flex-1 overflow-y-auto">
                            @if (!empty($couriers))
                                <template x-if="!active.activelyBooked">
                                    <form :action="bookAction" method="POST" data-v-form class="space-y-4">
                                        @csrf
                                        <template x-if="active.rebookable">
                                            <div class="rounded-v-md bg-v-sunken border border-v-border px-3 py-2 text-xs text-v-muted">
                                                Previous booking <span class="v-num" x-text="active.trackingNumber"></span> was canceled — booking again creates a new shipment.
                                            </div>
                                        </template>
                                        <x-admin.field name="courier" label="Courier">
                                            <x-admin.select name="courier" x-model="courier">
                                                <template x-for="(opt, key) in options" :key="key">
                                                    <option :value="key" x-text="opt.name"></option>
                                                </template>
                                            </x-admin.select>
                                        </x-admin.field>
                                        <div class="grid grid-cols-2 gap-3">
                                            <x-admin.field name="service" label="Service">
                                                <x-admin.select name="service">
                                                    <template x-for="o in current.services" :key="o.value"><option :value="o.value" x-text="o.label"></option></template>
                                                </x-admin.select>
                                            </x-admin.field>
                                            <x-admin.field name="parcel" label="Parcel">
                                                <x-admin.select name="parcel">
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
                                        <x-admin.button type="submit" variant="primary" class="w-full">Book Shipment</x-admin.button>
                                    </form>
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
                                            <span class="font-medium text-v-text" x-text="active.courierStatus || '—'"></span>
                                        </div>
                                        <template x-if="active.estimatedDelivery">
                                            <div class="flex justify-between text-sm">
                                                <span class="text-v-muted">Est. delivery</span>
                                                <span class="font-medium text-v-text" x-text="active.estimatedDelivery"></span>
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
    </div>

    @push('scripts')
    <script>
        function fulfillment() {
            return {
                drawerOpen: false,
                active: null,
                courier: @js(array_key_first($courierOptions) ?? ''),
                options: @js($courierOptions),
                urlBase: '{{ url(config('admin.prefix', 'admin') . '/sales/fulfillment') }}',
                get current() { return this.options[this.courier] ?? {services:[],parcels:[],payments:[]}; },
                get bookAction() { return this.urlBase + '/' + (this.active ? this.active.id : '') + '/book'; },
                get trackAction() { return this.urlBase + '/' + (this.active ? this.active.id : '') + '/track'; },
                get cancelAction() { return this.urlBase + '/' + (this.active ? this.active.id : '') + '/cancel'; },
                get labelUrl() { return this.urlBase + '/' + (this.active ? this.active.id : '') + '/label'; },
                openDrawer(order) {
                    this.active = order;
                    this.courier = @js(array_key_first($courierOptions) ?? '');
                    this.drawerOpen = true;
                },
                closeDrawer() {
                    this.drawerOpen = false;
                },
            };
        }
    </script>
    @endpush
    </x-admin.page>
</x-admin.shell>
