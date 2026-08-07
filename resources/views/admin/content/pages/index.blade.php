<x-admin.shell title="Pages">
    <x-admin.page title="Pages">
        <x-slot:actions>
            <x-admin.button variant="primary" href="{{ route('admin.pages.create') }}">
                <x-admin.icon name="plus" class="w-4 h-4" /> New Page
            </x-admin.button>
        </x-slot:actions>

        <div class="space-y-5">
            <x-admin.card>
                <form method="GET" action="{{ route('admin.pages.index') }}" class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[220px] flex-1">
                        <x-admin.field name="search" label="Search">
                            <x-admin.input type="text" name="search" :value="request('search')" placeholder="Search pages..." />
                        </x-admin.field>
                    </div>
                    <div class="w-40">
                        <x-admin.field name="status" label="Status">
                            <x-admin.select name="status">
                                <option value="">All statuses</option>
                                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </x-admin.select>
                        </x-admin.field>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-admin.button type="submit" variant="primary">Filter</x-admin.button>
                        @if (request()->anyFilled(['search', 'status']))
                            <a href="{{ route('admin.pages.index') }}" class="text-[13px] text-v-muted underline hover:text-v-text">Clear</a>
                        @endif
                    </div>
                </form>
            </x-admin.card>

            <x-admin.table>
                <x-slot:head>
                    <x-admin.table.th>Title</x-admin.table.th>
                    <x-admin.table.th>URL</x-admin.table.th>
                    <x-admin.table.th align="right">Order</x-admin.table.th>
                    <x-admin.table.th>Status</x-admin.table.th>
                    <x-admin.table.th align="right">Actions</x-admin.table.th>
                </x-slot:head>
                @forelse ($pages as $page)
                    <x-admin.table.row href="{{ route('admin.pages.edit', $page) }}">
                        <x-admin.table.cell>
                            <span class="text-[13px] font-medium text-v-text">{{ $page->title }}</span>
                        </x-admin.table.cell>
                        <x-admin.table.cell mono>
                            <a href="/{{ $page->slug }}" target="_blank" class="text-xs text-v-muted hover:text-v-accent">/{{ $page->slug }}</a>
                        </x-admin.table.cell>
                        <x-admin.table.cell align="right" mono>{{ $page->sort_order }}</x-admin.table.cell>
                        <x-admin.table.cell>
                            <x-admin.badge :tone="$page->is_active ? 'success' : 'neutral'">
                                {{ $page->is_active ? 'Active' : 'Inactive' }}
                            </x-admin.badge>
                        </x-admin.table.cell>
                        <x-admin.table.cell align="right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.pages.edit', $page) }}" class="text-[13px] text-v-accent hover:opacity-80">Edit</a>
                                <button type="button" class="text-[13px] text-v-danger hover:opacity-80" @click="$dispatch('open-modal', 'delete-page-{{ $page->id }}')">Delete</button>
                            </div>
                        </x-admin.table.cell>
                    </x-admin.table.row>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-2">
                            <x-admin.empty-state icon="pages" title="No pages yet" :description="request()->anyFilled(['search', 'status']) ? 'Try adjusting your filters.' : null">
                                <a href="{{ route('admin.pages.create') }}" class="text-[13px] text-v-accent hover:underline">Create your first page</a>
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
