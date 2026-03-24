<x-admin-layout>
    <x-slot name="header">Shipping Methods</x-slot>
    <x-slot name="headerActions">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.shipping-settings.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors">
                Back to Shipping
            </a>
            <a href="{{ route('admin.shipping-methods.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Add Method
            </a>
        </div>
    </x-slot>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Geo Zone</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer Group</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cost / Rates</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($methods as $method)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3">
                            <div class="text-sm font-medium text-gray-900">{{ $method->name }}</div>
                            <div class="text-xs text-gray-400">sort: {{ $method->sort_order }}</div>
                        </td>
                        <td class="px-6 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $method->type === 'flat_rate' ? 'bg-blue-50 text-blue-700' : 'bg-amber-50 text-amber-700' }}">
                                {{ $method->type === 'flat_rate' ? 'Flat Rate' : 'Weight-Based' }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-sm text-gray-500">{{ $method->geoZone?->name ?? 'All zones' }}</td>
                        <td class="px-6 py-3 text-sm text-gray-500">{{ $method->customerGroup?->name ?? 'All groups' }}</td>
                        <td class="px-6 py-3 text-sm text-gray-700">
                            @if ($method->type === 'flat_rate')
                                ₱{{ number_format($method->cost, 2) }}
                            @else
                                {{ $method->rates_count }} {{ Str::plural('tier', $method->rates_count) }}
                            @endif
                        </td>
                        <td class="px-6 py-3 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $method->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500' }}">
                                {{ $method->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-right text-sm">
                            <a href="{{ route('admin.shipping-methods.edit', $method) }}" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</a>
                            <form method="POST" action="{{ route('admin.shipping-methods.destroy', $method) }}" class="inline" onsubmit="return confirm('Delete this shipping method?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                            No shipping methods yet. <a href="{{ route('admin.shipping-methods.create') }}" class="text-indigo-600 hover:underline">Create your first one</a>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
