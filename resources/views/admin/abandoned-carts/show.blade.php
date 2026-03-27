<x-admin-layout>
    <x-slot name="header">Abandoned Cart #{{ $cart->id }}</x-slot>
    <x-slot name="headerActions">
        <a href="{{ route('admin.abandoned-carts.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors">
            Back to List
        </a>
    </x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Cart Details --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-sm font-medium text-gray-900">Cart Items</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Variant</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Line Total</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($cart->items as $item)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">{{ $item->name }}</div>
                                    @if ($item->sku)
                                        <div class="text-xs text-gray-400">SKU: {{ $item->sku }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $item->variant_label ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900">{{ number_format($item->price, 2) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900">{{ $item->quantity }}</td>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900 text-right">{{ number_format($item->line_total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="4" class="px-6 py-3 text-right text-sm font-medium text-gray-900">Total</td>
                            <td class="px-6 py-3 text-right text-sm font-bold text-gray-900">{{ number_format($cart->total, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- Customer Info --}}
        <div>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-medium text-gray-900 mb-4">Customer</h3>
                @if ($cart->customer)
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-xs text-gray-500">Name</dt>
                            <dd class="text-sm text-gray-900">{{ $cart->customer->first_name }} {{ $cart->customer->last_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Email</dt>
                            <dd class="text-sm text-gray-900">{{ $cart->customer->email }}</dd>
                        </div>
                        @if ($cart->customer->phone)
                            <div>
                                <dt class="text-xs text-gray-500">Phone</dt>
                                <dd class="text-sm text-gray-900">{{ $cart->customer->phone }}</dd>
                            </div>
                        @endif
                    </dl>
                @else
                    <p class="text-sm text-gray-400">Guest session</p>
                    <p class="text-xs text-gray-400 mt-1">Session: {{ $cart->session_id }}</p>
                @endif
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mt-6">
                <h3 class="text-sm font-medium text-gray-900 mb-4">Timeline</h3>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-xs text-gray-500">Created</dt>
                        <dd class="text-sm text-gray-900">{{ $cart->created_at->format('M d, Y H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Last Activity</dt>
                        <dd class="text-sm text-gray-900">{{ $cart->updated_at->format('M d, Y H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Abandoned For</dt>
                        <dd class="text-sm text-red-600 font-medium">{{ $cart->updated_at->diffForHumans() }}</dd>
                    </div>
                </dl>
            </div>

            <div class="mt-6">
                <form method="POST" action="{{ route('admin.abandoned-carts.destroy', $cart) }}" onsubmit="return confirm('Delete this abandoned cart?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="w-full px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition-colors">
                        Delete Cart
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
