<x-admin.shell title="Tags" active="catalog">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">Tags</h1>
        </div>
        <div class="v-head__actions">
            <a href="{{ route('admin.tags.create') }}" class="v-btn v-btn--primary">Add tag</a>
        </div>
    </div>

    <div class="v-settle">
        <form method="GET" action="{{ route('admin.tags.index') }}" class="v-filterbar">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Tag name…"
                   aria-label="Search tags" class="v-control v-filterbar__grow">
            <button type="submit" class="v-btn v-btn--secondary">Search</button>
            @if (request('search'))
                <a href="{{ route('admin.tags.index') }}" class="v-link">Clear</a>
            @endif
        </form>

        <x-admin.panel flush>
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr>
                            <th>Tag</th>
                            <th>Slug</th>
                            <th class="v-cell-num">Products</th>
                            @if ($showBlogPosts)
                                <th class="v-cell-num">Blog posts</th>
                            @endif
                            <th class="v-cell-act"><span class="v-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tags as $tag)
                            <tr>
                                <td><a href="{{ route('admin.tags.edit', $tag) }}" class="v-rowlink">{{ $tag->name }}</a></td>
                                <td class="v-mono v-cell-quiet">{{ $tag->slug }}</td>
                                <td class="v-cell-num v-mono">{{ $tag->products_count }}</td>
                                {{-- Present only while Blog is installed; the column header is guarded to match. --}}
                                @if ($showBlogPosts)
                                    <td class="v-cell-num v-mono">{{ $tag->blog_posts_count }}</td>
                                @endif
                                <td class="v-cell-act">
                                    <a href="{{ route('admin.tags.edit', $tag) }}" class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                                    <button type="button" class="v-btn v-btn--danger v-btn--sm"
                                            @click="$dispatch('v-confirm', 'delete-tag-{{ $tag->id }}')">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="v-empty">
                                        <p class="v-empty__title">No tags yet</p>
                                        <p class="v-empty__line">{{ request('search') ? 'Try adjusting the search.' : 'Tags group products and blog posts across the catalog.' }}</p>
                                        @unless (request('search'))
                                            <a href="{{ route('admin.tags.create') }}" class="v-btn v-btn--primary v-btn--sm">Create one</a>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.panel>

        <x-admin.pager :paginator="$tags" />

        @foreach ($tags as $tag)
            <x-admin.confirm name="delete-tag-{{ $tag->id }}" title="Delete {{ $tag->name }}?"
                message="This removes the tag from all products and blog posts. This cannot be undone."
                confirm="Delete" :action="route('admin.tags.destroy', $tag)" method="DELETE" />
        @endforeach
    </div>
</x-admin.shell>
