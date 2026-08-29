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

<x-admin.shell title="Order #{{ $order->id }}" active="sales">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.orders.index') }}">Orders</a>
                <span aria-hidden="true">/</span>
                <span>#{{ $order->id }}</span>
            </div>
            <h1 class="v-head__title">Order #{{ $order->id }}
                <span class="v-badge v-badge--{{ $statusTone }}">{{ $order->order_status_name ?? 'No Status' }}</span>
            </h1>
            <p class="v-head__standing">{{ $order->full_name }} · {{ $order->created_at->format('M d, Y g:i A') }}</p>
        </div>
        <div class="v-head__actions">
            <a href="{{ route('admin.orders.edit', $order) }}" class="v-btn v-btn--secondary">Edit</a>
        </div>
    </div>

    <div class="v-editcols">
        {{-- LEFT: the order itself --}}
        <div class="v-settle">

            <x-admin.panel title="Order">
                <div class="v-factrow">
                    <div>
                        <div class="v-label">Total</div>
                        <div class="v-bigmoney v-mono">{{ $currency }}{{ number_format($order->total, 2) }}</div>
                    </div>
                    <div>
                        <div class="v-label">Payment</div>
                        <div>{{ $order->payment_method_label }}</div>
                        @if ($order->payment_reference)
                            <div class="v-cell-quiet v-mono">{{ $order->payment_reference }}</div>
                        @endif
                    </div>
                    <div>
                        <div class="v-label">Shipping</div>
                        <div>Shipping @if ($shippingCourierLabel)({{ $shippingCourierLabel }})@endif</div>
                    </div>
                </div>
            </x-admin.panel>

            <x-admin.panel title="Customer">
                <x-slot:acts>
                    @if ($order->customer)
                        <a href="{{ route('admin.customers.edit', $order->customer) }}" class="v-btn v-btn--secondary v-btn--sm">View profile</a>
                    @endif
                </x-slot:acts>
                <div class="v-namerow">
                    <strong>{{ $order->first_name }} {{ $order->last_name }}</strong>
                    @if ($order->customer_group_name)
                        <span class="v-badge v-badge--quiet">{{ $order->customer_group_name }}</span>
                    @endif
                </div>
                <a href="mailto:{{ $order->email }}" class="v-link">{{ $order->email }}</a>
            </x-admin.panel>

            <x-admin.panel title="Addresses">
                <div class="v-grid v-grid--2">
                    <div>
                        <div class="v-label v-gap-sm">Ship to</div>
                        <p><strong>{{ $order->shipping_first_name }} {{ $order->shipping_last_name }}</strong></p>
                        <p class="v-cell-quiet">{{ $order->shipping_address_1 }}</p>
                        @if ($order->shipping_barangay)<p class="v-cell-quiet">{{ $order->shipping_barangay }}</p>@endif
                        @if ($order->shipping_district)<p class="v-cell-quiet">{{ $order->shipping_district }}</p>@endif
                        <p class="v-cell-quiet">{{ $order->shipping_city }}@if ($order->shipping_state), {{ $order->shipping_state }}@endif {{ $order->shipping_postcode }}</p>
                        @if ($order->shipping_country_name ?? $order->shippingCountry?->name)<p class="v-cell-quiet">{{ $order->shipping_country_name ?? $order->shippingCountry?->name }}</p>@endif
                        @if ($order->shipping_phone || $order->phone)
                            <p class="v-mono v-gap-sm">{{ $order->shipping_phone ?: $order->phone }}</p>
                        @endif
                    </div>
                    <div>
                        <div class="v-label v-gap-sm">Bill to</div>
                        @if ($order->billing_first_name)
                            <p><strong>{{ $order->billing_first_name }} {{ $order->billing_last_name }}</strong></p>
                            <p class="v-cell-quiet">{{ $order->billing_address_1 }}</p>
                            @if ($order->billing_barangay)<p class="v-cell-quiet">{{ $order->billing_barangay }}</p>@endif
                            @if ($order->billing_district)<p class="v-cell-quiet">{{ $order->billing_district }}</p>@endif
                            <p class="v-cell-quiet">{{ $order->billing_city }}@if ($order->billing_state), {{ $order->billing_state }}@endif {{ $order->billing_postcode }}</p>
                            @if ($order->billing_country_name ?? $order->billingCountry?->name)<p class="v-cell-quiet">{{ $order->billing_country_name ?? $order->billingCountry?->name }}</p>@endif
                            @if ($order->billing_phone)
                                <p class="v-mono v-gap-sm">{{ $order->billing_phone }}</p>
                            @endif
                        @else
                            <p class="v-cell-quiet">Same as shipping</p>
                        @endif
                    </div>
                </div>
            </x-admin.panel>

            <x-admin.panel title="Products" flush>
                <div class="v-tablewrap">
                    <table class="v-table">
                        <thead>
                            <tr><th>Item</th><th class="v-cell-num">Price</th><th class="v-cell-num">Qty</th><th class="v-cell-num">Total</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($order->products as $item)
                                <tr>
                                    <td>
                                        <span class="v-namerow">
                                            @if ($item->image)
                                                <img src="{{ thumb_url($item->image) }}" alt="" class="v-thumb" loading="lazy" decoding="async" width="40" height="40">
                                            @else
                                                <span class="v-thumb v-thumb--blank"><x-admin.icon name="products" /></span>
                                            @endif
                                            <span>
                                                {{-- A line whose product still exists opens that product; a
                                                     custom line or a deleted product stays the name it was sold as. --}}
                                                @if ($item->product)
                                                    <a href="{{ route('admin.products.edit', $item->product_id) }}" class="v-rowlink">{{ $item->name }}</a>
                                                @else
                                                    {{ $item->name }}
                                                @endif
                                                <span class="v-cell-quiet v-block">
                                                    @if ($item->variant_label){{ $item->variant_label }}@endif
                                                    @if ($item->sku) · {{ $item->sku }}@endif
                                                </span>
                                                @if ($item->is_custom)
                                                    <span class="v-badge v-badge--warn">Custom</span>
                                                @endif
                                            </span>
                                        </span>
                                    </td>
                                    <td class="v-cell-num v-mono">{{ $currency }}{{ number_format($item->price, 2) }}</td>
                                    <td class="v-cell-num v-mono">{{ $item->quantity }}</td>
                                    <td class="v-cell-num v-mono">{{ $currency }}{{ number_format($item->price * $item->quantity, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="v-ledger">
                    <dl>
                        <dt>Subtotal</dt>
                        <dd class="v-mono">{{ $currency }}{{ number_format($order->subtotal, 2) }}</dd>
                        <dt>Shipping @if ($shippingCourierLabel)({{ $shippingCourierLabel }})@endif</dt>
                        <dd class="v-mono">@if ($order->shipping_cost > 0){{ $currency }}{{ number_format($order->shipping_cost, 2) }}@else Free @endif</dd>
                        @if ($order->coupon_code)
                            <dt>Coupon ({{ $order->coupon_code }})</dt>
                            <dd class="v-mono">-{{ $currency }}{{ number_format($order->coupon_discount, 2) }}</dd>
                        @endif
                        {{-- Other totals-pipeline lines (store credit, future
                             fees). Coupon skipped: its columns render above. --}}
                        @foreach (collect($order->total_lines ?? [])->where('code', '!=', 'coupon') as $line)
                            @if (($line['amount'] ?? 0) != 0)
                                <dt>{{ $line['label'] }}</dt>
                                <dd class="v-mono">{{ $line['amount'] < 0 ? '-' : '' }}{{ $currency }}{{ number_format(abs($line['amount']), 2) }}</dd>
                            @endif
                        @endforeach
                        <dt class="v-facts__sum">Total</dt>
                        <dd class="v-facts__sum v-mono">{{ $currency }}{{ number_format($order->total, 2) }}</dd>
                    </dl>
                </div>
                @if ($order->comment || $order->courier_comment)
                    <div class="v-ledger__notes">
                        @if ($order->comment)
                            <div class="v-label">Message to Store</div>
                            <p class="v-cell-quiet">{{ $order->comment }}</p>
                        @endif
                        @if ($order->courier_comment)
                            <div class="v-label {{ $order->comment ? 'v-gap-sm' : '' }}">Message to Courier</div>
                            <p class="v-cell-quiet">{{ $order->courier_comment }}</p>
                        @endif
                    </div>
                @endif
            </x-admin.panel>

            <x-admin.panel title="Shipping and Tracking">
                @if ($order->tracking_number)
                    <div class="v-namerow">
                        @if ($order->shipping_courier_name)
                            <strong>{{ $order->shipping_courier_name }}</strong>
                        @endif
                        @if ($order->tracking_link)
                            <a href="{{ $order->tracking_link }}" target="_blank" rel="noopener" class="v-link v-mono">{{ $order->tracking_number }}</a>
                        @else
                            <span class="v-mono">{{ $order->tracking_number }}</span>
                        @endif
                    </div>
                @else
                    <p class="v-cell-quiet">No tracking yet.</p>
                @endif
            </x-admin.panel>
        </div>

        {{-- RIGHT: the working column --}}
        <div class="v-settle">
            {{-- Every submit here CREATES a history entry, so the button is
                 the verb and the save bar stays out of it. --}}
            <x-admin.panel title="Update order">
                <form method="POST" action="{{ route('admin.orders.history.store', $order) }}">
                    @csrf
                    <x-admin.field name="order_status_id" label="Status" required>
                        <select name="order_status_id" required class="v-control">
                            @foreach ($statuses as $status)
                                <option value="{{ $status->id }}" @selected($order->order_status_id == $status->id)>{{ $status->name }}</option>
                            @endforeach
                        </select>
                    </x-admin.field>
                    <div class="v-gap-sm">
                        <x-admin.field name="shipping_courier_id" label="Courier">
                            <select name="shipping_courier_id" class="v-control">
                                <option value="">Select courier…</option>
                                @foreach ($couriers as $courier)
                                    <option value="{{ $courier->id }}" @selected($order->shipping_courier_id == $courier->id)>{{ $courier->name }}</option>
                                @endforeach
                            </select>
                        </x-admin.field>
                    </div>
                    <div class="v-gap-sm">
                        <x-admin.field name="tracking_number" label="Tracking number">
                            <input type="text" name="tracking_number" value="{{ $order->tracking_number }}" class="v-control v-mono">
                        </x-admin.field>
                    </div>
                    <div class="v-gap-sm">
                        <x-admin.field name="comment" label="Comment">
                            <input type="text" name="comment" class="v-control">
                        </x-admin.field>
                    </div>
                    <div class="v-gap v-actrow">
                        <label class="v-check">
                            <input type="checkbox" name="notify_customer" value="1">
                            Notify customer
                        </label>
                        <button type="submit" class="v-btn v-btn--primary v-btn--sm">Update</button>
                    </div>
                </form>
            </x-admin.panel>

            <x-admin.panel title="History" flush>
                @if ($order->histories->count() > 0)
                    <ul class="v-feed">
                        @foreach ($order->histories->sortByDesc('created_at') as $history)
                            <li class="v-feed__entry">
                                <div class="v-feed__row">
                                    <span class="v-badge {{ $loop->first ? 'v-badge--good' : 'v-badge--quiet' }}">{{ $history->order_status_name ?? '-' }}</span>
                                    @if ($history->notify_customer)
                                        <span class="v-badge v-badge--info">Notified</span>
                                    @endif
                                    <button type="button" class="v-row__x v-feed__x" aria-label="Delete history entry"
                                            @click="$dispatch('v-confirm', 'delete-history-{{ $history->id }}')">
                                        <x-admin.icon name="trash" />
                                    </button>
                                </div>
                                @if ($history->comment)
                                    <p class="v-feed__note">{{ strip_tags($history->comment) }}</p>
                                @endif
                                <p class="v-feed__when">{{ $history->created_at->format('M d, Y g:i A') }}{{ $history->created_by_name ? ' by ' . $history->created_by_name : '' }}</p>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="v-empty"><p class="v-empty__title">No history yet</p></div>
                @endif
            </x-admin.panel>
        </div>
    </div>

    @foreach ($order->histories as $history)
        <x-admin.confirm name="delete-history-{{ $history->id }}" title="Delete this history entry?"
            message="This removes the status-change record from the order's timeline. This cannot be undone."
            confirm="Delete" :action="route('admin.orders.history.destroy', [$order, $history->id])" method="DELETE" />
    @endforeach
</x-admin.shell>
