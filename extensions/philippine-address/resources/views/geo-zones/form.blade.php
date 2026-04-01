@php
    // Map PH extension provinces to core zone IDs
    $phCountry = \App\Models\Country::where('iso2', 'PH')->first();
    $coreZones = $phCountry
        ? \App\Models\Zone::where('country_code', 'PH')->pluck('id', 'name')
        : collect();
@endphp

<x-admin-layout>
    <x-slot name="header">{{ $geoZone ? 'Edit: ' . $geoZone->name : 'New PH Geo Zone' }}</x-slot>
    <x-slot name="headerActions">
        <a href="{{ route('admin.ph-address.geo-zones.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300">
            Back
        </a>
    </x-slot>

    <div class="max-w-4xl">
        <form method="POST" action="{{ $geoZone ? route('admin.ph-address.geo-zones.update', $geoZone) : route('admin.ph-address.geo-zones.store') }}">
            @csrf
            @if ($geoZone) @method('PUT') @endif

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input type="text" name="name" id="name" value="{{ old('name', $geoZone?->name) }}" required
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="e.g. Metro Manila, Luzon, Visayas">
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <input type="text" name="description" id="description" value="{{ old('description', $geoZone?->description) }}"
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $geoZone?->is_active ?? true) ? 'checked' : '' }}
                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    Active
                </label>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6" x-data="{
                selected: {{ json_encode(old('zone_ids', $selectedZoneIds)) }},
                toggleAll(zoneIds) {
                    const allSelected = zoneIds.every(id => this.selected.includes(id));
                    if (allSelected) {
                        this.selected = this.selected.filter(id => !zoneIds.includes(id));
                    } else {
                        zoneIds.forEach(id => { if (!this.selected.includes(id)) this.selected.push(id); });
                    }
                },
                isAllSelected(zoneIds) {
                    return zoneIds.length > 0 && zoneIds.every(id => this.selected.includes(id));
                }
            }">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Provinces</h3>
                        <p class="text-xs text-gray-400 mt-1">Select which provinces belong to this geo zone.</p>
                    </div>
                    <span class="text-xs text-gray-500" x-text="selected.length + ' selected'"></span>
                </div>

                @error('zone_ids') <p class="mb-4 text-sm text-red-600">{{ $message }}</p> @enderror

                <div class="space-y-4">
                    @foreach ($regions as $region)
                        @php
                            $regionZoneIds = $region->provinces->map(function ($p) use ($coreZones) {
                                return $coreZones->get($p->name);
                            })->filter()->values()->toArray();
                        @endphp
                        @if (!empty($regionZoneIds))
                            <div class="border border-gray-200 rounded-lg overflow-hidden">
                                <div class="bg-gray-50 px-4 py-2.5 flex items-center justify-between cursor-pointer"
                                     @click="toggleAll({{ json_encode($regionZoneIds) }})">
                                    <span class="text-sm font-medium text-gray-700">{{ $region->name }}</span>
                                    <span class="text-xs text-gray-400" x-text="'(' + {{ json_encode($regionZoneIds) }}.filter(id => selected.includes(id)).length + '/{{ count($regionZoneIds) }})'"></span>
                                </div>
                                <div class="px-4 py-3 grid grid-cols-2 md:grid-cols-3 gap-1.5">
                                    @foreach ($region->provinces as $province)
                                        @php $zoneId = $coreZones->get($province->name); @endphp
                                        @if ($zoneId)
                                            <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer hover:text-gray-900 py-0.5">
                                                <input type="checkbox" value="{{ $zoneId }}"
                                                    :checked="selected.includes({{ $zoneId }})"
                                                    @change="$event.target.checked ? selected.push({{ $zoneId }}) : selected = selected.filter(id => id !== {{ $zoneId }})"
                                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                {{ $province->name }}
                                            </label>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>

                <template x-for="id in selected" :key="id">
                    <input type="hidden" name="zone_ids[]" :value="id">
                </template>
            </div>

            <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm rounded-lg hover:bg-gray-700 transition-colors">
                {{ $geoZone ? 'Update Geo Zone' : 'Create Geo Zone' }}
            </button>
        </form>
    </div>
</x-admin-layout>
