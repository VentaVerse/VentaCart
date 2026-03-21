<x-admin-layout>
    <x-slot name="header">{{ $zone ? 'Edit Shipping Zone' : 'Create Shipping Zone' }}</x-slot>
    <x-slot name="headerActions">
        <a href="{{ route('admin.shipping-zones.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors">
            Back to Zones
        </a>
    </x-slot>

    @php
        // Group regions by island group
        $islandGroups = [
            'NCR' => [],
            'Luzon' => [],
            'Visayas' => [],
            'Mindanao' => [],
        ];

        $luzonCodes = ['01', '02', '03', '04', '17', '05', '14', '15']; // I, II, III, IV-A, IV-B/MIMAROPA, V, CAR, CVR
        $visayasCodes = ['06', '07', '08']; // VI, VII, VIII
        $mindanaoCodes = ['09', '10', '11', '12', '13', '16', '19']; // IX, X, XI, XII, XIII, Caraga, BARMM

        foreach ($regions as $region) {
            $name = strtoupper($region->name);
            $code = $region->code;

            if (str_contains($name, 'NCR') || str_contains($name, 'NATIONAL CAPITAL')) {
                $islandGroups['NCR'][] = $region;
            } elseif (in_array($code, $luzonCodes) || str_contains($name, 'ILOCOS') || str_contains($name, 'CAGAYAN') || str_contains($name, 'CENTRAL LUZON') || str_contains($name, 'CALABARZON') || str_contains($name, 'MIMAROPA') || str_contains($name, 'BICOL') || str_contains($name, 'CAR') || str_contains($name, 'CORDILLERA')) {
                $islandGroups['Luzon'][] = $region;
            } elseif (in_array($code, $visayasCodes) || str_contains($name, 'WESTERN VISAYAS') || str_contains($name, 'CENTRAL VISAYAS') || str_contains($name, 'EASTERN VISAYAS')) {
                $islandGroups['Visayas'][] = $region;
            } else {
                $islandGroups['Mindanao'][] = $region;
            }
        }

        // Build rate tab labels
        $rateTabs = [['key' => 'default', 'label' => 'Default']];
        foreach ($customerGroups as $cg) {
            $rateTabs[] = ['key' => (string) $cg->id, 'label' => $cg->name];
        }
    @endphp

    <form method="POST"
          action="{{ $zone ? route('admin.shipping-zones.update', $zone) : route('admin.shipping-zones.store') }}">
        @csrf
        @if ($zone)
            @method('PUT')
        @endif

        {{-- Section 1: General --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 border-b border-gray-200 pb-3">General</h2>

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Zone Name</label>
                <input type="text" name="name" id="name" required value="{{ old('name', $zone?->name) }}"
                    class="w-full max-w-md rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                <input type="number" name="sort_order" id="sort_order" min="0" value="{{ old('sort_order', $zone?->sort_order ?? 0) }}"
                    class="w-24 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div class="space-y-3">
                <label class="flex items-center">
                    <input type="hidden" name="is_default" value="0">
                    <input type="checkbox" name="is_default" value="1" {{ old('is_default', $zone?->is_default) ? 'checked' : '' }}
                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="ml-2 text-sm text-gray-700">Default zone</span>
                </label>
                <p class="text-xs text-gray-400 ml-6">Used when no other zone matches the delivery address.</p>

                <label class="flex items-center">
                    <input type="hidden" name="status" value="0">
                    <input type="checkbox" name="status" value="1" {{ old('status', $zone?->status ?? true) ? 'checked' : '' }}
                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="ml-2 text-sm text-gray-700">Active</span>
                </label>
            </div>
        </div>

        {{-- Section 2: Regions --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 border-b border-gray-200 pb-3 mb-4">Regions</h2>
            <p class="text-sm text-gray-500 mb-4">Select which Philippine regions this shipping zone covers.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @foreach ($islandGroups as $island => $islandRegions)
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800 mb-2 uppercase tracking-wide">{{ $island }}</h3>
                        <div class="space-y-2 pl-1">
                            @foreach ($islandRegions as $region)
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="region_ids[]" value="{{ $region->id }}"
                                        {{ in_array($region->id, old('region_ids', $assignedRegionIds)) ? 'checked' : '' }}
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span class="text-sm text-gray-700">{{ $region->name }}</span>
                                    <span class="text-xs text-gray-400">({{ $region->provinces->count() }} provinces)</span>
                                </label>
                            @endforeach
                            @if (count($islandRegions) === 0)
                                <p class="text-xs text-gray-400 italic">No regions</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Section 3: Rate Table --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6"
             x-data="rateTable({{ json_encode($rates) }}, {{ json_encode($rateTabs) }})">
            <h2 class="text-lg font-semibold text-gray-800 border-b border-gray-200 pb-3 mb-4">Shipping Rates</h2>
            <p class="text-sm text-gray-500 mb-4">Define weight-based rates. Leave a tab empty to inherit from Default.</p>

            {{-- Tabs --}}
            <div class="border-b border-gray-200 mb-4">
                <nav class="flex space-x-4 -mb-px">
                    <template x-for="tab in tabs" :key="tab.key">
                        <button type="button"
                            @click="activeTab = tab.key"
                            :class="activeTab === tab.key
                                ? 'border-indigo-500 text-indigo-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap pb-3 px-1 border-b-2 font-medium text-sm transition-colors"
                            x-text="tab.label">
                        </button>
                    </template>
                </nav>
            </div>

            {{-- Rate rows per tab --}}
            <template x-for="tab in tabs" :key="'panel-' + tab.key">
                <div x-show="activeTab === tab.key" x-cloak>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase">
                                <th class="pb-2 pr-2">Weight From (kg)</th>
                                <th class="pb-2 pr-2">Weight To (kg)</th>
                                <th class="pb-2 pr-2">Cost</th>
                                <th class="pb-2 pr-2">Free Shipping Threshold</th>
                                <th class="pb-2 w-10"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(row, index) in rateRows[tab.key]" :key="tab.key + '-' + index">
                                <tr class="align-top">
                                    <td class="pr-2 pb-2">
                                        <input type="number" step="0.001" min="0"
                                            :name="`rates[${tab.key}][${index}][weight_from]`"
                                            x-model="row.weight_from"
                                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </td>
                                    <td class="pr-2 pb-2">
                                        <input type="number" step="0.001" min="0"
                                            :name="`rates[${tab.key}][${index}][weight_to]`"
                                            x-model="row.weight_to"
                                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </td>
                                    <td class="pr-2 pb-2">
                                        <input type="number" step="0.01" min="0"
                                            :name="`rates[${tab.key}][${index}][cost]`"
                                            x-model="row.cost"
                                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </td>
                                    <td class="pr-2 pb-2">
                                        <input type="number" step="0.01" min="0"
                                            :name="`rates[${tab.key}][${index}][free_shipping_threshold]`"
                                            x-model="row.free_shipping_threshold"
                                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            placeholder="Optional">
                                    </td>
                                    <td class="pb-2">
                                        <button type="button" @click="removeRow(tab.key, index)"
                                            class="p-1.5 text-red-400 hover:text-red-600 transition-colors" title="Remove row">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <button type="button" @click="addRow(tab.key)"
                        class="mt-2 inline-flex items-center px-3 py-1.5 text-sm text-indigo-600 hover:text-indigo-800 transition-colors">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Add Rate Row
                    </button>

                    <template x-if="rateRows[tab.key].length === 0">
                        <p class="text-sm text-gray-400 italic mt-2" x-show="tab.key !== 'default'">No rates defined. Will use Default rates.</p>
                    </template>
                </div>
            </template>
        </div>

        <div class="flex justify-end space-x-3 mb-6">
            <a href="{{ route('admin.shipping-zones.index') }}" class="px-6 py-2.5 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors">Cancel</a>
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                {{ $zone ? 'Update Zone' : 'Create Zone' }}
            </button>
        </div>
    </form>

    @push('scripts')
    <script>
        function rateTable(initialRates, tabs) {
            // Build rateRows from initialRates, ensuring every tab key exists
            var rateRows = {};
            for (var i = 0; i < tabs.length; i++) {
                var key = tabs[i].key;
                rateRows[key] = initialRates[key] || [];
            }

            return {
                tabs: tabs,
                activeTab: 'default',
                rateRows: rateRows,
                addRow: function(tabKey) {
                    this.rateRows[tabKey].push({
                        weight_from: 0,
                        weight_to: null,
                        cost: 0,
                        free_shipping_threshold: null
                    });
                },
                removeRow: function(tabKey, index) {
                    this.rateRows[tabKey].splice(index, 1);
                }
            };
        }
    </script>
    @endpush
</x-admin-layout>
