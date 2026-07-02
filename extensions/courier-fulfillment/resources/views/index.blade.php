<x-admin-layout>
    <x-slot name="header">Fulfillment</x-slot>

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

        {{-- Flash --}}
        @if (session('success'))
            <div class="rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3">{{ session('error') }}</div>
        @endif

        @if (empty($couriers))
            <div class="rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm px-4 py-3">
                No courier is enabled yet.
                <a href="{{ route('admin.fulfillment.settings') }}" class="font-semibold underline">Configure a courier →</a>
            </div>
        @endif

        {{-- Header + filters --}}
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
            <div class="inline-flex rounded-lg border border-gray-200 bg-white p-1 text-sm">
                @foreach (['to_book' => 'To Book', 'in_transit' => 'In Transit', 'delivered' => 'Delivered', 'all' => 'All'] as $key => $label)
                    <a href="{{ route('admin.fulfillment.index', ['filter' => $key, 'q' => request('q')]) }}"
                       class="px-3 py-1.5 rounded-md whitespace-nowrap {{ $filter === $key ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-50' }}">{{ $label }}</a>
                @endforeach
            </div>

            <div class="flex items-center gap-3">
                <form method="GET" action="{{ route('admin.fulfillment.index') }}" class="flex items-center gap-2">
                    <input type="hidden" name="filter" value="{{ $filter }}">
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Search order #, tracking, customer…"
                        class="w-64 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <button type="submit" class="px-3 py-2 bg-gray-800 text-white text-xs font-semibold rounded-lg hover:bg-gray-700">Search</button>
                </form>
                <a href="{{ route('admin.fulfillment.settings') }}" class="text-sm text-indigo-600 hover:underline whitespace-nowrap">Courier settings</a>
            </div>
        </div>

        {{-- Orders table --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-[11px] font-semibold text-gray-400 uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-2.5 text-left">Order</th>
                        <th class="px-4 py-2.5 text-left">Customer</th>
                        <th class="px-4 py-2.5 text-left">Order Status</th>
                        <th class="px-4 py-2.5 text-left">Items</th>
                        <th class="px-4 py-2.5 text-left">Progress</th>
                        <th class="px-4 py-2.5 text-left">Tracking/Status</th>
                        <th class="px-4 py-2.5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
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
                        <tr class="hover:bg-gray-50 cursor-pointer" @click="openDrawer(@js($rowData))">
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900">#{{ $order->id }}</div>
                                <div class="text-[11px] text-gray-400">{{ optional($order->created_at)->format('M d, Y') }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ trim($order->shipping_first_name . ' ' . $order->shipping_last_name) ?: '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $order->order_status_id ? 'bg-gray-100 text-gray-800' : 'bg-red-50 text-red-600' }}">
                                    {{ $order->status?->name ?? 'Missing' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $order->products_count ?? 0 }} item(s)</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-0.5" title="{{ $order->courier_status ?: 'not booked' }}">
                                    @for ($i = 0; $i < 4; $i++)
                                        <span class="w-2.5 h-2.5 rounded-full {{ $i <= $phase['step'] ? ($phase['failed'] && $i === $phase['step'] ? 'bg-red-500' : 'bg-emerald-500') : 'bg-gray-200' }}"></span>
                                        @if ($i < 3)<span class="w-4 h-px {{ $i < $phase['step'] ? 'bg-emerald-500' : 'bg-gray-200' }}"></span>@endif
                                    @endfor
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                @if ($order->courier_tracking_number)
                                    @if ($trackingUrl)
                                        <a href="{{ $trackingUrl }}" target="_blank" rel="noopener" @click.stop class="text-indigo-600 hover:underline font-mono text-xs">{{ $order->courier_tracking_number }}</a>
                                    @else
                                        <div class="text-gray-900 font-mono text-xs">{{ $order->courier_tracking_number }}</div>
                                    @endif
                                    <div class="text-[11px] text-gray-500">{{ strtoupper($order->courier_provider) }} · {{ $order->courier_status ?: 'booked' }}</div>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                @if ($activelyBooked)
                                    <a href="{{ route('admin.fulfillment.label', $order) }}" target="_blank" rel="noopener" @click.stop class="text-indigo-600 hover:underline text-xs mr-2">Print AWB</a>
                                    <form method="POST" action="{{ route('admin.fulfillment.track', $order) }}" class="inline">@csrf<button @click.stop class="text-gray-500 hover:text-gray-700 text-xs mr-2">Refresh</button></form>
                                    @if ($cancelable)
                                        <form method="POST" action="{{ route('admin.fulfillment.cancel', $order) }}" class="inline" onsubmit="return confirm('Cancel this booking?')">@csrf<button @click.stop class="text-red-500 hover:text-red-700 text-xs">Cancel</button></form>
                                    @endif
                                @elseif (!empty($couriers))
                                    <button type="button" @click.stop="openDrawer(@js($rowData))"
                                        class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700">{{ $rebookable ? 'Rebook' : 'Book' }}</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">No orders in this view.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $orders->links() }}</div>

        {{-- Detail drawer --}}
        <div x-show="drawerOpen" x-cloak class="fixed inset-0 z-50" @keydown.escape.window="closeDrawer()">
            <div class="absolute inset-0 bg-black/40" x-show="drawerOpen" x-transition.opacity @click="closeDrawer()"></div>

            <div class="absolute inset-y-0 right-0 w-full max-w-md bg-white shadow-xl flex flex-col"
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
                        <div class="px-5 py-4 border-b border-gray-100 flex items-start justify-between">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900">Order <span x-text="'#' + active.id"></span></h3>
                                <p class="text-xs text-gray-500 mt-0.5" x-text="active.date"></p>
                            </div>
                            <button type="button" @click="closeDrawer()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
                        </div>

                        {{-- Big stepper --}}
                        <div class="px-5 py-4 border-b border-gray-100">
                            <div class="flex items-center">
                                @foreach (['Pending', 'Booked', 'Shipped', 'Delivered'] as $i => $label)
                                    <div class="flex flex-col items-center flex-none" style="width: 3rem;">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold"
                                             :class="{{ $i }} <= active.phaseStep ? (active.phaseFailed && {{ $i }} === active.phaseStep ? 'bg-red-500 text-white' : 'bg-emerald-500 text-white') : 'bg-gray-200 text-gray-400'">
                                            {{ $i + 1 }}
                                        </div>
                                        <span class="mt-1 text-[10px] text-gray-500 text-center">{{ $label }}</span>
                                    </div>
                                    @if ($i < 3)
                                        <div class="flex-1 h-px mb-4" :class="{{ $i }} < active.phaseStep ? 'bg-emerald-500' : 'bg-gray-200'"></div>
                                    @endif
                                @endforeach
                            </div>
                        </div>

                        {{-- Tracking history --}}
                        <template x-if="active.activelyBooked && active.timeline && active.timeline.length">
                            <div class="px-5 py-4 border-b border-gray-100">
                                <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Tracking history</h4>
                                <ul class="space-y-1.5">
                                    <template x-for="(event, idx) in active.timeline" :key="idx">
                                        <li class="flex items-baseline justify-between gap-3 text-sm">
                                            <span class="text-gray-800" x-text="event.label"></span>
                                            <span class="text-xs text-gray-400 whitespace-nowrap" x-text="event.at"></span>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </template>

                        {{-- Summary --}}
                        <div class="px-5 py-4 border-b border-gray-100 space-y-2 text-sm">
                            <div class="flex justify-between gap-4">
                                <span class="text-gray-500 shrink-0">Customer</span>
                                <span class="font-medium text-gray-900 text-right" x-text="active.customerName"></span>
                            </div>
                            <div class="flex justify-between gap-4">
                                <span class="text-gray-500 shrink-0">Order status</span>
                                <span class="font-medium text-gray-900 text-right" x-text="active.statusName"></span>
                            </div>
                            <div class="flex justify-between gap-4">
                                <span class="text-gray-500 shrink-0">Items</span>
                                <span class="font-medium text-gray-900 text-right" x-text="active.itemsCount + ' item(s)'"></span>
                            </div>
                            <div class="flex justify-between gap-4">
                                <span class="text-gray-500 shrink-0">Ship to</span>
                                <span class="font-medium text-gray-900 text-right" x-text="active.address"></span>
                            </div>
                        </div>

                        {{-- Booking / tracking body --}}
                        <div class="px-5 py-4 flex-1 overflow-y-auto">
                            @if (!empty($couriers))
                                <template x-if="!active.activelyBooked">
                                    <form :action="bookAction" method="POST" class="space-y-4">
                                        @csrf
                                        <template x-if="active.rebookable">
                                            <div class="rounded-lg bg-gray-50 border border-gray-200 px-3 py-2 text-xs text-gray-500">
                                                Previous booking <span class="font-mono" x-text="active.trackingNumber"></span> was canceled — booking again creates a new shipment.
                                            </div>
                                        </template>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 mb-1">Courier</label>
                                            <select name="courier" x-model="courier" class="w-full rounded-lg border-gray-300 text-sm">
                                                <template x-for="(opt, key) in options" :key="key">
                                                    <option :value="key" x-text="opt.name"></option>
                                                </template>
                                            </select>
                                        </div>
                                        <div class="grid grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-500 mb-1">Service</label>
                                                <select name="service" class="w-full rounded-lg border-gray-300 text-sm">
                                                    <template x-for="o in current.services" :key="o.value"><option :value="o.value" x-text="o.label"></option></template>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-500 mb-1">Parcel</label>
                                                <select name="parcel" class="w-full rounded-lg border-gray-300 text-sm">
                                                    <template x-for="o in current.parcels" :key="o.value"><option :value="o.value" x-text="o.label"></option></template>
                                                </select>
                                            </div>
                                        </div>
                                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                            <input type="hidden" name="insure" value="0">
                                            <input type="checkbox" name="insure" value="1" class="rounded border-gray-300 text-indigo-600">
                                            Insure shipment (&asymp;1% fee, declared at order total)
                                        </label>
                                        <div class="rounded-lg bg-gray-50 border border-gray-200 px-3 py-2">
                                            <div class="text-xs font-medium text-gray-500 mb-0.5">Buyer pays for goods</div>
                                            <div class="text-sm text-gray-700" x-text="active.buyerPaymentLabel"></div>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 mb-1">You pay shipping</label>
                                            <select name="shipping_payment" class="w-full rounded-lg border-gray-300 text-sm">
                                                <template x-for="o in current.payments" :key="o.value"><option :value="o.value" x-text="o.label"></option></template>
                                            </select>
                                        </div>
                                        <button type="submit" class="w-full px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700">Book Shipment</button>
                                    </form>
                                </template>
                            @else
                                <template x-if="!active.activelyBooked">
                                    <div class="rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm px-4 py-3">
                                        No courier is enabled yet.
                                        <a href="{{ route('admin.fulfillment.settings') }}" class="font-semibold underline">Configure a courier →</a>
                                    </div>
                                </template>
                            @endif

                            <template x-if="active.activelyBooked">
                                <div class="space-y-4">
                                    <div class="rounded-lg bg-gray-50 border border-gray-200 px-4 py-3 space-y-2">
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-500">Tracking #</span>
                                            <template x-if="active.trackingUrl">
                                                <a :href="active.trackingUrl" target="_blank" rel="noopener" class="font-mono font-medium text-indigo-600 hover:underline" x-text="active.trackingNumber"></a>
                                            </template>
                                            <template x-if="!active.trackingUrl">
                                                <span class="font-mono font-medium text-gray-900" x-text="active.trackingNumber"></span>
                                            </template>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-500">Courier</span>
                                            <span class="font-medium text-gray-900" x-text="active.provider"></span>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-500">Status</span>
                                            <span class="font-medium text-gray-900" x-text="active.courierStatus || '—'"></span>
                                        </div>
                                        <template x-if="active.estimatedDelivery">
                                            <div class="flex justify-between text-sm">
                                                <span class="text-gray-500">Est. delivery</span>
                                                <span class="font-medium text-gray-900" x-text="active.estimatedDelivery"></span>
                                            </div>
                                        </template>
                                        <template x-if="active.trackingUrl">
                                            <div class="pt-1">
                                                <a :href="active.trackingUrl" target="_blank" rel="noopener" class="text-indigo-600 hover:underline text-sm">Track shipment →</a>
                                            </div>
                                        </template>
                                    </div>

                                    <div class="flex flex-col gap-2">
                                        <a :href="labelUrl" target="_blank" rel="noopener" class="text-center px-4 py-2 border border-gray-300 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-50">Print AWB</a>
                                        <form :action="trackAction" method="POST">
                                            @csrf
                                            <button type="submit" class="w-full px-4 py-2 border border-gray-300 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-50">Refresh status</button>
                                        </form>
                                        <template x-if="active.cancelable">
                                            <form :action="cancelAction" method="POST" onsubmit="return confirm('Cancel this booking?')">
                                                @csrf
                                                <button type="submit" class="w-full px-4 py-2 border border-red-200 text-red-600 text-sm font-semibold rounded-lg hover:bg-red-50">Cancel booking</button>
                                            </form>
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
                urlBase: '{{ url(config('admin.prefix', 'admin') . '/fulfillment') }}',
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
</x-admin-layout>
