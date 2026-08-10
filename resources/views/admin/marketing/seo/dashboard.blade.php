@php
    $totalEntities = collect($healthScores)->sum('total');
    $totalWithMeta = collect($healthScores)->sum('withMeta');
    $overallScore = $totalEntities > 0 ? round(($totalWithMeta / $totalEntities) * 100, 1) : 0;

    $entityLabels = [
        'App\\Models\\Product' => 'Product',
        'App\\Models\\Category' => 'Category',
        'App\\Models\\Brand' => 'Brand',
        'App\\Models\\BlogPost' => 'Blog Post',
        'App\\Models\\Page' => 'Page',
    ];

    $statusTones = ['pending' => 'warning', 'accepted' => 'success', 'rejected' => 'danger'];
@endphp
<x-admin.seo-shell title="SEO">
    <div class="space-y-5">
        {{-- Four measures, four identities. The accent says which
             figure you are reading, never how the news is: a health
             score keeps its hue at 4% and at 94%, and the number is
             what tells you which one you are looking at. --}}
        <div class="v-stats">
            <x-admin.figure label="Health score" :value="$overallScore . '%'" accent="cyan" icon="seo"
                :note="$totalWithMeta . ' of ' . $totalEntities . ' carry meta'" />
            <x-admin.figure label="Waiting for review" :value="$pendingCount" accent="amber" icon="check"
                :href="route('admin.extensions.seo.review')" />
            <x-admin.figure label="Redirects live" :value="$redirectsLive" accent="violet" icon="external"
                :href="route('admin.extensions.seo.redirects.index')" />
            <x-admin.figure label="404s this month" :value="$notFoundThisMonth" accent="rose" icon="search"
                :href="route('admin.extensions.seo.not-found.index')" />
        </div>

        <x-admin.panel title="Automation" :link="route('admin.settings.section', 'automations')" link-label="Open Automations" flush>
            @if ($seoAutomation)
                <dl class="v-facts">
                    <dt class="v-label">Status</dt>
                    <dd>
                        <x-admin.badge :tone="$seoAutomation->enabled ? 'success' : 'neutral'">
                            {{ $seoAutomation->enabled ? 'Enabled' : 'Disabled' }}
                        </x-admin.badge>
                    </dd>
                    <dt class="v-label">Schedule</dt>
                    <dd class="text-[13px] text-v-text">{{ $seoAutomation->schedule_description }}</dd>
                    <dt class="v-label">Last run</dt>
                    <dd class="flex flex-wrap items-center justify-end gap-2 text-[13px] text-v-text">
                        @if ($seoAutomation->last_run_at)
                            {{ $seoAutomation->last_run_at->diffForHumans() }}
                            <x-admin.badge :tone="$statusTones[$seoAutomation->last_run_status] ?? 'neutral'">
                                {{ ucfirst($seoAutomation->last_run_status ?? 'unknown') }}
                            </x-admin.badge>
                        @else
                            <span class="text-v-muted">Never run</span>
                        @endif
                    </dd>
                </dl>
            @else
                <x-admin.empty-state title="Automation not configured"
                    description="The seo_generate automation row has not been seeded yet." />
            @endif
        </x-admin.panel>

        <x-admin.panel title="Recent suggestions" :link="route('admin.extensions.seo.review')" link-label="Review all" flush>
            @if ($recentSuggestions->isEmpty())
                <x-admin.empty-state title="No suggestions yet"
                    description="Run generation from an entity list, or wait for the next scheduled run." />
            @else
                <x-admin.table bare>
                    <x-slot:head>
                        <x-admin.table.th>Entity</x-admin.table.th>
                        <x-admin.table.th>Name</x-admin.table.th>
                        <x-admin.table.th>Status</x-admin.table.th>
                        <x-admin.table.th align="right">Generated</x-admin.table.th>
                    </x-slot:head>
                    @foreach ($recentSuggestions as $suggestion)
                        <x-admin.table.row>
                            <x-admin.table.cell>
                                <x-admin.badge tone="info">{{ $entityLabels[$suggestion->entity_type] ?? class_basename($suggestion->entity_type) }}</x-admin.badge>
                            </x-admin.table.cell>
                            <x-admin.table.cell class="text-v-text">
                                {{ Str::limit($suggestion->entity?->name ?? $suggestion->entity?->title ?? 'Deleted', 40) }}
                            </x-admin.table.cell>
                            <x-admin.table.cell>
                                <x-admin.badge :tone="$statusTones[$suggestion->status] ?? 'neutral'">{{ ucfirst($suggestion->status) }}</x-admin.badge>
                            </x-admin.table.cell>
                            <x-admin.table.cell align="right" class="text-v-muted">
                                {{ $suggestion->generated_at?->format('M d, Y') ?? '-' }}
                            </x-admin.table.cell>
                        </x-admin.table.row>
                    @endforeach
                </x-admin.table>
            @endif
        </x-admin.panel>

        <x-admin.panel title="Search Console, last 28 days" flush>
            @if (!empty($topQueries))
                <x-admin.table bare>
                    <x-slot:head>
                        <x-admin.table.th>Query</x-admin.table.th>
                        <x-admin.table.th align="right">Impressions</x-admin.table.th>
                        <x-admin.table.th align="right">CTR</x-admin.table.th>
                    </x-slot:head>
                    @foreach ($topQueries as $query)
                        <x-admin.table.row>
                            <x-admin.table.cell class="text-v-text">{{ Str::limit($query['query'] ?? '', 40) }}</x-admin.table.cell>
                            <x-admin.table.cell align="right" mono>{{ number_format($query['impressions'] ?? 0) }}</x-admin.table.cell>
                            <x-admin.table.cell align="right" mono>{{ number_format(($query['ctr'] ?? 0) * 100, 1) }}%</x-admin.table.cell>
                        </x-admin.table.row>
                    @endforeach
                </x-admin.table>
            @else
                <x-admin.empty-state title="Search Console not connected"
                    description="Connect Google Search Console to see real query performance here.">
                    <x-admin.button variant="secondary" size="sm" :href="route('admin.extensions.seo.settings')">Connect in settings</x-admin.button>
                </x-admin.empty-state>
            @endif
        </x-admin.panel>
    </div>
</x-admin.seo-shell>
