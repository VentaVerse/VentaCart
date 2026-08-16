<x-admin3.settings-shell title="Orders" active="order-statuses">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Orders</h1>
            <p class="v3-head__standing">The status flow, and what each status does to stock and revenue.</p>
        </div>
    </div>

    <div class="v3-settle">
        {{-- Always-editable rows are ONE save scope (the doctrine): every
             field rides the save bar's single submit, and the default is
             a radio so exactly one status holds it. Deletes stay record
             actions through the confirm's own form. --}}
        <x-admin3.form :action="route('admin.order-statuses.update-all')" method="PUT">
            <x-admin3.panel title="Statuses" flush>
                <div class="v3-tablewrap">
                    <table class="v3-table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th><span class="v3-namerow">Deducts stock <x-admin3.help text="Stock leaves inventory at this status. Orders reaching a status without it get their stock returned." /></span></th>
                                <th><span class="v3-namerow">Counts as revenue <x-admin3.help text="Orders with no status never count, whatever this is set to." /></span></th>
                                <th>Sort</th>
                                <th>Default</th>
                                <th class="v3-cell-act"><span class="v3-sr">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($statuses as $status)
                                <tr>
                                    <td class="v3-cell-name">
                                        <input type="text" name="rows[{{ $status->id }}][name]" value="{{ old('rows.'.$status->id.'.name', $status->name) }}" required maxlength="255"
                                               aria-label="Status name" class="v3-control">
                                    </td>
                                    <td>
                                        <label class="v3-toggle">
                                            <input type="hidden" name="rows[{{ $status->id }}][deducts_stock]" value="0">
                                            <input type="checkbox" name="rows[{{ $status->id }}][deducts_stock]" value="1" @checked(old('rows.'.$status->id.'.deducts_stock', $status->deducts_stock))
                                                   aria-label="{{ $status->name }} deducts stock">
                                            <span class="v3-toggle__track" aria-hidden="true"></span>
                                        </label>
                                    </td>
                                    <td>
                                        <label class="v3-toggle">
                                            <input type="hidden" name="rows[{{ $status->id }}][counts_as_revenue]" value="0">
                                            <input type="checkbox" name="rows[{{ $status->id }}][counts_as_revenue]" value="1" @checked(old('rows.'.$status->id.'.counts_as_revenue', $status->counts_as_revenue))
                                                   aria-label="{{ $status->name }} counts as revenue">
                                            <span class="v3-toggle__track" aria-hidden="true"></span>
                                        </label>
                                    </td>
                                    <td>
                                        <input type="number" name="rows[{{ $status->id }}][sort_order]" value="{{ old('rows.'.$status->id.'.sort_order', $status->sort_order) }}" min="0"
                                               aria-label="{{ $status->name }} sort order" class="v3-control v3-mono v3-w-sort">
                                    </td>
                                    <td>
                                        <label class="v3-check">
                                            <input type="radio" name="default_id" value="{{ $status->id }}" @checked(old('default_id', $statuses->firstWhere('is_default', true)?->id) == $status->id)
                                                   aria-label="{{ $status->name }} is the default status">
                                        </label>
                                    </td>
                                    <td class="v3-cell-act">
                                        @unless ($status->is_default)
                                            <button type="button" class="v3-btn v3-btn--danger v3-btn--sm" @click="$dispatch('v3-confirm', 'delete-status-{{ $status->id }}')">Delete</button>
                                        @endunless
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-admin3.panel>
        </x-admin3.form>

        @foreach ($statuses as $status)
            @unless ($status->is_default)
                <x-admin3.confirm name="delete-status-{{ $status->id }}" title="Delete {{ $status->name }}?"
                    message="Orders using this status keep their history; the status becomes unavailable for new changes."
                    confirm="Delete" :action="route('admin.order-statuses.destroy', $status)" method="DELETE" />
            @endunless
        @endforeach

        <x-admin3.panel title="Add status">
            <form method="POST" action="{{ route('admin.order-statuses.store') }}" class="v3-addrow v3-addrow--3">
                @csrf
                <x-admin3.field name="name" label="Name" required>
                    <input type="text" name="name" required placeholder="Quality Check" class="v3-control">
                </x-admin3.field>
                <x-admin3.field name="sort_order" label="Sort">
                    <input type="number" name="sort_order" min="0" value="0" class="v3-control v3-mono">
                </x-admin3.field>
                <div class="v3-addrow__opts">
                    <label class="v3-toggle" title="Stock leaves inventory at this status. Orders reaching a status without it get their stock returned.">
                        <input type="hidden" name="deducts_stock" value="0">
                        <input type="checkbox" name="deducts_stock" value="1">
                        <span class="v3-toggle__track" aria-hidden="true"></span>
                        Deducts stock
                    </label>
                    <label class="v3-toggle" title="Orders with no status never count, whatever this is set to.">
                        <input type="hidden" name="counts_as_revenue" value="0">
                        <input type="checkbox" name="counts_as_revenue" value="1">
                        <span class="v3-toggle__track" aria-hidden="true"></span>
                        Counts as revenue
                    </label>
                    <label class="v3-check">
                        <input type="checkbox" name="is_default" value="1">
                        Default
                    </label>
                    <button type="submit" class="v3-btn v3-btn--primary">Add status</button>
                </div>
            </form>
        </x-admin3.panel>
    </div>
</x-admin3.settings-shell>
