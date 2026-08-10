<x-admin.shell title="Banners">
    <x-admin.page title="Banners">
        <x-slot:actions>
            <x-admin.button variant="primary" href="{{ route('admin.banners.create') }}">
                <x-admin.icon name="plus" class="w-4 h-4" /> New Group
            </x-admin.button>
        </x-slot:actions>

        <x-admin.table>
            <x-slot:head>
                <x-admin.table.th>Group</x-admin.table.th>
                <x-admin.table.th align="right">Slides</x-admin.table.th>
                <x-admin.table.th>Rotation</x-admin.table.th>
                <x-admin.table.th>Status</x-admin.table.th>
                <x-admin.table.th class="v-col-act">Actions</x-admin.table.th>
            </x-slot:head>
            @forelse ($groups as $group)
                <x-admin.table.row href="{{ route('admin.banners.slides', $group) }}">
                    <x-admin.table.cell>
                        <div class="min-w-0">
                            <div class="truncate text-[13px] font-medium text-v-text">{{ $group->name }}</div>
                            <div class="v-mono text-xs text-v-muted">{{ $group->slug }}</div>
                        </div>
                    </x-admin.table.cell>
                    <x-admin.table.cell align="right" mono>{{ $group->slides_count }}</x-admin.table.cell>
                    <x-admin.table.cell>{{ $group->autoplay ? 'Every ' . $group->speed . 'ms' : 'Manual' }}</x-admin.table.cell>
                    <x-admin.table.cell>
                        <x-admin.badge :tone="$group->is_active ? 'success' : 'neutral'">{{ $group->is_active ? 'Active' : 'Draft' }}</x-admin.badge>
                    </x-admin.table.cell>
                    <x-admin.table.cell class="v-col-act">
                        <div class="v-rowactions v-rowactions--end">
                            <x-admin.button size="sm" variant="ghost" :href="route('admin.banners.slides', $group)">Slides</x-admin.button>
                            <x-admin.button size="sm" variant="ghost" :href="route('admin.banners.edit', $group)">Settings</x-admin.button>
                            <x-admin.button size="sm" variant="ghost" class="v-rowactions__danger" @click="$dispatch('open-modal', 'delete-group-{{ $group->id }}')">Delete</x-admin.button>
                        </div>
                    </x-admin.table.cell>
                </x-admin.table.row>
            @empty
                <tr>
                    <td colspan="5">
                        <x-admin.empty-state icon="media" title="No banner groups yet" description="A group is one carousel placement, and its slides live inside it.">
                            <x-admin.button variant="primary" :href="route('admin.banners.create')">Create your first group</x-admin.button>
                        </x-admin.empty-state>
                    </td>
                </tr>
            @endforelse
        </x-admin.table>

        @foreach ($groups as $group)
            <x-admin.confirm-dialog name="delete-group-{{ $group->id }}" title="Delete {{ $group->name }}?"
                message="Delete this group and all its slides?" confirm="Delete"
                :action="route('admin.banners.destroy', $group)" method="DELETE" />
        @endforeach
    </x-admin.page>
</x-admin.shell>
