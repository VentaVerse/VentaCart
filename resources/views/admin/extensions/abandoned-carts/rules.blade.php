<x-admin.shell title="Rules">
    <x-admin.page title="Rules">
        <x-slot:actions>
            <x-admin.button href="{{ route('admin.abandoned-carts.rules.create') }}">Create Rule</x-admin.button>
        </x-slot:actions>

        @include('admin.extensions.abandoned-carts.partials.nav')

        <div class="space-y-4">
                <x-admin.table>
                    <x-slot name="head">
                        <x-admin.table.th>Priority</x-admin.table.th>
                        <x-admin.table.th>Template</x-admin.table.th>
                        <x-admin.table.th>Customer Group</x-admin.table.th>
                        <x-admin.table.th>Max Sends</x-admin.table.th>
                        <x-admin.table.th>Min Age</x-admin.table.th>
                        <x-admin.table.th>Status</x-admin.table.th>
                        <x-admin.table.th class="v-col-act">Actions</x-admin.table.th>
                    </x-slot>
                    @forelse ($rules as $rule)
                        <x-admin.table.row>
                            <x-admin.table.cell mono class="font-medium text-v-text">{{ $rule->priority }}</x-admin.table.cell>
                            <x-admin.table.cell class="text-v-text">{{ $rule->emailTemplate->name ?? '-' }}</x-admin.table.cell>
                            <x-admin.table.cell class="text-v-secondary">{{ $rule->customerGroup->name ?? 'All Groups' }}</x-admin.table.cell>
                            <x-admin.table.cell class="text-v-secondary">{{ $rule->max_sends }}</x-admin.table.cell>
                            <x-admin.table.cell class="text-v-secondary">{{ $rule->min_cart_age_hours }}h</x-admin.table.cell>
                            <x-admin.table.cell>
                                <x-admin.badge :tone="$rule->is_active ? 'success' : 'neutral'">{{ $rule->is_active ? 'Active' : 'Inactive' }}</x-admin.badge>
                            </x-admin.table.cell>
                            <x-admin.table.cell class="v-col-act">
                                <div class="v-rowactions v-rowactions--end">
                                    <x-admin.button size="sm" variant="ghost" :href="route('admin.abandoned-carts.rules.edit', $rule)">Edit</x-admin.button>
                                    <x-admin.button size="sm" variant="ghost" class="v-rowactions__danger" type="button"
                                        @click="$dispatch('open-modal', 'delete-rule-{{ $rule->id }}')">Delete</x-admin.button>
                                </div>
                                <x-admin.confirm-dialog name="delete-rule-{{ $rule->id }}" title="Delete rule"
                                    message="Delete this rule? This cannot be undone." confirm="Delete"
                                    :action="route('admin.abandoned-carts.rules.destroy', $rule)" method="DELETE" />
                            </x-admin.table.cell>
                        </x-admin.table.row>
                    @empty
                        <tr><td colspan="7"><x-admin.empty-state title="No rules configured yet" /></td></tr>
                    @endforelse
                </x-admin.table>
        </div>
    </x-admin.page>
</x-admin.shell>
