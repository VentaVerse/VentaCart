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

    $statusTones = ['pending' => 'v-badge--warn', 'accepted' => 'v-badge--good', 'rejected' => 'v-badge--bad'];
@endphp
<x-admin.seo-shell title="SEO" active="dashboard">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">SEO</h1>
        </div>
    </div>

    <div class="v-settle">
        <div class="v-stats">
            <div class="v-stat">
                <div class="v-stat__label">Health score <x-admin.help :text="$totalWithMeta . ' of ' . $totalEntities . ' entities carry meta.'" /></div>
                <div class="v-stat__value">{{ $overallScore }}%</div>
            </div>
            <a class="v-stat" href="{{ route('admin.extensions.seo.review') }}">
                <div class="v-stat__label">Waiting for review</div>
                <div class="v-stat__value">{{ number_format($pendingCount) }}</div>
            </a>
            <a class="v-stat" href="{{ route('admin.extensions.seo.redirects.index') }}">
                <div class="v-stat__label">Redirects live</div>
                <div class="v-stat__value">{{ number_format($redirectsLive) }}</div>
            </a>
            <a class="v-stat" href="{{ route('admin.extensions.seo.not-found.index') }}">
                <div class="v-stat__label">404s this month</div>
                <div class="v-stat__value">{{ number_format($notFoundThisMonth) }}</div>
            </a>
        </div>

        <x-admin.panel title="Automation">
            <x-slot:acts>
                <a href="{{ route('admin.settings.section', 'automations') }}" class="v-btn v-btn--ghost v-btn--sm">Open Automations</a>
            </x-slot:acts>
            @if ($seoAutomation)
                <dl class="v-facts">
                    <dt>Status</dt>
                    <dd><span class="v-badge {{ $seoAutomation->enabled ? 'v-badge--good' : 'v-badge--quiet' }}">{{ $seoAutomation->enabled ? 'Enabled' : 'Disabled' }}</span></dd>
                    <dt>Schedule</dt>
                    <dd>{{ $seoAutomation->schedule_description }}</dd>
                    <dt>Last run</dt>
                    <dd>
                        @if ($seoAutomation->last_run_at)
                            {{ $seoAutomation->last_run_at->diffForHumans() }}
                            <span class="v-badge {{ $statusTones[$seoAutomation->last_run_status] ?? 'v-badge--quiet' }}">{{ ucfirst($seoAutomation->last_run_status ?? 'unknown') }}</span>
                        @else
                            <span class="v-cell-quiet">Never run</span>
                        @endif
                    </dd>
                </dl>
            @else
                <div class="v-empty">
                    <p class="v-empty__title">Automation not configured</p>
                    <p class="v-empty__line">The seo_generate automation row has not been seeded yet.</p>
                </div>
            @endif
        </x-admin.panel>

        <x-admin.panel title="Recent suggestions" flush>
            <x-slot:acts>
                <a href="{{ route('admin.extensions.seo.review') }}" class="v-btn v-btn--ghost v-btn--sm">Review all</a>
            </x-slot:acts>
            @if ($recentSuggestions->isEmpty())
                <div class="v-empty">
                    <p class="v-empty__title">No suggestions yet</p>
                    <p class="v-empty__line">Run generation from an entity list, or wait for the next scheduled run.</p>
                </div>
            @else
                <div class="v-tablewrap">
                    <table class="v-table">
                        <thead>
                            <tr><th>Entity</th><th>Name</th><th>Status</th><th>Generated</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($recentSuggestions as $suggestion)
                                <tr>
                                    <td><span class="v-badge v-badge--quiet">{{ $entityLabels[$suggestion->entity_type] ?? class_basename($suggestion->entity_type) }}</span></td>
                                    <td>{{ Str::limit($suggestion->entity?->name ?? $suggestion->entity?->title ?? 'Deleted', 40) }}</td>
                                    <td><span class="v-badge {{ $statusTones[$suggestion->status] ?? 'v-badge--quiet' }}">{{ ucfirst($suggestion->status) }}</span></td>
                                    <td class="v-mono v-cell-quiet">{{ $suggestion->generated_at?->format('M d, Y') ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-admin.panel>

        <x-admin.panel title="Search Console, last 28 days" flush>
            @if (!empty($topQueries))
                <div class="v-tablewrap">
                    <table class="v-table">
                        <thead>
                            <tr><th>Query</th><th>Impressions</th><th>CTR</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($topQueries as $query)
                                <tr>
                                    <td>{{ Str::limit($query['query'] ?? '', 40) }}</td>
                                    <td class="v-mono">{{ number_format($query['impressions'] ?? 0) }}</td>
                                    <td class="v-mono">{{ number_format(($query['ctr'] ?? 0) * 100, 1) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="v-empty">
                    <p class="v-empty__title">Search Console not connected</p>
                    <p class="v-empty__line">Connect Google Search Console to see real query performance here.</p>
                    <a href="{{ route('admin.extensions.seo.settings') }}" class="v-btn v-btn--secondary v-btn--sm">Connect in settings</a>
                </div>
            @endif
        </x-admin.panel>
    </div>
</x-admin.seo-shell>
