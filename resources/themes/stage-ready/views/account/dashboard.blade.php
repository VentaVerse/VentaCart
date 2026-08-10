<x-store-layout title="My Account">
    <div class="container-store py-10">
        <h1 class="section-title reveal">MY ACCOUNT</h1>

        <div class="account-layout">
            @include('account.partials.sidebar')

            <div class="space-y-6">
                {{-- Welcome --}}
                <div class="account-card reveal">
                    <h2 class="account-section-title">Welcome, {{ $customer->first_name }}</h2>
                    <p class="text-sm text-[#777]">From your account dashboard you can view your recent orders, manage your addresses, and edit your profile.</p>
                </div>

                {{-- Default Address --}}
                @if ($defaultAddress)
                    <div class="account-card reveal">
                        <div class="flex items-center justify-between mb-3">
                            <h2 class="account-section-title mb-0">Default Address</h2>
                            <a href="{{ route('account.addresses') }}" class="text-xs text-[var(--color-primary)] hover:underline">Manage</a>
                        </div>
                        <div class="text-sm text-[#999] space-y-0.5">
                            <p class="text-[#E8E8E8]">{{ $defaultAddress->first_name }} {{ $defaultAddress->last_name }}</p>
                            <p>{{ $defaultAddress->address_1 }}</p>
                            @if ($defaultAddress->barangay)
                                <p>{{ $defaultAddress->barangay }}</p>
                            @endif
                            @if ($defaultAddress->district)
                                <p>{{ $defaultAddress->district }}</p>
                            @endif
                            <p>{{ $defaultAddress->city }}@if ($defaultAddress->state), {{ $defaultAddress->state }}@endif @if ($defaultAddress->postcode) {{ $defaultAddress->postcode }}@endif</p>
                            <p>{{ $defaultAddress->country_code }}</p>
                        </div>
                    </div>
                @endif

                {{-- Recent Orders --}}
                <div class="account-card reveal">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="account-section-title mb-0">Recent Orders</h2>
                        <a href="{{ route('account.orders') }}" class="text-xs text-[var(--color-primary)] hover:underline">View All</a>
                    </div>

                    @if ($recentOrders->isEmpty())
                        <p class="text-sm text-[#555]">You haven't placed any orders yet.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="account-table w-full">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Date</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentOrders as $order)
                                        <tr>
                                            <td class="text-[#E8E8E8]">#{{ $order->id }}</td>
                                            <td>{{ $order->created_at->format('M d, Y') }}</td>
                                            <td class="font-heading">{{ $defaultCurrency->symbol ?? '₱' }}{{ number_format($order->total, 2) }}</td>
                                            <td>
                                                @php $statusName = $order->order_status_name ?? 'Pending'; @endphp
                                                <span class="account-status-badge account-status-{{ strtolower($statusName) }}">{{ $statusName }}</span>
                                            </td>
                                            <td><a href="{{ route('account.orders.show', $order) }}" class="text-xs text-[var(--color-primary)] hover:underline">View</a></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-store-layout>
