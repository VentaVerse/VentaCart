<x-admin3.shell title="Tags" active="catalog">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Tags</h1>
        </div>
        <div class="v3-head__actions">
            <a href="{{ route('admin.tags.create') }}" class="v3-btn v3-btn--primary">Add tag</a>
        </div>
    </div>

    <div class="v3-settle">
        <form method="GET" action="{{ route('admin.tags.index') }}" class="v3-filterbar">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Tag name…"
                   aria-label="Search tags" class="v3-control v3-filterbar__grow">
            <button type="submit" class="v3-btn v3-btn--secondary">Search</button>
            @if (request('search'))
                <a href="{{ route('admin.tags.index') }}" class="v3-link">Clear</a>
            @endif
        </form>

        <x-admin3.panel flush>
            <div class="v3-tablewrap">
                <table class="v3-table">
                    <thead>
                        <tr>
                            <th>Tag</th>
                            <th>Slug</th>
                            <th class="v3-cell-num">Products</th>
                            <th class="v3-cell-num">Blog posts</th>
                            <th class="v3-cell-act"><span class="v3-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tags as $tag)
                            <tr>
                                <td><a href="{{ route('admin.tags.edit', $tag) }}" class="v3-rowlink">{{ $tag->name }}</a></td>
                                <td class="v3-mono v3-cell-quiet">{{ $tag->slug }}</td>
                                <td class="v3-cell-num v3-mono">{{ $tag->products_count }}</td>
                                <td class="v3-cell-num v3-mono">{{ $tag->blog_posts_count }}</td>
                                <td class="v3-cell-act">
                                    <a href="{{ route('admin.tags.edit', $tag) }}" class="v3-btn v3-btn--ghost v3-btn--sm">Edit</a>
                                    <button type="button" class="v3-btn v3-btn--danger v3-btn--sm"
                                            @click="$dispatch('v3-confirm', 'delete-tag-{{ $tag->id }}')">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="v3-empty">
                                        <p class="v3-empty__title">No tags yet</p>
                                        <p class="v3-empty__line">{{ request('search') ? 'Try adjusting the search.' : 'Tags group products and blog posts across the catalog.' }}</p>
                                        @unless (request('search'))
                                            <a href="{{ route('admin.tags.create') }}" class="v3-btn v3-btn--primary v3-btn--sm">Create one</a>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin3.panel>

        <x-admin3.pager :paginator="$tags" />

        @foreach ($tags as $tag)
            <x-admin3.confirm name="delete-tag-{{ $tag->id }}" title="Delete {{ $tag->name }}?"
                message="This removes the tag from all products and blog posts. This cannot be undone."
                confirm="Delete" :action="route('admin.tags.destroy', $tag)" method="DELETE" />
        @endforeach
    </div>
</x-admin3.shell>
