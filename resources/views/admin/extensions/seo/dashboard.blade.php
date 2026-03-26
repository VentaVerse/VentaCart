<x-admin-layout>
    <x-slot name="header">SEO Optimization</x-slot>

    @include('admin.extensions.seo.partials.tabs')

    {{-- Health Score Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        @foreach (['products' => 'Products', 'categories' => 'Categories', 'brands' => 'Brands', 'blog' => 'Blog'] as $key => $label)
            @php
                $data = $healthScores[$key] ?? ['withMeta' => 0, 'total' => 0, 'score' => 0];
                $score = $data['score'];
                $barColor = $score > 70 ? 'bg-green-500' : ($score >= 30 ? 'bg-yellow-500' : 'bg-red-500');
                $textColor = $score > 70 ? 'text-green-700' : ($score >= 30 ? 'text-yellow-700' : 'text-red-700');
            @endphp
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">{{ $label }}</h3>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-2xl font-bold {{ $textColor }}">{{ $score }}%</span>
                    <span class="text-sm text-gray-500">{{ $data['withMeta'] }} / {{ $data['total'] }}</span>
                </div>
                <div class="mt-3 w-full bg-gray-200 rounded-full h-2">
                    <div class="{{ $barColor }} h-2 rounded-full transition-all" style="width: {{ $score }}%"></div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Pending Suggestions Banner --}}
    @if ($pendingCount > 0)
        <div class="mb-6 flex items-center justify-between rounded-lg border border-amber-300 bg-amber-50 px-5 py-3">
            <div class="flex items-center gap-2">
                <svg class="h-5 w-5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M12 3a9 9 0 110 18 9 9 0 010-18z"/>
                </svg>
                <span class="text-sm font-medium text-amber-800">{{ $pendingCount }} pending suggestion{{ $pendingCount !== 1 ? 's' : '' }} awaiting review</span>
            </div>
            <a href="{{ route('admin.extensions.seo.review') }}" class="text-sm font-medium text-amber-700 hover:text-amber-900 underline">Review</a>
        </div>
    @endif

    {{-- Two-column Layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left: Recent Activity --}}
        <div class="lg:col-span-2 bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="px-5 py-4 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Recent Activity</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                        <tr>
                            <th class="px-5 py-3 font-medium">Entity Type</th>
                            <th class="px-5 py-3 font-medium">Entity Name</th>
                            <th class="px-5 py-3 font-medium">Status</th>
                            <th class="px-5 py-3 font-medium">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($recentSuggestions as $suggestion)
                            @php
                                $typeMap = [
                                    'App\\Models\\Product' => 'Product',
                                    'App\\Models\\Category' => 'Category',
                                    'App\\Models\\Brand' => 'Brand',
                                    'App\\Models\\BlogPost' => 'Blog Post',
                                ];
                                $entityTypeName = $typeMap[$suggestion->entity_type] ?? class_basename($suggestion->entity_type);
                                $entityName = $suggestion->entity?->name ?? $suggestion->entity?->title ?? 'Deleted';

                                $statusColors = [
                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                    'accepted' => 'bg-green-100 text-green-800',
                                    'rejected' => 'bg-red-100 text-red-800',
                                ];
                                $badgeClass = $statusColors[$suggestion->status] ?? 'bg-gray-100 text-gray-800';
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3 text-gray-700">{{ $entityTypeName }}</td>
                                <td class="px-5 py-3 text-gray-900 font-medium">{{ Str::limit($entityName, 40) }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $badgeClass }}">
                                        {{ ucfirst($suggestion->status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-gray-500">{{ $suggestion->generated_at?->format('M d, Y') ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-8 text-center text-gray-400">No suggestions generated yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Right: Quick Actions + GSC --}}
        <div class="space-y-6">
            {{-- Quick Links --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-5 py-4 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Manage SEO</h3>
                </div>
                <div class="p-5 space-y-3">
                    @foreach (['products' => 'Products', 'categories' => 'Categories', 'brands' => 'Brands', 'blog' => 'Blog', 'pages' => 'Pages'] as $type => $label)
                        <a href="{{ route('admin.extensions.seo.entities', ['type' => $type]) }}" class="w-full flex items-center justify-between px-4 py-2.5 text-sm font-medium text-gray-700 bg-gray-50 border border-gray-200 rounded-lg hover:bg-gray-100 transition-colors">
                            <span>{{ $label }}</span>
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                            </svg>
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Google Search Console --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-5 py-4 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Search Console</h3>
                </div>
                @if (!empty($topQueries))
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                                <tr>
                                    <th class="px-5 py-2 font-medium">Query</th>
                                    <th class="px-5 py-2 font-medium text-right">Impr.</th>
                                    <th class="px-5 py-2 font-medium text-right">CTR</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($topQueries as $query)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-5 py-2 text-gray-900">{{ Str::limit($query['query'] ?? '', 25) }}</td>
                                        <td class="px-5 py-2 text-gray-500 text-right">{{ number_format($query['impressions'] ?? 0) }}</td>
                                        <td class="px-5 py-2 text-gray-500 text-right">{{ number_format(($query['ctr'] ?? 0) * 100, 1) }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-5 text-center">
                        <svg class="mx-auto h-8 w-8 text-gray-300 mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                        </svg>
                        <p class="text-sm text-gray-500 mb-3">Google Search Console is not connected.</p>
                        <a href="{{ route('admin.extensions.seo.settings') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Connect in Settings &rarr;</a>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if (session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" class="fixed bottom-4 right-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg shadow-lg text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)" class="fixed bottom-4 right-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg shadow-lg text-sm">
            {{ session('error') }}
        </div>
    @endif
</x-admin-layout>
