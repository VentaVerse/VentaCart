@php
    $totalEntities = collect($healthScores)->sum('total');
    $totalWithMeta = collect($healthScores)->sum('withMeta');
    $overallScore = $totalEntities > 0 ? round(($totalWithMeta / $totalEntities) * 100, 1) : 0;
    $scoreTone = $overallScore >= 70 ? 'accent' : ($overallScore >= 30 ? 'warning' : 'danger');

    $entityLabels = [
        'App\\Models\\Product' => 'Product',
        'App\\Models\\Category' => 'Category',
        'App\\Models\\Brand' => 'Brand',
        'App\\Models\\BlogPost' => 'Blog Post',
        'App\\Models\\Page' => 'Page',
    ];

    $statusTones = ['pending' => 'warning', 'accepted' => 'success', 'rejected' => 'danger'];
@endphp
<x-admin.shell title="SEO">
    <x-admin.page title="SEO">
        <div class="flex flex-col gap-6 lg:flex-row lg:gap-8">
            @include('admin.marketing.seo.partials.nav-v2')

            <div class="min-w-0 flex-1 space-y-6">
                {{-- Stat row --}}
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <x-admin.stat label="Health Score" :value="$overallScore . '%'" :tone="$scoreTone" />
                    <x-admin.stat label="Pending Review" :value="$pendingCount" :tone="$pendingCount > 0 ? 'warning' : 'default'" />
                    <x-admin.stat label="Redirects Live" :value="$redirectsLive" />
                    <x-admin.stat label="404s This Month" :value="$notFoundThisMonth" :tone="$notFoundThisMonth > 0 ? 'warning' : 'default'" />
                </div>

                {{-- Automation status --}}
                <x-admin.card title="Automation">
                    <x-slot name="headerActions">
                        <x-admin.button variant="ghost" size="sm" :href="route('admin.settings.section', 'automations')">
                            Open Automations <x-admin.icon name="arrow-right" class="w-3.5 h-3.5" />
                        </x-admin.button>
                    </x-slot>

                    @if ($seoAutomation)
                        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-[13px]">
                            <div class="flex items-center gap-2">
                                <span class="text-v-muted">Status</span>
                                <x-admin.badge :tone="$seoAutomation->enabled ? 'success' : 'neutral'">
                                    {{ $seoAutomation->enabled ? 'Enabled' : 'Disabled' }}
                                </x-admin.badge>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-v-muted">Schedule</span>
                                <span class="text-v-text">{{ $seoAutomation->schedule_description }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-v-muted">Last run</span>
                                @if ($seoAutomation->last_run_at)
                                    <span class="text-v-text">{{ $seoAutomation->last_run_at->diffForHumans() }}</span>
                                    <x-admin.badge :tone="$statusTones[$seoAutomation->last_run_status] ?? 'neutral'">
                                        {{ ucfirst($seoAutomation->last_run_status ?? 'unknown') }}
                                    </x-admin.badge>
                                @else
                                    <span class="text-v-muted">Never run</span>
                                @endif
                            </div>
                        </div>
                    @else
                        <x-admin.empty-state icon="warning" title="Automation not configured"
                            description="The seo_generate automation row hasn't been seeded yet. Run the AutomationSeeder to enable scheduled generation." />
                    @endif
                </x-admin.card>

                {{-- Recent suggestions --}}
                <x-admin.card title="Recent Suggestions">
                    <x-slot name="headerActions">
                        <x-admin.button variant="secondary" size="sm" :href="route('admin.extensions.seo.review')">Review all <x-admin.icon name="arrow-right" class="w-3.5 h-3.5" /></x-admin.button>
                    </x-slot>

                    @if ($recentSuggestions->isEmpty())
                        <x-admin.empty-state icon="seo" title="No suggestions yet"
                            description="Run generation from an entity list, or wait for the next scheduled run." />
                    @else
                        <x-admin.table>
                            <x-slot name="head">
                                <x-admin.table.th>Entity</x-admin.table.th>
                                <x-admin.table.th>Name</x-admin.table.th>
                                <x-admin.table.th>Status</x-admin.table.th>
                                <x-admin.table.th align="right">Generated</x-admin.table.th>
                            </x-slot>
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
                </x-admin.card>

                {{-- Search Console --}}
                <x-admin.card title="Search Console" description="Top queries by impressions over the last 28 days.">
                    @if (!empty($topQueries))
                        <x-admin.table>
                            <x-slot name="head">
                                <x-admin.table.th>Query</x-admin.table.th>
                                <x-admin.table.th align="right">Impressions</x-admin.table.th>
                                <x-admin.table.th align="right">CTR</x-admin.table.th>
                            </x-slot>
                            @foreach ($topQueries as $query)
                                <x-admin.table.row>
                                    <x-admin.table.cell class="text-v-text">{{ Str::limit($query['query'] ?? '', 40) }}</x-admin.table.cell>
                                    <x-admin.table.cell align="right" mono>{{ number_format($query['impressions'] ?? 0) }}</x-admin.table.cell>
                                    <x-admin.table.cell align="right" mono>{{ number_format(($query['ctr'] ?? 0) * 100, 1) }}%</x-admin.table.cell>
                                </x-admin.table.row>
                            @endforeach
                        </x-admin.table>
                    @else
                        <x-admin.empty-state icon="search" title="Search Console not connected"
                            description="Connect Google Search Console to see real query performance here.">
                            <x-admin.button variant="secondary" size="sm" :href="route('admin.extensions.seo.settings')">Connect in Settings <x-admin.icon name="arrow-right" class="w-3.5 h-3.5" /></x-admin.button>
                        </x-admin.empty-state>
                    @endif
                </x-admin.card>
            </div>
        </div>
    </x-admin.page>
</x-admin.shell>
