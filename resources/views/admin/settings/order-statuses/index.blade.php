<x-admin.settings-shell title="Order Statuses" :wide="true">
        <div class="space-y-5">
            <x-admin.card title="Statuses">
                {{--
                    A <form> cannot legally wrap table rows/cells (invalid HTML: form is not a
                    permitted child of <tr>). Each row's edit form is instead declared as an
                    empty, id'd <form> and every input/select/checkbox/button inside the row
                    references it via the HTML5 form="" attribute — keeps the table structure
                    valid (and column widths governed by one single table) while each row still
                    submits independently. Pattern matches sections/currencies.blade.php.
                --}}
                @foreach ($statuses as $status)
                    <form id="status-update-{{ $status->id }}" method="POST" action="{{ route('admin.order-statuses.update', $status) }}" data-v-form class="hidden">
                        @csrf @method('PUT')
                    </form>
                @endforeach

                <x-admin.table>
                    <x-slot:head>
                        <x-admin.table.th>Status</x-admin.table.th>
                        {{-- The only genuinely non-obvious consequence of this
                             control, and the reason it earns a tooltip under the
                             owner's UI copy rule: clearing it on a status does
                             not merely stop deducting, it hands the units back
                             the moment an order reaches that status. --}}
                        <x-admin.table.th title="Stock leaves inventory at this status. Orders reaching a status without it get their stock returned.">Deducts stock</x-admin.table.th>
                        {{-- The non-obvious consequence, and the only reason
                             this column earns a tooltip: the flag has a hard
                             floor under it. An order that never reached a
                             status at all is a ghost of an abandoned checkout
                             and is never revenue, whatever is ticked here. --}}
                        <x-admin.table.th title="Orders with no status never count, whatever this is set to.">Counts as revenue</x-admin.table.th>
                        <x-admin.table.th>Sort</x-admin.table.th>
                        <x-admin.table.th align="right">Actions</x-admin.table.th>
                    </x-slot:head>
                    @foreach ($statuses as $status)
                        @php $editForm = 'status-update-'.$status->id; @endphp
                        <x-admin.table.row>
                            <x-admin.table.cell>
                                <div class="flex items-center gap-2">
                                    <input type="text" form="{{ $editForm }}" name="name" value="{{ old('name', $status->name) }}" required maxlength="255"
                                        class="v-control w-full h-[var(--control-h-sm)] px-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                    @if ($status->is_default)
                                        <x-admin.badge tone="success">Default</x-admin.badge>
                                    @endif
                                </div>
                            </x-admin.table.cell>
                            <x-admin.table.cell>
                                {{-- One checkbox IS the whole control. A status
                                     either deducts or it does not (owner ruling,
                                     2026-08-08); the old three-way select, the
                                     badge that repeated its value beside it and
                                     the legend card that explained that badge
                                     are all gone. The hidden 0 makes an
                                     unchecked box post, so clearing it saves.
                                     Column header carries the one consequence
                                     worth stating. --}}
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="hidden" form="{{ $editForm }}" name="deducts_stock" value="0">
                                    <input type="checkbox" form="{{ $editForm }}" name="deducts_stock" value="1" @checked(old('deducts_stock', $status->deducts_stock))
                                        aria-label="{{ $status->name }} deducts stock"
                                        class="w-4 h-4 rounded-v-sm border-v-strong text-v-accent focus:ring-v-accent bg-v-surface">
                                </label>
                            </x-admin.table.cell>
                            <x-admin.table.cell>
                                {{-- Same control idiom as Deducts stock beside
                                     it, deliberately: one checkbox, a hidden 0
                                     so clearing it saves, and the consequence
                                     stated once in the column header. The two
                                     start out agreeing (the flag is derived
                                     from stock on migrate) and part company
                                     whenever the merchant says so. --}}
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="hidden" form="{{ $editForm }}" name="counts_as_revenue" value="0">
                                    <input type="checkbox" form="{{ $editForm }}" name="counts_as_revenue" value="1" @checked(old('counts_as_revenue', $status->counts_as_revenue))
                                        aria-label="{{ $status->name }} counts as revenue"
                                        class="w-4 h-4 rounded-v-sm border-v-strong text-v-accent focus:ring-v-accent bg-v-surface">
                                </label>
                            </x-admin.table.cell>
                            <x-admin.table.cell>
                                <input type="number" form="{{ $editForm }}" name="sort_order" value="{{ old('sort_order', $status->sort_order) }}" min="0"
                                    class="v-control w-20 h-[var(--control-h-sm)] px-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                            </x-admin.table.cell>
                            <x-admin.table.cell align="right">
                                <div class="flex items-center justify-end gap-3">
                                    <label class="inline-flex items-center gap-1.5 text-[13px] text-v-text cursor-pointer">
                                        <input type="hidden" form="{{ $editForm }}" name="is_default" value="0">
                                        <input type="checkbox" form="{{ $editForm }}" name="is_default" value="1" @checked(old('is_default', $status->is_default))
                                            class="w-4 h-4 rounded-v-sm border-v-strong text-v-accent focus:ring-v-accent bg-v-surface">
                                        <span>Default</span>
                                    </label>
                                    <x-admin.button variant="secondary" size="sm" type="submit" form="{{ $editForm }}">Save</x-admin.button>
                                    @unless ($status->is_default)
                                        <x-admin.button variant="ghost" size="sm" type="button" class="text-v-danger hover:text-v-danger" @click="$dispatch('open-modal', 'delete-status-{{ $status->id }}')">Delete</x-admin.button>
                                    @endunless
                                </div>
                            </x-admin.table.cell>
                        </x-admin.table.row>
                    @endforeach
                </x-admin.table>
            </x-admin.card>

            @foreach ($statuses as $status)
                @unless ($status->is_default)
                    <x-admin.confirm-dialog name="delete-status-{{ $status->id }}" title="Delete “{{ $status->name }}”?"
                        message="Orders using this status keep their history; the status becomes unavailable for new changes."
                        confirm="Delete" :action="route('admin.order-statuses.destroy', $status)" method="DELETE" />
                @endunless
            @endforeach

            <x-admin.card title="Add status">
                <form method="POST" action="{{ route('admin.order-statuses.store') }}" data-v-form class="grid grid-cols-1 gap-4 sm:grid-cols-3 sm:items-end">
                    @csrf
                    <x-admin.field name="name" label="Name">
                        <x-admin.input name="name" required placeholder="Quality Check" />
                    </x-admin.field>
                    <x-admin.field name="sort_order" label="Sort">
                        <x-admin.input name="sort_order" type="number" min="0" value="0" />
                    </x-admin.field>
                    <div class="flex flex-wrap items-center gap-4">
                        <input type="hidden" name="deducts_stock" value="0">
                        <x-admin.checkbox name="deducts_stock" label="Deducts stock"
                            title="Stock leaves inventory at this status. Orders reaching a status without it get their stock returned." />
                        <input type="hidden" name="counts_as_revenue" value="0">
                        <x-admin.checkbox name="counts_as_revenue" label="Counts as revenue"
                            title="Orders with no status never count, whatever this is set to." />
                        <x-admin.checkbox name="is_default" label="Default" />
                        <x-admin.button variant="primary" type="submit">Add</x-admin.button>
                    </div>
                </form>
            </x-admin.card>
        </div>
</x-admin.settings-shell>
