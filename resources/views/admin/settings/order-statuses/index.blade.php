<x-admin3.settings-shell title="Orders" active="order-statuses">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Orders</h1>
            <p class="v3-head__standing">The status flow, and what each status does to stock and revenue.</p>
        </div>
    </div>

    <div class="v3-settle">
        {{-- Per-row forms via the id'd-form + form="" idiom: a status is a
             record, so each row saves itself. --}}
        @foreach ($statuses as $status)
            <form id="status-update-{{ $status->id }}" method="POST" action="{{ route('admin.order-statuses.update', $status) }}" hidden>
                @csrf @method('PUT')
            </form>
        @endforeach

        <x-admin3.panel title="Statuses" flush>
            <div class="v3-tablewrap">
                <table class="v3-table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            {{-- The one non-obvious consequence each flag carries,
                                 stated once in its header. --}}
                            <th title="Stock leaves inventory at this status. Orders reaching a status without it get their stock returned.">Deducts stock</th>
                            <th title="Orders with no status never count, whatever this is set to.">Counts as revenue</th>
                            <th>Sort</th>
                            <th class="v3-cell-act"><span class="v3-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($statuses as $status)
                            @php $editForm = 'status-update-'.$status->id; @endphp
                            <tr>
                                <td class="v3-cell-name">
                                    <span class="v3-namerow">
                                        <input type="text" form="{{ $editForm }}" name="name" value="{{ old('name', $status->name) }}" required maxlength="255"
                                               aria-label="Status name" class="v3-control">
                                        @if ($status->is_default)
                                            <span class="v3-badge v3-badge--good">Default</span>
                                        @endif
                                    </span>
                                </td>
                                <td>
                                    <label class="v3-check">
                                        <input type="hidden" form="{{ $editForm }}" name="deducts_stock" value="0">
                                        <input type="checkbox" form="{{ $editForm }}" name="deducts_stock" value="1" @checked(old('deducts_stock', $status->deducts_stock))
                                               aria-label="{{ $status->name }} deducts stock">
                                    </label>
                                </td>
                                <td>
                                    <label class="v3-check">
                                        <input type="hidden" form="{{ $editForm }}" name="counts_as_revenue" value="0">
                                        <input type="checkbox" form="{{ $editForm }}" name="counts_as_revenue" value="1" @checked(old('counts_as_revenue', $status->counts_as_revenue))
                                               aria-label="{{ $status->name }} counts as revenue">
                                    </label>
                                </td>
                                <td>
                                    <input type="number" form="{{ $editForm }}" name="sort_order" value="{{ old('sort_order', $status->sort_order) }}" min="0"
                                           aria-label="{{ $status->name }} sort order" class="v3-control v3-mono v3-w-sort">
                                </td>
                                <td class="v3-cell-act">
                                    <span class="v3-actrow">
                                        <label class="v3-check">
                                            <input type="hidden" form="{{ $editForm }}" name="is_default" value="0">
                                            <input type="checkbox" form="{{ $editForm }}" name="is_default" value="1" @checked(old('is_default', $status->is_default))>
                                            Default
                                        </label>
                                        <button type="submit" form="{{ $editForm }}" class="v3-btn v3-btn--secondary v3-btn--sm">Save</button>
                                        @unless ($status->is_default)
                                            <button type="button" class="v3-btn v3-btn--danger v3-btn--sm" @click="$dispatch('v3-confirm', 'delete-status-{{ $status->id }}')">Delete</button>
                                        @endunless
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-admin3.panel>

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
                    <label class="v3-check" title="Stock leaves inventory at this status. Orders reaching a status without it get their stock returned.">
                        <input type="hidden" name="deducts_stock" value="0">
                        <input type="checkbox" name="deducts_stock" value="1">
                        Deducts stock
                    </label>
                    <label class="v3-check" title="Orders with no status never count, whatever this is set to.">
                        <input type="hidden" name="counts_as_revenue" value="0">
                        <input type="checkbox" name="counts_as_revenue" value="1">
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
