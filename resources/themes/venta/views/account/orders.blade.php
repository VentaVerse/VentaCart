<x-store-layout title="My Orders">
    <x-storefront.columns>
    <x-storefront.region name="content_top" />
    <div class="container-store py-10">
        <h1 class="section-title reveal">MY ORDERS</h1>

        <div class="account-layout">
            @include('account.partials.sidebar')

            <div>
                @if ($orders->isEmpty())
                    <div class="account-card text-center py-10 reveal">
                        <svg class="w-16 h-16 mx-auto text-[var(--color-text-heading)] mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        <p class="text-[var(--color-text)] mb-4">You haven't placed any orders yet.</p>
                        <a href="{{ route('home') }}" class="account-btn-primary inline-block">START SHOPPING</a>
                    </div>
                @else
                    <div class="account-card reveal">
                        <div class="overflow-x-auto account-table-wrap">
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
                                {{-- One tbody per order: the confirm strip is a second row
                                     in the same Alpine scope as the row it belongs to. --}}
                                @foreach ($orders as $order)
                                    @php $canCancel = $cancellable[$order->id] ?? false; @endphp
                                    <tbody x-data="{ open: false }">
                                        <tr>
                                            <td class="text-[var(--color-text-heading)]">#{{ $order->id }}</td>
                                            <td>{{ $order->created_at->format('M d, Y') }}</td>
                                            <td>{{ $order->products_count ?? $order->products()->count() }}</td>
                                            <td class="font-heading">{{ $defaultCurrency->symbol ?? '₱' }}{{ number_format($order->total, 2) }}</td>
                                            <td>
                                                @php $statusName = $order->order_status_name ?? 'Pending'; @endphp
                                                <span class="account-status-badge account-status-{{ strtolower($statusName) }}">{{ $statusName }}</span>
                                            </td>
                                            <td class="account-row-actions">
                                                <a href="{{ route('account.orders.show', $order) }}">View</a>
                                                @if ($canCancel)
                                                    <button type="button" class="account-row-actions__danger" x-show="!open" @click="open = true; $nextTick(() => $refs.reason.focus())">Cancel</button>
                                                @endif
                                            </td>
                                        </tr>
                                        @if ($canCancel)
                                            <tr x-show="open" x-cloak x-transition.opacity.duration.200ms>
                                                <td colspan="6" class="account-cancel-cell">
                                                    <form method="POST" action="{{ route('account.orders.cancel', $order) }}" class="account-cancel-panel">
                                                        @csrf
                                                        <label class="account-label" for="cancel-reason-{{ $order->id }}">Reason (optional)</label>
                                                        <input id="cancel-reason-{{ $order->id }}" x-ref="reason" type="text" name="reason" maxlength="500" class="account-input w-full">
                                                        <div class="account-cancel-panel__acts">
                                                            <button type="submit" class="account-btn-danger account-btn-danger--solid">YES, CANCEL ORDER #{{ $order->id }}</button>
                                                            <button type="button" class="account-btn-secondary" @click="open = false">KEEP IT</button>
                                                        </div>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endif
                                    </tbody>
                                @endforeach
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
    <x-storefront.region name="content_bottom" />
    </x-storefront.columns>
</x-store-layout>
