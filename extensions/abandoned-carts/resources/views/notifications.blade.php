<x-admin3.shell title="Cart Notifications" active="sales">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Abandoned Carts</h1>
        </div>
    </div>

    @include('abandoned-carts::partials.tabs')

    <div class="v3-settle">
        <form method="GET" action="{{ route('admin.abandoned-carts.notifications') }}" class="v3-filterbar">
            <select name="template" aria-label="Filter by template" class="v3-control v3-w-name">
                <option value="">All templates</option>
                @foreach ($templates as $template)
                    <option value="{{ $template->id }}" @selected(request('template') == $template->id)>{{ $template->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="v3-btn v3-btn--secondary">Filter</button>
            @if (request('template'))
                <a class="v3-link" href="{{ route('admin.abandoned-carts.notifications') }}">Clear</a>
            @endif
        </form>

        <x-admin3.panel flush>
            <div class="v3-tablewrap">
                <table class="v3-table">
                    <thead>
                        <tr><th>Customer</th><th>Cart</th><th>Template</th><th class="v3-cell-num">Sends</th><th>Last sent</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($notifications as $notification)
                            <tr>
                                <td>
                                    @if ($notification->customer)
                                        {{ $notification->customer->full_name }}
                                        <span class="v3-cell-quiet v3-block">{{ $notification->customer->email }}</span>
                                    @else
                                        <span class="v3-cell-quiet">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($notification->cart)
                                        <a href="{{ route('admin.abandoned-carts.show', $notification->cart_id) }}" class="v3-rowlink v3-mono">#{{ $notification->cart_id }}</a>
                                        <span class="v3-cell-quiet">({{ $notification->cart->items->count() }} items)</span>
                                    @else
                                        <span class="v3-cell-quiet">Deleted</span>
                                    @endif
                                </td>
                                <td class="v3-cell-quiet">{{ $notification->emailTemplate->name ?? '-' }}</td>
                                <td class="v3-cell-num v3-mono">{{ $notification->send_count }}</td>
                                <td class="v3-cell-quiet">{{ $notification->last_sent_at?->diffForHumans() ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="v3-empty"><p class="v3-empty__title">No notifications sent yet</p></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin3.panel>

        <x-admin3.pager :paginator="$notifications" />
    </div>
</x-admin3.shell>
