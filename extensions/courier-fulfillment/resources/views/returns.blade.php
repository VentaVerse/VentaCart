<x-admin.shell title="Returns" active="fulfillment">
    @push('head')
        @vite('extensions/courier-fulfillment/resources/css/fulfillment.css')
    @endpush
    {{-- Task W4: a MONITORING surface only: what's coming back, what
         failed. Booking, cancelling, and the drawer stay on the main
         queue; the full customer-initiated RMA process (refunds,
         restock) is a distinct product feature, tracked separately. --}}
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <span>Sales</span>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.fulfillment.index') }}">Fulfillment</a>
                <span aria-hidden="true">/</span>
                <span>Returns</span>
            </div>
            <h1 class="v-head__title">Returns</h1>
            <p class="v-head__standing">On their way back, or stuck in a failed courier state</p>
        </div>
        <div class="v-head__actions">
            <a href="{{ route('admin.fulfillment.index') }}" class="v-btn v-btn--secondary">All shipments</a>
        </div>
    </div>

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

        // The queue's shared tone words mapped onto the v badge vocabulary.
        $badgeTone = fn (string $tone) => [
            'neutral' => 'quiet', 'success' => 'good', 'warning' => 'warn',
            'danger' => 'bad', 'info' => 'info',
        ][$tone] ?? 'quiet';
    @endphp

    <div class="v-settle">
        <x-admin.panel flush>
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th class="v-w-courier">Courier</th>
                            <th class="v-w-status">Status</th>
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
                                <a class="v-rowlink v-mono" href="{{ route('admin.orders.show', $order) }}">#{{ $order->id }}</a>
                            </td>
                            <td class="v-w-courier">
                                <span class="v-lead">{{ $orderCourier?->getName() ?? (strtoupper((string) $order->courier_provider) ?: '-') }}</span>
                                @if ($order->courier_tracking_number)
                                    <span class="v-cell-quiet v-mono v-block">{{ $order->courier_tracking_number }}</span>
                                @endif
                            </td>
                            <td class="v-w-status">
                                <div class="v-cellstack">
                                    <span class="v-badge v-badge--{{ $badgeTone($spineTone) }}">{{ $spineLabel }}</span>
                                    @if ($order->courier_status)
                                        <span class="v-cellstack__note">{{ $humanizeStatus((string) $order->courier_status, $order->courier_provider) }}</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                @if ($lastEvent)
                                    {{ $humanizeStatus((string) $lastEvent['status'], $order->courier_provider) }}
                                    <span class="v-cell-quiet v-block">{{ \Carbon\Carbon::createFromTimestamp($lastEvent['at'])->timezone(config('app.timezone'))->format('M j, Y g:ia') }}</span>
                                @else
                                    <span class="v-cell-quiet">-</span>
                                @endif
                            </td>
                            <td class="v-cell-quiet">{{ optional($order->created_at)->format('M d, Y g:ia') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="v-empty">
                                    <p class="v-empty__title">Nothing coming back</p>
                                    <p class="v-empty__line">No orders are currently returning to you or stuck in a failed courier state.</p>
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
</x-admin.shell>
