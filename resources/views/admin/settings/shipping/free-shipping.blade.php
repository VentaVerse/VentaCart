<x-admin.settings-shell title="Free Shipping" :wide="true">
    <div class="space-y-8">

        <x-admin.card title="Free Shipping Rules"
            description="First matching rule applies. Full condition editing lives on the dedicated rules page.">
            <x-slot name="headerActions">
                <x-admin.button variant="secondary" size="sm" :href="route('admin.free-shipping-rules.index')">Manage rules <x-admin.icon name="arrow-right" class="w-3.5 h-3.5" /></x-admin.button>
            </x-slot>

            @if ($freeShippingRules->isEmpty())
                <x-admin.empty-state icon="cart" title="No free shipping rules"
                    description="Create a rule to automatically waive shipping when conditions are met.">
                    <x-admin.button variant="primary" size="sm" :href="route('admin.free-shipping-rules.create')">Add rule</x-admin.button>
                </x-admin.empty-state>
            @else
                <x-admin.table>
                    <x-slot:head>
                        <x-admin.table.th>Rule</x-admin.table.th>
                        <x-admin.table.th>Conditions</x-admin.table.th>
                        <x-admin.table.th>Order</x-admin.table.th>
                        <x-admin.table.th>Status</x-admin.table.th>
                        <x-admin.table.th align="right">Actions</x-admin.table.th>
                    </x-slot:head>
                    @foreach ($freeShippingRules as $rule)
                        <x-admin.table.row>
                            <x-admin.table.cell><span class="font-medium text-v-text">{{ $rule->name }}</span></x-admin.table.cell>
                            <x-admin.table.cell><span class="text-v-secondary">{{ $rule->getConditionsSummary() }}</span></x-admin.table.cell>
                            <x-admin.table.cell><span class="text-v-secondary">{{ $rule->sort_order }}</span></x-admin.table.cell>
                            <x-admin.table.cell>
                                <form method="POST" action="{{ route('admin.free-shipping-rules.toggle', $rule) }}" data-v-form>
                                    @csrf
                                    <button type="submit">
                                        <x-admin.badge :tone="$rule->is_active ? 'success' : 'neutral'">
                                            {{ $rule->is_active ? 'Active' : 'Disabled' }}
                                        </x-admin.badge>
                                    </button>
                                </form>
                            </x-admin.table.cell>
                            <x-admin.table.cell align="right">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('admin.free-shipping-rules.edit', $rule) }}" class="text-xs font-medium text-v-accent hover:underline">Edit</a>
                                    <button type="button" class="text-xs font-medium text-v-danger hover:underline"
                                        @click="$dispatch('open-modal', 'delete-fsr-{{ $rule->id }}')">Delete</button>
                                </div>
                            </x-admin.table.cell>
                        </x-admin.table.row>
                    @endforeach
                </x-admin.table>
            @endif
        </x-admin.card>
        @foreach ($freeShippingRules as $rule)
            <x-admin.confirm-dialog name="delete-fsr-{{ $rule->id }}" title="Delete “{{ $rule->name }}”?"
                message="This free shipping rule will stop applying immediately." confirm="Delete"
                :action="route('admin.free-shipping-rules.destroy', $rule)" method="DELETE" />
        @endforeach

    </div>
</x-admin.settings-shell>
