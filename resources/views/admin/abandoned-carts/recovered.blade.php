<x-admin-layout>
    <x-slot name="header">Abandoned Carts</x-slot>

    {{-- Tab Navigation --}}
    <div class="mb-6 border-b border-gray-200">
        <nav class="-mb-px flex space-x-8">
            <a href="{{ route('admin.abandoned-carts.index') }}" class="py-3 px-1 border-b-2 text-sm font-medium border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">Carts</a>
            <a href="{{ route('admin.abandoned-carts.rules') }}" class="py-3 px-1 border-b-2 text-sm font-medium border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">Rules</a>
            <a href="{{ route('admin.abandoned-carts.notifications') }}" class="py-3 px-1 border-b-2 text-sm font-medium border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">Notifications</a>
            <a href="{{ route('admin.abandoned-carts.recovered') }}" class="py-3 px-1 border-b-2 text-sm font-medium border-indigo-500 text-indigo-600">Recovered</a>
        </nav>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cart Items</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notified</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recovery Time</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse ($recoveredCarts as $recovered)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900">{{ $recovered['customer']->full_name }}</div>
                            <div class="text-xs text-gray-400">{{ $recovered['customer']->email }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ Str::limit($recovered['cart_items'], 80) }}</td>
                        <td class="px-6 py-4">
                            <div class="text-sm text-gray-900">{{ $recovered['send_count'] }}x</div>
                            <div class="text-xs text-gray-400">Last: {{ $recovered['last_sent_at']->diffForHumans() }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <a href="{{ route('admin.orders.show', $recovered['order']) }}" class="text-sm text-indigo-600 hover:text-indigo-900">
                                #{{ $recovered['order']->id }}
                            </a>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $recovered['recovery_time'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">No recovered carts yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
