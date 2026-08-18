{{-- No settings-nav item claims this page (Settings > API retired with
     the erp-sync extension move; ERP Sync's own settings page links
     here), so nothing in the rail is lit. --}}
<x-admin3.settings-shell title="API Sync Logs">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.settings.index') }}">Settings</a>
                <span aria-hidden="true">/</span>
                <span>API Sync Logs</span>
            </div>
            <h1 class="v3-head__title">API Sync Logs</h1>
        </div>
        <div class="v3-head__actions">
            <button type="button" class="v3-btn v3-btn--danger" @click="$dispatch('v3-confirm', 'destroy-all-logs')">Delete all logs</button>
        </div>
    </div>

    <div class="v3-settle">
        <form method="GET" action="{{ route('admin.api-sync-logs.index') }}" class="v3-filterbar">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Endpoint or SKU…"
                   aria-label="Search logs" class="v3-control v3-filterbar__grow">
            <select name="method" aria-label="Filter by method" class="v3-control v3-w-name">
                <option value="">All methods</option>
                @foreach (['GET', 'POST', 'PUT', 'DELETE'] as $m)
                    <option value="{{ $m }}" @selected(request('method') === $m)>{{ $m }}</option>
                @endforeach
            </select>
            <select name="status" aria-label="Filter by status" class="v3-control v3-w-name">
                <option value="">All statuses</option>
                @foreach ([200, 201, 401, 404, 422, 500] as $s)
                    <option value="{{ $s }}" @selected(request('status') == $s)>{{ $s }}</option>
                @endforeach
            </select>
            <button type="submit" class="v3-btn v3-btn--secondary">Filter</button>
            @if (request()->hasAny(['method', 'status', 'search']))
                <a href="{{ route('admin.api-sync-logs.index') }}" class="v3-link">Clear</a>
            @endif
        </form>

        <x-admin3.panel flush>
            <div class="v3-tablewrap">
                <table class="v3-table">
                    <thead>
                        <tr>
                            <th>Method</th>
                            <th>Endpoint</th>
                            <th>SKU</th>
                            <th>Status</th>
                            <th class="v3-cell-num">Time</th>
                            <th>IP</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            @php
                                $methodTone = match ($log->method) {
                                    'GET' => 'good',
                                    'POST' => 'info',
                                    'PUT' => 'warn',
                                    'DELETE' => 'bad',
                                    default => 'quiet',
                                };
                                $statusTone = match (true) {
                                    $log->status_code >= 500 => 'bad',
                                    $log->status_code >= 400 => 'warn',
                                    $log->status_code >= 200 && $log->status_code < 300 => 'good',
                                    default => 'quiet',
                                };
                            @endphp
                            <tr>
                                <td><span class="v3-badge v3-badge--{{ $methodTone }}">{{ $log->method }}</span></td>
                                <td class="v3-mono">{{ $log->endpoint }}</td>
                                <td class="v3-mono v3-cell-quiet">{{ $log->sku ?? '-' }}</td>
                                <td><span class="v3-badge v3-badge--{{ $statusTone }}">{{ $log->status_code }}</span></td>
                                <td class="v3-cell-num v3-mono">{{ $log->response_time_ms }}ms</td>
                                <td class="v3-mono v3-cell-quiet">{{ $log->ip_address }}</td>
                                <td class="v3-cell-quiet">{{ $log->created_at?->format('M d, Y H:i:s') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="v3-empty">
                                        <p class="v3-empty__title">No API logs found</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin3.panel>

        <x-admin3.pager :paginator="$logs" />

        <x-admin3.confirm name="destroy-all-logs" title="Delete all API sync logs?"
            message="Delete ALL API sync logs? This cannot be undone." confirm="Delete all"
            :action="route('admin.api-sync-logs.destroy-all')" method="DELETE" />
    </div>
</x-admin3.settings-shell>
