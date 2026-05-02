<x-admin-layout>
    <x-slot name="header">SEO Optimization</x-slot>

    @include('admin.extensions.seo.partials.tabs')

    @if (session('success'))
        <div class="mb-4 rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif

    {{-- Summary cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Unique missing URLs</h3>
            <div class="mt-1 text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($totalLogged) }}</div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Total 404 hits</h3>
            <div class="mt-1 text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($totalHits) }}</div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 flex items-center justify-between gap-4">
            <div>
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Maintenance</h3>
                <p class="text-xs text-gray-500 mt-1">Trim old entries to keep the log focused.</p>
            </div>
            <form method="POST" action="{{ route('admin.extensions.seo.not-found.clear') }}"
                onsubmit="return confirm('Clear entries older than 30 days?')">
                @csrf
                <input type="hidden" name="older_than_days" value="30">
                <button type="submit" class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                    Clear &gt;30 days
                </button>
            </form>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between gap-4 flex-wrap">
            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">404 Log</h3>
            <div class="flex items-center gap-3">
                <form method="GET" class="flex items-center gap-2">
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="text" name="q" value="{{ $search }}" placeholder="Filter by path"
                        class="rounded-md border-gray-300 text-sm w-56">
                    @if ($search !== '')
                        <a href="{{ route('admin.extensions.seo.not-found.index') }}" class="text-xs text-gray-500 hover:text-gray-700">Clear</a>
                    @endif
                </form>
                <div class="flex items-center text-xs gap-1">
                    <span class="text-gray-500">Sort:</span>
                    @foreach (['hits' => 'Most hits', 'recent' => 'Most recent', 'first' => 'First seen'] as $key => $label)
                        <a href="{{ route('admin.extensions.seo.not-found.index', ['sort' => $key, 'q' => $search]) }}"
                            class="px-2 py-1 rounded {{ $sort === $key ? 'bg-indigo-100 text-indigo-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                    <tr>
                        <th class="px-5 py-3 font-medium">Path</th>
                        <th class="px-5 py-3 font-medium text-right">Hits</th>
                        <th class="px-5 py-3 font-medium">Last hit</th>
                        <th class="px-5 py-3 font-medium">First seen</th>
                        <th class="px-5 py-3 font-medium">Last referer</th>
                        <th class="px-5 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($logs as $log)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <div class="font-mono text-xs text-gray-900 break-all">{{ $log->path }}</div>
                                @if ($log->last_query)
                                    <div class="font-mono text-[11px] text-gray-400 break-all mt-0.5">?{{ $log->last_query }}</div>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right text-gray-700 tabular-nums">{{ number_format($log->hits) }}</td>
                            <td class="px-5 py-3 text-xs text-gray-500" title="{{ $log->last_hit_at }}">{{ $log->last_hit_at->diffForHumans() }}</td>
                            <td class="px-5 py-3 text-xs text-gray-500" title="{{ $log->first_hit_at }}">{{ $log->first_hit_at->diffForHumans() }}</td>
                            <td class="px-5 py-3 text-xs text-gray-500 max-w-xs">
                                <div class="truncate" title="{{ $log->last_referer }}">{{ $log->last_referer ?: '—' }}</div>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('admin.extensions.seo.redirects.index', ['source_path' => $log->path]) }}"
                                        class="text-xs text-indigo-600 hover:text-indigo-800">Redirect</a>
                                    <form method="POST" action="{{ route('admin.extensions.seo.not-found.destroy', $log) }}"
                                        onsubmit="return confirm('Remove this entry from the log?')" class="inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs text-red-600 hover:text-red-800">Dismiss</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center text-sm text-gray-500">
                                No 404s logged yet. Once visitors or crawlers hit missing URLs, they'll show up here.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div class="px-5 py-3 border-t border-gray-200">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</x-admin-layout>
