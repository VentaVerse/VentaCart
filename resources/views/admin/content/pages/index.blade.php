<x-admin3.shell title="Pages" active="content">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Pages</h1>
        </div>
        <div class="v3-head__actions">
            <a href="{{ route('admin.pages.create') }}" class="v3-btn v3-btn--primary">New page</a>
        </div>
    </div>

    <div class="v3-settle">
        <form method="GET" action="{{ route('admin.pages.index') }}" class="v3-filterbar">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Title or slug…"
                   aria-label="Search pages" class="v3-control v3-filterbar__grow">
            <select name="status" aria-label="Filter by status" class="v3-control v3-w-name">
                <option value="">All statuses</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
            <button type="submit" class="v3-btn v3-btn--secondary">Filter</button>
            @if (request()->anyFilled(['search', 'status']))
                <a href="{{ route('admin.pages.index') }}" class="v3-link">Clear</a>
            @endif
        </form>

        <x-admin3.panel flush>
            <div class="v3-tablewrap">
                <table class="v3-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>URL</th>
                            <th class="v3-cell-num">Order</th>
                            <th>Status</th>
                            <th class="v3-cell-act"><span class="v3-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pages as $page)
                            <tr>
                                <td><a href="{{ route('admin.pages.edit', $page) }}" class="v3-rowlink">{{ $page->title }}</a></td>
                                <td><a href="/{{ $page->slug }}" target="_blank" class="v3-link v3-mono">/{{ $page->slug }}</a></td>
                                <td class="v3-cell-num v3-mono">{{ $page->sort_order }}</td>
                                <td>
                                    <span class="v3-badge v3-badge--{{ $page->is_active ? 'good' : 'quiet' }}">
                                        {{ $page->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="v3-cell-act">
                                    <a href="{{ route('admin.pages.edit', $page) }}" class="v3-btn v3-btn--ghost v3-btn--sm">Edit</a>
                                    <button type="button" class="v3-btn v3-btn--danger v3-btn--sm"
                                            @click="$dispatch('v3-confirm', 'delete-page-{{ $page->id }}')">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="v3-empty">
                                        <p class="v3-empty__title">No pages yet</p>
                                        <p class="v3-empty__line">{{ request()->anyFilled(['search', 'status']) ? 'Try adjusting the filters.' : 'Static storefront pages (about, terms, delivery) live here.' }}</p>
                                        @unless (request()->anyFilled(['search', 'status']))
                                            <a href="{{ route('admin.pages.create') }}" class="v3-btn v3-btn--primary v3-btn--sm">Create your first page</a>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin3.panel>

        <x-admin3.pager :paginator="$pages" />

        @foreach ($pages as $page)
            <x-admin3.confirm name="delete-page-{{ $page->id }}" title="Delete {{ $page->title }}?"
                message="This cannot be undone." confirm="Delete" :action="route('admin.pages.destroy', $page)" method="DELETE" />
        @endforeach
    </div>
</x-admin3.shell>
