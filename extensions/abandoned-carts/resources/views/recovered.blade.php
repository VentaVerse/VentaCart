<x-abandoned-carts::space title="Recovered Carts" active="recovered">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">Abandoned Carts</h1>
        </div>
    </div>


    <div class="v-settle">
        <x-admin.panel flush>
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr><th>Customer</th><th>Cart items</th><th>Notified</th><th>Order</th><th>Recovery time</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($recoveredCarts as $recovered)
                            <tr>
                                <td>
                                    {{ $recovered['customer']->full_name }}
                                    <span class="v-cell-quiet v-block">{{ $recovered['customer']->email }}</span>
                                </td>
                                <td class="v-cell-quiet">{{ Str::limit($recovered['cart_items'], 80) }}</td>
                                <td>
                                    <span class="v-mono">{{ $recovered['send_count'] }}x</span>
                                    <span class="v-cell-quiet v-block">Last {{ $recovered['last_sent_at']->diffForHumans() }}</span>
                                </td>
                                <td><a href="{{ route('admin.orders.show', $recovered['order']) }}" class="v-rowlink v-mono">#{{ $recovered['order']->id }}</a></td>
                                <td class="v-cell-quiet">{{ $recovered['recovery_time'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="v-empty"><p class="v-empty__title">No recovered carts yet</p><p class="v-empty__line">A cart counts as recovered when its customer orders one of its products after a reminder.</p></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.panel>
    </div>
</x-abandoned-carts::space>
