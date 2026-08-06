<x-admin.shell title="Blog Posts">
    <x-admin.page title="Blog Posts" :breadcrumb="[['label' => 'Content']]">
        <x-slot:actions>
            <x-admin.button variant="secondary" href="{{ route('admin.blog-categories.index') }}">Categories</x-admin.button>
            <x-admin.button variant="primary" href="{{ route('admin.blog.create') }}">
                <x-admin.icon name="plus" class="w-4 h-4" /> New Post
            </x-admin.button>
        </x-slot:actions>

        <div class="space-y-5">
            <x-admin.card>
                <form method="GET" action="{{ route('admin.blog.index') }}" class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[220px] flex-1">
                        <x-admin.field name="search" label="Search">
                            <x-admin.input type="text" name="search" :value="request('search')" placeholder="Search posts..." />
                        </x-admin.field>
                    </div>
                    <div class="w-48">
                        <x-admin.field name="category_id" label="Category">
                            <x-admin.select name="category_id">
                                <option value="">All Categories</option>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                @endforeach
                            </x-admin.select>
                        </x-admin.field>
                    </div>
                    <div class="w-40">
                        <x-admin.field name="status" label="Status">
                            <x-admin.select name="status">
                                <option value="">All Status</option>
                                <option value="published" {{ request('status') === 'published' ? 'selected' : '' }}>Published</option>
                                <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                            </x-admin.select>
                        </x-admin.field>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-admin.button type="submit" variant="primary">Filter</x-admin.button>
                        @if (request()->anyFilled(['search', 'category_id', 'status']))
                            <a href="{{ route('admin.blog.index') }}" class="text-[13px] text-v-muted underline hover:text-v-text">Clear</a>
                        @endif
                    </div>
                </form>
            </x-admin.card>

            <x-admin.table>
                <x-slot:head>
                    <x-admin.table.th>Image</x-admin.table.th>
                    <x-admin.table.th>Title</x-admin.table.th>
                    <x-admin.table.th>Category</x-admin.table.th>
                    <x-admin.table.th>Status</x-admin.table.th>
                    <x-admin.table.th align="right">Views</x-admin.table.th>
                    <x-admin.table.th>Published</x-admin.table.th>
                    <x-admin.table.th align="right">Actions</x-admin.table.th>
                </x-slot:head>
                @forelse ($posts as $post)
                    <x-admin.table.row href="{{ route('admin.blog.edit', $post) }}">
                        <x-admin.table.cell>
                            @if ($post->featured_image)
                                <img src="{{ storage_url($post->featured_image) }}" alt="" class="w-12 h-12 object-cover rounded-v-sm">
                            @else
                                <div class="w-12 h-12 rounded-v-sm bg-v-sunken flex items-center justify-center text-v-muted">
                                    <x-admin.icon name="blog" class="w-5 h-5" />
                                </div>
                            @endif
                        </x-admin.table.cell>
                        <x-admin.table.cell>
                            <div class="text-[13px] font-medium text-v-text">{{ $post->title }}</div>
                            <div class="text-xs text-v-muted">{{ $post->slug }}</div>
                        </x-admin.table.cell>
                        <x-admin.table.cell>{{ $post->category?->name ?? '—' }}</x-admin.table.cell>
                        <x-admin.table.cell>
                            <x-admin.badge :tone="$post->is_published ? 'success' : 'warning'">
                                {{ $post->is_published ? 'Published' : 'Draft' }}
                            </x-admin.badge>
                        </x-admin.table.cell>
                        <x-admin.table.cell align="right" mono>{{ number_format($post->view_count) }}</x-admin.table.cell>
                        <x-admin.table.cell>{{ $post->published_at?->format('M d, Y') ?? '—' }}</x-admin.table.cell>
                        <x-admin.table.cell align="right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.blog.edit', $post) }}" class="text-[13px] text-v-accent hover:opacity-80">Edit</a>
                                <button type="button" class="text-[13px] text-v-danger hover:opacity-80" @click="$dispatch('open-modal', 'delete-post-{{ $post->id }}')">Delete</button>
                            </div>
                        </x-admin.table.cell>
                    </x-admin.table.row>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-2">
                            <x-admin.empty-state icon="blog" title="No blog posts yet" :description="request()->anyFilled(['search', 'category_id', 'status']) ? 'Try adjusting your filters.' : null">
                                <a href="{{ route('admin.blog.create') }}" class="text-[13px] text-v-accent hover:underline">Create your first post</a>
                            </x-admin.empty-state>
                        </td>
                    </tr>
                @endforelse
                @if ($posts->hasPages())
                    <x-slot:footer>
                        {{ $posts->links() }}
                    </x-slot:footer>
                @endif
            </x-admin.table>
        </div>

        @foreach ($posts as $post)
            <x-admin.confirm-dialog name="delete-post-{{ $post->id }}" title="Delete {{ $post->title }}?"
                message="This cannot be undone." confirm="Delete" :action="route('admin.blog.destroy', $post)" method="DELETE" />
        @endforeach
    </x-admin.page>
</x-admin.shell>
