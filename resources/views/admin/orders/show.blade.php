@php
    $currency = $defaultCurrency->symbol ?? '₱';
    $statusColors = [
        'Pending' => 'bg-amber-100 text-amber-800 ring-amber-300',
        'Unpaid' => 'bg-amber-100 text-amber-800 ring-amber-300',
        'Processing' => 'bg-blue-100 text-blue-800 ring-blue-300',
        'Payment Received' => 'bg-teal-100 text-teal-800 ring-teal-300',
        'Shipped' => 'bg-indigo-100 text-indigo-800 ring-indigo-300',
        'Order Being Shipped' => 'bg-indigo-100 text-indigo-800 ring-indigo-300',
        'Delivered' => 'bg-emerald-100 text-emerald-800 ring-emerald-300',
        'Complete' => 'bg-emerald-100 text-emerald-800 ring-emerald-300',
        'Canceled' => 'bg-red-100 text-red-800 ring-red-300',
        'Refunded' => 'bg-gray-200 text-gray-700 ring-gray-300',
        'Failed' => 'bg-red-100 text-red-800 ring-red-300',
        'Expired' => 'bg-gray-200 text-gray-600 ring-gray-300',
    ];
    $statusClass = $statusColors[$order->status?->name] ?? 'bg-gray-100 text-gray-700 ring-gray-300';
@endphp

