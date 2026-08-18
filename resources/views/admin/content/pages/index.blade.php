<x-admin.shell title="Pages" active="content">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">Pages</h1>
        </div>
        <div class="v-head__actions">
            <a href="{{ route('admin.pages.create') }}" class="v-btn v-btn--primary">New page</a>
        </div>
    </div>

    <div class="v-settle">
        <form method="GET" action="{{ route('admin.pages.index') }}" class="v-filterbar">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Title or slug…"
                   aria-label="Search pages" class="v-control v-filterbar__grow">
            <select name="status" aria-label="Filter by status" class="v-control v-w-name">
                <option value="">All statuses</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
            <button type="submit" class="v-btn v-btn--secondary">Filter</button>
            @if (request()->anyFilled(['search', 'status']))
                <a href="{{ route('admin.pages.index') }}" class="v-link">Clear</a>
            @endif
        </form>

        <x-admin.panel flush>
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>URL</th>
                            <th class="v-cell-num">Order</th>
                            <th>Status</th>
                            <th class="v-cell-act"><span class="v-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pages as $page)
                            <tr>
                                <td><a href="{{ route('admin.pages.edit', $page) }}" class="v-rowlink">{{ $page->title }}</a></td>
                                <td><a href="/{{ $page->slug }}" target="_blank" class="v-link v-mono">/{{ $page->slug }}</a></td>
                                <td class="v-cell-num v-mono">{{ $page->sort_order }}</td>
                                <td>
                                    <span class="v-badge v-badge--{{ $page->is_active ? 'good' : 'quiet' }}">
                                        {{ $page->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="v-cell-act">
                                    <a href="{{ route('admin.pages.edit', $page) }}" class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                                    <button type="button" class="v-btn v-btn--danger v-btn--sm"
                                            @click="$dispatch('v-confirm', 'delete-page-{{ $page->id }}')">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="v-empty">
                                        <p class="v-empty__title">No pages yet</p>
                                        <p class="v-empty__line">{{ request()->anyFilled(['search', 'status']) ? 'Try adjusting the filters.' : 'Static storefront pages (about, terms, delivery) live here.' }}</p>
                                        @unless (request()->anyFilled(['search', 'status']))
                                            <a href="{{ route('admin.pages.create') }}" class="v-btn v-btn--primary v-btn--sm">Create your first page</a>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.panel>

        <x-admin.pager :paginator="$pages" />

        @foreach ($pages as $page)
            <x-admin.confirm name="delete-page-{{ $page->id }}" title="Delete {{ $page->title }}?"
                message="This cannot be undone." confirm="Delete" :action="route('admin.pages.destroy', $page)" method="DELETE" />
        @endforeach
    </div>
</x-admin.shell>
