<x-admin.seo-shell title="SEO Search Console" heading="Search Console">
    @if (! $gscConfigured)
        <x-admin.panel flush>
            <x-admin.empty-state title="Google Search Console is not configured yet"
                description="Upload credentials and set your site URL to see real query performance here.">
                <x-admin.button variant="secondary" size="sm" :href="route('admin.extensions.seo.settings')">Go to settings</x-admin.button>
            </x-admin.empty-state>
        </x-admin.panel>
    @else
        <div class="space-y-5">
            <x-admin.toolbar>
                <span class="text-xs text-v-muted">
                    @if ($fetchedAt)
                        Fetched {{ $fetchedAt->diffForHumans() }} ({{ $fetchedAt->format('M d, Y g:i A') }})
                    @else
                        Not fetched yet
                    @endif
                </span>
                <div class="v-toolbar__actions">
                    {{-- The one thing these numbers cannot say for
                         themselves: they are Google's, and Google is
                         two to three days behind. --}}
                    <x-admin.hint label="How current this is">Search Console data lags by two to three days, so the most recent days are always incomplete.</x-admin.hint>
                    <x-admin.button variant="secondary" size="sm" :href="route('admin.extensions.seo.search-console', ['refresh' => 1])">Refresh</x-admin.button>
                </div>
            </x-admin.toolbar>

            <x-admin.panel title="Top queries, last 28 days" flush>
                @if (count($queries) > 0)
                    <x-admin.table bare>
                        <x-slot:head>
                            <x-admin.table.th>Query</x-admin.table.th>
                            <x-admin.table.th align="right">Clicks</x-admin.table.th>
                            <x-admin.table.th align="right">Impressions</x-admin.table.th>
                            <x-admin.table.th align="right">CTR</x-admin.table.th>
                            <x-admin.table.th align="right">Avg position</x-admin.table.th>
                        </x-slot:head>
                        @foreach ($queries as $row)
                            @php
                                $ctrVal = $row['ctr'] * 100;
                                $ctrTone = $ctrVal < 2 ? 'text-v-danger' : ($ctrVal <= 5 ? 'text-v-warning' : 'text-v-accent');
                                $posTone = $row['position'] < 10 ? 'text-v-accent' : ($row['position'] <= 20 ? 'text-v-warning' : 'text-v-danger');
                            @endphp
                            <x-admin.table.row>
                                <x-admin.table.cell class="text-v-text">{{ $row['query'] }}</x-admin.table.cell>
                                <x-admin.table.cell align="right" mono>{{ number_format($row['clicks']) }}</x-admin.table.cell>
                                <x-admin.table.cell align="right" mono>{{ number_format($row['impressions']) }}</x-admin.table.cell>
                                <x-admin.table.cell align="right" mono class="font-medium {{ $ctrTone }}">{{ number_format($ctrVal, 1) }}%</x-admin.table.cell>
                                <x-admin.table.cell align="right" mono class="font-medium {{ $posTone }}">{{ number_format($row['position'], 1) }}</x-admin.table.cell>
                            </x-admin.table.row>
                        @endforeach
                    </x-admin.table>
                @else
                    <x-admin.empty-state title="No query data available" />
                @endif
            </x-admin.panel>

            <x-admin.panel title="Top pages, last 28 days" flush>
                @if (count($pages) > 0)
                    <x-admin.table bare>
                        <x-slot:head>
                            <x-admin.table.th>Page URL</x-admin.table.th>
                            <x-admin.table.th align="right">Clicks</x-admin.table.th>
                            <x-admin.table.th align="right">Impressions</x-admin.table.th>
                            <x-admin.table.th align="right">CTR</x-admin.table.th>
                            <x-admin.table.th align="right">Avg position</x-admin.table.th>
                        </x-slot:head>
                        @foreach ($pages as $row)
                            @php
                                $ctrVal = $row['ctr'] * 100;
                                $ctrTone = $ctrVal < 2 ? 'text-v-danger' : ($ctrVal <= 5 ? 'text-v-warning' : 'text-v-accent');
                                $posTone = $row['position'] < 10 ? 'text-v-accent' : ($row['position'] <= 20 ? 'text-v-warning' : 'text-v-danger');
                            @endphp
                            <x-admin.table.row>
                                <x-admin.table.cell class="max-w-xs truncate text-v-text" title="{{ $row['page'] }}">{{ Str::limit($row['page'], 60) }}</x-admin.table.cell>
                                <x-admin.table.cell align="right" mono>{{ number_format($row['clicks']) }}</x-admin.table.cell>
                                <x-admin.table.cell align="right" mono>{{ number_format($row['impressions']) }}</x-admin.table.cell>
                                <x-admin.table.cell align="right" mono class="font-medium {{ $ctrTone }}">{{ number_format($ctrVal, 1) }}%</x-admin.table.cell>
                                <x-admin.table.cell align="right" mono class="font-medium {{ $posTone }}">{{ number_format($row['position'], 1) }}</x-admin.table.cell>
                            </x-admin.table.row>
                        @endforeach
                    </x-admin.table>
                @else
                    <x-admin.empty-state title="No page data available" />
                @endif
            </x-admin.panel>
        </div>
    @endif
</x-admin.seo-shell>
