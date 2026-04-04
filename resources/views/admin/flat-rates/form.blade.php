<x-admin-layout>
    <x-slot name="header">{{ $rate ? 'Edit Flat Rate' : 'New Flat Rate' }}</x-slot>
    <x-slot name="headerActions">
        <a href="{{ route('admin.shipping-settings.index', ['tab' => 'flat-rate']) }}" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors">Back to Shipping</a>
    </x-slot>

    <form method="POST" action="{{ $rate ? route('admin.flat-rates.update', $rate) : route('admin.flat-rates.store') }}" class="max-w-xl">
        @csrf
        @if ($rate) @method('PUT') @endif

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-5">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                <input type="text" name="name" id="name" required value="{{ old('name', $rate?->name) }}"
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                    placeholder="e.g. Pickup from Warehouse, Metro Manila Flat">
                <p class="text-xs text-gray-400 mt-1">This name appears in checkout exactly as you type it</p>
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="cost" class="block text-sm font-medium text-gray-700 mb-1">Cost (₱)</label>
                <input type="number" name="cost" id="cost" step="0.01" min="0" required value="{{ old('cost', $rate?->cost ?? '0.00') }}"
                    class="w-40 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <p class="text-xs text-gray-400 mt-1">Set to 0 for free pickup or promotional methods</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="geo_zone_id" class="block text-sm font-medium text-gray-700 mb-1">Geo Zone</label>
                    <select name="geo_zone_id" id="geo_zone_id" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All zones</option>
                        @foreach ($geoZones as $zone)
                            <option value="{{ $zone->id }}" {{ old('geo_zone_id', $rate?->geo_zone_id) == $zone->id ? 'selected' : '' }}>{{ $zone->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="customer_group_id" class="block text-sm font-medium text-gray-700 mb-1">Customer Group</label>
                    <select name="customer_group_id" id="customer_group_id" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All customers</option>
                        @foreach ($customerGroups as $group)
                            <option value="{{ $group->id }}" {{ old('customer_group_id', $rate?->customer_group_id) == $group->id ? 'selected' : '' }}>{{ $group->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex items-center gap-6">
                <div>
                    <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                    <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order', $rate?->sort_order ?? 0) }}"
                        class="w-24 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div class="pt-5">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $rate?->is_active ?? true) ? 'checked' : '' }}
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                        <span class="text-sm text-gray-700">Active</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3 mt-6">
            <a href="{{ route('admin.shipping-settings.index', ['tab' => 'flat-rate']) }}" class="px-6 py-2.5 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300">Cancel</a>
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                {{ $rate ? 'Update' : 'Create' }}
            </button>
        </div>
    </form>
</x-admin-layout>
