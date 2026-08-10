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
                <x-admin.table.th class="v-col-act">Actions</x-admin.table.th>
            </x-slot:head>
            @forelse ($rules as $rule)
                <x-admin.table.row>
                    <x-admin.table.cell><span class="font-medium text-v-text">{{ $rule->name }}</span></x-admin.table.cell>
                    <x-admin.table.cell><span class="text-v-secondary">{{ $rule->getConditionsSummary() }}</span></x-admin.table.cell>
                    <x-admin.table.cell>{{ $rule->sort_order }}</x-admin.table.cell>
                    <x-admin.table.cell>
                        <form method="POST" action="{{ route('admin.free-shipping-rules.toggle', $rule) }}" data-v-form>
                            @csrf
                            <button type="submit" class="v-badgebtn">
                                <x-admin.badge :tone="$rule->is_active ? 'success' : 'neutral'">
                                    {{ $rule->is_active ? 'Active' : 'Disabled' }}
                                </x-admin.badge>
                            </button>
                        </form>
                    </x-admin.table.cell>
                    <x-admin.table.cell class="v-col-act">
                        <div class="v-rowactions v-rowactions--end">
                            <x-admin.button size="sm" variant="ghost" :href="route('admin.free-shipping-rules.edit', $rule)">Edit</x-admin.button>
                            <x-admin.button size="sm" variant="ghost" class="v-rowactions__danger" type="button"
                                @click="$dispatch('open-modal', 'delete-fsr-{{ $rule->id }}')">Delete</x-admin.button>
                        </div>
                    </x-admin.table.cell>
                </x-admin.table.row>
            @empty
                <tr>
                    <td colspan="5">
                        <x-admin.empty-state title="No free shipping rules" description="Create a rule to automatically waive shipping when conditions are met.">
                            <x-admin.button variant="primary" :href="route('admin.free-shipping-rules.create')">Create your first rule</x-admin.button>
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
