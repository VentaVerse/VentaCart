<x-admin.shell title="Tags">
    <x-admin.page title="Tags" :breadcrumb="[['label' => 'Catalog']]">
        <x-slot:actions>
            <x-admin.button variant="primary" href="{{ route('admin.tags.create') }}">
                <x-admin.icon name="plus" class="w-4 h-4" /> Add Tag
            </x-admin.button>
        </x-slot:actions>

        <div class="space-y-5">
            <x-admin.card>
                <form method="GET" action="{{ route('admin.tags.index') }}" class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[220px]">
                        <x-admin.field name="search" label="Search">
                            <x-admin.input type="text" name="search" :value="request('search')" placeholder="Search tags..." />
                        </x-admin.field>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-admin.button type="submit" variant="primary">Search</x-admin.button>
                        @if (request('search'))
                            <a href="{{ route('admin.tags.index') }}" class="text-[13px] text-v-muted underline hover:text-v-text">Clear</a>
                        @endif
                    </div>
                </form>
            </x-admin.card>

            <x-admin.table>
                <x-slot:head>
                    <x-admin.table.th>Tag</x-admin.table.th>
                    <x-admin.table.th>Slug</x-admin.table.th>
                    <x-admin.table.th align="right">Products</x-admin.table.th>
                    <x-admin.table.th align="right">Blog Posts</x-admin.table.th>
                    <x-admin.table.th align="right">Actions</x-admin.table.th>
                </x-slot:head>
                @forelse ($tags as $tag)
                    <x-admin.table.row href="{{ route('admin.tags.edit', $tag) }}">
                        <x-admin.table.cell>
                            <span class="text-[13px] font-medium text-v-text">{{ $tag->name }}</span>
                        </x-admin.table.cell>
                        <x-admin.table.cell mono>{{ $tag->slug }}</x-admin.table.cell>
                        <x-admin.table.cell align="right" mono>{{ $tag->products_count }}</x-admin.table.cell>
                        <x-admin.table.cell align="right" mono>{{ $tag->blog_posts_count }}</x-admin.table.cell>
                        <x-admin.table.cell align="right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.tags.edit', $tag) }}" class="text-[13px] text-v-accent hover:opacity-80">Edit</a>
                                <button type="button" class="text-[13px] text-v-danger hover:opacity-80" @click="$dispatch('open-modal', 'delete-tag-{{ $tag->id }}')">Delete</button>
                            </div>
                        </x-admin.table.cell>
                    </x-admin.table.row>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-2">
                            <x-admin.empty-state icon="tags" title="No tags yet" :description="request('search') ? 'Try adjusting your search.' : null">
                                <a href="{{ route('admin.tags.create') }}" class="text-[13px] text-v-accent hover:underline">Create one</a>
                            </x-admin.empty-state>
                        </td>
                    </tr>
                @endforelse
                @if ($tags->hasPages())
                    <x-slot:footer>
                        {{ $tags->links() }}
                    </x-slot:footer>
                @endif
            </x-admin.table>
        </div>

        @foreach ($tags as $tag)
            <x-admin.confirm-dialog name="delete-tag-{{ $tag->id }}" title="Delete {{ $tag->name }}?"
                message="This removes the tag from all products and blog posts. This cannot be undone."
                confirm="Delete" :action="route('admin.tags.destroy', $tag)" method="DELETE" />
        @endforeach
    </x-admin.page>
</x-admin.shell>
