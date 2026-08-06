<x-admin.shell title="Shipping">
    <x-admin.page title="Shipping" :breadcrumb="[['label' => 'Settings', 'url' => route('admin.settings.index')]]">

        {{--
            Chain strip — numbered in the REAL evaluation order from
            ShippingQuoteService::getProviders(): Free Shipping, Weight Based,
            Flat Rate, DHL. If a free-shipping rule matches, it overrides every
            paid method below it, which is why it has no enable toggle of its
            own — its "on" state is simply "does an active rule exist".
        --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4 mb-8">
            @foreach ($chain as $i => $provider)
                <x-admin.card>
                    <div class="flex items-start gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-v-sunken text-[13px] font-semibold text-v-secondary">{{ $i + 1 }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="text-[14px] font-semibold text-v-text">{{ $provider['label'] }}</h3>
                                @if ($provider['settingKey'])
                                    <form method="POST" action="{{ route('admin.settings.update') }}">
                                        @csrf
                                        <input type="hidden" name="group" value="shipping">
                                        <input type="hidden" name="_redirect" value="{{ route('admin.shipping-settings.index') }}">
                                        <x-admin.toggle name="settings[{{ $provider['settingKey'] }}]" :checked="$provider['active']"
                                            x-on:change="$el.closest('form').submit()" />
                                    </form>
                                @else
                                    <x-admin.badge :tone="$provider['active'] ? 'success' : 'neutral'">
                                        {{ $provider['active'] ? 'Active' : 'No active rules' }}
                                    </x-admin.badge>
                                @endif
                            </div>
                            <p class="mt-1 text-[13px] leading-snug text-v-secondary">{{ $provider['description'] }}</p>
                            @if ($provider['id'] === 'free-shipping')
                                <p class="mt-1.5 text-[11px] text-v-muted">Overrides every paid method below when a rule matches.</p>
                            @endif
                            <div class="mt-3 flex items-center justify-end">
                                <x-admin.button variant="ghost" size="sm" href="#panel-{{ $provider['id'] }}">Configure →</x-admin.button>
                            </div>
                        </div>
                    </div>
                </x-admin.card>
            @endforeach
        </div>

        <div class="space-y-8">

            {{-- ==================== RATE TESTER ==================== --}}
            @include('admin.shipping-settings.partials.rate-tester')

            {{-- ==================== FREE SHIPPING ==================== --}}
            <x-admin.card id="panel-free-shipping" title="Free Shipping Rules"
                description="First matching rule applies. Full condition editing lives on the dedicated rules page.">
                <x-slot name="headerActions">
                    <x-admin.button variant="secondary" size="sm" :href="route('admin.free-shipping-rules.index')">Manage rules →</x-admin.button>
                </x-slot>

                @if ($freeShippingRules->isEmpty())
                    <x-admin.empty-state icon="cart" title="No free shipping rules"
                        description="Create a rule to automatically waive shipping when conditions are met.">
                        <x-admin.button variant="primary" size="sm" :href="route('admin.free-shipping-rules.create')">Add rule</x-admin.button>
                    </x-admin.empty-state>
                @else
                    <x-admin.table>
                        <x-slot:head>
                            <x-admin.table.th>Rule</x-admin.table.th>
                            <x-admin.table.th>Conditions</x-admin.table.th>
                            <x-admin.table.th>Order</x-admin.table.th>
                            <x-admin.table.th>Status</x-admin.table.th>
                            <x-admin.table.th align="right">Actions</x-admin.table.th>
                        </x-slot:head>
                        @foreach ($freeShippingRules as $rule)
                            <x-admin.table.row>
                                <x-admin.table.cell><span class="font-medium text-v-text">{{ $rule->name }}</span></x-admin.table.cell>
                                <x-admin.table.cell><span class="text-v-secondary">{{ $rule->getConditionsSummary() }}</span></x-admin.table.cell>
                                <x-admin.table.cell><span class="text-v-secondary">{{ $rule->sort_order }}</span></x-admin.table.cell>
                                <x-admin.table.cell>
                                    <form method="POST" action="{{ route('admin.free-shipping-rules.toggle', $rule) }}" data-v-form>
                                        @csrf
                                        <button type="submit">
                                            <x-admin.badge :tone="$rule->is_active ? 'success' : 'neutral'">
                                                {{ $rule->is_active ? 'Active' : 'Disabled' }}
                                            </x-admin.badge>
                                        </button>
                                    </form>
                                </x-admin.table.cell>
                                <x-admin.table.cell align="right">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('admin.free-shipping-rules.edit', $rule) }}" class="text-xs font-medium text-v-accent hover:underline">Edit</a>
                                        <button type="button" class="text-xs font-medium text-v-danger hover:underline"
                                            @click="$dispatch('open-modal', 'delete-fsr-{{ $rule->id }}')">Delete</button>
                                    </div>
                                </x-admin.table.cell>
                            </x-admin.table.row>
                        @endforeach
                    </x-admin.table>
                @endif
            </x-admin.card>
            @foreach ($freeShippingRules as $rule)
                <x-admin.confirm-dialog name="delete-fsr-{{ $rule->id }}" title="Delete “{{ $rule->name }}”?"
                    message="This free shipping rule will stop applying immediately." confirm="Delete"
                    :action="route('admin.free-shipping-rules.destroy', $rule)" method="DELETE" />
            @endforeach

            {{-- ==================== WEIGHT BASED ==================== --}}
            <x-admin.card id="panel-weight-based" title="Weight Based"
                description="Cost tiers based on cart weight. Click a method to expand and edit.">
                <x-slot name="headerActions">
                    <x-admin.button variant="primary" size="sm" :href="route('admin.weight-based-methods.create')">Add method</x-admin.button>
                </x-slot>

                @if ($weightMethods->isEmpty())
                    <x-admin.empty-state icon="fulfillment" title="No weight-based methods"
                        description="Create tiers priced by cart weight.">
                        <x-admin.button variant="primary" size="sm" :href="route('admin.weight-based-methods.create')">Add method</x-admin.button>
                    </x-admin.empty-state>
                @else
                    <form method="POST" action="{{ route('admin.weight-based-methods.bulk-update') }}" data-v-form>
                        @csrf
                        @method('PUT')
                        <div class="border border-v-border rounded-v-lg divide-y divide-v-border overflow-hidden">
                            @foreach ($weightMethods as $wm)
                                <div x-data="{
                                        open: false,
                                        rates: {{ json_encode($wm->rates->map(function ($r) {
                                            return ['weight_from' => $r->weight_from, 'weight_to' => $r->weight_to, 'cost' => $r->cost];
                                        })) }},
                                        addRate() {
                                            const last = this.rates.length > 0 ? this.rates[this.rates.length - 1] : null;
                                            this.rates.push({
                                                weight_from: last ? last.weight_to : '0.000',
                                                weight_to: last ? (parseFloat(last.weight_to) + 5).toFixed(3) : '5.000',
                                                cost: last ? last.cost : '0.00',
                                            });
                                        },
                                    }">
                                    {{-- Method header row --}}
                                    <div class="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-v-hover transition-colors" @click="open = !open">
                                        <x-admin.icon name="chevron-right" class="w-3.5 h-3.5 text-v-muted transition-transform shrink-0" x-bind:class="open && 'rotate-90'" />
                                        <span class="w-2 h-2 rounded-full shrink-0 {{ $wm->is_active ? 'bg-v-accent' : 'bg-v-strong' }}"></span>
                                        <span class="text-[13px] font-medium text-v-text flex-1 truncate">{{ $wm->admin_name ?: $wm->name }}</span>
                                        <span class="hidden sm:inline text-xs text-v-muted">{{ $wm->geoZones->isNotEmpty() ? $wm->geoZones->pluck('name')->implode(', ') : 'All zones' }}</span>
                                        <span class="hidden sm:inline text-xs text-v-muted">{{ $wm->customerGroups->isNotEmpty() ? $wm->customerGroups->pluck('name')->implode(', ') : 'All groups' }}</span>
                                        <span class="text-xs text-v-muted whitespace-nowrap">{{ $wm->rates_count }} {{ Str::plural('tier', $wm->rates_count) }}</span>
                                    </div>

                                    {{-- Expanded edit --}}
                                    <div x-show="open" x-transition x-cloak class="px-4 pb-5 bg-v-sunken/40 border-t border-v-border">
                                        <div class="pt-4 space-y-4">
                                            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                                                <div>
                                                    <label class="block text-xs font-medium text-v-secondary mb-1">Admin name</label>
                                                    <input type="text" name="methods[{{ $wm->id }}][admin_name]" value="{{ $wm->admin_name }}" placeholder="Internal reference"
                                                        class="v-control w-full h-[var(--control-h-sm)] px-2.5 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-v-secondary mb-1">Checkout name</label>
                                                    <input type="text" name="methods[{{ $wm->id }}][name]" value="{{ $wm->name }}" required
                                                        class="v-control w-full h-[var(--control-h-sm)] px-2.5 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                                </div>
                                                <div class="flex items-end gap-4">
                                                    <div class="pb-1.5">
                                                        <x-admin.checkbox name="methods[{{ $wm->id }}][is_active]" :checked="$wm->is_active" label="Active" />
                                                    </div>
                                                    <div class="flex items-center gap-1.5 pb-1.5">
                                                        <label class="text-xs text-v-muted">Sort</label>
                                                        <input type="number" name="methods[{{ $wm->id }}][sort_order]" value="{{ $wm->sort_order }}"
                                                            class="v-control w-14 h-[var(--control-h-sm)] px-1.5 text-[13px] text-center bg-v-surface text-v-text border border-v-border rounded-v-md">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                <div>
                                                    <label class="block text-xs font-medium text-v-secondary mb-1">Geo zones</label>
                                                    <div class="space-y-1 max-h-32 overflow-y-auto border border-v-border rounded-v-md p-2">
                                                        @foreach ($geoZones as $zone)
                                                            <label class="flex items-center gap-2 text-xs text-v-text cursor-pointer">
                                                                <input type="checkbox" name="methods[{{ $wm->id }}][geo_zone_ids][]" value="{{ $zone->id }}"
                                                                    {{ $wm->geoZones->contains('id', $zone->id) ? 'checked' : '' }}
                                                                    class="w-3.5 h-3.5 rounded-v-sm border-v-strong text-v-accent focus:ring-v-accent bg-v-surface">
                                                                {{ $zone->name }}
                                                            </label>
                                                        @endforeach
                                                        @if ($geoZones->isEmpty())
                                                            <p class="text-xs text-v-muted">No geo zones</p>
                                                        @endif
                                                    </div>
                                                    <p class="text-[11px] text-v-muted mt-1">None = all zones</p>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-v-secondary mb-1">Customer groups</label>
                                                    <div class="space-y-1 max-h-32 overflow-y-auto border border-v-border rounded-v-md p-2">
                                                        @foreach ($customerGroups as $group)
                                                            <label class="flex items-center gap-2 text-xs text-v-text cursor-pointer">
                                                                <input type="checkbox" name="methods[{{ $wm->id }}][customer_group_ids][]" value="{{ $group->id }}"
                                                                    {{ $wm->customerGroups->contains('id', $group->id) ? 'checked' : '' }}
                                                                    class="w-3.5 h-3.5 rounded-v-sm border-v-strong text-v-accent focus:ring-v-accent bg-v-surface">
                                                                {{ $group->name }}
                                                            </label>
                                                        @endforeach
                                                        @if ($customerGroups->isEmpty())
                                                            <p class="text-xs text-v-muted">No groups</p>
                                                        @endif
                                                    </div>
                                                    <p class="text-[11px] text-v-muted mt-1">None = all customers</p>
                                                </div>
                                            </div>

                                            {{-- Weight tiers --}}
                                            <div>
                                                <div class="flex items-center justify-between mb-2">
                                                    <label class="text-xs font-medium text-v-secondary">Weight tiers</label>
                                                    <button type="button" @click="addRate()"
                                                        class="inline-flex items-center gap-1 px-2 py-1 text-[11px] bg-v-accent-weak text-v-accent rounded-v-md hover:opacity-90 font-medium">
                                                        <x-admin.icon name="plus" class="w-3 h-3" /> Add tier
                                                    </button>
                                                </div>
                                                <template x-if="rates.length > 0">
                                                    <div class="border border-v-border rounded-v-md overflow-hidden">
                                                        <table class="w-full text-[13px]">
                                                            <thead class="bg-v-sunken">
                                                                <tr>
                                                                    <th class="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-widest text-v-muted">From (kg)</th>
                                                                    <th class="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-widest text-v-muted">To (kg)</th>
                                                                    <th class="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-widest text-v-muted">Cost (₱)</th>
                                                                    <th class="px-3 py-2 w-8"></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody class="divide-y divide-v-border">
                                                                <template x-for="(rate, i) in rates" :key="i">
                                                                    <tr>
                                                                        <td class="px-3 py-1.5">
                                                                            <input type="number" :name="'methods[{{ $wm->id }}][rates]['+i+'][weight_from]'" x-model="rate.weight_from" step="0.001" min="0"
                                                                                class="v-control w-full h-[var(--control-h-sm)] px-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                                                        </td>
                                                                        <td class="px-3 py-1.5">
                                                                            <input type="number" :name="'methods[{{ $wm->id }}][rates]['+i+'][weight_to]'" x-model="rate.weight_to" step="0.001" min="0"
                                                                                class="v-control w-full h-[var(--control-h-sm)] px-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                                                        </td>
                                                                        <td class="px-3 py-1.5">
                                                                            <input type="number" :name="'methods[{{ $wm->id }}][rates]['+i+'][cost]'" x-model="rate.cost" step="0.01" min="0"
                                                                                class="v-control w-full h-[var(--control-h-sm)] px-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                                                        </td>
                                                                        <td class="px-1 py-1.5 text-center">
                                                                            <button type="button" @click="rates.splice(i, 1)" class="p-0.5 text-v-muted hover:text-v-danger" title="Remove tier">
                                                                                <x-admin.icon name="x" class="w-3.5 h-3.5" />
                                                                            </button>
                                                                        </td>
                                                                    </tr>
                                                                </template>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </template>
                                                <template x-if="rates.length === 0">
                                                    <p class="text-[13px] text-v-muted text-center py-4">No tiers. Click "Add tier" above.</p>
                                                </template>
                                            </div>

                                            {{-- Actions --}}
                                            <div class="flex items-center justify-between pt-2 border-t border-v-border">
                                                <button type="button" class="text-xs font-medium text-v-danger hover:underline"
                                                    @click="$dispatch('open-modal', 'delete-wm-{{ $wm->id }}')">Delete method</button>
                                                <button type="button" class="inline-flex items-center gap-1 text-xs font-medium text-v-accent hover:underline"
                                                    @click="document.getElementById('duplicate-wm-{{ $wm->id }}').submit()">
                                                    Duplicate
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="pt-5">
                            <x-admin.button variant="primary" type="submit">Save all methods</x-admin.button>
                        </div>
                    </form>

                    {{-- Delete & duplicate can't live inside the bulk-update form (forms cannot nest). --}}
                    @foreach ($weightMethods as $wm)
                        <form id="duplicate-wm-{{ $wm->id }}" method="POST" action="{{ route('admin.weight-based-methods.duplicate', $wm) }}" class="hidden">
                            @csrf
                        </form>
                        <x-admin.confirm-dialog name="delete-wm-{{ $wm->id }}" title="Delete “{{ $wm->admin_name ?: $wm->name }}”?"
                            message="All weight tiers for this method will be removed." confirm="Delete method"
                            :action="route('admin.weight-based-methods.destroy', $wm)" method="DELETE" />
                    @endforeach
                @endif
            </x-admin.card>

            {{-- ==================== FLAT RATE ==================== --}}
            <x-admin.card id="panel-flat-rate" title="Flat Rate" description="Fixed-cost shipping methods with custom names.">
                <x-slot name="headerActions">
                    <x-admin.button variant="primary" size="sm" :href="route('admin.flat-rates.create')">Add flat rate</x-admin.button>
                </x-slot>

                @if ($flatRates->isEmpty())
                    <x-admin.empty-state icon="fulfillment" title="No flat rate methods"
                        description="Add a fixed-cost method scoped to a geo zone or customer group.">
                        <x-admin.button variant="primary" size="sm" :href="route('admin.flat-rates.create')">Add flat rate</x-admin.button>
                    </x-admin.empty-state>
                @else
                    <x-admin.table>
                        <x-slot:head>
                            <x-admin.table.th>Name</x-admin.table.th>
                            <x-admin.table.th>Geo zone</x-admin.table.th>
                            <x-admin.table.th>Group</x-admin.table.th>
                            <x-admin.table.th align="right">Cost</x-admin.table.th>
                            <x-admin.table.th>Status</x-admin.table.th>
                            <x-admin.table.th align="right">Actions</x-admin.table.th>
                        </x-slot:head>
                        @foreach ($flatRates as $fr)
                            <x-admin.table.row>
                                <x-admin.table.cell><span class="font-medium text-v-text">{{ $fr->name ?: 'Unnamed' }}</span></x-admin.table.cell>
                                <x-admin.table.cell><span class="text-v-secondary">{{ $fr->geoZone?->name ?? 'All' }}</span></x-admin.table.cell>
                                <x-admin.table.cell><span class="text-v-secondary">{{ $fr->customerGroup?->name ?? 'All' }}</span></x-admin.table.cell>
                                <x-admin.table.cell align="right" mono>₱{{ number_format($fr->cost, 2) }}</x-admin.table.cell>
                                <x-admin.table.cell>
                                    <x-admin.badge :tone="$fr->is_active ? 'success' : 'neutral'">{{ $fr->is_active ? 'Active' : 'Inactive' }}</x-admin.badge>
                                </x-admin.table.cell>
                                <x-admin.table.cell align="right">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('admin.flat-rates.edit', $fr) }}" class="text-xs font-medium text-v-accent hover:underline">Edit</a>
                                        <button type="button" class="text-xs font-medium text-v-danger hover:underline"
                                            @click="$dispatch('open-modal', 'delete-fr-{{ $fr->id }}')">Delete</button>
                                    </div>
                                </x-admin.table.cell>
                            </x-admin.table.row>
                        @endforeach
                    </x-admin.table>
                @endif
            </x-admin.card>
            @foreach ($flatRates as $fr)
                <x-admin.confirm-dialog name="delete-fr-{{ $fr->id }}" title="Delete “{{ $fr->name ?: 'this flat rate' }}”?"
                    message="This flat rate method will no longer be offered at checkout." confirm="Delete"
                    :action="route('admin.flat-rates.destroy', $fr)" method="DELETE" />
            @endforeach

            {{-- ==================== DHL EXPRESS ==================== --}}
            <x-admin.card id="panel-dhl" title="DHL Express" description="International shipments via the DHL Express API.">
                <div class="space-y-6">
                    <form method="POST" action="{{ route('admin.settings.update') }}" data-v-form class="space-y-5 max-w-lg">
                        @csrf
                        <input type="hidden" name="group" value="shipping">
                        <input type="hidden" name="_redirect" value="{{ route('admin.shipping-settings.index') }}">

                        <x-admin.field name="settings[dhl_site_id]" label="Site ID"
                            :help="!empty($maskedKeys['dhl_site_id'] ?? null) ? 'Current key ends in ' . $maskedKeys['dhl_site_id'] . ' — leave blank to keep it.' : null">
                            <x-admin.input name="settings[dhl_site_id]" autocomplete="off" placeholder="Enter Site ID" />
                        </x-admin.field>

                        <x-admin.field name="settings[dhl_password]" label="Password"
                            :help="!empty($maskedKeys['dhl_password'] ?? null) ? 'Current password ends in ' . $maskedKeys['dhl_password'] . ' — leave blank to keep it.' : null">
                            <x-admin.input type="password" name="settings[dhl_password]" autocomplete="new-password" placeholder="Enter Password" />
                        </x-admin.field>

                        <x-admin.field name="settings[dhl_account_number]" label="Account number">
                            <x-admin.input name="settings[dhl_account_number]" :value="$settings['shipping']['dhl_account_number'] ?? ''" placeholder="e.g. 642344995" />
                        </x-admin.field>

                        <x-admin.form-actions>
                            <x-admin.button variant="primary" type="submit">Save credentials</x-admin.button>
                        </x-admin.form-actions>
                    </form>

                    <div class="flex items-center gap-3 border-t border-v-border pt-5">
                        <form method="POST" action="{{ route('admin.settings.test-dhl') }}" data-v-form>
                            @csrf
                            <x-admin.button variant="secondary" type="submit">Test connection</x-admin.button>
                        </form>
                        <span class="text-xs text-v-muted">Tests a rate quote from Manila to Singapore.</span>
                    </div>
                </div>
            </x-admin.card>

            {{-- ==================== ORIGIN ==================== --}}
            <x-admin.card id="origin" title="Origin"
                description="Used as the origin for courier rate quotes (DHL) and future fulfillment bookings.">
                <form method="POST" action="{{ route('admin.settings.update') }}" data-v-form class="space-y-5">
                    @csrf
                    <input type="hidden" name="group" value="shipping">
                    <input type="hidden" name="_redirect" value="{{ route('admin.shipping-settings.index') }}">

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-admin.field name="settings[pickup_address]" label="Address" class="sm:col-span-2">
                            <x-admin.input name="settings[pickup_address]" :value="$settings['shipping']['pickup_address'] ?? ''" placeholder="Unit / street / building" />
                        </x-admin.field>
                        <x-admin.field name="settings[pickup_barangay]" label="Barangay">
                            <x-admin.input name="settings[pickup_barangay]" :value="$settings['shipping']['pickup_barangay'] ?? ''" />
                        </x-admin.field>
                        <x-admin.field name="settings[pickup_city]" label="City">
                            <x-admin.input name="settings[pickup_city]" :value="$settings['shipping']['pickup_city'] ?? ''" />
                        </x-admin.field>
                        <x-admin.field name="settings[pickup_province]" label="Province">
                            <x-admin.input name="settings[pickup_province]" :value="$settings['shipping']['pickup_province'] ?? ''" />
                        </x-admin.field>
                        <x-admin.field name="settings[pickup_postal_code]" label="Postal code">
                            <x-admin.input name="settings[pickup_postal_code]" :value="$settings['shipping']['pickup_postal_code'] ?? ''" />
                        </x-admin.field>
                        <x-admin.field name="settings[pickup_contact_name]" label="Contact name">
                            <x-admin.input name="settings[pickup_contact_name]" :value="$settings['shipping']['pickup_contact_name'] ?? ''" />
                        </x-admin.field>
                        <x-admin.field name="settings[pickup_contact_number]" label="Contact number">
                            <x-admin.input name="settings[pickup_contact_number]" :value="$settings['shipping']['pickup_contact_number'] ?? ''" placeholder="e.g. 09171234567" />
                        </x-admin.field>
                        <x-admin.field name="settings[pickup_contact_email]" label="Contact email" class="sm:col-span-2">
                            <x-admin.input type="email" name="settings[pickup_contact_email]" :value="$settings['shipping']['pickup_contact_email'] ?? ''" />
                        </x-admin.field>
                    </div>

                    <x-admin.form-actions>
                        <x-admin.button variant="primary" type="submit">Save origin</x-admin.button>
                    </x-admin.form-actions>
                </form>
            </x-admin.card>

        </div>
    </x-admin.page>

    {{--
        Old create/edit/duplicate flows for flat rates and weight-based methods
        still redirect here with ?tab=<slug> (e.g. after saving a new flat rate).
        Scroll the matching panel into view instead of leaving the admin at the
        top of a much longer page than the old tabbed layout.
    --}}
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const tab = new URLSearchParams(window.location.search).get('tab');
                if (tab) {
                    document.getElementById('panel-' + tab)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        </script>
    @endpush
</x-admin.shell>
