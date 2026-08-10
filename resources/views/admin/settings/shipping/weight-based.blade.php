<x-admin.settings-shell title="Weight Based">
    <x-admin.card title="Weight Based">
        <x-slot name="headerActions">
            <x-admin.button variant="primary" size="sm" :href="route('admin.weight-based-methods.create')">Add method</x-admin.button>
        </x-slot>

        @if ($weightMethods->isEmpty())
            <x-admin.empty-state title="No weight-based methods" description="Create tiers priced by cart weight.">
                <x-admin.button variant="primary" size="sm" :href="route('admin.weight-based-methods.create')">Add method</x-admin.button>
            </x-admin.empty-state>
        @else
            <form method="POST" action="{{ route('admin.weight-based-methods.bulk-update') }}" data-v-form>
                @csrf
                @method('PUT')
                <div class="v-sheet v-sortlist overflow-hidden">
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
                            <button type="button" class="v-sortrow w-full text-left" @click="open = !open" x-bind:aria-expanded="open">
                                <span class="shrink-0 text-v-muted" aria-hidden="true">
                                    <x-admin.icon name="chevron-right" class="w-3.5 h-3.5 transition-transform" x-bind:class="open && 'rotate-90'" />
                                </span>
                                <span class="v-sortrow__body">
                                    <span class="v-sortrow__name">{{ $wm->admin_name ?: $wm->name }}</span>
                                </span>
                                <span class="v-sortrow__fact">{{ $wm->geoZones->isNotEmpty() ? $wm->geoZones->pluck('name')->implode(', ') : 'All zones' }}</span>
                                <span class="v-sortrow__fact">{{ $wm->customerGroups->isNotEmpty() ? $wm->customerGroups->pluck('name')->implode(', ') : 'All groups' }}</span>
                                <span class="v-sortrow__fact">{{ $wm->rates_count }} {{ Str::plural('tier', $wm->rates_count) }}</span>
                                <x-admin.badge :tone="$wm->is_active ? 'success' : 'neutral'">{{ $wm->is_active ? 'Active' : 'Inactive' }}</x-admin.badge>
                            </button>

                            <div x-show="open" x-transition x-cloak class="v-sortnest space-y-4 border-t border-v-border px-5 py-4">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <x-admin.field :name="'methods[' . $wm->id . '][admin_name]'" label="Admin name">
                                        <x-admin.input :name="'methods[' . $wm->id . '][admin_name]'" :value="$wm->admin_name" placeholder="Internal reference" />
                                    </x-admin.field>
                                    <x-admin.field :name="'methods[' . $wm->id . '][name]'" label="Checkout name">
                                        <x-admin.input :name="'methods[' . $wm->id . '][name]'" :value="$wm->name" required />
                                    </x-admin.field>
                                    <x-admin.field :name="'methods[' . $wm->id . '][sort_order]'" label="Sort">
                                        <x-admin.input type="number" :name="'methods[' . $wm->id . '][sort_order]'" :value="$wm->sort_order" class="w-20" />
                                    </x-admin.field>
                                    <div class="flex items-end pb-1.5">
                                        <x-admin.checkbox :name="'methods[' . $wm->id . '][is_active]'" :checked="$wm->is_active" label="Active" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <x-admin.field :name="'methods[' . $wm->id . '][geo_zone_ids]'" label="Geo zones" help="None checked quotes for every zone.">
                                        <div class="v-checklist">
                                            @foreach ($geoZones as $zone)
                                                <x-admin.checkbox :name="'methods[' . $wm->id . '][geo_zone_ids][]'" :value="$zone->id"
                                                    :checked="$wm->geoZones->contains('id', $zone->id)" :label="$zone->name" />
                                            @endforeach
                                        </div>
                                        @if ($geoZones->isEmpty())
                                            <p class="v-panel__blank">No geo zones</p>
                                        @endif
                                    </x-admin.field>
                                    <x-admin.field :name="'methods[' . $wm->id . '][customer_group_ids]'" label="Customer groups" help="None checked quotes for every customer.">
                                        <div class="v-checklist">
                                            @foreach ($customerGroups as $group)
                                                <x-admin.checkbox :name="'methods[' . $wm->id . '][customer_group_ids][]'" :value="$group->id"
                                                    :checked="$wm->customerGroups->contains('id', $group->id)" :label="$group->name" />
                                            @endforeach
                                        </div>
                                        @if ($customerGroups->isEmpty())
                                            <p class="v-panel__blank">No customer groups</p>
                                        @endif
                                    </x-admin.field>
                                </div>

                                <div>
                                    <div class="mb-2 flex items-center justify-between gap-3">
                                        <span class="v-label">Weight tiers</span>
                                        <x-admin.button type="button" variant="secondary" size="sm" @click="addRate()">
                                            <x-admin.icon name="plus" class="w-3 h-3" /> Add tier
                                        </x-admin.button>
                                    </div>
                                    <template x-if="rates.length > 0">
                                        <x-admin.table>
                                            <x-slot:head>
                                                <x-admin.table.th>From (kg)</x-admin.table.th>
                                                <x-admin.table.th>To (kg)</x-admin.table.th>
                                                <x-admin.table.th>Cost (&#8369;)</x-admin.table.th>
                                                <x-admin.table.th class="v-col-act"><span class="v-sr">Remove</span></x-admin.table.th>
                                            </x-slot:head>
                                            <template x-for="(rate, i) in rates" :key="i">
                                                <tr class="v-row">
                                                    <td class="px-4 py-2">
                                                        <input type="number" :name="'methods[{{ $wm->id }}][rates]['+i+'][weight_from]'" x-model="rate.weight_from" step="0.001" min="0" aria-label="Weight from"
                                                            class="v-control h-[var(--control-h-sm)] w-full rounded-v-md border bg-v-surface px-2 text-[13px] text-v-text">
                                                    </td>
                                                    <td class="px-4 py-2">
                                                        <input type="number" :name="'methods[{{ $wm->id }}][rates]['+i+'][weight_to]'" x-model="rate.weight_to" step="0.001" min="0" aria-label="Weight to"
                                                            class="v-control h-[var(--control-h-sm)] w-full rounded-v-md border bg-v-surface px-2 text-[13px] text-v-text">
                                                    </td>
                                                    <td class="px-4 py-2">
                                                        <input type="number" :name="'methods[{{ $wm->id }}][rates]['+i+'][cost]'" x-model="rate.cost" step="0.01" min="0" aria-label="Cost"
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
                                    <template x-if="rates.length === 0">
                                        <p class="v-panel__blank">No tiers yet.</p>
                                    </template>
                                </div>

                                <div class="flex items-center justify-between gap-3 border-t border-v-border pt-3">
                                    <x-admin.button type="button" size="sm" variant="ghost" class="v-rowactions__danger"
                                        @click="$dispatch('open-modal', 'delete-wm-{{ $wm->id }}')">Delete method</x-admin.button>
                                    <x-admin.button type="button" size="sm" variant="ghost"
                                        @click="document.getElementById('duplicate-wm-{{ $wm->id }}').submit()">Duplicate</x-admin.button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <x-admin.form-actions>
                    <x-admin.button variant="primary" type="submit">Save all methods</x-admin.button>
                </x-admin.form-actions>
            </form>

            {{-- Delete and duplicate cannot live inside the bulk-update form: forms do not nest. --}}
            @foreach ($weightMethods as $wm)
                <form id="duplicate-wm-{{ $wm->id }}" method="POST" action="{{ route('admin.weight-based-methods.duplicate', $wm) }}" class="hidden">
                    @csrf
                </form>
                <x-admin.confirm-dialog name="delete-wm-{{ $wm->id }}" title="Delete &ldquo;{{ $wm->admin_name ?: $wm->name }}&rdquo;?"
                    message="All weight tiers for this method will be removed." confirm="Delete method"
                    :action="route('admin.weight-based-methods.destroy', $wm)" method="DELETE" />
            @endforeach
        @endif
    </x-admin.card>
</x-admin.settings-shell>
