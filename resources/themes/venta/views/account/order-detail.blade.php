<x-store-layout title="Order #{{ $order->id }}">
    <x-storefront.columns>
    <x-storefront.region name="content_top" />
    <div class="container-store py-10">
        <h1 class="section-title reveal">ORDER #{{ $order->id }}</h1>

        <div class="account-layout">
            @include('account.partials.sidebar')

            <div class="space-y-6">
                @if (session('success'))
                    <div class="account-flash-success reveal">{{ session('success') }}</div>
                @endif

                {{-- Order Summary Header --}}
                <div class="account-card reveal">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <span class="account-label">Date</span>
                            <p class="text-sm text-[var(--color-text-heading)]">{{ $order->created_at->format('M d, Y') }}</p>
                        </div>
                        <div>
                            <span class="account-label">Status</span>
                            @php $statusName = $order->order_status_name ?? 'Pending'; @endphp
                            <p><span class="account-status-badge account-status-badge--{{ $order->status?->tone ?? 'neutral' }}">{{ $statusName }}</span></p>
                        </div>
                        <div>
                            <span class="account-label">Payment</span>
                            <p class="text-sm text-[var(--color-text)]">{{ $order->payment_method_label ?: 'N/A' }}</p>
                        </div>
                        <div>
                            <span class="account-label">Total</span>
                            <p class="text-lg text-[var(--color-text-heading)] font-heading">{{ $defaultCurrency->symbol ?? '₱' }}{{ number_format($order->total, 2) }}</p>
                        </div>
                    </div>
                </div>

                {{-- Addresses --}}
                @if ($order->shipping_address_1 || $order->billing_address_1)
                    <div class="account-card reveal">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {{-- Shipping --}}
                            @if ($order->shipping_address_1)
                                <div>
                                    <h2 class="account-section-title">Ship To</h2>
                                    <div class="text-sm text-[var(--color-text)] space-y-0.5">
                                        <p class="text-[var(--color-text-heading)]">{{ $order->shipping_first_name }} {{ $order->shipping_last_name }}</p>
                                        <p>{{ $order->shipping_address_1 }}</p>
                                        @if ($order->shipping_barangay) <p>{{ $order->shipping_barangay }}</p> @endif
                                        @if ($order->shipping_district) <p>{{ $order->shipping_district }}</p> @endif
                                        <p>{{ $order->shipping_city }}@if ($order->shipping_state), {{ $order->shipping_state }}@endif @if ($order->shipping_postcode) {{ $order->shipping_postcode }}@endif</p>
                                        @if ($order->shipping_country_name ?? $order->shippingCountry?->name) <p>{{ $order->shipping_country_name ?? $order->shippingCountry?->name }}</p> @endif
                                        @if ($order->shipping_phone_display) <p class="mt-1">{{ $order->shipping_phone_display }}</p> @endif
                                    </div>
                                </div>
                            @endif

                            {{-- Billing --}}
                            <div>
                                <h2 class="account-section-title">Bill To</h2>
                                @if ($order->billing_first_name)
                                    <div class="text-sm text-[var(--color-text)] space-y-0.5">
                                        <p class="text-[var(--color-text-heading)]">{{ $order->billing_first_name }} {{ $order->billing_last_name }}</p>
                                        <p>{{ $order->billing_address_1 }}</p>
                                        @if ($order->billing_barangay) <p>{{ $order->billing_barangay }}</p> @endif
                                        @if ($order->billing_district) <p>{{ $order->billing_district }}</p> @endif
                                        <p>{{ $order->billing_city }}@if ($order->billing_state), {{ $order->billing_state }}@endif @if ($order->billing_postcode) {{ $order->billing_postcode }}@endif</p>
                                        @if ($order->billing_country_name ?? $order->billingCountry?->name) <p>{{ $order->billing_country_name ?? $order->billingCountry?->name }}</p> @endif
                                        @if ($order->billing_phone_display) <p class="mt-1">{{ $order->billing_phone_display }}</p> @endif
                                    </div>
                                @else
                                    <p class="text-sm text-[var(--color-text)] italic">Same as shipping</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Tracking --}}
                @if ($order->tracking_number)
                    <div class="account-card reveal">
                        <h2 class="account-section-title">Tracking</h2>
                        <p class="text-sm text-[var(--color-text)]">
                            @if ($order->shipping_courier_name)
                                {{ $order->shipping_courier_name }}:
                            @endif
                            @if ($order->tracking_link)
                                <a href="{{ $order->tracking_link }}" target="_blank" rel="noopener noreferrer" class="account-link">{{ $order->tracking_number }}<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5h5m0 0v5m0-5L10 14M19 14v4a1 1 0 01-1 1H6a1 1 0 01-1-1V6a1 1 0 011-1h4"/></svg><span class="sr-only">(opens in a new tab)</span></a>
                            @else
                                <span class="text-[var(--color-text-heading)]">{{ $order->tracking_number }}</span>
                            @endif
                            <span x-data="{ copied: false }" class="inline-flex">
                                <button type="button" class="account-copy" :class="copied && 'is-copied'" :aria-label="copied ? 'Copied' : 'Copy tracking number'"
                                        @click="navigator.clipboard?.writeText(@js($order->tracking_number)).then(() => { copied = true; setTimeout(() => copied = false, 2000) })">
                                    <svg x-show="!copied" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a1 1 0 011-1h10"/></svg>
                                    <svg x-show="copied" x-cloak class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
                                </button>
                            </span>
                        </p>
                    </div>
                @endif

                {{-- Order Items --}}
                <div class="account-card reveal">
                    <h2 class="account-section-title">Items</h2>
                    <ul class="account-items" aria-label="Items">
                        <li class="account-items__head" aria-hidden="true">
                            <span>Product</span><span>Price</span><span>Qty</span><span>Total</span>
                        </li>
                        @foreach ($order->products as $item)
                            <li class="account-item">
                                <div class="account-item__name">
                                    <span class="text-[var(--color-text-heading)]">{{ $item->name }}</span>
                                    @if ($item->variant_label)
                                        <span class="block text-xs text-[var(--color-text)]">{{ $item->variant_label }}</span>
                                    @endif
                                </div>
                                <span class="account-item__price font-heading">{{ $defaultCurrency->symbol ?? '₱' }}{{ number_format($item->price, 2) }}</span>
                                <span class="account-item__qty"><span class="account-item__x" aria-hidden="true">&times;</span>{{ $item->quantity }}</span>
                                <span class="account-item__total font-heading">{{ $defaultCurrency->symbol ?? '₱' }}{{ number_format($item->price * $item->quantity, 2) }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-4 pt-4 border-t border-[var(--color-border)] space-y-2 text-sm text-right">
                        <div class="flex justify-end gap-8">
                            <span class="text-[var(--color-text)]">Subtotal</span>
                            <span class="text-[var(--color-text-heading)] w-28 font-heading">{{ $defaultCurrency->symbol ?? '₱' }}{{ number_format($order->subtotal, 2) }}</span>
                        </div>
                        @if ($order->shipping_cost > 0 || $order->courier_label)
                            <div class="flex justify-end gap-8">
                                <span class="text-[var(--color-text)]">Shipping @if ($order->courier_label)<span class="text-[var(--color-text)]">({{ $order->courier_label }})</span>@endif</span>
                                <span class="text-[var(--color-text-heading)] w-28 font-heading">
                                    @if ($order->shipping_cost > 0)
                                        {{ $defaultCurrency->symbol ?? '₱' }}{{ number_format($order->shipping_cost, 2) }}
                                    @else
                                        <span class="account-free">FREE</span>
                                    @endif
                                </span>
                            </div>
                        @endif
                        <div class="flex justify-end gap-8 pt-2 border-t border-[var(--color-border)]">
                            <span class="text-[var(--color-text-heading)] font-heading-wide">TOTAL</span>
                            <span class="text-lg text-[var(--color-text-heading)] w-28 font-heading">{{ $defaultCurrency->symbol ?? '₱' }}{{ number_format($order->total, 2) }}</span>
                        </div>
                    </div>
                </div>

                {{-- Order History Timeline --}}
                @if ($order->histories->isNotEmpty())
                    <div class="account-card reveal">
                        <h2 class="account-section-title">Order History</h2>
                        <div class="space-y-4">
                            @foreach ($order->histories->sortByDesc('created_at') as $history)
                                <div class="flex items-start gap-3 text-sm">
                                    <div class="text-[var(--color-text)] whitespace-nowrap pt-0.5">{{ $history->created_at->format('M d, Y H:i') }}</div>
                                    <div>
                                        @if ($history->order_status_name)
                                            <span class="account-status-badge account-status-badge--sm account-status-badge--{{ $history->status?->tone ?? 'neutral' }}">{{ $history->order_status_name }}</span>
                                        @endif
                                        {{-- A comment reaches the customer only when it was written for
                                             them (notify_customer): the store's own notes and the courier
                                             feed's raw vocabulary stay in the admin. --}}
                                        @if ($history->comment && $history->notify_customer)
                                            <p class="text-[var(--color-text)] mt-1">{{ strip_tags($history->comment) }}</p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- The page foot: the way back, and the one destructive act,
                     offered only while the status allows it (Settings, Checkout,
                     Cancellation by the customer). One confirm step with an
                     optional reason; after it the button is gone and the
                     status at the top says where the order went. --}}
                <div class="account-order-foot" x-data="{ open: {{ session('error') || $errors->any() ? 'true' : 'false' }} }" @keydown.escape.window="open = false">
                    @if (session('error'))
                        <div class="account-flash-error" role="alert">{{ session('error') }}</div>
                    @endif
                    @if ($canCancel ?? false)
                        <form method="POST" action="{{ route('account.orders.cancel', $order) }}" id="cancel-panel" class="account-cancel-panel" role="region" aria-label="Cancel order #{{ $order->id }}" x-show="open" x-cloak x-transition.opacity.duration.200ms>
                            @csrf
                            <label class="account-label" for="cancel-reason">Reason (optional)</label>
                            <input id="cancel-reason" x-ref="reason" type="text" name="reason" maxlength="500" class="account-input w-full" value="{{ old('reason') }}">
                            <div class="account-cancel-panel__acts">
                                <button type="submit" class="account-btn-danger account-btn-danger--solid">YES, CANCEL ORDER #{{ $order->id }}</button>
                                <button type="button" class="account-btn-secondary" @click="open = false">KEEP IT</button>
                            </div>
                        </form>
                    @endif
                    <div class="account-order-foot__row">
                        <a href="{{ route('account.orders') }}" class="account-link">&larr; Back to Orders</a>
                        @if ($canCancel ?? false)
                            <button type="button" class="account-btn-danger" x-show="!open" :aria-expanded="open" aria-controls="cancel-panel" @click="open = true; $nextTick(() => $refs.reason.focus())">CANCEL THIS ORDER</button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    <x-storefront.region name="content_bottom" />
    </x-storefront.columns>
</x-store-layout>
