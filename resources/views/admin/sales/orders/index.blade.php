@php
    $statusTones = [
        'Pending' => 'warn', 'Unpaid' => 'warn', 'Processing' => 'info',
        'Payment Received' => 'info', 'Shipped' => 'info', 'Order Being Shipped' => 'info',
        'Delivered' => 'good', 'Complete' => 'good',
        'Canceled' => 'bad', 'Failed' => 'bad',
        'Refunded' => 'quiet', 'Expired' => 'quiet',
    ];
@endphp
<x-admin3.shell title="Orders" active="sales">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Orders</h1>
        </div>
        <div class="v3-head__actions">
            <a href="{{ route('admin.orders.create') }}" class="v3-btn v3-btn--primary">Create order</a>
        </div>
    </div>

    <div class="v3-settle">
        <form method="GET" action="{{ route('admin.orders.index') }}" class="v3-filterbar">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Order #, name, email, tracking…"
                   aria-label="Search orders" class="v3-control v3-filterbar__grow">
            <select name="order_status_id" aria-label="Filter by status" class="v3-control v3-w-name">
                <option value="">All statuses</option>
                <option value="missing" @selected(request('order_status_id') === 'missing')>Missing</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->id }}" @selected((string) request('order_status_id') === (string) $status->id)>{{ $status->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="v3-btn v3-btn--secondary">Filter</button>
            @if (request('search') || request('order_status_id'))
                <a href="{{ route('admin.orders.index') }}" class="v3-link">Clear</a>
            @endif
        </form>

        <x-admin3.panel flush>
            <div class="v3-tablewrap">
                <table class="v3-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Status</th>
                            <th class="v3-cell-num">Total</th>
                            <th>Date</th>
                            <th class="v3-cell-act"><span class="v3-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                            <tr>
                                <td><a href="{{ route('admin.orders.show', $order) }}" class="v3-rowlink v3-mono">#{{ $order->id }}</a></td>
                                <td>
                                    {{ $order->full_name }}
                                    <span class="v3-cell-quiet v3-block">{{ $order->email }}</span>
                                </td>
                                <td>
                                    <span class="v3-badge v3-badge--{{ $order->order_status_name ? ($statusTones[$order->order_status_name] ?? 'quiet') : 'bad' }}">
                                        {{ $order->order_status_name ?? 'Missing' }}
                                    </span>
                                </td>
                                <td class="v3-cell-num v3-mono">{{ number_format($order->total, 2) }}</td>
                                <td class="v3-cell-quiet">{{ $order->created_at->format('M d, Y H:i') }}</td>
                                <td class="v3-cell-act">
                                    <a href="{{ route('admin.orders.show', $order) }}" class="v3-btn v3-btn--ghost v3-btn--sm">View</a>
                                    <a href="{{ route('admin.orders.edit', $order) }}" class="v3-btn v3-btn--ghost v3-btn--sm">Edit</a>
                                    <button type="button" class="v3-btn v3-btn--danger v3-btn--sm"
                                            @click="$dispatch('v3-confirm', 'delete-order-{{ $order->id }}')">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="v3-empty">
                                        <p class="v3-empty__title">No orders found</p>
                                        <p class="v3-empty__line">{{ (request('search') || request('order_status_id')) ? 'Try adjusting the filters.' : 'Orders placed on the storefront appear here.' }}</p>
                                        @unless (request('search') || request('order_status_id'))
                                            <a href="{{ route('admin.orders.create') }}" class="v3-btn v3-btn--primary v3-btn--sm">Create your first order</a>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin3.panel>

        <x-admin3.pager :paginator="$orders" />

        @foreach ($orders as $order)
            <x-admin3.confirm name="delete-order-{{ $order->id }}" title="Delete order #{{ $order->id }}?"
                message="This permanently removes the order and its history. This cannot be undone."
                confirm="Delete" :action="route('admin.orders.destroy', $order)" method="DELETE" />
        @endforeach
    </div>
</x-admin3.shell>
