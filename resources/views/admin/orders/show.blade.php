@php
    $currency = $defaultCurrency->symbol ?? '₱';
    $statusTones = [
        'Pending' => 'warning', 'Unpaid' => 'warning', 'Processing' => 'info',
        'Payment Received' => 'info', 'Shipped' => 'info', 'Order Being Shipped' => 'info',
        'Delivered' => 'success', 'Complete' => 'success',
        'Canceled' => 'danger', 'Failed' => 'danger',
        'Refunded' => 'neutral', 'Expired' => 'neutral',
    ];
    $statusTone = $statusTones[$order->status?->name] ?? 'neutral';
    $shippingCourierLabel = $order->courier_label;
@endphp

<x-admin.shell title="Order #{{ $order->id }}">
    <x-admin.page title="{{ $order->first_name }} {{ $order->last_name }}"
        :breadcrumb="[['label' => 'Orders', 'url' => route('admin.orders.index')], ['label' => '#'.$order->id]]">
        <x-slot:actions>
            <x-admin.button variant="secondary" href="{{ route('admin.orders.edit', $order) }}">Edit</x-admin.button>
            <x-admin.button variant="ghost" href="{{ route('admin.orders.index') }}">Back to Orders</x-admin.button>
        </x-slot:actions>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

            {{-- LEFT COLUMN --}}
            <div class="space-y-6 lg:col-span-2">

                {{-- 1. ORDER OVERVIEW --}}
                <x-admin.card>
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <x-admin.badge :tone="$statusTone">{{ $order->status?->name ?? 'No Status' }}</x-admin.badge>
                            <span class="v-num text-2xl font-semibold text-v-text">{{ $currency }}{{ number_format($order->total, 2) }}</span>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-x-8 gap-y-3 border-t border-v-border pt-4 text-[13px]">
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-v-muted">Placed</p>
                            <p class="mt-0.5 text-v-secondary">{{ $order->created_at->format('M d, Y · g:i A') }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-v-muted">Payment Method</p>
                            <p class="mt-0.5 text-v-secondary">{{ $order->payment_method_label }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-v-muted">Shipping</p>
                            <p class="mt-0.5 text-v-secondary">Shipping @if ($shippingCourierLabel)({{ $shippingCourierLabel }})@endif</p>
                        </div>
                        @if ($order->payment_reference)
                            <div>
                                <p class="text-[10px] font-semibold uppercase tracking-widest text-v-muted">Payment Reference</p>
                                <p class="v-num mt-0.5 text-v-secondary">{{ $order->payment_reference }}</p>
                            </div>
                        @endif
                    </div>
                </x-admin.card>

                {{-- 2. CUSTOMER --}}
                <x-admin.card title="Customer">
                    <x-slot:headerActions>
                        @if ($order->customer)
                            <a href="{{ route('admin.customers.edit', $order->customer) }}" class="text-[13px] font-medium text-v-accent hover:opacity-80">View Profile →</a>
                        @endif
                    </x-slot:headerActions>
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-v-accent-weak text-sm font-bold text-v-accent">
                            {{ strtoupper(substr($order->first_name, 0, 1) . substr($order->last_name, 0, 1)) }}
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="text-[13px] font-semibold text-v-text">{{ $order->first_name }} {{ $order->last_name }}</p>
                                @if ($order->customer?->customerGroup)
                                    <x-admin.badge tone="neutral">{{ $order->customer->customerGroup->name }}</x-admin.badge>
                                @endif
                            </div>
                            <a href="mailto:{{ $order->email }}" class="text-[13px] text-v-accent hover:opacity-80">{{ $order->email }}</a>
                        </div>
                    </div>
                </x-admin.card>

                {{-- 3. ADDRESSES --}}
                <x-admin.card title="Addresses">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        {{-- Shipping --}}
                        <div>
                            <h4 class="mb-3 text-[10px] font-semibold uppercase tracking-widest text-v-muted">Ship To</h4>
                            <div class="space-y-0.5 text-[13px]">
                                <p class="font-medium text-v-text">{{ $order->shipping_first_name }} {{ $order->shipping_last_name }}</p>
                                <p class="text-v-secondary">{{ $order->shipping_address_1 }}</p>
                                @if ($order->shipping_barangay) <p class="text-v-secondary">{{ $order->shipping_barangay }}</p> @endif
                                @if ($order->shipping_district) <p class="text-v-secondary">{{ $order->shipping_district }}</p> @endif
                                <p class="text-v-secondary">{{ $order->shipping_city }}@if ($order->shipping_state), {{ $order->shipping_state }}@endif {{ $order->shipping_postcode }}</p>
                                @if ($order->shipping_country_name ?? $order->shippingCountry?->name) <p class="text-xs text-v-muted">{{ $order->shipping_country_name ?? $order->shippingCountry?->name }}</p> @endif
                                @if ($order->shipping_phone || $order->phone)
                                    <p class="mt-1.5 text-v-muted">{{ $order->shipping_phone ?: $order->phone }}</p>
                                @endif
                            </div>
                        </div>

                        {{-- Billing --}}
                        <div>
                            <h4 class="mb-3 text-[10px] font-semibold uppercase tracking-widest text-v-muted">Bill To</h4>
                            @if ($order->billing_first_name)
                                <div class="space-y-0.5 text-[13px]">
                                    <p class="font-medium text-v-text">{{ $order->billing_first_name }} {{ $order->billing_last_name }}</p>
                                    <p class="text-v-secondary">{{ $order->billing_address_1 }}</p>
                                    @if ($order->billing_barangay) <p class="text-v-secondary">{{ $order->billing_barangay }}</p> @endif
                                    @if ($order->billing_district) <p class="text-v-secondary">{{ $order->billing_district }}</p> @endif
                                    <p class="text-v-secondary">{{ $order->billing_city }}@if ($order->billing_state), {{ $order->billing_state }}@endif {{ $order->billing_postcode }}</p>
                                    @if ($order->billing_country_name ?? $order->billingCountry?->name) <p class="text-xs text-v-muted">{{ $order->billing_country_name ?? $order->billingCountry?->name }}</p> @endif
                                    @if ($order->billing_phone)
                                        <p class="mt-1.5 text-v-muted">{{ $order->billing_phone }}</p>
                                    @endif
                                </div>
                            @else
                                <p class="text-[13px] italic text-v-muted">Same as shipping</p>
                            @endif
                        </div>
                    </div>
                </x-admin.card>

                {{-- 4. PRODUCTS --}}
                <x-admin.card title="Products">
                    <x-admin.table>
                        <x-slot:head>
                            <x-admin.table.th>Item</x-admin.table.th>
                            <x-admin.table.th align="right">Price</x-admin.table.th>
                            <x-admin.table.th align="right">Qty</x-admin.table.th>
                            <x-admin.table.th align="right">Total</x-admin.table.th>
                        </x-slot:head>
                        @foreach ($order->products as $item)
                            <x-admin.table.row>
                                <x-admin.table.cell>
                                    <div class="flex items-center gap-3">
                                        @php($itemImage = $item->image ?? $item->product?->image)
                                        @if ($itemImage)
                                            <img src="{{ storage_url($itemImage) }}" alt="{{ $item->name }}" class="h-10 w-10 shrink-0 rounded-v-md border border-v-border object-cover">
                                        @else
                                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-v-md border border-v-border bg-v-sunken">
                                                <x-admin.icon name="products" class="h-5 w-5 text-v-muted" />
                                            </div>
                                        @endif
                                        <div class="min-w-0">
                                            <p class="font-medium text-v-text">{{ $item->name }}</p>
                                            <div class="mt-0.5 flex flex-wrap items-center gap-1.5">
                                                @if ($item->variant_label)
                                                    <span class="rounded px-1.5 py-0.5 text-[11px] font-medium text-v-accent bg-v-accent-weak">{{ $item->variant_label }}</span>
                                                @endif
                                                @if ($item->sku)
                                                    <span class="v-num text-[11px] text-v-muted">{{ $item->sku }}</span>
                                                @endif
                                                @if ($item->is_custom)
                                                    <x-admin.badge tone="warning">Custom</x-admin.badge>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </x-admin.table.cell>
                                <x-admin.table.cell align="right" mono>{{ $currency }}{{ number_format($item->price, 2) }}</x-admin.table.cell>
                                <x-admin.table.cell align="right" mono>{{ $item->quantity }}</x-admin.table.cell>
                                <x-admin.table.cell align="right" mono>{{ $currency }}{{ number_format($item->price * $item->quantity, 2) }}</x-admin.table.cell>
                            </x-admin.table.row>
                        @endforeach
                        <x-slot:footer>
                            <div class="flex justify-end">
                                <div class="w-full space-y-1.5 sm:max-w-md">
                                    <div class="flex justify-between text-[13px]">
                                        <span class="text-v-muted">Subtotal</span>
                                        <span class="v-num text-v-secondary">{{ $currency }}{{ number_format($order->subtotal, 2) }}</span>
                                    </div>
                                    <div class="flex items-start justify-between gap-3 text-[13px]">
                                        <span class="break-words text-v-muted">Shipping @if ($shippingCourierLabel)({{ $shippingCourierLabel }})@endif</span>
                                        <span class="v-num whitespace-nowrap text-v-secondary">{{ $order->shipping_cost > 0 ? $currency . number_format($order->shipping_cost, 2) : 'Free' }}</span>
                                    </div>
                                    @if ($order->coupon_code)
                                        <div class="flex justify-between text-[13px]">
                                            <span class="text-v-muted">Coupon ({{ $order->coupon_code }})</span>
                                            <span class="v-num text-v-accent">-{{ $currency }}{{ number_format($order->coupon_discount, 2) }}</span>
                                        </div>
                                    @endif
                                    <div class="flex justify-between border-t border-v-border pt-2 text-[13px] font-bold">
                                        <span class="text-v-text">Total</span>
                                        <span class="v-num text-base text-v-text">{{ $currency }}{{ number_format($order->total, 2) }}</span>
                                    </div>
                                </div>
                            </div>
                        </x-slot:footer>
                    </x-admin.table>
                    @if ($order->comment)
                        <div class="mt-4 rounded-v-md px-3 py-2.5 text-[13px] bg-[color-mix(in_srgb,var(--warning)_10%,transparent)]">
                            <span class="font-medium text-v-warning">Customer Note:</span>
                            <span class="text-v-secondary">{{ $order->comment }}</span>
                        </div>
                    @endif
                </x-admin.card>

                {{-- 5. SHIPPING & TRACKING --}}
                <x-admin.card title="Shipping & Tracking">
                    @if ($order->tracking_number)
                        <div class="flex items-center gap-2 rounded-v-lg bg-v-accent-weak p-3 text-[13px]">
                            @if ($order->shippingCourier)
                                <span class="font-medium text-v-accent">{{ $order->shippingCourier->name }}</span>
                            @endif
                            @if ($order->tracking_link)
                                <a href="{{ $order->tracking_link }}" target="_blank" class="v-num text-v-accent hover:underline">{{ $order->tracking_number }}</a>
                            @else
                                <span class="v-num text-v-accent">{{ $order->tracking_number }}</span>
                            @endif
                        </div>
                    @else
                        <p class="text-[13px] text-v-muted">No tracking info yet. Use the Update Order form to add courier and tracking number.</p>
                    @endif
                </x-admin.card>
            </div>

            {{-- RIGHT COLUMN --}}
            <div class="space-y-6">

                {{-- UPDATE STATUS --}}
                <x-admin.card title="Update Order">
                    <form method="POST" action="{{ route('admin.orders.history.store', $order) }}" data-v-form class="-mt-2 space-y-3">
                        @csrf
                        <x-admin.field name="order_status_id" label="Status">
                            <x-admin.select name="order_status_id" required>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->id }}" @selected($order->order_status_id == $status->id)>{{ $status->name }}</option>
                                @endforeach
                            </x-admin.select>
                        </x-admin.field>
                        <x-admin.field name="shipping_courier_id" label="Courier">
                            <x-admin.select name="shipping_courier_id">
                                <option value="">Select courier...</option>
                                @foreach ($couriers as $courier)
                                    <option value="{{ $courier->id }}" @selected($order->shipping_courier_id == $courier->id)>{{ $courier->name }}</option>
                                @endforeach
                            </x-admin.select>
                        </x-admin.field>
                        <x-admin.field name="tracking_number" label="Tracking Number">
                            <x-admin.input type="text" name="tracking_number" :value="$order->tracking_number" placeholder="Enter tracking number" />
                        </x-admin.field>
                        <x-admin.field name="comment" label="Comment">
                            <x-admin.input type="text" name="comment" placeholder="Add a comment..." />
                        </x-admin.field>
                        <div class="flex items-center justify-between pt-1">
                            <x-admin.checkbox name="notify_customer" label="Notify Customer" />
                            <x-admin.button type="submit" size="sm">Update</x-admin.button>
                        </div>
                    </form>
                </x-admin.card>

                {{-- ORDER HISTORY --}}
                <x-admin.card title="History">
                    @if ($order->histories->count() > 0)
                        <div class="relative -mt-1">
                            <div class="absolute bottom-2 left-[7px] top-2 w-px bg-v-border"></div>
                            <div class="space-y-5">
                                @foreach ($order->histories->sortByDesc('created_at') as $history)
                                    <div class="group relative pl-7">
                                        <div class="absolute left-0 top-1 h-[15px] w-[15px] rounded-full border-2 {{ $loop->first ? 'border-v-accent bg-v-accent-weak' : 'border-v-strong bg-v-surface' }}"></div>
                                        <div class="flex items-start justify-between">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <x-admin.badge :tone="$loop->first ? 'success' : 'neutral'">{{ $history->status?->name ?? '—' }}</x-admin.badge>
                                                    @if ($history->notify_customer)
                                                        <span class="text-[10px] font-medium text-v-accent">Notified</span>
                                                    @endif
                                                </div>
                                                @if ($history->comment)
                                                    <p class="mt-1 text-[13px] text-v-secondary">{{ strip_tags($history->comment) }}</p>
                                                @endif
                                                <p class="mt-1 text-[11px] text-v-muted">
                                                    {{ $history->created_at->format('M d, Y g:i A') }}
                                                    @if ($history->createdByUser) &mdash; {{ $history->createdByUser->name }} @endif
                                                </p>
                                            </div>
                                            <button type="button" class="ml-2 rounded p-1 text-v-muted transition-colors hover:bg-v-hover hover:text-v-danger" title="Delete"
                                                @click="$dispatch('open-modal', 'delete-history-{{ $history->id }}')">
                                                <x-admin.icon name="trash" class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <p class="py-6 text-center text-[13px] text-v-muted">No history yet.</p>
                    @endif
                </x-admin.card>
            </div>
        </div>

        @foreach ($order->histories as $history)
            <x-admin.confirm-dialog name="delete-history-{{ $history->id }}" title="Delete this history entry?"
                message="This removes the status-change record from the order's timeline. This cannot be undone."
                confirm="Delete" :action="route('admin.orders.history.destroy', [$order, $history->id])" method="DELETE" />
        @endforeach
    </x-admin.page>
</x-admin.shell>
