<x-admin.shell title="Returns">
    @push('head')
        @vite('extensions/courier-fulfillment/resources/css/fulfillment.css')
    @endpush
    <x-admin.page title="Returns">
        <x-slot:actions>
            <x-admin.button variant="secondary" size="sm" href="{{ route('admin.fulfillment.index') }}">All shipments</x-admin.button>
        </x-slot:actions>

        {{-- Task W4: a MONITORING surface only — what's coming back, what
             failed. Booking, cancelling, and the drawer stay on the main
             queue; the full customer-initiated RMA process (refunds,
             restock) is a distinct product feature, tracked separately. --}}
        <p class="text-[13px] text-v-secondary mb-4">
            Orders on their way back to you, or stuck in a failed courier state. Book, cancel, and track shipments from the main queue: this page is for watching what needs attention.
        </p>

        @php
            // Same cached-label source the queue uses (Refresh from {courier}
            // on the settings page) — never a live call.
            $statusLabelsByProvider = [];
            $humanizeStatus = function (string $status, ?string $provider) use (&$statusLabelsByProvider) {
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
        @endphp

        <div class="cf-table">
            <div class="cf-table__scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th class="cf-col-courier">Courier</th>
                            <th class="cf-col-status">Status</th>
                            <th>Last event</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($orders as $order)
                        @php
                            $orderCourier = $order->courier_provider ? \Extensions\CourierFulfillment\CourierManager::get($order->courier_provider) : null;

                            $spinePhase = \Extensions\CourierFulfillment\FulfillmentService::phaseFor($order);
                            $spineTone  = \Extensions\CourierFulfillment\Support\PhaseTone::toneFor($spinePhase);
                            // Display-only (see PhaseTone::labelFor()): same
                            // chip vocabulary as the queue, so one shipment
                            // never reads two different words across pages.
                            $spineLabel = \Extensions\CourierFulfillment\Support\PhaseTone::labelFor($spinePhase);

                            // Most recent timeline entry (courier_meta shape is
                            // per-courier; validated the same way the queue's
                            // drawer timeline is built).
                            $meta = $order->courier_meta ? (json_decode($order->courier_meta, true) ?: []) : [];
                            $timelineRaw = is_array($meta['timeline'] ?? null) ? $meta['timeline'] : [];
                            $lastEvent = null;
                            foreach ($timelineRaw as $event) {
                                if (!is_array($event) || !isset($event['status'], $event['at']) || !is_int($event['at'])) {
                                    continue;
                                }
                                if ($lastEvent === null || $event['at'] > $lastEvent['at']) {
                                    $lastEvent = $event;
                                }
                            }
                        @endphp
                        <tr>
                            <td>
                                <a class="co-sn" href="{{ route('admin.orders.show', $order) }}">#{{ $order->id }}</a>
                            </td>
                            <td class="cf-col-courier">
                                <span class="co-carrier">{{ $orderCourier?->getName() ?? (strtoupper((string) $order->courier_provider) ?: '-') }}</span>
                                @if ($order->courier_tracking_number)
                                    <span class="co-ship__no">{{ $order->courier_tracking_number }}</span>
                                @endif
                            </td>
                            <td class="cf-col-status">
                                <div class="co-status">
                                    <x-admin.badge :tone="$spineTone">{{ $spineLabel }}</x-admin.badge>
                                    @if ($order->courier_status)
                                        <span class="co-status__note">{{ $humanizeStatus((string) $order->courier_status, $order->courier_provider) }}</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                @if ($lastEvent)
                                    <span class="block text-[13px] text-v-text">{{ $humanizeStatus((string) $lastEvent['status'], $order->courier_provider) }}</span>
                                    <span class="block text-xs text-v-muted">{{ \Carbon\Carbon::createFromTimestamp($lastEvent['at'])->timezone(config('app.timezone'))->format('M j, Y g:ia') }}</span>
                                @else
                                    <span class="co-empty-mark">-</span>
                                @endif
                            </td>
                            <td>
                                <span class="text-[13px] text-v-secondary">{{ optional($order->created_at)->format('M d, Y g:ia') }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <x-admin.empty-state icon="fulfillment" title="Nothing coming back"
                                    description="No orders are currently returning to you or stuck in a failed courier state." />
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($orders->total() > 0)
            <div class="cf-pager">
                <span class="cf-pager__count">{{ number_format($orders->firstItem() ?? 0) }} to {{ number_format($orders->lastItem() ?? 0) }} of {{ number_format($orders->total()) }}</span>
                <div class="cf-pager__nav">
                    @if ($orders->previousPageUrl())
                        <x-admin.button size="sm" variant="secondary" href="{{ $orders->previousPageUrl() }}">Previous</x-admin.button>
                    @else
                        <x-admin.button size="sm" variant="secondary" disabled>Previous</x-admin.button>
                    @endif
                    @if ($orders->nextPageUrl())
                        <x-admin.button size="sm" variant="secondary" href="{{ $orders->nextPageUrl() }}">Next</x-admin.button>
                    @else
                        <x-admin.button size="sm" variant="secondary" disabled>Next</x-admin.button>
                    @endif
                </div>
            </div>
        @endif
    </x-admin.page>
</x-admin.shell>
