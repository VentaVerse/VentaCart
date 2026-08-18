{{-- Included by the customer edit page while the store-credit extension
     is enabled (guarded there by Route::has + View::exists). Its own
     form, so it sits OUTSIDE the customer form element. --}}
@php
    $creditBalance = \Extensions\StoreCredit\CreditService::balance($customer->id);
    $creditHistory = \Extensions\StoreCredit\Models\CustomerTransaction::with('creator')
        ->where('customer_id', $customer->id)
        ->latest('created_at')->latest('id')
        ->limit(10)
        ->get();
@endphp
<x-admin.panel class="mt-6">
    <div class="flex items-center justify-between gap-4 flex-wrap">
        <div>
            <h2 class="text-base font-semibold">Store Credit</h2>
            <p class="text-sm text-v-muted">Applies itself at checkout until the balance runs out.</p>
        </div>
        <div class="text-right">
            <div class="text-xs uppercase tracking-wide text-v-muted">Balance</div>
            <div class="text-xl font-semibold {{ $creditBalance < 0 ? 'text-v-danger' : '' }}">{{ number_format($creditBalance, 2) }}</div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.store-credit.adjust') }}" class="mt-4 grid grid-cols-1 md:grid-cols-[10rem_1fr_auto] gap-3 items-end">
        @csrf
        <input type="hidden" name="customer_id" value="{{ $customer->id }}">
        <x-admin.field name="amount" label="Amount">
            <x-admin.input type="number" name="amount" step="0.01" required placeholder="50 or -50" />
        </x-admin.field>
        <x-admin.field name="description" label="Description">
            <x-admin.input type="text" name="description" required maxlength="255" placeholder="Refund for order #1042" />
        </x-admin.field>
        <x-admin.button type="submit">Apply</x-admin.button>
    </form>

    @if ($creditHistory->isNotEmpty())
        <div class="mt-5 overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-v-muted">
                        <th class="py-1.5 pr-4 font-medium">Date</th>
                        <th class="py-1.5 pr-4 font-medium">Description</th>
                        <th class="py-1.5 pr-4 font-medium text-right">Amount</th>
                        <th class="py-1.5 font-medium">By</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($creditHistory as $tx)
                        <tr class="border-t border-v-border">
                            <td class="py-1.5 pr-4 whitespace-nowrap">{{ $tx->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="py-1.5 pr-4">
                                {{ $tx->description }}
                                @if ($tx->order_id)
                                    <a class="text-v-muted underline" href="{{ route('admin.orders.show', $tx->order_id) }}">order</a>
                                @endif
                            </td>
                            <td class="py-1.5 pr-4 text-right tabular-nums {{ $tx->amount < 0 ? 'text-v-danger' : '' }}">{{ $tx->amount < 0 ? '' : '+' }}{{ number_format($tx->amount, 2) }}</td>
                            <td class="py-1.5 text-v-muted">{{ $tx->creator?->name ?? ($tx->order_id ? 'Checkout' : 'System') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">
            <a class="text-sm text-v-muted underline" href="{{ route('admin.store-credit.ledger', ['search' => $customer->email]) }}">Full ledger</a>
        </div>
    @endif
</x-admin.panel>
