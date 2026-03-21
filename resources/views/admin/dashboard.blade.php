@php
    $currency = '₱';
    $revenueDelta = $revenueLastMonth > 0 ? round(($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth * 100, 1) : ($revenueThisMonth > 0 ? 100 : 0);
    $ordersDelta = $ordersLastMonth > 0 ? round(($ordersThisMonth - $ordersLastMonth) / $ordersLastMonth * 100, 1) : ($ordersThisMonth > 0 ? 100 : 0);
    $statusColors = ['Pending' => '#f59e0b', 'Processing' => '#3b82f6', 'Payment Received' => '#6366f1', 'Shipped' => '#8b5cf6', 'Delivered' => '#10b981', 'Complete' => '#059669', 'Canceled' => '#ef4444', 'Expired' => '#6b7280', 'Unpaid' => '#dc2626'];
    $paymentColors = ['Bank Transfer' => '#6366f1', 'HitPay' => '#ec4899', 'COD' => '#f59e0b', 'Other' => '#94a3b8'];
@endphp

<x-admin-layout>
    <x-slot name="header">Dashboard</x-slot>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 relative overflow-hidden">
            <div class="absolute -right-4 -top-4 w-20 h-20 rounded-full kpi-glow-indigo"></div>
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Revenue</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $currency }}{{ number_format($revenueThisMonth, 0) }}</p>
            <div class="flex items-center gap-1 mt-2">
                @if ($revenueDelta >= 0)
                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                    <span class="text-xs font-semibold text-emerald-600">+{{ $revenueDelta }}%</span>
                @else
                    <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0v-8m0 8l-8-8-4 4-6-6"/></svg>
                    <span class="text-xs font-semibold text-red-600">{{ $revenueDelta }}%</span>
                @endif
                <span class="text-xs text-gray-400">vs last month</span>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 relative overflow-hidden">
            <div class="absolute -right-4 -top-4 w-20 h-20 rounded-full kpi-glow-pink"></div>
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Orders</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $ordersThisMonth }}</p>
            <div class="flex items-center gap-1 mt-2">
                @if ($ordersDelta >= 0)
                    <span class="text-xs font-semibold text-emerald-600">+{{ $ordersDelta }}%</span>
                @else
                    <span class="text-xs font-semibold text-red-600">{{ $ordersDelta }}%</span>
                @endif
                <span class="text-xs text-gray-400">vs last month</span>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 relative overflow-hidden">
            <div class="absolute -right-4 -top-4 w-20 h-20 rounded-full kpi-glow-emerald"></div>
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Avg Order</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $currency }}{{ number_format($avgOrderValue, 0) }}</p>
            <p class="text-xs text-gray-400 mt-2">Across all valid orders</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 relative overflow-hidden">
            <div class="absolute -right-4 -top-4 w-20 h-20 rounded-full kpi-glow-amber"></div>
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Customers</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $customersTotal }}</p>
            @if ($customersThisMonth > 0)
                <p class="text-xs text-emerald-600 font-semibold mt-2">+{{ $customersThisMonth }} this month</p>
            @else
                <p class="text-xs text-gray-400 mt-2">No new this month</p>
            @endif
        </div>
    </div>

    {{-- Pending Actions --}}
    @if ($pendingOrders > 0 || $pendingReviews > 0 || $waitingNotifications > 0 || $inventoryHealth['low_stock'] > 0)
        <div class="flex flex-wrap gap-3 mb-6">
            @if ($pendingOrders > 0)
                <a href="{{ route('admin.orders.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-amber-50 border border-amber-200 rounded-lg text-sm font-medium text-amber-800 hover:bg-amber-100 transition-colors">
                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                    {{ $pendingOrders }} pending order{{ $pendingOrders > 1 ? 's' : '' }}
                </a>
            @endif
            @if ($pendingReviews > 0)
                <a href="{{ route('admin.reviews.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-purple-50 border border-purple-200 rounded-lg text-sm font-medium text-purple-800 hover:bg-purple-100 transition-colors">
                    <span class="w-2 h-2 rounded-full bg-purple-500 animate-pulse"></span>
                    {{ $pendingReviews }} review{{ $pendingReviews > 1 ? 's' : '' }} to approve
                </a>
            @endif
            @if ($waitingNotifications > 0)
                <a href="{{ route('admin.stock-notifications.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-50 border border-blue-200 rounded-lg text-sm font-medium text-blue-800 hover:bg-blue-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    {{ $waitingNotifications }} waiting for stock
                </a>
            @endif
            @if ($inventoryHealth['low_stock'] > 0)
                <span class="inline-flex items-center gap-2 px-4 py-2 bg-red-50 border border-red-200 rounded-lg text-sm font-medium text-red-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    {{ $inventoryHealth['low_stock'] }} low stock
                </span>
            @endif
        </div>
    @endif

    {{-- Row 1: Revenue chart + Order status --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-1">Revenue & Orders</h3>
            <p class="text-xs text-gray-400 mb-4">Last 30 days</p>
            <div id="revenueChart"></div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Order Status</h3>
            <div id="statusChart"></div>
        </div>
    </div>

    {{-- Row 2: Payment + Inventory + Top Products --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Payment Methods</h3>
            <div id="paymentChart"></div>
            <div class="mt-4 space-y-2">
                @foreach ($paymentBreakdown as $pm)
                    <div class="flex items-center justify-between text-sm">
                        <span class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full" style="background: {{ $paymentColors[$pm->method] ?? '#94a3b8' }}"></span>
                            <span class="text-gray-600">{{ $pm->method }}</span>
                        </span>
                        <span class="font-medium text-gray-900">{{ $currency }}{{ number_format($pm->total, 0) }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Inventory Health</h3>
            <div id="inventoryChart"></div>
            <div class="grid grid-cols-3 gap-2 mt-4">
                <div class="text-center p-2 bg-emerald-50 rounded-lg">
                    <p class="text-lg font-bold text-emerald-700">{{ $inventoryHealth['in_stock'] }}</p>
                    <p class="text-[10px] text-emerald-600 uppercase font-semibold">In Stock</p>
                </div>
                <div class="text-center p-2 bg-amber-50 rounded-lg">
                    <p class="text-lg font-bold text-amber-700">{{ $inventoryHealth['low_stock'] }}</p>
                    <p class="text-[10px] text-amber-600 uppercase font-semibold">Low</p>
                </div>
                <div class="text-center p-2 bg-red-50 rounded-lg">
                    <p class="text-lg font-bold text-red-700">{{ $inventoryHealth['out_of_stock'] }}</p>
                    <p class="text-[10px] text-red-600 uppercase font-semibold">Out</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Top Sellers</h3>
            @if ($topProducts->isNotEmpty())
                <div class="space-y-3">
                    @foreach ($topProducts as $i => $tp)
                        @php $maxQty = $topProducts->first()->qty_sold ?: 1; @endphp
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs text-gray-700 truncate max-w-[180px]" title="{{ $tp->name }}">{{ Str::limit($tp->name, 28) }}</span>
                                <span class="text-xs font-semibold text-gray-900">{{ $tp->qty_sold }}</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-1.5">
                                <div class="h-1.5 rounded-full" style="width: {{ ($tp->qty_sold / $maxQty) * 100 }}%; background: hsl({{ 250 - ($i * 25) }}, 70%, 60%)"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-400 text-center py-8">No sales data yet</p>
            @endif
        </div>
    </div>

    {{-- Row 3: Recent Orders + Low Stock --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700">Recent Orders</h3>
                <a href="{{ route('admin.orders.index') }}" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View All</a>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse ($recentOrders as $order)
                    <a href="{{ route('admin.orders.show', $order) }}" class="flex items-center justify-between px-6 py-3.5 hover:bg-gray-50 transition-colors">
                        <div class="flex items-center gap-4">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold
                                {{ in_array($order->status?->slug, ['complete', 'delivered']) ? 'bg-emerald-100 text-emerald-700' : '' }}
                                {{ in_array($order->status?->slug, ['pending', 'unpaid']) ? 'bg-amber-100 text-amber-700' : '' }}
                                {{ in_array($order->status?->slug, ['canceled', 'expired']) ? 'bg-red-100 text-red-700' : '' }}
                                {{ !in_array($order->status?->slug, ['complete', 'delivered', 'pending', 'unpaid', 'canceled', 'expired']) ? 'bg-blue-100 text-blue-700' : '' }}
                            ">#{{ $order->id }}</div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $order->first_name }} {{ $order->last_name }}</p>
                                <p class="text-xs text-gray-400">{{ $order->created_at->diffForHumans() }} &middot; {{ ucwords(str_replace('_', ' ', $order->payment_method ?? '')) }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-gray-900">{{ $currency }}{{ number_format($order->total, 2) }}</p>
                            <p class="text-[11px] font-medium
                                {{ in_array($order->status?->slug, ['complete', 'delivered']) ? 'text-emerald-600' : '' }}
                                {{ $order->status?->slug === 'pending' ? 'text-amber-600' : '' }}
                                {{ in_array($order->status?->slug, ['canceled', 'expired']) ? 'text-red-500' : '' }}
                                {{ !in_array($order->status?->slug, ['complete', 'delivered', 'pending', 'canceled', 'expired']) ? 'text-blue-600' : '' }}
                            ">{{ $order->status?->name ?? '—' }}</p>
                        </div>
                    </a>
                @empty
                    <div class="px-6 py-10 text-center text-sm text-gray-400">No orders yet</div>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-700">Low Stock Alert</h3>
            </div>
            @if ($lowStockProducts->isNotEmpty())
                <div class="divide-y divide-gray-100">
                    @foreach ($lowStockProducts as $product)
                        <a href="{{ route('admin.products.edit', $product) }}" class="flex items-center justify-between px-6 py-3.5 hover:bg-gray-50 transition-colors">
                            <div class="min-w-0">
                                <p class="text-sm text-gray-900 truncate">{{ $product->name }}</p>
                                @if ($product->brand)
                                    <p class="text-[11px] text-gray-400">{{ $product->brand->name }}</p>
                                @endif
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold flex-shrink-0 ml-3 {{ $product->quantity <= 2 ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' }}">
                                {{ $product->quantity }} left
                            </span>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="px-6 py-10 text-center text-sm text-gray-400">All stocked up</div>
            @endif
        </div>
    </div>

    @push('head')
        <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.49.0/dist/apexcharts.min.js"></script>
    @endpush

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Revenue + Orders combo
            new ApexCharts(document.querySelector('#revenueChart'), {
                chart: { type: 'area', height: 280, toolbar: { show: false }, fontFamily: 'inherit', zoom: { enabled: false } },
                series: [
                    { name: 'Revenue', type: 'area', data: @json($revenueValues) },
                    { name: 'Orders', type: 'column', data: @json($orderCounts) }
                ],
                xaxis: {
                    categories: @json($revenueDates),
                    labels: { style: { fontSize: '10px', colors: '#9ca3af' }, rotate: -45, rotateAlways: false, hideOverlappingLabels: true },
                    tickAmount: 8, axisBorder: { show: false }, axisTicks: { show: false }
                },
                yaxis: [
                    { title: { text: 'Revenue', style: { fontSize: '11px', color: '#9ca3af', fontWeight: 400 } }, labels: { style: { colors: '#6366f1', fontSize: '10px' }, formatter: function(v) { return v >= 1000 ? '{{ $currency }}' + (v/1000).toFixed(0) + 'k' : '{{ $currency }}' + v; } } },
                    { opposite: true, title: { text: 'Orders', style: { fontSize: '11px', color: '#9ca3af', fontWeight: 400 } }, labels: { style: { colors: '#ec4899', fontSize: '10px' } }, forceNiceScale: true, min: 0 }
                ],
                colors: ['#6366f1', '#ec4899'],
                fill: { type: ['gradient', 'solid'], gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.02, stops: [0, 95, 100] } },
                stroke: { width: [2.5, 0], curve: 'smooth' },
                plotOptions: { bar: { borderRadius: 4, columnWidth: '35%' } },
                dataLabels: { enabled: false },
                tooltip: { shared: true, intersect: false, y: { formatter: function(v, opts) { return opts.seriesIndex === 0 ? '{{ $currency }}' + v.toLocaleString() : v + ' orders'; } } },
                grid: { borderColor: '#f3f4f6', strokeDashArray: 4, padding: { bottom: 0 } },
                legend: { position: 'top', horizontalAlign: 'right', fontSize: '11px', markers: { radius: 12, width: 8, height: 8 }, itemMargin: { horizontal: 12 } }
            }).render();

            // Order status donut
            @if ($statusBreakdown->isNotEmpty())
            new ApexCharts(document.querySelector('#statusChart'), {
                chart: { type: 'donut', height: 280, fontFamily: 'inherit' },
                series: @json($statusBreakdown->pluck('count')),
                labels: @json($statusBreakdown->pluck('name')),
                colors: @json($statusBreakdown->pluck('name')->map(function($n) use ($statusColors) { return $statusColors[$n] ?? '#94a3b8'; })->values()),
                plotOptions: { pie: { donut: { size: '72%', labels: { show: true, total: { show: true, label: 'Total', fontSize: '12px', fontWeight: 600, color: '#6b7280', formatter: function(w) { return w.globals.seriesTotals.reduce(function(a, b) { return a + b; }, 0); } } } } } },
                dataLabels: { enabled: false },
                legend: { position: 'bottom', fontSize: '11px', markers: { radius: 12, width: 8, height: 8 }, itemMargin: { horizontal: 8, vertical: 4 } },
                stroke: { width: 3, colors: ['#fff'] },
                tooltip: { y: { formatter: function(v) { return v + ' orders'; } } }
            }).render();
            @endif

            // Payment donut
            @if ($paymentBreakdown->isNotEmpty())
            new ApexCharts(document.querySelector('#paymentChart'), {
                chart: { type: 'donut', height: 180, fontFamily: 'inherit' },
                series: @json($paymentBreakdown->pluck('count')),
                labels: @json($paymentBreakdown->pluck('method')),
                colors: @json($paymentBreakdown->pluck('method')->map(function($m) use ($paymentColors) { return $paymentColors[$m] ?? '#94a3b8'; })->values()),
                plotOptions: { pie: { donut: { size: '68%' } } },
                dataLabels: { enabled: false },
                legend: { show: false },
                stroke: { width: 3, colors: ['#fff'] },
                tooltip: { y: { formatter: function(v) { return v + ' orders'; } } }
            }).render();
            @endif

            // Inventory radial bar
            @php $healthPct = $inventoryHealth['total_active'] > 0 ? round($inventoryHealth['in_stock'] / $inventoryHealth['total_active'] * 100) : 0; @endphp
            new ApexCharts(document.querySelector('#inventoryChart'), {
                chart: { type: 'radialBar', height: 200, fontFamily: 'inherit' },
                series: [{{ $healthPct }}],
                labels: ['In Stock'],
                colors: ['{{ $healthPct > 60 ? '#10b981' : ($healthPct > 30 ? '#f59e0b' : '#ef4444') }}'],
                plotOptions: { radialBar: { hollow: { size: '68%' }, track: { background: '#f3f4f6', strokeWidth: '100%' }, dataLabels: { name: { fontSize: '12px', color: '#9ca3af', offsetY: -8 }, value: { fontSize: '26px', fontWeight: 700, color: '#111827', offsetY: 4, formatter: function(v) { return v + '%'; } } } } },
                stroke: { lineCap: 'round' }
            }).render();
        });
    </script>
    @endpush
</x-admin-layout>
