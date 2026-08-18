<x-admin3.shell title="Store Credit Ledger">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.extensions.order-totals') }}">Order Totals</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.store-credit.index') }}">Store Credit</a>
                <span aria-hidden="true">/</span>
                <span>Ledger</span>
            </div>
            <h1 class="v3-head__title">Ledger</h1>
            <p class="v3-head__standing">Every credit granted and spent, store-wide.</p>
        </div>
    </div>

    <div class="v3-settle">
        <form method="GET" action="{{ route('admin.store-credit.ledger') }}" class="v3-filterbar">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Customer name or email…"
                   aria-label="Search customers" class="v3-control v3-filterbar__grow">
            <button type="submit" class="v3-btn v3-btn--secondary">Search</button>
            @if (request('search'))
                <a class="v3-link" href="{{ route('admin.store-credit.ledger') }}">Clear</a>
            @endif
        </form>

        <x-admin3.panel flush>
            @if ($transactions->isEmpty())
                <div class="v3-empty">No transactions yet. Grant credit from a customer's page and it appears here.</div>
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
                                    <td class="v3-mono v3-cell-quiet">{{ $tx->created_at?->format('Y-m-d H:i') }}</td>
                                    <td>
                                        @if ($tx->customer)
                                            <a class="v3-link" href="{{ route('admin.customers.edit', $tx->customer) }}">{{ $tx->customer->first_name }} {{ $tx->customer->last_name }}</a>
                                            <span class="v3-cell-quiet">{{ $tx->customer->email }}</span>
                                        @else
                                            <span class="v3-cell-quiet">Deleted customer</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $tx->description }}
                                        @if ($tx->order_id)
                                            <a class="v3-link" href="{{ route('admin.orders.show', $tx->order_id) }}">view order</a>
                                        @endif
                                    </td>
                                    <td class="v3-cell-num v3-mono {{ $tx->amount < 0 ? 'v3-tone-bad' : 'v3-tone-act' }}">{{ $tx->amount < 0 ? '' : '+' }}{{ number_format($tx->amount, 2) }}</td>
                                    <td class="v3-cell-quiet">{{ $tx->creator?->name ?? ($tx->order_id ? 'Checkout' : 'System') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-admin3.panel>

        @if ($transactions->hasPages())
            <div class="v3-settle">{{ $transactions->links() }}</div>
        @endif
    </div>
</x-admin3.shell>
