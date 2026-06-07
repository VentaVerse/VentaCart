<x-admin-layout>
    <x-slot name="header">Fulfillment</x-slot>

    <div x-data="fulfillment()" class="space-y-4">

        {{-- Flash --}}
        @if (session('success'))
            <div class="rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3">{{ session('error') }}</div>
        @endif

        @if (empty($couriers))
            <div class="rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm px-4 py-3">
                No courier is enabled yet.
                <a href="{{ route('admin.fulfillment.settings') }}" class="font-semibold underline">Configure a courier →</a>
            </div>
        @endif

        {{-- Header + filters --}}
        <div class="flex items-center justify-between">
            <div class="inline-flex rounded-lg border border-gray-200 bg-white p-1 text-sm">
                @foreach (['to_book' => 'To Book', 'booked' => 'Booked', 'all' => 'All'] as $key => $label)
                    <a href="{{ route('admin.fulfillment.index', ['filter' => $key]) }}"
                       class="px-3 py-1.5 rounded-md {{ $filter === $key ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-50' }}">{{ $label }}</a>
                @endforeach
            </div>
            <a href="{{ route('admin.fulfillment.settings') }}" class="text-sm text-indigo-600 hover:underline">Courier settings</a>
        </div>

        {{-- Orders table --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-[11px] font-semibold text-gray-400 uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-2.5 text-left">Order</th>
                        <th class="px-4 py-2.5 text-left">Customer</th>
                        <th class="px-4 py-2.5 text-left">Destination</th>
                        <th class="px-4 py-2.5 text-left">Payment</th>
                        <th class="px-4 py-2.5 text-left">Courier</th>
                        <th class="px-4 py-2.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($orders as $order)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">#{{ $order->id }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ trim($order->shipping_first_name . ' ' . $order->shipping_last_name) ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">
                                {{ $order->shipping_city }}@if ($order->shipping_state), {{ $order->shipping_state }}@endif
                            </td>
                            <td class="px-4 py-3 text-gray-500 text-xs uppercase">{{ $order->payment_method ?: '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($order->courier_tracking_number)
                                    <div class="text-gray-900 font-mono text-xs">{{ $order->courier_tracking_number }}</div>
                                    <div class="text-[11px] text-gray-500">{{ strtoupper($order->courier_provider) }} · {{ $order->courier_status ?: 'booked' }}</div>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                @if ($order->courier_tracking_number)
                                    <a href="{{ route('admin.fulfillment.label', $order) }}" target="_blank" class="text-indigo-600 hover:underline text-xs mr-2">Print AWB</a>
                                    <form method="POST" action="{{ route('admin.fulfillment.track', $order) }}" class="inline">@csrf<button class="text-gray-500 hover:text-gray-700 text-xs mr-2">Refresh</button></form>
                                    <form method="POST" action="{{ route('admin.fulfillment.cancel', $order) }}" class="inline" onsubmit="return confirm('Cancel this booking?')">@csrf<button class="text-red-500 hover:text-red-700 text-xs">Cancel</button></form>
                                @elseif (!empty($couriers))
                                    <button type="button"
                                        @click="openBooking({{ $order->id }}, '{{ str_contains(strtolower($order->payment_method ?? ''), 'cod') ? 'cod' : 'prepaid' }}')"
                                        class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700">Book</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">No orders in this view.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $orders->links() }}</div>

        {{-- Booking modal --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @keydown.escape.window="open=false">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg" @click.outside="open=false">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900">Book shipment — Order <span x-text="'#'+orderId"></span></h3>
                    <button type="button" @click="open=false" class="text-gray-400 hover:text-gray-600">&times;</button>
                </div>
                <form :action="bookAction" method="POST" class="p-5 space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Courier</label>
                        <select name="courier" x-model="courier" class="w-full rounded-lg border-gray-300 text-sm">
                            <template x-for="(opt, key) in options" :key="key">
                                <option :value="key" x-text="opt.name"></option>
                            </template>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Service</label>
                            <select name="service" class="w-full rounded-lg border-gray-300 text-sm">
                                <template x-for="o in current.services" :key="o.value"><option :value="o.value" x-text="o.label"></option></template>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Parcel</label>
                            <select name="parcel" class="w-full rounded-lg border-gray-300 text-sm">
                                <template x-for="o in current.parcels" :key="o.value"><option :value="o.value" x-text="o.label"></option></template>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Buyer pays for goods</label>
                            <select name="buyer_payment" x-model="buyerPayment" class="w-full rounded-lg border-gray-300 text-sm">
                                <option value="cod">COD (collect on delivery)</option>
                                <option value="prepaid">Prepaid (already paid)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">You pay shipping</label>
                            <select name="shipping_payment" class="w-full rounded-lg border-gray-300 text-sm">
                                <template x-for="o in current.payments" :key="o.value"><option :value="o.value" x-text="o.label"></option></template>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="open=false" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700">Book Shipment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function fulfillment() {
            return {
                open: false,
                orderId: null,
                buyerPayment: 'cod',
                courier: @js(array_key_first($courierOptions) ?? ''),
                options: @js($courierOptions),
                get current() { return this.options[this.courier] ?? {services:[],parcels:[],payments:[]}; },
                get bookAction() { return '{{ url(config('admin.prefix', 'admin') . '/fulfillment') }}/' + this.orderId + '/book'; },
                openBooking(id, codFlag) {
                    this.orderId = id;
                    this.buyerPayment = codFlag;
                    this.open = true;
                },
            };
        }
    </script>
    @endpush
</x-admin-layout>
