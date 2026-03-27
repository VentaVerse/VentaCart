<x-admin-layout>
    <x-slot name="header">Abandoned Carts</x-slot>

    {{-- Tab Navigation --}}
    <div class="mb-6 border-b border-gray-200">
        <nav class="-mb-px flex space-x-8">
            <a href="{{ route('admin.abandoned-carts.index') }}" class="py-3 px-1 border-b-2 text-sm font-medium border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">Carts</a>
            <a href="{{ route('admin.abandoned-carts.rules') }}" class="py-3 px-1 border-b-2 text-sm font-medium border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">Rules</a>
            <a href="{{ route('admin.abandoned-carts.notifications') }}" class="py-3 px-1 border-b-2 text-sm font-medium border-indigo-500 text-indigo-600">Notifications</a>
            <a href="{{ route('admin.abandoned-carts.recovered') }}" class="py-3 px-1 border-b-2 text-sm font-medium border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">Recovered</a>
        </nav>
    </div>

    {{-- Filter --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
        <form method="GET" action="{{ route('admin.abandoned-carts.notifications') }}" class="flex items-end gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Template</label>
                <select name="template" class="rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Templates</option>
                    @foreach ($templates as $template)
                        <option value="{{ $template->id }}" {{ request('template') == $template->id ? 'selected' : '' }}>{{ $template->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm rounded-lg hover:bg-gray-700 transition-colors">Filter</button>
            <a href="{{ route('admin.abandoned-carts.notifications') }}" class="px-4 py-2 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300 transition-colors">Clear</a>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cart</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Template</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sends</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Sent</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse ($notifications as $notification)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            @if ($notification->customer)
                                <div class="text-sm font-medium text-gray-900">{{ $notification->customer->full_name }}</div>
                                <div class="text-xs text-gray-400">{{ $notification->customer->email }}</div>
                            @else
                                <span class="text-sm text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if ($notification->cart)
                                <a href="{{ route('admin.abandoned-carts.show', $notification->cart_id) }}" class="text-sm text-indigo-600 hover:text-indigo-900">
                                    #{{ $notification->cart_id }} ({{ $notification->cart->items->count() }} items)
                                </a>
                            @else
                                <span class="text-sm text-gray-400">Deleted</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $notification->emailTemplate->name ?? '—' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900">{{ $notification->send_count }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $notification->last_sent_at?->diffForHumans() ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">No notifications sent yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($notifications->hasPages())
            <div class="px-6 py-3 border-t border-gray-200">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</x-admin-layout>
