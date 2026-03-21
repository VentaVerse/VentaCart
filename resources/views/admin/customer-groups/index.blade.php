<x-admin-layout>
    <x-slot name="header">Customer Groups</x-slot>
    <x-slot name="headerActions">
        <a href="{{ route('admin.customer-groups.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Group
        </a>
    </x-slot>

    {{-- Table --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Group</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customers</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Default</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse ($groups as $group)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900">{{ $group->name }}</div>
                            @if ($group->description)
                                <div class="text-xs text-gray-400">{{ Str::limit($group->description, 60) }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $group->customers_count }}</td>
                        <td class="px-6 py-4">
                            @if ($group->is_default)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Default</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right text-sm">
                            <a href="{{ route('admin.customer-groups.edit', $group) }}" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</a>
                            @if ($group->customers_count === 0)
                                <form method="POST" action="{{ route('admin.customer-groups.destroy', $group) }}" class="inline" onsubmit="return confirm('Delete this group?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                </form>
                            @else
                                <span class="text-xs text-gray-400" title="Cannot delete — group has customers">{{ $group->customers_count }} customer{{ $group->customers_count > 1 ? 's' : '' }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                            No customer groups found. <a href="{{ route('admin.customer-groups.create') }}" class="text-indigo-600 hover:underline">Create your first group</a>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($groups->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $groups->links() }}
            </div>
        @endif
    </div>
</x-admin-layout>
