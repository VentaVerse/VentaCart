@php
    $currency = $defaultCurrency->symbol ?? '₱';
    $statusTones = [
        'Pending' => 'warn', 'Unpaid' => 'warn', 'Processing' => 'info',
        'Payment Received' => 'info', 'Shipped' => 'info', 'Order Being Shipped' => 'info',
        'Delivered' => 'good', 'Complete' => 'good',
        'Canceled' => 'bad', 'Failed' => 'bad',
        'Refunded' => 'quiet', 'Expired' => 'quiet',
    ];
    $statusTone = $statusTones[$order->order_status_name] ?? 'quiet';
    $shippingCourierLabel = $order->courier_label;
@endphp

<x-admin3.shell title="Order #{{ $order->id }}" active="sales">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.orders.index') }}">Orders</a>
                <span aria-hidden="true">/</span>
                <span>#{{ $order->id }}</span>
            </div>
            <h1 class="v3-head__title">Order #{{ $order->id }}
                <span class="v3-badge v3-badge--{{ $statusTone }}">{{ $order->order_status_name ?? 'No Status' }}</span>
            </h1>
            <p class="v3-head__standing">{{ $order->full_name }} · {{ $order->created_at->format('M d, Y g:i A') }}</p>
        </div>
        <div class="v3-head__actions">
            <a href="{{ route('admin.orders.edit', $order) }}" class="v3-btn v3-btn--secondary">Edit</a>
        </div>
    </div>

    <div class="v3-editcols">
        {{-- LEFT: the order itself --}}
        <div class="v3-settle">

            <x-admin3.panel title="Order">
                <div class="v3-factrow">
                    <div>
                        <div class="v3-label">Total</div>
                        <div class="v3-bigmoney v3-mono">{{ $currency }}{{ number_format($order->total, 2) }}</div>
                    </div>
                    <div>
                        <div class="v3-label">Payment</div>
                        <div>{{ $order->payment_method_label }}</div>
                        @if ($order->payment_reference)
                            <div class="v3-cell-quiet v3-mono">{{ $order->payment_reference }}</div>
                        @endif
                    </div>
                    <div>
                        <div class="v3-label">Shipping</div>
                        <div>Shipping @if ($shippingCourierLabel)({{ $shippingCourierLabel }})@endif</div>
                    </div>
                </div>
            </x-admin3.panel>

            <x-admin3.panel title="Customer">
                <x-slot:acts>
                    @if ($order->customer)
                        <a href="{{ route('admin.customers.edit', $order->customer) }}" class="v3-btn v3-btn--secondary v3-btn--sm">View profile</a>
                    @endif
                </x-slot:acts>
                <div class="v3-namerow">
                    <strong>{{ $order->first_name }} {{ $order->last_name }}</strong>
                    @if ($order->customer_group_name)
                        <span class="v3-badge v3-badge--quiet">{{ $order->customer_group_name }}</span>
                    @endif
                </div>
                <a href="mailto:{{ $order->email }}" class="v3-link">{{ $order->email }}</a>
            </x-admin3.panel>

            <x-admin3.panel title="Addresses">
                <div class="v3-grid v3-grid--2">
                    <div>
                        <div class="v3-label v3-gap-sm">Ship to</div>
                        <p><strong>{{ $order->shipping_first_name }} {{ $order->shipping_last_name }}</strong></p>
                        <p class="v3-cell-quiet">{{ $order->shipping_address_1 }}</p>
                        @if ($order->shipping_barangay)<p class="v3-cell-quiet">{{ $order->shipping_barangay }}</p>@endif
                        @if ($order->shipping_district)<p class="v3-cell-quiet">{{ $order->shipping_district }}</p>@endif
                        <p class="v3-cell-quiet">{{ $order->shipping_city }}@if ($order->shipping_state), {{ $order->shipping_state }}@endif {{ $order->shipping_postcode }}</p>
                        @if ($order->shipping_country_name ?? $order->shippingCountry?->name)<p class="v3-cell-quiet">{{ $order->shipping_country_name ?? $order->shippingCountry?->name }}</p>@endif
                        @if ($order->shipping_phone || $order->phone)
                            <p class="v3-mono v3-gap-sm">{{ $order->shipping_phone ?: $order->phone }}</p>
                        @endif
                    </div>
                    <div>
                        <div class="v3-label v3-gap-sm">Bill to</div>
                        @if ($order->billing_first_name)
                            <p><strong>{{ $order->billing_first_name }} {{ $order->billing_last_name }}</strong></p>
                            <p class="v3-cell-quiet">{{ $order->billing_address_1 }}</p>
                            @if ($order->billing_barangay)<p class="v3-cell-quiet">{{ $order->billing_barangay }}</p>@endif
                            @if ($order->billing_district)<p class="v3-cell-quiet">{{ $order->billing_district }}</p>@endif
                            <p class="v3-cell-quiet">{{ $order->billing_city }}@if ($order->billing_state), {{ $order->billing_state }}@endif {{ $order->billing_postcode }}</p>
                            @if ($order->billing_country_name ?? $order->billingCountry?->name)<p class="v3-cell-quiet">{{ $order->billing_country_name ?? $order->billingCountry?->name }}</p>@endif
                            @if ($order->billing_phone)
                                <p class="v3-mono v3-gap-sm">{{ $order->billing_phone }}</p>
                            @endif
                        @else
                            <p class="v3-cell-quiet">Same as shipping</p>
                        @endif
                    </div>
                </div>
            </x-admin3.panel>

            <x-admin3.panel title="Products" flush>
                <div class="v3-tablewrap">
                    <table class="v3-table">
                        <thead>
                            <tr><th>Item</th><th class="v3-cell-num">Price</th><th class="v3-cell-num">Qty</th><th class="v3-cell-num">Total</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($order->products as $item)
                                <tr>
                                    <td>
                                        <span class="v3-namerow">
                                            @if ($item->image)
                                                <img src="{{ storage_url($item->image) }}" alt="" class="v3-thumb">
                                            @endif
                                            <span>
                                                {{ $item->name }}
                                                <span class="v3-cell-quiet v3-block">
                                                    @if ($item->variant_label){{ $item->variant_label }}@endif
                                                    @if ($item->sku) · {{ $item->sku }}@endif
                                                </span>
                                                @if ($item->is_custom)
                                                    <span class="v3-badge v3-badge--warn">Custom</span>
                                                @endif
                                            </span>
                                        </span>
                                    </td>
                                    <td class="v3-cell-num v3-mono">{{ $currency }}{{ number_format($item->price, 2) }}</td>
                                    <td class="v3-cell-num v3-mono">{{ $item->quantity }}</td>
                                    <td class="v3-cell-num v3-mono">{{ $currency }}{{ number_format($item->price * $item->quantity, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="v3-ledger">
                    <dl>
                        <dt>Subtotal</dt>
                        <dd class="v3-mono">{{ $currency }}{{ number_format($order->subtotal, 2) }}</dd>
                        <dt>Shipping @if ($shippingCourierLabel)({{ $shippingCourierLabel }})@endif</dt>
                        <dd class="v3-mono">@if ($order->shipping_cost > 0){{ $currency }}{{ number_format($order->shipping_cost, 2) }}@else Free @endif</dd>
                        @if ($order->coupon_code)
                            <dt>Coupon ({{ $order->coupon_code }})</dt>
                            <dd class="v3-mono">-{{ $currency }}{{ number_format($order->coupon_discount, 2) }}</dd>
                        @endif
                        <dt class="v3-facts__sum">Total</dt>
                        <dd class="v3-facts__sum v3-mono">{{ $currency }}{{ number_format($order->total, 2) }}</dd>
                    </dl>
                </div>
                @if ($order->comment || $order->courier_comment)
                    <div class="v3-ledger__notes">
                        @if ($order->comment)
                            <div class="v3-label">Message to Store</div>
                            <p class="v3-cell-quiet">{{ $order->comment }}</p>
                        @endif
                        @if ($order->courier_comment)
                            <div class="v3-label {{ $order->comment ? 'v3-gap-sm' : '' }}">Message to Courier</div>
                            <p class="v3-cell-quiet">{{ $order->courier_comment }}</p>
                        @endif
                    </div>
                @endif
            </x-admin3.panel>

            <x-admin3.panel title="Shipping and Tracking">
                @if ($order->tracking_number)
                    <div class="v3-namerow">
                        @if ($order->shipping_courier_name)
                            <strong>{{ $order->shipping_courier_name }}</strong>
                        @endif
                        @if ($order->tracking_link)
                            <a href="{{ $order->tracking_link }}" target="_blank" rel="noopener" class="v3-link v3-mono">{{ $order->tracking_number }}</a>
                        @else
                            <span class="v3-mono">{{ $order->tracking_number }}</span>
                        @endif
                    </div>
                @else
                    <p class="v3-cell-quiet">No tracking yet.</p>
                @endif
            </x-admin3.panel>
        </div>

        {{-- RIGHT: the working column --}}
        <div class="v3-settle">
            {{-- Every submit here CREATES a history entry, so the button is
                 the verb and the save bar stays out of it. --}}
            <x-admin3.panel title="Update order">
                <form method="POST" action="{{ route('admin.orders.history.store', $order) }}">
                    @csrf
                    <x-admin3.field name="order_status_id" label="Status" required>
                        <select name="order_status_id" required class="v3-control">
                            @foreach ($statuses as $status)
                                <option value="{{ $status->id }}" @selected($order->order_status_id == $status->id)>{{ $status->name }}</option>
                            @endforeach
                        </select>
                    </x-admin3.field>
                    <div class="v3-gap-sm">
                        <x-admin3.field name="shipping_courier_id" label="Courier">
                            <select name="shipping_courier_id" class="v3-control">
                                <option value="">Select courier…</option>
                                @foreach ($couriers as $courier)
                                    <option value="{{ $courier->id }}" @selected($order->shipping_courier_id == $courier->id)>{{ $courier->name }}</option>
                                @endforeach
                            </select>
                        </x-admin3.field>
                    </div>
                    <div class="v3-gap-sm">
                        <x-admin3.field name="tracking_number" label="Tracking number">
                            <input type="text" name="tracking_number" value="{{ $order->tracking_number }}" class="v3-control v3-mono">
                        </x-admin3.field>
                    </div>
                    <div class="v3-gap-sm">
                        <x-admin3.field name="comment" label="Comment">
                            <input type="text" name="comment" class="v3-control">
                        </x-admin3.field>
                    </div>
                    <div class="v3-gap v3-actrow">
                        <label class="v3-check">
                            <input type="checkbox" name="notify_customer" value="1">
                            Notify customer
                        </label>
                        <button type="submit" class="v3-btn v3-btn--primary v3-btn--sm">Update</button>
                    </div>
                </form>
            </x-admin3.panel>

            <x-admin3.panel title="History" flush>
                @if ($order->histories->count() > 0)
                    <ul class="v3-feed">
                        @foreach ($order->histories->sortByDesc('created_at') as $history)
                            <li class="v3-feed__entry">
                                <div class="v3-feed__row">
                                    <span class="v3-badge {{ $loop->first ? 'v3-badge--good' : 'v3-badge--quiet' }}">{{ $history->order_status_name ?? '-' }}</span>
                                    @if ($history->notify_customer)
                                        <span class="v3-badge v3-badge--info">Notified</span>
                                    @endif
                                    <button type="button" class="v3-row__x v3-feed__x" aria-label="Delete history entry"
                                            @click="$dispatch('v3-confirm', 'delete-history-{{ $history->id }}')">
                                        <x-admin3.icon name="trash" />
                                    </button>
                                </div>
                                @if ($history->comment)
                                    <p class="v3-feed__note">{{ strip_tags($history->comment) }}</p>
                                @endif
                                <p class="v3-feed__when">{{ $history->created_at->format('M d, Y g:i A') }}{{ $history->created_by_name ? ' by ' . $history->created_by_name : '' }}</p>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="v3-empty"><p class="v3-empty__title">No history yet</p></div>
                @endif
            </x-admin3.panel>
        </div>
    </div>

    @foreach ($order->histories as $history)
        <x-admin3.confirm name="delete-history-{{ $history->id }}" title="Delete this history entry?"
            message="This removes the status-change record from the order's timeline. This cannot be undone."
            confirm="Delete" :action="route('admin.orders.history.destroy', [$order, $history->id])" method="DELETE" />
    @endforeach
</x-admin3.shell>
