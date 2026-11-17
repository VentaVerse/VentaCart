{{--
    One order as core keeps it: status, payment, total, tracking, the lines
    and totals in the store's currency, both addresses, the history notes
    written for the customer, and Cancel while core's rule allows it.
--}}
@php
    app(\Themes\Xelga\Rendering\Storefront::class)->page('account');
    $statusName = $order->order_status_name ?? 'Pending';
    // The tracking link comes from a courier or an import, so only a web address passing the store's link rule becomes a link.
    $trackingLink = \App\Support\SafeInput::link($order->tracking_link);
    $trackingLink = preg_match('#^https?://#i', $trackingLink) === 1 ? $trackingLink : '';
@endphp
<x-store-layout title="Order #{{ $order->id }}">
    @include('xelga::partials.fixed.page-assets')
    <div class="s fixed-page scheme-1" data-fixed-page="order">
        <div class="s__inner">
            @include('xelga::partials.fixed.crumbs', ['trail' => [['label' => 'Your account', 'url' => route('account.dashboard')], ['label' => 'Orders', 'url' => route('account.orders')], ['label' => 'Order #' . $order->id]]])
            <div class="acct">
                @include('xelga::partials.fixed.account-nav')
                <div class="acct__main">
                    <header class="acct__head order-head">
                        <div>
                            <h1 class="acct__title">Order #{{ $order->id }}</h1>
                            <p class="muted">Placed {{ $order->created_at->format('j M Y, g:i a') }}</p>
                        </div>
                        @include('xelga::partials.fixed.status', ['name' => $statusName, 'tone' => $order->status?->tone, 'large' => true])
                    </header>
                    @include('xelga::partials.fixed.flash', ['showErrors' => true])
                    <dl class="facts">
                        <div><dt>Payment</dt><dd>{{ $order->payment_method_label ?: 'Not recorded' }}</dd></div>
                        @if ($order->courier_label)
                            <div><dt>Delivery</dt><dd>{{ $order->courier_label }}</dd></div>
                        @endif
                        <div><dt>Total</dt><dd class="tabular">{{ store_money($order->total) }}</dd></div>
                    </dl>
                    @if ($order->tracking_number)
                        <section class="acct__block track" aria-labelledby="track-title">
                            <h2 class="acct__subtitle" id="track-title">Tracking</h2>
                            @if ($order->shipping_courier_name)
                                <p class="track__row"><svg class="icon" aria-hidden="true"><use href="#i-truck"></use></svg><span>Courier: <strong>{{ $order->shipping_courier_name }}</strong></span></p>
                            @endif
                            <p class="track__row">
                                <span>Tracking number</span>
                                @if ($trackingLink !== '')
                                    <a class="track__num tabular" href="{{ $trackingLink }}" target="_blank" rel="noopener noreferrer">{{ $order->tracking_number }}<span class="visually-hidden"> (opens in a new tab)</span></a>
                                @else
                                    <span class="track__num tabular">{{ $order->tracking_number }}</span>
                                @endif
                                <button type="button" class="icon-btn icon-btn--sm" data-copy="{{ $order->tracking_number }}" aria-label="Copy tracking number" hidden><svg class="icon icon--sm" aria-hidden="true"><use href="#i-copy"></use></svg></button>
                                <span class="visually-hidden" role="status" data-copy-status></span>
                            </p>
                        </section>
                    @endif
                    <section class="acct__block" aria-labelledby="items-title">
                        <h2 class="acct__subtitle" id="items-title">Items</h2>
                        <ul class="lines">
                            @foreach ($order->products as $item)
                                @php
                                    $itemImage = $item->image ?: $item->product?->image;
                                    $itemUrl = $item->product && $item->product->is_active ? route('product.show', $item->product->slug) : null;
                                @endphp
                                <li class="line">
                                    @if ($itemImage)
                                        <img src="{{ thumb_url($itemImage) }}" alt="" width="72" height="90" loading="lazy" decoding="async">
                                    @else
                                        <span class="line__ph" aria-hidden="true"></span>
                                    @endif
                                    <div class="line__text">
                                        @if ($itemUrl)<a href="{{ $itemUrl }}">{{ $item->name }}</a>@else<span>{{ $item->name }}</span>@endif
                                        @if ($item->variant_label)<span class="muted">{{ $item->variant_label }}</span>@endif
                                        <span class="muted">{{ $item->quantity }} &times; {{ store_money($item->price) }}</span>
                                    </div>
                                    <span class="line__price tabular">{{ store_money($item->price * $item->quantity) }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <dl class="totals">
                            <div><dt>Subtotal</dt><dd class="tabular">{{ store_money($order->subtotal) }}</dd></div>
                            @if ($order->shipping_cost > 0 || $order->courier_label)
                                <div><dt>Shipping @if ($order->courier_label)<span class="muted">({{ $order->courier_label }})</span>@endif</dt><dd class="tabular">{{ $order->shipping_cost > 0 ? store_money($order->shipping_cost) : 'Free' }}</dd></div>
                            @endif
                            @foreach (collect($order->total_lines ?? []) as $line)
                                @if (($line['amount'] ?? 0) != 0)
                                    <div><dt>{{ $line['label'] }}</dt><dd class="tabular">{{ $line['amount'] < 0 ? '-' : '' }}{{ store_money(abs($line['amount'])) }}</dd></div>
                                @endif
                            @endforeach
                            <div class="totals__grand"><dt>Total</dt><dd class="tabular">{{ store_money($order->total) }}</dd></div>
                        </dl>
                    </section>
                    @if ($order->shipping_address_1 || $order->billing_address_1)
                        <div class="acct__pair">
                            @if ($order->shipping_address_1)
                                <section class="acct__block" aria-labelledby="ship-title">
                                    <h2 class="acct__subtitle" id="ship-title">Ship to</h2>
                                    @include('xelga::partials.fixed.address', ['a' => [
                                        'first_name' => $order->shipping_first_name, 'last_name' => $order->shipping_last_name,
                                        'address_1' => $order->shipping_address_1, 'barangay' => $order->shipping_barangay,
                                        'district' => $order->shipping_district, 'city' => $order->shipping_city,
                                        'state' => $order->shipping_state, 'postcode' => $order->shipping_postcode,
                                        'country' => $order->shipping_country_name ?? $order->shippingCountry?->name, 'phone' => $order->shipping_phone_display,
                                    ]])
                                </section>
                            @endif
                            <section class="acct__block" aria-labelledby="bill-title">
                                <h2 class="acct__subtitle" id="bill-title">Bill to</h2>
                                @if ($order->billing_first_name)
                                    @include('xelga::partials.fixed.address', ['a' => [
                                        'first_name' => $order->billing_first_name, 'last_name' => $order->billing_last_name,
                                        'address_1' => $order->billing_address_1, 'barangay' => $order->billing_barangay,
                                        'district' => $order->billing_district, 'city' => $order->billing_city,
                                        'state' => $order->billing_state, 'postcode' => $order->billing_postcode,
                                        'country' => $order->billing_country_name ?? $order->billingCountry?->name, 'phone' => $order->billing_phone_display,
                                    ]])
                                @else
                                    <p class="muted">Same as the delivery address.</p>
                                @endif
                            </section>
                        </div>
                    @endif
                    @if ($order->histories->isNotEmpty())
                        <section class="acct__block" aria-labelledby="history-title">
                            <h2 class="acct__subtitle" id="history-title">Order history</h2>
                            <ol class="timeline">
                                @foreach ($order->histories->sortByDesc('created_at')->values() as $history)
                                    <li @if ($loop->first) class="is-current" @endif>
                                        <span class="timeline__dot" aria-hidden="true"></span>
                                        <div>
                                            <p>@if ($history->order_status_name)<strong>{{ $history->order_status_name }}</strong> @endif<span class="muted">{{ $history->created_at->format('j M Y, g:i a') }}</span></p>
                                            {{-- Only notes written for the customer reach this page; the store's own notes stay in the admin. --}}
                                            @if ($history->notify_customer && $history->customerFacingComment() !== '')
                                                <p class="muted">{{ strip_tags($history->customerFacingComment()) }}</p>
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ol>
                        </section>
                    @endif
                    <div class="order-foot">
                        @if ($canCancel ?? false)
                            <details class="cancel" @if (session('error') || $errors->any()) open @endif>
                                <summary class="btn btn--secondary cancel__open">Cancel this order</summary>
                                <form class="cancel__form" method="POST" action="{{ route('account.orders.cancel', $order) }}" aria-label="Cancel order #{{ $order->id }}">
                                    @csrf
                                    <div class="field">
                                        <label class="field__label" for="cancel-reason">Reason <span class="muted">(optional)</span></label>
                                        <input class="input" id="cancel-reason" type="text" name="reason" maxlength="500" value="{{ old('reason') }}">
                                    </div>
                                    <div class="cancel__acts">
                                        <button type="submit" class="btn btn--danger">Yes, cancel order #{{ $order->id }}</button>
                                        <button type="button" class="text-btn" data-close-details>Keep it</button>
                                    </div>
                                </form>
                            </details>
                        @endif
                        <p><a class="link-arrow link-arrow--back" href="{{ route('account.orders') }}"><svg class="icon icon--sm" aria-hidden="true"><use href="#i-arrow-left"></use></svg><span>Back to your orders</span></a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-store-layout>
