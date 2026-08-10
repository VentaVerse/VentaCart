<x-admin.shell :title="$method ? 'Edit Weight-Based Method' : 'New Weight-Based Method'">
    <x-admin.page :title="$method ? 'Edit Weight-Based Method' : 'New Weight-Based Method'" :breadcrumb="[['label' => 'Settings', 'url' => route('admin.settings.index')], ['label' => 'Shipping', 'url' => route('admin.shipping-settings.weight-based')]]">
        <x-slot:actions>
            <x-admin.button variant="ghost" :href="route('admin.shipping-settings.weight-based')">Back to Shipping</x-admin.button>
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
                            <x-admin.field name="admin_name" label="Admin reference name" help="Never shown to customers. Blank copies the checkout name.">
                                <x-admin.input type="text" name="admin_name" :value="$method?->admin_name" placeholder="e.g. WB - Metro Manila Default, WB - Provincial Resellers" />
                            </x-admin.field>
                            <x-admin.field name="name" label="Checkout name" required>
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
                            <p class="v-panel__blank">No weight tiers yet.</p>
                        </template>

                        <template x-if="rates.length > 0">
                            <x-admin.table>
                                <x-slot:head>
                                    <x-admin.table.th>From (kg)</x-admin.table.th>
                                    <x-admin.table.th>To (kg)</x-admin.table.th>
                                    <x-admin.table.th>Cost (₱)</x-admin.table.th>
                                    <x-admin.table.th class="v-col-act"><span class="v-sr">Remove</span></x-admin.table.th>
                                </x-slot:head>
                                <template x-for="(rate, i) in rates" :key="i">
                                    <tr class="v-row">
                                        <td class="px-4 py-2">
                                            <input type="number" :name="'rates['+i+'][weight_from]'" x-model="rate.weight_from" step="0.001" min="0" aria-label="Weight from"
                                                class="v-control h-[var(--control-h-sm)] w-full rounded-v-md border bg-v-surface px-2 text-[13px] text-v-text">
                                        </td>
                                        <td class="px-4 py-2">
                                            <input type="number" :name="'rates['+i+'][weight_to]'" x-model="rate.weight_to" step="0.001" min="0" aria-label="Weight to"
                                                class="v-control h-[var(--control-h-sm)] w-full rounded-v-md border bg-v-surface px-2 text-[13px] text-v-text">
                                        </td>
                                        <td class="px-4 py-2">
                                            <input type="number" :name="'rates['+i+'][cost]'" x-model="rate.cost" step="0.01" min="0" aria-label="Cost"
                                                class="v-control h-[var(--control-h-sm)] w-full rounded-v-md border bg-v-surface px-2 text-[13px] text-v-text">
                                        </td>
                                        <td class="v-col-act">
                                            <div class="v-rowactions v-rowactions--end">
                                                <x-admin.button type="button" size="sm" variant="ghost" class="v-rowactions__danger" @click="rates.splice(i, 1)" aria-label="Remove tier">
                                                    <x-admin.icon name="x" class="w-3.5 h-3.5" />
                                                </x-admin.button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </x-admin.table>
                        </template>
                    </x-admin.card>
                </div>

                {{-- Right --}}
                <div class="space-y-6">
                    <x-admin.card title="Geo Zones">
                        <x-slot name="headerActions">
                            <x-admin.hint label="About geo zone scope" class="v-hint--end">Leave every box unchecked and the method quotes for all zones.</x-admin.hint>
                        </x-slot>
                        @php $selectedGeoZones = old('geo_zone_ids', $method?->geoZones?->pluck('id')->toArray() ?? []); @endphp
                        <div class="v-checklist">
                            @foreach ($geoZones as $zone)
                                <x-admin.checkbox name="geo_zone_ids[]" :value="$zone->id" :label="$zone->name"
                                    :checked="in_array($zone->id, $selectedGeoZones)" />
                            @endforeach
                        </div>
                        @if ($geoZones->isEmpty())
                            <p class="v-panel__blank">No geo zones configured.</p>
                        @endif
                    </x-admin.card>

                    <x-admin.card title="Customer Groups">
                        <x-slot name="headerActions">
                            <x-admin.hint label="About customer group scope" class="v-hint--end">Leave every box unchecked and the method quotes for all customers.</x-admin.hint>
                        </x-slot>
                        @php $selectedGroups = old('customer_group_ids', $method?->customerGroups?->pluck('id')->toArray() ?? []); @endphp
                        <div class="v-checklist">
                            @foreach ($customerGroups as $group)
                                <x-admin.checkbox name="customer_group_ids[]" :value="$group->id" :label="$group->name"
                                    :checked="in_array($group->id, $selectedGroups)" />
                            @endforeach
                        </div>
                        @if ($customerGroups->isEmpty())
                            <p class="v-panel__blank">No customer groups configured.</p>
                        @endif
                    </x-admin.card>

                    <x-admin.card title="Settings">
                        <div class="space-y-4">
                            <x-admin.toggle name="is_active" label="Active" lead class="w-full justify-between"
                                :checked="$method?->is_active ?? true" />
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
