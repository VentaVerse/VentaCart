<x-admin.seo-shell title="Search Console" active="search-console">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">Search Console
                <x-admin.help text="Google's numbers, fetched from Search Console. The data lags by two to three days, so the most recent days are always incomplete." /></h1>
            @if ($gscConfigured)
                <p class="v-head__standing">
                    @if ($fetchedAt)
                        Fetched {{ $fetchedAt->diffForHumans() }} ({{ $fetchedAt->format('M d, Y g:i A') }})
                    @else
                        Not fetched yet
                    @endif
                </p>
            @endif
        </div>
        @if ($gscConfigured)
            <div class="v-head__actions">
                <a href="{{ route('admin.extensions.seo.search-console', ['refresh' => 1]) }}" class="v-btn v-btn--secondary">Refresh</a>
            </div>
        @endif
    </div>

    <div class="v-settle">
        @if (! $gscConfigured)
            <x-admin.panel>
                <div class="v-empty">
                    <p class="v-empty__title">Google Search Console is not configured yet</p>
                    <p class="v-empty__line">Upload credentials and set your site URL to see real query performance here.</p>
                    <a href="{{ route('admin.extensions.seo.settings') }}" class="v-btn v-btn--secondary v-btn--sm">Go to settings</a>
                </div>
            </x-admin.panel>
        @else
            @foreach ([['Top queries, last 28 days', $queries, 'query', 'Query'], ['Top pages, last 28 days', $pages, 'page', 'Page URL']] as [$panelTitle, $rows, $field, $colLabel])
                <x-admin.panel :title="$panelTitle" flush>
                    @if (count($rows) > 0)
                        <div class="v-tablewrap">
                            <table class="v-table">
                                <thead>
                                    <tr><th>{{ $colLabel }}</th><th>Clicks</th><th>Impressions</th><th>CTR</th><th>Avg position</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $row)
                                        @php
                                            $ctrVal = $row['ctr'] * 100;
                                            $ctrTone = $ctrVal < 2 ? 'v-badge--bad' : ($ctrVal <= 5 ? 'v-badge--warn' : 'v-badge--good');
                                            $posTone = $row['position'] < 10 ? 'v-badge--good' : ($row['position'] <= 20 ? 'v-badge--warn' : 'v-badge--bad');
                                        @endphp
                                        <tr>
                                            <td class="v-cell-url" title="{{ $row[$field] }}">{{ Str::limit($row[$field], 60) }}</td>
                                            <td class="v-mono">{{ number_format($row['clicks']) }}</td>
                                            <td class="v-mono">{{ number_format($row['impressions']) }}</td>
                                            <td class="v-mono {{ $ctrTone }}">{{ number_format($ctrVal, 1) }}%</td>
                                            <td class="v-mono {{ $posTone }}">{{ number_format($row['position'], 1) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="v-empty"><p class="v-empty__title">No data available</p></div>
                    @endif
                </x-admin.panel>
            @endforeach
        @endif
    </div>
</x-admin.seo-shell>
