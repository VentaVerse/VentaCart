<x-store-layout title="My Orders">
    <div class="container-store py-10">
        <h1 class="section-title reveal">MY ORDERS</h1>

        <div class="account-layout">
            @include('account.partials.sidebar')

            <div>
                @if ($orders->isEmpty())
                    <div class="account-card text-center py-10 reveal">
                        <svg class="w-16 h-16 mx-auto text-[#333] mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        <p class="text-[#6B8FAD] mb-4">You haven't placed any orders yet.</p>
                        <a href="{{ route('home') }}" class="account-btn-primary inline-block">START SHOPPING</a>
                    </div>
                @else
                    <div class="account-card reveal">
                        <div class="overflow-x-auto">
                            <table class="account-table w-full">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Date</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($orders as $order)
                                        <tr>
                                            <td class="text-[#F0EDE8]">#{{ $order->id }}</td>
                                            <td>{{ $order->created_at->format('M d, Y') }}</td>
                                            <td>{{ $order->products_count ?? $order->products()->count() }}</td>
                                            <td class="font-heading">{{ $defaultCurrency->symbol ?? '₱' }}{{ number_format($order->total, 2) }}</td>
                                            <td>
                                                @php $statusName = $order->status?->name ?? 'Pending'; @endphp
                                                <span class="account-status-badge account-status-{{ strtolower($statusName) }}">{{ $statusName }}</span>
                                            </td>
                                            <td><a href="{{ route('account.orders.show', $order) }}" class="text-xs text-[var(--color-primary)] hover:underline">View</a></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-6">
                            {{ $orders->links() }}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-store-layout>
