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

            {{-- Same no-nesting rule for the SPX "Create shipper account"
                 action: its form lives out here, its button (inside the main
                 form's card below) targets it via form="…". Nesting it where
                 the button renders made the browser close the main form
                 early, orphaning every field after it (Alpine's mode scope
                 included). --}}
            @if ($courier->getKey() === 'spx' && empty($settings['spx_user_id']))
                <form id="create-account-form" method="POST" action="{{ route('admin.fulfillment.settings.create-account', $courier->getKey()) }}" data-v-form class="hidden">
                    @csrf
                </form>
            @endif

            <form method="POST" action="{{ route('admin.fulfillment.settings.courier.update', $courier->getKey()) }}" data-v-form class="space-y-5"
                @if ($modeFieldKey) x-data="{ mode: '{{ $initialMode }}' }" @endif>
                @csrf

                {{-- Task T4: SPX-specific page sections. Per W3's own note
                     ("T4's SPX-specific page sections... will extend
                     settings-courier.blade.php via per-courier action
                     slots"), the lighter mechanism was chosen over a
                     schema-declared slot type: a plain key check, since
                     exactly one courier needs either of these today and a
                     new abstraction for a single caller would be
                     speculative. --}}
                @if ($courier->getKey() === 'spx')
                    {{-- UAT-mode note: SPX's UAT environment only accepts
                         orders addressed to the recipients whitelisted in
                         the harvested onboarding guide. That PDF lives in
                         this repo's docs (not a public/servable path), so
                         this links nowhere — it names the file so whoever
                         is configuring UAT knows exactly where to look. --}}
                    <div x-show="mode === 'uat'" x-cloak class="rounded-v-md border border-v-warning bg-[color-mix(in_srgb,var(--warning)_12%,transparent)] px-4 py-3 text-[13px] text-v-warning">
                        <p class="font-semibold">UAT mode: whitelisted test orders only</p>
                        <p class="mt-1">SPX's UAT environment only accepts bookings for the recipient details listed in <code class="font-mono">docs/spx/NSS OpenAPI UAT Order Creation Guide.pdf</code> in this codebase's repo. A real customer address will fail with an unrelated-looking SPX error. Switch to Production mode once SPX has approved this integration.</p>
                    </div>

                    {{-- Create shipper account: only meaningful before a
                         User ID exists — once one is on file, this
                         disappears and the User ID / User Secret fields
                         below are the way to change it. --}}
                    @if (empty($settings['spx_user_id']))
                        <div class="rounded-v-md border border-v-border bg-v-sunken px-4 py-3">
                            <p class="text-[13px] font-medium text-v-text">No SPX shipper account yet</p>
                            <p class="text-xs text-v-muted mt-0.5 mb-2">Creates a User ID / User Secret via SPX's Create Account API using this store's pickup contact number, and saves both here automatically.</p>
                            <x-admin.button type="submit" form="create-account-form" variant="secondary" size="sm">Create shipper account</x-admin.button>
                        </div>
                    @endif
                @endif

                @foreach ($topFields as $field)
                    {{-- Task T4: spx_pouch_presets gets a structured editor
                         (below) instead of the generic raw-JSON text field —
                         same setting key, same stored JSON format, so
                         courierAllowedKeys()/persist() need no change. --}}
                    @continue($field['key'] === 'spx_pouch_presets')
                    @include('courier-fulfillment::partials.schema-field', [
                        'field' => $field,
                        'value' => $settings[$field['key']] ?? null,
                        'masked' => $masked[$field['key']] ?? null,
                        'isModeField' => $field['key'] === $modeFieldKey,
                    ])
                @endforeach

                @if ($courier->getKey() === 'spx')
                    @php
                        $pouchField = collect($topFields)->firstWhere('key', 'spx_pouch_presets');
                        $pouchRaw = $settings['spx_pouch_presets'] ?? null;
                        $pouchDecoded = $pouchRaw ? json_decode($pouchRaw, true) : null;
                        $pouchPresets = (is_array($pouchDecoded) && $pouchDecoded !== [])
                            ? array_values($pouchDecoded)
                            : \Extensions\CourierFulfillment\Couriers\Spx\SpxFulfillment::DEFAULT_POUCH_PRESETS;
                    @endphp
                    {{-- Task T4: structured pouch-preset editor (replaces
                         T1's raw-JSON text field). Rows live in local Alpine
                         state, seeded from the stored value; the ONLY thing
                         that reaches the server is the hidden input below,
                         JSON-serialized client-side into the SAME
                         settings[spx_pouch_presets] field name/format the
                         old text field posted — no controller/allowlist
                         change needed, no data-shape change. --}}
                    <div x-data="{ presets: {{ \Illuminate\Support\Js::from($pouchPresets) }} }">
                        <x-admin.field name="pouch_presets_editor" label="Pouch presets" :help="$pouchField['help'] ?? null">
                            <div class="space-y-2">
                                <template x-for="(p, idx) in presets" :key="idx">
                                    <div class="grid grid-cols-12 gap-2 items-end">
                                        <div class="col-span-4">
                                            <label class="block text-[11px] text-v-muted mb-0.5">Label</label>
                                            <input type="text" x-model="p.label" placeholder="e.g. Medium"
                                                class="v-control w-full h-[var(--control-h-sm)] px-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                        </div>
                                        <div class="col-span-2">
                                            <label class="block text-[11px] text-v-muted mb-0.5">Length (cm)</label>
                                            <input type="number" step="0.1" min="0.1" x-model.number="p.length_cm"
                                                class="v-control w-full h-[var(--control-h-sm)] px-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                        </div>
                                        <div class="col-span-2">
                                            <label class="block text-[11px] text-v-muted mb-0.5">Width (cm)</label>
                                            <input type="number" step="0.1" min="0.1" x-model.number="p.width_cm"
                                                class="v-control w-full h-[var(--control-h-sm)] px-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                        </div>
                                        <div class="col-span-2">
                                            <label class="block text-[11px] text-v-muted mb-0.5">Height (cm)</label>
                                            <input type="number" step="0.1" min="0.1" x-model.number="p.height_cm"
                                                class="v-control w-full h-[var(--control-h-sm)] px-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                        </div>
                                        <div class="col-span-1">
                                            <label class="block text-[11px] text-v-muted mb-0.5">Max kg</label>
                                            <input type="number" step="0.1" min="0.1" x-model.number="p.max_weight_kg"
                                                class="v-control w-full h-[var(--control-h-sm)] px-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                        </div>
                                        <div class="col-span-1">
                                            <button type="button" class="w-full h-[var(--control-h-sm)] text-v-danger hover:bg-v-hover rounded-v-md text-sm"
                                                @click="presets.splice(idx, 1)" :disabled="presets.length <= 1" title="Remove preset">&times;</button>
                                        </div>
                                    </div>
                                </template>
                                <x-admin.button type="button" variant="secondary" size="sm"
                                    @click="presets.push({label:'', length_cm:null, width_cm:null, height_cm:null, max_weight_kg:null})">+ Add preset</x-admin.button>
                            </div>
                        </x-admin.field>
                        <input type="hidden" name="settings[spx_pouch_presets]" :value="JSON.stringify(presets)">
                    </div>
                @endif

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
                                        <td class="py-2 text-v-text">
                                            {{ $label }}
                                            @if ($label !== $statusKey)
                                                <span class="block text-[11px] text-v-muted">{{ $statusKey }}</span>
                                            @endif
                                        </td>
                                        <td class="py-2">
                                            <x-admin.select name="settings[{{ $courier->getKey() }}_status_map][{{ $statusKey }}][order_status_id]" class="max-w-xs">
                                                <option value="">(none)</option>
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
