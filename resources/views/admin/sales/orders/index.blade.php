@php
    $statusTones = [
        'Pending' => 'warn', 'Unpaid' => 'warn', 'Processing' => 'info',
        'Payment Received' => 'info', 'Shipped' => 'info', 'Order Being Shipped' => 'info',
        'Delivered' => 'good', 'Complete' => 'good',
        'Canceled' => 'bad', 'Failed' => 'bad',
        'Refunded' => 'quiet', 'Expired' => 'quiet',
    ];
@endphp
<x-admin.shell title="Orders" active="sales">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">Orders</h1>
        </div>
        <div class="v-head__actions">
            <a href="{{ route('admin.orders.create') }}" class="v-btn v-btn--primary">Create order</a>
        </div>
    </div>

    <div class="v-settle">
        <form method="GET" action="{{ route('admin.orders.index') }}" class="v-filterbar">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Order #, name, email, tracking…"
                   aria-label="Search orders" class="v-control v-filterbar__grow">
            <select name="order_status_id" aria-label="Filter by status" class="v-control v-w-name">
                <option value="">All statuses</option>
                <option value="missing" @selected(request('order_status_id') === 'missing')>Missing</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->id }}" @selected((string) request('order_status_id') === (string) $status->id)>{{ $status->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="v-btn v-btn--secondary">Filter</button>
            @if (request('search') || request('order_status_id'))
                <a href="{{ route('admin.orders.index') }}" class="v-link">Clear</a>
            @endif
        </form>

        <x-admin.panel flush>
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Status</th>
                            <th class="v-cell-num">Total</th>
                            <th>Date</th>
                            <th class="v-cell-act"><span class="v-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                            <tr>
                                <td><a href="{{ route('admin.orders.show', $order) }}" class="v-rowlink v-mono">#{{ $order->id }}</a></td>
                                <td>
                                    {{ $order->full_name }}
                                    <span class="v-cell-quiet v-block">{{ $order->email }}</span>
                                </td>
                                <td>
                                    <span class="v-badge v-badge--{{ $order->order_status_name ? ($statusTones[$order->order_status_name] ?? 'quiet') : 'bad' }}">
                                        {{ $order->order_status_name ?? 'Missing' }}
                                    </span>
                                </td>
                                <td class="v-cell-num v-mono">{{ number_format($order->total, 2) }}</td>
                                <td class="v-cell-quiet">{{ $order->created_at->format('M d, Y H:i') }}</td>
                                <td class="v-cell-act">
                                    <a href="{{ route('admin.orders.show', $order) }}" class="v-btn v-btn--ghost v-btn--sm">View</a>
                                    <a href="{{ route('admin.orders.edit', $order) }}" class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                                    <button type="button" class="v-btn v-btn--danger v-btn--sm"
                                            @click="$dispatch('v-confirm', 'delete-order-{{ $order->id }}')">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="v-empty">
                                        <p class="v-empty__title">No orders found</p>
                                        <p class="v-empty__line">{{ (request('search') || request('order_status_id')) ? 'Try adjusting the filters.' : 'Orders placed on the storefront appear here.' }}</p>
                                        @unless (request('search') || request('order_status_id'))
                                            <a href="{{ route('admin.orders.create') }}" class="v-btn v-btn--primary v-btn--sm">Create your first order</a>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.panel>

        <x-admin.pager :paginator="$orders" />

        @foreach ($orders as $order)
            <x-admin.confirm name="delete-order-{{ $order->id }}" title="Delete order #{{ $order->id }}?"
                message="This permanently removes the order and its history. This cannot be undone."
                confirm="Delete" :action="route('admin.orders.destroy', $order)" method="DELETE" />
        @endforeach
    </div>
</x-admin.shell>
