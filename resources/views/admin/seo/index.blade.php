<x-admin-layout>
    <x-slot name="header">SEO</x-slot>

    <x-slot name="headerActions">
        <form method="POST" action="{{ route('admin.seo.generate') }}" class="inline">
            @csrf
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700"
                onclick="return confirm('This will call the Claude API and may take a moment. Continue?')">
                Generate Now
            </button>
        </form>
    </x-slot>

    {{-- Health Score Banner --}}
    <div class="mb-6 grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Health Score</p>
            <p class="mt-1 text-2xl font-bold {{ $healthScore >= 80 ? 'text-green-600' : ($healthScore >= 50 ? 'text-amber-600' : 'text-red-600') }}">{{ $healthScore }}%</p>
            <p class="text-xs text-gray-400 mt-1">{{ $withMeta }}/{{ $totalProducts }} products with meta</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Pending Review</p>
            <p class="mt-1 text-2xl font-bold text-amber-600">{{ $pendingCount }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Accepted</p>
            <p class="mt-1 text-2xl font-bold text-green-600">{{ $acceptedCount }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Rejected</p>
            <p class="mt-1 text-2xl font-bold text-gray-500">{{ $rejectedCount }}</p>
        </div>
    </div>

    <div x-data="{ activeTab: '{{ $tab }}' }">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <nav class="flex border-b border-gray-200">
                <button type="button" @click="activeTab = 'suggestions'"
                    :class="activeTab === 'suggestions' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap px-5 py-3 border-b-2 text-sm font-medium transition-colors">
                    Suggestions
                    @if ($pendingCount > 0)
                        <span class="ml-1 inline-flex items-center justify-center px-2 py-0.5 text-xs font-bold rounded-full bg-amber-100 text-amber-700">{{ $pendingCount }}</span>
                    @endif
                </button>
                <button type="button" @click="activeTab = 'reports'"
                    :class="activeTab === 'reports' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap px-5 py-3 border-b-2 text-sm font-medium transition-colors">Reports</button>
                <button type="button" @click="activeTab = 'search-console'"
                    :class="activeTab === 'search-console' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap px-5 py-3 border-b-2 text-sm font-medium transition-colors">Search Console</button>
            </nav>

            {{-- ==================== SUGGESTIONS ==================== --}}
            <div x-show="activeTab === 'suggestions'" class="p-6">
                {{-- Status Filter --}}
                <div class="flex items-center gap-2 mb-4">
                    <span class="text-sm text-gray-500">Filter:</span>
                    @foreach (['pending', 'accepted', 'rejected'] as $s)
                        <a href="{{ route('admin.seo.index', ['tab' => 'suggestions', 'status' => $s]) }}"
                            class="px-3 py-1 text-sm rounded-full border transition-colors {{ $status === $s ? 'bg-indigo-50 border-indigo-300 text-indigo-700 font-medium' : 'border-gray-200 text-gray-500 hover:bg-gray-50' }}">
                            {{ ucfirst($s) }}
                        </a>
                    @endforeach
                </div>

                @if ($suggestions->isEmpty())
                    <p class="text-sm text-gray-400 py-8 text-center">No {{ $status }} suggestions found.</p>
                @else
                    <form method="POST" action="{{ route('admin.seo.bulk-accept') }}" id="bulk-form">
                        @csrf
                        @if ($status === 'pending')
                            <div class="mb-3 flex items-center gap-3">
                                <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                                    <input type="checkbox" id="select-all" class="rounded border-gray-300 text-indigo-600">
                                    Select All
                                </label>
                                <button type="submit" class="px-3 py-1.5 bg-green-600 text-white text-sm rounded hover:bg-green-700"
                                    onclick="return confirm('Accept all selected suggestions?')">
                                    Bulk Accept
                                </button>
                            </div>
                        @endif

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        @if ($status === 'pending')
                                            <th class="px-3 py-3 w-8"></th>
                                        @endif
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Current Title</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Suggested Title</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                        @if ($status === 'pending')
                                            <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach ($suggestions as $suggestion)
                                        <tr x-data="{ expanded: false }">
                                            @if ($status === 'pending')
                                                <td class="px-3 py-3 align-top">
                                                    <input type="checkbox" name="ids[]" value="{{ $suggestion->id }}" class="bulk-check rounded border-gray-300 text-indigo-600">
                                                </td>
                                            @endif
                                            <td class="px-3 py-3 align-top">
                                                <button type="button" @click="expanded = !expanded" class="text-left text-indigo-600 hover:text-indigo-800 font-medium">
                                                    {{ $suggestion->product?->name ?? 'Deleted Product' }}
                                                </button>
                                            </td>
                                            <td class="px-3 py-3 align-top text-gray-500 max-w-[200px] truncate">{{ $suggestion->current_meta_title ?: '(empty)' }}</td>
                                            <td class="px-3 py-3 align-top text-gray-800 max-w-[200px] truncate">{{ $suggestion->suggested_meta_title ?: '(none)' }}</td>
                                            <td class="px-3 py-3 align-top text-gray-400 whitespace-nowrap">{{ $suggestion->generated_at->format('M j, Y') }}</td>
                                            @if ($status === 'pending')
                                                <td class="px-3 py-3 align-top text-right whitespace-nowrap">
                                                    <form method="POST" action="{{ route('admin.seo.accept', $suggestion) }}" class="inline">
                                                        @csrf
                                                        <button type="submit" class="text-green-600 hover:text-green-800 font-medium mr-2">Accept</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('admin.seo.reject', $suggestion) }}" class="inline">
                                                        @csrf
                                                        <button type="submit" class="text-red-500 hover:text-red-700 font-medium">Reject</button>
                                                    </form>
                                                </td>
                                            @endif
                                        </tr>
                                        {{-- Expanded SERP Preview --}}
                                        <tr x-show="expanded" x-cloak>
                                            <td colspan="{{ $status === 'pending' ? 6 : 4 }}" class="px-3 py-4 bg-gray-50">
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                    {{-- Current --}}
                                                    <div>
                                                        <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">Current</h4>
                                                        <div class="bg-white rounded-lg border border-gray-200 p-4">
                                                            <p class="text-sm font-medium text-blue-700 truncate">{{ $suggestion->current_meta_title ?: '(no title)' }}</p>
                                                            <p class="text-xs text-green-700 truncate mt-0.5">{{ url('/product/' . ($suggestion->product?->slug ?? '')) }}</p>
                                                            <p class="text-xs text-gray-600 mt-1 line-clamp-2">{{ $suggestion->current_meta_description ?: '(no description)' }}</p>
                                                        </div>
                                                        <p class="text-xs text-gray-400 mt-2"><span class="font-medium">Keywords:</span> {{ $suggestion->current_meta_keywords ?: '(none)' }}</p>
                                                    </div>
                                                    {{-- Suggested --}}
                                                    <div>
                                                        <h4 class="text-xs font-semibold text-indigo-600 uppercase mb-2">Suggested</h4>
                                                        <div class="bg-white rounded-lg border border-indigo-200 p-4">
                                                            <p class="text-sm font-medium text-blue-700 truncate">{{ $suggestion->suggested_meta_title ?: '(no title)' }}</p>
                                                            <p class="text-xs text-green-700 truncate mt-0.5">{{ url('/product/' . ($suggestion->product?->slug ?? '')) }}</p>
                                                            <p class="text-xs text-gray-600 mt-1 line-clamp-2">{{ $suggestion->suggested_meta_description ?: '(no description)' }}</p>
                                                        </div>
                                                        <p class="text-xs text-gray-400 mt-2"><span class="font-medium">Keywords:</span> {{ $suggestion->suggested_meta_keywords ?: '(none)' }}</p>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4">
                            {{ $suggestions->links() }}
                        </div>
                    </form>
                @endif
            </div>

            {{-- ==================== REPORTS ==================== --}}
            <div x-show="activeTab === 'reports'" x-cloak class="p-6">
                @if ($reports->isEmpty())
                    <p class="text-sm text-gray-400 py-8 text-center">No reports generated yet. Click "Generate Now" to create the first one.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Products Analyzed</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Suggestions</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Auto-Accepted</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pending</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Health Score</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach ($reports as $report)
                                    <tr x-data="{ showOps: false }">
                                        <td class="px-4 py-3 text-gray-700 whitespace-nowrap">
                                            <button type="button" @click="showOps = !showOps" class="text-indigo-600 hover:text-indigo-800">
                                                {{ $report->generated_at->format('M j, Y g:ia') }}
                                            </button>
                                        </td>
                                        <td class="px-4 py-3 text-gray-600">{{ $report->total_products_analyzed }}</td>
                                        <td class="px-4 py-3 text-gray-600">{{ $report->suggestions_generated }}</td>
                                        <td class="px-4 py-3 text-gray-600">{{ $report->auto_accepted }}</td>
                                        <td class="px-4 py-3 text-gray-600">{{ $report->pending_review }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $report->health_score >= 80 ? 'bg-green-100 text-green-800' : ($report->health_score >= 50 ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800') }}">
                                                {{ $report->health_score }}%
                                            </span>
                                        </td>
                                    </tr>
                                    <tr x-show="showOps" x-cloak>
                                        <td colspan="6" class="px-4 py-3 bg-gray-50">
                                            @if (!empty($report->top_opportunities))
                                                <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">Top Opportunities (missing meta)</h4>
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                    @foreach ($report->top_opportunities as $op)
                                                        <div class="text-sm text-gray-600">
                                                            <a href="{{ route('admin.products.edit', $op['id']) }}" class="text-indigo-600 hover:underline">{{ $op['name'] }}</a>
                                                            <span class="text-gray-400 text-xs ml-1">({{ $op['views'] ?? 0 }} views)</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <p class="text-sm text-gray-400">No top opportunities data.</p>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $reports->links() }}
                    </div>
                @endif
            </div>

            {{-- ==================== SEARCH CONSOLE ==================== --}}
            <div x-show="activeTab === 'search-console'" x-cloak class="p-6">
                @if (!$gscConfigured)
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <h3 class="mt-3 text-sm font-medium text-gray-900">Google Search Console Not Configured</h3>
                        <p class="mt-1 text-sm text-gray-500">Upload your service account credentials and set your site URL in <a href="{{ route('admin.settings.index') }}?tab=seo" class="text-indigo-600 hover:underline">Settings &rarr; SEO</a>.</p>
                    </div>
                @else
                    <div class="space-y-8">
                        {{-- Top Queries --}}
                        <div>
                            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">Top Queries (Last 28 Days)</h3>
                            @if (!empty($gscData['queries']))
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Query</th>
                                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Clicks</th>
                                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Impressions</th>
                                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">CTR %</th>
                                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Avg Position</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            @foreach ($gscData['queries'] as $row)
                                                <tr>
                                                    <td class="px-4 py-2.5 text-gray-800 font-medium">{{ $row['query'] }}</td>
                                                    <td class="px-4 py-2.5 text-right text-gray-600">{{ number_format($row['clicks']) }}</td>
                                                    <td class="px-4 py-2.5 text-right text-gray-600">{{ number_format($row['impressions']) }}</td>
                                                    <td class="px-4 py-2.5 text-right {{ $row['ctr'] >= 5 ? 'text-green-600' : ($row['ctr'] >= 2 ? 'text-amber-600' : 'text-red-500') }}">{{ $row['ctr'] }}%</td>
                                                    <td class="px-4 py-2.5 text-right {{ $row['position'] <= 10 ? 'text-green-600' : ($row['position'] <= 20 ? 'text-amber-600' : 'text-gray-500') }}">{{ $row['position'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-sm text-gray-400">No query data available.</p>
                            @endif
                        </div>

                        {{-- Top Pages --}}
                        <div>
                            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">Top Pages (Last 28 Days)</h3>
                            @if (!empty($gscData['pages']))
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Page URL</th>
                                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Clicks</th>
                                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Impressions</th>
                                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">CTR %</th>
                                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Avg Position</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            @foreach ($gscData['pages'] as $row)
                                                <tr>
                                                    <td class="px-4 py-2.5 text-gray-800 max-w-xs truncate" title="{{ $row['page'] }}">{{ str_replace(config('app.url'), '', $row['page']) ?: $row['page'] }}</td>
                                                    <td class="px-4 py-2.5 text-right text-gray-600">{{ number_format($row['clicks']) }}</td>
                                                    <td class="px-4 py-2.5 text-right text-gray-600">{{ number_format($row['impressions']) }}</td>
                                                    <td class="px-4 py-2.5 text-right {{ $row['ctr'] >= 5 ? 'text-green-600' : ($row['ctr'] >= 2 ? 'text-amber-600' : 'text-red-500') }}">{{ $row['ctr'] }}%</td>
                                                    <td class="px-4 py-2.5 text-right {{ $row['position'] <= 10 ? 'text-green-600' : ($row['position'] <= 20 ? 'text-amber-600' : 'text-gray-500') }}">{{ $row['position'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-sm text-gray-400">No page data available.</p>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAll = document.getElementById('select-all');
            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    document.querySelectorAll('.bulk-check').forEach(function(cb) {
                        cb.checked = selectAll.checked;
                    });
                });
            }
        });
    </script>
    @endpush
</x-admin-layout>
