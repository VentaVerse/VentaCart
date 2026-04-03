<x-store-layout title="Order Confirmed">
    <div class="container-store py-10">

        {{-- Success Icon --}}
        <div class="confirmation-icon reveal">
            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>

        {{-- Title --}}
        <h1 class="section-title reveal" style="margin-top: 1.5rem;">ORDER CONFIRMED</h1>
        <p class="confirmation-subtitle reveal">Thank you for your order!</p>
        <p class="confirmation-order-id reveal">Order #{{ $order->id }}</p>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-10">

            {{-- Order Summary Card --}}
            <div class="account-card reveal">
                <h2 class="checkout-section-title">ORDER SUMMARY</h2>

                <div class="space-y-3 mb-4">
                    @foreach ($order->products as $item)
                        <div class="flex items-start gap-3">
                            <div class="flex-1 min-w-0">
                                <div class="text-sm text-[#E8E8E8] leading-snug">{{ $item->name }}</div>
                                @if ($item->variant_label)
                                    <div class="text-xs text-[#555]">{{ $item->variant_label }}</div>
                                @endif
                                <div class="text-xs text-[#777]">Qty: {{ $item->quantity }}</div>
                            </div>
                            <div class="text-sm text-[#E8E8E8] flex-shrink-0" class="font-heading">
                                ₱{{ number_format($item->price * $item->quantity, 2) }}
                            </div>
                        </div>
                    @endforeach
                </div>

                <hr class="divider-glow my-4">

                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-[#888]">Subtotal</span>
                        <span class="text-[#E8E8E8]" class="font-heading">₱{{ number_format($order->subtotal, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-[#888]">Shipping ({{ $order->shipping_method }})</span>
                        <span class="font-heading">
                            @if ($order->shipping_cost > 0)
                                <span class="text-[#E8E8E8]">₱{{ number_format($order->shipping_cost, 2) }}</span>
                            @else
                                <span class="text-emerald-400">FREE</span>
                            @endif
                        </span>
                    </div>
                </div>

                <hr class="divider-glow my-4">

                <div class="flex justify-between">
                    <span class="text-[#E8E8E8]" class="font-heading-wide">TOTAL</span>
                    <span class="text-xl text-[#E8E8E8]" class="font-heading">₱{{ number_format($order->total, 2) }}</span>
                </div>
            </div>

            <div class="space-y-6">

                {{-- Shipping Address Card --}}
                @if ($order->shipping_address_1)
                    <div class="account-card reveal">
                        <h2 class="checkout-section-title">SHIPPING ADDRESS</h2>
                        <div class="text-sm text-[#999] space-y-0.5">
                            <p class="text-[#E8E8E8]">{{ $order->shipping_first_name }} {{ $order->shipping_last_name }}</p>
                            <p>{{ $order->shipping_address_1 }}</p>
                            @if ($order->shipping_barangay)
                                <p>{{ $order->shipping_barangay }}</p>
                            @endif
                            @if ($order->shipping_district)
                                <p>{{ $order->shipping_district }}</p>
                            @endif
                            <p>
                                {{ $order->shipping_city }}@if ($order->shipping_state), {{ $order->shipping_state }}@endif
                                @if ($order->shipping_postcode) {{ $order->shipping_postcode }}@endif
                            </p>
                            <p>{{ $order->shipping_country_code }}</p>
                        </div>
                    </div>
                @endif

                {{-- Payment & Status Card --}}
                @php
                    $statusName = $order->status?->name ?? 'Pending';
                    $bankAcct = null;
                    if (str_starts_with($order->payment_method, 'bank_transfer') && preg_match('/^bank_transfer_(\d+)$/', $order->payment_method, $m)) {
                        $bankAcct = \App\Models\BankAccount::find((int) $m[1]);
                    }
                @endphp
                <div class="account-card reveal">
                    <div class="text-center mb-4">
                        <p class="text-xs text-[#666] uppercase tracking-widest mb-1">Order Status</p>
                        <p class="text-2xl font-bold text-[var(--color-primary)] tracking-wide">{{ strtoupper($statusName) }}</p>
                    </div>
                    <hr class="divider-glow my-4">
                    <div class="text-sm">
                        <div class="flex justify-between py-1.5">
                            <span class="text-[#888]">Payment</span>
                            <span class="text-[#E8E8E8] font-medium">{{ $order->payment_method_label }}</span>
                        </div>
                        @if ($order->shipping_method)
                            <div class="flex justify-between py-1.5">
                                <span class="text-[#888]">Shipping</span>
                                <span class="text-[#E8E8E8]">{{ $order->shipping_method }}</span>
                            </div>
                        @endif
                    </div>

                    @if ($bankInstructions || ($bankAcct && $bankAcct->qr_image))
                        <div class="confirmation-bank-instructions">
                            <h3 class="confirmation-bank-title">BANK TRANSFER INSTRUCTIONS</h3>
                            @if ($bankAcct && $bankAcct->qr_image)
                                <div class="flex justify-center my-4">
                                    <img src="{{ storage_url($bankAcct->qr_image) }}" alt="QR Code — {{ $bankAcct->bank_name }}" class="max-w-[300px] rounded-lg border border-[#2A2A2A]">
                                </div>
                            @endif
                            @if ($bankInstructions)
                                <pre class="confirmation-bank-text">{{ $bankInstructions }}</pre>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Action Buttons --}}
        <div class="confirmation-actions reveal">
            <a href="{{ route('account.orders.show', $order) }}" class="btn-checkout">VIEW ORDER DETAILS</a>
            <a href="{{ route('home') }}" class="confirmation-continue-link">Continue Shopping</a>
        </div>

    </div>
</x-store-layout>
