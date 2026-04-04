<x-admin-layout>
    <x-slot name="header">Shipping Rates by Zone</x-slot>
    <x-slot name="headerActions">
        <a href="{{ route('admin.localization.index', ['tab' => 'geo-zones']) }}" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors">
            Manage Geo Zones
        </a>
    </x-slot>

    <p class="text-sm text-gray-500 mb-4">Configure weight-based shipping rates for each geographic zone. Zones are managed in <a href="{{ route('admin.localization.index', ['tab' => 'geo-zones']) }}" class="text-indigo-600 hover:underline">Settings > Localization</a>.</p>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Zone</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Regions</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rates</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse ($zones as $zone)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900">{{ $zone->name }}</div>
                            @if ($zone->description)
                                <div class="text-xs text-gray-400">{{ $zone->description }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $zone->entries->count() }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $zone->rates_count }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $zone->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                {{ $zone->is_active ? 'Active' : 'Disabled' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right text-sm">
                            <a href="{{ route('admin.shipping-zones.edit', $zone) }}" class="text-indigo-600 hover:text-indigo-900">Edit Rates</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                            No geo zones found. <a href="{{ route('admin.localization.index', ['tab' => 'geo-zones']) }}" class="text-indigo-600 hover:underline">Create zones in Localization</a> first.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
