<x-abandoned-carts::space title="Abandoned Carts" active="carts">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">Abandoned Carts</h1>
        </div>
        <div class="v-head__actions">
            <button type="button" class="v-btn v-btn--secondary" @click="$dispatch('v-confirm', 'mass-delete-guests')">Delete all guests</button>
            <button type="button" class="v-btn v-btn--danger" @click="$dispatch('v-confirm', 'mass-delete-all')">Delete all</button>
        </div>
    </div>


    <div class="v-settle">
        <div class="v-stats">
            <div class="v-stat"><div class="v-stat__label">Abandoned</div><div class="v-stat__value">{{ number_format($totalAbandoned) }}</div></div>
            <div class="v-stat"><div class="v-stat__label">Value sitting in them</div><div class="v-stat__value">₱{{ number_format($totalValue, 2) }}</div></div>
        </div>

        <form method="GET" action="{{ route('admin.abandoned-carts.index') }}" class="v-filterbar">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Customer name, email…"
                   aria-label="Search carts" class="v-control v-filterbar__grow">
            <select name="type" aria-label="Cart type" class="v-control v-w-name">
                <option value="">All types</option>
                <option value="registered" @selected(request('type') === 'registered')>Registered</option>
                <option value="guest" @selected(request('type') === 'guest')>Guest</option>
            </select>
            <button type="submit" class="v-btn v-btn--secondary">Filter</button>
            @if (request('search') || request('type'))
                <a class="v-link" href="{{ route('admin.abandoned-carts.index') }}">Clear</a>
            @endif
        </form>

        <x-admin.panel flush>
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th class="v-cell-num">Items</th>
                            <th class="v-cell-num">Total</th>
                            <th>Abandoned</th>
                            <th class="v-cell-act"><span class="v-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($carts as $cart)
                            <tr>
                                <td>
                                    @if ($cart->customer)
                                        <a href="{{ route('admin.abandoned-carts.show', $cart) }}" class="v-rowlink">{{ $cart->customer->first_name }} {{ $cart->customer->last_name }}</a>
                                        <span class="v-cell-quiet v-block">{{ $cart->customer->email }}</span>
                                    @else
                                        <a href="{{ route('admin.abandoned-carts.show', $cart) }}" class="v-rowlink">Guest</a>
                                    @endif
                                </td>
                                <td class="v-cell-num v-mono">{{ $cart->items->count() }}</td>
                                <td class="v-cell-num v-mono">{{ number_format($cart->total, 2) }}</td>
                                <td class="v-cell-quiet">{{ $cart->updated_at->diffForHumans() }}</td>
                                <td class="v-cell-act">
                                    <a href="{{ route('admin.abandoned-carts.show', $cart) }}" class="v-btn v-btn--ghost v-btn--sm">View</a>
                                    <button type="button" class="v-btn v-btn--danger v-btn--sm" @click="$dispatch('v-confirm', 'delete-cart-{{ $cart->id }}')">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="v-empty"><p class="v-empty__title">No abandoned carts found</p></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.panel>

        <x-admin.pager :paginator="$carts" />

        @foreach ($carts as $cart)
            <x-admin.confirm name="delete-cart-{{ $cart->id }}" title="Delete abandoned cart?"
                message="Delete this cart? This cannot be undone." confirm="Delete"
                :action="route('admin.abandoned-carts.destroy', $cart)" method="DELETE" />
        @endforeach

        <x-admin.confirm name="mass-delete-guests" title="Delete all guest carts?"
            message="Every guest cart is deleted. This cannot be undone." confirm="Delete all guests"
            :action="route('admin.abandoned-carts.mass-delete')" method="POST">
            <input type="hidden" name="type" value="guest">
        </x-admin.confirm>
        <x-admin.confirm name="mass-delete-all" title="Delete all abandoned carts?"
            message="Every abandoned cart is deleted. This cannot be undone." confirm="Delete all"
            :action="route('admin.abandoned-carts.mass-delete')" method="POST">
            <input type="hidden" name="type" value="all">
        </x-admin.confirm>
    </div>
</x-abandoned-carts::space>
