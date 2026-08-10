<x-admin.shell title="Abandoned Carts">
    <x-admin.page title="Carts">
        @include('admin.sales.abandoned-carts.partials.nav')

        <div>
            {{-- Summary. Both figures are identity, not state: a count of
                 carts and the money sitting in them. Neither goes red for
                 being large, so they take accents rather than tones. --}}
            <div class="v-stats v-stats--few mb-4">
                <x-admin.figure label="Total Abandoned" :value="$totalAbandoned" accent="amber" icon="cart" />
                <x-admin.figure label="Total Value" :value="number_format($totalValue, 2)" mark="₱" accent="violet" icon="banknote" />
            </div>

            <x-admin.toolbar>
                <x-admin.filters :action="route('admin.abandoned-carts.index')" class="mb-0 flex-1">
                    <x-admin.field name="search" label="Search" class="v-field--grow">
                        <x-admin.input name="search" :value="request('search')" placeholder="Search customer name, email..." />
                    </x-admin.field>
                    <x-admin.field name="type" label="Type" class="w-44">
                        <x-admin.select name="type">
                            <option value="">All Types</option>
                            <option value="registered" {{ request('type') === 'registered' ? 'selected' : '' }}>Registered</option>
                            <option value="guest" {{ request('type') === 'guest' ? 'selected' : '' }}>Guest</option>
                        </x-admin.select>
                    </x-admin.field>
                    <x-admin.button type="submit" variant="secondary">Filter</x-admin.button>
                    <a href="{{ route('admin.abandoned-carts.index') }}" class="v-filters__reset">Clear</a>
                </x-admin.filters>

                <div class="v-toolbar__actions ml-auto">
                    <x-admin.button variant="secondary" type="button"
                        @click="$dispatch('open-modal', 'mass-delete-guests')">
                        Delete All Guests
                    </x-admin.button>
                    <x-admin.button variant="danger" type="button"
                        @click="$dispatch('open-modal', 'mass-delete-all')">
                        Delete All
                    </x-admin.button>
                </div>
            </x-admin.toolbar>

            {{-- Table --}}
            <x-admin.table>
                <x-slot name="head">
                    <x-admin.table.th>Customer</x-admin.table.th>
                    <x-admin.table.th>Items</x-admin.table.th>
                    <x-admin.table.th align="right">Total</x-admin.table.th>
                    <x-admin.table.th>Abandoned</x-admin.table.th>
                    <x-admin.table.th class="v-col-act">Actions</x-admin.table.th>
                </x-slot>
                @forelse ($carts as $cart)
                    <x-admin.table.row :href="route('admin.abandoned-carts.show', $cart)">
                        <x-admin.table.cell>
                            @if ($cart->customer)
                                <span class="v-lead">{{ $cart->customer->first_name }} {{ $cart->customer->last_name }}</span>
                                <span class="v-sub">{{ $cart->customer->email }}</span>
                            @else
                                <span class="v-none">Guest</span>
                            @endif
                        </x-admin.table.cell>
                        <x-admin.table.cell class="text-v-secondary">{{ $cart->items->count() }} item(s)</x-admin.table.cell>
                        <x-admin.table.cell align="right"><x-admin.money mark="₱" :amount="number_format($cart->total, 2)" /></x-admin.table.cell>
                        <x-admin.table.cell class="text-v-secondary">{{ $cart->updated_at->diffForHumans() }}</x-admin.table.cell>
                        <x-admin.table.cell class="v-col-act">
                            <div class="v-rowactions v-rowactions--end">
                                <x-admin.button size="sm" variant="ghost" :href="route('admin.abandoned-carts.show', $cart)">View</x-admin.button>
                                <x-admin.button size="sm" variant="ghost" class="v-rowactions__danger"
                                    @click="$dispatch('open-modal', 'delete-cart-{{ $cart->id }}')">Delete</x-admin.button>
                            </div>
                            <x-admin.confirm-dialog name="delete-cart-{{ $cart->id }}" title="Delete abandoned cart"
                                message="Delete this cart? This cannot be undone." confirm="Delete"
                                :action="route('admin.abandoned-carts.destroy', $cart)" method="DELETE" />
                        </x-admin.table.cell>
                    </x-admin.table.row>
                @empty
                    <tr><td colspan="5"><x-admin.empty-state title="No abandoned carts found" /></td></tr>
                @endforelse

                <x-slot name="footer">
                    @if ($carts->hasPages())
                        {{ $carts->links() }}
                    @endif
                </x-slot>
            </x-admin.table>
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
