<x-admin.settings-shell title="Flat Rate" :wide="true">
    <div class="space-y-8">

        <x-admin.card title="Flat Rate">
            <x-slot name="headerActions">
                <x-admin.button variant="primary" size="sm" :href="route('admin.flat-rates.create')">Add flat rate</x-admin.button>
            </x-slot>

            @if ($flatRates->isEmpty())
                <x-admin.empty-state title="No flat rate methods"
                    description="Add a fixed-cost method scoped to a geo zone or customer group.">
                    <x-admin.button variant="primary" size="sm" :href="route('admin.flat-rates.create')">Add flat rate</x-admin.button>
                </x-admin.empty-state>
            @else
                <x-admin.table>
                    <x-slot:head>
                        <x-admin.table.th>Name</x-admin.table.th>
                        <x-admin.table.th>Geo zone</x-admin.table.th>
                        <x-admin.table.th>Group</x-admin.table.th>
                        <x-admin.table.th align="right">Cost</x-admin.table.th>
                        <x-admin.table.th>Status</x-admin.table.th>
                        <x-admin.table.th class="v-col-act">Actions</x-admin.table.th>
                    </x-slot:head>
                    @foreach ($flatRates as $fr)
                        <x-admin.table.row>
                            <x-admin.table.cell><span class="font-medium text-v-text">{{ $fr->name ?: 'Unnamed' }}</span></x-admin.table.cell>
                            <x-admin.table.cell><span class="text-v-secondary">{{ $fr->geoZone?->name ?? 'All' }}</span></x-admin.table.cell>
                            <x-admin.table.cell><span class="text-v-secondary">{{ $fr->customerGroup?->name ?? 'All' }}</span></x-admin.table.cell>
                            <x-admin.table.cell align="right" mono>₱{{ number_format($fr->cost, 2) }}</x-admin.table.cell>
                            <x-admin.table.cell>
                                <x-admin.badge :tone="$fr->is_active ? 'success' : 'neutral'">{{ $fr->is_active ? 'Active' : 'Inactive' }}</x-admin.badge>
                            </x-admin.table.cell>
                            <x-admin.table.cell class="v-col-act">
                                <div class="v-rowactions v-rowactions--end">
                                    <x-admin.button size="sm" variant="ghost" :href="route('admin.flat-rates.edit', $fr)">Edit</x-admin.button>
                                    <x-admin.button size="sm" variant="ghost" class="v-rowactions__danger" type="button"
                                        @click="$dispatch('open-modal', 'delete-fr-{{ $fr->id }}')">Delete</x-admin.button>
                                </div>
                            </x-admin.table.cell>
                        </x-admin.table.row>
                    @endforeach
                </x-admin.table>
            @endif
        </x-admin.card>
        @foreach ($flatRates as $fr)
            <x-admin.confirm-dialog name="delete-fr-{{ $fr->id }}" title="Delete “{{ $fr->name ?: 'this flat rate' }}”?"
                message="This flat rate method will no longer be offered at checkout." confirm="Delete"
                :action="route('admin.flat-rates.destroy', $fr)" method="DELETE" />
        @endforeach

    </div>
</x-admin.settings-shell>
