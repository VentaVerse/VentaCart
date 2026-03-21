<x-admin-layout>
    <x-slot name="header">Shipping Couriers</x-slot>

    @if (session('success'))
        <div class="mb-4 bg-emerald-50 border border-emerald-200 rounded-lg px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
            <p class="text-xs text-gray-500">Manual couriers for tracking shipments. Use <code class="bg-gray-200 px-1 rounded text-[11px]">{tracking_number}</code> in the URL template where the tracking code should be inserted.</p>
        </div>

        <table class="min-w-full divide-y divide-gray-200" x-data="{ editing: null }">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Courier</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tracking URL Template</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Active</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-40">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach ($couriers as $courier)
                    <tr class="hover:bg-gray-50">
                        {{-- Read mode --}}
                        <template x-if="editing !== {{ $courier->id }}">
                            <td class="px-6 py-3 text-sm font-medium text-gray-900">{{ $courier->name }}</td>
                        </template>
                        <template x-if="editing !== {{ $courier->id }}">
                            <td class="px-6 py-3 text-sm text-gray-500 font-mono text-xs truncate max-w-xs" title="{{ $courier->tracking_url }}">{{ $courier->tracking_url ?? '—' }}</td>
                        </template>
                        <template x-if="editing !== {{ $courier->id }}">
                            <td class="px-6 py-3 text-center text-sm text-gray-500">{{ $courier->sort_order }}</td>
                        </template>
                        <template x-if="editing !== {{ $courier->id }}">
                            <td class="px-6 py-3 text-center">
                                @if ($courier->is_active)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800">Active</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-500">Inactive</span>
                                @endif
                            </td>
                        </template>
                        <template x-if="editing !== {{ $courier->id }}">
                            <td class="px-6 py-3 text-right text-sm">
                                <button type="button" @click="editing = {{ $courier->id }}" class="text-indigo-600 hover:text-indigo-900 mr-2">Edit</button>
                                <form method="POST" action="{{ route('admin.shipping-couriers.destroy', $courier) }}" class="inline" onsubmit="return confirm('Delete this courier?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                </form>
                            </td>
                        </template>

                        {{-- Edit mode --}}
                        <template x-if="editing === {{ $courier->id }}">
                            <td colspan="5" class="px-6 py-3">
                                <form method="POST" action="{{ route('admin.shipping-couriers.update', $courier) }}" class="space-y-3">
                                    @csrf @method('PUT')
                                    <div class="flex items-center gap-3">
                                        <input type="text" name="name" value="{{ $courier->name }}" required placeholder="Courier name" class="rounded-lg border-gray-300 text-sm w-40 focus:border-indigo-500 focus:ring-indigo-500">
                                        <input type="number" name="sort_order" value="{{ $courier->sort_order }}" min="0" class="rounded-lg border-gray-300 text-sm w-16 focus:border-indigo-500 focus:ring-indigo-500">
                                        <label class="flex items-center gap-1 text-xs text-gray-600">
                                            <input type="hidden" name="is_active" value="0">
                                            <input type="checkbox" name="is_active" value="1" {{ $courier->is_active ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                                            Active
                                        </label>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <input type="text" name="tracking_url" value="{{ $courier->tracking_url }}" placeholder="https://example.com/track/{tracking_number}" class="flex-1 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-xs">
                                        <button type="submit" class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700">Save</button>
                                        <button type="button" @click="editing = null" class="px-3 py-1.5 bg-gray-200 text-gray-700 text-xs font-medium rounded-lg hover:bg-gray-300">Cancel</button>
                                    </div>
                                </form>
                            </td>
                        </template>
                    </tr>
                @endforeach

                @if ($couriers->isEmpty())
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-400">No couriers configured.</td>
                    </tr>
                @endif
            </tbody>
        </table>

        {{-- Add New --}}
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
            <form method="POST" action="{{ route('admin.shipping-couriers.store') }}" class="space-y-3">
                @csrf
                <div class="flex items-center gap-3">
                    <span class="text-xs font-medium text-gray-500 uppercase tracking-wider mr-2">Add</span>
                    <input type="text" name="name" required placeholder="e.g. GoGo Express" class="rounded-lg border-gray-300 text-sm w-40 focus:border-indigo-500 focus:ring-indigo-500">
                    <input type="number" name="sort_order" value="0" min="0" class="rounded-lg border-gray-300 text-sm w-16 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Order">
                    <label class="flex items-center gap-1 text-xs text-gray-600">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300 text-indigo-600">
                        Active
                    </label>
                </div>
                <div class="flex items-center gap-3">
                    <input type="text" name="tracking_url" placeholder="https://example.com/track/{tracking_number}" class="flex-1 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-xs">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700 uppercase tracking-wide">Add Courier</button>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>
