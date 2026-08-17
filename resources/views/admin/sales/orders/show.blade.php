@php
    $currency = $defaultCurrency->symbol ?? '₱';
    $statusTones = [
        'Pending' => 'warning', 'Unpaid' => 'warning', 'Processing' => 'info',
        'Payment Received' => 'info', 'Shipped' => 'info', 'Order Being Shipped' => 'info',
        'Delivered' => 'success', 'Complete' => 'success',
        'Canceled' => 'danger', 'Failed' => 'danger',
        'Refunded' => 'neutral', 'Expired' => 'neutral',
    ];
    $statusTone = $statusTones[$order->order_status_name] ?? 'neutral';
    $shippingCourierLabel = $order->courier_label;
@endphp

<x-admin.shell title="Order #{{ $order->id }}">
    <x-admin.page title="Order #{{ $order->id }}" standing="{{ $order->full_name }}">
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
                            <x-admin.badge :tone="$statusTone">{{ $order->order_status_name ?? 'No Status' }}</x-admin.badge>
                            <x-admin.money :mark="$currency" :amount="number_format($order->total, 2)" class="text-2xl font-semibold text-v-text" />
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-x-8 gap-y-3 border-t border-v-border pt-4 text-[13px]">
                        <div>
                            <p class="v-label">Placed</p>
                            <p class="mt-0.5 text-v-secondary">{{ $order->created_at->format('M d, Y · g:i A') }}</p>
                        </div>
                        <div>
                            <p class="v-label">Payment Method</p>
                            <p class="mt-0.5 text-v-secondary">{{ $order->payment_method_label }}</p>
                        </div>
                        <div>
                            <p class="v-label">Shipping</p>
                            <p class="mt-0.5 text-v-secondary">Shipping @if ($shippingCourierLabel)({{ $shippingCourierLabel }})@endif</p>
                        </div>
                        @if ($order->payment_reference)
                            <div>
                                <p class="v-label">Payment Reference</p>
                                <p class="mt-0.5"><span class="v-sub">{{ $order->payment_reference }}</span></p>
                            </div>
                        @endif
                    </div>
                </x-admin.card>

                {{-- 2. CUSTOMER --}}
                <x-admin.card title="Customer">
                    <x-slot:headerActions>
                        @if ($order->customer)
                            <x-admin.button size="sm" variant="secondary" :href="route('admin.customers.edit', $order->customer)">View Profile <x-admin.icon name="arrow-right" class="w-3.5 h-3.5" /></x-admin.button>
                        @endif
                    </x-slot:headerActions>
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-v-accent-weak text-sm font-bold text-v-accent">
                            {{ strtoupper(substr($order->first_name, 0, 1) . substr($order->last_name, 0, 1)) }}
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="text-[13px] font-semibold text-v-text">{{ $order->first_name }} {{ $order->last_name }}</p>
                                @if ($order->customer_group_name)
                                    <x-admin.badge tone="neutral">{{ $order->customer_group_name }}</x-admin.badge>
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
                            <h4 class="mb-3 v-label">Ship To</h4>
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
                            <h4 class="mb-3 v-label">Bill To</h4>
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
                                        @if ($item->image)
                                            <img src="{{ storage_url($item->image) }}" alt="{{ $item->name }}" class="h-10 w-10 shrink-0 rounded-v-md border border-v-border object-cover">
                                        @else
                                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-v-md border border-v-border bg-v-sunken">
                                                <x-admin.icon name="products" class="h-5 w-5 text-v-muted" />
                                            </div>
                                        @endif
                                        <div class="v-item__body">
                                            <p class="v-item__name">{{ $item->name }}</p>
                                            <div class="v-item__meta">
                                                @if ($item->variant_label)
                                                    <span class="v-item__var">{{ $item->variant_label }}</span>
                                                @endif
                                                @if ($item->sku)
                                                    <span class="v-item__sku">{{ $item->sku }}</span>
                                                @endif
                                                @if ($item->is_custom)
                                                    <x-admin.badge tone="warning">Custom</x-admin.badge>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </x-admin.table.cell>
                                <x-admin.table.cell align="right"><x-admin.money :mark="$currency" :amount="number_format($item->price, 2)" /></x-admin.table.cell>
                                <x-admin.table.cell align="right" mono>{{ $item->quantity }}</x-admin.table.cell>
                                <x-admin.table.cell align="right"><x-admin.money :mark="$currency" :amount="number_format($item->price * $item->quantity, 2)" /></x-admin.table.cell>
                            </x-admin.table.row>
                        @endforeach
                        <x-slot:footer>
                            <div class="flex justify-end">
                                <dl class="v-facts v-facts--flush w-full sm:max-w-md">
                                    <dt class="v-label">Subtotal</dt>
                                    <dd><x-admin.money :mark="$currency" :amount="number_format($order->subtotal, 2)" /></dd>
                                    <dt class="v-label">Shipping @if ($shippingCourierLabel)({{ $shippingCourierLabel }})@endif</dt>
                                    <dd>
                                        @if ($order->shipping_cost > 0)
                                            <x-admin.money :mark="$currency" :amount="number_format($order->shipping_cost, 2)" />
                                        @else
                                            Free
                                        @endif
                                    </dd>
                                    @if ($order->coupon_code)
                                        <dt class="v-label">Coupon ({{ $order->coupon_code }})</dt>
                                        <dd><x-admin.money mark="-{{ $currency }}" :amount="number_format($order->coupon_discount, 2)" /></dd>
                                    @endif
                                    <dt class="v-label v-facts__sum">Total</dt>
                                    <dd class="v-facts__sum"><x-admin.money :mark="$currency" :amount="number_format($order->total, 2)" /></dd>
                                </dl>
                            </div>
                        </x-slot:footer>
                    </x-admin.table>
                    @if ($order->comment)
                        <div class="mt-4">
                            <p class="v-label">Message to Store</p>
                            <p class="mt-1 text-[13px] text-v-secondary">{{ $order->comment }}</p>
                        </div>
                    @endif
                    @if ($order->courier_comment)
                        <div class="mt-4">
                            <p class="v-label">Message to Courier</p>
                            <p class="mt-1 text-[13px] text-v-secondary">{{ $order->courier_comment }}</p>
                        </div>
                    @endif
                </x-admin.card>

                {{-- 5. SHIPPING & TRACKING --}}
                <x-admin.panel title="Shipping and Tracking" id="order-tracking" flush>
                    @if ($order->tracking_number)
                        {{-- The courier over its own tracking number: the same
                             stacked cell the fulfillment queue prints, so a
                             shipment reads the same on both screens. --}}
                        <div class="v-panel__body">
                            @if ($order->shipping_courier_name)
                                <span class="v-lead">{{ $order->shipping_courier_name }}</span>
                            @endif
                            @if ($order->tracking_link)
                                <a href="{{ $order->tracking_link }}" target="_blank" class="v-sub">{{ $order->tracking_number }}</a>
                            @else
                                <span class="v-sub">{{ $order->tracking_number }}</span>
                            @endif
                        </div>
                    @else
                        <p class="v-panel__blank">No tracking yet.</p>
                    @endif
                </x-admin.panel>
            </div>

            {{-- RIGHT COLUMN --}}
            <div class="space-y-6">

                {{-- UPDATE STATUS --}}
                <x-admin.card title="Update Order">
                    <form method="POST" action="{{ route('admin.orders.history.store', $order) }}" data-v-form class="-mt-2 space-y-3">
                        @csrf
                        <x-admin.field name="order_status_id" label="Status" required>
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
                <x-admin.panel title="History" id="order-history" flush>
                    @if ($order->histories->count() > 0)
                        {{-- The feed, not a bespoke timeline. A dotted rail with
                             a ring per entry was drawing a second structure over
                             a list that already had one. --}}
                        <ul class="v-feed">
                            @foreach ($order->histories->sortByDesc('created_at') as $history)
                                {{-- Built in PHP, not in the attribute: a Blade
                                     directive inside a component attribute is
                                     literal text, but the interpolation beside
                                     it still runs, so an @if there evaluates
                                     both branches. --}}
                                @php
                                    $when = $history->created_at->format('M d, Y g:i A')
                                        . ($history->created_by_name ? ' by ' . $history->created_by_name : '');
                                @endphp
                                <x-admin.feed.entry icon="orders" tone="v-feed__ic--plain" :when="$when">
                                    <x-admin.badge :tone="$loop->first ? 'success' : 'neutral'">{{ $history->order_status_name ?? '-' }}</x-admin.badge>
                                    @if ($history->notify_customer)
                                        <x-admin.badge tone="info" :dot="false">Notified</x-admin.badge>
                                    @endif
                                    @if ($history->comment)
                                        <x-slot:note>{{ strip_tags($history->comment) }}</x-slot:note>
                                    @endif
                                    <x-slot:action>
                                        <x-admin.button size="sm" variant="ghost" class="v-rowactions__danger" aria-label="Delete history entry"
                                            @click="$dispatch('open-modal', 'delete-history-{{ $history->id }}')">
                                            <x-admin.icon name="trash" class="w-3.5 h-3.5" />
                                        </x-admin.button>
                                    </x-slot:action>
                                </x-admin.feed.entry>
                            @endforeach
                        </ul>
                    @else
                        <p class="v-panel__blank">No history yet.</p>
                    @endif
                </x-admin.panel>
            </div>
        </div>

        @foreach ($order->histories as $history)
            <x-admin.confirm-dialog name="delete-history-{{ $history->id }}" title="Delete this history entry?"
                message="This removes the status-change record from the order's timeline. This cannot be undone."
                confirm="Delete" :action="route('admin.orders.history.destroy', [$order, $history->id])" method="DELETE" />
        @endforeach
    </x-admin.page>
</x-admin.shell>
