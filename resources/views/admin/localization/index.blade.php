<x-admin-layout>
    <x-slot name="header">Localization</x-slot>

    <div x-data="localizationPage()">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <nav class="flex border-b border-gray-200 overflow-x-auto">
                @php
                    $tabs = [['id' => 'countries', 'label' => 'Countries'], ['id' => 'zones', 'label' => 'Zones'], ['id' => 'geo-zones', 'label' => 'Geo Zones']];
                @endphp
                @foreach ($tabs as $t)
                    <button type="button" @click="activeTab = '{{ $t['id'] }}'"
                        :class="activeTab === '{{ $t['id'] }}' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap px-5 py-3 border-b-2 text-sm font-medium transition-colors">{{ $t['label'] }}</button>
                @endforeach
            </nav>

            {{-- ==================== COUNTRIES ==================== --}}
            <div x-show="activeTab === 'countries'" class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-sm text-gray-500">
                        <span x-text="enabledCount">{{ $countries->where('is_active', true)->count() }}</span>
                        of {{ $countries->count() }} countries enabled
                    </p>
                    <div class="relative">
                        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="text" x-model="countrySearch" placeholder="Search countries..."
                            class="w-64 pl-9 pr-3 py-1.5 rounded-lg border-gray-300 text-sm placeholder-gray-400 focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>

                <div class="overflow-hidden rounded-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Country</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-16">ISO2</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-16">ISO3</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-20">Phone</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-14">Sort</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-16">Zones</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-20">Enabled</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase w-24">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($countries as $country)
                                <tr class="hover:bg-gray-50 group"
                                    x-show="!countrySearch || '{{ strtolower(addslashes($country->name)) }}'.includes(countrySearch.toLowerCase()) || '{{ $country->iso2 }}'.toLowerCase().includes(countrySearch.toLowerCase())"
                                    x-data="{ editing: false, active: {{ $country->is_active ? 'true' : 'false' }}, toggling: false }">

                                    {{-- View mode --}}
                                    <template x-if="!editing">
                                        <td class="px-4 py-2.5 text-sm text-gray-900">{{ $country->name }}</td>
                                    </template>
                                    <template x-if="!editing">
                                        <td class="px-4 py-2.5 text-xs text-gray-500 font-mono">{{ $country->iso2 }}</td>
                                    </template>
                                    <template x-if="!editing">
                                        <td class="px-4 py-2.5 text-xs text-gray-500 font-mono">{{ $country->iso3 }}</td>
                                    </template>
                                    <template x-if="!editing">
                                        <td class="px-4 py-2.5 text-xs text-gray-500">{{ $country->phone_code ? '+' . $country->phone_code : '—' }}</td>
                                    </template>
                                    <template x-if="!editing">
                                        <td class="px-4 py-2.5 text-xs text-gray-400 text-center font-mono">{{ $country->sort_order }}</td>
                                    </template>
                                    <template x-if="!editing">
                                        <td class="px-4 py-2.5 text-sm text-gray-500">{{ $country->zones_count }}</td>
                                    </template>
                                    <template x-if="!editing">
                                        <td class="px-4 py-2.5 text-center">
                                            <button type="button"
                                                @click="toggleCountry({{ $country->id }}, $data)"
                                                :disabled="toggling"
                                                class="inline-flex items-center justify-center w-9 h-5 rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 disabled:opacity-50"
                                                :style="active ? 'background-color: #4f46e5' : 'background-color: #d1d5db'"
                                                role="switch" :aria-checked="active"
                                                :title="active ? 'Enabled — click to disable' : 'Disabled — click to enable'">
                                                <span class="inline-block w-3.5 h-3.5 rounded-full bg-white shadow transform transition-transform duration-200"
                                                      :style="active ? 'transform: translateX(8px)' : 'transform: translateX(-8px)'"></span>
                                            </button>
                                        </td>
                                    </template>
                                    <template x-if="!editing">
                                        <td class="px-4 py-2.5 text-right">
                                            <button type="button" @click="editing = true" class="text-xs text-indigo-600 hover:text-indigo-900 opacity-0 group-hover:opacity-100 transition-opacity">Edit</button>
                                        </td>
                                    </template>

                                    {{-- Edit mode --}}
                                    <template x-if="editing">
                                        <td colspan="8" class="px-4 py-2.5">
                                            <form method="POST" action="{{ route('admin.localization.update-country', $country) }}" class="flex items-center gap-2">
                                                @csrf @method('PUT')
                                                <input type="text" name="name" value="{{ $country->name }}" required class="rounded-md border-gray-300 text-sm flex-1" placeholder="Country name">
                                                <input type="text" name="iso2" value="{{ $country->iso2 }}" required maxlength="2" class="rounded-md border-gray-300 text-sm w-16 font-mono text-center uppercase" placeholder="US">
                                                <input type="text" name="iso3" value="{{ $country->iso3 }}" required maxlength="3" class="rounded-md border-gray-300 text-sm w-16 font-mono text-center uppercase" placeholder="USA">
                                                <div class="relative">
                                                    <span class="absolute left-2 top-1/2 -translate-y-1/2 text-xs text-gray-400">+</span>
                                                    <input type="text" name="phone_code" value="{{ $country->phone_code }}" class="rounded-md border-gray-300 text-sm w-20 pl-5" placeholder="1">
                                                </div>
                                                <input type="number" name="sort_order" value="{{ $country->sort_order }}" class="rounded-md border-gray-300 text-sm w-16 text-center" title="Sort order">
                                                <button type="submit" class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-md hover:bg-indigo-700">Save</button>
                                                <button type="button" @click="editing = false" class="px-3 py-1.5 bg-gray-100 text-gray-600 text-xs font-medium rounded-md hover:bg-gray-200">Cancel</button>
                                            </form>
                                        </td>
                                    </template>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ==================== ZONES ==================== --}}
            <div x-show="activeTab === 'zones'" x-cloak class="p-6">
                <form method="GET" class="flex items-center gap-3 mb-4">
                    <input type="hidden" name="tab" value="zones">
                    <select name="country" onchange="this.form.submit()" class="rounded-lg border-gray-300 text-sm">
                        @foreach ($countryList as $code => $name)
                            <option value="{{ $code }}" {{ $selectedCountry === $code ? 'selected' : '' }}>{{ $name }} ({{ $code }})</option>
                        @endforeach
                    </select>
                    <span class="text-sm text-gray-500">{{ $zones->count() }} zones</span>
                </form>

                <div class="overflow-hidden rounded-lg border border-gray-200 mb-6">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Zone Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-24">Code</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-20">Enabled</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase w-28">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($zones as $zone)
                                <tr class="hover:bg-gray-50 group"
                                    x-data="{ editing: false, active: {{ $zone->is_active ? 'true' : 'false' }}, toggling: false }">
                                    {{-- View mode --}}
                                    <template x-if="!editing">
                                        <td class="px-4 py-2.5 text-sm text-gray-900">{{ $zone->name }}</td>
                                    </template>
                                    <template x-if="!editing">
                                        <td class="px-4 py-2.5 text-xs text-gray-500 font-mono">{{ $zone->code ?? '—' }}</td>
                                    </template>
                                    <template x-if="!editing">
                                        <td class="px-4 py-2.5 text-center">
                                            <button type="button"
                                                @click="toggleZone({{ $zone->id }}, $data)"
                                                :disabled="toggling"
                                                class="inline-flex items-center justify-center w-9 h-5 rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 disabled:opacity-50"
                                                :style="active ? 'background-color: #4f46e5' : 'background-color: #d1d5db'"
                                                role="switch" :aria-checked="active"
                                                :title="active ? 'Enabled — click to disable' : 'Disabled — click to enable'">
                                                <span class="inline-block w-3.5 h-3.5 rounded-full bg-white shadow transform transition-transform duration-200"
                                                      :style="active ? 'transform: translateX(8px)' : 'transform: translateX(-8px)'"></span>
                                            </button>
                                        </td>
                                    </template>
                                    <template x-if="!editing">
                                        <td class="px-4 py-2.5 text-right text-xs">
                                            <button type="button" @click="editing = true" class="text-indigo-600 hover:text-indigo-900 opacity-0 group-hover:opacity-100 transition-opacity mr-2">Edit</button>
                                            <form method="POST" action="{{ route('admin.localization.destroy-zone', $zone) }}" class="inline" onsubmit="return confirm('Delete this zone?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-red-500 hover:text-red-700 opacity-0 group-hover:opacity-100 transition-opacity">Delete</button>
                                            </form>
                                        </td>
                                    </template>

                                    {{-- Edit mode --}}
                                    <template x-if="editing">
                                        <td colspan="4" class="px-4 py-2.5">
                                            <form method="POST" action="{{ route('admin.localization.update-zone', $zone) }}" class="flex items-center gap-2">
                                                @csrf @method('PUT')
                                                <input type="text" name="name" value="{{ $zone->name }}" required class="rounded-md border-gray-300 text-sm flex-1">
                                                <input type="text" name="code" value="{{ $zone->code }}" class="rounded-md border-gray-300 text-sm w-20 font-mono" placeholder="Code">
                                                <input type="hidden" name="is_active" :value="active ? '1' : '0'">
                                                <button type="submit" class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-md hover:bg-indigo-700">Save</button>
                                                <button type="button" @click="editing = false" class="px-3 py-1.5 bg-gray-100 text-gray-600 text-xs font-medium rounded-md hover:bg-gray-200">Cancel</button>
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
                        <input type="hidden" name="country_id" value="{{ $selectedCountry }}">
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

                <div class="overflow-hidden rounded-lg border border-gray-200 mb-6">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-16">Entries</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-16">Order</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-20">Enabled</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase w-28">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($geoZones as $gz)
                                <tr class="hover:bg-gray-50 group"
                                    x-data="{ active: {{ $gz->is_active ? 'true' : 'false' }}, toggling: false }">
                                    <td class="px-4 py-2.5 text-sm font-medium text-gray-900">{{ $gz->name }}</td>
                                    <td class="px-4 py-2.5 text-xs text-gray-500">{{ $gz->description ?? '—' }}</td>
                                    <td class="px-4 py-2.5 text-sm text-gray-500">{{ $gz->entries_count }}</td>
                                    <td class="px-4 py-2.5 text-sm text-gray-500">{{ $gz->sort_order }}</td>
                                    <td class="px-4 py-2.5 text-center">
                                        <button type="button"
                                            @click="toggleGeoZone({{ $gz->id }}, $data)"
                                            :disabled="toggling"
                                            class="inline-flex items-center justify-center w-9 h-5 rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 disabled:opacity-50"
                                            :style="active ? 'background-color: #4f46e5' : 'background-color: #d1d5db'"
                                            role="switch" :aria-checked="active"
                                            :title="active ? 'Enabled — click to disable' : 'Disabled — click to enable'">
                                            <span class="inline-block w-3.5 h-3.5 rounded-full bg-white shadow transform transition-transform duration-200"
                                                  :style="active ? 'transform: translateX(8px)' : 'transform: translateX(-8px)'"></span>
                                        </button>
                                    </td>
                                    <td class="px-4 py-2.5 text-right text-xs">
                                        <a href="{{ route('admin.localization.edit-geo-zone', $gz) }}" class="text-indigo-600 hover:text-indigo-900 mr-2">Edit</a>
                                        <form method="POST" action="{{ route('admin.localization.destroy-geo-zone', $gz) }}" class="inline" onsubmit="return confirm('Delete this zone? Shipping rates linked to it will lose their zone reference.')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-red-500 hover:text-red-700 opacity-0 group-hover:opacity-100 transition-opacity">Delete</button>
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

                <div class="border-t border-gray-200 pt-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-3">Add Geo Zone</h4>
                    <form method="POST" action="{{ route('admin.localization.store-geo-zone') }}" class="flex items-end gap-3">
                        @csrf
                        <div class="flex-1">
                            <label class="block text-xs text-gray-600 mb-1">Name</label>
                            <input type="text" name="name" required class="block w-full rounded-md border-gray-300 text-sm" placeholder="e.g. Domestic">
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

        </div>

        {{-- Toast --}}
        <div x-show="toast" x-transition.opacity class="fixed bottom-6 right-6 z-50 px-4 py-3 rounded-lg shadow-lg text-sm text-white bg-emerald-600">
            <span x-text="toast"></span>
        </div>
    </div>

    @push('scripts')
    <script>
    function localizationPage() {
        return {
            activeTab: '{{ $tab }}',
            countrySearch: '',
            toast: '',
            enabledCount: {{ $countries->where('is_active', true)->count() }},

            async toggleCountry(id, row) {
                row.toggling = true;
                try {
                    const res = await fetch(`{{ route('admin.localization.toggle-country', ['country' => '__ID__']) }}`.replace('__ID__', id), {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    });
                    const data = await res.json();
                    row.active = data.is_active;
                    this.enabledCount += data.is_active ? 1 : -1;
                    this.showToast(data.message);
                } catch (e) {
                    this.showToast('Failed to toggle.');
                }
                row.toggling = false;
            },

            async toggleZone(id, row) {
                row.toggling = true;
                try {
                    const res = await fetch(`{{ route('admin.localization.toggle-zone', ['zone' => '__ID__']) }}`.replace('__ID__', id), {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    });
                    const data = await res.json();
                    row.active = data.is_active;
                    this.showToast(data.message);
                } catch (e) {
                    this.showToast('Failed to toggle.');
                }
                row.toggling = false;
            },

            async toggleGeoZone(id, row) {
                row.toggling = true;
                try {
                    const res = await fetch(`{{ route('admin.localization.toggle-geo-zone', ['geoZone' => '__ID__']) }}`.replace('__ID__', id), {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    });
                    const data = await res.json();
                    row.active = data.is_active;
                    this.showToast(data.message);
                } catch (e) {
                    this.showToast('Failed to toggle.');
                }
                row.toggling = false;
            },

            showToast(msg) {
                this.toast = msg;
                setTimeout(() => { this.toast = ''; }, 2500);
            },
        };
    }
    </script>
    @endpush
</x-admin-layout>
