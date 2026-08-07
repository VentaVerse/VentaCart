<x-admin.shell title="Abandoned Carts">
    <x-admin.page title="Carts">
        <div class="flex flex-col gap-6 lg:flex-row lg:gap-8">
            @include('admin.sales.abandoned-carts.partials.nav')

            <div class="min-w-0 flex-1 space-y-6">
                {{-- Summary --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-admin.stat label="Total Abandoned" :value="$totalAbandoned" />
                    <x-admin.stat label="Total Value" :value="number_format($totalValue, 2)" />
                </div>

                {{-- Filters --}}
                <x-admin.card>
                    <form method="GET" action="{{ route('admin.abandoned-carts.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-5">
                        <div class="md:col-span-2">
                            <x-admin.field name="search" label="Search">
                                <x-admin.input name="search" :value="request('search')" placeholder="Search customer name, email..." />
                            </x-admin.field>
                        </div>
                        <div>
                            <x-admin.field name="type" label="Type">
                                <x-admin.select name="type">
                                    <option value="">All Types</option>
                                    <option value="registered" {{ request('type') === 'registered' ? 'selected' : '' }}>Registered</option>
                                    <option value="guest" {{ request('type') === 'guest' ? 'selected' : '' }}>Guest</option>
                                </x-admin.select>
                            </x-admin.field>
                        </div>
                        <div class="flex items-end gap-2 md:col-span-2">
                            <x-admin.button type="submit">Filter</x-admin.button>
                            <x-admin.button variant="secondary" href="{{ route('admin.abandoned-carts.index') }}">Clear</x-admin.button>
                        </div>
                    </form>
                </x-admin.card>

                {{-- Mass actions --}}
                <div class="flex items-center justify-end gap-2">
                    <x-admin.button variant="secondary" size="sm" type="button"
                        @click="$dispatch('open-modal', 'mass-delete-guests')">
                        Delete All Guests
                    </x-admin.button>
                    <x-admin.button variant="danger" size="sm" type="button"
                        @click="$dispatch('open-modal', 'mass-delete-all')">
                        Delete All
                    </x-admin.button>
                </div>

                {{-- Table --}}
                <x-admin.table>
                    <x-slot name="head">
                        <x-admin.table.th>Customer</x-admin.table.th>
                        <x-admin.table.th>Items</x-admin.table.th>
                        <x-admin.table.th>Total</x-admin.table.th>
                        <x-admin.table.th>Abandoned</x-admin.table.th>
                        <x-admin.table.th align="right">Actions</x-admin.table.th>
                    </x-slot>
                    @forelse ($carts as $cart)
                        <x-admin.table.row :href="route('admin.abandoned-carts.show', $cart)">
                            <x-admin.table.cell>
                                @if ($cart->customer)
                                    <div class="font-medium text-v-text">{{ $cart->customer->first_name }} {{ $cart->customer->last_name }}</div>
                                    <div class="text-xs text-v-muted">{{ $cart->customer->email }}</div>
                                @else
                                    <span class="text-v-muted">Guest</span>
                                @endif
                            </x-admin.table.cell>
                            <x-admin.table.cell class="text-v-secondary">{{ $cart->items->count() }} item(s)</x-admin.table.cell>
                            <x-admin.table.cell mono class="font-medium text-v-text">{{ number_format($cart->total, 2) }}</x-admin.table.cell>
                            <x-admin.table.cell class="text-v-secondary">{{ $cart->updated_at->diffForHumans() }}</x-admin.table.cell>
                            <x-admin.table.cell align="right">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('admin.abandoned-carts.show', $cart) }}" class="text-v-accent hover:underline">View</a>
                                    <button type="button" class="text-v-danger hover:underline"
                                        @click="$dispatch('open-modal', 'delete-cart-{{ $cart->id }}')">Delete</button>
                                </div>
                                <x-admin.confirm-dialog name="delete-cart-{{ $cart->id }}" title="Delete abandoned cart"
                                    message="Delete this cart? This cannot be undone." confirm="Delete"
                                    :action="route('admin.abandoned-carts.destroy', $cart)" method="DELETE" />
                            </x-admin.table.cell>
                        </x-admin.table.row>
                    @empty
                        <tr><td colspan="5" class="px-4 py-2"><x-admin.empty-state icon="cart" title="No abandoned carts found" /></td></tr>
                    @endforelse

                    <x-slot name="footer">
                        @if ($carts->hasPages())
                            {{ $carts->links() }}
                        @endif
                    </x-slot>
                </x-admin.table>
            </div>
        </div>

        <x-admin.modal name="mass-delete-guests" title="Delete all guest carts" maxWidth="sm">
            <p class="text-[13px] text-v-secondary">Delete ALL guest carts? This cannot be undone.</p>
            <form method="POST" action="{{ route('admin.abandoned-carts.mass-delete') }}" data-v-form class="mt-4 flex justify-end gap-2">
                @csrf
                <input type="hidden" name="type" value="guest">
                <x-admin.button variant="secondary" type="button" @click="open = false">Cancel</x-admin.button>
                <x-admin.button variant="danger" type="submit">Delete All Guests</x-admin.button>
            </form>
        </x-admin.modal>

        <x-admin.modal name="mass-delete-all" title="Delete all abandoned carts" maxWidth="sm">
            <p class="text-[13px] text-v-secondary">Delete ALL abandoned carts? This cannot be undone.</p>
            <form method="POST" action="{{ route('admin.abandoned-carts.mass-delete') }}" data-v-form class="mt-4 flex justify-end gap-2">
                @csrf
                <input type="hidden" name="type" value="all">
                <x-admin.button variant="secondary" type="button" @click="open = false">Cancel</x-admin.button>
                <x-admin.button variant="danger" type="submit">Delete All</x-admin.button>
            </form>
        </x-admin.modal>
    </x-admin.page>
</x-admin.shell>
