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
    <x-admin.page title="Orders">
        <x-slot:actions>
            <x-admin.button variant="primary" href="{{ route('admin.orders.create') }}">
                <x-admin.icon name="plus" class="w-4 h-4" /> Create Order
            </x-admin.button>
        </x-slot:actions>

        <div>
            <x-admin.filters :action="route('admin.orders.index')">
                <x-admin.field name="search" label="Search" class="v-field--grow">
                    <x-admin.input type="text" name="search" :value="request('search')" placeholder="Order #, name, email, tracking..." />
                </x-admin.field>
                <x-admin.field name="order_status_id" label="Status" class="w-52">
                    <x-admin.select name="order_status_id">
                        <option value="">All Statuses</option>
                        <option value="missing" @selected(request('order_status_id') === 'missing')>Missing</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->id }}" @selected((string) request('order_status_id') === (string) $status->id)>{{ $status->name }}</option>
                        @endforeach
                    </x-admin.select>
                </x-admin.field>
                <x-admin.button type="submit" variant="secondary">Filter</x-admin.button>
                @if (request('search') || request('order_status_id'))
                    <a href="{{ route('admin.orders.index') }}" class="v-filters__reset">Clear</a>
                @endif
            </x-admin.filters>

            {{-- Orders --}}
            <x-admin.table>
                <x-slot:head>
                    <x-admin.table.th>Order #</x-admin.table.th>
                    <x-admin.table.th>Customer</x-admin.table.th>
                    <x-admin.table.th>Status</x-admin.table.th>
                    <x-admin.table.th align="right">Total</x-admin.table.th>
                    <x-admin.table.th>Date</x-admin.table.th>
                    <x-admin.table.th class="v-col-act">Actions</x-admin.table.th>
                </x-slot:head>
                @forelse ($orders as $order)
                    <x-admin.table.row href="{{ route('admin.orders.show', $order) }}">
                        <x-admin.table.cell><a href="{{ route('admin.orders.show', $order) }}" class="v-ref">#{{ $order->id }}</a></x-admin.table.cell>
                        <x-admin.table.cell>
                            <span class="v-lead">{{ $order->full_name }}</span>
                            <span class="v-sub">{{ $order->email }}</span>
                        </x-admin.table.cell>
                        <x-admin.table.cell>
                            <x-admin.badge :tone="$order->order_status_name ? ($statusTones[$order->order_status_name] ?? 'neutral') : 'danger'">
                                {{ $order->order_status_name ?? 'Missing' }}
                            </x-admin.badge>
                        </x-admin.table.cell>
                        <x-admin.table.cell align="right" mono>{{ number_format($order->total, 2) }}</x-admin.table.cell>
                        <x-admin.table.cell>{{ $order->created_at->format('M d, Y H:i') }}</x-admin.table.cell>
                        <x-admin.table.cell class="v-col-act">
                            <div class="v-rowactions v-rowactions--end">
                                <x-admin.button size="sm" variant="ghost" :href="route('admin.orders.show', $order)">View</x-admin.button>
                                <x-admin.button size="sm" variant="ghost" :href="route('admin.orders.edit', $order)">Edit</x-admin.button>
                                <x-admin.button size="sm" variant="ghost" class="v-rowactions__danger" @click="$dispatch('open-modal', 'delete-order-{{ $order->id }}')">Delete</x-admin.button>
                            </div>
                        </x-admin.table.cell>
                    </x-admin.table.row>
                @empty
                    <tr>
                        <td colspan="6">
                            <x-admin.empty-state title="No orders found"
                                :description="(request('search') || request('order_status_id')) ? 'Try adjusting your filters.' : 'Orders placed on the storefront will appear here.'">
                                <x-admin.button variant="primary" :href="route('admin.orders.create')">Create your first order</x-admin.button>
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
