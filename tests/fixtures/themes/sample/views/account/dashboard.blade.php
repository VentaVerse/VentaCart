<x-store-layout title="My Account">
    <x-storefront.columns>
    <x-storefront.region name="content_top" />
    <div class="container-store py-10">
        <h1 class="section-title reveal">MY ACCOUNT</h1>

        <div class="account-layout">
            @include('account.partials.sidebar')

            <div class="space-y-6">
                {{-- Welcome --}}
                <div class="account-card reveal">
                    <h2 class="account-section-title">Welcome, {{ $customer->first_name }}</h2>
                    <p class="text-sm text-[var(--color-text)]">From your account dashboard you can view your recent orders, manage your addresses, and edit your profile.</p>
                </div>

                {{-- Default Address --}}
                @if ($defaultAddress)
                    <div class="account-card reveal">
                        <div class="flex items-center justify-between mb-3">
                            <h2 class="account-section-title mb-0">Default Address</h2>
                            <a href="{{ route('account.addresses') }}" class="text-xs text-[var(--color-primary)] hover:underline">Manage</a>
                        </div>
                        <div class="text-sm text-[var(--color-text)] space-y-0.5">
                            <p class="text-[var(--color-text-heading)]">{{ $defaultAddress->first_name }} {{ $defaultAddress->last_name }}</p>
                            <p>{{ $defaultAddress->address_1 }}</p>
                            @if ($defaultAddress->barangay)
                                <p>{{ $defaultAddress->barangay }}</p>
                            @endif
                            @if ($defaultAddress->district)
                                <p>{{ $defaultAddress->district }}</p>
                            @endif
                            <p>{{ $defaultAddress->city }}@if ($defaultAddress->state), {{ $defaultAddress->state }}@endif</p>
                            @if ($defaultAddress->postcode)
                                <p>Postal code {{ $defaultAddress->postcode }}</p>
                            @endif
                            @if ($defaultAddress->country?->name)
                                <p>{{ $defaultAddress->country->name }}</p>
                            @endif
                            @if ($defaultAddress->phone_display)
                                <p class="text-[var(--color-text-heading)] mt-1">{{ $defaultAddress->phone_display }}</p>
                            @endif
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
                        <p class="text-sm text-[var(--color-text)]">You haven't placed any orders yet.</p>
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
                                            <td class="text-[var(--color-text-heading)]">#{{ $order->id }}</td>
                                            <td>{{ $order->created_at->format('M d, Y') }}</td>
                                            <td class="font-heading">{{ store_money($order->total) }}</td>
                                            <td>
                                                @php $statusName = $order->order_status_name ?? 'Pending'; @endphp
                                                <span class="account-status-badge account-status-badge--{{ $order->status?->tone ?? 'neutral' }}">{{ $statusName }}</span>
                                            </td>
                                            <td class="account-row-actions"><a href="{{ route('account.orders.show', $order) }}">View</a></td>
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
    <x-storefront.region name="content_bottom" />
    </x-storefront.columns>
</x-store-layout>
