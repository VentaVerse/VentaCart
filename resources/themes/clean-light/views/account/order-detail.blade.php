<x-store-layout title="Order #{{ $order->id }}">
    <div class="container-store py-10">
        <h1 class="section-title reveal">ORDER #{{ $order->id }}</h1>

        <div class="account-layout">
            @include('account.partials.sidebar')

            <div class="space-y-6">
                {{-- Order Summary Header --}}
                <div class="account-card reveal">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <span class="account-label">Date</span>
                            <p class="text-sm text-[var(--color-text-heading)]">{{ $order->created_at->format('M d, Y') }}</p>
                        </div>
                        <div>
                            <span class="account-label">Status</span>
                            @php $statusName = $order->status?->name ?? 'Pending'; @endphp
                            <p><span class="account-status-badge account-status-{{ strtolower($statusName) }}">{{ $statusName }}</span></p>
                        </div>
                        <div>
                            <span class="account-label">Payment</span>
                            <p class="text-sm text-[var(--color-text-muted)]">{{ $order->payment_method_label ?: 'N/A' }}</p>
                        </div>
                        <div>
                            <span class="account-label">Total</span>
                            <p class="text-lg text-[var(--color-text-heading)]" class="font-heading">{{ $defaultCurrency->symbol ?? '₱' }}{{ number_format($order->total, 2) }}</p>
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
                                    <div class="text-sm text-[var(--color-text-muted)] space-y-0.5">
                                        <p class="text-[var(--color-text-heading)]">{{ $order->shipping_first_name }} {{ $order->shipping_last_name }}</p>
                                        <p>{{ $order->shipping_address_1 }}</p>
                                        @if ($order->shipping_barangay) <p>{{ $order->shipping_barangay }}</p> @endif
                                        @if ($order->shipping_district) <p>{{ $order->shipping_district }}</p> @endif
                                        <p>{{ $order->shipping_city }}@if ($order->shipping_state), {{ $order->shipping_state }}@endif @if ($order->shipping_postcode) {{ $order->shipping_postcode }}@endif</p>
                                        @if ($order->shipping_country_name ?? $order->shippingCountry?->name) <p>{{ $order->shipping_country_name ?? $order->shippingCountry?->name }}</p> @endif
                                        @if ($order->shipping_phone) <p class="mt-1">{{ $order->shipping_phone }}</p> @endif
                                    </div>
                                </div>
                            @endif

                            {{-- Billing --}}
                            <div>
                                <h2 class="account-section-title">Bill To</h2>
                                @if ($order->billing_first_name)
                                    <div class="text-sm text-[var(--color-text-muted)] space-y-0.5">
                                        <p class="text-[var(--color-text-heading)]">{{ $order->billing_first_name }} {{ $order->billing_last_name }}</p>
                                        <p>{{ $order->billing_address_1 }}</p>
                                        @if ($order->billing_barangay) <p>{{ $order->billing_barangay }}</p> @endif
                                        @if ($order->billing_district) <p>{{ $order->billing_district }}</p> @endif
                                        <p>{{ $order->billing_city }}@if ($order->billing_state), {{ $order->billing_state }}@endif @if ($order->billing_postcode) {{ $order->billing_postcode }}@endif</p>
                                        @if ($order->billing_country_name ?? $order->billingCountry?->name) <p>{{ $order->billing_country_name ?? $order->billingCountry?->name }}</p> @endif
                                        @if ($order->billing_phone) <p class="mt-1">{{ $order->billing_phone }}</p> @endif
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
                        <p class="text-sm text-[var(--color-text-muted)]">
                            @if ($order->shippingCourier)
                                {{ $order->shippingCourier->name }}:
                            @endif
                            @if ($order->tracking_link)
                                <a href="{{ $order->tracking_link }}" target="_blank" class="text-[var(--color-primary)] hover:underline">{{ $order->tracking_number }}</a>
                            @else
                                <span class="text-[var(--color-text-heading)]">{{ $order->tracking_number }}</span>
                            @endif
                        </p>
                    </div>
                @endif

                {{-- Order Items --}}
                <div class="account-card reveal">
                    <h2 class="account-section-title">Items</h2>
                    <div class="overflow-x-auto">
                        <table class="account-table w-full" style="table-layout: fixed;">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th style="width: 5rem; text-align: right;">Price</th>
                                    <th style="width: 3rem; text-align: center;">Qty</th>
                                    <th style="width: 6rem; text-align: right;">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($order->products as $item)
                                    <tr>
                                        <td>
                                            <span class="text-[var(--color-text-heading)]">{{ $item->name }}</span>
                                            @if ($item->variant_label)
                                                <span class="block text-xs text-[var(--color-text)]">{{ $item->variant_label }}</span>
                                            @endif
                                        </td>
                                        <td style="text-align: right;" class="font-heading">{{ $defaultCurrency->symbol ?? '₱' }}{{ number_format($item->price, 2) }}</td>
                                        <td style="text-align: center;">{{ $item->quantity }}</td>
                                        <td style="text-align: right;" class="font-heading">{{ $defaultCurrency->symbol ?? '₱' }}{{ number_format($item->price * $item->quantity, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 pt-4 border-t border-[#2A2A2A] space-y-2 text-sm text-right">
                        <div class="flex justify-end gap-8">
                            <span class="text-[var(--color-text)]">Subtotal</span>
                            <span class="text-[var(--color-text-heading)] w-28" class="font-heading">{{ $defaultCurrency->symbol ?? '₱' }}{{ number_format($order->subtotal, 2) }}</span>
                        </div>
                        @if ($order->shipping_cost > 0 || $order->courier_label)
                            <div class="flex justify-end gap-8">
                                <span class="text-[var(--color-text)]">Shipping @if ($order->courier_label)<span class="text-[var(--color-text-muted)]">({{ $order->courier_label }})</span>@endif</span>
                                <span class="text-[var(--color-text-heading)] w-28" class="font-heading">
                                    @if ($order->shipping_cost > 0)
                                        {{ $defaultCurrency->symbol ?? '₱' }}{{ number_format($order->shipping_cost, 2) }}
                                    @else
                                        <span class="text-emerald-500">FREE</span>
                                    @endif
                                </span>
                            </div>
                        @endif
                        <div class="flex justify-end gap-8 pt-2 border-t border-[#2A2A2A]">
                            <span class="text-[var(--color-text-heading)]" class="font-heading-wide">TOTAL</span>
                            <span class="text-lg text-[var(--color-text-heading)] w-28" class="font-heading">{{ $defaultCurrency->symbol ?? '₱' }}{{ number_format($order->total, 2) }}</span>
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
                                        @if ($history->status)
                                            <span class="inline-block px-2 py-0.5 rounded text-[11px] font-semibold bg-[#2A2A2A] text-[var(--color-text-muted)]">{{ $history->status->name }}</span>
                                        @endif
                                        @if ($history->comment)
                                            <p class="text-[var(--color-text-muted)] mt-0.5">{{ strip_tags($history->comment) }}</p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <a href="{{ route('account.orders') }}" class="text-sm text-[var(--color-primary)] hover:underline">&larr; Back to Orders</a>
            </div>
        </div>
    </div>
</x-store-layout>
