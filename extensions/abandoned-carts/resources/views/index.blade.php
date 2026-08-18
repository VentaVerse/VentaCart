<x-admin3.shell title="Abandoned Carts" active="sales">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Abandoned Carts</h1>
        </div>
        <div class="v3-head__actions">
            <button type="button" class="v3-btn v3-btn--secondary" @click="$dispatch('v3-confirm', 'mass-delete-guests')">Delete all guests</button>
            <button type="button" class="v3-btn v3-btn--danger" @click="$dispatch('v3-confirm', 'mass-delete-all')">Delete all</button>
        </div>
    </div>

    @include('abandoned-carts::partials.tabs')

    <div class="v3-settle">
        <div class="v3-stats">
            <div class="v3-stat"><div class="v3-stat__label">Abandoned</div><div class="v3-stat__value">{{ number_format($totalAbandoned) }}</div></div>
            <div class="v3-stat"><div class="v3-stat__label">Value sitting in them</div><div class="v3-stat__value">₱{{ number_format($totalValue, 2) }}</div></div>
        </div>

        <form method="GET" action="{{ route('admin.abandoned-carts.index') }}" class="v3-filterbar">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Customer name, email…"
                   aria-label="Search carts" class="v3-control v3-filterbar__grow">
            <select name="type" aria-label="Cart type" class="v3-control v3-w-name">
                <option value="">All types</option>
                <option value="registered" @selected(request('type') === 'registered')>Registered</option>
                <option value="guest" @selected(request('type') === 'guest')>Guest</option>
            </select>
            <button type="submit" class="v3-btn v3-btn--secondary">Filter</button>
            @if (request('search') || request('type'))
                <a class="v3-link" href="{{ route('admin.abandoned-carts.index') }}">Clear</a>
            @endif
        </form>

        <x-admin3.panel flush>
            <div class="v3-tablewrap">
                <table class="v3-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th class="v3-cell-num">Items</th>
                            <th class="v3-cell-num">Total</th>
                            <th>Abandoned</th>
                            <th class="v3-cell-act"><span class="v3-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($carts as $cart)
                            <tr>
                                <td>
                                    @if ($cart->customer)
                                        <a href="{{ route('admin.abandoned-carts.show', $cart) }}" class="v3-rowlink">{{ $cart->customer->first_name }} {{ $cart->customer->last_name }}</a>
                                        <span class="v3-cell-quiet v3-block">{{ $cart->customer->email }}</span>
                                    @else
                                        <a href="{{ route('admin.abandoned-carts.show', $cart) }}" class="v3-rowlink">Guest</a>
                                    @endif
                                </td>
                                <td class="v3-cell-num v3-mono">{{ $cart->items->count() }}</td>
                                <td class="v3-cell-num v3-mono">{{ number_format($cart->total, 2) }}</td>
                                <td class="v3-cell-quiet">{{ $cart->updated_at->diffForHumans() }}</td>
                                <td class="v3-cell-act">
                                    <a href="{{ route('admin.abandoned-carts.show', $cart) }}" class="v3-btn v3-btn--ghost v3-btn--sm">View</a>
                                    <button type="button" class="v3-btn v3-btn--danger v3-btn--sm" @click="$dispatch('v3-confirm', 'delete-cart-{{ $cart->id }}')">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="v3-empty"><p class="v3-empty__title">No abandoned carts found</p></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin3.panel>

        <x-admin3.pager :paginator="$carts" />

        @foreach ($carts as $cart)
            <x-admin3.confirm name="delete-cart-{{ $cart->id }}" title="Delete abandoned cart?"
                message="Delete this cart? This cannot be undone." confirm="Delete"
                :action="route('admin.abandoned-carts.destroy', $cart)" method="DELETE" />
        @endforeach

        <x-admin3.confirm name="mass-delete-guests" title="Delete all guest carts?"
            message="Every guest cart is deleted. This cannot be undone." confirm="Delete all guests"
            :action="route('admin.abandoned-carts.mass-delete')" method="POST">
            <input type="hidden" name="type" value="guest">
        </x-admin3.confirm>
        <x-admin3.confirm name="mass-delete-all" title="Delete all abandoned carts?"
            message="Every abandoned cart is deleted. This cannot be undone." confirm="Delete all"
            :action="route('admin.abandoned-carts.mass-delete')" method="POST">
            <input type="hidden" name="type" value="all">
        </x-admin3.confirm>
    </div>
</x-admin3.shell>
