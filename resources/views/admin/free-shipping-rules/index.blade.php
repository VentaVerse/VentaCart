<x-admin-layout>
    <x-slot name="header">Free Shipping Rules</x-slot>
    <x-slot name="headerActions">
        <a href="{{ route('admin.free-shipping-rules.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Rule
        </a>
    </x-slot>

    <div class="mb-4">
        <a href="{{ route('admin.shipping-settings.index', ['tab' => 'free-shipping']) }}" class="text-sm text-indigo-600 hover:text-indigo-800">Back to Shipping</a>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rule</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Conditions</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse ($rules as $rule)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900">{{ $rule->name }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-xs text-gray-500 max-w-md">{{ $rule->getConditionsSummary() }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $rule->sort_order }}</td>
                        <td class="px-6 py-4">
                            <form method="POST" action="{{ route('admin.free-shipping-rules.toggle', $rule) }}" class="inline">
                                @csrf
                                <button type="submit" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $rule->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $rule->is_active ? 'Active' : 'Disabled' }}
                                </button>
                            </form>
                        </td>
                        <td class="px-6 py-4 text-right text-sm">
                            <a href="{{ route('admin.free-shipping-rules.edit', $rule) }}" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</a>
                            <form method="POST" action="{{ route('admin.free-shipping-rules.destroy', $rule) }}" class="inline" onsubmit="return confirm('Delete this rule?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                            No free shipping rules. <a href="{{ route('admin.free-shipping-rules.create') }}" class="text-indigo-600 hover:underline">Create your first rule</a>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
