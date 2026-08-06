@php
    $vCheck = 'w-4 h-4 rounded-v-sm border-v-strong text-v-accent focus:ring-v-accent bg-v-surface';
@endphp
<x-admin.shell :title="$method ? 'Edit Weight-Based Method' : 'New Weight-Based Method'">
    <x-admin.page :title="$method ? 'Edit Weight-Based Method' : 'New Weight-Based Method'" :breadcrumb="[['label' => 'Settings', 'url' => route('admin.settings.index')], ['label' => 'Shipping', 'url' => route('admin.shipping-settings.index') . '#panel-weight-based']]">
        <x-slot:actions>
            <x-admin.button variant="ghost" :href="route('admin.shipping-settings.index') . '#panel-weight-based'">Back to Shipping</x-admin.button>
        </x-slot:actions>

        <form method="POST"
              action="{{ $method ? route('admin.weight-based-methods.update', $method) : route('admin.weight-based-methods.store') }}"
              x-data="weightMethodForm()"
              data-v-form>
            @csrf
            @if ($method) @method('PUT') @endif

            <div class="grid grid-cols-1 lg:grid-cols-[1fr_300px] gap-6">
                {{-- Left --}}
                <div class="space-y-6">
                    <x-admin.card>
                        <div class="space-y-4">
                            <x-admin.field name="admin_name" label="Admin Reference Name" help="Internal label for admin — not shown to customers. Leave blank to copy from Method Name.">
                                <x-admin.input type="text" name="admin_name" :value="$method?->admin_name" placeholder="e.g. WB - Metro Manila Default, WB - Provincial Resellers" />
                            </x-admin.field>
                            <x-admin.field name="name" label="Method Name" help="This name appears in checkout exactly as you type it">
                                <x-admin.input type="text" name="name" required :value="$method?->name" placeholder="e.g. Standard Shipping, Express Courier" />
                            </x-admin.field>
                        </div>
                    </x-admin.card>

                    {{-- Weight Tiers --}}
                    <x-admin.card title="Weight Tiers">
                        <x-slot:headerActions>
                            <x-admin.button type="button" variant="secondary" size="sm" @click="addRate()">
                                <x-admin.icon name="plus" class="w-3.5 h-3.5" /> Add Tier
                            </x-admin.button>
                        </x-slot:headerActions>

                        <template x-if="rates.length === 0">
                            <p class="text-[13px] text-v-muted text-center py-6">No weight tiers. Click "Add Tier" to define costs by weight range.</p>
                        </template>

                        <template x-if="rates.length > 0">
                            <x-admin.table>
                                <x-slot:head>
                                    <x-admin.table.th>From (kg)</x-admin.table.th>
                                    <x-admin.table.th>To (kg)</x-admin.table.th>
                                    <x-admin.table.th>Cost (₱)</x-admin.table.th>
                                    <x-admin.table.th></x-admin.table.th>
                                </x-slot:head>
                                <template x-for="(rate, i) in rates" :key="i">
                                    <tr class="v-row">
                                        <td class="px-4 py-2">
                                            <input type="number" :name="'rates['+i+'][weight_from]'" x-model="rate.weight_from" step="0.001" min="0"
                                                class="v-control w-full h-[var(--control-h-sm)] px-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                        </td>
                                        <td class="px-4 py-2">
                                            <input type="number" :name="'rates['+i+'][weight_to]'" x-model="rate.weight_to" step="0.001" min="0"
                                                class="v-control w-full h-[var(--control-h-sm)] px-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                        </td>
                                        <td class="px-4 py-2">
                                            <input type="number" :name="'rates['+i+'][cost]'" x-model="rate.cost" step="0.01" min="0"
                                                class="v-control w-full h-[var(--control-h-sm)] px-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                        </td>
                                        <td class="px-2 py-2 text-center">
                                            <button type="button" @click="rates.splice(i, 1)" class="p-1 text-v-muted hover:text-v-danger transition-colors" title="Remove tier">
                                                <x-admin.icon name="x" class="w-4 h-4" />
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </x-admin.table>
                        </template>
                    </x-admin.card>
                </div>

                {{-- Right --}}
                <div class="space-y-6">
                    <x-admin.card title="Geo Zones" description="Select which geo zones this method applies to. Leave all unchecked for all zones.">
                        @php $selectedGeoZones = old('geo_zone_ids', $method?->geoZones?->pluck('id')->toArray() ?? []); @endphp
                        <div class="space-y-1.5 max-h-48 overflow-y-auto">
                            @foreach ($geoZones as $zone)
                                <label class="flex items-center gap-2 text-[13px] text-v-text cursor-pointer hover:text-v-text">
                                    <input type="checkbox" name="geo_zone_ids[]" value="{{ $zone->id }}"
                                        {{ in_array($zone->id, $selectedGeoZones) ? 'checked' : '' }}
                                        class="{{ $vCheck }}">
                                    {{ $zone->name }}
                                </label>
                            @endforeach
                        </div>
                        @if ($geoZones->isEmpty())
                            <p class="text-xs text-v-muted">No geo zones configured.</p>
                        @endif
                    </x-admin.card>

                    <x-admin.card title="Customer Groups" description="Restrict to specific customer groups. Leave all unchecked for all customers.">
                        @php $selectedGroups = old('customer_group_ids', $method?->customerGroups?->pluck('id')->toArray() ?? []); @endphp
                        <div class="space-y-1.5 max-h-48 overflow-y-auto">
                            @foreach ($customerGroups as $group)
                                <label class="flex items-center gap-2 text-[13px] text-v-text cursor-pointer hover:text-v-text">
                                    <input type="checkbox" name="customer_group_ids[]" value="{{ $group->id }}"
                                        {{ in_array($group->id, $selectedGroups) ? 'checked' : '' }}
                                        class="{{ $vCheck }}">
                                    {{ $group->name }}
                                </label>
                            @endforeach
                        </div>
                        @if ($customerGroups->isEmpty())
                            <p class="text-xs text-v-muted">No customer groups configured.</p>
                        @endif
                    </x-admin.card>

                    <x-admin.card title="Settings">
                        <div class="space-y-4">
                            <label class="flex items-center justify-between cursor-pointer">
                                <span class="text-[13px] text-v-text">Active</span>
                                <x-admin.toggle name="is_active" :checked="$method?->is_active ?? true" />
                            </label>
                            <x-admin.field name="sort_order" label="Sort Order">
                                <x-admin.input type="number" name="sort_order" :value="$method?->sort_order ?? 0" />
                            </x-admin.field>
                        </div>
                    </x-admin.card>

                    <x-admin.button type="submit" class="w-full justify-center">
                        {{ $method ? 'Update Method' : 'Create Method' }}
                    </x-admin.button>
                </div>
            </div>
        </form>

        @php
            $ratesData = old('rates', []);
            if (empty($ratesData) && $method?->rates) {
                $ratesData = $method->rates->map(function($r) {
                    return ['weight_from' => $r->weight_from, 'weight_to' => $r->weight_to, 'cost' => $r->cost];
                })->toArray();
            }
        @endphp

        @push('scripts')
        <script>
        function weightMethodForm() {
            return {
                rates: @json($ratesData),
                addRate() {
                    const last = this.rates.length > 0 ? this.rates[this.rates.length - 1] : null;
                    this.rates.push({
                        weight_from: last ? last.weight_to : '0.000',
                        weight_to: last ? (parseFloat(last.weight_to) + 5).toFixed(3) : '5.000',
                        cost: last ? last.cost : '0.00',
                    });
                },
            };
        }
        </script>
        @endpush
    </x-admin.page>
</x-admin.shell>
