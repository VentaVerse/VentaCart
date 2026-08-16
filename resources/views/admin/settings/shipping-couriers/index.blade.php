<x-admin3.settings-shell title="Couriers & Tracking" active="couriers">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Couriers & Tracking
                <x-admin3.help text="The couriers orders ship with, and the link a tracking number turns into." /></h1>
        </div>
        <div class="v3-head__actions">
            <a href="{{ route('admin.shipping-couriers.create') }}" class="v3-btn v3-btn--primary">Add courier</a>
        </div>
    </div>

    <div class="v3-settle">
        <x-admin3.panel title="Couriers" flush>
            <div class="v3-tablewrap">
                <table class="v3-table">
                    <thead>
                        <tr>
                            <th>Courier</th>
                            <th>Tracking URL template</th>
                            <th>Order</th>
                            <th>Status</th>
                            <th class="v3-cell-act"><span class="v3-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($couriers as $courier)
                            <tr class="{{ $courier->is_active ? '' : 'v3-row-dim' }}">
                                <td>{{ $courier->name }}</td>
                                <td class="v3-mono v3-cell-url" title="{{ $courier->tracking_url }}">{{ $courier->tracking_url ?? '-' }}</td>
                                <td class="v3-mono">{{ $courier->sort_order }}</td>
                                <td>
                                    <span class="v3-badge {{ $courier->is_active ? 'v3-badge--good' : 'v3-badge--quiet' }}">{{ $courier->is_active ? 'Active' : 'Inactive' }}</span>
                                </td>
                                <td class="v3-cell-act">
                                    <a href="{{ route('admin.shipping-couriers.edit', $courier) }}" class="v3-btn v3-btn--ghost v3-btn--sm">Edit</a>
                                    <button type="button" class="v3-btn v3-btn--danger v3-btn--sm" @click="$dispatch('v3-confirm', 'delete-courier-{{ $courier->id }}')">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="v3-empty">
                                        <p class="v3-empty__title">No couriers configured</p>
                                        <p class="v3-empty__line">Orders cannot carry a tracking link until one exists.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin3.panel>

        @foreach ($couriers as $courier)
            <x-admin3.confirm name="delete-courier-{{ $courier->id }}" title="Delete {{ $courier->name }}?"
                message="This cannot be undone. Orders that already carry its tracking links keep them."
                confirm="Delete" :action="route('admin.shipping-couriers.destroy', $courier)" method="DELETE" />
        @endforeach
    </div>
</x-admin3.settings-shell>
