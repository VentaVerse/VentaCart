<x-admin.shell title="Recovered Carts">
    <x-admin.page title="Recovered">
        @include('admin.sales.abandoned-carts.partials.nav')

        <x-admin.table>
            <x-slot name="head">
                <x-admin.table.th>Customer</x-admin.table.th>
                <x-admin.table.th>Cart Items</x-admin.table.th>
                <x-admin.table.th>Notified</x-admin.table.th>
                <x-admin.table.th>Order</x-admin.table.th>
                <x-admin.table.th>Recovery Time</x-admin.table.th>
            </x-slot>
            @forelse ($recoveredCarts as $recovered)
                <x-admin.table.row>
                    <x-admin.table.cell>
                        <span class="v-lead">{{ $recovered['customer']->full_name }}</span>
                        <span class="v-sub">{{ $recovered['customer']->email }}</span>
                    </x-admin.table.cell>
                    <x-admin.table.cell class="text-v-secondary">{{ Str::limit($recovered['cart_items'], 80) }}</x-admin.table.cell>
                    <x-admin.table.cell>
                        <span class="v-cellstack">
                            <span class="text-v-text">{{ $recovered['send_count'] }}x</span>
                            <span class="v-cellstack__note">Last: {{ $recovered['last_sent_at']->diffForHumans() }}</span>
                        </span>
                    </x-admin.table.cell>
                    <x-admin.table.cell>
                        <a href="{{ route('admin.orders.show', $recovered['order']) }}" class="v-ref">#{{ $recovered['order']->id }}</a>
                    </x-admin.table.cell>
                    <x-admin.table.cell class="text-v-secondary">{{ $recovered['recovery_time'] }}</x-admin.table.cell>
                </x-admin.table.row>
            @empty
                <tr><td colspan="5"><x-admin.empty-state title="No recovered carts yet" /></td></tr>
            @endforelse
        </x-admin.table>
    </x-admin.page>
</x-admin.shell>
