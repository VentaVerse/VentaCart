{{--
    A list of the customer's orders as core returns them: number, date,
    status, item count and total in the store's currency. An order the
    customer may still cancel (core's rule) gets a Cancel disclosure with
    core's optional reason; it works without the script. What extensions
    add under a row (App\Support\OrderPanels, `order.row.after`) is read
    once for the page and drawn in a row of its own below the order's.
--}}
@php
    $withCancel = collect($cancellable ?? [])->filter()->isNotEmpty();
    $orderPanels = class_exists(\App\Support\OrderPanels::class) ? \App\Support\OrderPanels::rows($orders) : [];
@endphp
<table class="orders">
    <thead><tr><th scope="col">Order</th><th scope="col">Placed</th><th scope="col">Status</th><th scope="col">Items</th><th scope="col">Total</th><th scope="col"><span class="visually-hidden">Details</span></th></tr></thead>
    @foreach ($orders as $order)
        @php $canCancel = (bool) ($cancellable[$order->id] ?? false); @endphp
        <tbody>
            <tr>
                <th scope="row"><a href="{{ route('account.orders.show', $order) }}">#{{ $order->id }}</a></th>
                <td data-label="Placed">{{ $order->created_at->format('j M Y') }}</td>
                <td data-label="Status">@include('xelga::partials.fixed.status', ['name' => $order->order_status_name ?? 'Pending', 'tone' => $order->status?->tone])</td>
                <td data-label="Items">@if (isset($order->products_count)){{ $order->products_count === 1 ? '1 item' : $order->products_count . ' items' }}@endif</td>
                <td data-label="Total" class="tabular">{{ store_money($order->total) }}</td>
                <td class="orders__go"><a class="link-arrow" href="{{ route('account.orders.show', $order) }}" aria-label="View order #{{ $order->id }}">View<svg class="icon icon--sm" aria-hidden="true"><use href="#i-arrow-right"></use></svg></a></td>
            </tr>
            @isset($orderPanels[$order->id])
                <tr class="orders__panel"><td colspan="6">{{ $orderPanels[$order->id] }}</td></tr>
            @endisset
            @if ($canCancel)
                <tr class="orders__cancel">
                    <td colspan="6">
                        <details class="cancel">
                            <summary class="text-btn cancel__open">Cancel order #{{ $order->id }}</summary>
                            <form class="cancel__form" method="POST" action="{{ route('account.orders.cancel', $order) }}" aria-label="Cancel order #{{ $order->id }}">
                                @csrf
                                <div class="field">
                                    <label class="field__label" for="cancel-reason-{{ $order->id }}">Reason <span class="muted">(optional)</span></label>
                                    <input class="input" id="cancel-reason-{{ $order->id }}" type="text" name="reason" maxlength="500">
                                </div>
                                <div class="cancel__acts">
                                    <button type="submit" class="btn btn--danger btn--sm">Yes, cancel order #{{ $order->id }}</button>
                                    <button type="button" class="text-btn" data-close-details>Keep it</button>
                                </div>
                            </form>
                        </details>
                    </td>
                </tr>
            @endif
        </tbody>
    @endforeach
</table>
