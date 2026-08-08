<x-admin.settings-shell title="Rules" :wide="true">
        <x-slot:actions>
            <x-admin.button href="{{ route('admin.abandoned-carts.rules.create') }}">Create Rule</x-admin.button>
        </x-slot:actions>

        @include('admin.settings.modules.abandoned-carts.partials.nav')

        <div class="space-y-4">
                <p class="text-[13px] text-v-secondary">Rules determine which template to send, to which customer group, and how often.</p>

                <x-admin.table>
                    <x-slot name="head">
                        <x-admin.table.th>Priority</x-admin.table.th>
                        <x-admin.table.th>Template</x-admin.table.th>
                        <x-admin.table.th>Customer Group</x-admin.table.th>
                        <x-admin.table.th>Max Sends</x-admin.table.th>
                        <x-admin.table.th>Min Age</x-admin.table.th>
                        <x-admin.table.th>Status</x-admin.table.th>
                        <x-admin.table.th align="right">Actions</x-admin.table.th>
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
                            <x-admin.table.cell align="right">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('admin.abandoned-carts.rules.edit', $rule) }}" class="text-v-accent hover:underline">Edit</a>
                                    <button type="button" class="text-v-danger hover:underline"
                                        @click="$dispatch('open-modal', 'delete-rule-{{ $rule->id }}')">Delete</button>
                                </div>
                                <x-admin.confirm-dialog name="delete-rule-{{ $rule->id }}" title="Delete rule"
                                    message="Delete this rule? This cannot be undone." confirm="Delete"
                                    :action="route('admin.abandoned-carts.rules.destroy', $rule)" method="DELETE" />
                            </x-admin.table.cell>
                        </x-admin.table.row>
                    @empty
                        <tr><td colspan="7" class="px-4 py-2"><x-admin.empty-state icon="cart" title="No rules configured yet" /></td></tr>
                    @endforelse
                </x-admin.table>
        </div>
</x-admin.settings-shell>