<x-admin-layout>
    <x-slot name="header">
        <span class="text-gray-400 font-normal">#{{ $order->id }}</span>
        {{ $order->first_name }} {{ $order->last_name }}
    </x-slot>
    <x-slot name="headerActions">
        <a href="{{ route('admin.orders.edit', $order) }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors mr-2">
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            Edit
        </a>
        <a href="{{ route('admin.orders.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors">
            Back
        </a>
    </x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- LEFT COLUMN --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- 1. ORDER OVERVIEW --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-5 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-bold uppercase tracking-wide ring-1 ring-inset {{ $statusClass }}">
                            {{ $order->status?->name ?? 'No Status' }}
                        </span>
                        <span class="text-2xl font-bold text-gray-900">{{ $currency }}{{ number_format($order->total, 2) }}</span>
                    </div>
                </div>
                <div class="border-t border-gray-100 px-6 py-3 flex flex-wrap gap-x-8 gap-y-2 text-sm text-gray-600 bg-gray-50/50">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span>{{ $order->created_at->format('M d, Y · g:i A') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        <span>{{ $order->payment_method_label }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        <span>{{ $order->shipping_method ?? '—' }}</span>
                    </div>
                    @if ($order->payment_reference)
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
                            <span class="font-mono text-xs">{{ $order->payment_reference }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- 2. CUSTOMER --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Customer</h3>
                    @if ($order->customer)
                        <a href="{{ route('admin.customers.edit', $order->customer) }}" class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                            View Profile
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                    @endif
                </div>
                <div class="px-6 py-4 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold text-sm">
                        {{ strtoupper(substr($order->first_name, 0, 1) . substr($order->last_name, 0, 1)) }}
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-semibold text-gray-900">{{ $order->first_name }} {{ $order->last_name }}</p>
                            @if ($order->customer?->customerGroup)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-gray-100 text-gray-600">{{ $order->customer->customerGroup->name }}</span>
                            @endif
                        </div>
                        <a href="mailto:{{ $order->email }}" class="text-sm text-indigo-600 hover:text-indigo-800 hover:underline">{{ $order->email }}</a>
                    </div>
                </div>
            </div>

            {{-- 3. ADDRESSES --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Addresses</h3>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-gray-100">
                    {{-- Shipping --}}
                    <div class="px-6 py-4">
                        <div class="flex items-center gap-2 mb-3">
                            <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/></svg>
                            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Ship To</h4>
                        </div>
                        <div class="text-sm space-y-0.5">
                            <p class="font-medium text-gray-900">{{ $order->shipping_first_name }} {{ $order->shipping_last_name }}</p>
                            <p class="text-gray-600">{{ $order->shipping_address_1 }}</p>
                            @if ($order->shipping_barangay) <p class="text-gray-600">{{ $order->shipping_barangay }}</p> @endif
                            @if ($order->shipping_district) <p class="text-gray-600">{{ $order->shipping_district }}</p> @endif
                            <p class="text-gray-600">{{ $order->shipping_city }}@if ($order->shipping_state), {{ $order->shipping_state }}@endif {{ $order->shipping_postcode }}</p>
                            @if ($order->shipping_country_code) <p class="text-gray-400 text-xs">{{ $order->shipping_country_code }}</p> @endif
                            @if ($order->shipping_phone || $order->phone)
                                <p class="text-gray-500 mt-1.5">{{ $order->shipping_phone ?: $order->phone }}</p>
                            @endif
                        </div>
                    </div>

                    {{-- Billing --}}
                    <div class="px-6 py-4">
                        <div class="flex items-center gap-2 mb-3">
                            <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg>
                            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Bill To</h4>
                        </div>
                        @if ($order->billing_first_name)
                            <div class="text-sm space-y-0.5">
                                <p class="font-medium text-gray-900">{{ $order->billing_first_name }} {{ $order->billing_last_name }}</p>
                                <p class="text-gray-600">{{ $order->billing_address_1 }}</p>
                                @if ($order->billing_barangay) <p class="text-gray-600">{{ $order->billing_barangay }}</p> @endif
                                @if ($order->billing_district) <p class="text-gray-600">{{ $order->billing_district }}</p> @endif
                                <p class="text-gray-600">{{ $order->billing_city }}@if ($order->billing_state), {{ $order->billing_state }}@endif {{ $order->billing_postcode }}</p>
                                @if ($order->billing_country_code) <p class="text-gray-400 text-xs">{{ $order->billing_country_code }}</p> @endif
                                @if ($order->billing_phone)
                                    <p class="text-gray-500 mt-1.5">{{ $order->billing_phone }}</p>
                                @endif
                            </div>
                        @else
                            <p class="text-sm text-gray-400 italic">Same as shipping</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- 4. PRODUCTS --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Products</h3>
                </div>
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b border-gray-100 text-[11px] font-semibold text-gray-400 uppercase tracking-wider">
                            <th class="px-6 py-3 text-left">Item</th>
                            <th class="px-4 py-3 text-right w-24">Price</th>
                            <th class="px-4 py-3 text-center w-16">Qty</th>
                            <th class="px-6 py-3 text-right w-28">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($order->products as $item)
                            <tr class="hover:bg-gray-50/50">
                                <td class="px-6 py-3">
                                    <p class="text-sm font-medium text-gray-900">{{ $item->name }}</p>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        @if ($item->variant_label)
                                            <span class="text-[11px] text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded font-medium">{{ $item->variant_label }}</span>
                                        @endif
                                        @if ($item->sku)
                                            <span class="text-[11px] text-gray-400">{{ $item->sku }}</span>
                                        @endif
                                        @if ($item->is_custom)
                                            <span class="text-[11px] text-amber-600 bg-amber-50 px-1.5 py-0.5 rounded font-medium">Custom</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 text-right">{{ $currency }}{{ number_format($item->price, 2) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 text-center">{{ $item->quantity }}</td>
                                <td class="px-6 py-3 text-sm font-semibold text-gray-900 text-right">{{ $currency }}{{ number_format($item->price * $item->quantity, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="border-t border-gray-200 bg-gray-50/70 px-6 py-3">
                    <div class="flex justify-end">
                        <div class="w-64 space-y-1.5">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Subtotal</span>
                                <span class="text-gray-700">{{ $currency }}{{ number_format($order->subtotal, 2) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Shipping</span>
                                <span class="text-gray-700">{{ $order->shipping_cost > 0 ? $currency . number_format($order->shipping_cost, 2) : 'Free' }}</span>
                            </div>
                            <div class="flex justify-between text-sm font-bold pt-2 border-t border-gray-200">
                                <span class="text-gray-800">Total</span>
                                <span class="text-gray-900 text-base">{{ $currency }}{{ number_format($order->total, 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                @if ($order->comment)
                    <div class="px-6 py-3 bg-amber-50 border-t border-amber-100 text-sm">
                        <span class="font-medium text-amber-800">Customer Note:</span>
                        <span class="text-amber-700">{{ $order->comment }}</span>
                    </div>
                @endif
            </div>

            {{-- 5. SHIPPING & TRACKING --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Shipping & Tracking</h3>
                </div>
                <div class="p-6">
                    @if ($order->shipment)
                        @php $shipment = $order->shipment; @endphp
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm mb-5">
                            <div class="bg-gray-50 rounded-lg p-3">
                                <span class="text-[11px] font-semibold text-gray-400 uppercase block mb-1">Courier</span>
                                <span class="font-medium text-gray-900">{{ $shipment->dph_partner_name }}</span>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <span class="text-[11px] font-semibold text-gray-400 uppercase block mb-1">Status</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                    {{ $shipment->dph_status === 'DONE' ? 'bg-emerald-100 text-emerald-800' : '' }}
                                    {{ in_array($shipment->dph_status, ['CANCEL', 'FAILED_PICKUP', 'FAILED_DELIVERY']) ? 'bg-red-100 text-red-800' : '' }}
                                    {{ !in_array($shipment->dph_status, ['DONE', 'CANCEL', 'FAILED_PICKUP', 'FAILED_DELIVERY']) ? 'bg-blue-100 text-blue-800' : '' }}
                                ">{{ $shipment->dph_status ?? 'PENDING' }}</span>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <span class="text-[11px] font-semibold text-gray-400 uppercase block mb-1">Tracking</span>
                                @if ($shipment->tracking_number)
                                    @if ($shipment->tracking_url)
                                        <a href="{{ $shipment->tracking_url }}" target="_blank" class="text-indigo-600 hover:underline font-medium text-sm">{{ $shipment->tracking_number }}</a>
                                    @else
                                        <span class="font-medium">{{ $shipment->tracking_number }}</span>
                                    @endif
                                @else
                                    <span class="text-gray-400 text-xs">Awaiting</span>
                                @endif
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <span class="text-[11px] font-semibold text-gray-400 uppercase block mb-1">Fee</span>
                                <span class="font-medium text-gray-900">{{ $currency }}{{ number_format($shipment->final_fee ?: $shipment->approximate_fee, 2) }}</span>
                            </div>
                        </div>

                        @if ($shipment->shareable_link)
                            <div class="p-3 bg-indigo-50 rounded-lg mb-4 flex items-center gap-2">
                                <svg class="w-4 h-4 text-indigo-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                <a href="{{ $shipment->shareable_link }}" target="_blank" class="text-sm text-indigo-600 hover:underline truncate">{{ $shipment->shareable_link }}</a>
                            </div>
                        @endif

                        @if ($shipment->events->isNotEmpty())
                            <div class="space-y-2 mt-4">
                                @foreach ($shipment->events as $event)
                                    <div class="flex items-start gap-3 text-sm pl-2">
                                        <div class="w-1.5 h-1.5 rounded-full bg-indigo-400 mt-2 flex-shrink-0"></div>
                                        <div>
                                            <span class="font-medium">{{ $event->status }}</span>
                                            @if ($event->sub_status) <span class="text-gray-400">({{ $event->sub_status }})</span> @endif
                                            @if ($event->remarks) <span class="text-gray-500">— {{ $event->remarks }}</span> @endif
                                            <span class="text-gray-400 text-xs block">{{ $event->created_at->format('M d, Y g:i A') }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if (!in_array($shipment->dph_status, ['DONE_PICKUP', 'ARRIVED_AT_HUB', 'START_DELIVERY', 'DONE', 'CANCEL', 'RETURNED']))
                            <div class="mt-5 pt-4 border-t border-gray-100" x-data="{ cancelling: false }">
                                <button type="button" @click="if(confirm('Cancel this shipment?')) { cancelling = true; fetch('{{ route('admin.orders.shipment.cancel', $order) }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json' } }).then(r => r.json()).then(d => { if(d.success) location.reload(); else { alert(d.error || 'Failed'); cancelling = false; } }).catch(() => { alert('Request failed'); cancelling = false; }); }"
                                    :disabled="cancelling"
                                    class="px-4 py-2 bg-red-50 text-red-700 text-sm font-medium rounded-lg hover:bg-red-100 border border-red-200 disabled:opacity-50 transition-colors">
                                    <span x-show="!cancelling">Cancel Shipment</span>
                                    <span x-show="cancelling">Cancelling...</span>
                                </button>
                            </div>
                        @endif

                    @else
                        <form method="POST" action="{{ route('admin.orders.update-tracking', $order) }}" class="space-y-4">
                            @csrf
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Courier</label>
                                    <select name="shipping_courier_id" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">Select courier...</option>
                                        @foreach ($couriers as $courier)
                                            <option value="{{ $courier->id }}" {{ $order->shipping_courier_id == $courier->id ? 'selected' : '' }}>{{ $courier->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Tracking Number</label>
                                    <div class="flex gap-2">
                                        <input type="text" name="tracking_number" value="{{ $order->tracking_number }}"
                                            class="flex-1 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Enter tracking number">
                                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors flex-shrink-0">Save</button>
                                    </div>
                                </div>
                            </div>
                        </form>

                        @if ($order->tracking_number && $order->shippingCourier)
                            <div class="mt-4 flex items-center gap-2 bg-indigo-50 rounded-lg p-3 text-sm">
                                <svg class="w-4 h-4 text-indigo-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                <span class="font-medium text-indigo-800">{{ $order->shippingCourier->name }}</span>
                                <span class="text-indigo-600">{{ $order->tracking_number }}</span>
                            </div>
                        @endif

                        @if ($dphEnabled)
                            <div class="mt-6 pt-5 border-t border-gray-100" x-data="dphBooking()">
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="text-sm font-semibold text-gray-700">Book via Deliveries PH</h4>
                                    <button type="button" @click="fetchRates()" :disabled="loading"
                                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                                        <span x-show="!loading">Fetch Rates</span>
                                        <span x-show="loading">Loading...</span>
                                    </button>
                                </div>
                                <div x-show="error" class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4 text-sm text-red-700" x-text="error"></div>
                                <template x-if="rates.length > 0">
                                    <div>
                                        <div class="space-y-2 mb-4">
                                            <template x-for="(rate, idx) in rates" :key="idx">
                                                <label class="flex items-center justify-between p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors"
                                                    :class="selectedIdx === idx ? 'border-indigo-500 bg-indigo-50 shadow-sm' : 'border-gray-200'">
                                                    <span class="flex items-center gap-3">
                                                        <input type="radio" :value="idx" x-model.number="selectedIdx" class="text-indigo-600">
                                                        <span>
                                                            <span class="font-medium text-sm" x-text="rate.partner_name"></span>
                                                            <span class="text-gray-400 text-xs" x-text="'(' + rate.size + ')'"></span>
                                                            <template x-if="rate.estimated_days">
                                                                <span class="text-gray-400 text-xs ml-1" x-text="'~' + rate.estimated_days + 'd'"></span>
                                                            </template>
                                                        </span>
                                                    </span>
                                                    <span class="font-semibold text-sm" x-text="'{{ $currency }}' + rate.price.toFixed(2)"></span>
                                                </label>
                                            </template>
                                        </div>
                                        <button type="button" @click="bookShipment()" :disabled="selectedIdx === null || booking"
                                            class="px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 disabled:opacity-50 transition-colors">
                                            <span x-show="!booking">Confirm Booking</span>
                                            <span x-show="booking">Booking...</span>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        {{-- RIGHT COLUMN --}}
        <div class="space-y-6">

            {{-- UPDATE STATUS --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 bg-gray-50">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Update Status</h3>
                </div>
                <form method="POST" action="{{ route('admin.orders.history.store', $order) }}" class="p-5 space-y-3">
                    @csrf
                    <select name="order_status_id" required class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach ($statuses as $status)
                            <option value="{{ $status->id }}" {{ $order->order_status_id == $status->id ? 'selected' : '' }}>{{ $status->name }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="comment" placeholder="Add a comment..."
                        class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <div class="flex items-center justify-between pt-1">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="notify_customer" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-xs text-gray-500">Notify Customer</span>
                        </label>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700 transition-colors uppercase tracking-wide">
                            Update
                        </button>
                    </div>
                </form>
            </div>

            {{-- ORDER HISTORY --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 bg-gray-50">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">History</h3>
                </div>
                <div class="p-5">
                    @if ($order->histories->count() > 0)
                        <div class="relative">
                            <div class="absolute left-[7px] top-2 bottom-2 w-px bg-gray-200"></div>
                            <div class="space-y-5">
                                @foreach ($order->histories->sortByDesc('created_at') as $history)
                                    <div class="relative pl-7 group">
                                        <div class="absolute left-0 top-1 w-[15px] h-[15px] rounded-full border-2 {{ $loop->first ? 'border-indigo-500 bg-indigo-100' : 'border-gray-300 bg-white' }}"></div>
                                        <div class="flex items-start justify-between">
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold {{ $loop->first ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-700' }}">
                                                        {{ $history->status?->name ?? '—' }}
                                                    </span>
                                                    @if ($history->notify_customer)
                                                        <span class="text-[10px] text-indigo-500 font-medium">Notified</span>
                                                    @endif
                                                </div>
                                                @if ($history->comment)
                                                    <p class="text-sm text-gray-600 mt-1">{{ $history->comment }}</p>
                                                @endif
                                                <p class="text-[11px] text-gray-400 mt-1">
                                                    {{ $history->created_at->format('M d, Y g:i A') }}
                                                    @if ($history->createdByUser) &mdash; {{ $history->createdByUser->name }} @endif
                                                </p>
                                            </div>
                                            <form method="POST" action="{{ route('admin.orders.history.destroy', [$order, $history->id]) }}" onsubmit="return confirm('Delete?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-gray-200 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-opacity ml-2">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <p class="text-sm text-gray-400 text-center py-6">No history yet.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if ($dphEnabled && !$order->shipment)
        @push('scripts')
        <script>
            function dphBooking() {
                return {
                    rates: [], selectedIdx: null, loading: false, booking: false, error: '',
                    async fetchRates() {
                        this.loading = true; this.error = ''; this.rates = []; this.selectedIdx = null;
                        try {
                            const res = await fetch('{{ route("admin.orders.shipment.rates", $order) }}', { headers: { 'Accept': 'application/json' } });
                            const data = await res.json();
                            if (data.success) { this.rates = data.rates; if (!this.rates.length) this.error = 'No couriers available.'; }
                            else { this.error = data.error || 'Failed to fetch rates.'; }
                        } catch (e) { this.error = 'Network error.'; }
                        this.loading = false;
                    },
                    async bookShipment() {
                        if (this.selectedIdx === null) return;
                        this.booking = true; this.error = '';
                        const rate = this.rates[this.selectedIdx];
                        try {
                            const res = await fetch('{{ route("admin.orders.shipment.book", $order) }}', {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json' },
                                body: JSON.stringify({ partner_id: rate.partner_id, partner_name: rate.partner_name, service_type: rate.service_type, product_size: rate.size, approximate_fee: rate.price })
                            });
                            const data = await res.json();
                            if (data.success) location.reload(); else this.error = data.error || 'Booking failed.';
                        } catch (e) { this.error = 'Network error.'; }
                        this.booking = false;
                    }
                };
            }
        </script>
        @endpush
    @endif
</x-admin-layout>
