<x-admin-layout>
    <x-slot name="header">Stock Alerts</x-slot>

    {{-- Summary Stats --}}
    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <p class="text-2xl font-bold text-amber-600">{{ $totalWaiting }}</p>
            <p class="text-sm text-gray-500">Waiting</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <p class="text-2xl font-bold text-green-600">{{ $totalNotified }}</p>
            <p class="text-sm text-gray-500">Notified</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <p class="text-2xl font-bold text-red-600">{{ $totalFailed }}</p>
            <p class="text-sm text-gray-500">Failed</p>
        </div>
    </div>

    <div x-data="{ activeTab: '{{ $tab }}' }">
        {{-- Tabs --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <nav class="flex border-b border-gray-200 overflow-x-auto">
                @foreach ([['id' => 'waitlist', 'label' => 'Waitlist'], ['id' => 'notified', 'label' => 'Notified'], ['id' => 'template', 'label' => 'Email Template']] as $t)
                    <button type="button" @click="activeTab = '{{ $t['id'] }}'"
                        :class="activeTab === '{{ $t['id'] }}' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap px-5 py-3 border-b-2 text-sm font-medium transition-colors">{{ $t['label'] }}</button>
                @endforeach
            </nav>

            {{-- WAITLIST TAB --}}
            <div x-show="activeTab === 'waitlist'" class="p-6">
                {{-- Search --}}
                <form method="GET" class="flex gap-3 mb-4">
                    <input type="hidden" name="tab" value="waitlist">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search product, name, or email..."
                        class="flex-1 rounded-lg border-gray-300 text-sm">
                    <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm rounded-lg hover:bg-gray-700">Search</button>
                    @if ($search)
                        <a href="{{ route('admin.stock-notifications.index', ['tab' => 'waitlist']) }}" class="px-4 py-2 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300">Clear</a>
                    @endif
                </form>

                {{-- Bulk Delete Form --}}
                <form method="POST" action="{{ route('admin.stock-notifications.bulk-delete') }}" x-data="{ selected: [] }" onsubmit="return confirm('Delete selected notifications?')">
                    @csrf
                    <template x-for="id in selected" :key="id">
                        <input type="hidden" name="ids[]" :value="id">
                    </template>

                    <div class="flex items-center justify-between mb-3">
                        <button type="submit" x-show="selected.length > 0" class="px-3 py-1.5 bg-red-600 text-white text-xs rounded hover:bg-red-700">
                            Delete Selected (<span x-text="selected.length"></span>)
                        </button>
                        <span class="text-xs text-gray-400" x-show="selected.length === 0">Select items to bulk delete</span>
                    </div>

                    <div class="overflow-hidden rounded-lg border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 w-8"><input type="checkbox" @change="selected = $event.target.checked ? [{{ $waitlist->pluck('id')->join(',') }}] : []" class="rounded border-gray-300"></th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Variant</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Requested</th>
                                    <th class="px-4 py-3 w-16"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($waitlist as $item)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3"><input type="checkbox" :value="{{ $item->id }}" x-model.number="selected" class="rounded border-gray-300"></td>
                                        <td class="px-4 py-3 text-sm text-gray-900">{{ $item->name }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ $item->email }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-900">{{ $item->product?->name ?? 'Deleted' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ $item->variant?->name ?? '—' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-400">{{ $item->created_at->format('M j, Y') }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <form method="POST" action="{{ route('admin.stock-notifications.destroy', $item) }}" class="inline" onsubmit="return confirm('Remove?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900 text-sm">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No subscribers waiting.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($waitlist->hasPages())
                        <div class="mt-4">{{ $waitlist->links() }}</div>
                    @endif
                </form>

                {{-- Analytics: Most Requested --}}
                @if ($topProducts->isNotEmpty())
                    <div class="mt-8 border-t pt-6">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3 uppercase tracking-wide">Most Requested Products</h3>
                        <div class="overflow-hidden rounded-lg border border-gray-200">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Requests</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Currently Waiting</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach ($topProducts as $tp)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 text-sm text-gray-900">{{ $tp->name }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-500">{{ $tp->total_requests }}</td>
                                            <td class="px-4 py-3 text-sm">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $tp->waiting_count > 0 ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600' }}">
                                                    {{ $tp->waiting_count }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>

            {{-- NOTIFIED TAB --}}
            <div x-show="activeTab === 'notified'" class="p-6">
                <div class="overflow-hidden rounded-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Variant</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Requested</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Notified</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($notified as $item)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $item->name }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $item->email }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $item->product?->name ?? 'Deleted' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $item->variant?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-400">{{ $item->created_at->format('M j, Y') }}</td>
                                    <td class="px-4 py-3 text-sm text-green-600">{{ $item->notified_at?->format('M j, Y g:i A') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">No notifications sent yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($notified->hasPages())
                    <div class="mt-4">{{ $notified->links() }}</div>
                @endif
            </div>

            {{-- EMAIL TEMPLATE TAB --}}
            <div x-show="activeTab === 'template'" class="p-6">
                <form method="POST" action="{{ route('admin.stock-notifications.update-template') }}" class="space-y-4 max-w-2xl">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                        <input type="text" name="subject" value="{{ old('subject', $template?->subject ?? '') }}"
                            class="w-full rounded-lg border-gray-300 text-sm" placeholder="Good news! {product_name} is back in stock">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Body</label>
                        <textarea name="body" id="stock-template-body" rows="12"
                            class="tinymce-editor w-full rounded-lg border-gray-300 text-sm">{{ old('body', $template?->body ?? '') }}</textarea>
                    </div>
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <p class="text-xs font-semibold text-gray-600 mb-2">Available Placeholders:</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach (['{customer_name}', '{product_name}', '{product_url}', '{variant_name}', '{product_image}', '{store_name}'] as $ph)
                                <code class="text-xs bg-white border border-gray-200 rounded px-2 py-1 text-indigo-600">{{ $ph }}</code>
                            @endforeach
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save Template</button>
                    </div>
                </form>

                <div class="border-t border-gray-200 mt-6 pt-6">
                    <h4 class="text-sm font-medium text-gray-700 mb-3">Test Email</h4>
                    <form method="POST" action="{{ route('admin.stock-notifications.send-test') }}" class="flex items-center gap-3">
                        @csrf
                        <span class="text-sm text-gray-500">Send test to {{ auth()->user()->email }}</span>
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700">Send Test</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="{{ asset('vendor/tinymce/tinymce.min.js') }}"></script>
    <script>
        tinymce.init({
            selector: '.tinymce-editor',
            height: 350,
            menubar: false,
            plugins: 'lists link autolink code table',
            toolbar: 'undo redo | blocks | bold italic underline strikethrough | bullist numlist | link table | removeformat code',
            content_style: 'body { font-family: Inter, ui-sans-serif, system-ui, sans-serif; font-size: 14px; }',
            branding: false,
            promotion: false,
            license_key: 'gpl',
            convert_urls: false,
        });
    </script>
    @endpush
</x-admin-layout>
