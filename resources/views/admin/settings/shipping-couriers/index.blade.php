<x-admin.settings-shell title="Couriers & Tracking" active="couriers">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">Couriers & Tracking
                <x-admin.help text="The couriers orders ship with, and the link a tracking number turns into." /></h1>
        </div>
        <div class="v-head__actions">
            <a href="{{ route('admin.shipping-couriers.create') }}" class="v-btn v-btn--primary">Add courier</a>
        </div>
    </div>

    <div class="v-settle">
        <x-admin.panel title="Couriers" flush>
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr>
                            <th>Courier</th>
                            <th>Tracking URL template</th>
                            <th>Order</th>
                            <th>Status</th>
                            <th class="v-cell-act"><span class="v-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($couriers as $courier)
                            <tr class="{{ $courier->is_active ? '' : 'v-row-dim' }}">
                                <td>{{ $courier->name }}</td>
                                <td class="v-mono v-cell-url" title="{{ $courier->tracking_url }}">{{ $courier->tracking_url ?? '-' }}</td>
                                <td class="v-mono">{{ $courier->sort_order }}</td>
                                <td>
                                    <span class="v-badge {{ $courier->is_active ? 'v-badge--good' : 'v-badge--quiet' }}">{{ $courier->is_active ? 'Active' : 'Inactive' }}</span>
                                </td>
                                <td class="v-cell-act">
                                    <a href="{{ route('admin.shipping-couriers.edit', $courier) }}" class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                                    <button type="button" class="v-btn v-btn--danger v-btn--sm" @click="$dispatch('v-confirm', 'delete-courier-{{ $courier->id }}')">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="v-empty">
                                        <p class="v-empty__title">No couriers configured</p>
                                        <p class="v-empty__line">Orders cannot carry a tracking link until one exists.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.panel>

        @foreach ($couriers as $courier)
            <x-admin.confirm name="delete-courier-{{ $courier->id }}" title="Delete {{ $courier->name }}?"
                message="This cannot be undone. Orders that already carry its tracking links keep them."
                confirm="Delete" :action="route('admin.shipping-couriers.destroy', $courier)" method="DELETE" />
        @endforeach
    </div>
</x-admin.settings-shell>
