<x-admin-layout>
    <x-slot name="header">API Sync Logs</x-slot>

    {{-- Filters --}}
    <div class="mb-4">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Method</label>
                <select name="method" class="rounded-md border-gray-300 text-sm py-1.5 pr-8">
                    <option value="">All</option>
                    @foreach (['GET', 'POST', 'PUT', 'DELETE'] as $m)
                        <option value="{{ $m }}" {{ request('method') === $m ? 'selected' : '' }}>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                <select name="status" class="rounded-md border-gray-300 text-sm py-1.5 pr-8">
                    <option value="">All</option>
                    @foreach ([200, 201, 401, 404, 422, 500] as $s)
                        <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Endpoint or SKU..."
                    class="rounded-md border-gray-300 text-sm py-1.5 w-48">
            </div>
            <button type="submit" class="px-3 py-1.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Filter</button>
            @if (request()->hasAny(['method', 'status', 'search']))
                <a href="{{ route('admin.api-sync-logs.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Clear</a>
            @endif
        </form>
        <form method="POST" action="{{ route('admin.api-sync-logs.destroy-all') }}" class="mt-3" onsubmit="return confirm('Delete ALL API sync logs? This cannot be undone.')">
            @csrf @method('DELETE')
            <button type="submit" class="px-3 py-1.5 bg-red-100 text-red-700 text-xs font-medium rounded-lg hover:bg-red-200 transition-colors">Delete All Logs</button>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Endpoint</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SKU</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($logs as $log)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap">
                                @php
                                    $methodColor = match($log->method) {
                                        'GET' => 'bg-green-100 text-green-700',
                                        'POST' => 'bg-blue-100 text-blue-700',
                                        'PUT' => 'bg-amber-100 text-amber-700',
                                        'DELETE' => 'bg-red-100 text-red-700',
                                        default => 'bg-gray-100 text-gray-700',
                                    };
                                @endphp
                                <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $methodColor }}">{{ $log->method }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 font-mono">{{ $log->endpoint }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500 font-mono">{{ $log->sku ?? '-' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @php
                                    $statusColor = match(true) {
                                        $log->status_code >= 500 => 'text-red-600 font-semibold',
                                        $log->status_code >= 400 => 'text-amber-600 font-semibold',
                                        $log->status_code >= 200 && $log->status_code < 300 => 'text-green-600',
                                        default => 'text-gray-600',
                                    };
                                @endphp
                                <span class="text-sm {{ $statusColor }}">{{ $log->status_code }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ $log->response_time_ms }}ms</td>
                            <td class="px-4 py-3 text-sm text-gray-400 font-mono">{{ $log->ip_address }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500 whitespace-nowrap">{{ $log->created_at?->format('M d, Y H:i:s') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-400">No API logs found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div class="px-4 py-3 border-t border-gray-200">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</x-admin-layout>
