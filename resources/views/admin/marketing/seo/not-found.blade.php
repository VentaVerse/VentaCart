<x-admin3.seo-shell title="404s" active="not-found">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">404s</h1>
        </div>
        <div class="v3-head__actions">
            <button type="button" class="v3-btn v3-btn--secondary" @click="$dispatch('v3-confirm', 'clear-old-404s')">Clear entries over 30 days old</button>
        </div>
    </div>

    <div class="v3-settle">
        <div class="v3-stats">
            <div class="v3-stat"><div class="v3-stat__label">Unique missing URLs</div><div class="v3-stat__value">{{ number_format($totalLogged) }}</div></div>
            <div class="v3-stat"><div class="v3-stat__label">Total 404 hits</div><div class="v3-stat__value">{{ number_format($totalHits) }}</div></div>
        </div>

        <x-admin3.panel title="404 log" flush>
            <x-slot:acts>
                <form method="GET" class="v3-namerow">
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="search" name="q" value="{{ $search }}" placeholder="Filter by path" aria-label="Filter by path" class="v3-control v3-w-search">
                    @if ($search !== '')
                        <a href="{{ route('admin.extensions.seo.not-found.index') }}" class="v3-btn v3-btn--ghost v3-btn--sm">Clear</a>
                    @endif
                </form>
            </x-slot:acts>

            <div class="v3-tabs v3-tabs--sub" role="tablist" aria-label="Sort">
                @foreach (['hits' => 'Most hits', 'recent' => 'Most recent', 'first' => 'First seen'] as $key => $label)
                    <a href="{{ route('admin.extensions.seo.not-found.index', ['sort' => $key, 'q' => $search]) }}"
                       class="v3-tab" role="tab" aria-selected="{{ $sort === $key ? 'true' : 'false' }}">{{ $label }}</a>
                @endforeach
            </div>

            <div class="v3-tablewrap">
                <table class="v3-table">
                    <thead>
                        <tr>
                            <th>Path</th><th>Hits</th><th>Last hit</th><th>First seen</th><th>Last referer</th>
                            <th class="v3-cell-act"><span class="v3-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td class="v3-mono">
                                    {{ $log->path }}
                                    @if ($log->last_query)
                                        <span class="v3-cell-quiet v3-block">?{{ $log->last_query }}</span>
                                    @endif
                                </td>
                                <td class="v3-mono">{{ number_format($log->hits) }}</td>
                                <td class="v3-cell-quiet" title="{{ $log->last_hit_at }}">{{ $log->last_hit_at->diffForHumans() }}</td>
                                <td class="v3-cell-quiet" title="{{ $log->first_hit_at }}">{{ $log->first_hit_at->diffForHumans() }}</td>
                                <td class="v3-cell-quiet v3-cell-url" title="{{ $log->last_referer }}">{{ $log->last_referer ?: '-' }}</td>
                                <td class="v3-cell-act">
                                    <a href="{{ route('admin.extensions.seo.redirects.index', ['source_path' => $log->path]) }}" class="v3-btn v3-btn--ghost v3-btn--sm">Redirect</a>
                                    <button type="button" class="v3-btn v3-btn--danger v3-btn--sm" @click="$dispatch('v3-confirm', 'dismiss-404-{{ $log->id }}')">Dismiss</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="v3-empty">
                                        <p class="v3-empty__title">No 404s logged yet</p>
                                        <p class="v3-empty__line">Once visitors or crawlers hit missing URLs, they show up here.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($logs->hasPages())
                <div class="v3-pager">{{ $logs->links() }}</div>
            @endif
        </x-admin3.panel>

        <x-admin3.confirm name="clear-old-404s" title="Clear entries older than 30 days?"
            message="Every logged 404 last hit more than 30 days ago is deleted, along with its hit count. Redirects you have already created are untouched."
            confirm="Clear log" :action="route('admin.extensions.seo.not-found.clear') . '?older_than_days=30'" />

        @foreach ($logs as $log)
            <x-admin3.confirm name="dismiss-404-{{ $log->id }}" title="Remove this entry?"
                message="{{ $log->path }} is deleted from the log with its {{ number_format($log->hits) }} recorded hits. It comes back if the URL is requested again."
                confirm="Remove" :action="route('admin.extensions.seo.not-found.destroy', $log)" method="DELETE" />
        @endforeach
    </div>
</x-admin3.seo-shell>
