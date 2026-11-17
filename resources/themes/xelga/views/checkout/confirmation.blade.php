{{--
    Order placed: core's order with its lines and totals in the store's
    currency, the delivery address, the status, and bank transfer
    instructions when the customer chose to pay by bank.
--}}
@php
    app(\Themes\Xelga\Rendering\Storefront::class)->page('checkout');
    $statusName = $order->order_status_name ?? 'Pending';
    $bankAcct = null;
    if (is_string($order->payment_method) && preg_match('/^bank_transfer_(\d+)$/', $order->payment_method, $bankMatch)) {
        $bankAcct = class_exists(\Extensions\PaymentBankTransfer\Models\BankAccount::class)
            ? \Extensions\PaymentBankTransfer\Models\BankAccount::find((int) $bankMatch[1])
            : null;
    }
@endphp
<x-store-layout title="Order placed">
    @include('xelga::partials.fixed.page-assets', ['checkout' => true])
    <div class="s fixed-page scheme-1" data-fixed-page="confirmation">
        <div class="s__inner">
            <header class="done-head">
                <span class="done-head__icon" aria-hidden="true"><svg class="icon" aria-hidden="true"><use href="#i-check"></use></svg></span>
                <h1 class="co-title">Thank you, your order is placed</h1>
                <p class="muted">Order <strong class="tabular">#{{ $order->id }}</strong>. We email you as it moves along.</p>
            </header>
            <div class="co">
                <div class="co__main">
                    <dl class="facts">
                        <div><dt>Status</dt><dd>@include('xelga::partials.fixed.status', ['name' => $statusName, 'tone' => $order->status?->tone])</dd></div>
                        <div><dt>Payment</dt><dd>{{ $order->payment_method_label ?: 'Not recorded' }}</dd></div>
                        @if ($order->courier_label)
                            <div><dt>Delivery</dt><dd>{{ $order->courier_label }}</dd></div>
                        @endif
                    </dl>
                    @if ($bankInstructions || ($bankAcct && $bankAcct->qr_image))
                        <section class="acct__block bank" aria-labelledby="bank-title">
                            <h2 class="acct__subtitle" id="bank-title">How to pay by bank transfer</h2>
                            @if ($bankAcct && $bankAcct->qr_image)
                                <img class="bank__qr" src="{{ storage_url($bankAcct->qr_image) }}" alt="QR code for {{ $bankAcct->bank_name }}" width="280" height="280" loading="lazy">
                            @endif
                            @if ($bankInstructions)
                                <pre class="bank__text">{{ $bankInstructions }}</pre>
                            @endif
                        </section>
                    @endif
                    @if ($order->shipping_address_1)
                        <section class="acct__block" aria-labelledby="ship-title">
                            <h2 class="acct__subtitle" id="ship-title">Delivering to</h2>
                            @include('xelga::partials.fixed.address', ['a' => [
                                'first_name' => $order->shipping_first_name, 'last_name' => $order->shipping_last_name,
                                'address_1' => $order->shipping_address_1, 'barangay' => $order->shipping_barangay,
                                'district' => $order->shipping_district, 'city' => $order->shipping_city,
                                'state' => $order->shipping_state, 'postcode' => $order->shipping_postcode,
                                'country' => $order->shipping_country_name ?? $order->shippingCountry?->name, 'phone' => null,
                            ]])
                        </section>
                    @endif
                    <div class="done-acts">
                        <a class="btn" href="{{ route('account.orders.show', $order) }}">View order details</a>
                        <a class="btn btn--secondary" href="{{ route('home') }}">Continue shopping</a>
                    </div>
                </div>
                <aside class="co__summary" aria-labelledby="sum-title">
                    <h2 class="co__subtitle" id="sum-title">Order summary</h2>
                    <ul class="lines">
                        @foreach ($order->products as $item)
                            <li class="line line--sm line--noimg">
                                <div class="line__text"><span>{{ $item->name }}</span>@if ($item->variant_label)<span class="muted">{{ $item->variant_label }}</span>@endif<span class="muted">Quantity {{ $item->quantity }}</span></div>
                                <span class="line__price tabular">{{ store_money($item->price * $item->quantity) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <dl class="totals">
                        <div><dt>Subtotal</dt><dd class="tabular">{{ store_money($order->subtotal) }}</dd></div>
                        <div><dt>Delivery @if ($order->courier_label)<span class="muted">({{ $order->courier_label }})</span>@endif</dt><dd class="tabular">{{ $order->shipping_cost > 0 ? store_money($order->shipping_cost) : 'Free' }}</dd></div>
                        @foreach (collect($order->total_lines ?? []) as $line)
                            @if (($line['amount'] ?? 0) != 0)
                                <div><dt>{{ $line['label'] }}</dt><dd class="tabular">{{ $line['amount'] < 0 ? '-' : '' }}{{ store_money(abs($line['amount'])) }}</dd></div>
                            @endif
                        @endforeach
                        <div class="totals__grand"><dt>Total</dt><dd class="tabular">{{ store_money($order->total) }}</dd></div>
                    </dl>
                </aside>
            </div>
        </div>
    </div>
</x-store-layout>
