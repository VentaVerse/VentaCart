<x-admin3.shell title="Blog Posts">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <span>Blog</span>
            </div>
            <h1 class="v3-head__title">Blog Posts</h1>
        </div>
        <div class="v3-head__actions">
            <a href="{{ route('admin.blog-categories.index') }}" class="v3-btn v3-btn--secondary">Categories</a>
            <a href="{{ route('admin.blog.create') }}" class="v3-btn v3-btn--primary">New post</a>
            <x-admin3.extension-switch name="blog" />
        </div>
    </div>

    <div class="v3-settle">
        <form method="GET" action="{{ route('admin.blog.index') }}" class="v3-filterbar">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Title or slug…"
                   aria-label="Search posts" class="v3-control v3-filterbar__grow">
            <select name="category_id" aria-label="Filter by category" class="v3-control v3-w-name">
                <option value="">All categories</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}" @selected((string) request('category_id') === (string) $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>
            <select name="status" aria-label="Filter by status" class="v3-control v3-w-name">
                <option value="">All statuses</option>
                <option value="published" @selected(request('status') === 'published')>Published</option>
                <option value="draft" @selected(request('status') === 'draft')>Draft</option>
            </select>
            <button type="submit" class="v3-btn v3-btn--secondary">Filter</button>
            @if (request()->anyFilled(['search', 'category_id', 'status']))
                <a href="{{ route('admin.blog.index') }}" class="v3-link">Clear</a>
            @endif
        </form>

        <x-admin3.panel flush>
            <div class="v3-tablewrap">
                <table class="v3-table">
                    <thead>
                        <tr>
                            <th><span class="v3-sr">Image</span></th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th class="v3-cell-num">Views</th>
                            <th>Published</th>
                            <th class="v3-cell-act"><span class="v3-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($posts as $post)
                            <tr>
                                <td>
                                    @if ($post->featured_image)
                                        <img src="{{ storage_url($post->featured_image) }}" alt="" class="v3-thumb">
                                    @else
                                        <span class="v3-thumb v3-thumb--blank"><x-admin3.icon name="text" /></span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('admin.blog.edit', $post) }}" class="v3-rowlink">{{ $post->title }}</a>
                                    <span class="v3-cell-quiet v3-block v3-mono">{{ $post->slug }}</span>
                                </td>
                                <td>{{ $post->category?->name ?? '-' }}</td>
                                <td>
                                    <span class="v3-badge v3-badge--{{ $post->is_published ? 'good' : 'warn' }}">
                                        {{ $post->is_published ? 'Published' : 'Draft' }}
                                    </span>
                                </td>
                                <td class="v3-cell-num v3-mono">{{ number_format($post->view_count) }}</td>
                                <td class="v3-cell-quiet">{{ $post->published_at?->format('M d, Y') ?? '-' }}</td>
                                <td class="v3-cell-act">
                                    <a href="{{ route('admin.blog.edit', $post) }}" class="v3-btn v3-btn--ghost v3-btn--sm">Edit</a>
                                    <button type="button" class="v3-btn v3-btn--danger v3-btn--sm"
                                            @click="$dispatch('v3-confirm', 'delete-post-{{ $post->id }}')">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="v3-empty">
                                        <p class="v3-empty__title">No blog posts yet</p>
                                        <p class="v3-empty__line">{{ request()->anyFilled(['search', 'category_id', 'status']) ? 'Try adjusting the filters.' : 'Posts publish to the storefront blog.' }}</p>
                                        @unless (request()->anyFilled(['search', 'category_id', 'status']))
                                            <a href="{{ route('admin.blog.create') }}" class="v3-btn v3-btn--primary v3-btn--sm">Create your first post</a>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin3.panel>

        <x-admin3.pager :paginator="$posts" />

        @foreach ($posts as $post)
            <x-admin3.confirm name="delete-post-{{ $post->id }}" title="Delete {{ $post->title }}?"
                message="This cannot be undone." confirm="Delete" :action="route('admin.blog.destroy', $post)" method="DELETE" />
        @endforeach
    </div>
</x-admin3.shell>
