<x-admin.shell title="Blog Posts">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <span>Blog</span>
            </div>
            <h1 class="v-head__title">Blog Posts</h1>
        </div>
        <div class="v-head__actions">
            <a href="{{ route('admin.blog-categories.index') }}" class="v-btn v-btn--secondary">Categories</a>
            <a href="{{ route('admin.blog.create') }}" class="v-btn v-btn--primary">New post</a>
            <x-admin.extension-switch name="blog" />
        </div>
    </div>

    <div class="v-settle">
        <form method="GET" action="{{ route('admin.blog.index') }}" class="v-filterbar">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Title or slug…"
                   aria-label="Search posts" class="v-control v-filterbar__grow">
            <select name="category_id" aria-label="Filter by category" class="v-control v-w-name">
                <option value="">All categories</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}" @selected((string) request('category_id') === (string) $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>
            <select name="status" aria-label="Filter by status" class="v-control v-w-name">
                <option value="">All statuses</option>
                <option value="published" @selected(request('status') === 'published')>Published</option>
                <option value="draft" @selected(request('status') === 'draft')>Draft</option>
            </select>
            <button type="submit" class="v-btn v-btn--secondary">Filter</button>
            @if (request()->anyFilled(['search', 'category_id', 'status']))
                <a href="{{ route('admin.blog.index') }}" class="v-link">Clear</a>
            @endif
        </form>

        <x-admin.panel flush>
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr>
                            <th><span class="v-sr">Image</span></th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th class="v-cell-num">Views</th>
                            <th>Published</th>
                            <th class="v-cell-act"><span class="v-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($posts as $post)
                            <tr>
                                <td>
                                    @if ($post->featured_image)
                                        <img src="{{ storage_url($post->featured_image) }}" alt="" class="v-thumb">
                                    @else
                                        <span class="v-thumb v-thumb--blank"><x-admin.icon name="text" /></span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('admin.blog.edit', $post) }}" class="v-rowlink">{{ $post->title }}</a>
                                    <span class="v-cell-quiet v-block v-mono">{{ $post->slug }}</span>
                                </td>
                                <td>{{ $post->category?->name ?? '-' }}</td>
                                <td>
                                    <span class="v-badge v-badge--{{ $post->is_published ? 'good' : 'warn' }}">
                                        {{ $post->is_published ? 'Published' : 'Draft' }}
                                    </span>
                                </td>
                                <td class="v-cell-num v-mono">{{ number_format($post->view_count) }}</td>
                                <td class="v-cell-quiet">{{ $post->published_at?->format('M d, Y') ?? '-' }}</td>
                                <td class="v-cell-act">
                                    <a href="{{ route('admin.blog.edit', $post) }}" class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                                    <button type="button" class="v-btn v-btn--danger v-btn--sm"
                                            @click="$dispatch('v-confirm', 'delete-post-{{ $post->id }}')">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="v-empty">
                                        <p class="v-empty__title">No blog posts yet</p>
                                        <p class="v-empty__line">{{ request()->anyFilled(['search', 'category_id', 'status']) ? 'Try adjusting the filters.' : 'Posts publish to the storefront blog.' }}</p>
                                        @unless (request()->anyFilled(['search', 'category_id', 'status']))
                                            <a href="{{ route('admin.blog.create') }}" class="v-btn v-btn--primary v-btn--sm">Create your first post</a>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.panel>

        <x-admin.pager :paginator="$posts" />

        @foreach ($posts as $post)
            <x-admin.confirm name="delete-post-{{ $post->id }}" title="Delete {{ $post->title }}?"
                message="This cannot be undone." confirm="Delete" :action="route('admin.blog.destroy', $post)" method="DELETE" />
        @endforeach
    </div>
</x-admin.shell>
