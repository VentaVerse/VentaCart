<x-admin.shell title="Flat Rate">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.shipping-settings.index') }}">Shipping</a>
                <span aria-hidden="true">/</span>
                <span>Flat Rate</span>
            </div>
            <h1 class="v-head__title">Flat Rate</h1>
        </div>
        <div class="v-head__actions">
            <x-admin.extension-switch name="shipping-flat-rate" />
            <a href="{{ route('admin.flat-rates.create') }}" class="v-btn v-btn--primary"><x-admin.icon name="plus" /> Add flat rate</a>
        </div>
    </div>

    <div class="v-settle">
        <x-admin.panel title="Flat rate methods" flush>
            @if ($flatRates->isEmpty())
                <div class="v-empty">
                    <p class="v-empty__title">No flat rate methods</p>
                    <p class="v-empty__line">Add a fixed-cost method scoped to a geo zone or customer group.</p>
                </div>
            @else
                <div class="v-tablewrap">
                    <table class="v-table">
                        <thead>
                            <tr><th>Name</th><th>Geo zone</th><th>Group</th><th>Cost</th><th>Status</th><th class="v-cell-act"><span class="v-sr">Actions</span></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($flatRates as $fr)
                                <tr>
                                    <td><a href="{{ route('admin.flat-rates.edit', $fr) }}" class="v-cell-link">{{ $fr->name ?: 'Unnamed' }}</a></td>
                                    <td class="v-cell-quiet">{{ $fr->geoZone?->name ?? 'All' }}</td>
                                    <td class="v-cell-quiet">{{ $fr->customerGroup?->name ?? 'All' }}</td>
                                    <td class="v-mono">₱{{ number_format($fr->cost, 2) }}</td>
                                    <td><span class="v-badge {{ $fr->is_active ? 'v-badge--good' : 'v-badge--quiet' }}">{{ $fr->is_active ? 'Active' : 'Inactive' }}</span></td>
                                    <td class="v-cell-act">
                                        <a href="{{ route('admin.flat-rates.edit', $fr) }}" class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                                        <button type="button" class="v-btn v-btn--danger v-btn--sm" @click="$dispatch('v-confirm', 'delete-fr-{{ $fr->id }}')">Delete</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-admin.panel>

        @foreach ($flatRates as $fr)
            <x-admin.confirm name="delete-fr-{{ $fr->id }}" title="Delete {{ $fr->name ?: 'this flat rate' }}?"
                message="The method stops being offered at checkout immediately."
                confirm="Delete" :action="route('admin.flat-rates.destroy', $fr)" method="DELETE" />
        @endforeach
    </div>
</x-admin.shell>
