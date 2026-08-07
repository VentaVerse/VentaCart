<x-admin.shell title="Recovered Carts">
    <x-admin.page title="Recovered" :breadcrumb="[['label' => 'Abandoned Carts', 'url' => route('admin.abandoned-carts.settings')]]">
        <div class="flex flex-col gap-6 lg:flex-row lg:gap-8">
            @include('admin.sales.abandoned-carts.partials.nav')

            <div class="min-w-0 flex-1">
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
                                <div class="font-medium text-v-text">{{ $recovered['customer']->full_name }}</div>
                                <div class="text-xs text-v-muted">{{ $recovered['customer']->email }}</div>
                            </x-admin.table.cell>
                            <x-admin.table.cell class="text-v-secondary">{{ Str::limit($recovered['cart_items'], 80) }}</x-admin.table.cell>
                            <x-admin.table.cell>
                                <div class="text-v-text">{{ $recovered['send_count'] }}x</div>
                                <div class="text-xs text-v-muted">Last: {{ $recovered['last_sent_at']->diffForHumans() }}</div>
                            </x-admin.table.cell>
                            <x-admin.table.cell>
                                <a href="{{ route('admin.orders.show', $recovered['order']) }}" class="text-v-accent hover:underline">
                                    #{{ $recovered['order']->id }}
                                </a>
                            </x-admin.table.cell>
                            <x-admin.table.cell class="text-v-secondary">{{ $recovered['recovery_time'] }}</x-admin.table.cell>
                        </x-admin.table.row>
                    @empty
                        <tr><td colspan="5" class="px-4 py-2"><x-admin.empty-state icon="cart" title="No recovered carts yet" /></td></tr>
                    @endforelse
                </x-admin.table>
            </div>
        </div>
    </x-admin.page>
</x-admin.shell>
