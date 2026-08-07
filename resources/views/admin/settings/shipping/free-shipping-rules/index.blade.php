<x-admin.shell title="Free Shipping Rules">
    <x-admin.page title="Free Shipping Rules" :breadcrumb="[['label' => 'Settings', 'url' => route('admin.settings.index')], ['label' => 'Shipping', 'url' => route('admin.shipping-settings.free-shipping')]]">
        <x-slot:actions>
            <x-admin.button variant="primary" :href="route('admin.free-shipping-rules.create')">
                <x-admin.icon name="plus" class="w-4 h-4" /> Add Rule
            </x-admin.button>
        </x-slot:actions>

        <x-admin.table>
            <x-slot:head>
                <x-admin.table.th>Rule</x-admin.table.th>
                <x-admin.table.th>Conditions</x-admin.table.th>
                <x-admin.table.th>Order</x-admin.table.th>
                <x-admin.table.th>Status</x-admin.table.th>
                <x-admin.table.th align="right">Actions</x-admin.table.th>
            </x-slot:head>
            @forelse ($rules as $rule)
                <x-admin.table.row>
                    <x-admin.table.cell><span class="font-medium text-v-text">{{ $rule->name }}</span></x-admin.table.cell>
                    <x-admin.table.cell><span class="text-v-secondary">{{ $rule->getConditionsSummary() }}</span></x-admin.table.cell>
                    <x-admin.table.cell>{{ $rule->sort_order }}</x-admin.table.cell>
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
                            <a href="{{ route('admin.free-shipping-rules.edit', $rule) }}" class="text-[13px] text-v-accent hover:underline">Edit</a>
                            <button type="button" class="text-[13px] text-v-danger hover:underline" @click="$dispatch('open-modal', 'delete-fsr-{{ $rule->id }}')">Delete</button>
                        </div>
                    </x-admin.table.cell>
                </x-admin.table.row>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-2">
                        <x-admin.empty-state icon="cart" title="No free shipping rules" description="Create a rule to automatically waive shipping when conditions are met.">
                            <a href="{{ route('admin.free-shipping-rules.create') }}" class="text-[13px] text-v-accent hover:underline">Create your first rule</a>
                        </x-admin.empty-state>
                    </td>
                </tr>
            @endforelse
        </x-admin.table>

        @foreach ($rules as $rule)
            <x-admin.confirm-dialog name="delete-fsr-{{ $rule->id }}" title="Delete “{{ $rule->name }}”?"
                message="This free shipping rule will stop applying immediately." confirm="Delete"
                :action="route('admin.free-shipping-rules.destroy', $rule)" method="DELETE" />
        @endforeach
    </x-admin.page>
</x-admin.shell>
