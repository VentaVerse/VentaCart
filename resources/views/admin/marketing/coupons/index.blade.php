<x-admin.shell title="Coupons">
    <x-admin.page title="Coupons">
        <x-slot:actions>
            <x-admin.button variant="primary" href="{{ route('admin.coupons.create') }}">
                <x-admin.icon name="plus" class="w-4 h-4" /> Add Coupon
            </x-admin.button>
        </x-slot:actions>

        <div class="space-y-5">
            {{-- Filters --}}
            <x-admin.card>
                <form method="GET" action="{{ route('admin.coupons.index') }}" class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[220px] flex-1">
                        <x-admin.field name="search" label="Search">
                            <x-admin.input type="text" name="search" :value="request('search')" placeholder="Search code or name..." />
                        </x-admin.field>
                    </div>
                    <div class="w-44">
                        <x-admin.field name="type" label="Type">
                            <x-admin.select name="type">
                                <option value="">All Types</option>
                                <option value="percent" {{ request('type') === 'percent' ? 'selected' : '' }}>Percent</option>
                                <option value="fixed" {{ request('type') === 'fixed' ? 'selected' : '' }}>Fixed</option>
                                <option value="free_shipping" {{ request('type') === 'free_shipping' ? 'selected' : '' }}>Free Shipping</option>
                            </x-admin.select>
                        </x-admin.field>
                    </div>
                    <div class="w-40">
                        <x-admin.field name="status" label="Status">
                            <x-admin.select name="status">
                                <option value="">All Statuses</option>
                                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                <option value="expired" {{ request('status') === 'expired' ? 'selected' : '' }}>Expired</option>
                            </x-admin.select>
                        </x-admin.field>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-admin.button type="submit" variant="primary">Filter</x-admin.button>
                        @if (request()->anyFilled(['search', 'type', 'status']))
                            <a href="{{ route('admin.coupons.index') }}" class="text-[13px] text-v-muted underline hover:text-v-text">Clear</a>
                        @endif
                    </div>
                </form>
            </x-admin.card>

            {{-- Table --}}
            <x-admin.table>
                <x-slot:head>
                    <x-admin.table.th>Code</x-admin.table.th>
                    <x-admin.table.th>Name</x-admin.table.th>
                    <x-admin.table.th>Type</x-admin.table.th>
                    <x-admin.table.th align="right">Discount</x-admin.table.th>
                    <x-admin.table.th>Usage</x-admin.table.th>
                    <x-admin.table.th>Status</x-admin.table.th>
                    <x-admin.table.th>Dates</x-admin.table.th>
                    <x-admin.table.th align="right">Actions</x-admin.table.th>
                </x-slot:head>
                @forelse ($coupons as $coupon)
                    <x-admin.table.row href="{{ route('admin.coupons.edit', $coupon) }}">
                        <x-admin.table.cell mono><span class="font-medium text-v-text">{{ $coupon->code }}</span></x-admin.table.cell>
                        <x-admin.table.cell>{{ $coupon->name }}</x-admin.table.cell>
                        <x-admin.table.cell>
                            @if ($coupon->type === 'percent')
                                <x-admin.badge tone="info">Percent</x-admin.badge>
                            @elseif ($coupon->type === 'fixed')
                                <x-admin.badge tone="warning">Fixed</x-admin.badge>
                            @else
                                <x-admin.badge tone="success">Free Shipping</x-admin.badge>
                            @endif
                        </x-admin.table.cell>
                        <x-admin.table.cell align="right" mono>
                            @if ($coupon->type === 'percent')
                                {{ rtrim(rtrim(number_format($coupon->discount_value, 2), '0'), '.') }}%
                            @elseif ($coupon->type === 'fixed')
                                {{ number_format($coupon->discount_value, 2) }}
                            @else
                                -
                            @endif
                        </x-admin.table.cell>
                        <x-admin.table.cell>
                            {{ $coupon->times_used }}{{ $coupon->usage_limit ? ' / ' . $coupon->usage_limit : '' }}
                        </x-admin.table.cell>
                        <x-admin.table.cell>
                            @if ($coupon->is_expired)
                                <x-admin.badge tone="danger">Expired</x-admin.badge>
                            @elseif ($coupon->is_active)
                                <x-admin.badge tone="success">Active</x-admin.badge>
                            @else
                                <x-admin.badge tone="neutral">Inactive</x-admin.badge>
                            @endif
                        </x-admin.table.cell>
                        <x-admin.table.cell>
                            @if ($coupon->date_start || $coupon->date_end)
                                <span class="text-v-secondary">
                                    {{ $coupon->date_start?->format('M d, Y') ?? 'Any' }}
                                    <x-admin.icon name="arrow-right" class="inline w-3 h-3 align-[-1px]" />
                                    {{ $coupon->date_end?->format('M d, Y') ?? 'No end' }}
                                </span>
                            @else
                                <span class="text-v-muted">No dates</span>
                            @endif
                        </x-admin.table.cell>
                        <x-admin.table.cell align="right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.coupons.edit', $coupon) }}" class="text-[13px] text-v-accent hover:opacity-80">Edit</a>
                                <button type="button" class="text-[13px] text-v-danger hover:opacity-80" @click="$dispatch('open-modal', 'delete-coupon-{{ $coupon->id }}')">Delete</button>
                            </div>
                        </x-admin.table.cell>
                    </x-admin.table.row>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-2">
                            <x-admin.empty-state icon="coupons" title="No coupons found" :description="request()->anyFilled(['search', 'type', 'status']) ? 'Try adjusting your filters.' : null">
                                <a href="{{ route('admin.coupons.create') }}" class="text-[13px] text-v-accent hover:underline">Create your first coupon</a>
                            </x-admin.empty-state>
                        </td>
                    </tr>
                @endforelse
                @if ($coupons->hasPages())
                    <x-slot:footer>
                        {{ $coupons->links() }}
                    </x-slot:footer>
                @endif
            </x-admin.table>
        </div>

        @foreach ($coupons as $coupon)
            <x-admin.confirm-dialog name="delete-coupon-{{ $coupon->id }}" title="Delete “{{ $coupon->code }}”?"
                message="This coupon will no longer be usable at checkout. This cannot be undone."
                confirm="Delete" :action="route('admin.coupons.destroy', $coupon)" method="DELETE" />
        @endforeach
    </x-admin.page>
</x-admin.shell>
