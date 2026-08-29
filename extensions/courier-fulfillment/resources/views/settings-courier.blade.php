{{--
    One courier's OWN settings page (Task W3 split) at
    settings/modules/courier-fulfillment/{courier} — rendered entirely from
    CourierFulfillmentInterface::settingsSchema() plus the courier's actions
    (Test Connection, Refresh statuses) and its status-mapping table, so a
    new courier gets this page for free.
--}}
<x-courier-fulfillment::space :title="$courier->getName()" :active="$courier->getKey()">

    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.fulfillment.settings') }}">Fulfillment</a>
                <span aria-hidden="true">/</span>
                <span>{{ $courier->getName() }}</span>
            </div>
            <h1 class="v-head__title">{{ $courier->getName() }}</h1>
            <p class="v-head__standing">
                <span class="v-badge {{ $courierState['enabled'] ? 'v-badge--good' : 'v-badge--quiet' }}">{{ $courierState['enabled'] ? 'Enabled' : 'Disabled' }}</span>
                @if ($courierState['modeLabel'])
                    <span class="v-badge v-badge--warn">{{ $courierState['modeLabel'] }}</span>
                @endif
                @if ($courierState['credentialHint'])
                    <span class="v-cell-quiet">{{ $courierState['credentialHint'] }}</span>
                @endif
            </p>
        </div>
    </div>


    @if (!empty($webhookUrl))
        <div class="v-settle">
            {{-- This courier's webhook URL (owner ruling 2026-08-19: it
                 lives with its courier, not on the hub). Server-generated,
                 never editable; a courier with no push API renders none. --}}
            <x-admin.panel title="Webhook URL"
                note="Unique to this store. A courier given the wrong one cannot push its status updates.">
                <div class="v-actrow"
                     x-data="{ copied: false, async copy() {
                         if (!await window.vCopy($refs.hook.value)) return;
                         this.copied = true;
                         setTimeout(() => this.copied = false, 1500);
                     } }">
                    <input type="text" readonly value="{{ $webhookUrl }}" x-ref="hook"
                           aria-label="{{ $courier->getName() }} webhook URL" class="v-control v-mono">
                    <button type="button" class="v-btn v-btn--secondary v-btn--sm" @click="copy()">
                        <span x-show="!copied">Copy</span><span x-show="copied" x-cloak>Copied &check;</span>
                    </button>
                </div>
            </x-admin.panel>
        </div>
    @endif

    {{-- Standalone forms for the page's verbs — HTML forms cannot nest, so
         these live outside the doctrine form; their buttons use form="…". --}}
    <form id="refresh-statuses-form" method="POST" action="{{ route('admin.fulfillment.settings.refresh-statuses', $courier->getKey()) }}" hidden>
        @csrf
    </form>
    @if ($courier->getKey() === 'spx' && empty($settings['spx_user_id']))
        <form id="create-account-form" method="POST" action="{{ route('admin.fulfillment.settings.create-account', $courier->getKey()) }}" hidden>
            @csrf
        </form>
    @endif

    <x-admin.form :action="route('admin.fulfillment.settings.courier.update', $courier->getKey())" class="v-settle">
        {{-- The mode scope nests inside the doctrine form (the form tag
             already carries vForm's own x-data). --}}
        <div @if ($modeFieldKey) x-data="{ mode: '{{ $initialMode }}' }" @endif>
            <x-admin.panel :title="$courier->getName()">

                @if ($courier->getKey() === 'spx')
                    {{-- UAT-mode note: SPX's UAT environment only accepts
                         orders addressed to the recipients whitelisted in the
                         harvested onboarding guide. That PDF lives in this
                         repo's docs (not a public/servable path), so this
                         links nowhere — it names the file so whoever is
                         configuring UAT knows exactly where to look. --}}
                    <div x-show="mode === 'uat'" x-cloak class="v-notice v-gap-sm">
                        <p class="v-notice__title">UAT mode: whitelisted test orders only</p>
                        <p class="v-notice__text">SPX's UAT environment only accepts bookings for the recipient details listed in <code class="v-mono">docs/spx/NSS OpenAPI UAT Order Creation Guide.pdf</code> in this codebase's repo. A real customer address will fail with an unrelated-looking SPX error. Switch to Production mode once SPX has approved this integration.</p>
                    </div>

                    {{-- Create shipper account: only meaningful before a User
                         ID exists — once one is on file, this disappears and
                         the User ID / User Secret fields below are the way to
                         change it. --}}
                    @if (empty($settings['spx_user_id']))
                        <div class="v-actrow v-gap-sm">
                            <span class="v-badge v-badge--warn">No SPX shipper account yet</span>
                            <button type="submit" form="create-account-form" class="v-btn v-btn--secondary v-btn--sm">Create shipper account</button>
                            <x-admin.help text="Creates a User ID and User Secret through SPX using this store's pickup contact number, and saves both here." />
                        </div>
                    @endif
                @endif

                @foreach ($topFields as $field)
                    {{-- spx_pouch_presets gets a structured editor (below)
                         instead of the generic raw-JSON text field — same
                         setting key, same stored JSON format. --}}
                    @continue($field['key'] === 'spx_pouch_presets')
                    @include('courier-fulfillment::partials.schema-field', [
                        'field' => $field,
                        'value' => $settings[$field['key']] ?? null,
                        'masked' => $masked[$field['key']] ?? null,
                        'plain' => $plain[$field['key']] ?? null,
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
                    {{-- Structured pouch-preset editor. Rows live in local
                         Alpine state, seeded from the stored value; the ONLY
                         thing that reaches the server is the hidden input
                         below, JSON-serialized client-side into the SAME
                         settings[spx_pouch_presets] field name/format the old
                         text field posted. Row add/remove calls the doctrine
                         form's touch() so structural edits raise the save bar
                         even before any keystroke. --}}
                    <div class="v-gap" x-data="{ presets: {{ \Illuminate\Support\Js::from($pouchPresets) }} }">
                        <div class="v-label v-gap-sm">
                            Pouch presets
                            @if (!empty($pouchField['help']))<x-admin.help :text="$pouchField['help']" />@endif
                        </div>
                        <template x-for="(p, idx) in presets" :key="idx">
                            <div class="v-grid v-grid--pouch v-gap-sm">
                                <input type="text" x-model="p.label" :aria-label="'Preset ' + (idx + 1) + ' label'" placeholder="e.g. Medium" class="v-control">
                                <input type="number" step="0.1" min="0.1" x-model.number="p.length_cm" :aria-label="'Preset ' + (idx + 1) + ' length (cm)'" placeholder="L cm" class="v-control v-mono">
                                <input type="number" step="0.1" min="0.1" x-model.number="p.width_cm" :aria-label="'Preset ' + (idx + 1) + ' width (cm)'" placeholder="W cm" class="v-control v-mono">
                                <input type="number" step="0.1" min="0.1" x-model.number="p.height_cm" :aria-label="'Preset ' + (idx + 1) + ' height (cm)'" placeholder="H cm" class="v-control v-mono">
                                <input type="number" step="0.1" min="0.1" x-model.number="p.max_weight_kg" :aria-label="'Preset ' + (idx + 1) + ' max kg'" placeholder="Max kg" class="v-control v-mono">
                                <button type="button" class="v-row__x" @click="presets.splice(idx, 1); $nextTick(() => touch())"
                                    :disabled="presets.length <= 1" :aria-label="'Remove preset ' + (idx + 1)">
                                    <x-admin.icon name="trash" />
                                </button>
                            </div>
                        </template>
                        <div class="v-gap-sm">
                            <button type="button" class="v-btn v-btn--secondary v-btn--sm"
                                @click="presets.push({label:'', length_cm:null, width_cm:null, height_cm:null, max_weight_kg:null}); $nextTick(() => touch())">Add preset</button>
                        </div>
                        <input type="hidden" name="settings[spx_pouch_presets]" :value="JSON.stringify(presets)">
                    </div>
                @endif

                @foreach ($modeGroups as $modeValue => $fields)
                    <div x-show="mode === '{{ $modeValue }}'" x-cloak class="v-gap v-modegroup">
                        <div class="v-label v-gap-sm">{{ ucfirst($modeValue) }} credentials</div>
                        @foreach ($fields as $field)
                            @include('courier-fulfillment::partials.schema-field', [
                                'field' => $field,
                                'value' => $settings[$field['key']] ?? null,
                                'masked' => $masked[$field['key']] ?? null,
                                'plain' => $plain[$field['key']] ?? null,
                                'isModeField' => false,
                            ])
                        @endforeach
                    </div>
                @endforeach

                {{-- Test Connection hits the live provider API with the saved
                     credentials, via CourierFulfillmentInterface::testConnection(). --}}
                <div x-data="{ testing: false, result: null }" class="v-gap">
                    <span class="v-actrow">
                        <button type="button" class="v-btn v-btn--secondary v-btn--sm"
                            @click="testing = true; result = null;
                                fetch('{{ route('admin.fulfillment.test-connection', $courier->getKey()) }}', {method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}})
                                .then(r => r.json()).then(d => result = d).finally(() => testing = false)">
                            <span x-show="!testing">Test Connection</span><span x-show="testing" x-cloak>Testing&hellip;</span>
                        </button>
                        <span x-show="result" x-cloak x-text="result?.message"
                              :class="result?.ok ? 'v-badge v-badge--good' : 'v-badge v-badge--bad'"></span>
                    </span>
                </div>
            </x-admin.panel>

            {{-- Order status mapping: courier status -> Venta order status.
                 A courier that exposes no mappable vocabulary (LBC) gets no
                 section at all rather than a table of column headings over
                 nothing, and no "Refresh from" button for a list it cannot
                 fetch. --}}
            @if (!empty($courierStatuses))
                <div class="v-gap">
                    <x-admin.panel title="Order status mapping" flush
                        note="A row left blank still tracks on the fulfillment page, it just never moves the order's own status.">
                        <x-slot:acts>
                            <button type="submit" form="refresh-statuses-form" class="v-btn v-btn--secondary v-btn--sm">Refresh from {{ $courier->getName() }}</button>
                        </x-slot:acts>
                        <div class="v-tablewrap">
                            <table class="v-table">
                                <thead>
                                    <tr>
                                        <th>{{ $courier->getName() }} status</th>
                                        <th>Venta order status</th>
                                        <th>Notify customer</th>
                                    </tr>
                                </thead>
                                @foreach ($courierStatusGroups as $groupLabel => $groupStatuses)
                                <tbody>
                                    @if ($groupLabel !== '')
                                        <tr class="v-table__group"><th colspan="3" scope="colgroup">{{ $groupLabel }}</th></tr>
                                    @endif
                                    @foreach ($groupStatuses as $statusKey => $label)
                                        <tr>
                                            <td>
                                                {{ $label }}
                                                @if ($label !== $statusKey)
                                                    <span class="v-cell-quiet v-block v-mono">{{ $statusKey }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                <select name="settings[{{ $courier->getKey() }}_status_map][{{ $statusKey }}][order_status_id]"
                                                        class="v-control v-w-name" aria-label="Order status for {{ $label }}">
                                                    <option value="">(none)</option>
                                                    @foreach ($orderStatuses as $id => $name)
                                                        <option value="{{ $id }}" {{ (string) ($statusMap[$statusKey]['order_status_id'] ?? '') === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <input type="hidden" name="settings[{{ $courier->getKey() }}_status_map][{{ $statusKey }}][notify]" value="0">
                                                <label class="v-check">
                                                    <input type="checkbox" name="settings[{{ $courier->getKey() }}_status_map][{{ $statusKey }}][notify]" value="1"
                                                        aria-label="Notify the customer on {{ $label }}"
                                                        {{ !empty($statusMap[$statusKey]['notify']) ? 'checked' : '' }}>
                                                </label>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                @endforeach
                            </table>
                        </div>
                    </x-admin.panel>
                </div>
            @endif
        </div>
    </x-admin.form>

</x-courier-fulfillment::space>
