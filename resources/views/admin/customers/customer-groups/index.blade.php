<x-admin.shell title="Customer Groups">
    <x-admin.page title="Customer Groups">
        <x-slot:actions>
            <x-admin.button variant="primary" href="{{ route('admin.customer-groups.create') }}">
                <x-admin.icon name="plus" class="w-4 h-4" /> Add Group
            </x-admin.button>
        </x-slot:actions>

        <x-admin.table>
            <x-slot:head>
                <x-admin.table.th>Group</x-admin.table.th>
                <x-admin.table.th align="right">Customers</x-admin.table.th>
                <x-admin.table.th>Default</x-admin.table.th>
                <x-admin.table.th align="right">Actions</x-admin.table.th>
            </x-slot:head>
            @forelse ($groups as $group)
                <x-admin.table.row href="{{ route('admin.customer-groups.edit', $group) }}">
                    <x-admin.table.cell>
                        <div class="text-[13px] font-medium text-v-text">{{ $group->name }}</div>
                        @if ($group->description)
                            <div class="text-xs text-v-muted">{{ Str::limit($group->description, 60) }}</div>
                        @endif
                    </x-admin.table.cell>
                    <x-admin.table.cell align="right" mono>{{ $group->customers_count }}</x-admin.table.cell>
                    <x-admin.table.cell>
                        @if ($group->is_default)
                            <x-admin.badge tone="success">Default</x-admin.badge>
                        @endif
                    </x-admin.table.cell>
                    <x-admin.table.cell align="right">
                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('admin.customer-groups.edit', $group) }}" class="text-[13px] text-v-accent hover:opacity-80">Edit</a>
                            @if ($group->customers_count === 0)
                                <button type="button" class="text-[13px] text-v-danger hover:opacity-80" @click="$dispatch('open-modal', 'delete-group-{{ $group->id }}')">Delete</button>
                            @else
                                <span class="text-xs text-v-muted" title="Cannot delete — group has customers">{{ $group->customers_count }} customer{{ $group->customers_count > 1 ? 's' : '' }}</span>
                            @endif
                        </div>
                    </x-admin.table.cell>
                </x-admin.table.row>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-2">
                        <x-admin.empty-state icon="groups" title="No customer groups found">
                            <a href="{{ route('admin.customer-groups.create') }}" class="text-[13px] text-v-accent hover:underline">Create your first group</a>
                        </x-admin.empty-state>
                    </td>
                </tr>
            @endforelse
            @if ($groups->hasPages())
                <x-slot:footer>
                    {{ $groups->links() }}
                </x-slot:footer>
            @endif
        </x-admin.table>

        @foreach ($groups as $group)
            @if ($group->customers_count === 0)
                <x-admin.confirm-dialog name="delete-group-{{ $group->id }}" title="Delete {{ $group->name }}?"
                    message="This cannot be undone." confirm="Delete" :action="route('admin.customer-groups.destroy', $group)" method="DELETE" />
            @endif
        @endforeach
    </x-admin.page>
</x-admin.shell>
