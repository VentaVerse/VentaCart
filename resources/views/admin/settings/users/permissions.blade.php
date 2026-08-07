<x-admin.settings-shell title="Role Permissions" :wide="true">
        <x-admin.card>
            <form method="POST" action="{{ route('admin.permissions.update') }}" data-v-form>
                @csrf

                <x-admin.table>
                    <x-slot:head>
                        <x-admin.table.th>Permission</x-admin.table.th>
                        @foreach ($roles as $role)
                            <x-admin.table.th align="right">{{ $role->name }}</x-admin.table.th>
                        @endforeach
                    </x-slot:head>
                    @foreach ($groups as $group => $perms)
                        <tr class="bg-v-sunken">
                            <td colspan="{{ $roles->count() + 1 }}" class="px-4 py-2">
                                <span class="text-[10px] font-semibold uppercase tracking-widest text-v-muted">{{ ucfirst($group) }}</span>
                            </td>
                        </tr>
                        @foreach ($perms as $permission)
                            <tr class="v-row">
                                <td class="px-4 py-2 pl-8 text-[13px] text-v-text">{{ $permission->name }}</td>
                                @foreach ($roles as $role)
                                    <td class="px-4 py-2 text-right">
                                        <x-admin.checkbox :name="'permissions[' . $role->id . '][]'" :value="$permission->id"
                                            :checked="$role->permissions->contains($permission->id)" />
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                </x-admin.table>

                <x-admin.form-actions>
                    <x-admin.button type="submit">Save Permissions</x-admin.button>
                </x-admin.form-actions>
            </form>
        </x-admin.card>
</x-admin.settings-shell>
