{{--
    The customer's store credit: the balance, then their own ledger, newest
    first, a page at a time. Drawn inside the live theme's account frame
    when it has one, else a plain page. Every value is printed as text.
--}}
@extends(view()->exists('extension.account') ? 'extension.account' : 'store-credit::plain')
@section('title', 'Store credit')
@push('head')
    @vite('extensions/store-credit/resources/css/account.css')
@endpush
@section('content')
    <section class="acct__block store-credit" aria-labelledby="store-credit-balance">
        <div class="acct__card store-credit__card">
            <p class="muted" id="store-credit-balance">Your balance</p>
            <p class="store-credit__balance" data-store-credit-balance>{{ store_money($balance) }}</p>
            <p class="muted">Your balance is taken off your next order at checkout.</p>
        </div>
    </section>

    @if ($history->isEmpty() && $history->onFirstPage())
        @if (view()->exists('extension.empty'))
            @include('extension.empty', ['title' => 'No store credit yet', 'text' => 'Credit the store gives you, and credit you spend on orders, is listed here.'])
        @else
            <p class="store-credit__empty">No store credit yet.</p>
        @endif
    @elseif ($history->isEmpty())
        <p class="store-credit__empty">Nothing more to show. <a href="{{ $history->url(1) }}">Back to the newest</a></p>
    @else
        <section class="acct__block" aria-label="Store credit history">
            <table class="store-credit__ledger">
                <thead>
                    <tr><th scope="col">Date</th><th scope="col">Description</th><th scope="col" class="store-credit__num">Amount</th><th scope="col" class="store-credit__num">Balance after</th></tr>
                </thead>
                <tbody>
                    @foreach ($history as $line)
                        <tr data-store-credit-line>
                            <td data-label="Date"><time datetime="{{ $line['date']->toIso8601String() }}">{{ $line['date']->format('j M Y') }}</time></td>
                            <td data-label="Description">{{ $line['description'] }}</td>
                            <td data-label="Amount" class="store-credit__num {{ $line['amount'] < 0 ? 'store-credit__out' : 'store-credit__in' }}">{{ $line['amount'] < 0 ? '−' : '+' }}{{ store_money(abs($line['amount'])) }}</td>
                            <td data-label="Balance after" class="store-credit__num">{{ store_money($line['balance']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
        @if ($history->hasPages())
            <nav class="acct__pages store-credit__pages" aria-label="Store credit pages">
                @if ($history->onFirstPage())
                    <span class="btn btn--secondary btn--sm" aria-disabled="true">Newer</span>
                @else
                    <a class="btn btn--secondary btn--sm" href="{{ $history->previousPageUrl() }}" rel="prev">Newer</a>
                @endif
                <span class="muted">Page {{ $history->currentPage() }}</span>
                @if ($history->hasMorePages())
                    <a class="btn btn--secondary btn--sm" href="{{ $history->nextPageUrl() }}" rel="next">Older</a>
                @else
                    <span class="btn btn--secondary btn--sm" aria-disabled="true">Older</span>
                @endif
            </nav>
        @endif
    @endif
@endsection
