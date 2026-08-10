<x-admin.shell title="Abandoned Cart Notifications">
    <x-admin.page title="Notifications">
        @include('admin.sales.abandoned-carts.partials.nav')

        <div>
            <x-admin.filters :action="route('admin.abandoned-carts.notifications')">
                <x-admin.field name="template" label="Template" class="w-64">
                    <x-admin.select name="template">
                        <option value="">All Templates</option>
                        @foreach ($templates as $template)
                            <option value="{{ $template->id }}" {{ request('template') == $template->id ? 'selected' : '' }}>{{ $template->name }}</option>
                        @endforeach
                    </x-admin.select>
                </x-admin.field>
                <x-admin.button type="submit" variant="secondary">Filter</x-admin.button>
                <a href="{{ route('admin.abandoned-carts.notifications') }}" class="v-filters__reset">Clear</a>
            </x-admin.filters>

                <x-admin.table>
                    <x-slot name="head">
                        <x-admin.table.th>Customer</x-admin.table.th>
                        <x-admin.table.th>Cart</x-admin.table.th>
                        <x-admin.table.th>Template</x-admin.table.th>
                        <x-admin.table.th>Sends</x-admin.table.th>
                        <x-admin.table.th>Last Sent</x-admin.table.th>
                    </x-slot>
                    @forelse ($notifications as $notification)
                        <x-admin.table.row>
                            <x-admin.table.cell>
                                @if ($notification->customer)
                                    <span class="v-lead">{{ $notification->customer->full_name }}</span>
                                    <span class="v-sub">{{ $notification->customer->email }}</span>
                                @else
                                    <span class="v-none">-</span>
                                @endif
                            </x-admin.table.cell>
                            <x-admin.table.cell>
                                @if ($notification->cart)
                                    <a href="{{ route('admin.abandoned-carts.show', $notification->cart_id) }}" class="v-ref">#{{ $notification->cart_id }}</a>
                                    <span class="text-v-muted">({{ $notification->cart->items->count() }} items)</span>
                                @else
                                    <span class="v-none">Deleted</span>
                                @endif
                            </x-admin.table.cell>
                            <x-admin.table.cell class="text-v-secondary">{{ $notification->emailTemplate->name ?? '-' }}</x-admin.table.cell>
                            <x-admin.table.cell mono class="text-v-text">{{ $notification->send_count }}</x-admin.table.cell>
                            <x-admin.table.cell class="text-v-secondary">{{ $notification->last_sent_at?->diffForHumans() ?? '-' }}</x-admin.table.cell>
                        </x-admin.table.row>
                    @empty
                        <tr><td colspan="5"><x-admin.empty-state title="No notifications sent yet" /></td></tr>
                    @endforelse

                    <x-slot name="footer">
                        @if ($notifications->hasPages())
                            {{ $notifications->links() }}
                        @endif
                    </x-slot>
                </x-admin.table>
        </div>
    </x-admin.page>
</x-admin.shell>
