<x-admin.shell title="SEO Search Console">
    <x-admin.page title="Search Console"
        :breadcrumb="[['label' => 'SEO', 'url' => route('admin.extensions.seo.dashboard')], ['label' => 'Search Console']]">
        <div class="flex flex-col gap-6 lg:flex-row lg:gap-8">
            @include('admin.marketing.seo.partials.nav-v2')

            <div class="min-w-0 flex-1 space-y-6">
                @if (! $gscConfigured)
                    <x-admin.card>
                        <x-admin.empty-state icon="search" title="Google Search Console is not configured yet"
                            description="Upload credentials and set your site URL to see real query performance here.">
                            <x-admin.button variant="secondary" size="sm" :href="route('admin.extensions.seo.settings')">Go to Settings →</x-admin.button>
                        </x-admin.empty-state>
                    </x-admin.card>
                @else
                    {{-- Last updated + Refresh --}}
                    <div class="flex items-center justify-between">
                        <p class="text-xs text-v-muted">
                            @if ($fetchedAt)
                                Last fetched: {{ $fetchedAt->diffForHumans() }} ({{ $fetchedAt->format('M d, Y g:i A') }})
                            @else
                                Not fetched yet.
                            @endif
                            <span class="mx-1 text-v-border">|</span>
                            Data from Google has a 2-3 day delay.
                        </p>
                        <x-admin.button variant="secondary" size="sm" :href="route('admin.extensions.seo.search-console', ['refresh' => 1])">
                            Refresh
                        </x-admin.button>
                    </div>

                    {{-- Top Queries --}}
                    <x-admin.card title="Top Queries" description="Last 28 days.">
                        @if (count($queries) > 0)
                            <x-admin.table>
                                <x-slot name="head">
                                    <x-admin.table.th>Query</x-admin.table.th>
                                    <x-admin.table.th align="right">Clicks</x-admin.table.th>
                                    <x-admin.table.th align="right">Impressions</x-admin.table.th>
                                    <x-admin.table.th align="right">CTR</x-admin.table.th>
                                    <x-admin.table.th align="right">Avg Position</x-admin.table.th>
                                </x-slot>
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
                                        <x-admin.table.cell align="right" class="font-medium {{ $ctrTone }}">{{ number_format($ctrVal, 1) }}%</x-admin.table.cell>
                                        <x-admin.table.cell align="right" class="font-medium {{ $posTone }}">{{ number_format($row['position'], 1) }}</x-admin.table.cell>
                                    </x-admin.table.row>
                                @endforeach
                            </x-admin.table>
                        @else
                            <x-admin.empty-state icon="search" title="No query data available" />
                        @endif
                    </x-admin.card>

                    {{-- Top Pages --}}
                    <x-admin.card title="Top Pages" description="Last 28 days.">
                        @if (count($pages) > 0)
                            <x-admin.table>
                                <x-slot name="head">
                                    <x-admin.table.th>Page URL</x-admin.table.th>
                                    <x-admin.table.th align="right">Clicks</x-admin.table.th>
                                    <x-admin.table.th align="right">Impressions</x-admin.table.th>
                                    <x-admin.table.th align="right">CTR</x-admin.table.th>
                                    <x-admin.table.th align="right">Avg Position</x-admin.table.th>
                                </x-slot>
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
                                        <x-admin.table.cell align="right" class="font-medium {{ $ctrTone }}">{{ number_format($ctrVal, 1) }}%</x-admin.table.cell>
                                        <x-admin.table.cell align="right" class="font-medium {{ $posTone }}">{{ number_format($row['position'], 1) }}</x-admin.table.cell>
                                    </x-admin.table.row>
                                @endforeach
                            </x-admin.table>
                        @else
                            <x-admin.empty-state icon="search" title="No page data available" />
                        @endif
                    </x-admin.card>
                @endif
            </div>
        </div>
    </x-admin.page>
</x-admin.shell>
