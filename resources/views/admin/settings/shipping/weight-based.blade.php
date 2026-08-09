<x-admin.settings-shell title="Weight Based" :wide="true">

    <x-admin.card title="Weight Based" description="Cost tiers based on cart weight. Click a method to expand and edit.">
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
                                                            <th class="px-3 py-2 text-left v-label">From (kg)</th>
                                                            <th class="px-3 py-2 text-left v-label">To (kg)</th>
                                                            <th class="px-3 py-2 text-left v-label">Cost (₱)</th>
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

</x-admin.settings-shell>
