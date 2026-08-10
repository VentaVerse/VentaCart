<x-admin.shell title="Pages">
    <x-admin.page title="Pages">
        <x-slot:actions>
            <x-admin.button variant="primary" href="{{ route('admin.pages.create') }}">
                <x-admin.icon name="plus" class="w-4 h-4" /> New Page
            </x-admin.button>
        </x-slot:actions>

        <div>
            <x-admin.filters :action="route('admin.pages.index')">
                <x-admin.field name="search" label="Search" class="v-field--grow">
                    <x-admin.input type="text" name="search" :value="request('search')" placeholder="Search pages..." />
                </x-admin.field>
                <x-admin.field name="status" label="Status" class="w-40">
                    <x-admin.select name="status">
                        <option value="">All statuses</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </x-admin.select>
                </x-admin.field>
                <x-admin.button type="submit" variant="secondary">Filter</x-admin.button>
                @if (request()->anyFilled(['search', 'status']))
                    <a href="{{ route('admin.pages.index') }}" class="v-filters__reset">Clear</a>
                @endif
            </x-admin.filters>

            <x-admin.table>
                <x-slot:head>
                    <x-admin.table.th>Title</x-admin.table.th>
                    <x-admin.table.th>URL</x-admin.table.th>
                    <x-admin.table.th align="right">Order</x-admin.table.th>
                    <x-admin.table.th>Status</x-admin.table.th>
                    <x-admin.table.th class="v-col-act">Actions</x-admin.table.th>
                </x-slot:head>
                @forelse ($pages as $page)
                    <x-admin.table.row href="{{ route('admin.pages.edit', $page) }}">
                        <x-admin.table.cell>
                            <span class="text-[13px] font-medium text-v-text">{{ $page->title }}</span>
                        </x-admin.table.cell>
                        <x-admin.table.cell>
                            <a href="/{{ $page->slug }}" target="_blank" class="v-sub">/{{ $page->slug }}</a>
                        </x-admin.table.cell>
                        <x-admin.table.cell align="right" mono>{{ $page->sort_order }}</x-admin.table.cell>
                        <x-admin.table.cell>
                            <x-admin.badge :tone="$page->is_active ? 'success' : 'neutral'">
                                {{ $page->is_active ? 'Active' : 'Inactive' }}
                            </x-admin.badge>
                        </x-admin.table.cell>
                        <x-admin.table.cell class="v-col-act">
                            <div class="v-rowactions v-rowactions--end">
                                <x-admin.button size="sm" variant="ghost" :href="route('admin.pages.edit', $page)">Edit</x-admin.button>
                                <x-admin.button size="sm" variant="ghost" class="v-rowactions__danger" @click="$dispatch('open-modal', 'delete-page-{{ $page->id }}')">Delete</x-admin.button>
                            </div>
                        </x-admin.table.cell>
                    </x-admin.table.row>
                @empty
                    <tr>
                        <td colspan="5">
                            <x-admin.empty-state title="No pages yet" :description="request()->anyFilled(['search', 'status']) ? 'Try adjusting your filters.' : null">
                                <x-admin.button variant="primary" :href="route('admin.pages.create')">Create your first page</x-admin.button>
                            </x-admin.empty-state>
                        </td>
                    </tr>
                @endforelse
                @if ($pages->hasPages())
                    <x-slot:footer>
                        {{ $pages->links() }}
                    </x-slot:footer>
                @endif
            </x-admin.table>
        </div>

        @foreach ($pages as $page)
            <x-admin.confirm-dialog name="delete-page-{{ $page->id }}" title="Delete {{ $page->title }}?"
                message="This cannot be undone." confirm="Delete" :action="route('admin.pages.destroy', $page)" method="DELETE" />
        @endforeach
    </x-admin.page>
</x-admin.shell>
