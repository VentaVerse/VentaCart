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
