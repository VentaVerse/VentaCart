<x-admin.shell title="SEO 404s">
    <x-admin.page title="404s">
        <div class="flex flex-col gap-6 lg:flex-row lg:gap-8">
            @include('admin.marketing.seo.partials.nav-v2')

            <div class="min-w-0 flex-1 space-y-5">
                {{-- Summary cards --}}
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <x-admin.stat label="Unique missing URLs" :value="number_format($totalLogged)" />
                    <x-admin.stat label="Total 404 hits" :value="number_format($totalHits)" />
                    <x-admin.card>
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h3 class="text-[10px] font-semibold uppercase tracking-widest text-v-muted">Maintenance</h3>
                                <p class="mt-1 text-xs text-v-secondary">Trim old entries to keep the log focused.</p>
                            </div>
                            <form method="POST" action="{{ route('admin.extensions.seo.not-found.clear') }}"
                                onsubmit="return confirm('Clear entries older than 30 days?')">
                                @csrf
                                <input type="hidden" name="older_than_days" value="30">
                                <x-admin.button type="submit" variant="secondary" size="sm">Clear &gt;30 days</x-admin.button>
                            </form>
                        </div>
                    </x-admin.card>
                </div>

                <x-admin.card title="404 Log">
                    <x-slot name="headerActions">
                        <div class="flex flex-wrap items-center gap-3">
                            <form method="GET" class="flex items-center gap-2">
                                <input type="hidden" name="sort" value="{{ $sort }}">
                                <x-admin.input type="text" name="q" :value="$search" placeholder="Filter by path" class="w-56" />
                                @if ($search !== '')
                                    <a href="{{ route('admin.extensions.seo.not-found.index') }}" class="text-xs text-v-muted hover:text-v-text">Clear</a>
                                @endif
                            </form>
                            <div class="flex items-center gap-1 text-xs">
                                <span class="text-v-muted">Sort:</span>
                                @foreach (['hits' => 'Most hits', 'recent' => 'Most recent', 'first' => 'First seen'] as $key => $label)
                                    <a href="{{ route('admin.extensions.seo.not-found.index', ['sort' => $key, 'q' => $search]) }}"
                                        class="rounded-v-md px-2 py-1 {{ $sort === $key ? 'bg-v-accent-weak font-medium text-v-accent' : 'text-v-secondary hover:bg-v-hover' }}">
                                        {{ $label }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </x-slot>

                    <x-admin.table>
                        <x-slot name="head">
                            <x-admin.table.th>Path</x-admin.table.th>
                            <x-admin.table.th align="right">Hits</x-admin.table.th>
                            <x-admin.table.th>Last hit</x-admin.table.th>
                            <x-admin.table.th>First seen</x-admin.table.th>
                            <x-admin.table.th>Last referer</x-admin.table.th>
                            <x-admin.table.th align="right">Actions</x-admin.table.th>
                        </x-slot>
                        @forelse ($logs as $log)
                            <x-admin.table.row>
                                <x-admin.table.cell>
                                    <div class="break-all font-mono text-xs text-v-text">{{ $log->path }}</div>
                                    @if ($log->last_query)
                                        <div class="mt-0.5 break-all font-mono text-[11px] text-v-muted">?{{ $log->last_query }}</div>
                                    @endif
                                </x-admin.table.cell>
                                <x-admin.table.cell align="right" mono>{{ number_format($log->hits) }}</x-admin.table.cell>
                                <x-admin.table.cell class="text-xs text-v-muted" title="{{ $log->last_hit_at }}">{{ $log->last_hit_at->diffForHumans() }}</x-admin.table.cell>
                                <x-admin.table.cell class="text-xs text-v-muted" title="{{ $log->first_hit_at }}">{{ $log->first_hit_at->diffForHumans() }}</x-admin.table.cell>
                                <x-admin.table.cell class="max-w-xs text-xs text-v-muted">
                                    <div class="truncate" title="{{ $log->last_referer }}">{{ $log->last_referer ?: '-' }}</div>
                                </x-admin.table.cell>
                                <x-admin.table.cell align="right">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('admin.extensions.seo.redirects.index', ['source_path' => $log->path]) }}"
                                            class="text-xs text-v-accent hover:opacity-80">Redirect</a>
                                        <form method="POST" action="{{ route('admin.extensions.seo.not-found.destroy', $log) }}"
                                            onsubmit="return confirm('Remove this entry from the log?')" class="inline">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs text-v-danger hover:opacity-80">Dismiss</button>
                                        </form>
                                    </div>
                                </x-admin.table.cell>
                            </x-admin.table.row>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center">
                                    <x-admin.empty-state icon="search" title="No 404s logged yet"
                                        description="Once visitors or crawlers hit missing URLs, they'll show up here." />
                                </td>
                            </tr>
                        @endforelse
                    </x-admin.table>

                    @if ($logs->hasPages())
                        <div class="pt-3">{{ $logs->links() }}</div>
                    @endif
                </x-admin.card>
            </div>
        </div>
    </x-admin.page>
</x-admin.shell>
