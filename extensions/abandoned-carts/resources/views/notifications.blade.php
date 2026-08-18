<x-abandoned-carts::space title="Cart Notifications" active="notifications">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">Abandoned Carts</h1>
        </div>
    </div>


    <div class="v-settle">
        <form method="GET" action="{{ route('admin.abandoned-carts.notifications') }}" class="v-filterbar">
            <select name="template" aria-label="Filter by template" class="v-control v-w-name">
                <option value="">All templates</option>
                @foreach ($templates as $template)
                    <option value="{{ $template->id }}" @selected(request('template') == $template->id)>{{ $template->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="v-btn v-btn--secondary">Filter</button>
            @if (request('template'))
                <a class="v-link" href="{{ route('admin.abandoned-carts.notifications') }}">Clear</a>
            @endif
        </form>

        <x-admin.panel flush>
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr><th>Customer</th><th>Cart</th><th>Template</th><th class="v-cell-num">Sends</th><th>Last sent</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($notifications as $notification)
                            <tr>
                                <td>
                                    @if ($notification->customer)
                                        {{ $notification->customer->full_name }}
                                        <span class="v-cell-quiet v-block">{{ $notification->customer->email }}</span>
                                    @else
                                        <span class="v-cell-quiet">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($notification->cart)
                                        <a href="{{ route('admin.abandoned-carts.show', $notification->cart_id) }}" class="v-rowlink v-mono">#{{ $notification->cart_id }}</a>
                                        <span class="v-cell-quiet">({{ $notification->cart->items->count() }} items)</span>
                                    @else
                                        <span class="v-cell-quiet">Deleted</span>
                                    @endif
                                </td>
                                <td class="v-cell-quiet">{{ $notification->emailTemplate->name ?? '-' }}</td>
                                <td class="v-cell-num v-mono">{{ $notification->send_count }}</td>
                                <td class="v-cell-quiet">{{ $notification->last_sent_at?->diffForHumans() ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="v-empty"><p class="v-empty__title">No notifications sent yet</p></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.panel>

        <x-admin.pager :paginator="$notifications" />
    </div>
</x-abandoned-carts::space>
