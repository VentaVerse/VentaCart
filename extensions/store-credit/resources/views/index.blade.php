<x-admin3.shell title="Store Credit">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <span>Store Credit</span>
            </div>
            <h1 class="v3-head__title">Store Credit</h1>
            <p class="v3-head__standing">Every credit granted and spent. Balances apply themselves at checkout; grant or deduct per customer from their own page.</p>
        </div>
        <div class="v3-head__actions">
            <x-admin3.extension-switch name="store-credit" />
        </div>
    </div>

    <div class="v3-panel v3-settle">
        <form method="GET" action="{{ route('admin.store-credit.index') }}" class="v3-inline v3-gap">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Customer name or email" aria-label="Search customers" class="v3-control">
            <button type="submit" class="v3-btn v3-btn--secondary">Search</button>
        </form>
    </div>

    <div class="v3-panel">
        @if ($transactions->isEmpty())
            <p class="v3-subnote">No transactions yet. Grant credit from a customer's page and it appears here.</p>
        @else
            <div class="v3-tablewrap">
                <table class="v3-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Description</th>
                            <th class="v3-cell-num">Amount</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transactions as $tx)
                            <tr>
                                <td class="v3-mono">{{ $tx->created_at?->format('Y-m-d H:i') }}</td>
                                <td>
                                    @if ($tx->customer)
                                        <a href="{{ route('admin.customers.edit', $tx->customer) }}">{{ $tx->customer->first_name }} {{ $tx->customer->last_name }}</a>
                                        <span class="v3-subnote">{{ $tx->customer->email }}</span>
                                    @else
                                        <span class="v3-subnote">Deleted customer</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $tx->description }}
                                    @if ($tx->order_id)
                                        <a class="v3-subnote" href="{{ route('admin.orders.show', $tx->order_id) }}">view order</a>
                                    @endif
                                </td>
                                <td class="v3-cell-num v3-mono {{ $tx->amount < 0 ? 'v3-tone-bad' : 'v3-tone-act' }}">{{ $tx->amount < 0 ? '' : '+' }}{{ number_format($tx->amount, 2) }}</td>
                                <td class="v3-subnote">{{ $tx->creator?->name ?? ($tx->order_id ? 'Checkout' : 'System') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="v3-settle">{{ $transactions->links() }}</div>
        @endif
    </div>
</x-admin3.shell>
