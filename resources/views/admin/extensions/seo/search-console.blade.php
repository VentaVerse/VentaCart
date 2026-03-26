<x-admin-layout>
    <x-slot name="header">SEO Optimization</x-slot>

    @include('admin.extensions.seo.partials.tabs')

    @if (! $gscConfigured)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-2">Google Search Console is not configured yet.</h3>
            <p class="text-gray-600">
                <a href="{{ route('admin.extensions.seo.settings') }}" class="text-indigo-600 hover:text-indigo-800 underline">Go to Settings</a>
                to upload credentials and set your site URL.
            </p>
        </div>
    @else
        {{-- Last updated + Refresh --}}
        <div class="flex items-center justify-between mb-4">
            <p class="text-xs text-gray-400">
                @if ($fetchedAt)
                    Last fetched: {{ $fetchedAt->diffForHumans() }} ({{ $fetchedAt->format('M d, Y g:i A') }})
                @else
                    Not fetched yet.
                @endif
                <span class="text-gray-300 mx-1">|</span>
                Data from Google has a 2-3 day delay.
            </p>
            <a href="{{ route('admin.extensions.seo.search-console', ['refresh' => 1]) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 text-gray-700 text-xs font-medium rounded-md hover:bg-gray-200 border border-gray-300 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182"/></svg>
                Refresh
            </a>
        </div>

        {{-- Top Queries --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Top Queries (Last 28 Days)</h3>
            </div>

            @if (count($queries) > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Query</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Clicks</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Impressions</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">CTR</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Position</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($queries as $row)
                                @php
                                    $ctrVal = $row['ctr'] * 100;
                                    $ctrClass = $ctrVal < 2 ? 'text-red-600' : ($ctrVal <= 5 ? 'text-amber-600' : 'text-green-600');
                                    $posClass = $row['position'] < 10 ? 'text-green-600' : ($row['position'] <= 20 ? 'text-amber-600' : 'text-red-600');
                                @endphp
                                <tr>
                                    <td class="px-6 py-4 text-sm text-gray-900">{{ $row['query'] }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900 text-right">{{ number_format($row['clicks']) }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900 text-right">{{ number_format($row['impressions']) }}</td>
                                    <td class="px-6 py-4 text-sm font-medium text-right {{ $ctrClass }}">{{ number_format($ctrVal, 1) }}%</td>
                                    <td class="px-6 py-4 text-sm font-medium text-right {{ $posClass }}">{{ number_format($row['position'], 1) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="px-6 py-4">
                    <p class="text-sm text-gray-500">No query data available.</p>
                </div>
            @endif
        </div>

        {{-- Top Pages --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Top Pages (Last 28 Days)</h3>
            </div>

            @if (count($pages) > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Page URL</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Clicks</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Impressions</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">CTR</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Position</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($pages as $row)
                                @php
                                    $ctrVal = $row['ctr'] * 100;
                                    $ctrClass = $ctrVal < 2 ? 'text-red-600' : ($ctrVal <= 5 ? 'text-amber-600' : 'text-green-600');
                                    $posClass = $row['position'] < 10 ? 'text-green-600' : ($row['position'] <= 20 ? 'text-amber-600' : 'text-red-600');
                                @endphp
                                <tr>
                                    <td class="px-6 py-4 text-sm text-gray-900 max-w-xs truncate" title="{{ $row['page'] }}">{{ Str::limit($row['page'], 60) }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900 text-right">{{ number_format($row['clicks']) }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900 text-right">{{ number_format($row['impressions']) }}</td>
                                    <td class="px-6 py-4 text-sm font-medium text-right {{ $ctrClass }}">{{ number_format($ctrVal, 1) }}%</td>
                                    <td class="px-6 py-4 text-sm font-medium text-right {{ $posClass }}">{{ number_format($row['position'], 1) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="px-6 py-4">
                    <p class="text-sm text-gray-500">No page data available.</p>
                </div>
            @endif
        </div>
    @endif
</x-admin-layout>
