<x-admin-layout>
    <x-slot name="header">Order Statuses</x-slot>

    @if (session('success'))
        <div class="mb-4 bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
            <p class="text-xs text-gray-500">Manage the lifecycle stages of an order. <strong>Stock Action</strong> controls inventory: <em>Decrement</em> reduces stock, <em>Increment</em> restores it (for cancellations/returns).</p>
        </div>

        <table class="min-w-full divide-y divide-gray-200" x-data="{ editing: null }">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock Action</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Default</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-40">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach ($statuses as $status)
                    <tr class="hover:bg-gray-50">
                        {{-- Read mode --}}
                        <template x-if="editing !== {{ $status->id }}">
                            <td class="px-6 py-3 text-sm font-medium text-gray-900" colspan="0">{{ $status->name }}</td>
                        </template>
                        <template x-if="editing !== {{ $status->id }}">
                            <td class="px-6 py-3 text-sm">
                                @if ($status->stock_action === 'decrement')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">Decrement</span>
                                @elseif ($status->stock_action === 'increment')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">Increment</span>
                                @else
                                    <span class="text-gray-400">None</span>
                                @endif
                            </td>
                        </template>
                        <template x-if="editing !== {{ $status->id }}">
                            <td class="px-6 py-3 text-sm text-center text-gray-500">{{ $status->sort_order }}</td>
                        </template>
                        <template x-if="editing !== {{ $status->id }}">
                            <td class="px-6 py-3 text-center">
                                @if ($status->is_default)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Default</span>
                                @endif
                            </td>
                        </template>
                        <template x-if="editing !== {{ $status->id }}">
                            <td class="px-6 py-3 text-right text-sm">
                                <button type="button" @click="editing = {{ $status->id }}" class="text-indigo-600 hover:text-indigo-900 mr-2">Edit</button>
                                @unless ($status->is_default)
                                    <form method="POST" action="{{ route('admin.order-statuses.destroy', $status) }}" class="inline" onsubmit="return confirm('Delete this status?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                    </form>
                                @endunless
                            </td>
                        </template>

                        {{-- Edit mode (using x-show on a separate row to avoid single-cell colspan issues) --}}
                        <template x-if="editing === {{ $status->id }}">
                            <td colspan="5" class="px-0 py-0">
                                <form method="POST" action="{{ route('admin.order-statuses.update', $status) }}">
                                    @csrf @method('PUT')
                                    <table class="min-w-full">
                                        <tr class="bg-indigo-50/50">
                                            <td class="px-6 py-3">
                                                <input type="text" name="name" value="{{ $status->name }}" required class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            </td>
                                            <td class="px-6 py-3">
                                                <select name="stock_action" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                    <option value="">None</option>
                                                    <option value="decrement" {{ $status->stock_action === 'decrement' ? 'selected' : '' }}>Decrement</option>
                                                    <option value="increment" {{ $status->stock_action === 'increment' ? 'selected' : '' }}>Increment</option>
                                                </select>
                                            </td>
                                            <td class="px-6 py-3 text-center">
                                                <input type="number" name="sort_order" value="{{ $status->sort_order }}" min="0" class="w-16 rounded-lg border-gray-300 text-sm text-center focus:border-indigo-500 focus:ring-indigo-500 mx-auto">
                                            </td>
                                            <td class="px-6 py-3 text-center">
                                                <input type="hidden" name="is_default" value="0">
                                                <input type="checkbox" name="is_default" value="1" {{ $status->is_default ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                                            </td>
                                            <td class="px-6 py-3 text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <button type="submit" class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700">Save</button>
                                                    <button type="button" @click="editing = null" class="px-3 py-1.5 bg-gray-200 text-gray-700 text-xs font-medium rounded-lg hover:bg-gray-300">Cancel</button>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                </form>
                            </td>
                        </template>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Add New --}}
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
            <form method="POST" action="{{ route('admin.order-statuses.store') }}" class="flex items-center gap-3">
                @csrf
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wider mr-2">Add</span>
                <input type="text" name="name" required placeholder="Status name" class="rounded-lg border-gray-300 text-sm w-40 focus:border-indigo-500 focus:ring-indigo-500">
                <select name="stock_action" class="rounded-lg border-gray-300 text-sm w-32 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">No stock action</option>
                    <option value="decrement">Decrement</option>
                    <option value="increment">Increment</option>
                </select>
                <input type="number" name="sort_order" value="0" min="0" class="rounded-lg border-gray-300 text-sm w-16 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Order">
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700 uppercase tracking-wide">Add Status</button>
            </form>
        </div>
    </div>
</x-admin-layout>
