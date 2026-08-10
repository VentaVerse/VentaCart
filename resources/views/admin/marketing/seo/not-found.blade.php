<x-admin.seo-shell title="SEO 404s" heading="404s">
    <div class="space-y-5">
        <div class="v-stats v-stats--few">
            <x-admin.figure label="Unique missing URLs" :value="number_format($totalLogged)" accent="rose" icon="search" />
            <x-admin.figure label="Total 404 hits" :value="number_format($totalHits)" accent="amber" icon="chart" />
        </div>

        <x-admin.panel title="404 log" flush>
            <x-slot:head>
                <div class="ml-auto flex flex-wrap items-center gap-3">
                    <form method="GET" class="flex items-center gap-2">
                        <input type="hidden" name="sort" value="{{ $sort }}">
                        <x-admin.input type="search" name="q" :value="$search" placeholder="Filter by path" class="w-56" aria-label="Filter by path" />
                        @if ($search !== '')
                            <a href="{{ route('admin.extensions.seo.not-found.index') }}" class="v-filters__reset">Clear</a>
                        @endif
                    </form>
                    <x-admin.button type="button" variant="secondary" size="sm"
                        @click="$dispatch('open-modal', 'clear-old-404s')">Clear entries over 30 days old</x-admin.button>
                </div>
            </x-slot:head>

            <x-admin.segment sub label="Sort">
                @foreach (['hits' => 'Most hits', 'recent' => 'Most recent', 'first' => 'First seen'] as $key => $label)
                    <x-admin.segment.item :href="route('admin.extensions.seo.not-found.index', ['sort' => $key, 'q' => $search])" :active="$sort === $key">{{ $label }}</x-admin.segment.item>
                @endforeach
            </x-admin.segment>

            <x-admin.table bare>
                <x-slot:head>
                    <x-admin.table.th>Path</x-admin.table.th>
                    <x-admin.table.th align="right">Hits</x-admin.table.th>
                    <x-admin.table.th>Last hit</x-admin.table.th>
                    <x-admin.table.th>First seen</x-admin.table.th>
                    <x-admin.table.th>Last referer</x-admin.table.th>
                    <x-admin.table.th class="v-col-act">Actions</x-admin.table.th>
                </x-slot:head>
                @forelse ($logs as $log)
                    <x-admin.table.row>
                        <x-admin.table.cell>
                            <div class="v-num break-all text-xs text-v-text">{{ $log->path }}</div>
                            @if ($log->last_query)
                                <div class="v-num mt-0.5 break-all text-[11px] text-v-muted">?{{ $log->last_query }}</div>
                            @endif
                        </x-admin.table.cell>
                        <x-admin.table.cell align="right" mono>{{ number_format($log->hits) }}</x-admin.table.cell>
                        <x-admin.table.cell class="text-xs text-v-muted" title="{{ $log->last_hit_at }}">{{ $log->last_hit_at->diffForHumans() }}</x-admin.table.cell>
                        <x-admin.table.cell class="text-xs text-v-muted" title="{{ $log->first_hit_at }}">{{ $log->first_hit_at->diffForHumans() }}</x-admin.table.cell>
                        <x-admin.table.cell class="max-w-xs text-xs text-v-muted">
                            <div class="truncate" title="{{ $log->last_referer }}">{{ $log->last_referer ?: '-' }}</div>
                        </x-admin.table.cell>
                        <x-admin.table.cell class="v-col-act">
                            <div class="v-rowactions v-rowactions--end">
                                <x-admin.button size="sm" variant="ghost" :href="route('admin.extensions.seo.redirects.index', ['source_path' => $log->path])">Redirect</x-admin.button>
                                <x-admin.button size="sm" variant="ghost" class="v-rowactions__danger" type="button"
                                    @click="$dispatch('open-modal', 'dismiss-404-{{ $log->id }}')">Dismiss</x-admin.button>
                            </div>
                        </x-admin.table.cell>
                    </x-admin.table.row>
                @empty
                    <tr>
                        <td colspan="6">
                            <x-admin.empty-state title="No 404s logged yet"
                                description="Once visitors or crawlers hit missing URLs, they show up here." />
                        </td>
                    </tr>
                @endforelse
                @if ($logs->hasPages())
                    <x-slot:footer>{{ $logs->links() }}</x-slot:footer>
                @endif
            </x-admin.table>
        </x-admin.panel>
    </div>

    <x-admin.confirm-dialog name="clear-old-404s" title="Clear entries older than 30 days?"
        message="Every logged 404 last hit more than 30 days ago is deleted, along with its hit count. Redirects you have already created are untouched."
        confirm="Clear log" :action="route('admin.extensions.seo.not-found.clear')">
        <x-slot name="fields">
            <input type="hidden" name="older_than_days" value="30">
        </x-slot>
    </x-admin.confirm-dialog>

    @foreach ($logs as $log)
        <x-admin.confirm-dialog name="dismiss-404-{{ $log->id }}" title="Remove this entry?"
            message="{{ $log->path }} is deleted from the log with its {{ number_format($log->hits) }} recorded hits. It comes back if the URL is requested again."
            confirm="Remove" :action="route('admin.extensions.seo.not-found.destroy', $log)" method="DELETE" />
    @endforeach
</x-admin.seo-shell>
