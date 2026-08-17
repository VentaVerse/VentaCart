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

    $statusTones = ['pending' => 'v3-badge--warn', 'accepted' => 'v3-badge--good', 'rejected' => 'v3-badge--bad'];
@endphp
<x-admin3.seo-shell title="SEO" active="dashboard">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">SEO</h1>
        </div>
    </div>

    <div class="v3-settle">
        <div class="v3-stats">
            <div class="v3-stat">
                <div class="v3-stat__label">Health score <x-admin3.help :text="$totalWithMeta . ' of ' . $totalEntities . ' entities carry meta.'" /></div>
                <div class="v3-stat__value">{{ $overallScore }}%</div>
            </div>
            <a class="v3-stat" href="{{ route('admin.extensions.seo.review') }}">
                <div class="v3-stat__label">Waiting for review</div>
                <div class="v3-stat__value">{{ number_format($pendingCount) }}</div>
            </a>
            <a class="v3-stat" href="{{ route('admin.extensions.seo.redirects.index') }}">
                <div class="v3-stat__label">Redirects live</div>
                <div class="v3-stat__value">{{ number_format($redirectsLive) }}</div>
            </a>
            <a class="v3-stat" href="{{ route('admin.extensions.seo.not-found.index') }}">
                <div class="v3-stat__label">404s this month</div>
                <div class="v3-stat__value">{{ number_format($notFoundThisMonth) }}</div>
            </a>
        </div>

        <x-admin3.panel title="Automation">
            <x-slot:acts>
                <a href="{{ route('admin.settings.section', 'automations') }}" class="v3-btn v3-btn--ghost v3-btn--sm">Open Automations</a>
            </x-slot:acts>
            @if ($seoAutomation)
                <dl class="v3-facts">
                    <dt>Status</dt>
                    <dd><span class="v3-badge {{ $seoAutomation->enabled ? 'v3-badge--good' : 'v3-badge--quiet' }}">{{ $seoAutomation->enabled ? 'Enabled' : 'Disabled' }}</span></dd>
                    <dt>Schedule</dt>
                    <dd>{{ $seoAutomation->schedule_description }}</dd>
                    <dt>Last run</dt>
                    <dd>
                        @if ($seoAutomation->last_run_at)
                            {{ $seoAutomation->last_run_at->diffForHumans() }}
                            <span class="v3-badge {{ $statusTones[$seoAutomation->last_run_status] ?? 'v3-badge--quiet' }}">{{ ucfirst($seoAutomation->last_run_status ?? 'unknown') }}</span>
                        @else
                            <span class="v3-cell-quiet">Never run</span>
                        @endif
                    </dd>
                </dl>
            @else
                <div class="v3-empty">
                    <p class="v3-empty__title">Automation not configured</p>
                    <p class="v3-empty__line">The seo_generate automation row has not been seeded yet.</p>
                </div>
            @endif
        </x-admin3.panel>

        <x-admin3.panel title="Recent suggestions" flush>
            <x-slot:acts>
                <a href="{{ route('admin.extensions.seo.review') }}" class="v3-btn v3-btn--ghost v3-btn--sm">Review all</a>
            </x-slot:acts>
            @if ($recentSuggestions->isEmpty())
                <div class="v3-empty">
                    <p class="v3-empty__title">No suggestions yet</p>
                    <p class="v3-empty__line">Run generation from an entity list, or wait for the next scheduled run.</p>
                </div>
            @else
                <div class="v3-tablewrap">
                    <table class="v3-table">
                        <thead>
                            <tr><th>Entity</th><th>Name</th><th>Status</th><th>Generated</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($recentSuggestions as $suggestion)
                                <tr>
                                    <td><span class="v3-badge v3-badge--quiet">{{ $entityLabels[$suggestion->entity_type] ?? class_basename($suggestion->entity_type) }}</span></td>
                                    <td>{{ Str::limit($suggestion->entity?->name ?? $suggestion->entity?->title ?? 'Deleted', 40) }}</td>
                                    <td><span class="v3-badge {{ $statusTones[$suggestion->status] ?? 'v3-badge--quiet' }}">{{ ucfirst($suggestion->status) }}</span></td>
                                    <td class="v3-mono v3-cell-quiet">{{ $suggestion->generated_at?->format('M d, Y') ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-admin3.panel>

        <x-admin3.panel title="Search Console, last 28 days" flush>
            @if (!empty($topQueries))
                <div class="v3-tablewrap">
                    <table class="v3-table">
                        <thead>
                            <tr><th>Query</th><th>Impressions</th><th>CTR</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($topQueries as $query)
                                <tr>
                                    <td>{{ Str::limit($query['query'] ?? '', 40) }}</td>
                                    <td class="v3-mono">{{ number_format($query['impressions'] ?? 0) }}</td>
                                    <td class="v3-mono">{{ number_format(($query['ctr'] ?? 0) * 100, 1) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="v3-empty">
                    <p class="v3-empty__title">Search Console not connected</p>
                    <p class="v3-empty__line">Connect Google Search Console to see real query performance here.</p>
                    <a href="{{ route('admin.extensions.seo.settings') }}" class="v3-btn v3-btn--secondary v3-btn--sm">Connect in settings</a>
                </div>
            @endif
        </x-admin3.panel>
    </div>
</x-admin3.seo-shell>
