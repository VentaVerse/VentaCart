<x-admin.shell title="Store Credit Ledger">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.extensions.order-totals') }}">Order Totals</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.store-credit.index') }}">Store Credit</a>
                <span aria-hidden="true">/</span>
                <span>Ledger</span>
            </div>
            <h1 class="v-head__title">Ledger</h1>
            <p class="v-head__standing">Every credit granted and spent, store-wide.</p>
        </div>
    </div>

    <div class="v-settle">
        <form method="GET" action="{{ route('admin.store-credit.ledger') }}" class="v-filterbar">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Customer name or email…"
                   aria-label="Search customers" class="v-control v-filterbar__grow">
            <button type="submit" class="v-btn v-btn--secondary">Search</button>
            @if (request('search'))
                <a class="v-link" href="{{ route('admin.store-credit.ledger') }}">Clear</a>
            @endif
        </form>

        <x-admin.panel flush>
            @if ($transactions->isEmpty())
                <div class="v-empty">No transactions yet. Grant credit from a customer's page and it appears here.</div>
            @else
                <div class="v-tablewrap">
                    <table class="v-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Description</th>
                                <th class="v-cell-num">Amount</th>
                                <th>By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($transactions as $tx)
                                <tr>
                                    <td class="v-mono v-cell-quiet">{{ $tx->created_at?->format('Y-m-d H:i') }}</td>
                                    <td>
                                        @if ($tx->customer)
                                            <a class="v-link" href="{{ route('admin.customers.edit', $tx->customer) }}">{{ $tx->customer->first_name }} {{ $tx->customer->last_name }}</a>
                                            <span class="v-cell-quiet">{{ $tx->customer->email }}</span>
                                        @else
                                            <span class="v-cell-quiet">Deleted customer</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $tx->description }}
                                        @if ($tx->order_id)
                                            <a class="v-link" href="{{ route('admin.orders.show', $tx->order_id) }}">view order</a>
                                        @endif
                                    </td>
                                    <td class="v-cell-num v-mono {{ $tx->amount < 0 ? 'v-tone-bad' : 'v-tone-act' }}">{{ $tx->amount < 0 ? '' : '+' }}{{ number_format($tx->amount, 2) }}</td>
                                    <td class="v-cell-quiet">{{ $tx->creator?->name ?? ($tx->order_id ? 'Checkout' : 'System') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-admin.panel>

        @if ($transactions->hasPages())
            <div class="v-settle">{{ $transactions->links() }}</div>
        @endif
    </div>
</x-admin.shell>
