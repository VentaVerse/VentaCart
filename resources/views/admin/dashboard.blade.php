@php
    $statusTones = [
        'Pending' => 'warning', 'Unpaid' => 'warning', 'Processing' => 'info',
        'Payment Received' => 'info', 'Shipped' => 'info', 'Order Being Shipped' => 'info',
        'Delivered' => 'success', 'Complete' => 'success',
        'Canceled' => 'danger', 'Failed' => 'danger',
        'Refunded' => 'neutral', 'Expired' => 'neutral',
    ];
@endphp
<x-admin.shell title="Dashboard">
    <x-admin.page title="Dashboard">
        <div class="space-y-6">
            {{-- Stat row --}}
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <x-admin.stat class="v-dash-in" label="Revenue Today" :value="'₱' . number_format($revenueToday, 2)" />
                <x-admin.stat class="v-dash-in" label="Orders Today" :value="$ordersToday" />
                <x-admin.stat class="v-dash-in" label="Pending Orders" :value="$pendingOrders" :tone="$pendingOrders > 0 ? 'warning' : 'default'" />
                <x-admin.stat class="v-dash-in" label="Low Stock" :value="$lowStockCount" :tone="$lowStockCount > 0 ? 'danger' : 'default'" />
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                {{-- Recent orders --}}
                <div class="xl:col-span-2">
                    <x-admin.card title="Recent Orders" description="Latest activity across the store.">
                        <x-slot:headerActions>
                            <x-admin.button variant="ghost" size="sm" :href="route('admin.orders.index')">View all →</x-admin.button>
                        </x-slot:headerActions>

                        @if ($recentOrders->isEmpty())
                            <x-admin.empty-state icon="orders" title="No orders yet"
                                description="Orders placed on the storefront will appear here." />
                        @else
                            <x-admin.table>
                                <x-slot:head>
                                    <x-admin.table.th>Order #</x-admin.table.th>
                                    <x-admin.table.th>Customer</x-admin.table.th>
                                    <x-admin.table.th>Status</x-admin.table.th>
                                    <x-admin.table.th align="right">Total</x-admin.table.th>
                                    <x-admin.table.th align="right">Placed</x-admin.table.th>
                                </x-slot:head>
                                @foreach ($recentOrders as $order)
                                    <x-admin.table.row href="{{ route('admin.orders.show', $order) }}">
                                        <x-admin.table.cell mono>#{{ $order->id }}</x-admin.table.cell>
                                        <x-admin.table.cell class="text-v-text">{{ $order->full_name }}</x-admin.table.cell>
                                        <x-admin.table.cell>
                                            <x-admin.badge :tone="$order->order_status_id ? ($statusTones[$order->status?->name] ?? 'neutral') : 'danger'">
                                                {{ $order->status?->name ?? 'Missing' }}
                                            </x-admin.badge>
                                        </x-admin.table.cell>
                                        <x-admin.table.cell align="right" mono>₱{{ number_format($order->total, 2) }}</x-admin.table.cell>
                                        <x-admin.table.cell align="right" class="text-v-muted">{{ $order->created_at->diffForHumans() }}</x-admin.table.cell>
                                    </x-admin.table.row>
                                @endforeach
                            </x-admin.table>
                        @endif
                    </x-admin.card>
                </div>

                {{-- Sidebar cards --}}
                <div class="space-y-6">
                    <x-admin.card title="Needs Attention" class="v-dash-in">
                        <div class="-mx-6 -mb-5 divide-y divide-v-border">
                            <a href="{{ route('admin.reviews.index') }}" class="flex items-center justify-between gap-3 px-6 py-3 text-[13px] transition-colors hover:bg-v-hover">
                                <span class="flex items-center gap-2 text-v-text"><x-admin.icon name="reviews" class="w-4 h-4 text-v-muted" /> Reviews awaiting approval</span>
                                <x-admin.badge :tone="$pendingReviews > 0 ? 'warning' : 'neutral'">{{ $pendingReviews }}</x-admin.badge>
                            </a>
                            <a href="{{ route('admin.extensions.seo.review') }}" class="flex items-center justify-between gap-3 px-6 py-3 text-[13px] transition-colors hover:bg-v-hover">
                                <span class="flex items-center gap-2 text-v-text"><x-admin.icon name="seo" class="w-4 h-4 text-v-muted" /> SEO suggestions pending</span>
                                <x-admin.badge :tone="$pendingSeoSuggestions > 0 ? 'warning' : 'neutral'">{{ $pendingSeoSuggestions }}</x-admin.badge>
                            </a>
                            <a href="{{ route('admin.abandoned-carts.index') }}" class="flex items-center justify-between gap-3 px-6 py-3 text-[13px] transition-colors hover:bg-v-hover">
                                <span class="flex items-center gap-2 text-v-text"><x-admin.icon name="cart" class="w-4 h-4 text-v-muted" /> Carts recoverable</span>
                                <x-admin.badge :tone="$recoverableCarts > 0 ? 'warning' : 'neutral'">{{ $recoverableCarts }}</x-admin.badge>
                            </a>
                        </div>
                    </x-admin.card>

                    <x-admin.card title="Automation Health" class="v-dash-in">
                        <x-slot:headerActions>
                            <x-admin.button variant="ghost" size="sm" :href="route('admin.settings.section', 'automations')">Manage →</x-admin.button>
                        </x-slot:headerActions>

                        @if ($automations->isEmpty())
                            <p class="text-[13px] text-v-muted">No automations configured.</p>
                        @elseif ($unhealthyAutomations->isEmpty())
                            <p class="flex items-center gap-2 text-[13px] text-v-muted">
                                <span class="h-1.5 w-1.5 rounded-full bg-v-accent"></span>
                                All {{ $automations->count() }} automation{{ $automations->count() === 1 ? '' : 's' }} running on schedule.
                            </p>
                        @else
                            <div class="space-y-3">
                                @foreach ($unhealthyAutomations as $automation)
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="truncate text-[13px] text-v-text">{{ $automation->name }}</p>
                                            <p class="text-xs text-v-muted">
                                                {{ $automation->last_run_at ? 'Last ran ' . $automation->last_run_at->diffForHumans() : 'Never run' }}
                                            </p>
                                        </div>
                                        <x-admin.badge tone="danger">{{ $automation->last_run_status === 'failed' ? 'Failed' : 'Overdue' }}</x-admin.badge>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </x-admin.card>

                    <x-admin.card title="Storefront" description="See what customers see." class="v-dash-in">
                        <x-admin.button variant="secondary" size="sm" href="{{ route('home') }}" target="_blank" rel="noopener">
                            <x-admin.icon name="external" class="w-4 h-4" /> View store
                        </x-admin.button>
                    </x-admin.card>
                </div>
            </div>

            {{-- Recent customer activity --}}
            <x-admin.card title="Recent Customer Activity">
                @if ($recentActivities->isEmpty())
                    <x-admin.empty-state icon="customers" title="No activity yet"
                        description="Customer logins, cart adds, and orders will show up here." />
                @else
                    <div class="-mx-6 -mb-5 max-h-[420px] divide-y divide-v-border overflow-y-auto">
                        @foreach ($recentActivities as $activity)
                            <div class="flex items-start gap-3 px-6 py-3">
                                <div class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full {{ $activity->color }}">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $activity->icon }}"/></svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-[13px] text-v-text">
                                        @if ($activity->customer)
                                            <a href="{{ route('admin.customers.edit', $activity->customer) }}" class="font-medium text-v-accent hover:opacity-80">{{ $activity->customer->first_name }} {{ $activity->customer->last_name }}</a>
                                        @else
                                            <span class="text-v-muted">Deleted customer</span>
                                        @endif
                                        {{ lcfirst($activity->description) }}
                                    </p>
                                    <p class="text-xs text-v-muted">{{ $activity->created_at->diffForHumans() }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-admin.card>
        </div>
    </x-admin.page>
</x-admin.shell>
