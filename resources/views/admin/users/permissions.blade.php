<x-admin-layout>
    <x-slot name="header">Role Permissions</x-slot>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <form method="POST" action="{{ route('admin.permissions.update') }}">
            @csrf

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Permission</th>
                            @foreach ($roles as $role)
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">{{ $role->name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($groups as $group => $perms)
                            <tr class="bg-gray-50/50">
                                <td colspan="{{ $roles->count() + 1 }}" class="px-4 py-2">
                                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ ucfirst($group) }}</span>
                                </td>
                            </tr>
                            @foreach ($perms as $permission)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 text-sm text-gray-700 pl-8">{{ $permission->name }}</td>
                                    @foreach ($roles as $role)
                                        <td class="px-4 py-2 text-center">
                                            <input type="checkbox"
                                                name="permissions[{{ $role->id }}][]"
                                                value="{{ $permission->id }}"
                                                {{ $role->permissions->contains($permission->id) ? 'checked' : '' }}
                                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-gray-200">
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save Permissions</button>
            </div>
        </form>
    </div>
</x-admin-layout>
