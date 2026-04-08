<x-admin-layout>
    <x-slot name="header">Coupons</x-slot>
    <x-slot name="headerActions">
        <a href="{{ route('admin.coupons.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Coupon
        </a>
    </x-slot>

    {{-- Filters --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
        <form method="GET" action="{{ route('admin.coupons.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search code or name..."
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <select name="type" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Types</option>
                    <option value="percent" {{ request('type') === 'percent' ? 'selected' : '' }}>Percent</option>
                    <option value="fixed" {{ request('type') === 'fixed' ? 'selected' : '' }}>Fixed</option>
                    <option value="free_shipping" {{ request('type') === 'free_shipping' ? 'selected' : '' }}>Free Shipping</option>
                </select>
            </div>
            <div>
                <select name="status" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Statuses</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    <option value="expired" {{ request('status') === 'expired' ? 'selected' : '' }}>Expired</option>
                </select>
            </div>
            <div class="flex space-x-2">
                <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm rounded-lg hover:bg-gray-700 transition-colors">Filter</button>
                <a href="{{ route('admin.coupons.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300 transition-colors">Clear</a>
            </div>
        </form>
    </div>

    {{-- Coupons Table --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Discount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usage</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dates</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse ($coupons as $coupon)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <span class="font-mono text-sm font-medium text-gray-900">{{ $coupon->code }}</span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700">{{ $coupon->name }}</td>
                        <td class="px-6 py-4">
                            @if ($coupon->type === 'percent')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Percent</span>
                            @elseif ($coupon->type === 'fixed')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Fixed</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-teal-100 text-teal-800">Free Shipping</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700">
                            @if ($coupon->type === 'percent')
                                {{ rtrim(rtrim(number_format($coupon->discount_value, 2), '0'), '.') }}%
                            @elseif ($coupon->type === 'fixed')
                                {{ number_format($coupon->discount_value, 2) }}
                            @else
                                &mdash;
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700">
                            {{ $coupon->times_used }}{{ $coupon->usage_limit ? ' / ' . $coupon->usage_limit : '' }}
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if ($coupon->is_expired)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Expired</span>
                            @elseif ($coupon->is_active)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Inactive</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            @if ($coupon->date_start || $coupon->date_end)
                                {{ $coupon->date_start?->format('M d, Y') ?? 'Any' }}
                                &rarr;
                                {{ $coupon->date_end?->format('M d, Y') ?? 'No end' }}
                            @else
                                <span class="text-gray-400">No dates</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right text-sm">
                            <a href="{{ route('admin.coupons.edit', $coupon) }}" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</a>
                            <form method="POST" action="{{ route('admin.coupons.destroy', $coupon) }}" class="inline" onsubmit="return confirm('Delete this coupon? This cannot be undone.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                            No coupons found. <a href="{{ route('admin.coupons.create') }}" class="text-indigo-600 hover:underline">Create your first coupon</a>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($coupons->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $coupons->links() }}
            </div>
        @endif
    </div>
</x-admin-layout>
