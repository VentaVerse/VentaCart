<x-admin.settings-shell active="api" title="API Sync Logs" :wide="true">
        <x-admin.card class="mb-6">
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <x-admin.field name="method" label="Method">
                    <x-admin.select name="method">
                        <option value="">All</option>
                        @foreach (['GET', 'POST', 'PUT', 'DELETE'] as $m)
                            <option value="{{ $m }}" {{ request('method') === $m ? 'selected' : '' }}>{{ $m }}</option>
                        @endforeach
                    </x-admin.select>
                </x-admin.field>
                <x-admin.field name="status" label="Status">
                    <x-admin.select name="status">
                        <option value="">All</option>
                        @foreach ([200, 201, 401, 404, 422, 500] as $s)
                            <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ $s }}</option>
                        @endforeach
                    </x-admin.select>
                </x-admin.field>
                <x-admin.field name="search" label="Search">
                    <x-admin.input name="search" :value="request('search')" placeholder="Endpoint or SKU..." class="w-48" />
                </x-admin.field>
                <x-admin.button type="submit">Filter</x-admin.button>
                @if (request()->hasAny(['method', 'status', 'search']))
                    <x-admin.button variant="ghost" href="{{ route('admin.api-sync-logs.index') }}">Clear</x-admin.button>
                @endif
            </form>
            <div class="mt-3">
                <x-admin.button variant="danger" size="sm" type="button" @click="$dispatch('open-modal', 'destroy-all-logs')">Delete All Logs</x-admin.button>
            </div>
        </x-admin.card>

        <x-admin.table>
            <x-slot:head>
                <x-admin.table.th>Method</x-admin.table.th>
                <x-admin.table.th>Endpoint</x-admin.table.th>
                <x-admin.table.th>SKU</x-admin.table.th>
                <x-admin.table.th>Status</x-admin.table.th>
                <x-admin.table.th>Time</x-admin.table.th>
                <x-admin.table.th>IP</x-admin.table.th>
                <x-admin.table.th>Date</x-admin.table.th>
            </x-slot:head>
            @forelse ($logs as $log)
                @php
                    $methodTone = match ($log->method) {
                        'GET' => 'success',
                        'POST' => 'info',
                        'PUT' => 'warning',
                        'DELETE' => 'danger',
                        default => 'neutral',
                    };
                    $statusTone = match (true) {
                        $log->status_code >= 500 => 'danger',
                        $log->status_code >= 400 => 'warning',
                        $log->status_code >= 200 && $log->status_code < 300 => 'success',
                        default => 'neutral',
                    };
                @endphp
                <x-admin.table.row>
                    <x-admin.table.cell><x-admin.badge :tone="$methodTone">{{ $log->method }}</x-admin.badge></x-admin.table.cell>
                    <x-admin.table.cell class="text-v-text font-mono text-xs">{{ $log->endpoint }}</x-admin.table.cell>
                    <x-admin.table.cell class="text-v-secondary font-mono text-xs">{{ $log->sku ?? '-' }}</x-admin.table.cell>
                    <x-admin.table.cell><x-admin.badge :tone="$statusTone">{{ $log->status_code }}</x-admin.badge></x-admin.table.cell>
                    <x-admin.table.cell class="text-v-secondary">{{ $log->response_time_ms }}ms</x-admin.table.cell>
                    <x-admin.table.cell class="text-v-muted font-mono text-xs">{{ $log->ip_address }}</x-admin.table.cell>
                    <x-admin.table.cell class="text-v-secondary whitespace-nowrap">{{ $log->created_at?->format('M d, Y H:i:s') }}</x-admin.table.cell>
                </x-admin.table.row>
            @empty
                <tr><td colspan="7" class="px-4 py-2"><x-admin.empty-state icon="settings" title="No API logs found" /></td></tr>
            @endforelse

            <x-slot:footer>
                @if ($logs->hasPages())
                    {{ $logs->links() }}
                @endif
            </x-slot:footer>
        </x-admin.table>

        <x-admin.confirm-dialog name="destroy-all-logs" title="Delete all API sync logs?"
            message="Delete ALL API sync logs? This cannot be undone." confirm="Delete All"
            :action="route('admin.api-sync-logs.destroy-all')" method="DELETE" />
</x-admin.settings-shell>
