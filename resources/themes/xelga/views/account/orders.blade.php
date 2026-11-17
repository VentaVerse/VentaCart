{{-- Every order of the customer, as core lists them, fifteen to a page. --}}
@php app(\Themes\Xelga\Rendering\Storefront::class)->page('account'); @endphp
<x-store-layout title="Your orders">
    @include('xelga::partials.fixed.page-assets')
    <div class="s fixed-page scheme-1" data-fixed-page="orders">
        <div class="s__inner">
            @include('xelga::partials.fixed.crumbs', ['trail' => [['label' => 'Your account', 'url' => route('account.dashboard')], ['label' => 'Orders']]])
            <div class="acct">
                @include('xelga::partials.fixed.account-nav')
                <div class="acct__main">
                    <header class="acct__head">
                        <h1 class="acct__title">Your orders</h1>
                    </header>
                    @include('xelga::partials.fixed.flash')
                    @if ($orders->isEmpty())
                        <div class="empty-note">
                            <p class="empty-note__title">No orders yet</p>
                            <p class="muted">When you place an order, it shows here with its status and tracking.</p>
                            <p><a class="btn" href="{{ route('home') }}">Start shopping</a></p>
                        </div>
                    @else
                        <section class="acct__block" aria-label="Orders">
                            @include('xelga::partials.fixed.orders-table', ['orders' => $orders, 'cancellable' => $cancellable ?? []])
                        </section>
                        @if ($orders->hasPages())
                            <nav class="acct__pages" aria-label="Order pages">
                                @if ($orders->onFirstPage())
                                    <span class="btn btn--secondary btn--sm" aria-disabled="true">Newer</span>
                                @else
                                    <a class="btn btn--secondary btn--sm" href="{{ $orders->previousPageUrl() }}" rel="prev">Newer</a>
                                @endif
                                <span class="muted">Page {{ $orders->currentPage() }} of {{ $orders->lastPage() }}</span>
                                @if ($orders->hasMorePages())
                                    <a class="btn btn--secondary btn--sm" href="{{ $orders->nextPageUrl() }}" rel="next">Older</a>
                                @else
                                    <span class="btn btn--secondary btn--sm" aria-disabled="true">Older</span>
                                @endif
                            </nav>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-store-layout>
