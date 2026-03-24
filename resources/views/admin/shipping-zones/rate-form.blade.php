<x-admin-layout>
    <x-slot name="header">Shipping Rates: {{ $zone->name }}</x-slot>
    <x-slot name="headerActions">
        <a href="{{ request('from') === 'shipping' ? route('admin.shipping-settings.index', ['tab' => 'weight-based']) : route('admin.shipping-zones.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors">
            Back
        </a>
    </x-slot>

    <form method="POST" action="{{ route('admin.shipping-zones.update', $zone) }}" x-data="rateEditor(@js($rates), @js($customerGroups->map(fn($g) => ['id' => $g->id, 'name' => $g->name])->values()->all()))">
        @csrf
        @method('PUT')

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <p class="text-sm text-gray-500 mb-4">Configure weight-based shipping rates for <strong>{{ $zone->name }}</strong>. Rates are matched by total cart weight.</p>

            {{-- Customer Group Tabs --}}
            <div class="border-b border-gray-200 mb-4">
                <nav class="flex gap-1 overflow-x-auto">
                    <button type="button" @click="activeGroup = 'default'"
                        :class="activeGroup === 'default' ? 'border-indigo-500 text-indigo-600 bg-indigo-50' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="px-4 py-2 text-sm font-medium border-b-2 rounded-t transition-colors whitespace-nowrap">
                        Default (All Groups)
                    </button>
                    <template x-for="group in groups" :key="group.id">
                        <button type="button" @click="activeGroup = String(group.id)"
                            :class="activeGroup === String(group.id) ? 'border-indigo-500 text-indigo-600 bg-indigo-50' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="px-4 py-2 text-sm font-medium border-b-2 rounded-t transition-colors whitespace-nowrap"
                            x-text="group.name">
                        </button>
                    </template>
                </nav>
            </div>

            {{-- Rate Table --}}
            <div>
                <table class="min-w-full">
                    <thead>
                        <tr class="text-xs text-gray-500 uppercase">
                            <th class="text-left py-2 pr-2">Weight From (kg)</th>
                            <th class="text-left py-2 pr-2">Weight To (kg)</th>
                            <th class="text-left py-2 pr-2">Cost (₱)</th>
                            <th class="text-left py-2 pr-2">Free Threshold (₱)</th>
                            <th class="w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(row, idx) in currentRates" :key="idx">
                            <tr class="border-t border-gray-100">
                                <td class="py-2 pr-2">
                                    <input type="number" :name="'rates[' + activeGroup + '][' + idx + '][weight_from]'" x-model="row.weight_from" step="0.001" min="0"
                                        class="w-full rounded border-gray-300 text-sm">
                                </td>
                                <td class="py-2 pr-2">
                                    <input type="number" :name="'rates[' + activeGroup + '][' + idx + '][weight_to]'" x-model="row.weight_to" step="0.001" min="0"
                                        class="w-full rounded border-gray-300 text-sm">
                                </td>
                                <td class="py-2 pr-2">
                                    <input type="number" :name="'rates[' + activeGroup + '][' + idx + '][cost]'" x-model="row.cost" step="0.01" min="0"
                                        class="w-full rounded border-gray-300 text-sm">
                                </td>
                                <td class="py-2 pr-2">
                                    <input type="number" :name="'rates[' + activeGroup + '][' + idx + '][free_shipping_threshold]'" x-model="row.free_shipping_threshold" step="0.01" min="0"
                                        class="w-full rounded border-gray-300 text-sm" placeholder="Optional">
                                </td>
                                <td class="py-2">
                                    <button type="button" @click="removeRow(idx)" class="text-red-500 hover:text-red-700">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>

                <button type="button" @click="addRow()" class="mt-3 text-sm text-indigo-600 hover:text-indigo-800 font-medium">+ Add Rate Row</button>
            </div>
        </div>

        {{-- Hidden inputs for all groups' rates --}}
        <template x-for="(groupKey, gIdx) in allGroupKeys" :key="'group-' + groupKey">
            <template x-for="(row, rIdx) in allRates[groupKey]" :key="'rate-' + groupKey + '-' + rIdx">
                <div x-show="false">
                    <input type="hidden" :name="'rates[' + groupKey + '][' + rIdx + '][weight_from]'" :value="row.weight_from">
                    <input type="hidden" :name="'rates[' + groupKey + '][' + rIdx + '][weight_to]'" :value="row.weight_to">
                    <input type="hidden" :name="'rates[' + groupKey + '][' + rIdx + '][cost]'" :value="row.cost">
                    <input type="hidden" :name="'rates[' + groupKey + '][' + rIdx + '][free_shipping_threshold]'" :value="row.free_shipping_threshold">
                </div>
            </template>
        </template>

        <div class="flex justify-end gap-3">
            <a href="{{ request('from') === 'shipping' ? route('admin.shipping-settings.index', ['tab' => 'weight-based']) : route('admin.shipping-zones.index') }}" class="px-6 py-2.5 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300">Cancel</a>
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save Rates</button>
        </div>
    </form>

    @push('scripts')
    <script>
        function rateEditor(initialRates, groups) {
            return {
                allRates: initialRates,
                groups: groups,
                activeGroup: 'default',

                get currentRates() {
                    if (!this.allRates[this.activeGroup]) {
                        this.allRates[this.activeGroup] = [];
                    }
                    return this.allRates[this.activeGroup];
                },

                get allGroupKeys() {
                    return Object.keys(this.allRates);
                },

                addRow() {
                    if (!this.allRates[this.activeGroup]) {
                        this.allRates[this.activeGroup] = [];
                    }
                    this.allRates[this.activeGroup].push({
                        weight_from: 0,
                        weight_to: '',
                        cost: '',
                        free_shipping_threshold: ''
                    });
                },

                removeRow(idx) {
                    this.allRates[this.activeGroup].splice(idx, 1);
                }
            };
        }
    </script>
    @endpush
</x-admin-layout>
