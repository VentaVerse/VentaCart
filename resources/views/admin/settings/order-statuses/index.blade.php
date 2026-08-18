<x-admin.settings-shell title="Orders" active="order-statuses">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">Orders</h1>
            <p class="v-head__standing">The status flow, and what each status does to stock and revenue.</p>
        </div>
    </div>

    <div class="v-settle">
        {{-- Always-editable rows are ONE save scope (the doctrine): every
             field rides the save bar's single submit, and the default is
             a radio so exactly one status holds it. Deletes stay record
             actions through the confirm's own form. --}}
        <x-admin.form :action="route('admin.order-statuses.update-all')" method="PUT">
            <x-admin.panel title="Statuses" flush>
                <div class="v-tablewrap">
                    <table class="v-table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th><span class="v-namerow">Deducts stock <x-admin.help text="Stock leaves inventory at this status. Orders reaching a status without it get their stock returned." /></span></th>
                                <th><span class="v-namerow">Counts as revenue <x-admin.help text="Orders with no status never count, whatever this is set to." /></span></th>
                                <th>Sort</th>
                                <th>Default</th>
                                <th class="v-cell-act"><span class="v-sr">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($statuses as $status)
                                <tr>
                                    <td class="v-cell-name">
                                        <input type="text" name="rows[{{ $status->id }}][name]" value="{{ old('rows.'.$status->id.'.name', $status->name) }}" required maxlength="255"
                                               aria-label="Status name" class="v-control">
                                    </td>
                                    <td>
                                        <label class="v-toggle">
                                            <input type="hidden" name="rows[{{ $status->id }}][deducts_stock]" value="0">
                                            <input type="checkbox" name="rows[{{ $status->id }}][deducts_stock]" value="1" @checked(old('rows.'.$status->id.'.deducts_stock', $status->deducts_stock))
                                                   aria-label="{{ $status->name }} deducts stock">
                                            <span class="v-toggle__track" aria-hidden="true"></span>
                                        </label>
                                    </td>
                                    <td>
                                        <label class="v-toggle">
                                            <input type="hidden" name="rows[{{ $status->id }}][counts_as_revenue]" value="0">
                                            <input type="checkbox" name="rows[{{ $status->id }}][counts_as_revenue]" value="1" @checked(old('rows.'.$status->id.'.counts_as_revenue', $status->counts_as_revenue))
                                                   aria-label="{{ $status->name }} counts as revenue">
                                            <span class="v-toggle__track" aria-hidden="true"></span>
                                        </label>
                                    </td>
                                    <td>
                                        <input type="number" name="rows[{{ $status->id }}][sort_order]" value="{{ old('rows.'.$status->id.'.sort_order', $status->sort_order) }}" min="0"
                                               aria-label="{{ $status->name }} sort order" class="v-control v-mono v-w-sort">
                                    </td>
                                    <td>
                                        <label class="v-check">
                                            <input type="radio" name="default_id" value="{{ $status->id }}" @checked(old('default_id', $statuses->firstWhere('is_default', true)?->id) == $status->id)
                                                   aria-label="{{ $status->name }} is the default status">
                                        </label>
                                    </td>
                                    <td class="v-cell-act">
                                        @unless ($status->is_default)
                                            <button type="button" class="v-btn v-btn--danger v-btn--sm" @click="$dispatch('v-confirm', 'delete-status-{{ $status->id }}')">Delete</button>
                                        @endunless
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-admin.panel>
        </x-admin.form>

        @foreach ($statuses as $status)
            @unless ($status->is_default)
                <x-admin.confirm name="delete-status-{{ $status->id }}" title="Delete {{ $status->name }}?"
                    message="Orders using this status keep their history; the status becomes unavailable for new changes."
                    confirm="Delete" :action="route('admin.order-statuses.destroy', $status)" method="DELETE" />
            @endunless
        @endforeach

        <x-admin.panel title="Add status">
            <form method="POST" action="{{ route('admin.order-statuses.store') }}" class="v-addrow v-addrow--3">
                @csrf
                <x-admin.field name="name" label="Name" required>
                    <input type="text" name="name" required placeholder="Quality Check" class="v-control">
                </x-admin.field>
                <x-admin.field name="sort_order" label="Sort">
                    <input type="number" name="sort_order" min="0" value="0" class="v-control v-mono">
                </x-admin.field>
                <div class="v-addrow__opts">
                    <label class="v-toggle" title="Stock leaves inventory at this status. Orders reaching a status without it get their stock returned.">
                        <input type="hidden" name="deducts_stock" value="0">
                        <input type="checkbox" name="deducts_stock" value="1">
                        <span class="v-toggle__track" aria-hidden="true"></span>
                        Deducts stock
                    </label>
                    <label class="v-toggle" title="Orders with no status never count, whatever this is set to.">
                        <input type="hidden" name="counts_as_revenue" value="0">
                        <input type="checkbox" name="counts_as_revenue" value="1">
                        <span class="v-toggle__track" aria-hidden="true"></span>
                        Counts as revenue
                    </label>
                    <label class="v-check">
                        <input type="checkbox" name="is_default" value="1">
                        Default
                    </label>
                    <button type="submit" class="v-btn v-btn--primary">Add status</button>
                </div>
            </form>
        </x-admin.panel>
    </div>
</x-admin.settings-shell>
