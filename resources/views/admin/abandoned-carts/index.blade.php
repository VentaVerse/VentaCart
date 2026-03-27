<x-admin-layout>
    <x-slot name="header">Abandoned Carts</x-slot>

    {{-- Tab Navigation --}}
    <div class="mb-6 border-b border-gray-200">
        <nav class="-mb-px flex space-x-8">
            <a href="{{ route('admin.abandoned-carts.index') }}"
               class="py-3 px-1 border-b-2 text-sm font-medium {{ request()->routeIs('admin.abandoned-carts.index') ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                Carts
            </a>
            <a href="{{ route('admin.abandoned-carts.rules') }}"
               class="py-3 px-1 border-b-2 text-sm font-medium {{ request()->routeIs('admin.abandoned-carts.rules*') ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                Rules
            </a>
            <a href="{{ route('admin.abandoned-carts.notifications') }}"
               class="py-3 px-1 border-b-2 text-sm font-medium {{ request()->routeIs('admin.abandoned-carts.notifications') ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                Notifications
            </a>
            <a href="{{ route('admin.abandoned-carts.recovered') }}"
               class="py-3 px-1 border-b-2 text-sm font-medium {{ request()->routeIs('admin.abandoned-carts.recovered') ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                Recovered
            </a>
        </nav>
    </div>

    {{-- Summary --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <div class="text-sm text-gray-500">Total Abandoned</div>
            <div class="text-2xl font-semibold text-gray-900">{{ $totalAbandoned }}</div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <div class="text-sm text-gray-500">Total Value</div>
            <div class="text-2xl font-semibold text-gray-900">{{ number_format($totalValue, 2) }}</div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
        <form method="GET" action="{{ route('admin.abandoned-carts.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search customer name, email..."
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <select name="type" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Types</option>
                    <option value="registered" {{ request('type') === 'registered' ? 'selected' : '' }}>Registered</option>
                    <option value="guest" {{ request('type') === 'guest' ? 'selected' : '' }}>Guest</option>
                </select>
            </div>
            <div class="flex space-x-2">
                <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm rounded-lg hover:bg-gray-700 transition-colors">Filter</button>
                <a href="{{ route('admin.abandoned-carts.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300 transition-colors">Clear</a>
            </div>
        </form>
    </div>

    {{-- Table --}}
    <form id="bulk-form" method="POST" action="{{ route('admin.abandoned-carts.bulk-delete') }}">
        @csrf
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-3 bg-gray-50 border-b border-gray-200 flex items-center gap-3">
                <button type="submit" onclick="return confirm('Delete selected abandoned carts?')"
                    class="px-3 py-1.5 bg-red-600 text-white text-xs font-medium rounded-lg hover:bg-red-700 transition-colors hidden" id="bulk-delete-btn">
                    Delete Selected
                </button>
            </div>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left">
                            <input type="checkbox" id="select-all" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Abandoned</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse ($carts as $cart)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <input type="checkbox" name="ids[]" value="{{ $cart->id }}" class="cart-checkbox rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            </td>
                            <td class="px-6 py-4">
                                @if ($cart->customer)
                                    <div class="text-sm font-medium text-gray-900">{{ $cart->customer->first_name }} {{ $cart->customer->last_name }}</div>
                                    <div class="text-xs text-gray-400">{{ $cart->customer->email }}</div>
                                @else
                                    <span class="text-sm text-gray-400">Guest</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ $cart->items->count() }} item(s)</td>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ number_format($cart->total, 2) }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ $cart->updated_at->diffForHumans() }}</td>
                            <td class="px-6 py-4 text-right text-sm space-x-3">
                                <a href="{{ route('admin.abandoned-carts.show', $cart) }}" class="text-indigo-600 hover:text-indigo-900">View</a>
                                <form method="POST" action="{{ route('admin.abandoned-carts.destroy', $cart) }}" class="inline" onsubmit="return confirm('Delete this cart?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">No abandoned carts found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if ($carts->hasPages())
                <div class="px-6 py-3 border-t border-gray-200">
                    {{ $carts->links() }}
                </div>
            @endif
        </div>
    </form>

    <script>
        document.getElementById('select-all')?.addEventListener('change', function () {
            document.querySelectorAll('.cart-checkbox').forEach(cb => cb.checked = this.checked);
            toggleBulkBtn();
        });
        document.querySelectorAll('.cart-checkbox').forEach(cb => cb.addEventListener('change', toggleBulkBtn));
        function toggleBulkBtn() {
            const checked = document.querySelectorAll('.cart-checkbox:checked').length;
            document.getElementById('bulk-delete-btn').classList.toggle('hidden', checked === 0);
        }
    </script>
</x-admin-layout>
