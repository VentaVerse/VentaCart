<x-abandoned-carts::space :title="'Cart #' . $cart->id" active="carts">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.abandoned-carts.index') }}">Abandoned Carts</a>
                <span aria-hidden="true">/</span>
                <span>Cart #{{ $cart->id }}</span>
            </div>
            <h1 class="v3-head__title">Cart #{{ $cart->id }}</h1>
            <p class="v3-head__standing">Last activity {{ $cart->updated_at->diffForHumans() }}</p>
        </div>
        <div class="v3-head__actions">
            <button type="button" class="v3-btn v3-btn--danger" @click="$dispatch('v3-confirm', 'delete-cart')">Delete cart</button>
        </div>
    </div>

    <div class="v3-editcols">
        <div class="v3-settle">
            <x-admin3.panel title="Items" flush>
                <div class="v3-tablewrap">
                    <table class="v3-table">
                        <thead>
                            <tr><th>Product</th><th>Variant</th><th class="v3-cell-num">Price</th><th class="v3-cell-num">Qty</th><th class="v3-cell-num">Line total</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($cart->items as $item)
                                <tr>
                                    <td>
                                        {{ $item->name }}
                                        @if ($item->sku)<span class="v3-cell-quiet v3-block v3-mono">{{ $item->sku }}</span>@endif
                                    </td>
                                    <td class="v3-cell-quiet">{{ $item->variant_label ?? '-' }}</td>
                                    <td class="v3-cell-num v3-mono">{{ number_format($item->price, 2) }}</td>
                                    <td class="v3-cell-num v3-mono">{{ $item->quantity }}</td>
                                    <td class="v3-cell-num v3-mono">{{ number_format($item->line_total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="v3-ledger">
                    <dl>
                        <dt class="v3-facts__sum">Total</dt>
                        <dd class="v3-facts__sum v3-mono">₱{{ number_format($cart->total, 2) }}</dd>
                    </dl>
                </div>
            </x-admin3.panel>
        </div>

        <div class="v3-settle">
            <x-admin3.panel title="Customer">
                @if ($cart->customer)
                    <dl class="v3-facts">
                        <dt>Name</dt><dd>{{ $cart->customer->first_name }} {{ $cart->customer->last_name }}</dd>
                        <dt>Email</dt><dd>{{ $cart->customer->email }}</dd>
                        @if ($cart->customer->phone)<dt>Phone</dt><dd>{{ $cart->customer->phone }}</dd>@endif
                    </dl>
                @else
                    <dl class="v3-facts">
                        <dt>Who</dt><dd>Guest session</dd>
                        <dt>Session</dt><dd class="v3-mono">{{ Str::limit($cart->session_id, 18) }}</dd>
                    </dl>
                @endif
            </x-admin3.panel>

            <x-admin3.panel title="Timeline">
                <dl class="v3-facts">
                    <dt>Created</dt><dd>{{ $cart->created_at->format('M d, Y H:i') }}</dd>
                    <dt>Last activity</dt><dd>{{ $cart->updated_at->format('M d, Y H:i') }}</dd>
                    <dt>Abandoned for</dt><dd>{{ $cart->updated_at->diffForHumans(null, true) }}</dd>
                </dl>
            </x-admin3.panel>
        </div>
    </div>

    <x-admin3.confirm name="delete-cart" title="Delete abandoned cart?"
        message="Delete this abandoned cart? This cannot be undone." confirm="Delete cart"
        :action="route('admin.abandoned-carts.destroy', $cart)" method="DELETE" />
</x-abandoned-carts::space>
