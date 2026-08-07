<x-admin.shell title="Blog Categories">
    <x-admin.page title="Blog Categories" :breadcrumb="[['label' => 'Content'], ['label' => 'Blog Posts', 'url' => route('admin.blog.index')]]">
        <x-slot:actions>
            <x-admin.button variant="secondary" href="{{ route('admin.blog.index') }}">Back to Posts</x-admin.button>
            <x-admin.button variant="primary" href="{{ route('admin.blog-categories.create') }}">
                <x-admin.icon name="plus" class="w-4 h-4" /> Add Category
            </x-admin.button>
        </x-slot:actions>

        <x-admin.table>
            <x-slot:head>
                <x-admin.table.th>Name</x-admin.table.th>
                <x-admin.table.th>Slug</x-admin.table.th>
                <x-admin.table.th align="right">Posts</x-admin.table.th>
                <x-admin.table.th align="right">Sort</x-admin.table.th>
                <x-admin.table.th align="right">Actions</x-admin.table.th>
            </x-slot:head>
            @forelse ($categories as $category)
                <x-admin.table.row href="{{ route('admin.blog-categories.edit', $category) }}">
                    <x-admin.table.cell>
                        <span class="text-[13px] font-medium text-v-text">{{ $category->name }}</span>
                    </x-admin.table.cell>
                    <x-admin.table.cell mono>{{ $category->slug }}</x-admin.table.cell>
                    <x-admin.table.cell align="right" mono>{{ $category->posts_count }}</x-admin.table.cell>
                    <x-admin.table.cell align="right" mono>{{ $category->sort_order }}</x-admin.table.cell>
                    <x-admin.table.cell align="right">
                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('admin.blog-categories.edit', $category) }}" class="text-[13px] text-v-accent hover:opacity-80">Edit</a>
                            <button type="button" class="text-[13px] text-v-danger hover:opacity-80" @click="$dispatch('open-modal', 'delete-blog-category-{{ $category->id }}')">Delete</button>
                        </div>
                    </x-admin.table.cell>
                </x-admin.table.row>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-2">
                        <x-admin.empty-state icon="categories" title="No blog categories yet">
                            <a href="{{ route('admin.blog-categories.create') }}" class="text-[13px] text-v-accent hover:underline">Create one</a>
                        </x-admin.empty-state>
                    </td>
                </tr>
            @endforelse
        </x-admin.table>

        @foreach ($categories as $category)
            <x-admin.confirm-dialog name="delete-blog-category-{{ $category->id }}" title="Delete {{ $category->name }}?"
                message="This cannot be undone." confirm="Delete" :action="route('admin.blog-categories.destroy', $category)" method="DELETE" />
        @endforeach
    </x-admin.page>
</x-admin.shell>
