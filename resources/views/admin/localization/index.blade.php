<x-admin-layout>
    <x-slot name="header">Localization</x-slot>

    <div x-data="{ activeTab: '{{ $tab }}' }">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <nav class="flex border-b border-gray-200 overflow-x-auto">
                @foreach ([['id' => 'countries', 'label' => 'Countries'], ['id' => 'zones', 'label' => 'Zones'], ['id' => 'geo-zones', 'label' => 'Geo Zones'], ['id' => 'ph-reference', 'label' => 'PH Reference']] as $t)
                    <button type="button" @click="activeTab = '{{ $t['id'] }}'"
                        :class="activeTab === '{{ $t['id'] }}' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap px-5 py-3 border-b-2 text-sm font-medium transition-colors">{{ $t['label'] }}</button>
                @endforeach
            </nav>

            {{-- ==================== COUNTRIES ==================== --}}
            <div x-show="activeTab === 'countries'" class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-sm text-gray-500">{{ $countries->where('is_active', true)->count() }} of {{ $countries->count() }} countries enabled</p>
                </div>

                <div class="overflow-hidden rounded-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Country</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-20">ISO</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-20">Phone</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-24">Order</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-24">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($countries as $country)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2.5 text-sm text-gray-900">{{ $country->name }}</td>
                                    <td class="px-4 py-2.5 text-xs text-gray-500 font-mono">{{ $country->iso2 }}</td>
                                    <td class="px-4 py-2.5 text-xs text-gray-500">{{ $country->phone_code ? '+' . $country->phone_code : '—' }}</td>
                                    <td class="px-4 py-2.5 text-sm text-gray-500">{{ $country->sort_order }}</td>
                                    <td class="px-4 py-2.5">
                                        <form method="POST" action="{{ route('admin.localization.toggle-country', $country) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $country->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500' }}">
                                                {{ $country->is_active ? 'Active' : 'Disabled' }}
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ==================== ZONES ==================== --}}
            <div x-show="activeTab === 'zones'" x-cloak class="p-6">
                {{-- Country selector --}}
                <form method="GET" class="flex items-center gap-3 mb-4">
                    <input type="hidden" name="tab" value="zones">
                    <select name="country" onchange="this.form.submit()" class="rounded-lg border-gray-300 text-sm">
                        @foreach ($countryList as $code => $name)
                            <option value="{{ $code }}" {{ $selectedCountry === $code ? 'selected' : '' }}>{{ $name }} ({{ $code }})</option>
                        @endforeach
                    </select>
                    <span class="text-sm text-gray-500">{{ $zones->count() }} zones</span>
                </form>

                {{-- Zone list --}}
                <div class="overflow-hidden rounded-lg border border-gray-200 mb-6">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Zone Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-24">Code</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-24">Status</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase w-32">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" x-data="{ editing: null }">
                            @forelse ($zones as $zone)
                                <tr class="hover:bg-gray-50">
                                    {{-- View mode --}}
                                    <template x-if="editing !== {{ $zone->id }}">
                                        <td class="px-4 py-2.5 text-sm text-gray-900" colspan="1">{{ $zone->name }}</td>
                                    </template>
                                    <template x-if="editing !== {{ $zone->id }}">
                                        <td class="px-4 py-2.5 text-xs text-gray-500 font-mono">{{ $zone->code ?? '—' }}</td>
                                    </template>
                                    <template x-if="editing !== {{ $zone->id }}">
                                        <td class="px-4 py-2.5">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $zone->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500' }}">
                                                {{ $zone->is_active ? 'Active' : 'Disabled' }}
                                            </span>
                                        </td>
                                    </template>
                                    <template x-if="editing !== {{ $zone->id }}">
                                        <td class="px-4 py-2.5 text-right text-sm">
                                            <button type="button" @click="editing = {{ $zone->id }}" class="text-indigo-600 hover:text-indigo-900 mr-2">Edit</button>
                                            <form method="POST" action="{{ route('admin.localization.destroy-zone', $zone) }}" class="inline" onsubmit="return confirm('Delete this zone?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                            </form>
                                        </td>
                                    </template>

                                    {{-- Edit mode --}}
                                    <template x-if="editing === {{ $zone->id }}">
                                        <td colspan="4" class="px-4 py-2.5">
                                            <form method="POST" action="{{ route('admin.localization.update-zone', $zone) }}" class="flex items-center gap-3">
                                                @csrf @method('PUT')
                                                <input type="text" name="name" value="{{ $zone->name }}" required class="rounded border-gray-300 text-sm flex-1">
                                                <input type="text" name="code" value="{{ $zone->code }}" class="rounded border-gray-300 text-sm w-20" placeholder="Code">
                                                <label class="flex items-center gap-1 text-sm">
                                                    <input type="hidden" name="is_active" value="0">
                                                    <input type="checkbox" name="is_active" value="1" {{ $zone->is_active ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                                                    Active
                                                </label>
                                                <button type="submit" class="px-3 py-1.5 bg-indigo-600 text-white text-xs rounded hover:bg-indigo-700">Save</button>
                                                <button type="button" @click="editing = null" class="px-3 py-1.5 bg-gray-200 text-gray-700 text-xs rounded hover:bg-gray-300">Cancel</button>
                                            </form>
                                        </td>
                                    </template>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-gray-400">No zones for this country.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Add zone --}}
                <div class="border-t border-gray-200 pt-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-3">Add Zone</h4>
                    <form method="POST" action="{{ route('admin.localization.store-zone') }}" class="flex items-end gap-3">
                        @csrf
                        <input type="hidden" name="country_code" value="{{ $selectedCountry }}">
                        <div class="flex-1">
                            <label class="block text-xs text-gray-600 mb-1">Name</label>
                            <input type="text" name="name" required class="block w-full rounded-md border-gray-300 text-sm" placeholder="Zone name">
                        </div>
                        <div class="w-24">
                            <label class="block text-xs text-gray-600 mb-1">Code</label>
                            <input type="text" name="code" class="block w-full rounded-md border-gray-300 text-sm" placeholder="e.g. NY">
                        </div>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Add</button>
                    </form>
                </div>
            </div>

            {{-- ==================== GEO ZONES ==================== --}}
            <div x-show="activeTab === 'geo-zones'" x-cloak class="p-6">
                <p class="text-sm text-gray-500 mb-4">Geographic zones are shared groupings used by shipping, payment, and other modules. Define a zone once, use it everywhere.</p>

                {{-- Geo Zone list --}}
                <div class="overflow-hidden rounded-lg border border-gray-200 mb-6">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entries</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-20">Order</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-24">Status</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase w-32">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($geoZones as $gz)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2.5 text-sm font-medium text-gray-900">{{ $gz->name }}</td>
                                    <td class="px-4 py-2.5 text-xs text-gray-500">{{ $gz->description ?? '—' }}</td>
                                    <td class="px-4 py-2.5 text-sm text-gray-500">{{ $gz->entries_count }}</td>
                                    <td class="px-4 py-2.5 text-sm text-gray-500">{{ $gz->sort_order }}</td>
                                    <td class="px-4 py-2.5">
                                        <form method="POST" action="{{ route('admin.localization.toggle-geo-zone', $gz) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $gz->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500' }}">
                                                {{ $gz->is_active ? 'Active' : 'Disabled' }}
                                            </button>
                                        </form>
                                    </td>
                                    <td class="px-4 py-2.5 text-right text-sm">
                                        <a href="{{ route('admin.localization.edit-geo-zone', $gz) }}" class="text-indigo-600 hover:text-indigo-900 mr-2">Edit</a>
                                        <form method="POST" action="{{ route('admin.localization.destroy-geo-zone', $gz) }}" class="inline" onsubmit="return confirm('Delete this zone? Shipping rates linked to it will lose their zone reference.')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-400">No geo zones yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Add Geo Zone --}}
                <div class="border-t border-gray-200 pt-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-3">Add Geo Zone</h4>
                    <form method="POST" action="{{ route('admin.localization.store-geo-zone') }}" class="flex items-end gap-3">
                        @csrf
                        <div class="flex-1">
                            <label class="block text-xs text-gray-600 mb-1">Name</label>
                            <input type="text" name="name" required class="block w-full rounded-md border-gray-300 text-sm" placeholder="e.g. Metro Manila">
                        </div>
                        <div class="flex-1">
                            <label class="block text-xs text-gray-600 mb-1">Description</label>
                            <input type="text" name="description" class="block w-full rounded-md border-gray-300 text-sm" placeholder="Optional description">
                        </div>
                        <div class="w-20">
                            <label class="block text-xs text-gray-600 mb-1">Order</label>
                            <input type="number" name="sort_order" value="0" min="0" class="block w-full rounded-md border-gray-300 text-sm">
                        </div>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Add</button>
                    </form>
                </div>
            </div>

            {{-- ==================== PH REFERENCE ==================== --}}
            <div x-show="activeTab === 'ph-reference'" x-cloak class="p-6">
                <p class="text-sm text-gray-500 mb-4">Philippine Standard Geographic Code (PSGC) data. This is read-only reference data used by address forms.</p>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-2 mb-4 text-sm text-gray-500">
                    <span class="bg-gray-50 rounded px-3 py-2 text-center">
                        <span class="block text-lg font-bold text-gray-800">17</span> Regions
                    </span>
                    <span class="bg-gray-50 rounded px-3 py-2 text-center">
                        <span class="block text-lg font-bold text-gray-800">82</span> Provinces
                    </span>
                    <span class="bg-gray-50 rounded px-3 py-2 text-center">
                        <span class="block text-lg font-bold text-gray-800">1,646</span> Cities
                    </span>
                    <span class="bg-gray-50 rounded px-3 py-2 text-center">
                        <span class="block text-lg font-bold text-gray-800">41,954</span> Barangays
                    </span>
                </div>

                <div class="space-y-2">
                    @foreach ($phRegions as $region)
                        <div x-data="{ open: false }" class="border border-gray-200 rounded-lg overflow-hidden">
                            <button type="button" @click="open = !open"
                                class="w-full flex items-center justify-between px-4 py-3 bg-gray-50 hover:bg-gray-100 transition-colors text-left">
                                <div>
                                    <span class="text-sm font-medium text-gray-900">{{ $region->name }}</span>
                                    <span class="text-xs text-gray-500 ml-2">({{ $region->provinces->count() }} provinces)</span>
                                </div>
                                <svg class="w-4 h-4 text-gray-400 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="open" x-transition class="px-4 py-3 border-t border-gray-200">
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                                    @foreach ($region->provinces as $province)
                                        <div class="text-sm text-gray-700 bg-gray-50 rounded px-3 py-1.5">
                                            {{ $province->name }}
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
