{{-- Included by the customer edit page while the store-credit extension
     is enabled (guarded there by Route::has + View::exists). Its own
     form, a record-scoped verb (Add-row create), so it sits outside the
     page's doctrine form and save bar. --}}
@php
    $creditBalance = \Extensions\StoreCredit\CreditService::balance($customer->id);
    $creditHistory = \Extensions\StoreCredit\Models\CustomerTransaction::with('creator')
        ->where('customer_id', $customer->id)
        ->latest('created_at')->latest('id')
        ->limit(10)
        ->get();
@endphp
<x-admin3.panel title="Store Credit" note="Applies itself at checkout until the balance runs out.">
    <x-slot:acts>
        <span class="v3-field__label">Balance</span>
        <span class="v3-mono {{ $creditBalance < 0 ? 'v3-tone-bad' : '' }}">{{ number_format($creditBalance, 2) }}</span>
    </x-slot:acts>

    <form method="POST" action="{{ route('admin.store-credit.adjust') }}" class="v3-inline">
        @csrf
        <input type="hidden" name="customer_id" value="{{ $customer->id }}">
        <label class="v3-field">
            <span class="v3-field__label">Description</span>
            <input type="text" name="description" required maxlength="255" class="v3-control">
        </label>
        <label class="v3-field">
            <span class="v3-field__label">
                Amount
                <x-admin3.help text="Signed: positive grants credit, negative deducts it." />
            </span>
            <input type="number" name="amount" step="0.01" required class="v3-control v3-mono v3-w-name">
        </label>
        <button type="submit" class="v3-btn v3-btn--secondary">Apply</button>
    </form>

    @if ($creditHistory->isNotEmpty())
        <div class="v3-gap v3-tablewrap">
            <table class="v3-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th class="v3-cell-num">Amount</th>
                        <th>By</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($creditHistory as $tx)
                        <tr>
                            <td class="v3-mono v3-cell-quiet">{{ $tx->created_at?->format('Y-m-d H:i') }}</td>
                            <td>
                                {{ $tx->description }}
                                @if ($tx->order_id)
                                    <a class="v3-link" href="{{ route('admin.orders.show', $tx->order_id) }}">order</a>
                                @endif
                            </td>
                            <td class="v3-cell-num v3-mono {{ $tx->amount < 0 ? 'v3-tone-bad' : 'v3-tone-act' }}">{{ $tx->amount < 0 ? '' : '+' }}{{ number_format($tx->amount, 2) }}</td>
                            <td class="v3-cell-quiet">{{ $tx->creator?->name ?? ($tx->order_id ? 'Checkout' : 'System') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="v3-gap">
            <a class="v3-link" href="{{ route('admin.store-credit.ledger', ['search' => $customer->email]) }}">Full ledger</a>
        </div>
    @endif
</x-admin3.panel>
