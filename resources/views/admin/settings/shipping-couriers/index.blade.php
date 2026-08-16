<x-admin3.settings-shell title="Couriers & Tracking" active="couriers">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Couriers & Tracking</h1>
            <p class="v3-head__standing">The couriers orders ship with, and the link a tracking number turns into. Put {{ '{tracking_number}' }} where the code belongs.</p>
        </div>
    </div>

    <div class="v3-settle" x-data="{ editing: null }">
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
                            <tr>
                                <template x-if="editing !== {{ $courier->id }}">
                                    <td>{{ $courier->name }}</td>
                                </template>
                                <template x-if="editing !== {{ $courier->id }}">
                                    <td class="v3-mono v3-cell-url" title="{{ $courier->tracking_url }}">{{ $courier->tracking_url ?? '-' }}</td>
                                </template>
                                <template x-if="editing !== {{ $courier->id }}">
                                    <td class="v3-mono">{{ $courier->sort_order }}</td>
                                </template>
                                <template x-if="editing !== {{ $courier->id }}">
                                    <td>
                                        <span class="v3-badge {{ $courier->is_active ? 'v3-badge--good' : 'v3-badge--quiet' }}">{{ $courier->is_active ? 'Active' : 'Inactive' }}</span>
                                    </td>
                                </template>
                                <template x-if="editing !== {{ $courier->id }}">
                                    <td class="v3-cell-act">
                                        <button type="button" class="v3-btn v3-btn--ghost v3-btn--sm" @click="editing = {{ $courier->id }}">Edit</button>
                                        <button type="button" class="v3-btn v3-btn--danger v3-btn--sm" @click="$dispatch('v3-confirm', 'delete-courier-{{ $courier->id }}')">Delete</button>
                                    </td>
                                </template>

                                <template x-if="editing === {{ $courier->id }}">
                                    <td colspan="5">
                                        <form method="POST" action="{{ route('admin.shipping-couriers.update', $courier) }}" class="v3-rowedit">
                                            @csrf @method('PUT')
                                            <div class="v3-rowedit__line">
                                                <input type="text" name="name" value="{{ $courier->name }}" required aria-label="Courier name" class="v3-control v3-w-name">
                                                <input type="number" name="sort_order" value="{{ $courier->sort_order }}" min="0" aria-label="Sort order" class="v3-control v3-mono v3-w-sort">
                                                <label class="v3-check">
                                                    <input type="hidden" name="is_active" value="0">
                                                    <input type="checkbox" name="is_active" value="1" @checked($courier->is_active)>
                                                    Active
                                                </label>
                                            </div>
                                            <div class="v3-rowedit__line">
                                                <input type="text" name="tracking_url" value="{{ $courier->tracking_url }}"
                                                       placeholder="https://example.com/track/{tracking_number}" aria-label="Tracking URL template"
                                                       class="v3-control v3-mono">
                                                <button type="submit" class="v3-btn v3-btn--primary v3-btn--sm">Save</button>
                                                <button type="button" class="v3-btn v3-btn--ghost v3-btn--sm" @click="editing = null">Cancel</button>
                                            </div>
                                        </form>
                                    </td>
                                </template>
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

        <x-admin3.panel title="Add courier">
            <form method="POST" action="{{ route('admin.shipping-couriers.store') }}">
                @csrf
                <div class="v3-addrow v3-addrow--3">
                    <x-admin3.field name="name" label="Courier" required>
                        <input type="text" name="name" required placeholder="e.g. GoGo Express" class="v3-control">
                    </x-admin3.field>
                    <x-admin3.field name="sort_order" label="Order">
                        <input type="number" name="sort_order" value="0" min="0" class="v3-control v3-mono">
                    </x-admin3.field>
                    <div class="v3-addrow__opts">
                        <label class="v3-check">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" checked>
                            Active
                        </label>
                    </div>
                </div>
                <div class="v3-inline v3-gap">
                    <x-admin3.field name="tracking_url" label="Tracking URL template">
                        <input type="text" name="tracking_url" placeholder="https://example.com/track/{tracking_number}" class="v3-control v3-mono">
                    </x-admin3.field>
                    <button type="submit" class="v3-btn v3-btn--primary">Add courier</button>
                </div>
            </form>
        </x-admin3.panel>
    </div>
</x-admin3.settings-shell>
