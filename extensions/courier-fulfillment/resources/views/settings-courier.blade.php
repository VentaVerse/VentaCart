{{--
    One courier's OWN settings page (Task W3 split) at
    settings/modules/courier-fulfillment/{courier} — rendered entirely from
    CourierFulfillmentInterface::settingsSchema() plus the courier's actions
    (Test Connection, Refresh statuses) and its status-mapping table, so a
    new courier gets this page for free. The page title is the courier's
    name, which the path-derived breadcrumb picks up as the trailing crumb:
    Settings › Modules › Courier Fulfillment › {Name}.
--}}
<x-admin.settings-shell :title="$courier->getName()" :wide="true" active="fulfillment">

    <div class="space-y-6">
        <x-admin.card :title="$courier->getName()" description="Connection, defaults, and order status mapping for this courier. Module-wide settings and webhook URLs live on the Courier Settings page.">
            {{-- Standalone form for "Refresh from {courier}" — HTML forms cannot
                 nest, so this lives outside the main form below; its button uses
                 form="…". --}}
            <form id="refresh-statuses-form" method="POST" action="{{ route('admin.fulfillment.settings.refresh-statuses', $courier->getKey()) }}" class="hidden">
                @csrf
            </form>

            <form method="POST" action="{{ route('admin.fulfillment.settings.courier.update', $courier->getKey()) }}" data-v-form class="space-y-5"
                @if ($modeFieldKey) x-data="{ mode: '{{ $initialMode }}' }" @endif>
                @csrf

                @foreach ($topFields as $field)
                    @include('courier-fulfillment::partials.schema-field', [
                        'field' => $field,
                        'value' => $settings[$field['key']] ?? null,
                        'masked' => $masked[$field['key']] ?? null,
                        'isModeField' => $field['key'] === $modeFieldKey,
                    ])
                @endforeach

                @foreach ($modeGroups as $modeValue => $fields)
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

                {{-- Test Connection hits the live provider API with the saved
                     credentials, via CourierFulfillmentInterface::testConnection(). --}}
                <div x-data="{ testing:false, result:null }" class="mt-2">
                    <x-admin.button type="button" variant="secondary" size="sm"
                        x-on:click="testing=true; result=null;
                            fetch('{{ route('admin.fulfillment.test-connection', $courier->getKey()) }}', {method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}})
                            .then(r=>r.json()).then(d=>result=d).finally(()=>testing=false)">
                        <span x-show="!testing">Test Connection</span><span x-show="testing" x-cloak>Testing&hellip;</span>
                    </x-admin.button>
                    <span x-show="result" x-cloak x-text="result?.message" :class="result?.ok ? 'text-v-accent' : 'text-v-danger'" class="ml-2 text-[13px]"></span>
                </div>

                {{-- Order status mapping — courier status -> Venta order status.
                     Statuses come from $courier->mappableStatuses(); saved keys are
                     "{key}_status_map" (StatusMapper::currentMap($key)), still through
                     this same form and this courier's own allowlist. --}}
                <div class="mt-8 pt-6 border-t border-v-border">
                    <div class="flex items-center justify-between mb-1">
                        <h3 class="text-[13px] font-semibold text-v-text">Order status mapping</h3>
                        <x-admin.button type="submit" form="refresh-statuses-form" variant="secondary" size="sm">Refresh from {{ $courier->getName() }}</x-admin.button>
                    </div>
                    <p class="text-xs text-v-muted mb-3">When {{ $courier->getName() }} reports a status, set the Venta order status. Leave blank to only track it on the fulfillment page.</p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-[13px]">
                            <thead>
                                <tr class="text-[11px] uppercase text-v-muted">
                                    <th class="text-left py-1">{{ $courier->getName() }} status</th>
                                    <th class="text-left py-1">Venta order status</th>
                                    <th class="text-left py-1">Notify customer</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($courierStatuses as $statusKey => $label)
                                    <tr class="border-t border-v-border">
                                        <td class="py-2 text-v-text">{{ $label }}</td>
                                        <td class="py-2">
                                            <x-admin.select name="settings[{{ $courier->getKey() }}_status_map][{{ $statusKey }}][order_status_id]" class="max-w-xs">
                                                <option value="">&mdash; none &mdash;</option>
                                                @foreach ($orderStatuses as $id => $name)
                                                    <option value="{{ $id }}" {{ (string) ($statusMap[$statusKey]['order_status_id'] ?? '') === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                                                @endforeach
                                            </x-admin.select>
                                        </td>
                                        <td class="py-2">
                                            <input type="hidden" name="settings[{{ $courier->getKey() }}_status_map][{{ $statusKey }}][notify]" value="0">
                                            <input type="checkbox" name="settings[{{ $courier->getKey() }}_status_map][{{ $statusKey }}][notify]" value="1"
                                                {{ !empty($statusMap[$statusKey]['notify']) ? 'checked' : '' }}
                                                class="w-4 h-4 rounded-v-sm border-v-strong text-v-accent focus:ring-v-accent bg-v-surface">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <x-admin.form-actions>
                    <x-admin.button type="submit">Save</x-admin.button>
                </x-admin.form-actions>
            </form>
        </x-admin.card>
    </div>

</x-admin.settings-shell>
