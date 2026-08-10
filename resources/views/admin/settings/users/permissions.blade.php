<x-admin.settings-shell title="Role Permissions">
        <x-admin.card>
            <form method="POST" action="{{ route('admin.permissions.update') }}" data-v-form>
                @csrf

                <x-admin.table ruled>
                    <x-slot:head>
                        <x-admin.table.th>Permission</x-admin.table.th>
                        @foreach ($roles as $role)
                            <x-admin.table.th align="right">{{ $role->name }}</x-admin.table.th>
                        @endforeach
                    </x-slot:head>
                    @foreach ($groups as $group => $perms)
                        <tr class="v-tier">
                            <td colspan="{{ $roles->count() + 1 }}">
                                <div class="v-tier__row">
                                    <div class="v-tier__left"><span class="v-tier__label">{{ ucfirst($group) }}</span></div>
                                </div>
                            </td>
                        </tr>
                        @foreach ($perms as $permission)
                            <x-admin.table.row>
                                <x-admin.table.cell class="pl-8 text-v-text">{{ $permission->name }}</x-admin.table.cell>
                                @foreach ($roles as $role)
                                    <x-admin.table.cell align="right">
                                        <x-admin.checkbox :name="'permissions[' . $role->id . '][]'" :value="$permission->id"
                                            :aria-label="$permission->name . ' for ' . $role->name"
                                            :checked="$role->permissions->contains($permission->id)" />
                                    </x-admin.table.cell>
                                @endforeach
                            </x-admin.table.row>
                        @endforeach
                    @endforeach
                </x-admin.table>

                <x-admin.form-actions>
                    <x-admin.button type="submit">Save Permissions</x-admin.button>
                </x-admin.form-actions>
            </form>
        </x-admin.card>
</x-admin.settings-shell>
