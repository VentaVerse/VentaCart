<x-abandoned-carts::space title="Recovered Carts" active="recovered">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Abandoned Carts</h1>
        </div>
    </div>


    <div class="v3-settle">
        <x-admin3.panel flush>
            <div class="v3-tablewrap">
                <table class="v3-table">
                    <thead>
                        <tr><th>Customer</th><th>Cart items</th><th>Notified</th><th>Order</th><th>Recovery time</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($recoveredCarts as $recovered)
                            <tr>
                                <td>
                                    {{ $recovered['customer']->full_name }}
                                    <span class="v3-cell-quiet v3-block">{{ $recovered['customer']->email }}</span>
                                </td>
                                <td class="v3-cell-quiet">{{ Str::limit($recovered['cart_items'], 80) }}</td>
                                <td>
                                    <span class="v3-mono">{{ $recovered['send_count'] }}x</span>
                                    <span class="v3-cell-quiet v3-block">Last {{ $recovered['last_sent_at']->diffForHumans() }}</span>
                                </td>
                                <td><a href="{{ route('admin.orders.show', $recovered['order']) }}" class="v3-rowlink v3-mono">#{{ $recovered['order']->id }}</a></td>
                                <td class="v3-cell-quiet">{{ $recovered['recovery_time'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="v3-empty"><p class="v3-empty__title">No recovered carts yet</p><p class="v3-empty__line">A cart counts as recovered when its customer orders one of its products after a reminder.</p></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin3.panel>
    </div>
</x-abandoned-carts::space>
