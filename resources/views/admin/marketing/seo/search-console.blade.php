<x-admin3.seo-shell title="Search Console" active="search-console">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Search Console
                <x-admin3.help text="Google's numbers, fetched from Search Console. The data lags by two to three days, so the most recent days are always incomplete." /></h1>
            @if ($gscConfigured)
                <p class="v3-head__standing">
                    @if ($fetchedAt)
                        Fetched {{ $fetchedAt->diffForHumans() }} ({{ $fetchedAt->format('M d, Y g:i A') }})
                    @else
                        Not fetched yet
                    @endif
                </p>
            @endif
        </div>
        @if ($gscConfigured)
            <div class="v3-head__actions">
                <a href="{{ route('admin.extensions.seo.search-console', ['refresh' => 1]) }}" class="v3-btn v3-btn--secondary">Refresh</a>
            </div>
        @endif
    </div>

    <div class="v3-settle">
        @if (! $gscConfigured)
            <x-admin3.panel>
                <div class="v3-empty">
                    <p class="v3-empty__title">Google Search Console is not configured yet</p>
                    <p class="v3-empty__line">Upload credentials and set your site URL to see real query performance here.</p>
                    <a href="{{ route('admin.extensions.seo.settings') }}" class="v3-btn v3-btn--secondary v3-btn--sm">Go to settings</a>
                </div>
            </x-admin3.panel>
        @else
            @foreach ([['Top queries, last 28 days', $queries, 'query', 'Query'], ['Top pages, last 28 days', $pages, 'page', 'Page URL']] as [$panelTitle, $rows, $field, $colLabel])
                <x-admin3.panel :title="$panelTitle" flush>
                    @if (count($rows) > 0)
                        <div class="v3-tablewrap">
                            <table class="v3-table">
                                <thead>
                                    <tr><th>{{ $colLabel }}</th><th>Clicks</th><th>Impressions</th><th>CTR</th><th>Avg position</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $row)
                                        @php
                                            $ctrVal = $row['ctr'] * 100;
                                            $ctrTone = $ctrVal < 2 ? 'v3-badge--bad' : ($ctrVal <= 5 ? 'v3-badge--warn' : 'v3-badge--good');
                                            $posTone = $row['position'] < 10 ? 'v3-badge--good' : ($row['position'] <= 20 ? 'v3-badge--warn' : 'v3-badge--bad');
                                        @endphp
                                        <tr>
                                            <td class="v3-cell-url" title="{{ $row[$field] }}">{{ Str::limit($row[$field], 60) }}</td>
                                            <td class="v3-mono">{{ number_format($row['clicks']) }}</td>
                                            <td class="v3-mono">{{ number_format($row['impressions']) }}</td>
                                            <td class="v3-mono {{ $ctrTone }}">{{ number_format($ctrVal, 1) }}%</td>
                                            <td class="v3-mono {{ $posTone }}">{{ number_format($row['position'], 1) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="v3-empty"><p class="v3-empty__title">No data available</p></div>
                    @endif
                </x-admin3.panel>
            @endforeach
        @endif
    </div>
</x-admin3.seo-shell>
