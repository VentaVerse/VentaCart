<x-admin3.shell title="Flat Rate">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.shipping-settings.index') }}">Shipping</a>
                <span aria-hidden="true">/</span>
                <span>Flat Rate</span>
            </div>
            <h1 class="v3-head__title">Flat Rate</h1>
        </div>
        <div class="v3-head__actions">
            <x-admin3.extension-switch name="shipping-flat-rate" />
            <a href="{{ route('admin.flat-rates.create') }}" class="v3-btn v3-btn--primary"><x-admin3.icon name="plus" /> Add flat rate</a>
        </div>
    </div>

    <div class="v3-settle">
        <x-admin3.panel title="Flat rate methods" flush>
            @if ($flatRates->isEmpty())
                <div class="v3-empty">
                    <p class="v3-empty__title">No flat rate methods</p>
                    <p class="v3-empty__line">Add a fixed-cost method scoped to a geo zone or customer group.</p>
                </div>
            @else
                <div class="v3-tablewrap">
                    <table class="v3-table">
                        <thead>
                            <tr><th>Name</th><th>Geo zone</th><th>Group</th><th>Cost</th><th>Status</th><th class="v3-cell-act"><span class="v3-sr">Actions</span></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($flatRates as $fr)
                                <tr>
                                    <td><a href="{{ route('admin.flat-rates.edit', $fr) }}" class="v3-cell-link">{{ $fr->name ?: 'Unnamed' }}</a></td>
                                    <td class="v3-cell-quiet">{{ $fr->geoZone?->name ?? 'All' }}</td>
                                    <td class="v3-cell-quiet">{{ $fr->customerGroup?->name ?? 'All' }}</td>
                                    <td class="v3-mono">₱{{ number_format($fr->cost, 2) }}</td>
                                    <td><span class="v3-badge {{ $fr->is_active ? 'v3-badge--good' : 'v3-badge--quiet' }}">{{ $fr->is_active ? 'Active' : 'Inactive' }}</span></td>
                                    <td class="v3-cell-act">
                                        <a href="{{ route('admin.flat-rates.edit', $fr) }}" class="v3-btn v3-btn--ghost v3-btn--sm">Edit</a>
                                        <button type="button" class="v3-btn v3-btn--danger v3-btn--sm" @click="$dispatch('v3-confirm', 'delete-fr-{{ $fr->id }}')">Delete</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-admin3.panel>

        @foreach ($flatRates as $fr)
            <x-admin3.confirm name="delete-fr-{{ $fr->id }}" title="Delete {{ $fr->name ?: 'this flat rate' }}?"
                message="The method stops being offered at checkout immediately."
                confirm="Delete" :action="route('admin.flat-rates.destroy', $fr)" method="DELETE" />
        @endforeach
    </div>
</x-admin3.shell>
