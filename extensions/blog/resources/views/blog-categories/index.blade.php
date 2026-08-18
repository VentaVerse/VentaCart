<x-admin3.shell title="Blog Categories">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.blog.index') }}">Blog</a>
                <span aria-hidden="true">/</span>
                <span>Categories</span>
            </div>
            <h1 class="v3-head__title">Blog Categories</h1>
        </div>
        <div class="v3-head__actions">
            <a href="{{ route('admin.blog.index') }}" class="v3-btn v3-btn--secondary">Posts</a>
            <a href="{{ route('admin.blog-categories.create') }}" class="v3-btn v3-btn--primary">Add category</a>
        </div>
    </div>

    <div class="v3-settle">
        <x-admin3.panel flush>
            <div class="v3-tablewrap">
                <table class="v3-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Slug</th>
                            <th class="v3-cell-num">Posts</th>
                            <th class="v3-cell-num">Sort</th>
                            <th class="v3-cell-act"><span class="v3-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categories as $category)
                            <tr>
                                <td><a href="{{ route('admin.blog-categories.edit', $category) }}" class="v3-rowlink">{{ $category->name }}</a></td>
                                <td class="v3-mono v3-cell-quiet">{{ $category->slug }}</td>
                                <td class="v3-cell-num v3-mono">{{ $category->posts_count }}</td>
                                <td class="v3-cell-num v3-mono">{{ $category->sort_order }}</td>
                                <td class="v3-cell-act">
                                    <a href="{{ route('admin.blog-categories.edit', $category) }}" class="v3-btn v3-btn--ghost v3-btn--sm">Edit</a>
                                    <button type="button" class="v3-btn v3-btn--danger v3-btn--sm"
                                            @click="$dispatch('v3-confirm', 'delete-blog-category-{{ $category->id }}')">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="v3-empty">
                                        <p class="v3-empty__title">No blog categories yet</p>
                                        <a href="{{ route('admin.blog-categories.create') }}" class="v3-btn v3-btn--primary v3-btn--sm">Create one</a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin3.panel>

        @foreach ($categories as $category)
            <x-admin3.confirm name="delete-blog-category-{{ $category->id }}" title="Delete {{ $category->name }}?"
                message="This cannot be undone." confirm="Delete" :action="route('admin.blog-categories.destroy', $category)" method="DELETE" />
        @endforeach
    </div>
</x-admin3.shell>
