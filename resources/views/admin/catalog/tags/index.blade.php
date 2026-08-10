<x-admin.shell title="Tags">
    <x-admin.page title="Tags">
        <x-slot:actions>
            <x-admin.button variant="primary" href="{{ route('admin.tags.create') }}">
                <x-admin.icon name="plus" class="w-4 h-4" /> Add Tag
            </x-admin.button>
        </x-slot:actions>

        <div>
            <x-admin.filters :action="route('admin.tags.index')">
                <x-admin.field name="search" label="Search" class="v-field--grow">
                    <x-admin.input type="text" name="search" :value="request('search')" placeholder="Search tags..." />
                </x-admin.field>
                <x-admin.button type="submit" variant="secondary">Search</x-admin.button>
                @if (request('search'))
                    <a href="{{ route('admin.tags.index') }}" class="v-filters__reset">Clear</a>
                @endif
            </x-admin.filters>

            <x-admin.table>
                <x-slot:head>
                    <x-admin.table.th>Tag</x-admin.table.th>
                    <x-admin.table.th>Slug</x-admin.table.th>
                    <x-admin.table.th align="right">Products</x-admin.table.th>
                    <x-admin.table.th align="right">Blog Posts</x-admin.table.th>
                    <x-admin.table.th class="v-col-act">Actions</x-admin.table.th>
                </x-slot:head>
                @forelse ($tags as $tag)
                    <x-admin.table.row href="{{ route('admin.tags.edit', $tag) }}">
                        <x-admin.table.cell>
                            <span class="text-[13px] font-medium text-v-text">{{ $tag->name }}</span>
                        </x-admin.table.cell>
                        <x-admin.table.cell mono>{{ $tag->slug }}</x-admin.table.cell>
                        <x-admin.table.cell align="right" mono>{{ $tag->products_count }}</x-admin.table.cell>
                        <x-admin.table.cell align="right" mono>{{ $tag->blog_posts_count }}</x-admin.table.cell>
                        <x-admin.table.cell class="v-col-act">
                            <div class="v-rowactions v-rowactions--end">
                                <x-admin.button size="sm" variant="ghost" :href="route('admin.tags.edit', $tag)">Edit</x-admin.button>
                                <x-admin.button size="sm" variant="ghost" class="v-rowactions__danger" @click="$dispatch('open-modal', 'delete-tag-{{ $tag->id }}')">Delete</x-admin.button>
                            </div>
                        </x-admin.table.cell>
                    </x-admin.table.row>
                @empty
                    <tr>
                        <td colspan="5">
                            <x-admin.empty-state title="No tags yet" :description="request('search') ? 'Try adjusting your search.' : null">
                                <x-admin.button variant="primary" :href="route('admin.tags.create')">Create one</x-admin.button>
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
