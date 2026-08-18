<x-admin.shell title="Blog Categories">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.blog.index') }}">Blog</a>
                <span aria-hidden="true">/</span>
                <span>Categories</span>
            </div>
            <h1 class="v-head__title">Blog Categories</h1>
        </div>
        <div class="v-head__actions">
            <a href="{{ route('admin.blog.index') }}" class="v-btn v-btn--secondary">Posts</a>
            <a href="{{ route('admin.blog-categories.create') }}" class="v-btn v-btn--primary">Add category</a>
        </div>
    </div>

    <div class="v-settle">
        <x-admin.panel flush>
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Slug</th>
                            <th class="v-cell-num">Posts</th>
                            <th class="v-cell-num">Sort</th>
                            <th class="v-cell-act"><span class="v-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categories as $category)
                            <tr>
                                <td><a href="{{ route('admin.blog-categories.edit', $category) }}" class="v-rowlink">{{ $category->name }}</a></td>
                                <td class="v-mono v-cell-quiet">{{ $category->slug }}</td>
                                <td class="v-cell-num v-mono">{{ $category->posts_count }}</td>
                                <td class="v-cell-num v-mono">{{ $category->sort_order }}</td>
                                <td class="v-cell-act">
                                    <a href="{{ route('admin.blog-categories.edit', $category) }}" class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                                    <button type="button" class="v-btn v-btn--danger v-btn--sm"
                                            @click="$dispatch('v-confirm', 'delete-blog-category-{{ $category->id }}')">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="v-empty">
                                        <p class="v-empty__title">No blog categories yet</p>
                                        <a href="{{ route('admin.blog-categories.create') }}" class="v-btn v-btn--primary v-btn--sm">Create one</a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.panel>

        @foreach ($categories as $category)
            <x-admin.confirm name="delete-blog-category-{{ $category->id }}" title="Delete {{ $category->name }}?"
                message="This cannot be undone." confirm="Delete" :action="route('admin.blog-categories.destroy', $category)" method="DELETE" />
        @endforeach
    </div>
</x-admin.shell>
