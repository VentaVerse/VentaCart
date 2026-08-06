<x-admin.shell title="Abandoned Cart Notifications">
    <x-admin.page title="Notifications" :breadcrumb="[['label' => 'Abandoned Carts', 'url' => route('admin.abandoned-carts.settings')]]">
        <div class="flex flex-col gap-6 lg:flex-row lg:gap-8">
            @include('admin.abandoned-carts.partials.nav')

            <div class="min-w-0 flex-1 space-y-6">
                {{-- Filter --}}
                <x-admin.card>
                    <form method="GET" action="{{ route('admin.abandoned-carts.notifications') }}" class="flex flex-wrap items-end gap-4">
                        <div class="w-64">
                            <x-admin.field name="template" label="Template">
                                <x-admin.select name="template">
                                    <option value="">All Templates</option>
                                    @foreach ($templates as $template)
                                        <option value="{{ $template->id }}" {{ request('template') == $template->id ? 'selected' : '' }}>{{ $template->name }}</option>
                                    @endforeach
                                </x-admin.select>
                            </x-admin.field>
                        </div>
                        <x-admin.button type="submit">Filter</x-admin.button>
                        <x-admin.button variant="secondary" href="{{ route('admin.abandoned-carts.notifications') }}">Clear</x-admin.button>
                    </form>
                </x-admin.card>

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
                                    <div class="font-medium text-v-text">{{ $notification->customer->full_name }}</div>
                                    <div class="text-xs text-v-muted">{{ $notification->customer->email }}</div>
                                @else
                                    <span class="text-v-muted">—</span>
                                @endif
                            </x-admin.table.cell>
                            <x-admin.table.cell>
                                @if ($notification->cart)
                                    <a href="{{ route('admin.abandoned-carts.show', $notification->cart_id) }}" class="text-v-accent hover:underline">
                                        #{{ $notification->cart_id }} ({{ $notification->cart->items->count() }} items)
                                    </a>
                                @else
                                    <span class="text-v-muted">Deleted</span>
                                @endif
                            </x-admin.table.cell>
                            <x-admin.table.cell class="text-v-secondary">{{ $notification->emailTemplate->name ?? '—' }}</x-admin.table.cell>
                            <x-admin.table.cell mono class="text-v-text">{{ $notification->send_count }}</x-admin.table.cell>
                            <x-admin.table.cell class="text-v-secondary">{{ $notification->last_sent_at?->diffForHumans() ?? '—' }}</x-admin.table.cell>
                        </x-admin.table.row>
                    @empty
                        <tr><td colspan="5" class="px-4 py-2"><x-admin.empty-state icon="cart" title="No notifications sent yet" /></td></tr>
                    @endforelse

                    <x-slot name="footer">
                        @if ($notifications->hasPages())
                            {{ $notifications->links() }}
                        @endif
                    </x-slot>
                </x-admin.table>
            </div>
        </div>
    </x-admin.page>
</x-admin.shell>
