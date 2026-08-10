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
                <x-admin.table.th class="v-col-act">Actions</x-admin.table.th>
            </x-slot:head>
            @forelse ($groups as $group)
                <x-admin.table.row href="{{ route('admin.customer-groups.edit', $group) }}">
                    <x-admin.table.cell>
                        <span class="v-lead">{{ $group->name }}</span>
                        @if ($group->description)
                            <span class="v-cellstack__note">{{ Str::limit($group->description, 60) }}</span>
                        @endif
                    </x-admin.table.cell>
                    <x-admin.table.cell align="right" mono>{{ $group->customers_count }}</x-admin.table.cell>
                    <x-admin.table.cell>
                        @if ($group->is_default)
                            <x-admin.badge tone="success">Default</x-admin.badge>
                        @endif
                    </x-admin.table.cell>
                    <x-admin.table.cell class="v-col-act">
                        <div class="v-rowactions v-rowactions--end">
                            <x-admin.button size="sm" variant="ghost" :href="route('admin.customer-groups.edit', $group)">Edit</x-admin.button>
                            @if ($group->customers_count === 0)
                                <x-admin.button size="sm" variant="ghost" class="v-rowactions__danger" @click="$dispatch('open-modal', 'delete-group-{{ $group->id }}')">Delete</x-admin.button>
                            @else
                                <span class="v-none text-xs" title="Cannot delete: group has customers">{{ $group->customers_count }} customer{{ $group->customers_count > 1 ? 's' : '' }}</span>
                            @endif
                        </div>
                    </x-admin.table.cell>
                </x-admin.table.row>
            @empty
                <tr>
                    <td colspan="4">
                        <x-admin.empty-state title="No customer groups found">
                            <x-admin.button variant="primary" :href="route('admin.customer-groups.create')">Create your first group</x-admin.button>
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
