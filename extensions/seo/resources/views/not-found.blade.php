<x-admin.seo-shell title="404s" active="not-found">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">404s</h1>
        </div>
        <div class="v-head__actions">
            <button type="button" class="v-btn v-btn--secondary" @click="$dispatch('v-confirm', 'clear-old-404s')">Clear entries over 30 days old</button>
        </div>
    </div>

    <div class="v-settle">
        <div class="v-stats">
            <div class="v-stat"><div class="v-stat__label">Unique missing URLs</div><div class="v-stat__value">{{ number_format($totalLogged) }}</div></div>
            <div class="v-stat"><div class="v-stat__label">Total 404 hits</div><div class="v-stat__value">{{ number_format($totalHits) }}</div></div>
        </div>

        <x-admin.panel title="404 log" flush>
            <x-slot:acts>
                <form method="GET" class="v-namerow">
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="search" name="q" value="{{ $search }}" placeholder="Filter by path" aria-label="Filter by path" class="v-control v-w-search">
                    @if ($search !== '')
                        <a href="{{ route('admin.extensions.seo.not-found.index') }}" class="v-btn v-btn--ghost v-btn--sm">Clear</a>
                    @endif
                </form>
            </x-slot:acts>

            <div class="v-tabs v-tabs--sub" role="tablist" aria-label="Sort">
                @foreach (['hits' => 'Most hits', 'recent' => 'Most recent', 'first' => 'First seen'] as $key => $label)
                    <a href="{{ route('admin.extensions.seo.not-found.index', ['sort' => $key, 'q' => $search]) }}"
                       class="v-tab" role="tab" aria-selected="{{ $sort === $key ? 'true' : 'false' }}">{{ $label }}</a>
                @endforeach
            </div>

            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr>
                            <th>Path</th><th>Hits</th><th>Last hit</th><th>First seen</th><th>Last referer</th>
                            <th class="v-cell-act"><span class="v-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td class="v-mono">
                                    {{ $log->path }}
                                    @if ($log->last_query)
                                        <span class="v-cell-quiet v-block">?{{ $log->last_query }}</span>
                                    @endif
                                </td>
                                <td class="v-mono">{{ number_format($log->hits) }}</td>
                                <td class="v-cell-quiet" title="{{ $log->last_hit_at }}">{{ $log->last_hit_at->diffForHumans() }}</td>
                                <td class="v-cell-quiet" title="{{ $log->first_hit_at }}">{{ $log->first_hit_at->diffForHumans() }}</td>
                                <td class="v-cell-quiet v-cell-url" title="{{ $log->last_referer }}">{{ $log->last_referer ?: '-' }}</td>
                                <td class="v-cell-act">
                                    <a href="{{ route('admin.extensions.seo.redirects.index', ['source_path' => $log->path]) }}" class="v-btn v-btn--ghost v-btn--sm">Redirect</a>
                                    <button type="button" class="v-btn v-btn--danger v-btn--sm" @click="$dispatch('v-confirm', 'dismiss-404-{{ $log->id }}')">Dismiss</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="v-empty">
                                        <p class="v-empty__title">No 404s logged yet</p>
                                        <p class="v-empty__line">Once visitors or crawlers hit missing URLs, they show up here.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($logs->hasPages())
                <x-admin.pager :paginator="$logs" />
            @endif
        </x-admin.panel>

        <x-admin.confirm name="clear-old-404s" title="Clear entries older than 30 days?"
            message="Every logged 404 last hit more than 30 days ago is deleted, along with its hit count. Redirects you have already created are untouched."
            confirm="Clear log" :action="route('admin.extensions.seo.not-found.clear') . '?older_than_days=30'" />

        @foreach ($logs as $log)
            <x-admin.confirm name="dismiss-404-{{ $log->id }}" title="Remove this entry?"
                message="{{ $log->path }} is deleted from the log with its {{ number_format($log->hits) }} recorded hits. It comes back if the URL is requested again."
                confirm="Remove" :action="route('admin.extensions.seo.not-found.destroy', $log)" method="DELETE" />
        @endforeach
    </div>
</x-admin.seo-shell>
