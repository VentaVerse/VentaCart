<x-admin-layout>
    <x-slot name="header">Edit Geo Zone: {{ $geoZone->name }}</x-slot>
    <x-slot name="headerActions">
        <a href="{{ route('admin.localization.index', ['tab' => 'geo-zones']) }}" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors">
            Back to Localization
        </a>
    </x-slot>

    <div class="max-w-4xl" x-data="geoZoneEditor()">
        {{-- General --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">General</h3>
            <form method="POST" action="{{ route('admin.localization.update-geo-zone', $geoZone) }}" class="space-y-4">
                @csrf @method('PUT')
                <div class="grid grid-cols-3 gap-4">
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input type="text" name="name" value="{{ $geoZone->name }}" required class="w-full rounded-lg border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                        <input type="number" name="sort_order" value="{{ $geoZone->sort_order }}" min="0" class="w-full rounded-lg border-gray-300 text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <input type="text" name="description" value="{{ $geoZone->description }}" class="w-full rounded-lg border-gray-300 text-sm">
                </div>
                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-2">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" {{ $geoZone->is_active ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                        <span class="text-sm text-gray-700">Enabled</span>
                    </label>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Update</button>
                </div>
            </form>
        </div>

        {{-- Entries --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Zone Entries</h3>
                    <p class="text-xs text-gray-500 mt-1">Define which geographic areas belong to this zone. Pick a country, then optionally narrow to a specific state/zone.</p>
                </div>
                <button type="button" @click="addEntry()"
                    class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700">
                    + Add Entry
                </button>
            </div>

            <form method="POST" action="{{ route('admin.localization.sync-geo-zone-entries', $geoZone) }}">
                @csrf

                {{-- Entries table --}}
                <div class="overflow-hidden rounded-lg border border-gray-200 mb-4" x-show="entries.length > 0">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Country</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Zone / State</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase w-16"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template x-for="(entry, idx) in entries" :key="idx">
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2.5">
                                        <input type="hidden" :name="'entries[' + idx + '][country_id]'" :value="entry.country_id || ''">
                                        <input type="hidden" :name="'entries[' + idx + '][zone_id]'" :value="entry.zone_id || ''">

                                        <select @change="onCountryChange(idx, $event.target.value)"
                                            class="w-full rounded-md border-gray-300 text-sm">
                                            <option value="">Select country...</option>
                                            @foreach ($countries as $c)
                                                <option value="{{ $c->id }}" :selected="entry.country_id == {{ $c->id }}">{{ $c->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <select @change="entry.zone_id = $event.target.value || null"
                                            :disabled="!entry.zones || entry.zones.length === 0"
                                            class="w-full rounded-md border-gray-300 text-sm disabled:bg-gray-50 disabled:text-gray-400">
                                            <option value="">All zones</option>
                                            <template x-for="z in (entry.zones || [])" :key="z.id">
                                                <option :value="z.id" :selected="entry.zone_id == z.id" x-text="z.name + (z.code ? ' (' + z.code + ')' : '')"></option>
                                            </template>
                                        </select>
                                    </td>
                                    <td class="px-4 py-2.5 text-right">
                                        <button type="button" @click="removeEntry(idx)"
                                            class="text-red-400 hover:text-red-600 transition-colors" title="Remove">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                {{-- Empty state --}}
                <div x-show="entries.length === 0" class="text-center py-8 border-2 border-dashed border-gray-200 rounded-lg mb-4">
                    <svg class="w-10 h-10 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="text-sm text-gray-500">No entries yet</p>
                    <p class="text-xs text-gray-400 mt-1">Click "Add Entry" to define which areas belong to this zone</p>
                </div>

                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700"
                    x-show="entries.length > 0">
                    Save Entries
                </button>
            </form>
        </div>

        {{-- Toast --}}
        <div x-show="toast" x-transition.opacity class="fixed bottom-6 right-6 z-50 px-4 py-3 rounded-lg shadow-lg text-sm text-white bg-emerald-600">
            <span x-text="toast"></span>
        </div>
    </div>

    @push('scripts')
    <script>
    function geoZoneEditor() {
        return {
            entries: @json($entries),
            toast: '',

            init() {
                // Load zones for entries that already have a country
                this.entries.forEach(function(entry, idx) {
                    if (entry.country_id) {
                        this.loadZones(idx, entry.country_id, entry.zone_id);
                    }
                }.bind(this));
            },

            addEntry() {
                this.entries.push({
                    country_id: null,
                    zone_id: null,
                    zones: [],
                    label: '',
                });
            },

            removeEntry(idx) {
                this.entries.splice(idx, 1);
            },

            async onCountryChange(idx, countryId) {
                this.entries[idx].country_id = countryId || null;
                this.entries[idx].zone_id = null;
                this.entries[idx].zones = [];

                if (!countryId) return;

                await this.loadZones(idx, countryId, null);
            },

            async loadZones(idx, countryId, preselectedZoneId) {
                try {
                    if (!countryId) return;

                    const res = await fetch(`{{ route('admin.address.zones') }}?country_id=${countryId}`);
                    const zones = await res.json();
                    this.entries[idx].zones = zones;

                    // Restore preselected zone
                    if (preselectedZoneId) {
                        this.$nextTick(() => {
                            this.entries[idx].zone_id = preselectedZoneId;
                        });
                    }
                } catch (e) {
                    // Silently fail — zone dropdown stays empty
                }
            },

            showToast(msg) {
                this.toast = msg;
                setTimeout(() => { this.toast = ''; }, 3000);
            },
        };
    }
    </script>
    @endpush
</x-admin-layout>
