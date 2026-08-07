@php
    $statusTones = [
        'Pending' => 'warning', 'Unpaid' => 'warning', 'Processing' => 'info',
        'Payment Received' => 'info', 'Shipped' => 'info', 'Order Being Shipped' => 'info',
        'Delivered' => 'success', 'Complete' => 'success',
        'Canceled' => 'danger', 'Failed' => 'danger',
        'Refunded' => 'neutral', 'Expired' => 'neutral',
    ];
@endphp
<x-admin.shell title="Orders">
    <x-admin.page title="Orders" :breadcrumb="[['label' => 'Sales']]">
        <x-slot:actions>
            <x-admin.button variant="primary" href="{{ route('admin.orders.create') }}">
                <x-admin.icon name="plus" class="w-4 h-4" /> Create Order
            </x-admin.button>
        </x-slot:actions>

        <div class="space-y-5">
            {{-- Filters --}}
            <x-admin.card>
                <form method="GET" action="{{ route('admin.orders.index') }}" class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[220px] flex-1">
                        <x-admin.field name="search" label="Search">
                            <x-admin.input type="text" name="search" :value="request('search')" placeholder="Order #, name, email, tracking..." />
                        </x-admin.field>
                    </div>
                    <div class="w-52">
                        <x-admin.field name="order_status_id" label="Status">
                            <x-admin.select name="order_status_id">
                                <option value="">All Statuses</option>
                                <option value="missing" @selected(request('order_status_id') === 'missing')>Missing</option>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->id }}" @selected((string) request('order_status_id') === (string) $status->id)>{{ $status->name }}</option>
                                @endforeach
                            </x-admin.select>
                        </x-admin.field>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-admin.button type="submit" variant="primary">Filter</x-admin.button>
                        @if (request('search') || request('order_status_id'))
                            <a href="{{ route('admin.orders.index') }}" class="text-[13px] text-v-muted underline hover:text-v-text">Clear</a>
                        @endif
                    </div>
                </form>
            </x-admin.card>

            {{-- Orders --}}
            <x-admin.table>
                <x-slot:head>
                    <x-admin.table.th>Order #</x-admin.table.th>
                    <x-admin.table.th>Customer</x-admin.table.th>
                    <x-admin.table.th>Status</x-admin.table.th>
                    <x-admin.table.th align="right">Total</x-admin.table.th>
                    <x-admin.table.th>Date</x-admin.table.th>
                    <x-admin.table.th align="right">Actions</x-admin.table.th>
                </x-slot:head>
                @forelse ($orders as $order)
                    <x-admin.table.row href="{{ route('admin.orders.show', $order) }}">
                        <x-admin.table.cell mono>#{{ $order->id }}</x-admin.table.cell>
                        <x-admin.table.cell>
                            <div class="text-v-text font-medium">{{ $order->full_name }}</div>
                            <div class="text-xs text-v-muted">{{ $order->email }}</div>
                        </x-admin.table.cell>
                        <x-admin.table.cell>
                            <x-admin.badge :tone="$order->order_status_id ? ($statusTones[$order->status?->name] ?? 'neutral') : 'danger'">
                                {{ $order->status?->name ?? 'Missing' }}
                            </x-admin.badge>
                        </x-admin.table.cell>
                        <x-admin.table.cell align="right" mono>{{ number_format($order->total, 2) }}</x-admin.table.cell>
                        <x-admin.table.cell>{{ $order->created_at->format('M d, Y H:i') }}</x-admin.table.cell>
                        <x-admin.table.cell align="right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.orders.show', $order) }}" class="text-[13px] text-v-accent hover:opacity-80">View</a>
                                <a href="{{ route('admin.orders.edit', $order) }}" class="text-[13px] text-v-secondary hover:text-v-text">Edit</a>
                                <button type="button" class="text-[13px] text-v-danger hover:opacity-80" @click="$dispatch('open-modal', 'delete-order-{{ $order->id }}')">Delete</button>
                            </div>
                        </x-admin.table.cell>
                    </x-admin.table.row>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-2">
                            <x-admin.empty-state icon="orders" title="No orders found"
                                :description="(request('search') || request('order_status_id')) ? 'Try adjusting your filters.' : 'Orders placed on the storefront will appear here.'">
                                <a href="{{ route('admin.orders.create') }}" class="text-[13px] text-v-accent hover:underline">Create your first order</a>
                            </x-admin.empty-state>
                        </td>
                    </tr>
                @endforelse
                @if ($orders->hasPages())
                    <x-slot:footer>
                        {{ $orders->links() }}
                    </x-slot:footer>
                @endif
            </x-admin.table>
        </div>

        @foreach ($orders as $order)
            <x-admin.confirm-dialog name="delete-order-{{ $order->id }}" title="Delete order #{{ $order->id }}?"
                message="This permanently removes the order and its history. This cannot be undone."
                confirm="Delete" :action="route('admin.orders.destroy', $order)" method="DELETE" />
        @endforeach
    </x-admin.page>
</x-admin.shell>
