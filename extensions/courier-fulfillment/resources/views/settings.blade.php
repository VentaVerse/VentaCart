<x-admin.settings-shell title="Courier Settings" :wide="true">

    <div class="space-y-6">

        {{-- Module-level card: not owned by any one courier's schema. --}}
        <x-admin.card title="Fulfillment" description="Pause the manual booking queue, choose which payment methods book as COD, and share your webhook URL with couriers.">
            <form method="POST" action="{{ route('admin.fulfillment.settings.update') }}" data-v-form class="space-y-6">
                @csrf

                <x-admin.field name="settings[fulfillment_enabled]"
                    help="On by default — installing and enabling this extension is already the signal you want it active. Turn off to pause the booking queue (Sales › Fulfillment shows an explanatory page instead) without disabling the extension itself. Bookings triggered by the ERP API keep working either way.">
                    <label class="flex items-center gap-3 text-[13px] text-v-text cursor-pointer">
                        <x-admin.toggle name="settings[fulfillment_enabled]" :checked="($settings['fulfillment_enabled'] ?? '1') !== '0'" />
                        Fulfillment queue active
                    </label>
                </x-admin.field>

                <div>
                    <h3 class="text-[13px] font-semibold text-v-text mb-1">COD payment methods</h3>
                    <p class="text-xs text-v-muted mb-3">Orders paid with a checked method are booked as COD (the rider collects the order total). Everything else books as prepaid.</p>
                    <input type="hidden" name="settings[cod_payment_methods][]" value="__none__">
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-2">
                        @foreach ($paymentMethodOptions as $slug => $method)
                            <label class="flex items-center gap-2 text-[13px] text-v-text cursor-pointer">
                                <input type="checkbox" name="settings[cod_payment_methods][]" value="{{ $slug }}"
                                    {{ in_array($slug, $codMethods, true) ? 'checked' : '' }}
                                    class="w-4 h-4 rounded-v-sm border-v-strong text-v-accent focus:ring-v-accent bg-v-surface">
                                <span class="text-xs text-v-secondary">{{ $method['label'] }}</span>
                                @if ($method['legacy'])
                                    <x-admin.badge tone="neutral">legacy</x-admin.badge>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </div>

                <x-admin.form-actions>
                    <x-admin.button type="submit">Save</x-admin.button>
                </x-admin.form-actions>
            </form>

            <div class="mt-6 pt-5 border-t border-v-border" x-data="{ copied: false }">
                <h3 class="text-[13px] font-semibold text-v-text mb-1">Webhook URL</h3>
                <p class="text-xs text-v-muted mb-3">QuadX has no UI to set this — email/Slack them this URL so they push status updates here. Unique to this store.</p>
                <div class="flex items-center gap-2">
                    <input type="text" readonly value="{{ $webhookUrl }}" x-ref="hook"
                        class="v-control flex-1 h-[var(--control-h)] rounded-v-md border border-v-border bg-v-sunken px-3 text-xs font-mono text-v-secondary">
                    <x-admin.button type="button" variant="secondary" size="sm"
                        x-on:click="navigator.clipboard.writeText($refs.hook.value); copied=true; setTimeout(()=>copied=false,1500)">
                        <span x-show="!copied">Copy</span><span x-show="copied" x-cloak>Copied &check;</span>
                    </x-admin.button>
                </div>
            </div>
        </x-admin.card>

        {{-- One card per registered courier, rendered from its own settingsSchema(). --}}
        @foreach ($courierCards as $key => $card)
            @php $courier = $card['courier']; @endphp
            <x-admin.card :title="$courier->getName()">
                @if ($courier->getKey() === 'quadx')
                    {{-- Standalone form for "Refresh from QuadX" — HTML forms cannot nest,
                         so this lives outside the main form below; its button uses form="…". --}}
                    <form id="refresh-statuses-form" method="POST" action="{{ route('admin.fulfillment.settings.refresh-statuses') }}" class="hidden">
                        @csrf
                    </form>
                @endif

                <form method="POST" action="{{ route('admin.fulfillment.settings.update') }}" data-v-form class="space-y-5"
                    @if ($card['modeFieldKey']) x-data="{ mode: '{{ $card['initialMode'] }}' }" @endif>
                    @csrf

                    @foreach ($card['topFields'] as $field)
                        @include('courier-fulfillment::partials.schema-field', [
                            'field' => $field,
                            'value' => $settings[$field['key']] ?? null,
                            'masked' => $masked[$field['key']] ?? null,
                            'isModeField' => $field['key'] === $card['modeFieldKey'],
                        ])
                    @endforeach

                    @foreach ($card['modeGroups'] as $modeValue => $fields)
                        <div x-show="mode === '{{ $modeValue }}'" x-cloak class="space-y-4 border-l-2 border-v-border pl-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-v-secondary">{{ ucfirst($modeValue) }} credentials</p>
                            @foreach ($fields as $field)
                                @include('courier-fulfillment::partials.schema-field', [
                                    'field' => $field,
                                    'value' => $settings[$field['key']] ?? null,
                                    'masked' => $masked[$field['key']] ?? null,
                                    'isModeField' => false,
                                ])
                            @endforeach
                        </div>
                    @endforeach

                    @if ($courier->getKey() === 'quadx')
                        {{-- Test Connection hits the live QuadX API with the saved credentials.
                             Not generalized to every courier yet — this action and the route it
                             calls are still QuadX-specific (CourierSettingsController::testConnection()
                             hardcodes CourierManager::get('quadx')); a future courier gets this once
                             that action takes a {courier} route parameter. --}}
                        <div x-data="{ testing:false, result:null }" class="mt-2">
                            <x-admin.button type="button" variant="secondary" size="sm"
                                x-on:click="testing=true; result=null;
                                    fetch('{{ route('admin.fulfillment.test-connection') }}', {method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}})
                                    .then(r=>r.json()).then(d=>result=d).finally(()=>testing=false)">
                                <span x-show="!testing">Test Connection</span><span x-show="testing" x-cloak>Testing&hellip;</span>
                            </x-admin.button>
                            <span x-show="result" x-cloak x-text="result?.message" :class="result?.ok ? 'text-v-accent' : 'text-v-danger'" class="ml-2 text-[13px]"></span>
                        </div>

                        {{-- Order status mapping — QuadX status -> Venta order status. Kept
                             hardcoded to quadx_status_map/quadx_status_labels here (courier-agnostic
                             StatusMapper derivation is a separate, later pass); still saves through
                             this same form/allowlist. --}}
                        <div class="mt-8 pt-6 border-t border-v-border">
                            <div class="flex items-center justify-between mb-1">
                                <h3 class="text-[13px] font-semibold text-v-text">Order status mapping</h3>
                                <x-admin.button type="submit" form="refresh-statuses-form" variant="secondary" size="sm">Refresh from QuadX</x-admin.button>
                            </div>
                            <p class="text-xs text-v-muted mb-3">When QuadX reports a status, set the Venta order status. Leave blank to only track it on the fulfillment page.</p>
                            <div class="overflow-x-auto">
                                <table class="w-full text-[13px]">
                                    <thead>
                                        <tr class="text-[11px] uppercase text-v-muted">
                                            <th class="text-left py-1">QuadX status</th>
                                            <th class="text-left py-1">Venta order status</th>
                                            <th class="text-left py-1">Notify customer</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($quadxStatuses as $statusKey => $label)
                                            <tr class="border-t border-v-border">
                                                <td class="py-2 text-v-text">{{ $label }}</td>
                                                <td class="py-2">
                                                    <x-admin.select name="settings[quadx_status_map][{{ $statusKey }}][order_status_id]" class="max-w-xs">
                                                        <option value="">&mdash; none &mdash;</option>
                                                        @foreach ($orderStatuses as $id => $name)
                                                            <option value="{{ $id }}" {{ (string) ($statusMap[$statusKey]['order_status_id'] ?? '') === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                                                        @endforeach
                                                    </x-admin.select>
                                                </td>
                                                <td class="py-2">
                                                    <input type="hidden" name="settings[quadx_status_map][{{ $statusKey }}][notify]" value="0">
                                                    <input type="checkbox" name="settings[quadx_status_map][{{ $statusKey }}][notify]" value="1"
                                                        {{ !empty($statusMap[$statusKey]['notify']) ? 'checked' : '' }}
                                                        class="w-4 h-4 rounded-v-sm border-v-strong text-v-accent focus:ring-v-accent bg-v-surface">
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <x-admin.form-actions>
                        <x-admin.button type="submit">Save</x-admin.button>
                    </x-admin.form-actions>
                </form>
            </x-admin.card>
        @endforeach

    </div>

</x-admin.settings-shell>
