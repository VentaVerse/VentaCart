<x-admin-layout>
    <x-slot name="header">Edit Geo Zone: {{ $geoZone->name }}</x-slot>
    <x-slot name="headerActions">
        <a href="{{ route('admin.localization.index', ['tab' => 'geo-zones']) }}" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors">
            Back to Localization
        </a>
    </x-slot>

    <div class="max-w-3xl">
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
                        <span class="text-sm text-gray-700">Active</span>
                    </label>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Update</button>
                </div>
            </form>
        </div>

        {{-- Region Entries --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">Region Assignments</h3>
            <p class="text-xs text-gray-500 mb-4">Select which PH regions belong to this geo zone. These are used by shipping and other modules to determine the delivery zone.</p>

            <form method="POST" action="{{ route('admin.localization.sync-geo-zone-entries', $geoZone) }}">
                @csrf
                <div class="space-y-2 mb-4">
                    @foreach ($phRegions as $region)
                        <label class="flex items-center gap-2 text-sm cursor-pointer hover:bg-gray-50 px-3 py-1.5 rounded">
                            <input type="checkbox" name="ph_region_ids[]" value="{{ $region->id }}"
                                {{ in_array($region->id, $assignedRegionIds) ? 'checked' : '' }}
                                class="rounded border-gray-300 text-indigo-600">
                            <span>{{ $region->name }}</span>
                            <span class="text-xs text-gray-400">({{ $region->provinces->count() }} provinces)</span>
                        </label>
                    @endforeach
                </div>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save Assignments</button>
            </form>
        </div>
    </div>
</x-admin-layout>
